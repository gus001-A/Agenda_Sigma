<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Fecha from '@/Components/Fecha.vue';
import TablaDatos from '@/Components/TablaDatos.vue';
import Persona from '@/Components/Persona.vue';
import { ui, usePermisos } from '@/lib/ui';
import { filtrar } from '@/lib/api';
import { fmtD, money, hoyISO } from '@/lib/util';

const props = defineProps({ filtros: Object, resultados: Array });
const page = usePage();
const { puede } = usePermisos();
const op = computed(() => page.props.opciones);
const medicos = computed(() => op.value.medicos.filter((d) => d.active && (!props.filtros.spec || d.specialty_id === props.filtros.spec)));
function ir(c) {
    const f = { ...props.filtros, ...c };
    if (c.spec !== undefined && f.doc && op.value.medicos.find((d) => d.id === f.doc)?.specialty_id !== c.spec) f.doc = null;
    filtrar('disponibilidad', { ...f, pref: f.pref === 'Cualquier horario' ? null : f.pref });
}
const agendar = (x, t) => puede('cita') && ui.abrir('nuevaCita', { doctor_id: x.doctor_id, date: x.date, time: t });
const filas = computed(() => props.resultados.map((x, i) => ({ ...x, _id: i, fecha: fmtD(x.date), espacios: x.times.length })));
const unicos = (k) => [...new Set(filas.value.map((x) => x[k]))].sort();
const columnas = computed(() => [
    { campo: 'fecha', titulo: 'Fecha', filtro: 'texto', ancho: '130px' },
    { campo: 'doctor', titulo: 'Médico', filtro: 'lista', opciones: unicos('doctor'), ancho: '230px' },
    { campo: 'specialty', titulo: 'Especialidad', filtro: 'lista', opciones: unicos('specialty') },
    { campo: 'espacios', titulo: 'Libres', alinear: 'right', ancho: '80px' },
    { campo: 'times', titulo: 'Horarios disponibles (clic para agendar)', ordenar: false },
]);
</script>

<template>
    <AppLayout titulo="Próxima disponibilidad" subtitulo="Primeras 8 fechas con espacio en los próximos 60 días">
        <div class="card pad barra-filtros">
            <div class="grid4">
                <div class="field"><label><i class="pi pi-bookmark" style="color: var(--teal)" /> Especialidad</label><Select :model-value="filtros.spec" :options="op.especialidades" option-label="name" option-value="id" placeholder="Cualquier especialidad" show-clear @update:model-value="ir({ spec: $event })" /></div>
                <div class="field"><label><i class="pi pi-user" style="color: var(--teal)" /> Médico</label><Select :model-value="filtros.doc" :options="medicos" option-label="name" option-value="id" placeholder="Cualquier médico" show-clear filter @update:model-value="ir({ doc: $event })" /></div>
                <div class="field"><label><i class="pi pi-calendar" style="color: var(--teal)" /> A partir de</label><Fecha :model-value="filtros.from" :min="hoyISO()" @update:model-value="ir({ from: $event })" /></div>
                <div class="field"><label><i class="pi pi-sun" style="color: var(--teal)" /> Preferencia</label><Select :model-value="filtros.pref" :options="['Cualquier horario', 'Mañana', 'Tarde']" @update:model-value="ir({ pref: $event })" /></div>
            </div>
        </div>
        <TablaDatos v-if="resultados.length" :filas="filas" :columnas="columnas" clave="_id">
            <template #c-fecha="{ fila }"><b style="font-weight: 600">{{ fila.fecha }}</b> <span v-if="fila.date === hoyISO()" class="tag">hoy</span></template>
            <template #c-doctor="{ fila }"><Persona :nombre="fila.doctor" :detalle="`${fila.duration} min · ${money(fila.cost)}`" /></template>
            <template #c-espacios="{ fila }"><span class="chip plain s-disp">{{ fila.espacios }}</span></template>
            <template #c-times="{ fila }">
                <div class="times">
                    <button v-for="t in fila.times.slice(0, 14)" :key="t" type="button" :class="t < '14:00' ? 'manana' : 'tarde'" :title="'Agendar ' + t" @click="agendar(fila, t)">{{ t }}</button>
                    <span v-if="fila.times.length > 14" class="muted small">+{{ fila.times.length - 14 }}</span>
                </div>
            </template>
        </TablaDatos>
        <div v-else class="card empty">
            <i class="pi pi-calendar-times" style="font-size: 30px; color: #b6c3d1" />
            <b style="margin-top: 6px">Sin espacios disponibles en los próximos 60 días</b>Agrega al paciente a la lista de espera para avisarle cuando se libere un horario.
            <div v-if="puede('cita')" style="margin-top: 12px"><Button label="Agregar a lista de espera" icon="pi pi-user-plus" @click="ui.abrir('nuevaEspera', { specialty_id: filtros.spec, doctor_id: filtros.doc })" /></div>
        </div>
    </AppLayout>
</template>
