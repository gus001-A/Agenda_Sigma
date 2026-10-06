<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import Kpi from '@/Components/Kpi.vue';
import TablaDatos from '@/Components/TablaDatos.vue';
import { ui, usePermisos } from '@/lib/ui';
import { api, avisar, refrescar, filtrar } from '@/lib/api';
import { fmtD, COLOR_HC } from '@/lib/util';

const props = defineProps({ rango: String, estado: String, conteo: Object, lista: Array });
const { puede } = usePermisos();
const ir = (c) => filtrar('historias', { rango: props.rango, estado: props.estado, ...c });
const rangos = [{ l: 'Hoy', v: '0' }, { l: 'Próximos 3 días', v: '3' }, { l: 'Próximos 7 días', v: '7' }, { l: 'Próximos 14 días', v: '14' }, { l: 'Todas las fechas', v: 'todas' }];
const ICONOS = { Pendiente: 'pi pi-hourglass', 'En proceso': 'pi pi-pencil', Completado: 'pi pi-check-circle', Revisado: 'pi pi-verified', 'Requiere actualización': 'pi pi-exclamation-circle' };
const unicos = (k) => [...new Set(props.lista.map((x) => x[k]).filter(Boolean))].sort();
const filas = computed(() => props.lista.map((a) => ({ ...a, cita: `${fmtD(a.date)} ${a.time}` })));
const columnas = computed(() => [
    { campo: 'cita', titulo: 'Cita', filtro: 'texto', ancho: '140px' },
    { campo: 'patient', titulo: 'Paciente', filtro: 'texto', ancho: '200px' },
    { campo: 'doctor', titulo: 'Médico', filtro: 'lista', opciones: unicos('doctor') },
    { campo: 'form', titulo: 'Formato', filtro: 'lista', opciones: unicos('form') },
    { campo: 'progress', titulo: 'Avance', ancho: '130px' },
    { campo: 'hc_status', titulo: 'Estado', filtro: 'lista', opciones: Object.keys(ICONOS) },
    { campo: 'acciones', titulo: 'Acciones', ordenar: false, ancho: '270px' },
]);
async function reenviar(a) { const r = await api.post(route('api.citas.reenviar', a.id)); avisar(r.message); refrescar(); }
async function portal(a) { const c = await api.get(route('api.citas.show', a.id)); window.open(c.portal_url, '_blank'); }
</script>

<template>
    <AppLayout titulo="Historias clínicas" subtitulo="Seguimiento del llenado de preconsulta">
        <template #acciones><Link :href="route('notificaciones')"><Button label="Recordatorios" icon="pi pi-bell" severity="secondary" outlined /></Link></template>
        <div class="kpis linea">
            <Kpi v-for="(n, k) in conteo" :key="k" :etiqueta="k" :valor="n" :icono="ICONOS[k]" :color="COLOR_HC[k]" clic :seleccionado="estado === k" @click="ir({ estado: estado === k ? null : k })" />
        </div>
        <div class="vh">
            <Select :model-value="rango" :options="rangos" option-label="l" option-value="v" @update:model-value="ir({ rango: $event })" />
            <Button v-if="estado" :label="'Quitar filtro: ' + estado" icon="pi pi-filter-slash" size="small" text @click="ir({ estado: null })" />
        </div>
        <TablaDatos :filas="filas" :columnas="columnas" clicable vacio="Sin historias clínicas con este filtro" @fila="(a) => ui.abrirCita(a.id)">
            <template #c-patient="{ fila }"><b style="font-weight: 600">{{ fila.patient }}</b></template>
            <template #c-form="{ fila }">{{ fila.form }} <span class="tag">v{{ fila.version }}</span></template>
            <template #c-progress="{ fila }">
                <div class="row" style="gap: 6px; flex-wrap: nowrap"><div class="prog" style="width: 70px"><i :style="{ width: fila.progress + '%' }" /></div><span class="small muted">{{ fila.progress }}%</span></div>
            </template>
            <template #c-hc_status="{ fila }"><Chip :valor="fila.hc_status" tipo="hc" /></template>
            <template #c-acciones="{ fila }">
                <div class="row" style="gap: 4px; flex-wrap: nowrap" @click.stop>
                    <Button v-if="puede('verclinico') && fila.hc_status !== 'Pendiente'" label="Ver" icon="pi pi-eye" size="small" severity="secondary" outlined @click="ui.abrir('historia', { id: fila.id })" />
                    <template v-if="['Pendiente', 'En proceso', 'Requiere actualización'].includes(fila.hc_status)">
                        <Button v-if="puede('cita')" label="Reenviar" icon="pi pi-send" size="small" severity="info" outlined @click="reenviar(fila)" />
                        <Button v-tooltip.top="'Llenar como paciente'" icon="pi pi-mobile" size="small" text rounded @click="portal(fila)" />
                    </template>
                </div>
            </template>
        </TablaDatos>
    </AppLayout>
</template>
