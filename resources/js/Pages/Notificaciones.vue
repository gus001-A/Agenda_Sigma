<script setup>
import { ref, computed, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
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

async function guardarRegla(r) {
    await api.put(route('api.recordatorios.update', r.id), { active: r.active, text: r.text, only_if_hc_pending: r.only_if_hc_pending });
    avisar('Recordatorio guardado');
}
async function guardarCanales() {
    await api.put(route('api.canales.update'), { canales: canalesLocal.value });
    avisar('Canales actualizados');
    refrescar();
}
async function ejecutar() {
    const r = await api.post(route('api.recordatorios.ejecutar'));
    avisar(r.message);
    refrescar();
}
const abrirPortal = async (id) => { const c = await api.get(route('api.citas.show', id)); window.open(c.portal_url, '_blank'); };
</script>

<template>
    <AppLayout titulo="Notificaciones" subtitulo="Mensajes enviados y recordatorios">
        <div class="split">
            <div class="card">
                <header><h2>Mensajes enviados</h2><span class="muted small">{{ total }} en total</span></header>
                <div v-for="n in mensajes" :key="n.id" class="list-item" :class="{ on: sel && sel.id === n.id }" @click="selId = n.id">
                    <span class="tag" style="min-width: 86px; text-align: center">{{ n.channel }}</span>
                    <div style="flex: 1; min-width: 0"><b style="font-weight: 500">{{ n.patient }}</b><div class="muted small">{{ n.kind }}, {{ fmtStamp(n.at) }}</div></div>
                    <Chip :valor="n.status" tipo="msg" />
                </div>
                <div v-if="!mensajes.length" class="empty"><b>Aún no se envían mensajes</b>Al registrar una cita se enviará el primero.</div>
            </div>
            <div class="stack">
                <div v-if="sel" class="card">
                    <header><h3>Vista previa</h3><span class="tag">{{ sel.channel }}</span></header>
                    <div class="body stack" :style="{ background: sel.channel === 'WhatsApp' ? 'color-mix(in srgb, #E5DDD5 60%, var(--surface))' : 'var(--surface-2)', borderRadius: '0 0 12px 12px' }">
                        <div class="bubble" :class="{ sms: sel.channel === 'SMS', mail: sel.channel === 'Correo electrónico' }">{{ sel.body }}</div>
                        <p v-if="sel.error" class="note err small">{{ sel.error }}</p>
                        <div v-if="sel.active" class="row">
                            <Button label="Abrir enlace como paciente" size="small" @click="abrirPortal(sel.appointment_id)" />
                            <Button label="Ver cita" size="small" severity="secondary" outlined @click="ui.abrirCita(sel.appointment_id)" />
                        </div>
                        <p class="muted small">Proveedor configurado en <code>config/mensajeria.php</code>. Con el driver "log", WhatsApp y SMS se registran en el log; la integración con el proveedor se define en la etapa técnica.</p>
                    </div>
                </div>
                <div class="card">
                    <header><h3>Recordatorios automáticos</h3></header>
                    <div class="body stack">
                        <div v-for="r in reglas" :key="r.id" class="stack" style="gap: 6px; padding-bottom: 12px; border-bottom: 1px solid var(--line-2)">
                            <div class="row">
                                <label class="chk"><Checkbox v-model="r.active" binary :disabled="!puede('config')" @change="guardarRegla(r)" /><b style="font-weight: 500">{{ r.hours ? r.hours + ' horas antes' : 'El día de la cita' }}</b></label>
                                <span v-if="r.only_if_hc_pending" class="tag">solo si falta la historia clínica</span>
                            </div>
                            <InputText v-model="r.text" :disabled="!puede('config')" aria-label="Texto del recordatorio" @change="guardarRegla(r)" />
                        </div>
                        <div>
                            <div class="sec-t">Canales habilitados</div>
                            <div class="row">
                                <label v-for="(on, c) in canalesLocal" :key="c" class="chk"><Checkbox v-model="canalesLocal[c]" binary :disabled="!puede('config')" @change="guardarCanales" />{{ c }}</label>
                            </div>
                        </div>
                        <Button v-if="puede('cita')" label="Ejecutar recordatorios ahora" icon="pi pi-send" @click="ejecutar" />
                        <p class="muted small">También se ejecutan automáticamente cada hora con el programador de tareas (<code>php artisan schedule:work</code>).</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
