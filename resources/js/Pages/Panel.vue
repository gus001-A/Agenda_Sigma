<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import Kpi from '@/Components/Kpi.vue';
import Barras from '@/Components/Barras.vue';
import { ui } from '@/lib/ui';
import { fmtDL, money, COLOR_HC } from '@/lib/util';

const props = defineProps({ fecha: String, medicosHoy: Number, bloqueosHoy: Number, kpis: Object, proximas: Array, cargas: Array, historias: Object, espera: Array });
const mx = computed(() => Math.max(1, ...props.cargas.map((x) => x.tot)));
const cargas = computed(() => props.cargas.map((x) => ({ ...x, max: mx.value, partes: [{ v: x.v, c: 'linear-gradient(90deg, #173a5f, #1f9e86)' }, { v: x.bloq, c: '#cbd5e1' }] })));
const hc = computed(() => Object.entries(props.historias).map(([l, v]) => ({ l, v, c: COLOR_HC[l] })));
const ir = (r) => router.visit(route(r));
const COL_PRIO = { Alta: '#d64545', Media: '#e08a1e', Baja: '#1f9e86' };
</script>

<template>
    <AppLayout titulo="Panel del día" :subtitulo="`${fmtDL(fecha)} · ${medicosHoy} médicos con consulta hoy, ${bloqueosHoy} bloqueos activos`">
        <div class="kpis linea">
            <Kpi etiqueta="Citas del día" :valor="kpis.citas" icono="pi pi-calendar" color="#173a5f" clic @click="ir('recepcion')" />
            <Kpi etiqueta="Confirmadas" :valor="kpis.confirmadas" icono="pi pi-check-circle" color="#1f9e86" />
            <Kpi etiqueta="Por confirmar" :valor="kpis.pendientes" icono="pi pi-hourglass" color="#2b5480" />
            <Kpi etiqueta="Canceladas" :valor="kpis.canceladas" icono="pi pi-times-circle" color="#d64545" />
            <Kpi etiqueta="Pacientes" :valor="kpis.pacientes" icono="pi pi-users" color="#6b4bc9" />
            <Kpi etiqueta="Anticipos pendientes" :valor="money(kpis.anticiposMonto)" :extra="kpis.anticiposCitas + ' citas'" icono="pi pi-wallet" color="#e08a1e" doble clic @click="ir('anticipos')" />
            <Kpi etiqueta="Historias por completar" :valor="kpis.historias" extra="7 días" icono="pi pi-file-edit" color="#0891b2" clic @click="ir('historias')" />
            <Kpi etiqueta="Consultorios ocupados" :valor="kpis.consultorios" icono="pi pi-building" color="#1c6b78" />
            <Kpi etiqueta="Quirófanos ocupados" :valor="kpis.quirofanos" icono="pi pi-heart" color="#db2777" clic @click="ir('espacios')" />
        </div>
        <div class="split">
            <div class="card" style="display: flex; flex-direction: column; overflow: hidden">
                <header>
                    <span class="hic" style="--c: #1f9e86"><i class="pi pi-clock" /></span><h2>Próximas citas de hoy</h2>
                    <Link :href="route('recepcion')"><Button label="Ver todas" icon="pi pi-arrow-right" icon-pos="right" size="small" severity="secondary" outlined /></Link>
                </header>
                <div class="scroll-y" style="flex: 1">
                    <div v-for="a in proximas" :key="a.id" class="list-item" @click="ui.abrirCita(a.id)">
                        <span class="time-b" style="color: var(--navy)">{{ a.time }}</span>
                        <div style="flex: 1; min-width: 0"><b style="font-weight: 600">{{ a.patient }}</b><div class="muted small">{{ a.doctor }}, {{ a.specialty }}</div></div>
                        <Chip :valor="a.hc_status" tipo="hc" /><Chip :valor="a.status" />
                    </div>
                    <div v-if="!proximas.length" class="empty"><i class="pi pi-calendar-times" style="font-size: 26px; color: #b6c3d1" /><b>No quedan citas por atender hoy</b>Las próximas aparecen en la agenda.</div>
                </div>
            </div>
            <div class="stack scroll-y">
                <div class="card">
                    <header><span class="hic" style="--c: #173a5f"><i class="pi pi-chart-bar" /></span><h3>Ocupación por médico hoy</h3></header>
                    <div class="body">
                        <Barras v-if="cargas.length" :items="cargas" :maximo="mx" :formato="(i) => `${i.v}/${i.tot}`" />
                        <p v-else class="muted">Ningún médico tiene consulta hoy.</p>
                        <p class="muted small" style="margin-top: 10px"><i class="pi pi-info-circle" style="font-size: 11px" /> Barra gris: espacios bloqueados.</p>
                    </div>
                </div>
                <div class="card"><header><span class="hic" style="--c: #0891b2"><i class="pi pi-file-edit" /></span><h3>Historias clínicas, próximos 7 días</h3></header><div class="body"><Barras :items="hc" /></div></div>
                <div class="card">
                    <header><span class="hic" style="--c: #e08a1e"><i class="pi pi-list" /></span><h3>Lista de espera</h3><Link :href="route('espera')"><Button label="Gestionar" size="small" severity="secondary" outlined /></Link></header>
                    <div class="body small">
                        <div v-for="(w, i) in espera" :key="i" class="row" style="justify-content: space-between; padding: 5px 0">
                            <span><i class="pi pi-user" style="font-size: 11px; color: #94a3b8; margin-right: 6px" />{{ w.patient }}</span>
                            <span class="chip plain" :style="{ '--sb': COL_PRIO[w.priority] + '1a', '--sf': COL_PRIO[w.priority] }">{{ w.specialty }} · {{ w.priority }}</span>
                        </div>
                        <p v-if="!espera.length" class="muted">Sin pacientes en espera.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
