<script setup>
import { ref, computed, watch } from 'vue';
import { ui } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';
import HistoriaContenido from '@/Components/HistoriaContenido.vue';

const visible = computed({ get: () => ui.dialogo === 'historia', set: (v) => { if (!v) ui.cerrar(); } });
const h = ref(null);
const pidiendo = ref(false);
const solicitud = ref('');
watch(visible, async (v) => {
    h.value = null;
    pidiendo.value = false;
    solicitud.value = '';
    if (v) {
        try { h.value = await api.get(route('api.citas.historia', ui.datos.id)); } catch { ui.cerrar(); }
    }
});

async function revisada() {
    const r = await api.post(route('api.citas.revisada', h.value.id));
    avisar(r.message);
    ui.cerrar();
    refrescar();
    ui.recargarCita();
}
async function solicitar() {
    const r = await api.post(route('api.citas.actualizacion', h.value.id), { request: solicitud.value });
    avisar(r.message);
    ui.cerrar();
    refrescar();
    ui.recargarCita();
}
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="h ? 'Historia clínica: ' + h.patient : 'Historia clínica'" :style="{ width: '860px' }" :breakpoints="{ '900px': '96vw' }">
        <template v-if="h && !pidiendo"><HistoriaContenido :resumen="h.resumen" :secciones="h.secciones" :estado="h.hc_status" /></template>
        <template v-else-if="pidiendo">
            <div class="field"><label for="ru">¿Qué información necesitas?</label><Textarea id="ru" v-model="solicitud" rows="3" placeholder="Ej. adjuntar resonancia reciente, aclarar dosis de medicamento" /></div>
            <p class="muted small">Se enviará un mensaje al paciente con el enlace para actualizar su historia clínica.</p>
        </template>
        <div v-else class="empty"><i class="pi pi-spin pi-spinner" /></div>
        <template #footer>
            <template v-if="pidiendo">
                <Button label="Volver" text @click="pidiendo = false" />
                <Button label="Enviar solicitud" icon="pi pi-send" :disabled="!solicitud.trim()" @click="solicitar" />
            </template>
            <template v-else>
                <template v-if="h?.puedeRevisar">
                    <Button label="Solicitar actualización" severity="secondary" outlined @click="pidiendo = true" />
                    <Button label="Marcar como revisada" icon="pi pi-check" @click="revisada" />
                </template>
                <Button label="Cerrar" severity="secondary" outlined @click="ui.cerrar()" />
            </template>
        </template>
    </Dialog>
</template>
