<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import TablaDatos from '@/Components/TablaDatos.vue';
import Accion from '@/Components/Accion.vue';
import Persona from '@/Components/Persona.vue';
import Fecha from '@/Components/Fecha.vue';
import Segmento from '@/Components/Segmento.vue';
import { ui, usePermisos } from '@/lib/ui';
import { api, avisar, refrescar, filtrar } from '@/lib/api';
import { fmtD, COLOR_HC, addDays, hoyISO } from '@/lib/util';

const props = defineProps({ desde: String, hasta: String, estado: String, conteo: Object, lista: Array });
const { puede } = usePermisos();
// Búsqueda por fecha de la cita. "todas" = sin fecha inicial.
const ir = (c) => filtrar('historias', { desde: props.desde || 'todas', hasta: props.hasta, estado: props.estado, ...c });
const hoy = hoyISO();
const atajos = [
    { l: 'Hoy', d: hoy, h: hoy }, { l: '3 días', d: hoy, h: addDays(hoy, 3) }, { l: '7 días', d: hoy, h: addDays(hoy, 7) },
    { l: '14 días', d: hoy, h: addDays(hoy, 14) }, { l: 'Todo', d: 'todas', h: null },
];
const atajo = computed(() => atajos.find((a) => (a.d === 'todas' ? !props.desde : a.d === props.desde) && (a.h || null) === (props.hasta || null))?.l);
// Indicadores: total del periodo con su reparto y una tarjeta por estado con porcentaje.
const total = computed(() => Object.values(props.conteo).reduce((a, b) => a + b, 0));
const pct = (n) => (total.value ? Math.round((n * 100) / total.value) : 0);
const ICONOS = { Pendiente: 'pi pi-hourglass', 'En proceso': 'pi pi-pencil', Completado: 'pi pi-check-circle', Revisado: 'pi pi-verified', 'Requiere actualización': 'pi pi-exclamation-circle' };
const unicos = (k) => [...new Set(props.lista.map((x) => x[k]).filter(Boolean))].sort();
const filas = computed(() => props.lista.map((a) => ({ ...a, cita: `${fmtD(a.date)} ${a.time}` })));
const columnas = computed(() => [
    { campo: 'cita', titulo: 'Cita', filtro: 'texto', ancho: '140px' },
    { campo: 'patient', titulo: 'Paciente', filtro: 'texto', ancho: '200px' },
    { campo: 'doctor', titulo: 'Médico', filtro: 'lista', opciones: unicos('doctor'), ancho: '210px' },
    { campo: 'form', titulo: 'Formato', filtro: 'lista', opciones: unicos('form') },
    { campo: 'progress', titulo: 'Avance', ancho: '130px' },
    { campo: 'hc_status', titulo: 'Estado', filtro: 'lista', opciones: Object.keys(ICONOS) },
    { campo: 'acciones', titulo: 'Acciones', ordenar: false, ancho: '200px' },
]);
async function reenviar(a) { const r = await api.post(route('api.citas.reenviar', a.id)); avisar(r.message); refrescar(); }
async function portal(a) { const c = await api.get(route('api.citas.show', a.id)); window.open(c.portal_url, '_blank'); }
</script>

<template>
    <AppLayout titulo="Historias clínicas" subtitulo="Seguimiento del llenado de preconsulta">
        <template #acciones><Link :href="route('notificaciones')"><Button label="Recordatorios" icon="pi pi-bell" severity="secondary" outlined /></Link></template>
        <div class="vh">
            <div class="field" style="width: 165px"><label><i class="pi pi-calendar" style="color: var(--teal)" /> Desde</label><Fecha :model-value="desde" @update:model-value="ir({ desde: $event || 'todas' })" /></div>
            <div class="field" style="width: 165px"><label><i class="pi pi-calendar" style="color: var(--teal)" /> Hasta</label><Fecha :model-value="hasta" :min="desde || undefined" @update:model-value="ir({ hasta: $event })" /></div>
            <Segmento :model-value="atajo" :opciones="atajos.map((a) => ({ l: a.l, v: a.l }))" vacio @update:model-value="(l) => { const a = atajos.find((x) => x.l === l); if (a) ir({ desde: a.d, hasta: a.h }); }" />
            <div class="sp" />
            <Button v-if="estado" :label="'Quitar filtro: ' + estado" icon="pi pi-filter-slash" size="small" severity="danger" text @click="ir({ estado: null })" />
        </div>
        <div class="hc-kpis">
            <div class="hc-total">
                <span class="hc-k">Historias en el periodo</span>
                <b>{{ total }}</b>
                <div class="hc-reparto"><i v-for="(n, k) in conteo" :key="k" :style="{ width: pct(n) + '%', background: COLOR_HC[k] }" :title="`${k}: ${n}`" /></div>
                <small>{{ pct((conteo['Completado'] || 0) + (conteo['Revisado'] || 0)) }}% completadas o revisadas</small>
            </div>
            <button v-for="(n, k) in conteo" :key="k" type="button" class="hc-est" :class="{ on: estado === k }" :style="{ '--hc': COLOR_HC[k] }" :title="estado === k ? 'Quitar filtro' : 'Filtrar: ' + k" @click="ir({ estado: estado === k ? null : k })">
                <span class="hc-ic"><i :class="ICONOS[k]" /></span>
                <span class="hc-k">{{ k }}</span>
                <span class="hc-n"><b>{{ n }}</b><small>{{ pct(n) }}%</small></span>
                <span class="hc-barra"><i :style="{ width: pct(n) + '%' }" /></span>
            </button>
        </div>
        <TablaDatos :filas="filas" :columnas="columnas" clicable vacio="Sin historias clínicas con este filtro" @fila="(a) => ui.abrirCita(a.id)">
            <template #c-patient="{ fila }"><Persona :nombre="fila.patient" /></template>
            <template #c-form="{ fila }">{{ fila.form }} <span class="tag">v{{ fila.version }}</span></template>
            <template #c-progress="{ fila }">
                <div class="row" style="gap: 6px; flex-wrap: nowrap"><div class="prog" style="width: 70px"><i :style="{ width: fila.progress + '%', background: COLOR_HC[fila.hc_status] }" /></div><span class="small" :style="{ color: COLOR_HC[fila.hc_status], fontWeight: 700 }">{{ fila.progress }}%</span></div>
            </template>
            <template #c-hc_status="{ fila }"><Chip :valor="fila.hc_status" tipo="hc" /></template>
            <template #c-acciones="{ fila }">
                <div class="accs">
                    <Accion v-if="puede('verclinico') && fila.hc_status !== 'Pendiente'" icono="pi pi-file-edit" tip="Ver historia clínica" color="cian" @click="ui.abrir('historia', { id: fila.id })" />
                    <template v-if="['Pendiente', 'En proceso', 'Requiere actualización'].includes(fila.hc_status)">
                        <Accion v-if="puede('cita')" icono="pi pi-whatsapp" tip="Recordar por WhatsApp / SMS" color="verde" @click="ui.abrir('mensaje', { cita: fila, tipo: 'historia' })" />
                        <Accion v-if="puede('cita')" icono="pi pi-send" tip="Reenviar enlace al paciente" color="rosa" @click="reenviar(fila)" />
                        <Accion icono="pi pi-mobile" tip="Llenar como paciente" color="morado" @click="portal(fila)" />
                    </template>
                    <Accion icono="pi pi-eye" tip="Ver detalle de la cita" color="azul" @click="ui.abrirCita(fila.id)" />
                </div>
            </template>
        </TablaDatos>
    </AppLayout>
</template>
