<script setup>
import Modal from '@/Components/Modal.vue';
import { ref, computed, watch, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ui } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';
import { hoyISO, DIAS, fmtDS } from '@/lib/util';
import Fecha from '@/Components/Fecha.vue';
import PacienteBuscador from '@/Components/PacienteBuscador.vue';

const page = usePage();
const op = computed(() => page.props.opciones);
const cat = computed(() => page.props.catalogo);
const visible = computed({ get: () => ui.dialogo === 'nuevaCita', set: (v) => { if (!v) ui.cerrar(); } });

const f = ref({});
const paciente = ref(null);
const horas = ref({ works: true, times: [], days: [] });
const formato = ref(null);
const guardando = ref(false);
let abriendo = false; // evita que los watchers borren los valores precargados al abrir

const medicos = computed(() => op.value.medicos.filter((d) => d.active && (!f.value.specialty_id || d.specialty_id === f.value.specialty_id)));
const medico = computed(() => op.value.medicos.find((d) => d.id === f.value.doctor_id));

watch(visible, async (v) => {
    if (!v) return;
    abriendo = true;
    const d = ui.datos || {};
    const doc = d.doctor_id ? op.value.medicos.find((x) => x.id === d.doctor_id) : null;
    f.value = {
        specialty_id: doc?.specialty_id ?? d.specialty_id ?? null, doctor_id: doc?.id ?? null, type: 'Primera consulta',
        date: d.date && d.date >= hoyISO() ? d.date : hoyISO(), time: d.time || '', reason: d.reason || '',
        cost: doc?.cost ?? 0, deposit: doc?.deposit ?? 0, paid: 0, payment_method: 'Efectivo', channel: op.value.canales[0] || 'Sistema', notes: '',
        waitlist_id: d.waitlist_id || null,
    };
    paciente.value = null;
    if (d.patient_id) {
        const r = await api.get(route('api.pacientes.historial', d.patient_id));
        paciente.value = { ...r.patient, previas: undefined };
    }
    await nextTick();
    abriendo = false;
    cargarHoras(true);
    sugerirTipo();
});

watch(() => f.value.specialty_id, (s) => { if (abriendo) return; if (medico.value && s && medico.value.specialty_id !== s) f.value.doctor_id = null; });
watch(() => f.value.doctor_id, (id, viejo) => {
    if (abriendo || !visible.value || id === viejo) return;
    const d = medico.value;
    if (d) { f.value.specialty_id = d.specialty_id; f.value.cost = d.cost; f.value.deposit = d.deposit; }
    f.value.time = '';
    cargarHoras();
    sugerirTipo();
});
watch(() => f.value.date, () => { if (visible.value && !abriendo) { f.value.time = ''; cargarHoras(); } });
watch(() => f.value.type, () => { if (!abriendo) cargarFormato(); });
watch(paciente, () => { if (!abriendo) sugerirTipo(); });

async function cargarHoras(conservar = false) {
    if (!f.value.doctor_id || !f.value.date) { horas.value = { works: true, times: [], days: [] }; return; }
    const t = f.value.time;
    horas.value = await api.get(route('api.agenda.horarios'), { doctor_id: f.value.doctor_id, date: f.value.date });
    if (conservar && t && horas.value.times.includes(t)) f.value.time = t;
}
async function sugerirTipo() {
    if (!f.value.doctor_id) { formato.value = null; return; }
    const r = await api.get(route('api.agenda.formato'), { doctor_id: f.value.doctor_id, patient_id: paciente.value?.id, date: f.value.date });
    if (r.sugerido) f.value.type = r.sugerido;
    cargarFormato();
}
async function cargarFormato() {
    if (!f.value.doctor_id) return;
    formato.value = await api.get(route('api.agenda.formato'), { doctor_id: f.value.doctor_id, type: f.value.type, patient_id: paciente.value?.id, date: f.value.date });
}
async function proxima() {
    const r = await api.get(route('api.agenda.proxima'), { doctor_id: f.value.doctor_id, from: f.value.date < hoyISO() ? hoyISO() : f.value.date });
    f.value.date = r.date;
}

