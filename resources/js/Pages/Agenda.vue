<script setup>
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Segmento from '@/Components/Segmento.vue';
import Chip from '@/Components/Chip.vue';
import DateNav from '@/Components/DateNav.vue';
import { ui, usePermisos } from '@/lib/ui';
import { filtrar } from '@/lib/api';
import { DIAS, MESES, parseISO, fmtDL, mt, money, hoyISO, toISO, CLASE_ESTADO, CLASE_HC } from '@/lib/util';

const props = defineProps({ fecha: String, modo: String, filtros: Object, bloqueado: Boolean, ahora: Number, columnas: Array, doctor: Object, celdas: Array });
const page = usePage();
const { puede } = usePermisos();
const op = computed(() => page.props.opciones);
const PPM = 1.6; // pixeles por minuto
const hoy = hoyISO();

const medicosFiltro = computed(() => op.value.medicos.filter((d) => !props.filtros.spec || d.specialty_id === props.filtros.spec));
const consultorios = computed(() => op.value.recursos.filter((r) => ['Consultorio', 'Sala de rehabilitación'].includes(r.type)));
const estados = Object.keys(page.props.catalogo.estados).filter((s) => s !== 'No disponible');
const leyenda = ['Disponible', 'Agendada', 'Confirmada', 'Anticipo pendiente', 'En consulta', 'Atendida', 'Bloqueada'];
const hayFiltros = computed(() => props.filtros.spec || props.filtros.room || props.filtros.st || props.filtros.type || (props.filtros.doc && !props.bloqueado));

function ir(cambios) {
    const f = { ...props.filtros, fecha: props.fecha, modo: props.modo, ...cambios };
    if (cambios.spec !== undefined && f.doc && op.value.medicos.find((d) => d.id === f.doc)?.specialty_id !== cambios.spec) f.doc = null;
    filtrar('agenda', { fecha: f.fecha, modo: f.modo, spec: f.spec, doc: props.bloqueado ? null : f.doc, room: f.room, st: f.st, type: f.type });
}
const limpiar = () => filtrar('agenda', { fecha: props.fecha, modo: props.modo });
function moverMes(n) { const d = parseISO(props.fecha); d.setMonth(d.getMonth() + n, 1); ir({ fecha: toISO(d) }); }

// Rango vertical del calendario a partir de los espacios
const rango = computed(() => {
    let a = 24 * 60, b = 0;
    (props.columnas || []).forEach((c) => c.slots.forEach((s) => { a = Math.min(a, s.m); b = Math.max(b, s.m + s.dur); }));
    if (props.modo === 'semana' && props.doctor) { const [h1, m1] = props.doctor.start.split(':'); const [h2, m2] = props.doctor.end.split(':'); a = Math.min(a, +h1 * 60 + +m1); b = Math.max(b, +h2 * 60 + +m2); }
    if (b <= a) return { base: 8 * 60, fin: 14 * 60 };
    return { base: Math.floor(a / 60) * 60, fin: Math.ceil(b / 60) * 60 };
});
const alto = computed(() => (rango.value.fin - rango.value.base) * PPM);
const horasEje = computed(() => { const r = []; for (let m = Math.ceil(rango.value.base / 60) * 60; m <= rango.value.fin; m += 60) r.push(m); return r; });
const fondo = computed(() => ({ height: alto.value + 'px', backgroundImage: `repeating-linear-gradient(to bottom, var(--line-2) 0 1px, transparent 1px ${60 * PPM}px)` }));

function estiloSlot(s) { return { top: (s.m - rango.value.base) * PPM + 'px', height: Math.max(s.dur * PPM - 3, 18) + 'px' }; }
function atenuado(s) { const f = props.filtros; return (f.st && s.state !== f.st) || (f.type && (!s.appt || s.appt.type !== f.type)); }
function clickSlot(s, docId, fecha) {
    if (s.appt) ui.abrirCita(s.appt.id);
    else if (s.state === 'Bloqueada') ui.abrir('bloqueoVer', { id: s.block.id });
    else if (s.state === 'Disponible' && puede('cita')) ui.abrir('nuevaCita', { doctor_id: docId, date: fecha, time: s.time });
}
const lineaAhora = (fecha) => fecha === hoy && props.ahora >= rango.value.base && props.ahora <= rango.value.fin;

