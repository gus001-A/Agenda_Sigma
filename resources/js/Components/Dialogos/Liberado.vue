<script setup>
import Modal from '@/Components/Modal.vue';
import SeccionModal from '@/Components/SeccionModal.vue';
import { computed } from 'vue';
import { ui } from '@/lib/ui';
import { fmtDL, fmtD } from '@/lib/util';

/** Se liberó un espacio: se proponen pacientes compatibles de la lista de espera. */
const visible = computed({ get: () => ui.dialogo === 'liberado', set: (v) => { if (!v) ui.cerrar(); } });
const d = computed(() => ui.datos || {});
function agendar(w) {
    ui.abrir('nuevaCita', { doctor_id: d.value.doctor_id, date: d.value.date, time: d.value.time, waitlist_id: w.id, patient_id: w.patient_id, reason: w.notes });
}
</script>

<template>
    <Modal icono="pi pi-bell" color="#e08a1e" subtitulo="Pacientes compatibles de la lista de espera" v-model:visible="visible" header="Se liberó un espacio" ancho="760px">
        <div class="note"><i class="pi pi-calendar-plus" /> Espacio libre: <b>{{ d.doctor }}, {{ fmtDL(d.date) }} a las {{ d.time }}</b></div>
        <SeccionModal titulo="Pacientes compatibles" icono="pi pi-users" color="#e08a1e" ayuda="Por especialidad, médico y preferencia de horario">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Paciente</th><th>Prioridad</th><th>Preferencia</th><th>Solicitó</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="w in d.candidatos" :key="w.id">
                        <td>{{ w.patient }}<div class="muted small">{{ w.notes }}</div></td>
                        <td>{{ w.priority }}</td><td>{{ w.preference }}</td><td>{{ fmtD(w.requested_at) }}</td>
                        <td><Button label="Agendar" icon="pi pi-calendar-plus" size="small" @click="agendar(w)" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
        </SeccionModal>
        <template #footer><Button label="Dejar el horario libre" text @click="ui.cerrar()" /></template>
    </Modal>
</template>
