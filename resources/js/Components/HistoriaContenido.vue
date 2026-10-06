<script setup>
import Modal from '@/Components/Modal.vue';
import { ref } from 'vue';
import axios from 'axios';
import { fmtStamp } from '@/lib/util';

/** Resumen y respuestas de la historia clínica (usado en el diálogo y en Preconsulta). */
defineProps({ resumen: Object, secciones: Array, estado: String });
const archivo = ref(null);

async function abrir(f) {
    // La consulta del documento queda registrada en auditoría.
    const r = await axios.get(route('api.archivos.show', f.id), { params: { info: 1 } });
    archivo.value = { ...r.data, url: route('api.archivos.show', f.id) };
}
</script>

<template>
    <div class="summary">
        <div><span>Paciente</span><b>{{ resumen.paciente }}</b></div>
        <div><span>Edad</span><b>{{ resumen.edad }} años</b></div>
        <div><span>Motivo</span><b>{{ resumen.motivo }}</b></div>
        <div><span>Historia clínica</span><b>{{ resumen.estado }}</b></div>
        <div><span>Alergias</span><b :style="resumen.alergias_alerta ? 'color: var(--danger)' : ''">{{ resumen.alergias }}</b></div>
        <div><span>Medicamentos</span><b>{{ resumen.medicamentos }}</b></div>
        <div><span>Documentos</span><b>{{ resumen.documentos }} archivos</b></div>
        <div><span>Formato</span><b>v{{ resumen.version }}</b></div>
    </div>
    <p v-if="estado === 'Pendiente'" class="note warn">El paciente aún no comienza el formulario.</p>
    <p v-else-if="estado === 'En proceso'" class="note">El paciente comenzó el formulario pero no lo ha enviado; se muestran las respuestas guardadas.</p>
    <div v-for="s in secciones" :key="s.title" class="card">
        <header><h3>{{ s.title }}</h3></header>
        <div class="body ans">
            <div v-for="f in s.fields" :key="f.id">
                <div class="q">{{ f.label }}</div>
                <template v-if="f.type === 'signature'">
                    <img v-if="f.signature" :src="f.signature" alt="Firma" style="max-width: 240px; background: #fff; border: 1px solid var(--line); border-radius: 8px" />
                    <div v-else class="a none">Sin firma</div>
                </template>
                <template v-else-if="f.files">
                    <div v-if="f.files.length" class="filelist">
                        <div v-for="x in f.files" :key="x.id"><i class="pi pi-file" /><span>{{ x.name }}</span><small class="muted">{{ x.size }} KB</small>
                            <Button label="Abrir" size="small" text @click="abrir(x)" /></div>
                    </div>
                    <div v-else class="a none">Sin archivos</div>
                </template>
                <div v-else class="a" :class="{ none: !f.text }">{{ f.text || (f.mode === 'required' ? 'Sin responder' : 'Sin respuesta') }}</div>
            </div>
        </div>
    </div>

    <Modal icono="pi pi-paperclip" color="#6b4bc9" subtitulo="Documento clínico" :visible="!!archivo" :header="archivo?.name" ancho="640px" @update:visible="archivo = null">
        <template v-if="archivo">
            <img v-if="archivo.exists && archivo.image" :src="archivo.url" :alt="archivo.name" style="max-width: 100%; border-radius: 8px" />
            <iframe v-else-if="archivo.exists" :src="archivo.url" style="width: 100%; height: 60vh; border: 1px solid var(--line); border-radius: 8px" />
            <div v-else class="empty" style="border: 1px dashed var(--line); border-radius: 10px">
                <i class="pi pi-file" style="font-size: 36px" /><b style="margin-top: 8px">{{ archivo.name }}</b>{{ archivo.size }} KB, cargado {{ fmtStamp(archivo.at) }}.<br />Documento de demostración sin archivo físico.
            </div>
            <div class="row">
                <a v-if="archivo.exists" :href="archivo.url" target="_blank" class="small"><i class="pi pi-external-link" /> Abrir en otra pestaña</a>
                <span class="muted small"><i class="pi pi-lock" style="font-size: 11px" /> Acceso registrado en auditoría. Solo usuarios autorizados pueden visualizar documentos clínicos.</span>
            </div>
        </template>
    </Modal>
</template>