const columnasVista = computed(() => {
    if (props.modo === 'dia') return (props.columnas || []).map((c) => ({ key: c.doctor.id, docId: c.doctor.id, fecha: props.fecha, hoy: props.fecha === hoy, off: false, titulo: c.doctor.name, sub: `${c.doctor.specialty}${c.doctor.room ? ', ' + c.doctor.room : ''}`, slots: c.slots }));
    return (props.columnas || []).map((c) => ({ key: c.date, docId: props.doctor.id, fecha: c.date, hoy: c.date === hoy, off: !c.works, titulo: `${DIAS[parseISO(c.date).getDay()]} ${parseISO(c.date).getDate()}`, sub: c.works ? c.slots.filter((x) => x.state === 'Disponible').length + ' disponibles' : 'Sin consulta', slots: c.slots }));
});
const etiquetaMes = computed(() => { const d = parseISO(props.fecha); return `${MESES[d.getMonth()]} ${d.getFullYear()}`; });
</script>

<template>
    <AppLayout titulo="Agenda médica" subtitulo="Disponibilidad generada a partir de reglas de horario">
        <div class="vh">
            <Segmento :model-value="modo" :opciones="[{ l: 'Día', v: 'dia', i: 'pi pi-stop' }, { l: 'Semana', v: 'semana', i: 'pi pi-pause' }, { l: 'Mes', v: 'mes', i: 'pi pi-th-large' }]" @update:model-value="ir({ modo: $event })" />
            <div v-if="modo === 'mes'" class="row" style="gap: 6px">
                <Button icon="pi pi-chevron-left" size="small" severity="secondary" outlined @click="moverMes(-1)" />
                <Button label="Hoy" size="small" severity="secondary" outlined @click="ir({ fecha: hoy })" />
                <Button icon="pi pi-chevron-right" size="small" severity="secondary" outlined @click="moverMes(1)" />
                <span class="muted small" style="margin-left: 4px; text-transform: capitalize">{{ etiquetaMes }}</span>
            </div>
            <DateNav v-else :fecha="fecha" :paso="modo === 'semana' ? 7 : 1" @cambiar="ir({ fecha: $event })" />
            <div class="sp" />
            <Button v-if="puede('bloqueo')" label="Bloquear horario" icon="pi pi-ban" severity="secondary" outlined @click="ui.abrir('bloqueo', { scope: 'doctor', target_id: filtros.doc || 0, date: fecha })" />
        </div>
        <div class="filtros">
            <Select :model-value="filtros.spec" :options="op.especialidades" option-label="name" option-value="id" placeholder="Todas las especialidades" show-clear :disabled="bloqueado" @update:model-value="ir({ spec: $event })" />
            <Select :model-value="filtros.doc" :options="medicosFiltro" option-label="name" option-value="id" :placeholder="modo === 'semana' ? 'Elige un médico' : 'Todos los médicos'" show-clear filter :disabled="bloqueado" @update:model-value="ir({ doc: $event })" />
            <Select :model-value="filtros.room" :options="consultorios" option-label="name" option-value="id" placeholder="Todos los consultorios" show-clear @update:model-value="ir({ room: $event })" />
            <Select :model-value="filtros.st" :options="estados" placeholder="Todos los estados" show-clear @update:model-value="ir({ st: $event })" />
            <Select :model-value="filtros.type" :options="page.props.catalogo.tiposConsulta" placeholder="Todos los tipos" show-clear @update:model-value="ir({ type: $event })" />
            <Button v-if="hayFiltros" label="Limpiar filtros" size="small" text @click="limpiar" />
        </div>

        <!-- MES -->
        <div v-if="modo === 'mes'" class="month">
            <div v-for="d in ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']" :key="d" class="dh">{{ d }}</div>
            <div v-for="c in celdas" :key="c.date" class="dc" :class="{ out: !c.in, today: c.date === hoy }" @click="ir({ fecha: c.date, modo: 'dia' })">
                <span class="n">{{ parseISO(c.date).getDate() }}</span>
                <template v-if="c.total">
                    <small>{{ c.citas }} citas</small><small>{{ c.libres }} disponibles</small>
                    <div class="prog"><i :style="{ width: Math.round((c.citas * 100) / c.total) + '%' }" /></div>
                </template>
                <small v-else>Sin consulta</small>
            </div>
        </div>

        <!-- DÍA / SEMANA -->
        <template v-else>
            <p v-if="modo === 'semana' && doctor" class="muted small" style="margin-bottom: 10px">
                Semana de {{ doctor.name }}: {{ doctor.days.map((x) => DIAS[x]).join(', ') }}, {{ doctor.start }} a {{ doctor.end }}, cada {{ doctor.duration }} min.
            </p>
            <div class="legend" style="margin-bottom: 10px"><Chip v-for="l in leyenda" :key="l" :valor="l" /></div>
            <div v-if="modo === 'semana' && !doctor" class="card empty"><b>Sin médicos para estos filtros</b>Ajusta especialidad o consultorio.</div>
            <div v-else-if="!columnasVista.length" class="card empty" style="margin-top: 12px">
                <b>No hay consulta programada este día</b>Ningún médico con estos filtros atiende el {{ fmtDL(fecha).toLowerCase() }}. Usa la próxima disponibilidad para encontrar el siguiente espacio.
                <div style="margin-top: 12px"><Link :href="route('disponibilidad')"><Button label="Buscar disponibilidad" severity="secondary" outlined /></Link></div>
            </div>
            <div v-else class="cal">
                <div class="cal-axis">
                    <div class="hd" />
                    <div :style="{ position: 'relative', height: alto + 'px' }">
                        <span v-for="m in horasEje" :key="m" class="h" :style="{ top: (m - rango.base) * PPM + 'px' }">{{ mt(m) }}</span>
                    </div>
                </div>
                <div v-for="c in columnasVista" :key="c.key" class="cal-col" :class="{ off: c.off }">
                    <div class="hd" :class="{ today: c.hoy }"><b :title="c.titulo">{{ c.titulo }}</b><small>{{ c.sub }}</small></div>
                    <div class="bd" :style="fondo">
                        <template v-for="s in c.slots" :key="s.time + (s.appt?.id || '')">
                            <div v-if="s.appt" class="slot ap" :class="[CLASE_ESTADO[s.appt.status], { dim: atenuado(s) }]" :style="estiloSlot(s)" :title="`${s.appt.patient}, ${s.appt.status}`" @click="clickSlot(s)">
                                <span class="tm">{{ s.time }}</span><span class="pn">{{ s.appt.patient }}</span>
                                <span v-if="s.off" class="off"> fuera de horario</span>
                                <div v-if="s.dur * PPM >= 36" class="meta">
                                    <span class="dot" :class="CLASE_HC[s.appt.hc_status]" :title="'Historia clínica: ' + s.appt.hc_status" />{{ s.appt.status }}<template v-if="s.appt.debt > 0">, debe {{ money(s.appt.debt) }}</template>
                                </div>
                            </div>
                            <div v-else-if="s.state === 'Bloqueada'" class="slot blk" :class="{ dim: atenuado(s) }" :style="estiloSlot(s)" :title="'Bloqueado: ' + s.block.type" @click="clickSlot(s)">{{ s.time }} <b>{{ s.block.type }}</b></div>
                            <div v-else-if="s.state === 'Disponible'" class="slot free" :class="{ dim: atenuado(s) }" :style="estiloSlot(s)" role="button" tabindex="0" :aria-label="'Agendar ' + s.time" @click="clickSlot(s, c.docId, c.fecha)" @keydown.enter="clickSlot(s, c.docId, c.fecha)">
                                {{ s.time }}<span v-if="puede('cita')" class="add">Agendar</span>
                            </div>
                            <div v-else class="slot past" :class="{ dim: atenuado(s) }" :style="estiloSlot(s)">{{ s.time }}</div>
                        </template>
                        <div v-if="lineaAhora(c.fecha)" class="nowline" :style="{ top: (ahora - rango.base) * PPM + 'px' }" />
                    </div>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
