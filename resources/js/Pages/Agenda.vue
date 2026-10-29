<script setup>
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Segmento from '@/Components/Segmento.vue';
import Chip from '@/Components/Chip.vue';
import DateNav from '@/Components/DateNav.vue';
import { ui, usePermisos } from '@/lib/ui';
import { filtrar } from '@/lib/api';
import { DIAS, DSH, MESES, parseISO, fmtDL, mt, money, hoyISO, toISO, initials, COLOR_ESTADO, COLOR_HC } from '@/lib/util';

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
// Fondo del día: bandas alternas por hora, línea fuerte en cada hora y punteada en la media hora.
const fondo = computed(() => {
    const h = 60 * PPM;
    return {
        height: alto.value + 'px',
        backgroundImage: [
            `repeating-linear-gradient(to bottom, #e3e9f1 0 1px, transparent 1px ${h}px)`,
            `repeating-linear-gradient(to bottom, transparent 0 ${h / 2}px, #eef2f7 ${h / 2}px ${h / 2 + 1}px, transparent ${h / 2 + 1}px ${h}px)`,
            `repeating-linear-gradient(to bottom, #fbfcfe 0 ${h}px, #f4f7fb ${h}px ${2 * h}px)`,
        ].join(','),
    };
});

function estiloSlot(s) {
    const e = { top: (s.m - rango.value.base) * PPM + 2 + 'px', height: Math.max(s.dur * PPM - 4, 18) + 'px' };
    if (s.appt) e['--ec'] = COLOR_ESTADO[s.appt.status] || '#64748b';
    return e;
}
function atenuado(s) { const f = props.filtros; return (f.st && s.state !== f.st) || (f.type && (!s.appt || s.appt.type !== f.type)); }
function clickSlot(s, docId, fecha) {
    if (s.appt) ui.abrirCita(s.appt.id);
    else if (s.state === 'Bloqueada') ui.abrir('bloqueoVer', { id: s.block.id });
    else if (s.state === 'Disponible' && puede('cita')) ui.abrir('nuevaCita', { doctor_id: docId, date: fecha, time: s.time });
}
const lineaAhora = (fecha) => fecha === hoy && props.ahora >= rango.value.base && props.ahora <= rango.value.fin;

// Cada médico conserva su color en todas las vistas.
const PALETA = ['#2b5480', '#1f9e86', '#6b4bc9', '#db2777', '#e08a1e', '#0e7490', '#16a34a', '#c2410c', '#7c3aed', '#0891b2'];
const colorMedico = (id) => PALETA[id % PALETA.length];
const plural = (n, palabra) => `${n} ${palabra}${n === 1 ? '' : 's'}`;
const resumen = (slots) => ({ citas: slots.filter((x) => x.appt).length, libres: slots.filter((x) => x.state === 'Disponible').length });

const columnasVista = computed(() => {
    if (props.modo === 'dia') return (props.columnas || []).map((c) => ({ key: c.doctor.id, docId: c.doctor.id, fecha: props.fecha, hoy: props.fecha === hoy, off: false, color: colorMedico(c.doctor.id),
        titulo: c.doctor.name, sub: c.doctor.specialty, sala: c.doctor.room, av: initials(c.doctor.name), slots: c.slots, ...resumen(c.slots) }));
    return (props.columnas || []).map((c) => {
        const d = parseISO(c.date);
        return { key: c.date, docId: props.doctor.id, fecha: c.date, hoy: c.date === hoy, off: !c.works, color: colorMedico(props.doctor.id), dia: DSH[d.getDay()], num: d.getDate(),
            titulo: DIAS[d.getDay()], sub: c.works ? null : 'Sin consulta', slots: c.slots, ...resumen(c.slots) };
    });
});
// Totales de la vista para la barra de resumen.
const totales = computed(() => {
    const t = { Disponible: 0, Bloqueada: 0 };
    columnasVista.value.forEach((c) => c.slots.forEach((s) => {
        const k = s.appt ? s.appt.status : s.state;
        t[k] = (t[k] || 0) + 1;
    }));
    t.citas = columnasVista.value.reduce((n, c) => n + c.citas, 0);
    return t;
});
// Ocupación del día en la vista de mes: verde, ámbar o rojo según qué tan lleno está.
const ocupacion = (c) => Math.round((c.citas * 100) / c.total);
const colorOcupacion = (pct) => (pct >= 85 ? '#d64545' : pct >= 60 ? '#e08a1e' : '#1f9e86');
const fechaTitulo = computed(() => { const d = parseISO(props.fecha); return `${DIAS[d.getDay()]} ${d.getDate()} de ${MESES[d.getMonth()]}`; });
const etiquetaMes = computed(() => { const d = parseISO(props.fecha); return `${MESES[d.getMonth()]} ${d.getFullYear()}`; });
</script>

