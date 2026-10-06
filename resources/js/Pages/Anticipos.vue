<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import Kpi from '@/Components/Kpi.vue';
import TablaDatos from '@/Components/TablaDatos.vue';
import { ui, usePermisos } from '@/lib/ui';
import { fmtD, money } from '@/lib/util';

const props = defineProps({ estado: String, kpis: Object, lista: Array });
const { puede } = usePermisos();
const unicos = (k) => [...new Set(props.lista.map((x) => x[k]).filter(Boolean))].sort();
const filas = computed(() => props.lista.map((a) => ({ ...a, fecha: `${fmtD(a.date)} ${a.time}` })));
const columnas = computed(() => [
    { campo: 'fecha', titulo: 'Fecha', filtro: 'texto', ancho: '140px' },
    { campo: 'patient', titulo: 'Paciente', filtro: 'texto', ancho: '200px' },
    { campo: 'doctor', titulo: 'Médico', filtro: 'lista', opciones: unicos('doctor') },
    { campo: 'status', titulo: 'Estado de cita', filtro: 'lista', opciones: unicos('status') },
    { campo: 'deposit', titulo: 'Requerido', alinear: 'right' },
    { campo: 'paid', titulo: 'Pagado', alinear: 'right' },
    { campo: 'balance', titulo: 'Saldo', alinear: 'right' },
    { campo: 'deposit_state', titulo: 'Anticipo', filtro: 'lista', opciones: ['Pendiente', 'Parcial', 'Pagado', 'Reembolsado'] },
    { campo: 'accion', titulo: 'Acción', ordenar: false, ancho: '150px' },
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
        <TablaDatos :filas="filas" :columnas="columnas" clicable :filtros-iniciales="{ deposit_state: estado }" :clase-fila="(a) => (a.balance > 0 && a.active ? 'fila-alerta' : '')"
            vacio="Sin anticipos con estos filtros" @fila="(a) => ui.abrirCita(a.id)">
            <template #c-patient="{ fila }"><b style="font-weight: 600">{{ fila.patient }}</b></template>
            <template #c-status="{ fila }"><Chip :valor="fila.status" /></template>
            <template #c-deposit="{ fila }">{{ money(fila.deposit) }}</template>
            <template #c-paid="{ fila }"><span style="color: #16806c; font-weight: 600">{{ money(fila.paid) }}</span></template>
            <template #c-balance="{ fila }"><span v-if="fila.refunded" class="muted">—</span><b v-else :style="{ color: fila.balance > 0 ? '#a86717' : 'inherit' }">{{ money(fila.balance) }}</b></template>
            <template #c-deposit_state="{ fila }"><Chip :valor="fila.deposit_state" tipo="dep" /></template>
            <template #c-accion="{ fila }">
                <div @click.stop>
                    <Button v-if="fila.active && fila.balance > 0 && puede('anticipo')" label="Registrar pago" icon="pi pi-wallet" size="small" severity="warn" @click="ui.abrir('pago', { cita: fila })" />
                    <Button v-else-if="!fila.active && fila.paid > 0 && !fila.refunded && puede('anticipo')" label="Reembolsar" icon="pi pi-replay" size="small" severity="secondary" outlined @click="ui.abrir('reembolso', { cita: fila })" />
                </div>
            </template>
        </TablaDatos>
    </AppLayout>
</template>
