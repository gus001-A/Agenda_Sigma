<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Barras from '@/Components/Barras.vue';
import Fecha from '@/Components/Fecha.vue';
import { filtrar } from '@/lib/api';
import { money, hoyISO, addDays, toISO, parseISO, CLASE_ESTADO, CLASE_HC } from '@/lib/util';

const props = defineProps({ desde: String, hasta: String, kpis: Object, porMedico: Array, porEspecialidad: Array, porEstado: Array, ocupacion: Array, horas: Object, anticipos: Object, historias: Array, topPacientes: Array });
const ir = (c) => filtrar('reportes', { desde: props.desde, hasta: props.hasta, ...c });
const hoy = hoyISO();
const finMes = addDays(toISO(new Date(parseISO(hoy).getFullYear(), parseISO(hoy).getMonth() + 1, 1)), -1);
const atajos = [{ l: 'Este mes', d: hoy.slice(0, 8) + '01', h: finMes }, { l: 'Últimos 7 días', d: addDays(hoy, -7), h: hoy }, { l: 'Próximos 14 días', d: hoy, h: addDays(hoy, 14) }];
const atajo = computed(() => atajos.find((a) => a.d === props.desde && a.h === props.hasta)?.l || null);
const apiladas = (lista) => lista.map((x) => ({ l: x.l, v: x.v, partes: [{ v: x.atendidas, c: 'var(--primary)' }, { v: x.v - x.atendidas - x.canceladas, c: 'color-mix(in srgb, var(--primary) 35%, transparent)' }, { v: x.canceladas, c: 'var(--danger)' }] }));
const estados = computed(() => props.porEstado.map((x) => ({ ...x, c: 'var(--sf)', clase: CLASE_ESTADO[x.l] })));
const hc = computed(() => props.historias.map((x) => ({ ...x, c: 'var(--sf)', clase: CLASE_HC[x.l] })));
const ant = computed(() => [{ l: 'Cobrados', v: props.anticipos.cobrados, c: 'var(--ok)' }, { l: 'Pendientes', v: props.anticipos.pendientes, c: 'var(--accent)' }, { l: 'Reembolsados', v: props.anticipos.reembolsados, c: '#8E3B69' }]);
const imprimir = () => window.print();
const exportar = computed(() => route('reportes.exportar', { desde: props.desde, hasta: props.hasta }));
</script>

<template>
    <AppLayout titulo="Reportes" subtitulo="Agenda, recursos, pacientes y anticipos">
        <div class="vh">
            <div class="field" style="width: 170px"><label>Desde</label><Fecha :model-value="desde" @update:model-value="ir({ desde: $event })" /></div>
            <div class="field" style="width: 170px"><label>Hasta</label><Fecha :model-value="hasta" @update:model-value="ir({ hasta: $event })" /></div>
            <SelectButton :model-value="atajo" :options="atajos" option-label="l" option-value="l" @update:model-value="(l) => { const a = atajos.find((x) => x.l === l); if (a) ir({ desde: a.d, hasta: a.h }); }" />
            <div class="sp" />
            <a :href="exportar" class="no-print"><Button label="Exportar CSV" icon="pi pi-download" severity="secondary" outlined /></a>
            <Button label="Imprimir" icon="pi pi-print" severity="secondary" outlined class="no-print" @click="imprimir" />
        </div>
        <div class="kpis">
            <div class="kpi"><span>Citas en el periodo</span><b>{{ kpis.citas }}</b></div>
            <div class="kpi"><span>Atendidas</span><b>{{ kpis.atendidas }}</b></div>
            <div class="kpi"><span>Canceladas</span><b>{{ kpis.canceladas }}</b></div>
            <div class="kpi"><span>Reagendadas</span><b>{{ kpis.reagendadas }}</b></div>
            <div class="kpi"><span>No atendidas</span><b>{{ kpis.noAtendidas }}</b></div>
            <div class="kpi"><span>Pacientes atendidos</span><b>{{ kpis.pacientesAtendidos }}</b></div>
            <div class="kpi"><span>Pacientes nuevos</span><b>{{ kpis.pacientesNuevos }}</b></div>
        </div>
        <div class="grid2">
            <div class="card"><header><h3>Citas por médico</h3></header><div class="body"><Barras :items="apiladas(porMedico)" /><p class="muted small" style="margin-top: 10px">Oscuro: atendidas. Claro: por atender. Rojo: canceladas.</p></div></div>
            <div class="card"><header><h3>Citas por especialidad</h3></header><div class="body"><Barras :items="apiladas(porEspecialidad)" /></div></div>
            <div class="card"><header><h3>Citas por estado</h3></header><div class="body"><Barras :items="estados" /></div></div>
            <div class="card"><header><h3>Ocupación de recursos</h3></header><div class="body"><Barras :items="ocupacion" :maximo="100" :formato="(i) => i.v + '%'" />
                <p class="muted small" style="margin-top: 10px">Horas de agenda médica: {{ horas.ocupadas }} h ocupadas, {{ horas.libres }} h libres, {{ horas.bloqueadas }} h bloqueadas.</p></div></div>
            <div class="card"><header><h3>Anticipos</h3></header><div class="body"><Barras :items="ant" :formato="(i) => money(i.v)" /></div></div>
            <div class="card"><header><h3>Historias clínicas</h3></header><div class="body"><Barras :items="hc" /></div></div>
            <div class="card"><header><h3>Pacientes con más citas</h3></header><div class="body small">
                <div v-for="(p, i) in topPacientes" :key="i" class="row" style="justify-content: space-between; padding: 4px 0"><span>{{ p.l }}</span><b>{{ p.v }}</b></div>
                <p v-if="!topPacientes.length" class="muted">Sin datos</p>
            </div></div>
        </div>
    </AppLayout>
</template>
