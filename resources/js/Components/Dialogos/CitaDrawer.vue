<script setup>
import { ref, watch, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ui, usePermisos } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';
import { fmtDL, fmtStamp, money } from '@/lib/util';
import Chip from '@/Components/Chip.vue';

const page = usePage();
const { puede } = usePermisos();
const a = ref(null);
const cargando = ref(false);
const visible = computed({ get: () => !!ui.cita, set: (v) => { if (!v) ui.cerrarCita(); } });

async function cargar() {
    if (!ui.cita) return;
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
</script>

<template>
    <Drawer v-model:visible="visible" position="right" style="width: min(520px, 100%)" :header="' '">
        <template #header>
            <div v-if="a" style="flex: 1; min-width: 0">
                <div class="row" style="gap: 6px; margin-bottom: 6px">
                    <Chip :valor="a.status" />
                    <span v-if="a.confirmed && a.status !== 'Confirmada'" class="tag">Confirmada por paciente</span>
                </div>
                <h2>{{ a.patient }}</h2>
                <p class="muted small">{{ p.sigma_id }}<template v-if="p.age !== null">, {{ p.age }} años</template><template v-if="p.phone">, tel. {{ p.phone }}</template></p>
            </div>
        </template>
        <template v-if="a">
            <div class="acts">
                <Button v-if="a.acciones.confirmar" label="Confirmar" icon="pi pi-check" severity="secondary" outlined @click="accion('api.citas.confirmar')" />
                <Button v-if="a.acciones.pagar" label="Registrar anticipo" icon="pi pi-wallet" severity="warn" @click="ui.abrir('pago', { cita: a })" />
                <Button v-if="a.acciones.iniciar" label="Iniciar consulta" @click="estado('En consulta')" />
                <Button v-if="a.acciones.finalizar" label="Finalizar atención" @click="ui.abrir('finalizar', { cita: a })" />
                <Button v-if="a.acciones.reagendar" label="Reagendar" severity="secondary" outlined @click="ui.abrir('reagendar', { cita: a })" />
                <Button v-if="a.acciones.cancelar" label="Cancelar cita" severity="danger" outlined @click="ui.abrir('cancelar', { cita: a })" />
                <Button v-if="a.acciones.reembolsar" label="Reembolsar anticipo" severity="secondary" outlined @click="ui.abrir('reembolso', { cita: a })" />
            </div>

            <dl class="kv">
                <dt>Fecha y hora</dt><dd><b style="font-weight: 500">{{ fmtDL(a.date) }}, {{ a.time }}</b></dd>
                <dt>Médico</dt><dd>{{ a.doctor }}</dd>
                <dt>Especialidad</dt><dd>{{ a.specialty }}</dd>
                <dt>Consultorio</dt><dd>{{ a.room?.name }} <span v-if="a.room?.location" class="muted">({{ a.room.location }})</span></dd>
                <dt>Tipo</dt><dd>{{ a.type }}</dd>
                <dt>Motivo</dt><dd>{{ a.reason || 'Sin especificar' }}</dd>
                <template v-if="a.notes"><dt>Observaciones</dt><dd>{{ a.notes }}</dd></template>
                <dt>Notificar por</dt><dd>{{ a.channel }}</dd>
            </dl>

            <div>
                <div class="sec-t">Pago</div>
                <dl class="kv">
                    <dt>Costo</dt><dd>{{ money(a.cost) }}</dd>
                    <dt>Anticipo</dt><dd>{{ money(a.paid) }} de {{ money(a.deposit) }} <Chip :valor="a.deposit_state" tipo="dep" /></dd>
                    <template v-if="a.balance > 0"><dt>Saldo de anticipo</dt><dd style="color: var(--accent); font-weight: 500">{{ money(a.balance) }}</dd></template>
                    <dt>Saldo a pagar en consulta</dt><dd>{{ money(a.cost - a.paid) }}</dd>
                </dl>
            </div>

            <div>
                <div class="sec-t">Historia clínica previa</div>
                <div class="row" style="justify-content: space-between">
                    <div>
                        <b style="font-weight: 500">{{ a.form?.name }}</b> <span class="tag">v{{ a.form?.version }}</span>
                        <div class="muted small">
                            {{ a.hc_submitted_at ? 'Enviada ' + fmtStamp(a.hc_submitted_at) : 'Sin enviar' }}<template v-if="a.files_count">, {{ a.files_count }} archivos</template><template v-if="a.reviewed_by">, revisada por {{ a.reviewed_by }}</template>
                        </div>
                    </div>
                    <Chip :valor="a.hc_status" tipo="hc" />
                </div>
                <div class="prog" style="margin-top: 8px"><i :style="{ width: a.progress + '%' }" /></div>
                <div class="acts" style="margin-top: 10px">
                    <Button v-if="a.acciones.verHistoria" label="Ver historia clínica" icon="pi pi-eye" size="small" severity="secondary" outlined @click="ui.abrir('historia', { id: a.id })" />
                    <Button v-if="a.acciones.reenviar" label="WhatsApp / SMS" icon="pi pi-whatsapp" size="small" severity="success" @click="ui.abrir('mensaje', { cita: a })" />
                    <Button v-if="a.acciones.reenviar" label="Reenviar enlace" icon="pi pi-send" size="small" severity="secondary" outlined @click="accion('api.citas.reenviar')" />
                    <Button v-if="a.acciones.portal" label="Abrir como paciente" icon="pi pi-mobile" size="small" severity="secondary" outlined @click="abrirPortal" />
                    <Button v-if="a.acciones.editar" label="Editar datos" size="small" severity="secondary" outlined @click="ui.abrir('editarCita', { cita: a })" />
                </div>
                <p v-if="!puede('verclinico')" class="muted small" style="margin-top: 6px"><i class="pi pi-lock" style="font-size: 11px" /> Tu rol puede ver el estado, no el contenido clínico.</p>
            </div>

            <div v-if="a.attention_note"><div class="sec-t">Nota de atención</div><p style="white-space: pre-wrap">{{ a.attention_note }}</p></div>
            <div v-if="a.rescheduled_to_id"><Button label="Ver cita reagendada" icon="pi pi-arrow-right" size="small" text @click="ui.abrirCita(a.rescheduled_to_id)" /></div>

            <div v-if="a.acciones.estado">
                <div class="sec-t">Cambiar estado</div>
                <Select :model-value="a.status" :options="page.props.catalogo.estadosManuales" style="min-width: 220px" @update:model-value="estado" />
            </div>

            <div>
                <div class="sec-t">Historial de la cita</div>
                <div class="timeline">
                    <div v-for="(h, i) in a.history" :key="i">{{ h.text }}<small>{{ fmtStamp(h.at) }}, {{ h.user || 'Sistema' }}</small></div>
                </div>
            </div>
        </template>
        <div v-else-if="cargando" class="empty"><i class="pi pi-spin pi-spinner" /></div>
    </Drawer>
</template>
