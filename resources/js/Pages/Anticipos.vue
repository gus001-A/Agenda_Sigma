<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import { ui, usePermisos } from '@/lib/ui';
import { filtrar } from '@/lib/api';
import { fmtD, money } from '@/lib/util';

defineProps({ estado: String, kpis: Object, lista: Array });
const { puede } = usePermisos();
const estados = [{ l: 'Todos', v: null }, ...['Pendiente', 'Parcial', 'Pagado', 'Reembolsado'].map((x) => ({ l: x, v: x }))];
</script>

<template>
    <AppLayout titulo="Anticipos" subtitulo="Pagos y saldos de citas">
        <div class="kpis">
            <div class="kpi"><span>Anticipos cobrados</span><b>{{ money(kpis.cobrados) }}</b></div>
            <div class="kpi alert"><span>Pendientes por cobrar</span><b>{{ money(kpis.pendientes) }}</b></div>
            <div class="kpi"><span>Reembolsados</span><b>{{ money(kpis.reembolsados) }}</b></div>
            <div class="kpi"><span>Citas con anticipo</span><b>{{ kpis.citas }}</b></div>
        </div>
        <div class="vh">
            <SelectButton :model-value="estado" :options="estados" option-label="l" option-value="v" :allow-empty="false" @update:model-value="filtrar('anticipos', { estado: $event })" />
        </div>
        <div class="card tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Fecha</th><th>Paciente</th><th>Médico</th><th>Estado de cita</th><th class="num">Requerido</th><th class="num">Pagado</th><th class="num">Saldo</th><th>Anticipo</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="a in lista" :key="a.id" class="click" @click="ui.abrirCita(a.id)">
                        <td style="white-space: nowrap">{{ fmtD(a.date) }} {{ a.time }}</td>
                        <td>{{ a.patient }}</td><td>{{ a.doctor }}</td><td><Chip :valor="a.status" /></td>
                        <td class="num">{{ money(a.deposit) }}</td><td class="num">{{ money(a.paid) }}</td>
                        <td class="num">{{ a.refunded ? '—' : money(a.balance) }}</td>
                        <td><Chip :valor="a.deposit_state" tipo="dep" /></td>
                        <td @click.stop>
                            <Button v-if="a.active && a.balance > 0 && puede('anticipo')" label="Registrar pago" size="small" severity="warn" @click="ui.abrir('pago', { cita: a })" />
                            <Button v-else-if="!a.active && a.paid > 0 && !a.refunded && puede('anticipo')" label="Reembolsar" size="small" severity="secondary" outlined @click="ui.abrir('reembolso', { cita: a })" />
                        </td>
                    </tr>
                    <tr v-if="!lista.length"><td colspan="9"><div class="empty"><b>Sin anticipos con este estado</b></div></td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
