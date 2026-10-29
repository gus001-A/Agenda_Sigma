<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import DateNav from '@/Components/DateNav.vue';
import HistoriaContenido from '@/Components/HistoriaContenido.vue';
import Persona from '@/Components/Persona.vue';
import { ui, usePermisos } from '@/lib/ui';
import { api, avisar, refrescar, filtrar } from '@/lib/api';
import { fmtDS, fmtDL, CLASE_HC, DIAS, parseISO } from '@/lib/util';

const props = defineProps({ fecha: String, doctor: Object, bloqueado: Boolean, lista: Array, seleccion: Object, hoy: String });
const page = usePage();
const { puede } = usePermisos();
const a = computed(() => props.seleccion);
const ir = (c) => filtrar('preconsulta', { fecha: props.fecha, doc: props.bloqueado ? null : props.doctor?.id, ...c });
async function estado(s) { const r = await api.post(route('api.citas.estado', a.value.id), { status: s }); avisar(r.message); refrescar(); }
async function revisada() { const r = await api.post(route('api.citas.revisada', a.value.id)); avisar(r.message); refrescar(); }
</script>

<template>
    <AppLayout titulo="Preconsulta médica" subtitulo="Información previa a la atención">
        <div class="vh">
            <Select :model-value="doctor?.id" :options="page.props.opciones.medicos" option-label="name" option-value="id" filter :disabled="bloqueado" @update:model-value="ir({ doc: $event, cita: null })" />
            <DateNav :fecha="fecha" @cambiar="ir({ fecha: $event, cita: null })" />
        </div>
        <div v-if="!lista.length" class="card empty">
            <i class="pi pi-calendar-times" style="font-size: 24px; color: #94a3b8" />
            <b>Sin pacientes este día</b>
            {{ doctor?.works ? 'La agenda aún no tiene citas.' : `${doctor?.name} no tiene consulta los ${DIAS[parseISO(fecha).getDay()].toLowerCase()}.` }}
        </div>
        <div v-else class="split" style="grid-template-columns: 300px minmax(0, 1fr)">
            <div class="card">
                <div v-for="x in lista" :key="x.id" class="list-item" :class="{ on: a && a.id === x.id }" @click="ir({ cita: x.id })">
                    <span class="time-b">{{ x.time }}</span>
                    <div style="flex: 1; min-width: 0"><Persona :nombre="x.patient" :detalle="x.type" /></div>
                    <span class="dot" :class="CLASE_HC[x.hc_status]" :title="x.hc_status" />
                </div>
            </div>
            <div v-if="a" class="stack">
                <div class="card pad stack pc-head">
                    <div class="row" style="justify-content: space-between">
                        <Persona class="pc-persona" :nombre="a.patient" :detalle="`${a.time} · ${a.type} · ${a.form} v${a.version}`" />
                        <div class="row"><Chip :valor="a.status" /><Chip :valor="a.hc_status" tipo="hc" /></div>
                    </div>
                    <div class="acts">
                        <Button v-if="['Agendada', 'Confirmada', 'Anticipo pendiente'].includes(a.status) && a.date === hoy && puede('consulta')" label="Iniciar consulta" icon="pi pi-play" @click="estado('En consulta')" />
                        <Button v-if="a.status === 'En consulta' && puede('consulta')" label="Finalizar atención" icon="pi pi-check-circle" @click="ui.abrir('finalizar', { cita: a })" />
                        <Button v-if="puede('clinico') && ['Completado', 'Requiere actualización'].includes(a.hc_status)" label="Marcar como revisada" icon="pi pi-verified" severity="success" outlined @click="revisada" />
                        <Button v-if="puede('clinico') && a.status !== 'Atendida' && a.hc_status !== 'Pendiente'" label="Solicitar actualización" icon="pi pi-refresh" severity="warn" outlined @click="ui.abrir('historia', { id: a.id })" />
                        <Button label="Detalle de la cita" icon="pi pi-external-link" severity="secondary" text @click="ui.abrirCita(a.id)" />
                    </div>
                    <div v-if="a.attention_note" class="note"><b style="font-weight: 500">Nota de atención:</b> {{ a.attention_note }}</div>
                </div>
                <div v-if="a.anteriores.length" class="card">
                    <header><h3>Citas anteriores</h3></header>
                    <div class="tbl-wrap"><table class="tbl"><tbody>
                        <tr v-for="(x, i) in a.anteriores" :key="i"><td>{{ fmtDS(x.date) }}</td><td>{{ x.doctor }}</td><td>{{ x.reason }}</td><td><Chip :valor="x.status" /></td><td class="muted small">{{ x.note }}</td></tr>
                    </tbody></table></div>
                </div>
                <HistoriaContenido :resumen="a.resumen" :secciones="a.secciones" :estado="a.hc_status" />
            </div>
        </div>
    </AppLayout>
</template>
