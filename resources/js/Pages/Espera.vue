<script setup>
import Modal from '@/Components/Modal.vue';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import Kpi from '@/Components/Kpi.vue';
import TablaDatos from '@/Components/TablaDatos.vue';
import Accion from '@/Components/Accion.vue';
import { ui, usePermisos } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';
import { fmtD } from '@/lib/util';

const props = defineProps({ lista: Array });
const { puede } = usePermisos();
const buscando = ref(null);
const espacios = ref([]);
const unicos = (k) => [...new Set(props.lista.map((x) => x[k]).filter(Boolean))].sort();
const filas = computed(() => props.lista.map((w) => ({ ...w, medico: w.doctor || 'Cualquiera', solicitud: fmtD(w.requested_at) })));
const columnas = computed(() => [
    { campo: 'patient', titulo: 'Paciente', filtro: 'texto', ancho: '220px' },
    { campo: 'specialty', titulo: 'Especialidad', filtro: 'lista', opciones: unicos('specialty') },
    { campo: 'medico', titulo: 'Médico', filtro: 'lista', opciones: [...new Set(filas.value.map((x) => x.medico))].sort() },
    { campo: 'solicitud', titulo: 'Solicitud', filtro: 'texto', ancho: '120px' },
    { campo: 'preference', titulo: 'Preferencia', filtro: 'lista', opciones: ['Cualquier horario', 'Mañana', 'Tarde'] },
    { campo: 'priority', titulo: 'Prioridad', filtro: 'lista', opciones: ['Alta', 'Media', 'Baja'] },
    { campo: 'status', titulo: 'Estado', filtro: 'lista', opciones: ['En espera', 'Contactado', 'Agendado', 'Descartado'] },
    { campo: 'acciones', titulo: 'Acciones', ordenar: false, ancho: '230px' },
]);
const COL_PRIO = { Alta: '#d64545', Media: '#e08a1e', Baja: '#1f9e86' };
const cuenta = (s) => props.lista.filter((w) => w.status === s).length;

async function estado(w, status) { await api.patch(route('api.espera.estado', w.id), { status }); avisar('Estado actualizado'); refrescar(); }
async function buscar(w) { espacios.value = await api.get(route('api.espera.espacios', w.id)); buscando.value = w; }
function agendar(x, t) {
    const w = buscando.value;
    buscando.value = null;
    ui.abrir('nuevaCita', { doctor_id: x.doctor_id, date: x.date, time: t, waitlist_id: w.id, patient_id: w.patient_id, reason: w.notes });
}
</script>

<template>
    <AppLayout titulo="Lista de espera" subtitulo="Cuando se cancela o reagenda una cita, el sistema propone a los pacientes compatibles">
        <template #acciones><Button v-if="puede('cita')" label="Agregar paciente" icon="pi pi-user-plus" severity="secondary" outlined @click="ui.abrir('nuevaEspera', {})" /></template>
        <div class="kpis linea">
            <Kpi etiqueta="En espera" :valor="cuenta('En espera')" icono="pi pi-clock" color="#e08a1e" />
            <Kpi etiqueta="Contactados" :valor="cuenta('Contactado')" icono="pi pi-phone" color="#2b5480" />
            <Kpi etiqueta="Agendados" :valor="cuenta('Agendado')" icono="pi pi-calendar-plus" color="#1f9e86" />
            <Kpi etiqueta="Prioridad alta" :valor="lista.filter((w) => w.priority === 'Alta' && ['En espera', 'Contactado'].includes(w.status)).length" icono="pi pi-exclamation-triangle" color="#d64545" />
        </div>
        <TablaDatos :filas="filas" :columnas="columnas" vacio="No hay pacientes en la lista de espera">
            <template #c-patient="{ fila }"><b style="font-weight: 600">{{ fila.patient }}</b><div v-if="fila.notes" class="muted small">{{ fila.notes }}</div></template>
            <template #c-medico="{ fila }"><span :class="{ muted: !fila.doctor }">{{ fila.medico }}</span></template>
            <template #c-priority="{ fila }"><span class="chip" :style="{ '--sb': COL_PRIO[fila.priority] + '1a', '--sf': COL_PRIO[fila.priority] }">{{ fila.priority }}</span></template>
            <template #c-status="{ fila }"><Chip :valor="fila.status" tipo="espera" /></template>
            <template #c-acciones="{ fila }">
                <div class="accs">
                    <template v-if="['En espera', 'Contactado'].includes(fila.status) && puede('cita')">
                        <Accion icono="pi pi-search" texto="Buscar espacio" color="verde" @click="buscar(fila)" />
                        <Accion :icono="fila.status === 'En espera' ? 'pi pi-phone' : 'pi pi-undo'" :tip="fila.status === 'En espera' ? 'Marcar contactado' : 'Regresar a espera'" color="azul" @click="estado(fila, fila.status === 'En espera' ? 'Contactado' : 'En espera')" />
                        <Accion icono="pi pi-trash" tip="Descartar" color="rojo" @click="estado(fila, 'Descartado')" />
                    </template>
                    <Accion v-else-if="fila.appointment_id" icono="pi pi-eye" tip="Ver cita" color="azul" @click="ui.abrirCita(fila.appointment_id)" />
                </div>
            </template>
        </TablaDatos>

        <Modal icono="pi pi-search" color="#1f9e86" subtitulo="Horarios compatibles con la preferencia del paciente" :visible="!!buscando" :header="buscando ? 'Espacios para ' + buscando.patient : ''" ancho="720px" @update:visible="buscando = null">
            <template v-if="buscando">
                <template v-if="espacios.length">
                    <p class="muted small">{{ buscando.specialty }}<template v-if="buscando.doctor">, {{ buscando.doctor }}</template>, preferencia: {{ buscando.preference.toLowerCase() }}.</p>
                    <div v-for="x in espacios" :key="x.date + x.doctor_id">
                        <b style="font-weight: 600"><i class="pi pi-calendar" style="color: var(--teal); font-size: 12px" /> {{ fmtD(x.date) }}</b> <span class="muted small">{{ x.doctor }}</span>
                        <div class="times" style="margin-top: 6px"><button v-for="t in x.times" :key="t" type="button" @click="agendar(x, t)">{{ t }}</button></div>
                    </div>
                </template>
                <div v-else class="empty"><b>Sin espacios compatibles en 45 días</b>El paciente seguirá en espera.</div>
            </template>
            <template #footer><Button label="Cerrar" text @click="buscando = null" /></template>
        </Modal>
    </AppLayout>
</template>
