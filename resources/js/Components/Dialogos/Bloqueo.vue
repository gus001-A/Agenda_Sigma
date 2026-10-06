<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { ui } from '@/lib/ui';
import { avisar, refrescar, mensajeError } from '@/lib/api';
import { hoyISO, dow, DSH } from '@/lib/util';
import Fecha from '@/Components/Fecha.vue';
import Hora from '@/Components/Hora.vue';

const page = usePage();
const visible = computed({ get: () => ui.dialogo === 'bloqueo', set: (v) => { if (!v) ui.cerrar(); } });
const f = ref({});
const conflictos = ref([]);
const guardando = ref(false);
const destinos = computed(() => f.value.scope === 'doctor'
    ? [{ id: 0, name: 'Todos los médicos' }, ...page.props.opciones.medicos.map((d) => ({ id: d.id, name: d.name }))]
    : page.props.opciones.recursos.map((r) => ({ id: r.id, name: r.name })));

watch(visible, (v) => {
    if (!v) return;
    const d = ui.datos || {};
    const fecha = d.date || hoyISO();
    f.value = { scope: d.scope || 'doctor', target_id: d.target_id ?? (d.scope === 'resource' ? page.props.opciones.recursos[0]?.id : 0), type: 'Comida', date: fecha,
        all_day: false, start_time: d.start || '13:00', end_time: d.end || '14:00', recurring: false, days: [dow(fecha)], until: null, notes: '' };
    conflictos.value = [];
});
watch(() => f.value.scope, (s) => {
    if (!destinos.value.some((x) => x.id === f.value.target_id)) f.value.target_id = s === 'doctor' ? 0 : page.props.opciones.recursos[0]?.id;
});

function toggleDia(x, on) { const s = new Set(f.value.days); on ? s.add(x) : s.delete(x); f.value.days = [...s]; }

async function guardar() {
    guardando.value = true;
    conflictos.value = [];
    try {
        const r = await axios.post(route('api.bloqueos.store'), { ...f.value, target_id: f.value.target_id || null });
        avisar(r.data.message);
        ui.cerrar();
        refrescar();
    } catch (e) {
        conflictos.value = e.response?.data?.conflictos || [];
        avisar(mensajeError(e), 'error');
    } finally { guardando.value = false; }
}
</script>

<template>
    <Dialog v-model:visible="visible" modal header="Bloquear horario" :style="{ width: '620px' }" :breakpoints="{ '660px': '96vw' }">
        <div class="grid2">
            <div class="field"><label>Aplica a</label><Select v-model="f.scope" :options="[{ l: 'Agenda de médico', v: 'doctor' }, { l: 'Espacio o quirófano', v: 'resource' }]" option-label="l" option-value="v" /></div>
            <div class="field"><label>Médico o espacio</label><Select v-model="f.target_id" :options="destinos" option-label="name" option-value="id" filter /></div>
        </div>
        <div class="grid3">
            <div class="field"><label>Tipo de bloqueo</label><Select v-model="f.type" :options="page.props.catalogo.tiposBloqueo" /></div>
            <div class="field"><label>Fecha</label><Fecha v-model="f.date" /></div>
            <div class="field"><label>&nbsp;</label><label class="chk" style="min-height: 38px"><Checkbox v-model="f.all_day" binary />Todo el día</label></div>
        </div>
        <div v-if="!f.all_day" class="grid2">
            <div class="field"><label>Hora inicial</label><Hora v-model="f.start_time" /></div>
            <div class="field"><label>Hora final</label><Hora v-model="f.end_time" /></div>
        </div>
        <label class="chk"><Checkbox v-model="f.recurring" binary />Repetir cada semana</label>
        <div v-if="f.recurring" class="stack">
            <div class="pills">
                <label v-for="x in [1, 2, 3, 4, 5, 6, 0]" :key="x"><input type="checkbox" :checked="f.days.includes(x)" @change="toggleDia(x, $event.target.checked)" />{{ DSH[x] }}</label>
            </div>
            <div class="field" style="max-width: 240px"><label>Hasta (opcional)</label><Fecha v-model="f.until" :min="f.date" /></div>
        </div>
        <div class="field"><label for="bk-n">Observaciones</label><InputText id="bk-n" v-model="f.notes" /></div>
        <div v-if="conflictos.length" class="note err small">
            <b>No se puede bloquear.</b> Reagenda o cancela antes:
            <ul style="margin: 6px 0 0 18px; list-style: disc"><li v-for="c in conflictos" :key="c">{{ c }}</li></ul>
        </div>
        <template #footer>
            <Button label="Cancelar" text @click="ui.cerrar()" />
            <Button label="Crear bloqueo" icon="pi pi-ban" :loading="guardando" @click="guardar" />
        </template>
    </Dialog>
</template>
