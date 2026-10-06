<script setup>
import { ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { ui } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';
import PacienteBuscador from '@/Components/PacienteBuscador.vue';

const page = usePage();
const op = computed(() => page.props.opciones);
const visible = computed({ get: () => ui.dialogo === 'nuevaEspera', set: (v) => { if (!v) ui.cerrar(); } });
const f = ref({});
const paciente = ref(null);
const guardando = ref(false);
const medicos = computed(() => [{ id: null, name: 'Cualquier médico' }, ...op.value.medicos.filter((d) => d.specialty_id === f.value.specialty_id)]);

watch(visible, (v) => {
    if (!v) return;
    paciente.value = null;
    f.value = { specialty_id: ui.datos.specialty_id || op.value.especialidades[0]?.id, doctor_id: ui.datos.doctor_id || null, preference: 'Cualquier horario', priority: 'Media', notes: '' };
});
watch(() => f.value.specialty_id, () => { if (f.value.doctor_id && !medicos.value.some((d) => d.id === f.value.doctor_id)) f.value.doctor_id = null; });

async function guardar() {
    if (!paciente.value) return avisar('Elige un paciente.', 'error');
    guardando.value = true;
    try {
        const r = await api.post(route('api.espera.store'), { ...f.value, patient_id: paciente.value.id });
        avisar(r.message);
        ui.cerrar();
        if (route().current('espera')) refrescar(); else router.visit(route('espera'));
    } finally { guardando.value = false; }
}
</script>

<template>
    <Dialog v-model:visible="visible" modal header="Agregar a lista de espera" :style="{ width: '600px' }" :breakpoints="{ '640px': '96vw' }">
        <div class="field"><label>Paciente</label><PacienteBuscador v-model="paciente" /></div>
        <div class="grid2">
            <div class="field"><label>Especialidad</label><Select v-model="f.specialty_id" :options="op.especialidades" option-label="name" option-value="id" /></div>
            <div class="field"><label>Médico</label><Select v-model="f.doctor_id" :options="medicos" option-label="name" option-value="id" /></div>
        </div>
        <div class="grid2">
            <div class="field"><label>Preferencia de horario</label><Select v-model="f.preference" :options="['Cualquier horario', 'Mañana', 'Tarde']" /></div>
            <div class="field"><label>Prioridad</label><Select v-model="f.priority" :options="['Alta', 'Media', 'Baja']" /></div>
        </div>
        <div class="field"><label for="w-n">Observaciones</label><InputText id="w-n" v-model="f.notes" /></div>
        <template #footer>
            <Button label="Cancelar" text @click="ui.cerrar()" />
            <Button label="Agregar a la lista" icon="pi pi-check" :loading="guardando" @click="guardar" />
        </template>
    </Dialog>
</template>