async function guardar() {
    if (!paciente.value) return avisar('Selecciona o da de alta al paciente.', 'error');
    if (!f.value.doctor_id) return avisar('Selecciona un médico.', 'error');
    if (!f.value.time) return avisar('Selecciona un horario disponible.', 'error');
    guardando.value = true;
    try {
        const r = await api.post(route('api.citas.store'), { ...f.value, patient_id: paciente.value.id });
        ui.cerrar();
        refrescar();
        avisar(r.message, 'success', { label: 'Ver cita', fn: () => ui.abrirCita(r.id) });
    } finally { guardando.value = false; }
}
</script>

<template>
    <Modal icono="pi pi-calendar-plus" color="#1f9e86" subtitulo="Elige paciente, médico y un horario disponible" v-model:visible="visible" header="Nueva cita" ancho="820px">
        <div class="field"><label>Paciente (expediente SIGMA24)</label><PacienteBuscador v-model="paciente" /></div>
        <div class="grid3">
            <div class="field"><label>Especialidad</label><Select v-model="f.specialty_id" :options="op.especialidades" option-label="name" option-value="id" placeholder="Elige especialidad" show-clear /></div>
            <div class="field"><label>Médico</label><Select v-model="f.doctor_id" :options="medicos" option-label="name" option-value="id" placeholder="Elige médico" filter /></div>
            <div class="field"><label>Tipo de consulta</label><Select v-model="f.type" :options="cat.tiposConsulta" /></div>
        </div>
        <div class="grid3">
            <div class="field"><label>Fecha</label><Fecha v-model="f.date" :min="hoyISO()" /></div>
            <div class="field" style="grid-column: span 2">
                <label>Horario disponible</label>
                <div class="times">
                    <span v-if="!f.doctor_id" class="muted small">Elige un médico para ver horarios.</span>
                    <template v-else-if="horas.times.length">
                        <button v-for="t in horas.times" :key="t" type="button" :class="{ on: f.time === t }" @click="f.time = t">{{ t }}</button>
                    </template>
                    <span v-else class="muted small">
                        {{ horas.works ? 'No quedan espacios este día.' : `${medico?.name} no atiende los ${DIAS[new Date(f.date + 'T12:00').getDay()].toLowerCase()}. Atiende: ${(medico?.days || []).map((x) => DIAS[x]).join(', ')}.` }}
                        <a href="#" @click.prevent="proxima">Ir a la próxima fecha disponible</a>
                    </span>
                </div>
            </div>
        </div>
        <div class="field"><label for="n-reason">Motivo de consulta</label><InputText id="n-reason" v-model="f.reason" placeholder="Ej. dolor de rodilla" /></div>
        <div class="grid3">
            <div class="field"><label>Costo</label><InputNumber v-model="f.cost" mode="currency" currency="MXN" locale="es-MX" :min="0" fluid /></div>
            <div class="field"><label>Anticipo requerido</label><InputNumber v-model="f.deposit" mode="currency" currency="MXN" locale="es-MX" :min="0" fluid /></div>
            <div class="field"><label>Anticipo recibido ahora</label><InputNumber v-model="f.paid" mode="currency" currency="MXN" locale="es-MX" :min="0" fluid /></div>
        </div>
        <div class="grid3">
            <div class="field"><label>Forma de pago</label><Select v-model="f.payment_method" :options="cat.formasPago" /></div>
            <div class="field"><label>Notificar por</label><Select v-model="f.channel" :options="op.canales" /></div>
            <div class="field"><label for="n-notes">Observaciones</label><InputText id="n-notes" v-model="f.notes" /></div>
        </div>
        <div class="note">
            <template v-if="formato">
                Al registrar se asignará <b>{{ formato.form }} v{{ formato.version }}</b> y se enviará al paciente el enlace para completarla.
                <template v-if="formato.previa"> El paciente tiene información previa del {{ fmtDS(formato.previa) }}; se le preguntará si sigue vigente.</template>
            </template>
            <template v-else>El formato clínico se asigna según especialidad, médico y tipo de consulta.</template>
        </div>
        <template #footer>
            <Button label="Cancelar" text @click="ui.cerrar()" />
            <Button label="Registrar cita" icon="pi pi-check" :loading="guardando" @click="guardar" />
        </template>
    </Modal>
</template>
