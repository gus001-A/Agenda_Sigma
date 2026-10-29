<script setup>
import Modal from '@/Components/Modal.vue';
import { ref, computed, watch, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ui } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';
import { hoyISO, DIAS, fmtDS } from '@/lib/util';
import Fecha from '@/Components/Fecha.vue';
import PacienteBuscador from '@/Components/PacienteBuscador.vue';
import SeccionModal from '@/Components/SeccionModal.vue';

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
// "Sistema" no es un medio para avisar al paciente, así que no se ofrece al registrar la cita.
const canales = computed(() => op.value.canales.filter((c) => c !== 'Sistema'));

watch(visible, async (v) => {
    if (!v) return;
    abriendo = true;
    const d = ui.datos || {};
    const doc = d.doctor_id ? op.value.medicos.find((x) => x.id === d.doctor_id) : null;
    f.value = {
        specialty_id: doc?.specialty_id ?? d.specialty_id ?? null, doctor_id: doc?.id ?? null, type: 'Primera consulta',
        date: d.date && d.date >= hoyISO() ? d.date : hoyISO(), time: d.time || '', reason: d.reason || '',
        cost: doc?.cost ?? 0, deposit: doc?.deposit ?? 0, paid: 0, payment_method: 'Efectivo', channel: canales.value[0] || 'WhatsApp', notes: '',
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
    if (errorPago.value) return avisar(errorPago.value, 'error');
    guardando.value = true;
    try {
        const r = await api.post(route('api.citas.store'), { ...f.value, cost: f.value.cost || 0, deposit: f.value.deposit || 0, paid: f.value.paid || 0, patient_id: paciente.value.id });
        ui.cerrar();
        refrescar();
        avisar(r.message, 'success', { label: 'Ver cita', fn: () => ui.abrirCita(r.id) });
    } finally { guardando.value = false; }
}

// ---------- presentación ----------
const turnos = computed(() => {
    const t = horas.value.times || [];
    return [
        { l: 'Mañana', i: 'pi pi-sun', h: t.filter((x) => x < '14:00') },
        { l: 'Tarde', i: 'pi pi-moon', h: t.filter((x) => x >= '14:00') },
    ].filter((g) => g.h.length);
});
const ICONO_PAGO = { Efectivo: 'pi pi-money-bill', 'Tarjeta de débito': 'pi pi-credit-card', 'Tarjeta de crédito': 'pi pi-credit-card', Transferencia: 'pi pi-arrow-right-arrow-left' };
const COLOR_PAGO = { Efectivo: '#16a34a', 'Tarjeta de débito': '#2b5480', 'Tarjeta de crédito': '#6b4bc9', Transferencia: '#0e7490' };
const ICONO_CANAL = { WhatsApp: 'pi pi-whatsapp', SMS: 'pi pi-comment', 'Correo electrónico': 'pi pi-envelope' };
const COLOR_CANAL = { WhatsApp: '#16a34a', SMS: '#2b5480', 'Correo electrónico': '#db2777' };
const ICONO_TIPO = { 'Primera consulta': 'pi pi-star', 'Consulta subsecuente': 'pi pi-replay', 'Valoración preoperatoria': 'pi pi-shield', Procedimiento: 'pi pi-briefcase', 'Cirugía': 'pi pi-heart', Seguimiento: 'pi pi-chart-line', Terapia: 'pi pi-bolt', Estudios: 'pi pi-search' };
const money = (n) => '$' + Math.round(Number(n || 0)).toLocaleString('es-MX');
// Al entrar a un monto en $0 se vacía para escribir directo; si se deja vacío vuelve a 0.
// El relleno espera un tick porque InputNumber emite su propio valor (null) al perder el foco.
const vaciar = (k) => { if (!f.value[k]) f.value[k] = null; };
const rellenar = (k) => nextTick(() => { if (f.value[k] == null) f.value[k] = 0; });
const saldo = computed(() => Math.max(0, (f.value.cost || 0) - (f.value.paid || 0)));
// Ni el anticipo requerido ni lo recibido pueden pasar del costo de la consulta.
const errDeposito = computed(() => ((f.value.deposit || 0) > (f.value.cost || 0) ? 'No puede ser mayor al costo de la consulta.' : ''));
const errRecibido = computed(() => ((f.value.paid || 0) > (f.value.cost || 0) ? 'No puede ser mayor al costo de la consulta.' : ''));
const errorPago = computed(() => (errDeposito.value ? 'El anticipo requerido no puede ser mayor al costo de la consulta.'
    : errRecibido.value ? 'Lo recibido no puede ser mayor al costo de la consulta.' : ''));
const fechaLarga = computed(() => {
    if (!f.value.date) return '';
    const d = new Date(f.value.date + 'T12:00');
    return d.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });
});
</script>

