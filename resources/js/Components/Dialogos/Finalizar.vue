<script setup>
import Modal from '@/Components/Modal.vue';
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
    <Modal icono="pi pi-check-circle" color="#16a34a" subtitulo="La nota se vincula al expediente del paciente" v-model:visible="visible" header="Finalizar atención" ancho="560px">
        <div class="field"><label for="att">Nota de atención (se vincula al expediente)</label><Textarea id="att" v-model="f.note" rows="4" placeholder="Diagnóstico, plan, indicaciones" /></div>
        <label class="chk"><Checkbox v-model="f.followup" binary />Programar consulta de seguimiento</label>
        <template #footer>
            <Button label="Cancelar" text @click="ui.cerrar()" />
            <Button label="Finalizar atención" icon="pi pi-check" :loading="guardando" @click="guardar" />
        </template>
    </Modal>
</template>
