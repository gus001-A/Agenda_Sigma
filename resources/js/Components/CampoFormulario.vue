<script setup>
import { computed } from 'vue';
import Fecha from '@/Components/Fecha.vue';
import Hora from '@/Components/Hora.vue';
import FirmaCanvas from '@/Components/FirmaCanvas.vue';

/** Renderiza un campo de formato clínico (portal del paciente y vista previa del constructor). */
const props = defineProps({
    campo: Object, modelValue: null, archivos: { type: Array, default: () => [] },
    vistaPrevia: Boolean, deshabilitado: Boolean, falta: Boolean, subiendo: Boolean,
});
const emit = defineEmits(['update:modelValue', 'subir', 'borrar-archivo']);

const f = computed(() => props.campo);
const soloLectura = computed(() => f.value.mode === 'readonly' || props.deshabilitado);
const nombre = computed(() => 'f_' + f.value.id);
const v = computed({ get: () => props.modelValue, set: (x) => emit('update:modelValue', x) });
const misArchivos = computed(() => props.archivos.filter((a) => a.field_id === f.value.id));

// Valores compuestos (alergias, medicamentos, antecedentes)
const compuesto = (base) => ({ ...base, ...(props.modelValue || {}) });
function setComp(base, k, x) { v.value = { ...compuesto(base), [k]: x }; }
const filas = (base, def) => { const r = compuesto(base).rows || []; return r.length ? r : [def]; };
function setFila(base, def, i, k, x) { const r = filas(base, def).map((y) => ({ ...y })); r[i][k] = x; setComp(base, 'rows', r); }
function agregarFila(base, def) { setComp(base, 'rows', [...filas(base, def), { ...def }]); }
function quitarFila(base, def, i) { const r = filas(base, def).filter((_, j) => j !== i); setComp(base, 'rows', r); }
const AL = { none: false, rows: [] }, ALD = { t: 'Medicamento', d: '' };
const ME = { none: false, rows: [] }, MED = { m: '', d: '', f: '' };
const HI = { sel: [], notes: '' };

function toggleMulti(o, on) { const s = new Set(v.value || []); on ? s.add(o) : s.delete(o); v.value = [...s]; }
function toggleHist(o, on) { const s = new Set(compuesto(HI).sel || []); on ? s.add(o) : s.delete(o); setComp(HI, 'sel', [...s]); }
const colorEscala = (i) => `hsl(${120 - i * 12} 55% 42%)`;
function alSubir(e) { emit('subir', f.value.id, [...e.target.files]); e.target.value = ''; }
</script>

