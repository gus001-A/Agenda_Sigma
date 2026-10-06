<script setup>
import Modal from '@/Components/Modal.vue';
import SeccionModal from '@/Components/SeccionModal.vue';
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
const PREF = [{ v: 'Cualquier horario', i: 'pi pi-clock', c: '#173a5f' }, { v: 'Mañana', i: 'pi pi-sun', c: '#e08a1e' }, { v: 'Tarde', i: 'pi pi-moon', c: '#6b4bc9' }];
const PRIO = [{ v: 'Alta', i: 'pi pi-angle-double-up', c: '#d64545' }, { v: 'Media', i: 'pi pi-minus', c: '#e08a1e' }, { v: 'Baja', i: 'pi pi-angle-double-down', c: '#1f9e86' }];

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
    <Modal v-model:visible="visible" titulo="Agregar a lista de espera" subtitulo="Se le avisará cuando se libere un horario compatible" icono="pi pi-user-plus" color="#e08a1e" ancho="720px">
        <SeccionModal paso="1" titulo="Paciente" icono="pi pi-user" color="#2b5480"><PacienteBuscador v-model="paciente" /></SeccionModal>
        <SeccionModal paso="2" titulo="Especialidad y médico" icono="pi pi-heart" color="#1f9e86">
            <div class="grid2">
                <div class="field"><label>Especialidad</label><Select v-model="f.specialty_id" :options="op.especialidades" option-label="name" option-value="id" fluid /></div>
                <div class="field"><label>Médico</label><Select v-model="f.doctor_id" :options="medicos" option-label="name" option-value="id" fluid /></div>
            </div>
        </SeccionModal>
        <SeccionModal paso="3" titulo="Preferencias" icono="pi pi-sliders-h" color="#e08a1e">
            <div class="grid2">
                <div class="field"><label>Horario preferido</label>
                    <div class="opciones-ic" style="grid-template-columns: repeat(3, 1fr)"><button v-for="p in PREF" :key="p.v" type="button" class="op-ic" :class="{ on: f.preference === p.v }" :style="{ '--oc': p.c }" @click="f.preference = p.v"><i :class="p.i" />{{ p.v === 'Cualquier horario' ? 'Cualquiera' : p.v }}</button></div>
                </div>
                <div class="field"><label>Prioridad</label>
                    <div class="opciones-ic" style="grid-template-columns: repeat(3, 1fr)"><button v-for="p in PRIO" :key="p.v" type="button" class="op-ic" :class="{ on: f.priority === p.v }" :style="{ '--oc': p.c }" @click="f.priority = p.v"><i :class="p.i" />{{ p.v }}</button></div>
                </div>
            </div>
            <div class="field"><label for="w-n">Observaciones</label><div class="campo-ic"><i class="pi pi-pencil" /><InputText id="w-n" v-model="f.notes" placeholder="Ej. solo puede antes de las 12:00" fluid /></div></div>
        </SeccionModal>
        <template #footer>
            <Button label="Cancelar" text @click="ui.cerrar()" />
            <Button label="Agregar a la lista" icon="pi pi-check" severity="warn" :loading="guardando" @click="guardar" />
        </template>
    </Modal>
</template>
