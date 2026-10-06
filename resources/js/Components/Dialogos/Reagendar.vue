<script setup>
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

watch(visible, (v) => {
    if (!v) return;
    f.value = { doctor_id: a.value.doctor_id, date: a.value.date < hoyISO() ? hoyISO() : a.value.date, time: '', reason: 'Solicitud del paciente' };
    cargar();
});
watch(() => [f.value.doctor_id, f.value.date], () => { if (visible.value) { f.value.time = ''; cargar(); } });

async function cargar() {
    if (!f.value.doctor_id || !f.value.date) return;
    const r = await api.get(route('api.agenda.horarios'), { doctor_id: f.value.doctor_id, date: f.value.date });
    // El horario actual de la cita no se ofrece como opción.
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
    <Dialog v-model:visible="visible" modal header="Reagendar cita" :style="{ width: '760px' }" :breakpoints="{ '800px': '96vw' }">
        <p class="muted">{{ a.patient }}. Actual: {{ fmtDL(a.date) }} a las {{ a.time }}. Las respuestas de historia clínica y los anticipos se conservan.</p>
        <div class="grid2">
            <div class="field"><label>Médico</label><Select v-model="f.doctor_id" :options="medicos" option-label="name" option-value="id" /></div>
            <div class="field"><label>Nueva fecha</label><Fecha v-model="f.date" :min="hoyISO()" /></div>
        </div>
        <div class="field">
            <label>Horarios disponibles</label>
            <div class="times">
                <button v-for="t in horas" :key="t" type="button" :class="{ on: f.time === t }" @click="f.time = t">{{ t }}</button>
                <span v-if="!horas.length" class="muted small">Sin espacios este día. <a href="#" @click.prevent="proxima">Buscar la próxima fecha disponible</a></span>
            </div>
        </div>
        <div class="field"><label for="rs-why">Motivo del cambio</label><InputText id="rs-why" v-model="f.reason" /></div>
        <template #footer>
            <Button label="Cancelar" text @click="ui.cerrar()" />
            <Button label="Confirmar nuevo horario" icon="pi pi-check" :loading="guardando" @click="guardar" />
        </template>
    </Dialog>
</template>
