<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import Barras from '@/Components/Barras.vue';
import { ui } from '@/lib/ui';
import { fmtDL, money, COLOR_HC } from '@/lib/util';

const props = defineProps({ fecha: String, medicosHoy: Number, bloqueosHoy: Number, kpis: Object, proximas: Array, cargas: Array, historias: Object, espera: Array });
const mx = computed(() => Math.max(1, ...props.cargas.map((x) => x.tot)));
const cargas = computed(() => props.cargas.map((x) => ({ ...x, max: mx.value, partes: [{ v: x.v, c: 'var(--primary)' }, { v: x.bloq, c: 'var(--line)' }] })));
const hc = computed(() => Object.entries(props.historias).map(([l, v]) => ({ l, v, c: COLOR_HC[l] })));
const ir = (r) => router.visit(route(r));
</script>

<template>
    <AppLayout titulo="Panel del día" subtitulo="Indicadores de la operación">
        <div class="vh">
            <div>
                <h2>{{ fmtDL(fecha) }}</h2>
                <p class="muted small">{{ medicosHoy }} médicos con consulta hoy, {{ bloqueosHoy }} bloqueos activos</p>
            </div>
        </div>
        <div class="kpis">
            <div class="kpi click" @click="ir('recepcion')"><span>Citas del día</span><b>{{ kpis.citas }}</b></div>
            <div class="kpi"><span>Confirmadas</span><b>{{ kpis.confirmadas }}</b></div>
            <div class="kpi"><span>Pendientes de confirmar</span><b>{{ kpis.pendientes }}</b></div>
            <div class="kpi"><span>Canceladas</span><b>{{ kpis.canceladas }}</b></div>
            <div class="kpi"><span>Pacientes del día</span><b>{{ kpis.pacientes }}</b></div>
            <div class="kpi alert click" @click="ir('anticipos')"><span>Anticipos pendientes</span><b>{{ money(kpis.anticiposMonto) }}</b><em>{{ kpis.anticiposCitas }} citas</em></div>
            <div class="kpi alert click" @click="ir('historias')"><span>Historias por completar</span><b>{{ kpis.historias }}</b><em>próx. 7 días</em></div>
            <div class="kpi"><span>Consultorios ocupados</span><b>{{ kpis.consultorios }}</b></div>
            <div class="kpi click" @click="ir('espacios')"><span>Quirófanos ocupados</span><b>{{ kpis.quirofanos }}</b></div>
        </div>
        <div class="split">
            <div class="card">
                <header><h2>Próximas citas de hoy</h2><Link :href="route('recepcion')"><Button label="Ver todas" size="small" severity="secondary" outlined /></Link></header>
                <div v-for="a in proximas" :key="a.id" class="list-item" @click="ui.abrirCita(a.id)">
                    <span class="time-b">{{ a.time }}</span>
                    <div style="flex: 1; min-width: 0"><b style="font-weight: 500">{{ a.patient }}</b><div class="muted small">{{ a.doctor }}, {{ a.specialty }}</div></div>
                    <Chip :valor="a.hc_status" tipo="hc" /><Chip :valor="a.status" />
                </div>
                <div v-if="!proximas.length" class="empty"><b>No quedan citas por atender hoy</b>Las próximas aparecen en la agenda.</div>
            </div>
            <div class="stack">
                <div class="card">
                    <header><h3>Ocupación por médico hoy</h3></header>
                    <div class="body">
                        <Barras v-if="cargas.length" :items="cargas" :maximo="mx" :formato="(i) => `${i.v}/${i.tot}`" />
                        <p v-else class="muted">Ningún médico tiene consulta hoy.</p>
                        <p class="muted small" style="margin-top: 10px">Barra gris: espacios bloqueados.</p>
                    </div>
                </div>
                <div class="card"><header><h3>Historias clínicas, próximos 7 días</h3></header><div class="body"><Barras :items="hc" /></div></div>
                <div class="card">
                    <header><h3>Lista de espera</h3><Link :href="route('espera')"><Button label="Gestionar" size="small" severity="secondary" outlined /></Link></header>
                    <div class="body small">
                        <div v-for="(w, i) in espera" :key="i" class="row" style="justify-content: space-between; padding: 4px 0">
                            <span>{{ w.patient }}</span><span class="muted">{{ w.specialty }}, prioridad {{ w.priority.toLowerCase() }}</span>
                        </div>
                        <p v-if="!espera.length" class="muted">Sin pacientes en espera.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