<template>
    <div class="pf" :class="{ miss: falta }">
        <label class="pf-l" :for="['text', 'textarea', 'number'].includes(f.type) ? nombre : undefined">
            {{ f.label }}<span v-if="f.mode === 'required'" class="req" aria-label="obligatorio">*</span>
            <span v-if="f.mode === 'readonly'" class="tag" style="margin-left: 6px">solo lectura</span>
        </label>

        <InputText v-if="f.type === 'text'" :id="nombre" v-model="v" :disabled="soloLectura" />
        <Textarea v-else-if="f.type === 'textarea'" :id="nombre" v-model="v" rows="3" auto-resize :disabled="soloLectura" />
        <InputNumber v-else-if="f.type === 'number'" :input-id="nombre" v-model="v" :min="0" :use-grouping="false" :disabled="soloLectura" fluid />
        <Fecha v-else-if="f.type === 'date'" v-model="v" :disabled="soloLectura" />
        <Hora v-else-if="f.type === 'time'" v-model="v" :disabled="soloLectura" />
        <Select v-else-if="f.type === 'select'" v-model="v" :options="f.options" placeholder="Seleccione" :disabled="soloLectura" show-clear fluid />

        <div v-else-if="f.type === 'yesno' || f.type === 'single'" class="pills" role="radiogroup">
            <label v-for="o in f.type === 'yesno' ? ['Sí', 'No'] : f.options" :key="o">
                <input type="radio" :name="nombre" :value="o" :checked="v === o" :disabled="soloLectura" @change="v = o" />{{ o }}
            </label>
        </div>

        <div v-else-if="f.type === 'multi'" class="pills">
            <label v-for="o in f.options" :key="o"><input type="checkbox" :checked="(v || []).includes(o)" :disabled="soloLectura" @change="toggleMulti(o, $event.target.checked)" />{{ o }}</label>
        </div>

        <template v-else-if="f.type === 'history'">
            <div class="pills">
                <label v-for="o in f.options" :key="o"><input type="checkbox" :checked="(compuesto(HI).sel || []).includes(o)" :disabled="soloLectura" @change="toggleHist(o, $event.target.checked)" />{{ o }}</label>
            </div>
            <InputText :model-value="compuesto(HI).notes" placeholder="Detalles (opcional)" :disabled="soloLectura" @update:model-value="setComp(HI, 'notes', $event)" />
        </template>

        <template v-else-if="f.type === 'scale'">
            <div class="scale">
                <button v-for="i in 11" :key="i" type="button" :class="{ on: v === i - 1 }" :style="v === i - 1 ? { background: colorEscala(i - 1) } : {}"
                    :disabled="soloLectura" :aria-label="String(i - 1)" @click="v = i - 1">{{ i - 1 }}</button>
            </div>
            <div class="row small muted" style="justify-content: space-between"><span>Sin dolor</span><span>Máximo</span></div>
        </template>

        <template v-else-if="f.type === 'allergies'">
            <label class="chk"><Checkbox :model-value="!!compuesto(AL).none" binary :disabled="soloLectura" @update:model-value="setComp(AL, 'none', $event)" />No tengo alergias conocidas</label>
            <div v-if="!compuesto(AL).none" class="rows">
                <div v-for="(r, i) in filas(AL, ALD)" :key="i" class="r">
                    <Select :model-value="r.t" :options="['Medicamento', 'Alimento', 'Otro']" :disabled="soloLectura" aria-label="Tipo" @update:model-value="setFila(AL, ALD, i, 't', $event)" />
                    <InputText :model-value="r.d" placeholder="¿A qué?" :disabled="soloLectura" @update:model-value="setFila(AL, ALD, i, 'd', $event)" />
                    <Button v-if="!soloLectura" icon="pi pi-times" text rounded size="small" aria-label="Quitar" @click="quitarFila(AL, ALD, i)" />
                </div>
                <Button v-if="!soloLectura" label="Agregar alergia" icon="pi pi-plus" size="small" text style="justify-self: start" @click="agregarFila(AL, ALD)" />
            </div>
        </template>

        <template v-else-if="f.type === 'meds'">
            <label class="chk"><Checkbox :model-value="!!compuesto(ME).none" binary :disabled="soloLectura" @update:model-value="setComp(ME, 'none', $event)" />No tomo medicamentos</label>
            <div v-if="!compuesto(ME).none" class="rows">
                <div v-for="(r, i) in filas(ME, MED)" :key="i" class="r three">
                    <InputText :model-value="r.m" placeholder="Medicamento" :disabled="soloLectura" @update:model-value="setFila(ME, MED, i, 'm', $event)" />
                    <InputText :model-value="r.d" placeholder="Dosis" :disabled="soloLectura" @update:model-value="setFila(ME, MED, i, 'd', $event)" />
                    <InputText :model-value="r.f" placeholder="Frecuencia" :disabled="soloLectura" @update:model-value="setFila(ME, MED, i, 'f', $event)" />
                    <Button v-if="!soloLectura" icon="pi pi-times" text rounded size="small" aria-label="Quitar" @click="quitarFila(ME, MED, i)" />
                </div>
                <Button v-if="!soloLectura" label="Agregar medicamento" icon="pi pi-plus" size="small" text style="justify-self: start" @click="agregarFila(ME, MED)" />
            </div>
        </template>

        <template v-else-if="f.type === 'files' || f.type === 'photo'">
            <div class="filelist">
                <div v-for="a in misArchivos" :key="a.id">
                    <i class="pi pi-file" /><span>{{ a.name }}</span><small class="muted">{{ a.size }} KB</small>
                    <Button v-if="!vistaPrevia && !soloLectura" icon="pi pi-times" text rounded size="small" aria-label="Quitar" @click="emit('borrar-archivo', a.id)" />
                </div>
            </div>
            <label v-if="!soloLectura" class="p-button p-component p-button-sm p-button-outlined p-button-secondary" style="justify-self: start; cursor: pointer" :class="{ 'p-disabled': vistaPrevia || subiendo }">
                <i class="pi" :class="subiendo ? 'pi-spin pi-spinner' : 'pi-plus'" style="margin-right: 6px" />{{ f.type === 'photo' ? 'Tomar o elegir foto' : 'Adjuntar archivo' }}
                <input type="file" hidden multiple :accept="f.type === 'photo' ? 'image/*' : 'image/*,.pdf'" :disabled="vistaPrevia || subiendo" @change="alSubir" />
            </label>
            <span class="small muted">PDF o imagen, máximo 10 MB.</span>
        </template>

        <FirmaCanvas v-else-if="f.type === 'signature'" v-model="v" :disabled="soloLectura || vistaPrevia" />

        <template v-else-if="f.type === 'consent'">
            <div class="consent">
                Clínica San Antonio es responsable del tratamiento de sus datos personales y clínicos, que se usarán exclusivamente para su atención médica,
                el seguimiento de su tratamiento y la integración de su expediente. Sus datos se resguardan con medidas de seguridad administrativas y técnicas,
                y solo el personal autorizado puede consultarlos. Puede ejercer sus derechos de acceso, rectificación, cancelación u oposición en recepción.
            </div>
            <label class="chk"><Checkbox :model-value="!!v?.accepted" binary :disabled="soloLectura" @update:model-value="v = $event ? { ...(modelValue || {}), accepted: true } : null" />He leído y acepto el aviso de privacidad</label>
        </template>
    </div>
</template>
