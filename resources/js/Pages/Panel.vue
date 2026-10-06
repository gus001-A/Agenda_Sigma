<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import Kpi from '@/Components/Kpi.vue';
import Grafica from '@/Components/Grafica.vue';
import { ui } from '@/lib/ui';
import { fmtDL, money, COLOR_HC, COLOR_ESTADO } from '@/lib/util';

const props = defineProps({ fecha: String, medicosHoy: Number, bloqueosHoy: Number, kpis: Object, proximas: Array, cargas: Array, historias: Object, estadosHoy: Object, espera: Array });
const ir = (r) => router.visit(route(r));
const COL_PRIO = { Alta: '#d64545', Media: '#e08a1e', Baja: '#1f9e86' };

const estados = computed(() => Object.entries(props.estadosHoy || {}));
const hc = computed(() => Object.entries(props.historias));
// Nombre corto del médico para el eje de la gráfica de barras.
const corto = (n) => n.replace(/^(Dr\.|Dra\.|Lic\.|Psic\.)\s*/, '').split(' ').slice(0, 2).join(' ');
const ocupacion = computed(() => ({
    etiquetas: props.cargas.map((c) => corto(c.l)),
    series: [
        { nombre: 'Con cita', datos: props.cargas.map((c) => c.v), color: '#173a5f' },
        { nombre: 'Libres', datos: props.cargas.map((c) => Math.max(0, c.tot - c.v - c.bloq)), color: '#1f9e86' },
        { nombre: 'Bloqueados', datos: props.cargas.map((c) => c.bloq), color: '#cbd5e1' },
    ],
}));
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

        <div class="panel-grid">
            <div class="card col">
                <header>
                    <span class="hic" style="--c: #1f9e86"><i class="pi pi-clock" /></span><h2>Próximas citas de hoy</h2>
                    <Link :href="route('recepcion')"><Button label="Ver todas" icon="pi pi-arrow-right" icon-pos="right" size="small" severity="secondary" outlined /></Link>
                </header>
                <div class="scroll-y" style="flex: 1">
                    <div v-for="a in proximas" :key="a.id" class="list-item" @click="ui.abrirCita(a.id)">
                        <span class="time-b" style="color: var(--navy)">{{ a.time }}</span>
                        <div style="flex: 1; min-width: 0"><b style="font-weight: 600">{{ a.patient }}</b><div class="muted small">{{ a.doctor }}, {{ a.specialty }}</div></div>
                        <div style="display: grid; gap: 4px; justify-items: end"><Chip :valor="a.status" /><Chip :valor="a.hc_status" tipo="hc" /></div>
                    </div>
                    <div v-if="!proximas.length" class="empty"><i class="pi pi-calendar-times" style="font-size: 26px; color: #b6c3d1" /><b>No quedan citas por atender hoy</b>Las próximas aparecen en la agenda.</div>
                </div>
            </div>

            <div class="stack scroll-y">
                <div class="card">
                    <header><span class="hic" style="--c: #2b5480"><i class="pi pi-chart-pie" /></span><h3>Citas de hoy por estado</h3></header>
                    <div class="body"><Grafica tipo="doughnut" :etiquetas="estados.map((e) => e[0])" :series="[{ nombre: 'Citas', datos: estados.map((e) => e[1]), colores: estados.map((e) => COLOR_ESTADO[e[0]]) }]" alto="230px" /></div>
                </div>
                <div class="card">
                    <header><span class="hic" style="--c: #0891b2"><i class="pi pi-file-edit" /></span><h3>Historias clínicas, próximos 7 días</h3></header>
                    <div class="body"><Grafica tipo="pie" :etiquetas="hc.map((e) => e[0])" :series="[{ nombre: 'Historias', datos: hc.map((e) => e[1]), colores: hc.map((e) => COLOR_HC[e[0]]) }]" alto="230px" /></div>
                </div>
            </div>

            <div class="stack scroll-y">
                <div class="card">
                    <header><span class="hic" style="--c: #173a5f"><i class="pi pi-chart-bar" /></span><h3>Ocupación por médico hoy</h3></header>
                    <div class="body">
                        <Grafica v-if="cargas.length" tipo="barh" apilada :etiquetas="ocupacion.etiquetas" :series="ocupacion.series" :alto="Math.max(200, cargas.length * 34 + 60) + 'px'" />
                        <p v-else class="muted">Ningún médico tiene consulta hoy.</p>
                    </div>
                </div>
                <div class="card">
                    <header><span class="hic" style="--c: #e08a1e"><i class="pi pi-list" /></span><h3>Lista de espera</h3><Link :href="route('espera')"><Button label="Gestionar" size="small" severity="secondary" outlined /></Link></header>
                    <div class="body small">
                        <div v-for="(w, i) in espera" :key="i" class="row" style="justify-content: space-between; padding: 5px 0; flex-wrap: nowrap">
                            <span class="row" style="gap: 8px; flex-wrap: nowrap; min-width: 0"><span class="dot" :style="{ '--sf': COL_PRIO[w.priority] }" />{{ w.patient }}</span>
                            <span class="chip plain" :style="{ '--sb': COL_PRIO[w.priority] + '1a', '--sf': COL_PRIO[w.priority] }">{{ w.specialty }}</span>
                        </div>
                        <p v-if="!espera.length" class="muted">Sin pacientes en espera.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
