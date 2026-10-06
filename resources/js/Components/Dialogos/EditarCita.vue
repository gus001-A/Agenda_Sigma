<script setup>
import Modal from '@/Components/Modal.vue';
import SeccionModal from '@/Components/SeccionModal.vue';
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
const ICONO_CANAL = { WhatsApp: 'pi pi-whatsapp', SMS: 'pi pi-comment', 'Correo electrónico': 'pi pi-envelope', Sistema: 'pi pi-desktop' };
const COLOR_CANAL = { WhatsApp: '#16a34a', SMS: '#2b5480', 'Correo electrónico': '#db2777', Sistema: '#64748b' };

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
    <Modal v-model:visible="visible" titulo="Editar datos de la cita" :subtitulo="a.patient" icono="pi pi-pencil" color="#2b5480" ancho="680px">
        <SeccionModal titulo="Consulta" icono="pi pi-clipboard" color="#6b4bc9">
            <div class="grid2">
                <div class="field"><label>Tipo de consulta</label><Select v-model="f.type" :options="page.props.catalogo.tiposConsulta" fluid /></div>
                <div class="field"><label>Costo</label><InputNumber v-model="f.cost" mode="currency" currency="MXN" locale="es-MX" :min="0" fluid /></div>
            </div>
            <div class="field"><label for="e-reason">Motivo</label><div class="campo-ic"><i class="pi pi-comment" /><InputText id="e-reason" v-model="f.reason" fluid /></div></div>
            <div class="field"><label for="e-notes">Observaciones</label><Textarea id="e-notes" v-model="f.notes" rows="3" auto-resize fluid /></div>
            <p v-if="a.hc_status === 'Pendiente'" class="note small"><i class="pi pi-info-circle" /> Si cambias el tipo de consulta se reasignará el formato clínico correspondiente.</p>
        </SeccionModal>
        <SeccionModal titulo="Canal de notificación" icono="pi pi-send" color="#16a34a">
            <div class="opciones-ic">
                <button v-for="c in page.props.catalogo.canales" :key="c" type="button" class="op-ic" :class="{ on: f.channel === c }" :style="{ '--oc': COLOR_CANAL[c] }" @click="f.channel = c"><i :class="ICONO_CANAL[c]" />{{ c === 'Correo electrónico' ? 'Correo' : c }}</button>
            </div>
        </SeccionModal>
        <template #footer>
            <Button label="Cancelar" text @click="ui.cerrar()" />
            <Button label="Guardar cambios" icon="pi pi-check" :loading="guardando" @click="guardar" />
        </template>
    </Modal>
</template>
