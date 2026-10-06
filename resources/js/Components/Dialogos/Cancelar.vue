<script setup>
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
    <Dialog v-model:visible="visible" modal header="Cancelar cita" :style="{ width: '540px' }" :breakpoints="{ '600px': '96vw' }">
        <p>{{ a.patient }}, {{ fmtDL(a.date) }} a las {{ a.time }} con {{ a.doctor }}.</p>
        <div class="field"><label>Motivo</label><Select v-model="f.reason" :options="page.props.catalogo.motivosCancelacion" /></div>
        <div class="field"><label for="cn-n">Observaciones</label><InputText id="cn-n" v-model="f.notes" /></div>
        <label class="chk"><Checkbox v-model="f.notify" binary />Notificar al paciente</label>
        <p v-if="a.paid > 0" class="note warn small">La cita tiene {{ money(a.paid) }} de anticipo. Podrás registrar el reembolso desde el detalle.</p>
        <p class="muted small">El horario quedará disponible y se buscarán candidatos en la lista de espera.</p>
        <template #footer>
            <Button label="Conservar cita" text @click="ui.cerrar()" />
            <Button label="Cancelar cita" severity="danger" :loading="guardando" @click="guardar" />
        </template>
    </Dialog>
</template>