<template>
    <Modal v-model:visible="visible" titulo="Nueva cita" subtitulo="Elige al paciente, el médico y un horario disponible; el formato clínico se asigna solo" icono="pi pi-calendar-plus" color="#1f9e86" ancho="1320px">
        <!-- Dos filas: paciente | médico y horario; debajo, detalles | pago y aviso -->
        <div class="nc-grid">
            <SeccionModal paso="1" titulo="Paciente" icono="pi pi-user" color="#2b5480" ayuda="Busca al paciente o da de alta uno nuevo">
                <PacienteBuscador v-model="paciente" />
            </SeccionModal>

            <SeccionModal paso="2" titulo="Médico y horario" icono="pi pi-calendar-clock" color="#1f9e86" ayuda="Solo se muestran los espacios libres según la agenda">
                <div class="grid2">
                    <div class="field"><label>Especialidad</label><Select v-model="f.specialty_id" :options="op.especialidades" option-label="name" option-value="id" placeholder="Todas" show-clear fluid /></div>
                    <div class="field"><label>Fecha</label><Fecha v-model="f.date" :min="hoyISO()" /></div>
                </div>
                <div class="field"><label>Médico</label><Select v-model="f.doctor_id" :options="medicos" option-label="name" option-value="id" placeholder="Elige médico" filter fluid /></div>
                <div class="field">
                    <label>Horario disponible <span v-if="fechaLarga" class="muted" style="text-transform: none; letter-spacing: 0; font-weight: 500">· {{ fechaLarga }}</span></label>
                    <div v-if="!f.doctor_id" class="note small"><i class="pi pi-info-circle" /> Elige un médico para ver sus horarios.</div>
                    <div v-else-if="turnos.length" class="turnos">
                        <div v-for="g in turnos" :key="g.l">
                            <div class="turno-t"><i :class="g.i" />{{ g.l }} · {{ g.h.length }}</div>
                            <div class="slots-grid"><button v-for="t in g.h" :key="t" type="button" :class="{ on: f.time === t }" @click="f.time = t">{{ t }}</button></div>
                        </div>
                    </div>
                    <div v-else class="note warn small">
                        <i class="pi pi-calendar-times" />
                        {{ horas.works ? 'No quedan espacios este día.' : `${medico?.name} no atiende los ${DIAS[new Date(f.date + 'T12:00').getDay()].toLowerCase()}. Atiende: ${(medico?.days || []).map((x) => DIAS[x]).join(', ')}.` }}
                        <a href="#" style="font-weight: 700" @click.prevent="proxima">Ir a la próxima fecha disponible →</a>
                    </div>
                </div>
            </SeccionModal>

            <SeccionModal paso="3" titulo="Detalles de la consulta" icono="pi pi-clipboard" color="#6b4bc9" class="nc-par">
                <div class="field">
                    <label>Tipo de consulta</label>
                    <div class="opciones-ic">
                        <button v-for="t in cat.tiposConsulta" :key="t" type="button" class="op-ic" :class="{ on: f.type === t }" style="--oc: #6b4bc9" @click="f.type = t">
                            <i :class="ICONO_TIPO[t] || 'pi pi-tag'" />{{ t }}
                        </button>
                    </div>
                </div>
                <div class="grid2">
                    <div class="field"><label for="n-reason">Motivo de consulta</label><div class="campo-ic"><i class="pi pi-comment" /><InputText id="n-reason" v-model="f.reason" placeholder="Ej. dolor de rodilla" fluid /></div></div>
                    <div class="field"><label for="n-notes">Observaciones</label><div class="campo-ic"><i class="pi pi-pencil" /><InputText id="n-notes" v-model="f.notes" placeholder="Opcional" fluid /></div></div>
                </div>
                <div v-if="formato" class="note small">
                    <i class="pi pi-file-edit" /> Se asignará <b>{{ formato.form }} v{{ formato.version }}</b> y se enviará el enlace por {{ f.channel }}.<template v-if="formato.previa"> Tiene información previa del {{ fmtDS(formato.previa) }}.</template>
                </div>
            </SeccionModal>

            <SeccionModal paso="4" titulo="Pago y aviso al paciente" icono="pi pi-wallet" color="#e08a1e" class="nc-par">
                <div class="grid3">
                    <div class="field"><label>Costo</label><InputNumber v-model="f.cost" mode="currency" currency="MXN" locale="es-MX" :min="0" placeholder="$0.00" @focus="vaciar('cost')" @blur="rellenar('cost')" fluid /></div>
                    <div class="field">
                        <label>Anticipo requerido</label><InputNumber v-model="f.deposit" mode="currency" currency="MXN" locale="es-MX" :min="0" placeholder="$0.00" @focus="vaciar('deposit')" @blur="rellenar('deposit')" :invalid="!!errDeposito" fluid />
                        <small v-if="errDeposito" class="campo-err">{{ errDeposito }}</small>
                    </div>
                    <div class="field">
                        <label>Recibido ahora</label><InputNumber v-model="f.paid" mode="currency" currency="MXN" locale="es-MX" :min="0" placeholder="$0.00" @focus="vaciar('paid')" @blur="rellenar('paid')" :invalid="!!errRecibido" fluid />
                        <small v-if="errRecibido" class="campo-err">{{ errRecibido }}</small>
                    </div>
                </div>
                <div v-if="f.paid > 0" class="field">
                    <label>Forma de pago</label>
                    <div class="opciones-ic">
                        <button v-for="p in cat.formasPago" :key="p" type="button" class="op-ic" :class="{ on: f.payment_method === p }" :style="{ '--oc': COLOR_PAGO[p] }" @click="f.payment_method = p">
                            <i :class="ICONO_PAGO[p]" />{{ p }}
                        </button>
                    </div>
                </div>
                <div class="field">
                    <label>Notificar por</label>
                    <div class="opciones-ic">
                        <button v-for="c in canales" :key="c" type="button" class="op-ic" :class="{ on: f.channel === c }" :style="{ '--oc': COLOR_CANAL[c] }" @click="f.channel = c">
                            <i :class="ICONO_CANAL[c]" />{{ c === 'Correo electrónico' ? 'Correo' : c }}
                        </button>
                    </div>
                </div>
                <div class="nc-saldo"><span>Por pagar en consulta</span><b>{{ money(saldo) }}</b></div>
            </SeccionModal>
        </div>
        <template #footer>
            <Button label="Cancelar" text @click="ui.cerrar()" />
            <Button label="Registrar cita" icon="pi pi-check" :loading="guardando" :disabled="!paciente || !f.doctor_id || !f.time || !!errorPago" @click="guardar" />
        </template>
    </Modal>
</template>
