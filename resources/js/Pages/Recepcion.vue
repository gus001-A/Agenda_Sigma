<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import DateNav from '@/Components/DateNav.vue';
import { ui, usePermisos } from '@/lib/ui';
import { filtrar } from '@/lib/api';
import { fmtDL } from '@/lib/util';

const props = defineProps({ fecha: String, filtros: Object, filas: Array, inactivas: Array });
const page = usePage();
const { puede } = usePermisos();
const medicos = computed(() => page.props.opciones.medicos);
const ir = (c) => filtrar('recepcion', { fecha: props.fecha, doc: props.filtros.doc, ocultar: props.filtros.ocultar ? 1 : null, ...c });
function clickFila(r) { if (r.appt) ui.abrirCita(r.appt.id); }
</script>

<template>
    <AppLayout titulo="Recepción del día" subtitulo="Pantalla operativa de citas">
        <div class="vh">
            <DateNav :fecha="fecha" @cambiar="ir({ fecha: $event })" />
            <div class="sp" />
            <Select :model-value="filtros.doc" :options="medicos" option-label="name" option-value="id" placeholder="Todos los médicos" show-clear filter @update:model-value="ir({ doc: $event })" />
            <label class="chk"><Checkbox :model-value="filtros.ocultar" binary @update:model-value="ir({ ocultar: $event ? 1 : null })" />Ocultar espacios libres</label>
        </div>
        <div class="card tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Hora</th><th>Paciente</th><th>Especialidad</th><th>Médico</th><th>Estado</th><th>Historia clínica</th><th>Anticipo</th><th>Acción</th></tr></thead>
                <tbody>
                    <tr v-for="(r, i) in filas" :key="i" :class="{ click: r.appt }" @click="clickFila(r)">
                        <td><b>{{ r.time }}</b></td>
                        <td>{{ r.appt ? r.appt.patient : '—' }}<span v-if="r.off" class="small" style="color: var(--danger)"> fuera de horario</span></td>
                        <td>{{ r.specialty }}</td>
                        <td>{{ r.doctor }}</td>
                        <td><Chip :valor="r.state" /> <span v-if="r.block" class="muted small">{{ r.block.type }}</span></td>
                        <td><Chip v-if="r.appt" :valor="r.appt.hc_status" tipo="hc" /><span v-else>—</span></td>
                        <td><Chip v-if="r.appt" :valor="r.appt.deposit_state" tipo="dep" /><span v-else>—</span></td>
                        <td @click.stop>
                            <Button v-if="r.state === 'Disponible' && puede('cita')" label="Agendar" size="small" severity="secondary" outlined @click="ui.abrir('nuevaCita', { doctor_id: r.doctor_id, date: fecha, time: r.time })" />
                            <Button v-else-if="r.state === 'Bloqueada'" label="Administrar" size="small" severity="secondary" outlined @click="ui.abrir('bloqueoVer', { id: r.block.id })" />
                            <Button v-else-if="r.appt && r.appt.status === 'Anticipo pendiente' && puede('anticipo')" label="Gestionar" size="small" severity="warn" @click="ui.abrirCita(r.appt.id)" />
                            <Button v-else-if="r.appt" label="Ver" size="small" severity="secondary" outlined @click="ui.abrirCita(r.appt.id)" />
                            <span v-else class="muted">—</span>
                        </td>
                    </tr>
                    <tr v-if="!filas.length"><td colspan="8"><div class="empty"><b>Sin agenda para este día</b>No hay médicos con consulta el {{ fmtDL(fecha).toLowerCase() }}.</div></td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="inactivas.length" class="card" style="margin-top: 14px">
            <header><h3>Canceladas y reagendadas del día</h3></header>
            <div class="tbl-wrap">
                <table class="tbl"><tbody>
                    <tr v-for="a in inactivas" :key="a.id" class="click" @click="ui.abrirCita(a.id)">
                        <td><b>{{ a.time }}</b></td><td>{{ a.patient }}</td><td>{{ a.doctor }}</td><td><Chip :valor="a.status" /></td><td class="muted small">{{ a.last }}</td>
                    </tr>
                </tbody></table>
            </div>
        </div>
    </AppLayout>
</template>
