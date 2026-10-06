<script setup>
import Modal from '@/Components/Modal.vue';
import SeccionModal from '@/Components/SeccionModal.vue';
import { ref, computed, watch } from 'vue';
import { ui } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';
import { money } from '@/lib/util';

const visible = computed({ get: () => ui.dialogo === 'reembolso', set: (v) => { if (!v) ui.cerrar(); } });
const a = computed(() => ui.datos.cita || {});
const motivo = ref('');
const guardando = ref(false);
watch(visible, (v) => { if (v) motivo.value = 'Cancelación con anticipación'; });

async function guardar() {
    guardando.value = true;
    try {
        const r = await api.post(route('api.citas.reembolso', a.value.id), { reason: motivo.value });
        avisar(r.message);
        ui.cerrar();
        refrescar();
        ui.recargarCita();
    } finally { guardando.value = false; }
}
</script>

<template>
    <Modal v-model:visible="visible" titulo="Reembolsar anticipo" :subtitulo="a.patient" icono="pi pi-replay" color="#8e3b69" ancho="540px">
        <div class="kpis linea" style="margin: 0">
            <div class="kpi" style="--acc: #8e3b69"><span class="kpi-ic"><i class="pi pi-replay" /></span><div><span>Monto a reembolsar</span><b>{{ money(a.paid) }}</b></div></div>
        </div>
        <SeccionModal titulo="Motivo del reembolso" icono="pi pi-comment" color="#8e3b69" ayuda="Queda registrado en el historial de la cita y en auditoría">
            <div class="campo-ic"><i class="pi pi-pencil" /><InputText v-model="motivo" fluid /></div>
        </SeccionModal>
        <template #footer>
            <Button label="Cancelar" text @click="ui.cerrar()" />
            <Button label="Registrar reembolso" icon="pi pi-check" :loading="guardando" @click="guardar" />
        </template>
    </Modal>
</template>
