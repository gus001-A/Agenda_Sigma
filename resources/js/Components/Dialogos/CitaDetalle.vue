<script setup>
import { ref, watch, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ui, usePermisos } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';
import { DIAS, MESES, parseISO, fmtStamp, money, initials, COLOR_ESTADO, COLOR_HC } from '@/lib/util';
import Modal from '@/Components/Modal.vue';
import SeccionModal from '@/Components/SeccionModal.vue';
import Chip from '@/Components/Chip.vue';
import Traza from '@/Components/Traza.vue';

/** Detalle de una cita en un modal centrado; se abre desde cualquier vista con ui.abrirCita(id). */
const page = usePage();
const { puede } = usePermisos();
const a = ref(null);
const cargando = ref(false);
const visible = computed({ get: () => !!ui.cita, set: (v) => { if (!v) ui.cerrarCita(); } });

async function cargar() {
    if (!ui.cita) return;
    if (a.value?.id !== ui.cita) a.value = null;
    cargando.value = true;
    try { a.value = await api.get(route('api.citas.show', ui.cita)); } catch { ui.cerrarCita(); } finally { cargando.value = false; }
}
watch(() => [ui.cita, ui.version], cargar);

async function accion(ruta, datos = {}) {
    const r = await api.post(route(ruta, a.value.id), datos);
    avisar(r.message);
    refrescar();
    cargar();
}
const estado = (s) => accion('api.citas.estado', { status: s });
const abrirPortal = () => window.open(a.value.portal_url, '_blank');

const p = computed(() => a.value?.patient_info || {});
const color = computed(() => COLOR_ESTADO[a.value?.status] || '#173a5f');
const fecha = computed(() => {
    if (!a.value) return {};
    const d = parseISO(a.value.date);
    return { dia: DIAS[d.getDay()], n: d.getDate(), mes: MESES[d.getMonth()] };
});
const subtitulo = computed(() => [p.value.age != null ? `${p.value.age} años` : null, p.value.phone ? `tel. ${p.value.phone}` : null].filter(Boolean).join(' · '));
const porcentajeAnticipo = computed(() => (a.value?.deposit > 0 ? Math.min(100, Math.round((a.value.paid * 100) / a.value.deposit)) : 100));
const hayAcciones = computed(() => a.value && ['confirmar', 'pagar', 'iniciar', 'finalizar', 'reagendar', 'cancelar', 'reembolsar'].some((k) => a.value.acciones[k]));
</script>