<template>
    <AppLayout titulo="Agenda médica" subtitulo="Disponibilidad generada a partir de reglas de horario">
        <!-- Barra superior: vista, fecha, resumen y filtros -->
        <div class="ag-bar">
            <div class="ag-fila">
                <Segmento :model-value="modo" :opciones="[{ l: 'Día', v: 'dia', i: 'pi pi-stop' }, { l: 'Semana', v: 'semana', i: 'pi pi-pause' }, { l: 'Mes', v: 'mes', i: 'pi pi-th-large' }]" @update:model-value="ir({ modo: $event })" />
                <div v-if="modo === 'mes'" class="row" style="gap: 6px">
                    <Button icon="pi pi-chevron-left" size="small" severity="secondary" outlined @click="moverMes(-1)" />
                    <Button label="Hoy" size="small" severity="secondary" outlined @click="ir({ fecha: hoy })" />
                    <Button icon="pi pi-chevron-right" size="small" severity="secondary" outlined @click="moverMes(1)" />
                </div>
                <DateNav v-else :fecha="fecha" :paso="modo === 'semana' ? 7 : 1" etiqueta=" " @cambiar="ir({ fecha: $event })" />
                <div class="ag-titulo">
                    <i class="pi pi-calendar" />
                    <b v-if="modo === 'mes'" style="text-transform: capitalize">{{ etiquetaMes }}</b>
                    <b v-else-if="modo === 'semana' && doctor">Semana de {{ doctor.name }}</b>
                    <b v-else>{{ fechaTitulo }}</b>
                </div>
                <div class="sp" />
                <Button v-if="puede('bloqueo')" label="Bloquear horario" icon="pi pi-ban" severity="secondary" outlined @click="ui.abrir('bloqueo', { scope: 'doctor', target_id: filtros.doc || 0, date: fecha })" />
            </div>
            <div class="filtros ag-filtros">
                <Select :model-value="filtros.spec" :options="op.especialidades" option-label="name" option-value="id" placeholder="Todas las especialidades" show-clear :disabled="bloqueado" @update:model-value="ir({ spec: $event })" />
                <Select :model-value="filtros.doc" :options="medicosFiltro" option-label="name" option-value="id" :placeholder="modo === 'semana' ? 'Elige un médico' : 'Todos los médicos'" show-clear filter :disabled="bloqueado" @update:model-value="ir({ doc: $event })" />
                <Select :model-value="filtros.room" :options="consultorios" option-label="name" option-value="id" placeholder="Todos los consultorios" show-clear @update:model-value="ir({ room: $event })" />
                <Select :model-value="filtros.st" :options="estados" placeholder="Todos los estados" show-clear @update:model-value="ir({ st: $event })" />
                <Select :model-value="filtros.type" :options="page.props.catalogo.tiposConsulta" placeholder="Todos los tipos" show-clear @update:model-value="ir({ type: $event })" />
                <Button v-if="hayFiltros" label="Limpiar" icon="pi pi-filter-slash" size="small" severity="danger" text @click="limpiar" />
            </div>
        </div>

        <!-- MES -->
        <div v-if="modo === 'mes'" class="month ag-mes">
            <div v-for="(d, i) in ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']" :key="d" class="dh" :class="{ fin: i > 4 }">{{ d }}</div>
            <div v-for="c in celdas" :key="c.date" class="dc" :class="{ out: !c.in, today: c.date === hoy, fin: [0, 6].includes(parseISO(c.date).getDay()) }" @click="ir({ fecha: c.date, modo: 'dia' })">
                <span class="n">{{ parseISO(c.date).getDate() }}</span>
                <template v-if="c.total">
                    <div class="ag-mes-pills">
                        <span class="p-citas"><i class="pi pi-calendar" />{{ plural(c.citas, 'cita') }}</span>
                        <span class="p-libres"><i class="pi pi-check-circle" />{{ plural(c.libres, 'libre') }}</span>
                    </div>
                    <div class="ag-ocup" :style="{ '--oc': colorOcupacion(ocupacion(c)) }">
                        <div class="prog"><i :style="{ width: ocupacion(c) + '%' }" /></div>
                        <small>{{ ocupacion(c) }}%</small>
                    </div>
                </template>
                <small v-else class="ag-sin">Sin consulta</small>
            </div>
        </div>

        <!-- DÍA / SEMANA -->
        <template v-else>
            <div class="ag-leyenda">
                <span class="ag-tot"><b>{{ totales.citas }}</b> {{ totales.citas === 1 ? 'cita' : 'citas' }}</span>
                <button v-for="l in leyenda" :key="l" type="button" class="ag-ley" :class="{ on: filtros.st === l }" :style="{ '--lc': COLOR_ESTADO[l] }" :title="'Filtrar: ' + l" @click="ir({ st: filtros.st === l ? null : l })">
                    <span class="d" />{{ l }}<b>{{ totales[l] || 0 }}</b>
                </button>
                <span v-if="modo === 'semana' && doctor" class="ag-horario"><i class="pi pi-clock" />{{ doctor.days.map((x) => DSH[x]).join(', ') }} · {{ doctor.start }} a {{ doctor.end }} · cada {{ doctor.duration }} min</span>
            </div>
            <div v-if="modo === 'semana' && !doctor" class="card empty"><i class="pi pi-user" style="font-size: 26px; color: #b6c3d1" /><b>Sin médicos para estos filtros</b>Ajusta especialidad o consultorio.</div>
            <div v-else-if="!columnasVista.length" class="card empty" style="margin-top: 4px">
                <i class="pi pi-calendar-times" style="font-size: 28px; color: #b6c3d1" />
                <b>No hay consulta programada este día</b>Ningún médico con estos filtros atiende el {{ fmtDL(fecha).toLowerCase() }}. Usa la próxima disponibilidad para encontrar el siguiente espacio.
                <div style="margin-top: 12px"><Link :href="route('disponibilidad')"><Button label="Buscar disponibilidad" icon="pi pi-search" severity="secondary" outlined /></Link></div>
            </div>
            <div v-else class="cal ag-cal">
                <div class="cal-axis">
                    <div class="hd"><i class="pi pi-clock" /></div>
                    <div :style="{ position: 'relative', height: alto + 'px' }">
                        <span v-for="m in horasEje" :key="m" class="h" :style="{ top: (m - rango.base) * PPM + 'px' }">{{ mt(m) }}</span>
                    </div>
                </div>
                <div v-for="c in columnasVista" :key="c.key" class="cal-col" :class="{ off: c.off, hoy: c.hoy }" :style="{ '--dc': c.color }">
                    <div class="hd">
                        <template v-if="modo === 'dia'">
                            <span class="ag-av">{{ c.av }}</span>
                            <div class="ag-hd-t">
                                <b :title="c.titulo">{{ c.titulo }}</b>
                                <small :title="c.sub + (c.sala ? ' · ' + c.sala : '')">{{ c.sub }}<template v-if="c.sala"> · {{ c.sala }}</template></small>
                                <span class="ag-hd-n"><i>{{ plural(c.citas, 'cita') }}</i><i class="l">{{ plural(c.libres, 'libre') }}</i></span>
                            </div>
                        </template>
                        <template v-else>
                            <span class="ag-dia"><small>{{ c.dia }}</small><b>{{ c.num }}</b></span>
                            <div class="ag-hd-t">
                                <b>{{ c.titulo }}</b>
                                <small v-if="c.sub">{{ c.sub }}</small>
                                <span v-else class="ag-hd-n"><i>{{ plural(c.citas, 'cita') }}</i><i class="l">{{ plural(c.libres, 'libre') }}</i></span>
                            </div>
                        </template>
                    </div>
                    <div class="bd" :style="fondo">
                        <template v-for="s in c.slots" :key="s.time + (s.appt?.id || '')">
                            <div v-if="s.appt" class="slot ap" :class="{ dim: atenuado(s), corto: s.dur * PPM < 40 }" :style="estiloSlot(s)" :title="`${s.time} · ${s.appt.patient} · ${s.appt.status}`" @click="clickSlot(s)">
                                <div class="ap-l1"><span class="tm">{{ s.time }}</span><span class="pn">{{ s.appt.patient }}</span></div>
                                <div v-if="s.dur * PPM >= 40" class="ap-l2">
                                    <span class="hc" :style="{ background: COLOR_HC[s.appt.hc_status] }" :title="'Historia clínica: ' + s.appt.hc_status" />
                                    <span class="st">{{ s.appt.status }}</span>
                                    <span v-if="s.appt.debt > 0" class="debe">debe {{ money(s.appt.debt) }}</span>
                                    <span v-if="s.off" class="fuera">fuera de horario</span>
                                </div>
                            </div>
                            <div v-else-if="s.state === 'Bloqueada'" class="slot blk" :class="{ dim: atenuado(s) }" :style="estiloSlot(s)" :title="'Bloqueado: ' + s.block.type" @click="clickSlot(s)"><i class="pi pi-lock" /><span>{{ s.time }}</span><b>{{ s.block.type }}</b></div>
                            <div v-else-if="s.state === 'Disponible'" class="slot free" :class="{ dim: atenuado(s) }" :style="estiloSlot(s)" role="button" tabindex="0" :aria-label="'Agendar ' + s.time" @click="clickSlot(s, c.docId, c.fecha)" @keydown.enter="clickSlot(s, c.docId, c.fecha)">
                                <span>{{ s.time }}</span><span v-if="puede('cita')" class="add"><i class="pi pi-plus" />Agendar</span>
                            </div>
                            <div v-else class="slot past" :class="{ dim: atenuado(s) }" :style="estiloSlot(s)">{{ s.time }}</div>
                        </template>
                        <div v-if="lineaAhora(c.fecha)" class="nowline" :style="{ top: (ahora - rango.base) * PPM + 'px' }"><span>{{ mt(ahora) }}</span></div>
                    </div>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
