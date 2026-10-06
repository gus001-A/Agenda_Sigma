<script setup>
import Modal from '@/Components/Modal.vue';
import { ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Segmento from '@/Components/Segmento.vue';
import CampoFormulario from '@/Components/CampoFormulario.vue';
import { usePermisos } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';
import { fmtDS } from '@/lib/util';

const props = defineProps({ lista: Array, formato: Object });
const page = usePage();
const { puede } = usePermisos();
const cfg = computed(() => puede('config'));
const cat = computed(() => page.props.catalogo);
const tipos = computed(() => Object.entries(cat.value.tiposCampo).map(([v, l]) => ({ v, l })));
const modos = [{ v: 'required', l: 'Obligatorio' }, { v: 'optional', l: 'Opcional' }, { v: 'readonly', l: 'Solo lectura' }];

const secs = ref([]);
const meta = ref({});
const vista = ref(false);
let ultimo = ''; // último estado conocido en el servidor (evita guardar borradores idénticos)
watch(() => props.formato, (f) => {
    if (!f) return;
    secs.value = JSON.parse(JSON.stringify(f.borrador || f.publicadas));
    ultimo = JSON.stringify(secs.value);
    meta.value = { name: f.name, specialty_id: f.specialty_id, consult_type: f.consult_type, active: f.active };
}, { immediate: true });
const sucio = computed(() => props.formato && JSON.stringify(secs.value) !== JSON.stringify(props.formato.publicadas));

// Guardado automático del borrador
let t = null;
watch(secs, () => {
    if (!cfg.value || !props.formato || JSON.stringify(secs.value) === ultimo) return;
    clearTimeout(t);
    t = setTimeout(() => { ultimo = JSON.stringify(secs.value); api.put(route('api.formatos.borrador', props.formato.id), { sections: secs.value }, { silencioso: true }); }, 600);
}, { deep: true });

const elegir = (id) => router.get(route('formatos'), { id }, { preserveScroll: true });
async function guardarMeta() { await api.put(route('api.formatos.update', props.formato.id), meta.value); avisar('Formato actualizado'); refrescar(); }
const uid = () => 'c' + Math.random().toString(36).slice(2, 8);
const mover = (arr, i, d) => { const j = i + d; if (j < 0 || j >= arr.length) return; [arr[i], arr[j]] = [arr[j], arr[i]]; };
const agregarCampo = (s) => s.fields.push({ id: uid(), label: 'Nueva pregunta', type: 'text', mode: 'optional', options: [] });
const agregarSeccion = () => secs.value.push({ title: 'Nueva sección', fields: [{ id: uid(), label: 'Nueva pregunta', type: 'text', mode: 'optional', options: [] }] });
function cambiarTipo(c, v) { c.type = v; if (cat.value.tiposConOpciones.includes(v) && !c.options?.length) c.options = ['Opción 1', 'Opción 2']; }
const opcionesTexto = (c) => (c.options || []).join(', ');
const setOpciones = (c, v) => { c.options = v.split(',').map((s) => s.trim()).filter(Boolean); };

async function descartar() {
    clearTimeout(t);
    const r = await api.del(route('api.formatos.descartar', props.formato.id));
    avisar(r.message);
    secs.value = JSON.parse(JSON.stringify(props.formato.publicadas));
    refrescar();
}

// Publicar
const pub = ref(null);
const versionSig = (mayor) => { const [ma, mi] = props.formato.version.split('.').map(Number); return mayor ? `${ma + 1}.0` : `${ma}.${mi + 1}`; };
async function publicar() {
    clearTimeout(t);
    await api.put(route('api.formatos.borrador', props.formato.id), { sections: secs.value });
    const r = await api.post(route('api.formatos.publicar', props.formato.id), pub.value);
    avisar(r.message);
    pub.value = null;
    refrescar();
}

// Nuevo formato
const nuevo = ref(null);
async function crear() {
    if (!nuevo.value.name?.trim()) return avisar('Escribe un nombre.', 'error');
    const r = await api.post(route('api.formatos.store'), nuevo.value);
    avisar(r.message);
    nuevo.value = null;
    elegir(r.id);
}
</script>

<template>
    <AppLayout titulo="Formatos clínicos" subtitulo="Constructor de formularios por especialidad">
        <div class="split" style="grid-template-columns: 290px minmax(0, 1fr)">
            <div class="card fb-list">
                <header><h3>Formatos</h3><Button v-if="cfg" label="Nuevo" icon="pi pi-plus" size="small" severity="secondary" outlined @click="nuevo = { name: '', specialty_id: null, consult_type: 'Primera consulta', plantilla: true }" /></header>
                <a v-for="x in lista" :key="x.id" :class="{ on: formato && x.id === formato.id }" @click="elegir(x.id)">
                    <b>{{ x.name }}</b>
                    <span class="muted small">{{ x.specialty || 'Todas las especialidades' }}, {{ x.consult_type }}, v{{ x.version }}{{ x.draft ? ' (borrador)' : '' }}{{ x.active ? '' : ' (inactivo)' }}</span>
                </a>
            </div>
            <div v-if="formato" class="stack">
                <div class="card pad stack">
                    <div class="grid3" style="grid-template-columns: 1.6fr 1fr 1fr">
                        <div class="field"><label for="fb-n">Nombre del formato</label><InputText id="fb-n" v-model="meta.name" :disabled="!cfg" @change="guardarMeta" /></div>
                        <div class="field"><label>Especialidad</label><Select v-model="meta.specialty_id" :options="page.props.opciones.especialidades" option-label="name" option-value="id" placeholder="Todas (formato general)" show-clear :disabled="!cfg" @change="guardarMeta" /></div>
                        <div class="field"><label>Tipo de consulta</label><Select v-model="meta.consult_type" :options="cat.tiposConsulta" :disabled="!cfg" @change="guardarMeta" /></div>
                    </div>
                    <div class="row">
                        <span class="tag">Versión publicada v{{ formato.version }}</span>
                        <span v-if="sucio" class="chip hpen">Cambios sin publicar</span>
                        <label class="chk"><Checkbox v-model="meta.active" binary :disabled="!cfg" @change="guardarMeta" />Activo</label>
                        <div style="flex: 1" />
                        <Segmento v-model="vista" :opciones="[{ l: 'Editar', v: false, i: 'pi pi-pencil' }, { l: 'Vista previa', v: true, i: 'pi pi-mobile' }]" />
                        <template v-if="cfg && sucio">
                            <Button label="Descartar" text @click="descartar" />
                            <Button label="Publicar versión" icon="pi pi-upload" @click="pub = { mayor: false, notes: '' }" />
                        </template>
                    </div>
                    <p v-if="!cfg" class="muted small"><i class="pi pi-lock" style="font-size: 11px" /> Solo el Administrador puede modificar formatos.</p>
                </div>

                <div v-if="vista" class="card pad" style="background: var(--bg)">
                    <p class="muted small" style="margin-bottom: 12px">Vista previa de cómo lo verá el paciente{{ sucio ? ' (incluye cambios sin publicar)' : '' }}.</p>
                    <div v-for="(s, si) in secs" :key="si" class="pc" style="margin-bottom: 12px">
                        <h3 style="margin-bottom: 12px">{{ s.title }}</h3>
                        <CampoFormulario v-for="c in s.fields" :key="c.id" :campo="c" vista-previa />
                    </div>
                </div>
                <template v-else>
                    <div v-for="(s, si) in secs" :key="si" class="fb-sec">
                        <header>
                            <InputText v-model="s.title" :disabled="!cfg" aria-label="Título de la sección" />
                            <span class="muted small" style="white-space: nowrap">{{ s.fields.length }} campos</span>
                            <template v-if="cfg">
                                <Button icon="pi pi-arrow-up" text rounded size="small" aria-label="Subir sección" @click="mover(secs, si, -1)" />
                                <Button icon="pi pi-arrow-down" text rounded size="small" aria-label="Bajar sección" @click="mover(secs, si, 1)" />
                                <Button icon="pi pi-trash" text rounded size="small" severity="danger" aria-label="Eliminar sección" @click="secs.splice(si, 1)" />
                            </template>
                        </header>
                        <div v-for="(c, fi) in s.fields" :key="c.id" class="fb-f">
                            <span class="ix">{{ fi + 1 }}</span>
                            <InputText v-model="c.label" :disabled="!cfg" aria-label="Etiqueta" />
                            <Select :model-value="c.type" :options="tipos" option-label="l" option-value="v" :disabled="!cfg" aria-label="Tipo" @update:model-value="cambiarTipo(c, $event)" />
                            <Select v-model="c.mode" :options="modos" option-label="l" option-value="v" :disabled="!cfg" aria-label="Requisito" />
                            <div class="tools">
                                <template v-if="cfg">
                                    <Button icon="pi pi-arrow-up" text rounded size="small" aria-label="Subir" @click="mover(s.fields, fi, -1)" />
                                    <Button icon="pi pi-arrow-down" text rounded size="small" aria-label="Bajar" @click="mover(s.fields, fi, 1)" />
                                    <Button icon="pi pi-trash" text rounded size="small" severity="danger" aria-label="Eliminar" @click="s.fields.splice(fi, 1)" />
                                </template>
                            </div>
                            <div v-if="cat.tiposConOpciones.includes(c.type)" class="opts">
                                <InputText :model-value="opcionesTexto(c)" :disabled="!cfg" placeholder="Opciones separadas por coma" aria-label="Opciones" fluid @change="setOpciones(c, $event.target.value)" />
                            </div>
                        </div>
                        <div v-if="cfg" style="padding: 10px 12px"><Button label="Agregar campo" icon="pi pi-plus" size="small" text @click="agregarCampo(s)" /></div>
                    </div>
                    <Button v-if="cfg" label="Agregar sección" icon="pi pi-plus" severity="secondary" outlined style="justify-self: start" @click="agregarSeccion" />
                </template>

                <div class="card">
                    <header><h3>Versiones</h3><span class="muted small">Las historias ya contestadas conservan la versión con la que se respondieron.</span></header>
                    <div class="tbl-wrap">
                        <table class="tbl">
                            <thead><tr><th>Versión</th><th>Fecha</th><th>Cambios</th><th>Publicó</th><th>Campos</th><th class="num">Historias respondidas</th></tr></thead>
                            <tbody>
                                <tr v-for="v in formato.versiones" :key="v.version">
                                    <td><b>v{{ v.version }}</b> <span v-if="v.version === formato.version" class="tag">vigente</span></td>
                                    <td>{{ fmtDS(v.date) }}</td><td>{{ v.notes }}</td><td>{{ v.by }}</td><td>{{ v.campos }}</td><td class="num">{{ v.usos }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <Modal icono="pi pi-upload" color="#7c3aed" subtitulo="Las historias contestadas conservan su versión" :visible="!!pub" header="Publicar nueva versión" ancho="520px" @update:visible="pub = null">
            <template v-if="pub">
                <p>Las citas nuevas usarán esta versión. Las {{ formato.usos }} historias ya respondidas conservarán su versión original.</p>
                <div class="pills">
                    <label><input type="radio" :checked="!pub.mayor" @change="pub.mayor = false" />Cambio menor: v{{ versionSig(false) }}</label>
                    <label><input type="radio" :checked="pub.mayor" @change="pub.mayor = true" />Cambio mayor: v{{ versionSig(true) }}</label>
                </div>
                <div class="field"><label for="pv-n">Descripción de los cambios</label><InputText id="pv-n" v-model="pub.notes" placeholder="Ej. se agregó pregunta sobre actividad física" /></div>
            </template>
            <template #footer><Button label="Cancelar" text @click="pub = null" /><Button label="Publicar" @click="publicar" /></template>
        </Modal>

        <Modal icono="pi pi-sliders-h" color="#7c3aed" subtitulo="Después podrás ajustar secciones y campos" :visible="!!nuevo" header="Nuevo formato clínico" ancho="560px" @update:visible="nuevo = null">
            <template v-if="nuevo">
                <div class="field"><label for="nf-n">Nombre</label><InputText id="nf-n" v-model="nuevo.name" placeholder="Ej. Historia clínica de Cardiología" /></div>
                <div class="grid2">
                    <div class="field"><label>Especialidad</label><Select v-model="nuevo.specialty_id" :options="page.props.opciones.especialidades" option-label="name" option-value="id" placeholder="Todas (general)" show-clear /></div>
                    <div class="field"><label>Tipo de consulta</label><Select v-model="nuevo.consult_type" :options="cat.tiposConsulta" /></div>
                </div>
                <label class="chk"><Checkbox v-model="nuevo.plantilla" binary />Partir de la plantilla general (datos, motivo, antecedentes, medicamentos, alergias, estudios y consentimiento)</label>
            </template>
            <template #footer><Button label="Cancelar" text @click="nuevo = null" /><Button label="Crear formato" @click="crear" /></template>
        </Modal>
    </AppLayout>
</template>
