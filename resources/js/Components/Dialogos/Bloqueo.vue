<script setup>
import Modal from '@/Components/Modal.vue';
import SeccionModal from '@/Components/SeccionModal.vue';
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
const ICONO_TIPO = { Comida: 'pi pi-shopping-bag', 'Médico no disponible': 'pi pi-user-minus', 'Reunión': 'pi pi-users', Procedimiento: 'pi pi-briefcase', Mantenimiento: 'pi pi-wrench', Evento: 'pi pi-star', Vacaciones: 'pi pi-sun', 'Fin de agenda': 'pi pi-flag', Otro: 'pi pi-ellipsis-h' };
</script>

<template>
    <Modal v-model:visible="visible" titulo="Bloquear horario" subtitulo="Los espacios bloqueados no se pueden agendar; no se permite bloquear sobre citas existentes" icono="pi pi-ban" color="#64748b" ancho="760px">
        <SeccionModal paso="1" titulo="¿Qué se bloquea?" icono="pi pi-th-large" color="#2b5480">
            <div class="opciones-ic" style="grid-template-columns: 1fr 1fr">
                <button type="button" class="op-ic" :class="{ on: f.scope === 'doctor' }" style="--oc: #1f9e86" @click="f.scope = 'doctor'"><i class="pi pi-user" />Agenda de médico</button>
                <button type="button" class="op-ic" :class="{ on: f.scope === 'resource' }" style="--oc: #6b4bc9" @click="f.scope = 'resource'"><i class="pi pi-building" />Espacio o quirófano</button>
            </div>
            <div class="field"><label>{{ f.scope === 'doctor' ? 'Médico' : 'Espacio' }}</label><Select v-model="f.target_id" :options="destinos" option-label="name" option-value="id" filter fluid /></div>
        </SeccionModal>
        <SeccionModal paso="2" titulo="Tipo de bloqueo" icono="pi pi-tag" color="#e08a1e">
            <div class="opciones-ic">
                <button v-for="t in page.props.catalogo.tiposBloqueo" :key="t" type="button" class="op-ic" :class="{ on: f.type === t }" style="--oc: #e08a1e" @click="f.type = t"><i :class="ICONO_TIPO[t] || 'pi pi-tag'" />{{ t }}</button>
            </div>
        </SeccionModal>
        <SeccionModal paso="3" titulo="Cuándo" icono="pi pi-clock" color="#1f9e86">
            <template #extra><label class="chk"><ToggleSwitch v-model="f.all_day" />Todo el día</label></template>
            <div class="grid3">
                <div class="field"><label>Fecha</label><Fecha v-model="f.date" /></div>
                <div class="field"><label>Hora inicial</label><Hora v-model="f.start_time" :disabled="f.all_day" /></div>
                <div class="field"><label>Hora final</label><Hora v-model="f.end_time" :disabled="f.all_day" /></div>
            </div>
            <label class="chk"><ToggleSwitch v-model="f.recurring" />Repetir cada semana</label>
            <div v-if="f.recurring" class="grid2" style="align-items: end">
                <div class="field"><label>Días</label>
                    <div class="pills"><label v-for="x in [1, 2, 3, 4, 5, 6, 0]" :key="x"><input type="checkbox" :checked="f.days.includes(x)" @change="toggleDia(x, $event.target.checked)" />{{ DSH[x] }}</label></div>
                </div>
                <div class="field"><label>Hasta (opcional)</label><Fecha v-model="f.until" :min="f.date" /></div>
            </div>
        </SeccionModal>
        <div class="field"><label for="bk-n">Observaciones</label><div class="campo-ic"><i class="pi pi-pencil" /><InputText id="bk-n" v-model="f.notes" placeholder="Opcional" fluid /></div></div>
        <div v-if="conflictos.length" class="note err small">
            <b><i class="pi pi-exclamation-triangle" /> No se puede bloquear.</b> Reagenda o cancela antes:
            <ul style="margin: 6px 0 0 18px; list-style: disc"><li v-for="c in conflictos" :key="c">{{ c }}</li></ul>
        </div>
        <template #footer>
            <Button label="Cancelar" text @click="ui.cerrar()" />
            <Button label="Crear bloqueo" icon="pi pi-ban" :loading="guardando" @click="guardar" />
        </template>
    </Modal>
</template>
