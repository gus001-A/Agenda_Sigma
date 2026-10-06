<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Segmento from '@/Components/Segmento.vue';
import Chip from '@/Components/Chip.vue';
import DateNav from '@/Components/DateNav.vue';
import Kpi from '@/Components/Kpi.vue';
import TablaDatos from '@/Components/TablaDatos.vue';
import Accion from '@/Components/Accion.vue';
import { ui, usePermisos } from '@/lib/ui';
import { filtrar } from '@/lib/api';

const props = defineProps({ fecha: String, filtros: Object, filas: Array, inactivas: Array });
const { puede } = usePermisos();
const vista = ref('agenda');
const ir = (c) => filtrar('recepcion', { fecha: props.fecha, ocultar: props.filtros.ocultar ? 1 : null, ...c });
const unicos = (l, k) => [...new Set(l.map((x) => x[k]).filter(Boolean))].sort();

const filas = computed(() => props.filas.map((r, i) => ({
    ...r, _id: i, paciente: r.appt?.patient || '', hc: r.appt?.hc_status || '', anticipo: r.appt?.deposit_state || '',
})));
const columnas = computed(() => [
    { campo: 'time', titulo: 'Hora', filtro: 'texto', ancho: '90px' },
    { campo: 'paciente', titulo: 'Paciente', filtro: 'texto', ancho: '200px' },
    { campo: 'specialty', titulo: 'Especialidad', filtro: 'lista', opciones: unicos(filas.value, 'specialty') },
    { campo: 'doctor', titulo: 'Médico', filtro: 'lista', opciones: unicos(filas.value, 'doctor'), ancho: '200px' },
    { campo: 'state', titulo: 'Estado', filtro: 'lista', opciones: unicos(filas.value, 'state') },
    { campo: 'hc', titulo: 'Historia clínica', filtro: 'lista', opciones: unicos(filas.value, 'hc') },
    { campo: 'anticipo', titulo: 'Anticipo', filtro: 'lista', opciones: unicos(filas.value, 'anticipo') },
    { campo: 'accion', titulo: 'Acción', ordenar: false, ancho: '120px' },
]);
const colInactivas = computed(() => [
    { campo: 'time', titulo: 'Hora', filtro: 'texto', ancho: '90px' },
    { campo: 'patient', titulo: 'Paciente', filtro: 'texto' },
    { campo: 'doctor', titulo: 'Médico', filtro: 'lista', opciones: unicos(props.inactivas, 'doctor') },
    { campo: 'status', titulo: 'Estado', filtro: 'lista', opciones: ['Cancelada', 'Reagendada'] },
    { campo: 'last', titulo: 'Último movimiento', filtro: 'texto' },
]);
const claseFila = (r) => (r.state === 'Disponible' ? 'fila-libre' : r.state === 'Bloqueada' ? 'fila-bloq' : r.state === 'Anticipo pendiente' ? 'fila-alerta' : '');
const conteo = (estados) => props.filas.filter((r) => estados.includes(r.state)).length;
</script>

<template>
    <AppLayout titulo="Recepción del día" subtitulo="Pantalla operativa de citas">
        <div class="vh">
            <DateNav :fecha="fecha" @cambiar="ir({ fecha: $event })" />
            <div class="sp" />
            <label class="chk"><Checkbox :model-value="filtros.ocultar" binary @update:model-value="ir({ ocultar: $event ? 1 : null })" />Ocultar espacios libres</label>
            <Segmento v-model="vista" :opciones="[{ l: 'Agenda del día', v: 'agenda', i: 'pi pi-calendar', n: filas.length }, { l: 'Canceladas y reagendadas', v: 'inactivas', i: 'pi pi-times-circle', n: inactivas.length }]" />
        </div>
        <div class="kpis linea">
            <Kpi etiqueta="Disponibles" :valor="conteo(['Disponible'])" icono="pi pi-calendar-plus" color="#1f9e86" />
            <Kpi etiqueta="Agendadas" :valor="conteo(['Agendada'])" icono="pi pi-bookmark" color="#2b5480" />
            <Kpi etiqueta="Confirmadas" :valor="conteo(['Confirmada'])" icono="pi pi-check-circle" color="#0e7490" />
            <Kpi etiqueta="Anticipo pendiente" :valor="conteo(['Anticipo pendiente'])" icono="pi pi-wallet" color="#e08a1e" />
            <Kpi etiqueta="En consulta / atendidas" :valor="conteo(['En consulta', 'Atendida'])" icono="pi pi-heart" color="#6b4bc9" />
            <Kpi etiqueta="Bloqueadas" :valor="conteo(['Bloqueada'])" icono="pi pi-ban" color="#64748b" />
        </div>

        <TablaDatos v-if="vista === 'agenda'" :filas="filas" :columnas="columnas" clave="_id" clicable :clase-fila="claseFila" vacio="Sin agenda para este día"
            @fila="(r) => r.appt && ui.abrirCita(r.appt.id)">
            <template #c-time="{ fila }"><b>{{ fila.time }}</b></template>
            <template #c-paciente="{ fila }">
                <span v-if="fila.appt" style="font-weight: 600">{{ fila.paciente }}</span><span v-else class="muted">—</span>
                <span v-if="fila.off" class="small" style="color: var(--danger)"> fuera de horario</span>
            </template>
            <template #c-state="{ fila }"><Chip :valor="fila.state" /> <span v-if="fila.block" class="muted small">{{ fila.block.type }}</span></template>
            <template #c-hc="{ fila }"><Chip v-if="fila.appt" :valor="fila.hc" tipo="hc" /><span v-else class="muted">—</span></template>
            <template #c-anticipo="{ fila }"><Chip v-if="fila.appt" :valor="fila.anticipo" tipo="dep" /><span v-else class="muted">—</span></template>
            <template #c-accion="{ fila }">
                <div class="accs">
                    <Accion v-if="fila.state === 'Disponible' && puede('cita')" icono="pi pi-calendar-plus" texto="Agendar" color="verde" @click="ui.abrir('nuevaCita', { doctor_id: fila.doctor_id, date: fecha, time: fila.time })" />
                    <Accion v-else-if="fila.state === 'Bloqueada'" icono="pi pi-lock" tip="Administrar bloqueo" color="gris" @click="ui.abrir('bloqueoVer', { id: fila.block.id })" />
                    <template v-else-if="fila.appt">
                        <Accion icono="pi pi-eye" tip="Ver detalle de la cita" color="azul" @click="ui.abrirCita(fila.appt.id)" />
                        <Accion v-if="fila.appt.debt > 0 && puede('anticipo')" icono="pi pi-wallet" tip="Cobrar anticipo" color="ambar" @click="ui.abrirCita(fila.appt.id)" />
                    </template>
                </div>
            </template>
        </TablaDatos>
        <TablaDatos v-else :filas="inactivas" :columnas="colInactivas" clicable vacio="Sin citas canceladas ni reagendadas este día" @fila="(a) => ui.abrirCita(a.id)">
            <template #c-status="{ fila }"><Chip :valor="fila.status" /></template>
            <template #c-last="{ fila }"><span class="muted small">{{ fila.last }}</span></template>
        </TablaDatos>
    </AppLayout>
</template>
