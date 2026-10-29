<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import Kpi from '@/Components/Kpi.vue';
import Grafica from '@/Components/Grafica.vue';
import { ui } from '@/lib/ui';
import { fmtDL, money, tm, initials, COLOR_ESTADO } from '@/lib/util';

const props = defineProps({ fecha: String, medicosHoy: Number, bloqueosHoy: Number, kpis: Object, proximas: Array, estadosHoy: Object, espera: Array, esperaTotal: Number });
const ir = (r) => router.visit(route(r));
const COL_PRIO = { Alta: '#d64545', Media: '#e08a1e', Baja: '#1f9e86' };

const estados = computed(() => Object.entries(props.estadosHoy || {}));
const esperando = (d) => (d === 0 ? 'Desde hoy' : d === 1 ? '1 día esperando' : `${d} días esperando`);

// Minuto actual del día; se refresca cada 30 s para que "en 15 min" no se quede viejo.
const ahora = ref(0);
const tic = () => { const d = new Date(); ahora.value = d.getHours() * 60 + d.getMinutes(); };
let reloj;
onMounted(() => { tic(); reloj = setInterval(tic, 30000); });
onUnmounted(() => clearInterval(reloj));

/** Cuándo es la cita respecto a la hora actual. */
function cuando(a) {
    const ini = tm(a.time), dif = ini - ahora.value;
    if (a.status === 'En consulta') return { t: 'En consulta', c: 'en-curso' };
    if (dif > 60) return { t: `en ${Math.floor(dif / 60)} h ${dif % 60 ? (dif % 60) + ' min' : ''}`.trim(), c: '' };
    if (dif > 0) return { t: `en ${dif} min`, c: dif <= 15 ? 'pronto' : '' };
    if (ahora.value < ini + (a.duration || 30)) return { t: 'Ahora', c: 'en-curso' };
    return { t: `hace ${-dif} min`, c: 'tarde' };
}
// La siguiente cita que aún no empieza se destaca.
const siguiente = computed(() => props.proximas.find((a) => a.status !== 'En consulta' && tm(a.time) >= ahora.value)?.id);
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
            <Kpi etiqueta="Consultorios ocupados" :valor="kpis.consultorios" icono="pi pi-building" color="#1c6b78" />
            <Kpi etiqueta="Quirófanos ocupados" :valor="kpis.quirofanos" icono="pi pi-heart" color="#db2777" clic @click="ir('espacios')" />
        </div>

        <div class="panel-v">
            <!-- Arriba: citas por estado y lista de espera -->
            <div class="panel-top">
                <div class="card">
                    <header><span class="hic" style="--c: #2b5480"><i class="pi pi-chart-pie" /></span><h3>Citas de hoy por estado</h3></header>
                    <div class="body">
                        <Grafica v-if="estados.length" tipo="doughnut" :etiquetas="estados.map((e) => e[0])" :series="[{ nombre: 'Citas', datos: estados.map((e) => e[1]), colores: estados.map((e) => COLOR_ESTADO[e[0]]) }]" alto="190px" />
                        <p v-else class="muted">Sin citas hoy.</p>
                    </div>
                </div>
                <div class="card col">
                    <header>
                        <span class="hic" style="--c: #e08a1e"><i class="pi pi-list" /></span><h3>Lista de espera <span v-if="esperaTotal" class="prox-n esp-n">{{ esperaTotal }}</span></h3>
                        <Link :href="route('espera')" class="btn-card" style="--bc: #e08a1e">Gestionar <i class="pi pi-arrow-right" /></Link>
                    </header>
                    <div class="scroll-y esp-lista">
                        <Link v-for="(w, i) in espera" :key="i" :href="route('espera')" class="esp-item" :style="{ '--pc': COL_PRIO[w.priority] }">
                            <span class="esp-av">{{ initials(w.patient) }}</span>
                            <div class="esp-info">
                                <b>{{ w.patient }}</b>
                                <small><i class="pi pi-bookmark" />{{ w.specialty }}<template v-if="w.doctor"> · {{ w.doctor }}</template></small>
                                <small><i class="pi pi-clock" />{{ w.preference }}<template v-if="w.phone"> · <i class="pi pi-phone" />{{ w.phone }}</template></small>
                            </div>
                            <div class="esp-lado">
                                <span class="esp-prio">{{ w.priority }}</span>
                                <small :class="{ largo: w.days >= 7 }">{{ esperando(w.days) }}</small>
                            </div>
                        </Link>
                        <div v-if="!espera.length" class="empty"><i class="pi pi-check-circle" style="font-size: 24px; color: #b6c3d1" /><b>Sin pacientes en espera</b>Cuando alguien pida lugar aparecerá aquí.</div>
                        <Link v-else-if="esperaTotal > espera.length" :href="route('espera')" class="esp-mas">Ver los {{ esperaTotal - espera.length }} restantes <i class="pi pi-arrow-right" /></Link>
                    </div>
                </div>
            </div>

            <!-- Abajo: próximas citas de hoy -->
            <div class="card col prox">
                <header>
                    <span class="hic" style="--c: #1f9e86"><i class="pi pi-clock" /></span><h2>Próximas citas de hoy <span v-if="proximas.length" class="prox-n">{{ proximas.length }}</span></h2>
                    <Link :href="route('recepcion')" class="btn-card" style="--bc: #1f9e86">Ver todas <i class="pi pi-arrow-right" /></Link>
                </header>
                <div class="scroll-y prox-lista">
                    <div v-for="a in proximas" :key="a.id" class="prox-item" :class="{ 'prox-sig': a.id === siguiente }" :style="{ '--ec': COLOR_ESTADO[a.status] || '#94a3b8' }" @click="ui.abrirCita(a.id)">
                        <div class="prox-hora">
                            <b>{{ a.time }}</b>
                            <span :class="cuando(a).c">{{ cuando(a).t }}</span>
                        </div>
                        <div class="prox-pac">
                            <span class="prox-av">{{ initials(a.patient) }}</span>
                            <div>
                                <b>{{ a.patient }}</b>
                                <small>{{ a.type }}<template v-if="a.reason"> · {{ a.reason }}</template></small>
                            </div>
                        </div>
                        <div class="prox-med">
                            <i class="pi pi-user-plus" />
                            <div><b>{{ a.doctor }}</b><small>{{ a.specialty }} · {{ a.duration }} min</small></div>
                        </div>
                        <div class="prox-chips">
                            <Chip :valor="a.status" />
                            <Chip :valor="a.hc_status" tipo="hc" />
                            <span v-if="a.balance > 0" class="prox-saldo"><i class="pi pi-wallet" /> Debe {{ money(a.balance) }}</span>
                        </div>
                        <span v-if="a.id === siguiente" class="prox-tag">Siguiente</span>
                        <i class="pi pi-angle-right prox-ir" />
                    </div>
                    <div v-if="!proximas.length" class="empty"><i class="pi pi-calendar-times" style="font-size: 26px; color: #b6c3d1" /><b>No quedan citas por atender hoy</b>Las próximas aparecen en la agenda.</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
