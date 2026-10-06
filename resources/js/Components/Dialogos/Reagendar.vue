<script setup>
import Modal from '@/Components/Modal.vue';
import SeccionModal from '@/Components/SeccionModal.vue';
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ui } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';
import { fmtDL, hoyISO, addDays } from '@/lib/util';
import Fecha from '@/Components/Fecha.vue';

const page = usePage();
const visible = computed({ get: () => ui.dialogo === 'reagendar', set: (v) => { if (!v) ui.cerrar(); } });
const a = computed(() => ui.datos.cita || {});
const medicos = computed(() => page.props.opciones.medicos.filter((d) => d.active && d.specialty_id === a.value.specialty_id));
const f = ref({});
const horas = ref([]);
const guardando = ref(false);
const turnos = computed(() => [
    { l: 'Mañana', i: 'pi pi-sun', h: horas.value.filter((x) => x < '14:00') },
    { l: 'Tarde', i: 'pi pi-moon', h: horas.value.filter((x) => x >= '14:00') },
].filter((g) => g.h.length));

watch(visible, (v) => {
    if (!v) return;
    f.value = { doctor_id: a.value.doctor_id, date: a.value.date < hoyISO() ? hoyISO() : a.value.date, time: '', reason: 'Solicitud del paciente' };
    cargar();
});
watch(() => [f.value.doctor_id, f.value.date], () => { if (visible.value) { f.value.time = ''; cargar(); } });

async function cargar() {
    if (!f.value.doctor_id || !f.value.date) return;
    const r = await api.get(route('api.agenda.horarios'), { doctor_id: f.value.doctor_id, date: f.value.date });
    horas.value = r.times;
}
async function proxima() {
    const r = await api.get(route('api.agenda.proxima'), { doctor_id: f.value.doctor_id, from: addDays(f.value.date, 1) });
    f.value.date = r.date;
}
async function guardar() {
    if (!f.value.time) return avisar('Elige un horario.', 'error');
    guardando.value = true;
    try {
        const r = await api.post(route('api.citas.reagendar', a.value.id), f.value);
        refrescar();
        ui.abrirCita(r.id);
        if (r.liberado && page.props.auth.user.acciones.includes('cita')) ui.abrir('liberado', r.liberado);
        else ui.cerrar();
        avisar(r.message);
    } finally { guardando.value = false; }
}
</script>

<template>
    <Modal v-model:visible="visible" titulo="Reagendar cita" :subtitulo="a.patient ? `${a.patient} · se conservan anticipos, respuestas e historial` : ''" icono="pi pi-calendar" color="#2b5480" ancho="820px">
        <div class="note"><i class="pi pi-clock" /> Horario actual: <b>{{ fmtDL(a.date) }}, {{ a.time }}</b> con {{ a.doctor }}</div>
        <SeccionModal titulo="Nuevo horario" icono="pi pi-calendar-clock" color="#1f9e86">
            <div class="grid2">
                <div class="field"><label>Médico</label><Select v-model="f.doctor_id" :options="medicos" option-label="name" option-value="id" fluid /></div>
                <div class="field"><label>Nueva fecha</label><Fecha v-model="f.date" :min="hoyISO()" /></div>
            </div>
            <div v-if="turnos.length" class="turnos">
                <div v-for="g in turnos" :key="g.l">
                    <div class="turno-t"><i :class="g.i" />{{ g.l }} · {{ g.h.length }}</div>
                    <div class="slots-grid"><button v-for="t in g.h" :key="t" type="button" :class="{ on: f.time === t }" @click="f.time = t">{{ t }}</button></div>
                </div>
            </div>
            <div v-else class="note warn small"><i class="pi pi-calendar-times" /> Sin espacios este día. <a href="#" style="font-weight: 700" @click.prevent="proxima">Buscar la próxima fecha disponible →</a></div>
        </SeccionModal>
        <SeccionModal titulo="Motivo del cambio" icono="pi pi-comment" color="#6b4bc9">
            <div class="campo-ic"><i class="pi pi-pencil" /><InputText v-model="f.reason" fluid /></div>
        </SeccionModal>
        <template #footer>
            <Button label="Cancelar" text @click="ui.cerrar()" />
            <Button label="Confirmar nuevo horario" icon="pi pi-check" :disabled="!f.time" :loading="guardando" @click="guardar" />
        </template>
    </Modal>
</template>
