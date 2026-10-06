<script setup>
import { ref, computed, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import Segmento from '@/Components/Segmento.vue';
import { ui } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';

/**
 * Envío manual de mensajes al paciente. El texto se arma en el servidor (con el enlace del portal)
 * y se abre WhatsApp, la app de SMS o el correo con el mensaje ya escrito; el envío queda registrado.
 */
const visible = computed({ get: () => ui.dialogo === 'mensaje', set: (v) => { if (!v) ui.cerrar(); } });
const cita = computed(() => ui.datos.cita || {});
const tipo = ref('registro');
const datos = ref(null);
const texto = ref('');
const cargando = ref(false);

const TIPOS = [
    { l: 'Cita y enlace', v: 'registro', i: 'pi pi-calendar' },
    { l: 'Recordatorio', v: 'r24', i: 'pi pi-bell' },
    { l: 'Historia clínica', v: 'historia', i: 'pi pi-file-edit' },
    { l: 'Anticipo', v: 'cobro', i: 'pi pi-wallet' },
    { l: 'Personalizado', v: 'manual', i: 'pi pi-pencil' },
];

async function cargar() {
    if (!cita.value.id) return;
    cargando.value = true;
    try {
        datos.value = await api.get(route('api.citas.mensaje', cita.value.id), { tipo: tipo.value });
        texto.value = datos.value.texto;
    } finally { cargando.value = false; }
}
watch(visible, (v) => { if (v) { tipo.value = ui.datos.tipo || 'registro'; datos.value = null; cargar(); } });
watch(tipo, () => { if (visible.value) cargar(); });

const tel = computed(() => datos.value?.telefono_int);
const enlaces = computed(() => ({
    WhatsApp: tel.value ? `https://wa.me/${tel.value}?text=${encodeURIComponent(texto.value)}` : null,
    SMS: tel.value ? `sms:+${tel.value}?&body=${encodeURIComponent(texto.value)}` : null,
    'Correo electrónico': datos.value?.email ? `mailto:${datos.value.email}?subject=${encodeURIComponent('Clínica San Antonio: su cita')}&body=${encodeURIComponent(texto.value)}` : null,
}));

function enviar(canal) {
    const url = enlaces.value[canal];
    if (!url) return avisar(canal === 'Correo electrónico' ? 'El paciente no tiene correo registrado.' : 'El paciente no tiene teléfono registrado.', 'error');
    if (!texto.value.trim()) return avisar('Escribe el mensaje.', 'error');
    // Se abre primero (gesto del usuario) para que el navegador no bloquee la ventana.
    window.open(url, canal === 'WhatsApp' ? '_blank' : '_self');
    api.post(route('api.citas.mensaje.store', cita.value.id), { canal, tipo: tipo.value, texto: texto.value }).then((r) => {
        avisar(r.message);
        refrescar();
        ui.recargarCita();
    });
}
async function copiar() {
    try { await navigator.clipboard.writeText(texto.value); avisar('Mensaje copiado al portapapeles'); } catch { avisar('No se pudo copiar', 'error'); }
}
</script>

<template>
    <Modal v-model:visible="visible" titulo="Enviar mensaje al paciente" :subtitulo="cita.patient ? `${cita.patient} · ${datos?.telefono || 'sin teléfono'}` : ''" icono="pi pi-whatsapp" color="#16a34a" ancho="640px">
        <div class="field">
            <label>Tipo de mensaje</label>
            <div style="overflow-x: auto"><Segmento v-model="tipo" :opciones="TIPOS" /></div>
        </div>
        <div class="field">
            <label for="msg-txt">Mensaje <span class="muted" style="text-transform: none; letter-spacing: 0; font-weight: 500">(puedes editarlo antes de enviar)</span></label>
            <div class="msg-preview">
                <Textarea id="msg-txt" v-model="texto" rows="8" auto-resize :disabled="cargando" />
                <span class="msg-cnt">{{ texto.length }} caracteres</span>
            </div>
        </div>
        <div class="msg-canales">
            <button type="button" class="msg-btn wa" :disabled="!tel || cargando" @click="enviar('WhatsApp')">
                <i class="pi pi-whatsapp" /><span><b>WhatsApp</b><small>Abre el chat con el mensaje listo</small></span>
            </button>
            <button type="button" class="msg-btn sms" :disabled="!tel || cargando" @click="enviar('SMS')">
                <i class="pi pi-comment" /><span><b>SMS</b><small>Mensaje de texto al celular</small></span>
            </button>
            <button type="button" class="msg-btn mail" :disabled="!datos?.email || cargando" @click="enviar('Correo electrónico')">
                <i class="pi pi-envelope" /><span><b>Correo</b><small>{{ datos?.email || 'Sin correo registrado' }}</small></span>
            </button>
        </div>
        <p class="muted small"><i class="pi pi-info-circle" /> El envío queda registrado en Notificaciones y en el historial de la cita.</p>
        <template #footer>
            <Button label="Copiar mensaje" icon="pi pi-copy" severity="secondary" outlined @click="copiar" />
            <Button label="Cerrar" text @click="ui.cerrar()" />
        </template>
    </Modal>
</template>
