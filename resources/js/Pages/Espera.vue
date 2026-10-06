<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import { ui, usePermisos } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';
import { fmtD } from '@/lib/util';

defineProps({ lista: Array });
const { puede } = usePermisos();
const buscando = ref(null);
const espacios = ref([]);

async function estado(w, status) {
    await api.patch(route('api.espera.estado', w.id), { status });
    avisar('Estado actualizado');
    refrescar();
}
async function buscar(w) {
    espacios.value = await api.get(route('api.espera.espacios', w.id));
    buscando.value = w;
}
function agendar(x, t) {
    const w = buscando.value;
    buscando.value = null;
    ui.abrir('nuevaCita', { doctor_id: x.doctor_id, date: x.date, time: t, waitlist_id: w.id, patient_id: w.patient_id, reason: w.notes });
}
</script>

<template>
    <AppLayout titulo="Lista de espera" subtitulo="Pacientes sin disponibilidad inmediata">
        <div class="vh">
            <p class="muted">Cuando se cancela o reagenda una cita, el sistema propone a los pacientes compatibles.</p>
            <div class="sp" />
            <Button v-if="puede('cita')" label="Agregar paciente" icon="pi pi-plus" @click="ui.abrir('nuevaEspera', {})" />
        </div>
        <div class="card tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Paciente</th><th>Especialidad</th><th>Médico</th><th>Solicitud</th><th>Preferencia</th><th>Prioridad</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="w in lista" :key="w.id">
                        <td>{{ w.patient }}<div v-if="w.notes" class="muted small">{{ w.notes }}</div></td>
                        <td>{{ w.specialty }}</td>
                        <td>{{ w.doctor || '' }}<span v-if="!w.doctor" class="muted">Cualquiera</span></td>
                        <td>{{ fmtD(w.requested_at) }}</td>
                        <td>{{ w.preference }}</td>
                        <td><b v-if="w.priority === 'Alta'" style="color: var(--danger); font-weight: 600">Alta</b><template v-else>{{ w.priority }}</template></td>
                        <td><Chip :valor="w.status" tipo="espera" /></td>
                        <td style="white-space: nowrap">
                            <template v-if="['En espera', 'Contactado'].includes(w.status) && puede('cita')">
                                <Button label="Buscar espacio" size="small" @click="buscar(w)" />
                                <Button :label="w.status === 'En espera' ? 'Marcar contactado' : 'Regresar a espera'" size="small" text @click="estado(w, w.status === 'En espera' ? 'Contactado' : 'En espera')" />
                                <Button label="Descartar" size="small" text severity="secondary" @click="estado(w, 'Descartado')" />
                            </template>
                            <Button v-else-if="w.appointment_id" label="Ver cita" size="small" severity="secondary" outlined @click="ui.abrirCita(w.appointment_id)" />
                        </td>
                    </tr>
                    <tr v-if="!lista.length"><td colspan="8"><div class="empty"><b>No hay pacientes en espera</b></div></td></tr>
                </tbody>
            </table>
        </div>

        <Dialog :visible="!!buscando" modal :header="buscando ? 'Espacios para ' + buscando.patient : ''" :style="{ width: '720px' }" :breakpoints="{ '760px': '96vw' }" @update:visible="buscando = null">
            <template v-if="buscando">
                <template v-if="espacios.length">
                    <p class="muted small">{{ buscando.specialty }}<template v-if="buscando.doctor">, {{ buscando.doctor }}</template>, preferencia: {{ buscando.preference.toLowerCase() }}.</p>
                    <div v-for="x in espacios" :key="x.date + x.doctor_id">
                        <b style="font-weight: 500">{{ fmtD(x.date) }}</b> <span class="muted small">{{ x.doctor }}</span>
                        <div class="times" style="margin-top: 6px"><button v-for="t in x.times" :key="t" type="button" @click="agendar(x, t)">{{ t }}</button></div>
                    </div>
                </template>
                <div v-else class="empty"><b>Sin espacios compatibles en 45 días</b>El paciente seguirá en espera.</div>
            </template>
            <template #footer><Button label="Cerrar" text @click="buscando = null" /></template>
        </Dialog>
    </AppLayout>
</template>