<template>
    <Modal v-model:visible="visible" :titulo="a?.patient || 'Cita'" :subtitulo="a ? subtitulo : 'Cargando…'" icono="pi pi-calendar" :color="color" ancho="1000px">
        <template v-if="a">
            <!-- Encabezado: fecha, hora, médico y estado -->
            <div class="cd-hero" :style="{ '--ec': color }">
                <div class="cd-fecha">
                    <small>{{ fecha.dia }}</small>
                    <b>{{ fecha.n }}</b>
                    <small>{{ fecha.mes }}</small>
                </div>
                <div class="cd-hora">
                    <span class="cd-k">Horario</span>
                    <b>{{ a.time }}</b>
                    <small>{{ a.type }}</small>
                </div>
                <div class="cd-med">
                    <span class="cd-av">{{ initials(a.doctor) }}</span>
                    <div>
                        <span class="cd-k">Médico</span>
                        <b>{{ a.doctor }}</b>
                        <small>{{ a.specialty }}<template v-if="a.room?.name"> · {{ a.room.name }}</template><template v-if="a.room?.location"> ({{ a.room.location }})</template></small>
                    </div>
                </div>
                <div class="cd-estado">
                    <Chip :valor="a.status" />
                    <span v-if="a.confirmed && a.status !== 'Confirmada'" class="cd-conf"><i class="pi pi-check" /> Confirmada por paciente</span>
                </div>
            </div>

            <div class="cd-traza"><Traza :r="a" etiqueta="Registró:" linea /></div>

            <!-- Acciones principales -->
            <div v-if="hayAcciones" class="cd-acts">
                <Button v-if="a.acciones.confirmar" label="Confirmar" icon="pi pi-check" severity="success" @click="accion('api.citas.confirmar')" />
                <Button v-if="a.acciones.pagar" label="Registrar anticipo" icon="pi pi-wallet" severity="warn" @click="ui.abrir('pago', { cita: a })" />
                <Button v-if="a.acciones.iniciar" label="Iniciar consulta" icon="pi pi-play" @click="estado('En consulta')" />
                <Button v-if="a.acciones.finalizar" label="Finalizar atención" icon="pi pi-flag" @click="ui.abrir('finalizar', { cita: a })" />
                <Button v-if="a.acciones.reagendar" label="Reagendar" icon="pi pi-calendar-clock" severity="info" outlined @click="ui.abrir('reagendar', { cita: a })" />
                <Button v-if="a.acciones.cancelar" label="Cancelar cita" icon="pi pi-times" severity="danger" outlined @click="ui.abrir('cancelar', { cita: a })" />
                <Button v-if="a.acciones.reembolsar" label="Reembolsar anticipo" icon="pi pi-replay" severity="secondary" outlined @click="ui.abrir('reembolso', { cita: a })" />
            </div>

            <div class="cd-grid">
                <div class="cd-col">
                    <SeccionModal titulo="Detalles de la consulta" icono="pi pi-clipboard" color="#6b4bc9">
                        <dl class="cd-kv">
                            <dt><i class="pi pi-tag" />Tipo</dt><dd>{{ a.type }}</dd>
                            <dt><i class="pi pi-comment" />Motivo</dt><dd>{{ a.reason || 'Sin especificar' }}</dd>
                            <template v-if="a.notes"><dt><i class="pi pi-pencil" />Observaciones</dt><dd>{{ a.notes }}</dd></template>
                            <dt><i class="pi pi-send" />Notificar por</dt><dd>{{ a.channel }}</dd>
                        </dl>
                        <Button v-if="a.acciones.editar" label="Editar datos" icon="pi pi-pencil" size="small" severity="secondary" text @click="ui.abrir('editarCita', { cita: a })" />
                    </SeccionModal>

                    <SeccionModal titulo="Pago" icono="pi pi-wallet" color="#e08a1e">
                        <div class="cd-pagos">
                            <div style="--pc: #173a5f"><span>Costo</span><b>{{ money(a.cost) }}</b></div>
                            <div style="--pc: #e08a1e"><span>Anticipo</span><b>{{ money(a.paid) }}</b><small>de {{ money(a.deposit) }}</small></div>
                            <div style="--pc: #1f9e86"><span>Por pagar en consulta</span><b>{{ money(a.cost - a.paid) }}</b></div>
                        </div>
                        <div class="cd-anticipo">
                            <div class="prog"><i :style="{ width: porcentajeAnticipo + '%' }" /></div>
                            <Chip :valor="a.deposit_state" tipo="dep" />
                            <span v-if="a.balance > 0" class="cd-debe">Falta {{ money(a.balance) }}</span>
                        </div>
                    </SeccionModal>

                    <SeccionModal v-if="a.attention_note" titulo="Nota de atención" icono="pi pi-file" color="#1f9e86">
                        <p style="white-space: pre-wrap; margin: 0">{{ a.attention_note }}</p>
                    </SeccionModal>
                </div>

                <div class="cd-col">
                    <SeccionModal titulo="Historia clínica previa" icono="pi pi-file-edit" color="#0891b2">
                        <div class="cd-hc" :style="{ '--hc': COLOR_HC[a.hc_status] || '#64748b' }">
                            <span class="cd-hc-ic"><i class="pi pi-file-edit" /></span>
                            <div style="flex: 1; min-width: 0">
                                <b>{{ a.form?.name }}</b> <span class="tag">v{{ a.form?.version }}</span>
                                <small>{{ a.hc_submitted_at ? 'Enviada ' + fmtStamp(a.hc_submitted_at) : 'Sin enviar' }}<template v-if="a.files_count"> · {{ a.files_count }} archivos</template><template v-if="a.reviewed_by"> · revisada por {{ a.reviewed_by }}</template></small>
                            </div>
                            <Chip :valor="a.hc_status" tipo="hc" />
                        </div>
                        <div class="prog cd-hc-prog" :style="{ '--hc': COLOR_HC[a.hc_status] || '#64748b' }"><i :style="{ width: a.progress + '%' }" /></div>
                        <div class="acts">
                            <Button v-if="a.acciones.verHistoria" label="Ver historia" icon="pi pi-eye" size="small" severity="info" outlined @click="ui.abrir('historia', { id: a.id })" />
                            <Button v-if="a.acciones.reenviar" label="WhatsApp / SMS" icon="pi pi-whatsapp" size="small" severity="success" @click="ui.abrir('mensaje', { cita: a })" />
                            <Button v-if="a.acciones.reenviar" label="Reenviar enlace" icon="pi pi-send" size="small" severity="secondary" outlined @click="accion('api.citas.reenviar')" />
                            <Button v-if="a.acciones.portal" label="Abrir como paciente" icon="pi pi-mobile" size="small" severity="secondary" outlined @click="abrirPortal" />
                        </div>
                        <p v-if="!puede('verclinico')" class="muted small" style="margin: 0"><i class="pi pi-lock" style="font-size: 11px" /> Tu rol puede ver el estado, no el contenido clínico.</p>
                    </SeccionModal>

                    <SeccionModal titulo="Historial de la cita" icono="pi pi-history" color="#2b5480">
                        <div class="timeline cd-timeline">
                            <div v-for="(h, i) in a.history" :key="i">{{ h.text }}<small>{{ fmtStamp(h.at) }}, {{ h.user || 'Sistema' }}</small></div>
                        </div>
                    </SeccionModal>
                </div>
            </div>
        </template>
        <div v-else-if="cargando" class="empty"><i class="pi pi-spin pi-spinner" style="font-size: 24px" /></div>

        <template #footer>
            <div v-if="a" class="cd-pie">
                <div v-if="a.acciones.estado" class="cd-cambio">
                    <span>Cambiar estado</span>
                    <Select :model-value="a.status" :options="page.props.catalogo.estadosManuales" style="min-width: 200px" @update:model-value="estado" />
                </div>
                <Button v-if="a.rescheduled_to_id" label="Ver cita reagendada" icon="pi pi-arrow-right" text @click="ui.abrirCita(a.rescheduled_to_id)" />
                <div class="sp" />
                <Button label="Cerrar" severity="secondary" outlined @click="ui.cerrarCita()" />
            </div>
        </template>
    </Modal>
</template>
