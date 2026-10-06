<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import { ui, usePermisos } from '@/lib/ui';
import { api, avisar, refrescar, filtrar } from '@/lib/api';
import { fmtD } from '@/lib/util';

const props = defineProps({ rango: String, estado: String, conteo: Object, lista: Array });
const { puede } = usePermisos();
const ir = (c) => filtrar('historias', { rango: props.rango, estado: props.estado, ...c });
const rangos = [{ l: 'Hoy', v: '0' }, { l: 'Próximos 3 días', v: '3' }, { l: 'Próximos 7 días', v: '7' }, { l: 'Próximos 14 días', v: '14' }, { l: 'Todas las fechas', v: 'todas' }];
async function reenviar(a) { const r = await api.post(route('api.citas.reenviar', a.id)); avisar(r.message); refrescar(); }
async function portal(a) { const c = await api.get(route('api.citas.show', a.id)); window.open(c.portal_url, '_blank'); }
</script>

<template>
    <AppLayout titulo="Historias clínicas" subtitulo="Seguimiento del llenado de preconsulta">
        <div class="kpis">
            <div v-for="(n, k) in conteo" :key="k" class="kpi click" :class="{ sel: estado === k }" @click="ir({ estado: estado === k ? null : k })"><span>{{ k }}</span><b>{{ n }}</b></div>
        </div>
        <div class="vh">
            <Select :model-value="rango" :options="rangos" option-label="l" option-value="v" @update:model-value="ir({ rango: $event })" />
            <Button v-if="estado" :label="'Quitar filtro: ' + estado" size="small" text @click="ir({ estado: null })" />
            <div class="sp" />
            <Link :href="route('notificaciones')"><Button label="Recordatorios" icon="pi pi-bell" severity="secondary" outlined /></Link>
        </div>
        <div class="card tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Cita</th><th>Paciente</th><th>Médico</th><th>Formato</th><th>Avance</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="a in lista" :key="a.id" class="click" @click="ui.abrirCita(a.id)">
                        <td style="white-space: nowrap">{{ fmtD(a.date) }} {{ a.time }}</td>
                        <td>{{ a.patient }}</td><td>{{ a.doctor }}</td>
                        <td>{{ a.form }} <span class="tag">v{{ a.version }}</span></td>
                        <td><div class="row" style="gap: 6px; flex-wrap: nowrap"><div class="prog" style="width: 70px"><i :style="{ width: a.progress + '%' }" /></div><span class="small muted">{{ a.progress }}%</span></div></td>
                        <td><Chip :valor="a.hc_status" tipo="hc" /></td>
                        <td style="white-space: nowrap" @click.stop>
                            <Button v-if="puede('verclinico') && a.hc_status !== 'Pendiente'" label="Ver" size="small" severity="secondary" outlined @click="ui.abrir('historia', { id: a.id })" />
                            <template v-if="['Pendiente', 'En proceso', 'Requiere actualización'].includes(a.hc_status)">
                                <Button v-if="puede('cita')" label="Reenviar enlace" size="small" severity="secondary" outlined @click="reenviar(a)" />
                                <Button label="Llenar como paciente" size="small" text @click="portal(a)" />
                            </template>
                        </td>
                    </tr>
                    <tr v-if="!lista.length"><td colspan="7"><div class="empty"><b>Sin historias clínicas con este filtro</b></div></td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
