<script setup>
import Modal from '@/Components/Modal.vue';
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DateNav from '@/Components/DateNav.vue';
import Fecha from '@/Components/Fecha.vue';
import Hora from '@/Components/Hora.vue';
import PacienteBuscador from '@/Components/PacienteBuscador.vue';
import { ui, usePermisos } from '@/lib/ui';
import { api, avisar, refrescar, filtrar } from '@/lib/api';
import { mt, tm, fmtDL, money, hoyISO } from '@/lib/util';

const props = defineProps({ fecha: String, tipo: String, filas: Array, ahora: Number });
const page = usePage();
const { puede } = usePermisos();
const HPX = 84, TL0 = 7 * 60, TL1 = 21 * 60;
const horas = []; for (let m = TL0; m < TL1; m += 60) horas.push(m);
const X = (m) => ((Math.max(m, TL0) - TL0) / 60) * HPX;
const W = (a, b) => Math.max(((Math.min(b, TL1) - Math.max(a, TL0)) / 60) * HPX - 2, 6);
const ir = (c) => filtrar('espacios', { fecha: props.fecha, tipo: props.tipo, ...c });

// ---------- reservación ----------
const dlg = ref(false);
const r = ref({});
const paciente = ref(null);
const checks = ref([]);
const guardando = ref(false);
const medicos = computed(() => [{ id: null, name: 'Sin médico' }, ...page.props.opciones.medicos]);
const valida = computed(() => checks.value.length && checks.value.every((c) => c.ok) && r.value.procedure?.trim() && r.value.duration > 0);

function nueva(resourceId = null, start = null) {
    if (!puede('espacio')) return avisar('Tu rol no permite reservar espacios.', 'error');
    r.value = { id: null, resource_id: resourceId || page.props.opciones.recursos.find((x) => x.type === 'Quirófano')?.id, date: props.fecha, start_time: start || '09:00', duration: 60, doctor_id: null, procedure: '', notes: '' };
    paciente.value = null;
    dlg.value = true;
}
async function editar(id) {
    const x = await api.get(route('api.reservaciones.show', id));
    r.value = { id: x.id, resource_id: x.resource_id, date: x.date_s, start_time: x.start_time, duration: x.duration, doctor_id: x.doctor_id, procedure: x.procedure, notes: x.notes || '' };
    paciente.value = x.patient ? { id: x.patient.id, name: x.patient.name, sigma_id: '', age: '', phone: '' } : null;
    ver.value = null;
    dlg.value = true;
}
let t = null;
watch(() => [dlg.value, r.value.resource_id, r.value.date, r.value.start_time, r.value.duration, r.value.doctor_id], () => {
    if (!dlg.value) return;
    clearTimeout(t);
    t = setTimeout(async () => { checks.value = await api.post(route('api.reservaciones.validar'), { ...r.value }, { silencioso: true }).catch(() => []); }, 200);
});
async function guardar() {
    guardando.value = true;
    try {
        const datos = { ...r.value, patient_id: paciente.value?.id || null };
        const res = r.value.id ? await api.put(route('api.reservaciones.update', r.value.id), datos) : await api.post(route('api.reservaciones.store'), datos);
        avisar(res.message);
        dlg.value = false;
        if (r.value.date !== props.fecha) ir({ fecha: r.value.date }); else refrescar();
    } finally { guardando.value = false; }
}
function clickPista(e, fila) {
    const rect = e.currentTarget.getBoundingClientRect();
    nueva(fila.id, mt(TL0 + Math.floor((((e.clientX - rect.left) / HPX) * 60) / 30) * 30));
}

// ---------- ver reservación ----------
const ver = ref(null);
async function abrir(id) { ver.value = await api.get(route('api.reservaciones.show', id)); }
async function cancelar() {
    const res = await api.post(route('api.reservaciones.cancelar', ver.value.id));
    avisar(res.message);
    ver.value = null;
    refrescar();
}
function clickItem(it) { if (it.kind === 'res') abrir(it.id); else if (it.kind === 'blk' && it.id) ui.abrir('bloqueoVer', { id: it.id }); }
</script>

