<script setup>
import { ref, computed, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import TablaDatos from '@/Components/TablaDatos.vue';
import Persona from '@/Components/Persona.vue';
import Traza from '@/Components/Traza.vue';
import { ui, usePermisos } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';
import { fmtStamp } from '@/lib/util';

const props = defineProps({ total: Number, mensajes: Array, recordatorios: Array, canales: Object });
const { puede } = usePermisos();
const selId = ref(null);
const sel = computed(() => props.mensajes.find((m) => m.id === selId.value) || props.mensajes[0]);
const reglas = ref([]);
const canalesLocal = ref({});
watch(() => props.recordatorios, (r) => { reglas.value = r.map((x) => ({ ...x })); }, { immediate: true });
watch(() => props.canales, (c) => { canalesLocal.value = { ...c }; }, { immediate: true });

const ICON_CANAL = { WhatsApp: 'pi pi-whatsapp', SMS: 'pi pi-comment', 'Correo electrónico': 'pi pi-envelope', Sistema: 'pi pi-desktop' };
const COLOR_CANAL = { WhatsApp: '#16a34a', SMS: '#2b5480', 'Correo electrónico': '#db2777', Sistema: '#64748b' };
const filas = computed(() => props.mensajes.map((m) => ({ ...m, fecha: fmtStamp(m.at) })));
const unicos = (k) => [...new Set(props.mensajes.map((x) => x[k]).filter(Boolean))].sort();
const columnas = computed(() => [
    { campo: 'channel', titulo: 'Canal', filtro: 'lista', opciones: unicos('channel'), ancho: '150px' },
    { campo: 'patient', titulo: 'Paciente', filtro: 'texto' },
    { campo: 'kind', titulo: 'Tipo', filtro: 'lista', opciones: unicos('kind') },
    { campo: 'fecha', titulo: 'Enviado', filtro: 'texto', ancho: '120px' },
    { campo: 'status', titulo: 'Estado', filtro: 'lista', opciones: unicos('status'), ancho: '120px' },
]);

async function guardarRegla(r) { await api.put(route('api.recordatorios.update', r.id), { active: r.active, text: r.text, only_if_hc_pending: r.only_if_hc_pending }); avisar('Recordatorio guardado'); }
async function guardarCanales() { await api.put(route('api.canales.update'), { canales: canalesLocal.value }); avisar('Canales actualizados'); refrescar(); }
async function ejecutar() { const r = await api.post(route('api.recordatorios.ejecutar')); avisar(r.message); refrescar(); }
const abrirPortal = async (id) => { const c = await api.get(route('api.citas.show', id)); window.open(c.portal_url, '_blank'); };
</script>

<template>
    <AppLayout titulo="Notificaciones" :subtitulo="`${total} mensajes enviados a pacientes y recordatorios automáticos`">
        <template #acciones><Button v-if="puede('cita')" label="Ejecutar recordatorios" icon="pi pi-send" @click="ejecutar" /></template>
        <div class="split">
            <TablaDatos :filas="filas" :columnas="columnas" clicable :clase-fila="(m) => (sel && m.id === sel.id ? 'p-highlight' : '')" vacio="Aún no se envían mensajes" @fila="(m) => (selId = m.id)">
                <template #c-channel="{ fila }"><span class="chip plain" :style="{ '--sb': COLOR_CANAL[fila.channel] + '1a', '--sf': COLOR_CANAL[fila.channel] }"><i :class="ICON_CANAL[fila.channel]" style="font-size: 11px" />{{ fila.channel }}</span></template>
                <template #c-patient="{ fila }"><Persona :nombre="fila.patient" /></template>
                <template #c-status="{ fila }"><Chip :valor="fila.status" tipo="msg" /></template>
            </TablaDatos>
            <div class="stack scroll-y">
                <div v-if="sel" class="card">
                    <header><span class="hic" :style="{ '--c': COLOR_CANAL[sel.channel] }"><i :class="ICON_CANAL[sel.channel]" /></span><h3>Mensaje</h3><span class="tag">{{ sel.kind }}</span></header>
                    <div class="body stack" :style="{ background: sel.channel === 'WhatsApp' ? '#efeae2' : 'var(--surface-2)', borderRadius: '0 0 14px 14px' }">
                        <div class="bubble" :class="{ sms: sel.channel === 'SMS', mail: sel.channel === 'Correo electrónico' }">{{ sel.body }}</div>
                        <Traza :r="sel" etiqueta="Envió:" />
                        <p v-if="sel.error" class="note err small">{{ sel.error }}</p>
                        <div v-if="sel.active" class="row">
                            <Button v-if="puede('cita')" label="WhatsApp / SMS" icon="pi pi-whatsapp" size="small" severity="success" @click="ui.abrir('mensaje', { cita: { id: sel.appointment_id, patient: sel.patient } })" />
                            <Button label="Abrir como paciente" icon="pi pi-mobile" size="small" severity="secondary" outlined @click="abrirPortal(sel.appointment_id)" />
                            <Button label="Ver cita" icon="pi pi-eye" size="small" severity="secondary" outlined @click="ui.abrirCita(sel.appointment_id)" />
                        </div>
                    </div>
                </div>
                <div class="card">
                    <header><span class="hic" style="--c: #db2777"><i class="pi pi-bell" /></span><h3>Recordatorios automáticos</h3></header>
                    <div class="body stack">
                        <div v-for="r in reglas" :key="r.id" class="stack" style="gap: 6px; padding-bottom: 12px; border-bottom: 1px solid var(--line-2)">
                            <div class="row">
                                <label class="chk"><ToggleSwitch v-model="r.active" :disabled="!puede('config')" @change="guardarRegla(r)" /><b style="font-weight: 600">{{ r.hours ? r.hours + ' horas antes' : 'El día de la cita' }}</b></label>
                                <span v-if="r.only_if_hc_pending" class="tag">solo si falta la historia clínica</span>
                            </div>
                            <InputText v-model="r.text" :disabled="!puede('config')" aria-label="Texto del recordatorio" @change="guardarRegla(r)" />
                        </div>
                        <div>
                            <div class="sec-t">Canales habilitados</div>
                            <div class="row">
                                <label v-for="(on, c) in canalesLocal" :key="c" class="chk"><Checkbox v-model="canalesLocal[c]" binary :disabled="!puede('config')" @change="guardarCanales" /><i :class="ICON_CANAL[c]" :style="{ color: COLOR_CANAL[c] }" />{{ c }}</label>
                            </div>
                        </div>
                        <p class="muted small"><i class="pi pi-info-circle" style="font-size: 11px" /> Se ejecutan cada hora con <code>php artisan schedule:work</code>. Proveedor configurable en <code>config/mensajeria.php</code>.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
