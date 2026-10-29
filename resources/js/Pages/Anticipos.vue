<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import Segmento from '@/Components/Segmento.vue';
import Kpi from '@/Components/Kpi.vue';
import TablaDatos from '@/Components/TablaDatos.vue';
import Accion from '@/Components/Accion.vue';
import Persona from '@/Components/Persona.vue';
import Traza from '@/Components/Traza.vue';
import Fecha from '@/Components/Fecha.vue';
import { ui, usePermisos } from '@/lib/ui';
import { filtrar } from '@/lib/api';
import { fmtD, money, addDays, hoyISO } from '@/lib/util';

const props = defineProps({ estado: String, desde: String, hasta: String, kpis: Object, lista: Array });
// Búsqueda por fecha de la cita. "todas" = sin fecha inicial (el servidor lo interpreta como sin límite).
const ir = (c) => filtrar('anticipos', { estado: props.estado, desde: props.desde || 'todas', hasta: props.hasta, ...c });
const hoy = hoyISO();
const atajos = [
    { l: 'Hoy', d: hoy, h: hoy },
    { l: 'Últimos 7 días', d: addDays(hoy, -7), h: hoy },
    { l: 'Últimos 30 días', d: addDays(hoy, -30), h: null },
    { l: 'Próximos 30 días', d: hoy, h: addDays(hoy, 30) },
    { l: 'Todo', d: 'todas', h: null },
];
const atajo = computed(() => atajos.find((a) => (a.d === 'todas' ? !props.desde : a.d === props.desde) && (a.h || null) === (props.hasta || null))?.l);
const { puede } = usePermisos();
const unicos = (k) => [...new Set(props.lista.map((x) => x[k]).filter(Boolean))].sort();
const filas = computed(() => props.lista.map((a) => ({ ...a, fecha: `${fmtD(a.date)} ${a.time}` })));
const columnas = computed(() => [
    { campo: 'fecha', titulo: 'Fecha', filtro: 'texto', ancho: '140px' },
    { campo: 'patient', titulo: 'Paciente', filtro: 'texto', ancho: '200px' },
    { campo: 'doctor', titulo: 'Médico', filtro: 'lista', opciones: unicos('doctor'), ancho: '210px' },
    { campo: 'status', titulo: 'Estado de cita', filtro: 'lista', opciones: unicos('status') },
    { campo: 'deposit', titulo: 'Requerido', alinear: 'right' },
    { campo: 'paid', titulo: 'Pagado', alinear: 'right' },
    { campo: 'balance', titulo: 'Saldo', alinear: 'right' },
    { campo: 'deposit_state', titulo: 'Anticipo', filtro: 'lista', opciones: ['Pendiente', 'Parcial', 'Pagado', 'Reembolsado'] },
    { campo: 'created_by', titulo: 'Registró', filtro: 'texto', ancho: '150px' },
    { campo: 'accion', titulo: 'Acciones', ordenar: false, ancho: '190px' },
]);
</script>

<template>
    <AppLayout titulo="Anticipos" subtitulo="Pagos, saldos y reembolsos de citas">
        <div class="kpis linea">
            <Kpi etiqueta="Anticipos cobrados" :valor="money(kpis.cobrados)" icono="pi pi-check-circle" color="#16a34a" />
            <Kpi etiqueta="Pendientes por cobrar" :valor="money(kpis.pendientes)" icono="pi pi-hourglass" color="#e08a1e" />
            <Kpi etiqueta="Reembolsados" :valor="money(kpis.reembolsados)" icono="pi pi-replay" color="#8e3b69" />
            <Kpi etiqueta="Citas con anticipo" :valor="kpis.citas" icono="pi pi-wallet" color="#173a5f" />
        </div>
        <div class="vh">
            <div class="field" style="width: 170px"><label><i class="pi pi-calendar" style="color: var(--teal)" /> Desde</label><Fecha :model-value="desde" @update:model-value="ir({ desde: $event || 'todas' })" /></div>
            <div class="field" style="width: 170px"><label><i class="pi pi-calendar" style="color: var(--teal)" /> Hasta</label><Fecha :model-value="hasta" :min="desde || undefined" @update:model-value="ir({ hasta: $event })" /></div>
            <Segmento :model-value="atajo" :opciones="atajos.map((a) => ({ l: a.l, v: a.l }))" vacio @update:model-value="(l) => { const a = atajos.find((x) => x.l === l); if (a) ir({ desde: a.d, hasta: a.h }); }" />
            <div class="sp" />
            <span class="muted small"><i class="pi pi-sort-amount-down" /> Más recientes primero · {{ lista.length }} registros</span>
        </div>
        <TablaDatos :filas="filas" :columnas="columnas" clicable :filtros-iniciales="{ deposit_state: estado }" :clase-fila="(a) => (a.balance > 0 && a.active ? 'fila-alerta' : '')"
            vacio="Sin anticipos con estos filtros" @fila="(a) => ui.abrirCita(a.id)">
            <template #c-patient="{ fila }"><Persona :nombre="fila.patient" /></template>
            <template #c-status="{ fila }"><Chip :valor="fila.status" /></template>
            <template #c-created_by="{ fila }"><Traza :r="fila" /></template>
            <template #c-deposit="{ fila }">{{ money(fila.deposit) }}</template>
            <template #c-paid="{ fila }"><span style="color: #16806c; font-weight: 600">{{ money(fila.paid) }}</span></template>
            <template #c-balance="{ fila }"><span v-if="fila.refunded" class="muted">—</span><b v-else :style="{ color: fila.balance > 0 ? '#a86717' : 'inherit' }">{{ money(fila.balance) }}</b></template>
            <template #c-deposit_state="{ fila }"><Chip :valor="fila.deposit_state" tipo="dep" /></template>
            <template #c-accion="{ fila }">
                <div class="accs">
                    <Accion icono="pi pi-eye" tip="Ver detalle de la cita" color="azul" @click="ui.abrirCita(fila.id)" />
                    <Accion v-if="fila.active && fila.balance > 0 && puede('cita')" icono="pi pi-whatsapp" tip="Recordar anticipo por WhatsApp / SMS" color="verde" @click="ui.abrir('mensaje', { cita: fila, tipo: 'cobro' })" />
                    <Accion v-if="fila.active && fila.balance > 0 && puede('anticipo')" icono="pi pi-wallet" texto="Cobrar" color="ambar" @click="ui.abrir('pago', { cita: fila })" />
                    <Accion v-else-if="!fila.active && fila.paid > 0 && !fila.refunded && puede('anticipo')" icono="pi pi-replay" texto="Reembolsar" color="morado" @click="ui.abrir('reembolso', { cita: fila })" />
                </div>
            </template>
        </TablaDatos>
    </AppLayout>
</template>
