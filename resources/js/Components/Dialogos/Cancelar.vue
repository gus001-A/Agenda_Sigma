<script setup>
import Modal from '@/Components/Modal.vue';
import SeccionModal from '@/Components/SeccionModal.vue';
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ui } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';
import { money, fmtDL } from '@/lib/util';

const page = usePage();
const visible = computed({ get: () => ui.dialogo === 'cancelar', set: (v) => { if (!v) ui.cerrar(); } });
const a = computed(() => ui.datos.cita || {});
const f = ref({});
const guardando = ref(false);
watch(visible, (v) => { if (v) f.value = { reason: 'Solicitud del paciente', notes: '', notify: true }; });
const ICONOS = { 'Solicitud del paciente': 'pi pi-user', 'Médico no disponible': 'pi pi-heart', 'Paciente no confirmó': 'pi pi-question-circle', 'Cita duplicada': 'pi pi-clone', Otro: 'pi pi-ellipsis-h' };

async function guardar() {
    guardando.value = true;
    try {
        const r = await api.post(route('api.citas.cancelar', a.value.id), f.value);
        refrescar();
        ui.recargarCita();
        // Si hay pacientes compatibles en lista de espera, se ofrecen de inmediato.
        if (r.liberado && page.props.auth.user.acciones.includes('cita')) ui.abrir('liberado', r.liberado);
        else { ui.cerrar(); avisar(r.message); }
    } finally { guardando.value = false; }
}
</script>

<template>
    <Modal v-model:visible="visible" titulo="Cancelar cita" subtitulo="El horario se libera y se buscan candidatos en la lista de espera" icono="pi pi-times-circle" color="#d64545" ancho="620px">
        <SeccionModal titulo="Cita a cancelar" icono="pi pi-calendar" color="#2b5480">
            <dl class="kv" style="margin: 0">
                <dt>Paciente</dt><dd><b>{{ a.patient }}</b></dd>
                <dt>Fecha y hora</dt><dd>{{ fmtDL(a.date) }}, {{ a.time }}</dd>
                <dt>Médico</dt><dd>{{ a.doctor }}</dd>
            </dl>
        </SeccionModal>
        <SeccionModal titulo="Motivo" icono="pi pi-comment" color="#d64545">
            <div class="opciones-ic">
                <button v-for="m in page.props.catalogo.motivosCancelacion" :key="m" type="button" class="op-ic" :class="{ on: f.reason === m }" style="--oc: #d64545" @click="f.reason = m"><i :class="ICONOS[m] || 'pi pi-tag'" />{{ m }}</button>
            </div>
            <div class="field"><label for="cn-n">Observaciones</label><div class="campo-ic"><i class="pi pi-pencil" /><InputText id="cn-n" v-model="f.notes" placeholder="Opcional" fluid /></div></div>
            <label class="chk"><ToggleSwitch v-model="f.notify" />Notificar al paciente</label>
        </SeccionModal>
        <p v-if="a.paid > 0" class="note warn small"><i class="pi pi-wallet" /> La cita tiene {{ money(a.paid) }} de anticipo. Podrás registrar el reembolso desde el detalle.</p>
        <template #footer>
            <Button label="Conservar cita" text @click="ui.cerrar()" />
            <Button label="Cancelar cita" icon="pi pi-times" severity="danger" :loading="guardando" @click="guardar" />
        </template>
    </Modal>
</template>
