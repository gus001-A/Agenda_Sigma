<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';
import { api, avisar } from '@/lib/api';
import { initials } from '@/lib/util';
import Fecha from '@/Components/Fecha.vue';

/** Búsqueda de expediente SIGMA24 con alta controlada de paciente nuevo. v-model = objeto paciente. */
const props = defineProps({ modelValue: Object, permitirAlta: { type: Boolean, default: true }, opcional: Boolean, placeholder: String });
const emit = defineEmits(['update:modelValue']);

const q = ref('');
const resultados = ref([]);
const alta = ref(false);
const nuevo = ref({ name: '', birth_date: null, phone: '', email: '' });
const guardando = ref(false);
let t = null;

async function buscar() {
    const r = await api.get(route('api.pacientes.buscar'), { q: q.value });
    resultados.value = r;
}
watch(q, () => { clearTimeout(t); t = setTimeout(buscar, 220); });
onMounted(() => { if (!props.modelValue) buscar(); });

function elegir(p) { emit('update:modelValue', p); }
function cambiar() { emit('update:modelValue', null); q.value = ''; buscar(); }

async function darAlta() {
    if (!nuevo.value.name || !nuevo.value.birth_date || !nuevo.value.phone) return avisar('Nombre, fecha de nacimiento y teléfono son obligatorios.', 'error');
    guardando.value = true;
    try {
        const r = await axios.post(route('api.pacientes.store'), nuevo.value);
        avisar(r.data.message);
        alta.value = false;
        elegir(r.data.patient);
    } catch (e) {
        // 409: ya existe un expediente con ese nombre y fecha; se selecciona el existente.
        if (e.response?.status === 409) { avisar(e.response.data.message, 'info'); alta.value = false; elegir(e.response.data.patient); }
        else avisar(e.response?.data?.message || Object.values(e.response?.data?.errors || {}).flat()[0] || 'Error al dar de alta', 'error');
    } finally { guardando.value = false; }
}
</script>

<template>
    <div v-if="modelValue" class="patcard">
        <div class="avatar">{{ initials(modelValue.name) }}</div>
        <div style="flex: 1; min-width: 0">
            <b style="font-weight: 500">{{ modelValue.name }}</b>
            <div class="muted small">
                {{ modelValue.sigma_id }}<template v-if="modelValue.age !== null">, {{ modelValue.age }} años</template><template v-if="modelValue.phone">, tel. {{ modelValue.phone }}</template><template v-else>, sin teléfono</template>
                <template v-if="modelValue.previas !== undefined">, {{ modelValue.previas ? modelValue.previas + ' citas previas' : 'paciente nuevo' }}</template>
            </div>
        </div>
        <Button label="Cambiar" size="small" severity="secondary" outlined @click="cambiar" />
    </div>
    <div v-else-if="alta" class="card pad stack" style="background: var(--surface-2)">
        <div class="note small">Alta controlada: se generará un identificador SIGMA24 y se validará que el paciente no exista.</div>
        <div class="grid2">
            <div class="field"><label for="np-name">Nombre completo</label><InputText id="np-name" v-model="nuevo.name" /></div>
            <div class="field"><label>Fecha de nacimiento</label><Fecha v-model="nuevo.birth_date" :max="new Date().toISOString().slice(0, 10)" /></div>
            <div class="field"><label for="np-phone">Teléfono</label><InputText id="np-phone" v-model="nuevo.phone" type="tel" /></div>
            <div class="field"><label for="np-mail">Correo</label><InputText id="np-mail" v-model="nuevo.email" type="email" /></div>
        </div>
        <div class="row">
            <Button label="Dar de alta y seleccionar" size="small" :loading="guardando" @click="darAlta" />
            <Button label="Buscar existente" size="small" text @click="alta = false" />
        </div>
    </div>
    <div v-else>
        <IconField>
            <InputIcon class="pi pi-search" />
            <InputText v-model="q" :placeholder="placeholder || 'Busca por nombre, teléfono, CURP o ID SIGMA24'" autocomplete="off" fluid />
        </IconField>
        <div class="pick" style="margin-top: 6px">
            <button v-if="opcional" type="button" @click="elegir(null)"><span class="muted">Sin paciente (evento interno)</span></button>
            <button v-for="p in resultados" :key="p.id" type="button" @click="elegir(p)">
                <b style="font-weight: 500; flex: 1">{{ p.name }}</b><span class="muted small">{{ p.sigma_id }}<template v-if="p.age !== null">, {{ p.age }} años</template><template v-if="p.curp">, {{ p.curp }}</template></span>
            </button>
            <button v-if="permitirAlta" type="button" style="color: var(--primary)" @click="alta = true"><i class="pi pi-plus" /> Dar de alta paciente nuevo</button>
        </div>
    </div>
</template>