<template>
    <AppLayout titulo="Quirófanos y espacios" subtitulo="Reservación de recursos físicos">
        <template #acciones>
            <Button v-if="puede('espacio')" label="Nueva reservación" icon="pi pi-plus" severity="secondary" outlined @click="nueva()" />
            <Button v-if="puede('bloqueo')" label="Bloquear espacio" icon="pi pi-ban" severity="secondary" outlined @click="ui.abrir('bloqueo', { scope: 'resource', date: fecha })" />
        </template>
        <div class="vh">
            <DateNav :fecha="fecha" @cambiar="ir({ fecha: $event })" />
            <div class="sp" />
            <Select :model-value="tipo" :options="page.props.catalogo.tiposRecurso" placeholder="Todos los espacios" show-clear @update:model-value="ir({ tipo: $event })" />
        </div>
        <p class="muted small"><i class="pi pi-info-circle" /> Haz clic en un espacio vacío de la línea de tiempo para reservar a esa hora. Antes de guardar se validan disponibilidad, horario, bloqueos y agenda del médico.</p>
        <div class="tl">
            <div class="tl-in">
                <div class="tl-row tl-head">
                    <div class="tl-lab"><small>Recurso</small></div>
                    <div class="tl-track"><span v-for="m in horas" :key="m" class="tl-hr" :style="{ left: X(m) + 'px' }">{{ mt(m) }}</span></div>
                </div>
                <div v-for="f in filas" :key="f.id" class="tl-row">
                    <div class="tl-lab"><b>{{ f.name }}{{ f.active ? '' : ' (inactivo)' }}</b><small>{{ f.type }}, {{ f.location }}</small></div>
                    <div class="tl-track" @click="clickPista($event, f)">
                        <div v-if="f.opens > TL0" class="tl-it blk inact" :style="{ left: 0, width: W(TL0, f.opens) + 'px' }" @click.stop>Cerrado</div>
                        <div v-if="f.closes < TL1" class="tl-it blk inact" :style="{ left: X(f.closes) + 'px', width: W(f.closes, TL1) + 'px' }" @click.stop>Cerrado</div>
                        <div v-for="(it, i) in f.items" :key="i" class="tl-it" :class="it.kind" :style="{ left: X(it.start) + 'px', width: W(it.start, it.end) + 'px' }" :title="it.label" @click.stop="clickItem(it)">
                            {{ it.label }}<small v-if="it.sub">{{ it.sub }}</small>
                        </div>
                        <div v-if="fecha === hoyISO() && ahora > TL0 && ahora < TL1" :style="{ position: 'absolute', top: 0, bottom: 0, left: X(ahora) + 'px', borderLeft: '2px solid var(--now)' }" />
                    </div>
                </div>
            </div>
        </div>

        <Modal icono="pi pi-building" color="#6b4bc9" subtitulo="Se valida disponibilidad, horario, bloqueos y agenda del médico" v-model:visible="dlg" :header="r.id ? 'Editar reservación' : 'Nueva reservación de espacio'" ancho="820px">
            <div class="grid2">
                <div class="field"><label>Paciente</label><PacienteBuscador v-model="paciente" opcional :permitir-alta="false" /></div>
                <div class="stack" style="align-content: start">
                    <div class="field"><label>Médico</label><Select v-model="r.doctor_id" :options="medicos" option-label="name" option-value="id" filter /></div>
                    <div class="field"><label for="rv-pr">Procedimiento o actividad</label><InputText id="rv-pr" v-model="r.procedure" placeholder="Ej. artroscopia de rodilla" /></div>
                </div>
            </div>
            <div class="grid4">
                <div class="field"><label>Recurso</label><Select v-model="r.resource_id" :options="page.props.opciones.recursos" option-label="name" option-value="id" /></div>
                <div class="field"><label>Fecha</label><Fecha v-model="r.date" /></div>
                <div class="field"><label>Hora</label><Hora v-model="r.start_time" :step="900" /></div>
                <div class="field"><label>Duración (min)</label><InputNumber v-model="r.duration" :min="15" :step="15" show-buttons fluid /></div>
            </div>
            <div class="field"><label for="rv-n">Notas</label><InputText id="rv-n" v-model="r.notes" /></div>
            <div>
                <div class="sec-t">Validación</div>
                <div class="checks">
                    <div v-for="(c, i) in checks" :key="i" :class="c.ok ? 'y' : 'x'"><i class="pi" :class="c.ok ? 'pi-check' : 'pi-times'" /><span>{{ c.t }}</span></div>
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" text @click="dlg = false" />
                <Button :label="r.id ? 'Guardar cambios' : 'Reservar espacio'" icon="pi pi-check" :disabled="!valida" :loading="guardando" @click="guardar" />
            </template>
        </Modal>

        <Modal icono="pi pi-building" color="#6b4bc9" subtitulo="Reservación de espacio" :visible="!!ver" :header="ver?.procedure" ancho="520px" @update:visible="ver = null">
            <dl v-if="ver" class="kv">
                <dt>Recurso</dt><dd>{{ ver.resource.name }}, {{ ver.resource.location }}</dd>
                <dt>Fecha</dt><dd>{{ fmtDL(ver.date_s) }}</dd>
                <dt>Horario</dt><dd>{{ ver.start_time }} a {{ ver.end_time }} ({{ ver.duration }} min)</dd>
                <dt>Médico</dt><dd>{{ ver.doctor?.name || '—' }}</dd>
                <dt>Paciente</dt><dd>{{ ver.patient?.name || '—' }}</dd>
                <template v-if="ver.resource.cost"><dt>Costo del recurso</dt><dd>{{ money(ver.resource.cost) }}</dd></template>
                <template v-if="ver.notes"><dt>Notas</dt><dd>{{ ver.notes }}</dd></template>
                <dt>Registró</dt><dd>{{ ver.created_by }}</dd>
            </dl>
            <template #footer>
                <template v-if="puede('espacio')">
                    <Button label="Cancelar reservación" severity="danger" outlined @click="cancelar" />
                    <Button label="Editar" severity="secondary" outlined @click="editar(ver.id)" />
                </template>
                <Button v-else label="Cerrar" severity="secondary" outlined @click="ver = null" />
            </template>
        </Modal>
    </AppLayout>
</template>
