<script setup>
import Modal from '@/Components/Modal.vue';
import SeccionModal from '@/Components/SeccionModal.vue';
import { ref, computed, watch } from 'vue';
import { ui } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';

const visible = computed({ get: () => ui.dialogo === 'finalizar', set: (v) => { if (!v) ui.cerrar(); } });
const a = computed(() => ui.datos.cita || {});
const f = ref({});
const guardando = ref(false);
watch(visible, (v) => { if (v) f.value = { note: '', followup: false }; });

async function guardar() {
    guardando.value = true;
    try {
        const r = await api.post(route('api.citas.finalizar', a.value.id), f.value);
        avisar(r.message);
        refrescar();
        ui.recargarCita();
        if (r.seguimiento) { ui.cerrarCita(); ui.abrir('nuevaCita', r.seguimiento); } else ui.cerrar();
    } finally { guardando.value = false; }
}
</script>

<template>
    <Modal v-model:visible="visible" titulo="Finalizar atención" :subtitulo="a.patient ? `${a.patient} · la nota se vincula al expediente` : ''" icono="pi pi-check-circle" color="#16a34a" ancho="640px">
        <SeccionModal titulo="Nota de atención" icono="pi pi-file-edit" color="#16a34a" ayuda="Diagnóstico, plan e indicaciones">
            <Textarea v-model="f.note" rows="6" auto-resize placeholder="Escribe la nota de la consulta…" fluid />
        </SeccionModal>
        <SeccionModal titulo="Seguimiento" icono="pi pi-calendar-plus" color="#2b5480">
            <label class="chk"><ToggleSwitch v-model="f.followup" />Programar consulta de seguimiento (se propone el primer espacio libre en 7 días)</label>
        </SeccionModal>
        <template #footer>
            <Button label="Cancelar" text @click="ui.cerrar()" />
            <Button label="Finalizar atención" icon="pi pi-check" severity="success" :loading="guardando" @click="guardar" />
        </template>
    </Modal>
</template>
