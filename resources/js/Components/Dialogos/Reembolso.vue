<script setup>
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
    <Dialog v-model:visible="visible" modal header="Reembolsar anticipo" :style="{ width: '500px' }" :breakpoints="{ '560px': '96vw' }">
        <p>Se registrará el reembolso de {{ money(a.paid) }} a {{ a.patient }}.</p>
        <div class="field"><label for="rf-n">Motivo</label><InputText id="rf-n" v-model="motivo" /></div>
        <template #footer>
            <Button label="Cancelar" text @click="ui.cerrar()" />
            <Button label="Registrar reembolso" :loading="guardando" @click="guardar" />
        </template>
    </Dialog>
</template>
