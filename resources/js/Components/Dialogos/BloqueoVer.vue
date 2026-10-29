<script setup>
import Modal from '@/Components/Modal.vue';
import SeccionModal from '@/Components/SeccionModal.vue';
import Traza from '@/Components/Traza.vue';
import { ref, computed, watch } from 'vue';
import { ui, usePermisos } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';
import { fmtDL, fmtDS, DIAS } from '@/lib/util';

const { puede } = usePermisos();
const visible = computed({ get: () => ui.dialogo === 'bloqueoVer', set: (v) => { if (!v) ui.cerrar(); } });
const b = ref(null);
watch(visible, async (v) => { b.value = null; if (v) b.value = await api.get(route('api.bloqueos.show', ui.datos.id)); });

async function quitar() {
    const r = await api.del(route('api.bloqueos.destroy', b.value.id));
    avisar(r.message);
    ui.cerrar();
    refrescar();
}
</script>

<template>
    <Modal icono="pi pi-lock" color="#64748b" v-model:visible="visible" header="Bloqueo de agenda" :subtitulo="b ? b.target : ''" ancho="560px">
        <SeccionModal v-if="b" :titulo="b.type" icono="pi pi-ban" color="#64748b" ayuda="Detalle del bloqueo">
        <dl class="kv" style="margin: 0">
            <dt>Tipo</dt><dd><b style="font-weight: 500">{{ b.type }}</b></dd>
            <dt>Aplica a</dt><dd>{{ b.target }}</dd>
            <dt>Fecha</dt><dd>{{ b.recurring ? `Cada ${b.days.map((x) => DIAS[x].toLowerCase()).join(', ')} desde ${fmtDS(b.date)}${b.until ? ' hasta ' + fmtDS(b.until) : ''}` : fmtDL(b.date) }}</dd>
            <dt>Horario</dt><dd>{{ b.start === '00:00' && b.end === '23:59' ? 'Todo el día' : `${b.start} a ${b.end}` }}</dd>
            <template v-if="b.notes"><dt>Observaciones</dt><dd>{{ b.notes }}</dd></template>
            <dt>Registró</dt><dd><Traza :r="b" /></dd>
        </dl>
        </SeccionModal>
        <template #footer>
            <Button v-if="puede('bloqueo') && b" label="Quitar bloqueo" icon="pi pi-trash" severity="danger" outlined @click="quitar" />
            <Button label="Cerrar" severity="secondary" outlined @click="ui.cerrar()" />
        </template>
    </Modal>
</template>
