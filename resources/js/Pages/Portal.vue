<script setup>
import { ref, computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Chip from '@/Components/Chip.vue';
import CampoFormulario from '@/Components/CampoFormulario.vue';
import { mensajeError } from '@/lib/api';
import { money, fmtD, fmtDS } from '@/lib/util';

/** Portal del paciente: confirmar asistencia y llenar la historia clínica por secciones. */
const props = defineProps({ token: String, clinica: String, cita: Object, secciones: Array, respuestas: Object, archivos: Array, previa: Object });
const toast = useToast();
const aviso = (t, tipo = 'success') => toast.add({ severity: tipo, summary: t, life: 4000 });

const pantalla = ref('inicio');
const paso = ref(0);
const datos = reactive(JSON.parse(JSON.stringify(props.respuestas || {})));
const archivos = ref([...props.archivos]);
const ocupado = ref(false);
const subiendo = ref(false);
const faltan = ref([]);
const c = computed(() => props.cita);
const soloLectura = computed(() => !c.value.editable);
const seccion = computed(() => props.secciones[Math.min(paso.value, props.secciones.length - 1)]);

const url = (accion) => `/p/${props.token}/${accion}`;
async function post(accion, body = {}) {
    ocupado.value = true;
    try { return (await axios.post(url(accion), body)).data; }
    catch (e) { aviso(mensajeError(e), 'error'); if (e.response?.data?.faltan) faltan.value = e.response.data.faltan; throw e; }
    finally { ocupado.value = false; }
}
const recargar = () => router.reload({ preserveScroll: true });

function respondido(f) {
    const v = datos[f.id];
    if (['files', 'photo'].includes(f.type)) return archivos.value.some((a) => a.field_id === f.id);
    if (['allergies', 'meds'].includes(f.type)) return v && (v.none || (v.rows || []).some((r) => Object.values(r).some(Boolean)));
    if (f.type === 'consent') return !!v?.accepted;
    if (f.type === 'multi') return Array.isArray(v) && v.length > 0;
    if (f.type === 'history') return v && ((v.sel || []).length || v.notes);
    return v !== undefined && v !== null && String(v).trim() !== '';
}
const avance = computed(() => {
    let t = 0, n = 0;
    props.secciones.forEach((s) => s.fields.forEach((f) => { if (f.mode === 'readonly') return; t++; if (respondido(f)) n++; }));
    return t ? Math.round((n * 100) / t) : 0;
});
const faltantes = computed(() => props.secciones.flatMap((s, si) => s.fields.filter((f) => f.mode === 'required' && !respondido(f)).map((f) => ({ si, f }))));

async function confirmar() { const r = await post('confirmar'); aviso(r.message); recargar(); }
async function guardar(explicito = false) {
    if (soloLectura.value) return;
    const r = await post('guardar', { respuestas: datos, explicito });
    if (explicito) { aviso(r.message); pantalla.value = 'inicio'; recargar(); }
}
async function navegar(d) {
    await guardar(false);
    const n = paso.value + d;
    if (n < 0) pantalla.value = 'inicio';
    else if (n >= props.secciones.length) pantalla.value = 'revisar';
    else paso.value = n;
    document.querySelector('.ph-body')?.scrollTo(0, 0);
}
async function enviar() { await post('enviar', { respuestas: datos }); pantalla.value = 'listo'; }
async function reutilizar(modo) {
    await post('reutilizar', { modo });
    router.reload({ onSuccess: () => {
        Object.keys(datos).forEach((k) => delete datos[k]);
        Object.assign(datos, JSON.parse(JSON.stringify(props.respuestas || {})));
        pantalla.value = modo === 'keep' ? 'revisar' : 'formulario';
        paso.value = 0;
        if (modo === 'keep') aviso('Se cargó su información anterior. Complete el motivo de esta consulta y acepte el aviso de privacidad.', 'info');
    } });
}
async function subir(campo, lista) {
    subiendo.value = true;
    try {
        for (const file of lista) {
            const fd = new FormData();
            fd.append('field_id', campo);
            fd.append('archivo', file);
            const r = await axios.post(url('archivos'), fd);
            archivos.value.push(r.data.archivo);
        }
        aviso('Archivo adjuntado');
    } catch (e) { aviso(mensajeError(e), 'error'); } finally { subiendo.value = false; }
}
async function borrarArchivo(id) {
    try { await axios.delete(url('archivos/' + id)); archivos.value = archivos.value.filter((a) => a.id !== id); }
    catch (e) { aviso(mensajeError(e), 'error'); }
}
function abrirFormulario() { pantalla.value = soloLectura.value ? 'revisar' : 'formulario'; paso.value = 0; }
const etiquetaBoton = computed(() => soloLectura.value ? 'Ver información enviada' : c.value.hc_status === 'Pendiente' && !avance.value ? 'Comenzar' : c.value.hc_status === 'Requiere actualización' ? 'Actualizar información' : 'Continuar');
function texto(f) {
    const v = datos[f.id];
    if (['files', 'photo'].includes(f.type)) return archivos.value.filter((a) => a.field_id === f.id).map((a) => a.name).join(', ');
    if (v === undefined || v === null || v === '') return '';
    if (f.type === 'allergies') return v.none ? 'Niega alergias' : (v.rows || []).filter((r) => r.d).map((r) => `${r.t}: ${r.d}`).join('; ');
    if (f.type === 'meds') return v.none ? 'No toma medicamentos' : (v.rows || []).filter((r) => r.m).map((r) => `${r.m} ${r.d || ''} ${r.f ? '(' + r.f + ')' : ''}`).join('; ');
    if (f.type === 'multi') return v.join(', ');
    if (f.type === 'history') return [(v.sel || []).join(', '), v.notes].filter(Boolean).join('. ');
    if (f.type === 'consent') return v.accepted ? 'Aceptado' : '';
    if (f.type === 'signature') return 'Firmado';
    if (f.type === 'scale') return `${v} de 10`;
    if (f.type === 'date') return fmtDS(v);
    return String(v);
}
</script>

<template>
    <Head :title="'Mi cita | ' + clinica" />
    <div class="portal-bg">
        <div class="phone">
            <div class="ph-top">
                <small>{{ clinica }}</small>
                <b>{{ pantalla === 'inicio' ? 'Hola, ' + cita.first_name : cita.form }}</b>
            </div>

            <!-- Enviado -->
            <template v-if="pantalla === 'listo'">
                <div class="ph-body">
                    <div class="pc" style="text-align: center; padding: 28px 18px">
                        <div style="width: 54px; height: 54px; border-radius: 50%; background: var(--ok-soft); color: var(--ok); display: grid; place-items: center; margin: 0 auto 12px"><i class="pi pi-check" style="font-size: 24px" /></div>
                        <h2>Información enviada</h2>
                        <p class="muted" style="margin-top: 6px">Gracias. {{ cita.doctor }} revisará su historia clínica antes de la consulta del {{ fmtD(cita.date) }} a las {{ cita.time }}.</p>
                    </div>
                </div>
                <div class="ph-foot"><Button label="Volver al inicio" severity="secondary" outlined @click="pantalla = 'inicio'; recargar()" /></div>
            </template>

            <!-- Inicio -->
            <template v-else-if="pantalla === 'inicio'">
                <div class="ph-body">
                    <div v-if="!cita.active" class="note err">Esta cita fue {{ cita.status.toLowerCase() }}. Si recibió un nuevo enlace, utilice ese.</div>
                    <div class="pc stack" style="gap: 10px">
                        <div class="row" style="justify-content: space-between"><b>Su cita</b><Chip :valor="cita.status" /></div>
                        <dl class="kv" style="grid-template-columns: 90px 1fr">
                            <dt>Médico</dt><dd>{{ cita.doctor }}</dd>
                            <dt>Especialidad</dt><dd>{{ cita.specialty }}</dd>
                            <dt>Fecha</dt><dd>{{ cita.date_label }}</dd>
                            <dt>Hora</dt><dd>{{ cita.time }}</dd>
                            <template v-if="cita.place"><dt>Lugar</dt><dd>{{ cita.place }}</dd></template>
                        </dl>
                        <p class="small muted">Indicaciones: llegue 15 minutos antes y traiga identificación{{ cita.type === 'Valoración preoperatoria' ? ' y sus estudios de laboratorio' : '' }}.</p>
                        <div v-if="cita.balance > 0" class="note warn small">Anticipo pendiente: <b>{{ money(cita.balance) }}</b>. Puede pagarlo en recepción o por transferencia.</div>
                        <template v-if="cita.active && cita.status !== 'Atendida'">
                            <div v-if="cita.confirmed" class="note small"><i class="pi pi-check" style="font-size: 12px" /> Usted confirmó su asistencia.</div>
                            <Button v-else label="Confirmar asistencia" :loading="ocupado" @click="confirmar" />
                        </template>
                    </div>
                    <div v-if="previa && cita.editable" class="pc stack" style="gap: 10px">
                        <b>¿La información registrada anteriormente continúa vigente?</b>
                        <p class="small muted">Encontramos su historia clínica del {{ previa.label }}. Puede reutilizarla para no capturar de nuevo.</p>
                        <Button label="Sí, sigue vigente" @click="reutilizar('keep')" />
                        <Button label="Quiero modificarla o agregar datos" severity="secondary" outlined @click="reutilizar('edit')" />
                        <Button label="Empezar de nuevo" text @click="reutilizar('new')" />
                    </div>
                    <div class="pc stack" style="gap: 10px">
                        <div class="row" style="justify-content: space-between"><b>Historia clínica</b><Chip :valor="cita.hc_status" tipo="hc" /></div>
                        <p class="small muted">{{ cita.form }}. {{ secciones.length }} secciones, toma unos 8 minutos. Puede guardar y continuar después.</p>
                        <div class="prog"><i :style="{ width: avance + '%' }" /></div>
                        <span class="small muted">{{ avance }}% completado</span>
                        <div v-if="cita.update_request" class="note warn small">Su médico solicita: {{ cita.update_request }}</div>
                        <Button :label="etiquetaBoton" :severity="soloLectura ? 'secondary' : undefined" :outlined="soloLectura" @click="abrirFormulario" />
                    </div>
                </div>
            </template>

            <!-- Revisar -->
            <template v-else-if="pantalla === 'revisar'">
                <div class="ph-body">
                    <div v-if="faltantes.length && !soloLectura" class="note err small">Faltan {{ faltantes.length }} campos obligatorios. Toque "Editar" en la sección para completarlos.</div>
                    <div v-for="(s, si) in secciones" :key="si" class="pc">
                        <div class="row" style="justify-content: space-between; margin-bottom: 8px">
                            <b>{{ s.title }}</b>
                            <Button v-if="!soloLectura" label="Editar" size="small" severity="secondary" outlined @click="pantalla = 'formulario'; paso = si" />
                        </div>
                        <div class="ans">
                            <div v-for="f in s.fields" :key="f.id">
                                <div class="q">{{ f.label }}</div>
                                <div class="a" :class="{ none: !texto(f) }" :style="faltantes.some((x) => x.f === f) ? 'color: var(--danger)' : ''">{{ texto(f) || (faltantes.some((x) => x.f === f) ? 'Obligatorio' : 'Sin respuesta') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ph-foot">
                    <Button v-if="soloLectura" label="Volver" severity="secondary" outlined @click="pantalla = 'inicio'" />
                    <template v-else>
                        <Button label="Atrás" severity="secondary" outlined @click="pantalla = 'formulario'; paso = secciones.length - 1" />
                        <Button label="Enviar historia clínica" :disabled="faltantes.length > 0" :loading="ocupado" @click="enviar" />
                    </template>
                </div>
            </template>

            <!-- Formulario por secciones -->
            <template v-else>
                <div class="ph-body">
                    <div>
                        <div class="row" style="justify-content: space-between; margin-bottom: 6px"><span class="small muted">Sección {{ paso + 1 }} de {{ secciones.length }}</span><span class="small muted">{{ avance }}%</span></div>
                        <div class="prog"><i :style="{ width: ((paso + 1) * 100) / secciones.length + '%' }" /></div>
                    </div>
                    <div class="pc">
                        <h3 style="margin-bottom: 14px">{{ seccion.title }}</h3>
                        <CampoFormulario v-for="f in seccion.fields" :key="f.id" v-model="datos[f.id]" :campo="f" :archivos="archivos" :subiendo="subiendo"
                            :falta="faltan.includes(f.label) && !respondido(f)" @subir="subir" @borrar-archivo="borrarArchivo" />
                    </div>
                    <Button label="Guardar avance y continuar después" text :loading="ocupado" @click="guardar(true)" />
                </div>
                <div class="ph-foot">
                    <Button :label="paso === 0 ? 'Inicio' : 'Anterior'" severity="secondary" outlined @click="navegar(-1)" />
                    <Button :label="paso === secciones.length - 1 ? 'Revisar' : 'Siguiente'" :loading="ocupado" @click="navegar(1)" />
                </div>
            </template>
        </div>
    </div>
    <Toast position="top-center" />
</template>
