<script setup>
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ui } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';

const page = usePage();
const visible = computed({ get: () => ui.dialogo === 'editarCita', set: (v) => { if (!v) ui.cerrar(); } });
const a = computed(() => ui.datos.cita || {});
const f = ref({});
const guardando = ref(false);
watch(visible, (v) => { if (v) f.value = { type: a.value.type, cost: a.value.cost, reason: a.value.reason, notes: a.value.notes, channel: a.value.channel }; });

async function guardar() {
    guardando.value = true;
    try {
        const r = await api.put(route('api.citas.update', a.value.id), f.value);
        avisar(r.message);
        ui.cerrar();
        refrescar();
        ui.recargarCita();
    } finally { guardando.value = false; }
}
</script>

<template>
    <Dialog v-model:visible="visible" modal header="Editar datos de la cita" :style="{ width: '560px' }" :breakpoints="{ '600px': '96vw' }">
        <div class="grid2">
            <div class="field"><label>Tipo de consulta</label><Select v-model="f.type" :options="page.props.catalogo.tiposConsulta" /></div>
            <div class="field"><label>Costo</label><InputNumber v-model="f.cost" mode="currency" currency="MXN" locale="es-MX" :min="0" fluid /></div>
        </div>
        <div class="field"><label for="e-reason">Motivo</label><InputText id="e-reason" v-model="f.reason" /></div>
        <div class="field"><label for="e-notes">Observaciones</label><Textarea id="e-notes" v-model="f.notes" rows="3" /></div>
        <div class="field"><label>Canal de notificación</label><Select v-model="f.channel" :options="page.props.catalogo.canales" /></div>
        <p v-if="a.hc_status === 'Pendiente'" class="muted small">Si cambias el tipo de consulta se reasignará el formato clínico correspondiente.</p>
        <template #footer>
            <Button label="Cancelar" text @click="ui.cerrar()" />
            <Button label="Guardar cambios" icon="pi pi-check" :loading="guardando" @click="guardar" />
        </template>
    </Dialog>
</template>
