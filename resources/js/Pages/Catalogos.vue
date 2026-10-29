<script setup>
import Modal from '@/Components/Modal.vue';
import SeccionModal from '@/Components/SeccionModal.vue';
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import Fecha from '@/Components/Fecha.vue';
import Hora from '@/Components/Hora.vue';
import TablaDatos from '@/Components/TablaDatos.vue';
import Accion from '@/Components/Accion.vue';
import Traza from '@/Components/Traza.vue';
import { ui, usePermisos } from '@/lib/ui';
import { api, avisar, refrescar, filtrar } from '@/lib/api';
import { money, fmtDS, DIAS, DSH, hoyISO, initials } from '@/lib/util';

const props = defineProps({ tab: String, formatos: Array, especialidades: Array, medicos: Array, espacios: Array, pacientes: Object, filtros: Object });
const page = usePage();
const { puede } = usePermisos();
const op = computed(() => page.props.opciones);
const tabs = [['esp', 'Especialidades', 'pi pi-bookmark'], ['med', 'Médicos y horarios', 'pi pi-user'], ['rec', 'Consultorios y espacios', 'pi pi-building'], ['pac', 'Pacientes', 'pi pi-id-card']];
const irTab = (t) => router.get(route('catalogos'), { tab: t }, { preserveScroll: true });
const diasOrden = [1, 2, 3, 4, 5, 6, 0];

// ---------- especialidad ----------
const esp = ref(null);
const abrirEsp = (e) => { esp.value = e ? { id: e.id, name: e.name, description: e.description, default_cost: e.default_cost, first_form_id: e.first_form_id, sub_form_id: e.sub_form_id, active: e.active } : { name: '', description: '', default_cost: 0, first_form_id: null, sub_form_id: null, active: true }; };
async function guardarEsp() {
    const r = esp.value.id ? await api.put(route('api.especialidades.update', esp.value.id), esp.value) : await api.post(route('api.especialidades.store'), esp.value);
    avisar(r.message); esp.value = null; refrescar();
}

// ---------- médico ----------
const med = ref(null);
function abrirMed(d) {
    med.value = d ? { id: d.id, name: d.name, specialty_id: d.specialty_id, phone: d.phone, resource_id: d.resource_id, cost: d.cost, duration: d.duration, deposit_mode: d.deposit_mode,
        deposit_value: d.deposit_value, days: [...d.days], start_time: d.start_time, end_time: d.end_time, notes: d.notes, active: d.active }
        : { name: '', specialty_id: op.value.especialidades[0]?.id, phone: '', resource_id: null, cost: 800, duration: 30, deposit_mode: 'monto', deposit_value: 0, days: [1, 3, 5], start_time: '08:00', end_time: '14:00', notes: '', active: true, email: '' };
}
function toggleDia(x, on) { const s = new Set(med.value.days); on ? s.add(x) : s.delete(x); med.value.days = [...s]; }
async function guardarMed() {
    const r = med.value.id ? await api.put(route('api.medicos.update', med.value.id), med.value) : await api.post(route('api.medicos.store'), med.value);
    avisar(r.message); med.value = null; refrescar();
}
const anticipo = (d) => (d.deposit_amount ? money(d.deposit_amount) + (d.deposit_mode === 'porcentaje' ? ` (${d.deposit_value}%)` : '') : '—');

// ---------- espacio ----------
const rec = ref(null);
const abrirRec = (r) => { rec.value = r ? { ...r } : { name: '', type: 'Consultorio', location: '', capacity: 1, opens_at: '07:00', closes_at: '21:00', cost: 0, equipment: '', responsible: '', rules: '', active: true }; };
async function guardarRec() {
    const r = rec.value.id ? await api.put(route('api.espacios.update', rec.value.id), rec.value) : await api.post(route('api.espacios.store'), rec.value);
    avisar(r.message); rec.value = null; refrescar();
}

// ---------- paciente ----------
const pac = ref(null);
const abrirPac = (p) => { pac.value = p ? { id: p.id, name: p.name, birth_date: p.birth_date, phone: (p.phone || '').replace(/\D/g, ''), email: p.email } : { name: '', birth_date: null, phone: '', email: '' }; };
async function guardarPac() {
    try {
        const r = pac.value.id ? await api.put(route('api.pacientes.update', pac.value.id), pac.value) : await api.post(route('api.pacientes.store'), pac.value);
        avisar(r.message); pac.value = null; refrescar();
    } catch {}
}
const historial = ref(null);
async function verHistorial(p) { historial.value = await api.get(route('api.pacientes.historial', p.id)); }
const cargando = ref(false);
function cambioPac({ pagina, filas, filtros }) {
    cargando.value = true;
    router.get(route('catalogos'), { tab: 'pac', f: filtros, page: pagina, per_page: filas }, { preserveState: true, preserveScroll: true, replace: true, onFinish: () => (cargando.value = false) });
}

// ---------- columnas de las tablas ----------
const leer = (x, k) => k.split('.').reduce((o, y) => o?.[y], x);
const unicos = (l, k) => [...new Set((l || []).map((x) => leer(x, k)).filter(Boolean))].sort();
const ESTATUS = ['Activo', 'Inactivo'];
const colEsp = computed(() => [
    { campo: 'name', titulo: 'Especialidad', filtro: 'texto', ancho: '260px' },
    { campo: 'doctors_count', titulo: 'Médicos', alinear: 'right', ancho: '90px' },
    { campo: 'default_cost', titulo: 'Costo base', alinear: 'right' },
    { campo: 'first_form.name', titulo: 'Primera consulta', filtro: 'lista', opciones: unicos(props.especialidades, 'first_form.name') },
    { campo: 'sub_form.name', titulo: 'Subsecuente', filtro: 'lista', opciones: unicos(props.especialidades, 'sub_form.name') },
    { campo: 'estatus', titulo: 'Estatus', filtro: 'lista', opciones: ['Activa', 'Inactiva'], ancho: '120px' },
    { campo: 'created_by', titulo: 'Registró', filtro: 'texto', ancho: '150px' },
    { campo: 'acc', titulo: '', ordenar: false, ancho: '60px' },
]);
const filasEsp = computed(() => (props.especialidades || []).map((e) => ({ ...e, estatus: e.active ? 'Activa' : 'Inactiva' })));
const filasMed = computed(() => (props.medicos || []).map((d) => ({ ...d, estatus: d.active ? 'Activo' : 'Inactivo', horario: d.start_time + ' a ' + d.end_time, especialidad: d.specialty?.name, consultorio: d.room?.name || '—', dur: d.duration + ' min' })));
const colMed = computed(() => [
    { campo: 'name', titulo: 'Médico', filtro: 'texto', ancho: '240px' },
    { campo: 'especialidad', titulo: 'Especialidad', filtro: 'lista', opciones: unicos(filasMed.value, 'especialidad') },
    { campo: 'dias', titulo: 'Días', ordenar: false, ancho: '230px' },
    { campo: 'horario', titulo: 'Horario', filtro: 'texto', ancho: '140px' },
    { campo: 'dur', titulo: 'Duración', filtro: 'lista', opciones: unicos(filasMed.value, 'dur') },
    { campo: 'cost', titulo: 'Costo', alinear: 'right' },
    { campo: 'anticipo', titulo: 'Anticipo', alinear: 'right', ordenar: false },
    { campo: 'consultorio', titulo: 'Consultorio', filtro: 'lista', opciones: unicos(filasMed.value, 'consultorio') },
    { campo: 'estatus', titulo: 'Estatus', filtro: 'lista', opciones: ESTATUS },
    { campo: 'created_by', titulo: 'Registró', filtro: 'texto', ancho: '150px' },
    { campo: 'acc', titulo: '', ordenar: false, ancho: '140px' },
]);
const filasRec = computed(() => (props.espacios || []).map((r) => ({ ...r, estatus: r.active ? 'Activo' : 'Inactivo', horario: r.opens_at + ' a ' + r.closes_at })));
const colRec = computed(() => [
    { campo: 'name', titulo: 'Nombre', filtro: 'texto', ancho: '230px' },
    { campo: 'type', titulo: 'Tipo', filtro: 'lista', opciones: unicos(filasRec.value, 'type') },
    { campo: 'location', titulo: 'Ubicación', filtro: 'lista', opciones: unicos(filasRec.value, 'location') },
    { campo: 'capacity', titulo: 'Capacidad', alinear: 'right', ancho: '100px' },
    { campo: 'horario', titulo: 'Horario', filtro: 'texto' },
    { campo: 'cost', titulo: 'Costo', alinear: 'right' },
    { campo: 'equipment', titulo: 'Equipamiento', filtro: 'texto' },
    { campo: 'responsible', titulo: 'Responsable', filtro: 'lista', opciones: unicos(filasRec.value, 'responsible') },
    { campo: 'estatus', titulo: 'Estatus', filtro: 'lista', opciones: ESTATUS },
    { campo: 'created_by', titulo: 'Registró', filtro: 'texto', ancho: '150px' },
    { campo: 'acc', titulo: '', ordenar: false, ancho: '60px' },
]);
const colPac = [
    { campo: 'name', titulo: 'Nombre', filtro: 'texto', ancho: '230px' },
    { campo: 'curp', titulo: 'CURP', filtro: 'texto', ancho: '200px' },
    { campo: 'age', titulo: 'Edad', alinear: 'right', ancho: '80px' },
    { campo: 'phone', titulo: 'Teléfono', filtro: 'texto', ancho: '150px' },
    { campo: 'email', titulo: 'Correo', filtro: 'texto' },
    { campo: 'citas', titulo: 'Citas', alinear: 'right', ancho: '80px' },
    { campo: 'ultima', titulo: 'Última historia clínica', ancho: '190px' },
    { campo: 'created_by', titulo: 'Registró', ordenar: false, ancho: '150px' },
    { campo: 'acc', titulo: 'Acciones', ancho: '130px' },
];
const ICONO_RECURSO = { Consultorio: 'pi pi-desktop', 'Quirófano': 'pi pi-heart', 'Sala de procedimientos': 'pi pi-briefcase', 'Sala de juntas': 'pi pi-users', 'Habitación': 'pi pi-home', 'Sala de rehabilitación': 'pi pi-bolt' };
const COLOR_RECURSO = { Consultorio: '#2b5480', 'Quirófano': '#d64545', 'Sala de procedimientos': '#6b4bc9', 'Sala de juntas': '#0e7490', 'Habitación': '#16a34a', 'Sala de rehabilitación': '#e08a1e' };
</script>

<template>
    <AppLayout titulo="Catálogos" subtitulo="Especialidades, médicos y horarios, espacios y pacientes" sin-nueva-cita>
        <template #acciones>
            <Button v-if="tab === 'esp' && puede('config')" label="Nueva especialidad" icon="pi pi-plus" severity="secondary" outlined @click="abrirEsp()" />
            <Button v-if="tab === 'med' && puede('config')" label="Nuevo médico" icon="pi pi-user-plus" severity="secondary" outlined @click="abrirMed()" />
            <Button v-if="tab === 'rec' && puede('operativo')" label="Nuevo espacio" icon="pi pi-plus" severity="secondary" outlined @click="abrirRec()" />
            <Button v-if="tab === 'pac' && puede('cita')" label="Alta de paciente" icon="pi pi-user-plus" severity="secondary" outlined @click="abrirPac()" />
        </template>
        <Tabs :value="tab"><TabList><Tab v-for="[k, l, i] in tabs" :key="k" :value="k" @click="irTab(k)"><i :class="i" style="margin-right: 7px" />{{ l }}</Tab></TabList></Tabs>

        <!-- ESPECIALIDADES -->
        <TablaDatos v-if="tab === 'esp'" :filas="filasEsp" :columnas="colEsp">
            <template #c-name="{ fila }"><span class="row" style="gap: 10px; flex-wrap: nowrap"><span class="hic" style="--c: #0891b2"><i class="pi pi-bookmark" /></span><span><b style="font-weight: 600">{{ fila.name }}</b><div class="muted small">{{ fila.description }}</div></span></span></template>
            <template #c-default_cost="{ fila }">{{ money(fila.default_cost) }}</template>
            <template #c-estatus="{ fila }"><span class="chip" :class="fila.active ? 'hcom' : 's-aten'">{{ fila.estatus }}</span></template>
            <template #c-created_by="{ fila }"><Traza :r="fila" /></template>
            <template #c-acc="{ fila }"><Accion v-if="puede('config')" icono="pi pi-pencil" tip="Editar" color="azul" @click="abrirEsp(fila)" /></template>
        </TablaDatos>

        <!-- MÉDICOS -->
        <TablaDatos v-else-if="tab === 'med'" :filas="filasMed" :columnas="colMed">
            <template #c-name="{ fila }"><span class="row" style="gap: 10px; flex-wrap: nowrap"><span class="avatar" style="width: 30px; height: 30px; font-size: 11px">{{ initials(fila.name) }}</span><span><b style="font-weight: 600">{{ fila.name }}</b><div v-if="fila.phone" class="muted small"><i class="pi pi-phone" style="font-size: 10px" /> {{ fila.phone }}</div></span></span></template>
            <template #c-dias="{ fila }"><div class="row" style="gap: 3px"><span v-for="x in diasOrden" :key="x" class="dia" :class="{ on: fila.days.includes(x) }">{{ DSH[x][0] }}</span></div></template>
            <template #c-cost="{ fila }">{{ money(fila.cost) }}</template>
            <template #c-anticipo="{ fila }"><span :style="{ color: fila.deposit_amount ? '#a86717' : '' }">{{ anticipo(fila) }}</span></template>
            <template #c-estatus="{ fila }"><span class="chip" :class="fila.active ? 'hcom' : 's-aten'">{{ fila.estatus }}</span></template>
            <template #c-created_by="{ fila }"><Traza :r="fila" /></template>
            <template #c-acc="{ fila }"><Accion v-if="puede('config')" icono="pi pi-sliders-h" texto="Horario" color="verde" @click="abrirMed(fila)" /></template>
        </TablaDatos>

        <!-- ESPACIOS -->
        <TablaDatos v-else-if="tab === 'rec'" :filas="filasRec" :columnas="colRec">
            <template #c-name="{ fila }"><span class="row" style="gap: 10px; flex-wrap: nowrap"><span class="hic" :style="{ '--c': COLOR_RECURSO[fila.type] || '#64748b' }"><i :class="ICONO_RECURSO[fila.type] || 'pi pi-box'" /></span><span><b style="font-weight: 600">{{ fila.name }}</b><div v-if="fila.rules" class="muted small">{{ fila.rules }}</div></span></span></template>
            <template #c-cost="{ fila }">{{ fila.cost ? money(fila.cost) : '—' }}</template>
            <template #c-equipment="{ fila }"><span class="small">{{ fila.equipment }}</span></template>
            <template #c-estatus="{ fila }"><span class="chip" :class="fila.active ? 'hcom' : 's-aten'">{{ fila.estatus }}</span></template>
            <template #c-created_by="{ fila }"><Traza :r="fila" /></template>
            <template #c-acc="{ fila }"><Accion v-if="puede('operativo')" icono="pi pi-pencil" tip="Editar" color="azul" @click="abrirRec(fila)" /></template>
        </TablaDatos>

        <!-- PACIENTES (filtrado en el servidor) -->
        <TablaDatos v-else :filas="pacientes.data" :columnas="colPac" lazy :total="pacientes.total" :primero="(pacientes.current_page - 1) * pacientes.per_page"
            :por-pagina="pacientes.per_page" :filtros-iniciales="filtros || {}" :cargando="cargando" @cambio="cambioPac">
            <template #c-name="{ fila }"><b style="font-weight: 600">{{ fila.name }}</b></template>
            <template #c-email="{ fila }"><span class="small">{{ fila.email }}</span></template>
            <template #c-citas="{ fila }"><span class="chip plain s-agen">{{ fila.citas }}</span></template>
            <template #c-created_by="{ fila }"><Traza :r="fila" /></template>
            <template #c-ultima="{ fila }"><template v-if="fila.ultima">{{ fmtDS(fila.ultima.date) }} <span class="muted small">{{ fila.ultima.specialty }}</span></template><span v-else class="muted">—</span></template>
            <template #c-acc="{ fila }">
                <div class="accs">
                    <Accion icono="pi pi-history" tip="Ver citas" color="cian" @click="verHistorial(fila)" />
                    <template v-if="puede('cita')">
                        <Accion icono="pi pi-calendar-plus" tip="Agendar" color="verde" @click="ui.abrir('nuevaCita', { patient_id: fila.id })" />
                        <Accion icono="pi pi-pencil" tip="Editar" color="morado" @click="abrirPac(fila)" />
                    </template>
                </div>
            </template>
        </TablaDatos>

        <!-- Diálogos -->
        <Modal icono="pi pi-bookmark" color="#0891b2" subtitulo="Costo base y formatos clínicos asociados" :visible="!!esp" :header="esp?.id ? 'Editar especialidad' : 'Nueva especialidad'" ancho="680px" @update:visible="esp = null">
            <template v-if="esp">
                <SeccionModal titulo="Datos generales" icono="pi pi-bookmark" color="#0891b2">
                    <template #extra><label class="chk"><ToggleSwitch v-model="esp.active" />Activa</label></template>
                    <div class="grid2">
                        <div class="field"><label>Nombre</label><div class="campo-ic"><i class="pi pi-tag" /><InputText v-model="esp.name" fluid /></div></div>
                        <div class="field"><label>Costo predeterminado</label><InputNumber v-model="esp.default_cost" mode="currency" currency="MXN" locale="es-MX" fluid /></div>
                    </div>
                    <div class="field"><label>Descripción</label><div class="campo-ic"><i class="pi pi-align-left" /><InputText v-model="esp.description" fluid /></div></div>
                </SeccionModal>
                <SeccionModal titulo="Formatos clínicos" icono="pi pi-file-edit" color="#7c3aed" ayuda="Se asignan automáticamente al agendar">
                    <div class="grid2">
                        <div class="field"><label>Primera consulta</label><Select v-model="esp.first_form_id" :options="formatos" option-label="name" option-value="id" placeholder="Sin formato" show-clear fluid /></div>
                        <div class="field"><label>Consulta subsecuente</label><Select v-model="esp.sub_form_id" :options="formatos" option-label="name" option-value="id" placeholder="Sin formato" show-clear fluid /></div>
                    </div>
                </SeccionModal>
            </template>
            <template #footer><Button label="Cancelar" text @click="esp = null" /><Button label="Guardar especialidad" icon="pi pi-check" @click="guardarEsp" /></template>
        </Modal>

        <Modal icono="pi pi-user" color="#1f9e86" subtitulo="La agenda se genera automáticamente a partir de este horario" :visible="!!med" :header="med?.id ? 'Configurar a ' + med.name : 'Nuevo médico'" ancho="1000px" @update:visible="med = null">
            <template v-if="med">
                <div class="nc-grid">
                    <SeccionModal paso="1" titulo="Datos del médico" icono="pi pi-id-card" color="#2b5480">
                        <template #extra><label class="chk"><ToggleSwitch v-model="med.active" />Activo</label></template>
                        <div class="field"><label>Nombre completo</label><div class="campo-ic"><i class="pi pi-user" /><InputText v-model="med.name" fluid /></div></div>
                        <div class="grid2">
                            <div class="field"><label>Especialidad</label><Select v-model="med.specialty_id" :options="op.especialidades" option-label="name" option-value="id" fluid /></div>
                            <div class="field"><label>Teléfono</label><div class="campo-ic"><i class="pi pi-phone" /><InputText v-model="med.phone" fluid /></div></div>
                        </div>
                        <div class="grid2">
                            <div class="field"><label>Consultorio</label><Select v-model="med.resource_id" :options="op.recursos" option-label="name" option-value="id" show-clear fluid /></div>
                            <div v-if="!med.id" class="field"><label>Correo del usuario (opcional)</label><div class="campo-ic"><i class="pi pi-envelope" /><InputText v-model="med.email" type="email" fluid /></div></div>
                        </div>
                    </SeccionModal>
                    <SeccionModal paso="2" titulo="Horario de atención" icono="pi pi-calendar-clock" color="#1f9e86">
                        <div class="field"><label>Días de atención</label>
                            <div class="pills"><label v-for="x in diasOrden" :key="x"><input type="checkbox" :checked="med.days.includes(x)" @change="toggleDia(x, $event.target.checked)" />{{ DIAS[x] }}</label></div>
                        </div>
                        <div class="grid3">
                            <div class="field"><label>Hora inicial</label><Hora v-model="med.start_time" /></div>
                            <div class="field"><label>Hora final</label><Hora v-model="med.end_time" /></div>
                            <div class="field"><label>Duración</label><Select v-model="med.duration" :options="[15, 20, 30, 40, 45, 50, 60].map((x) => ({ l: x + ' min', v: x }))" option-label="l" option-value="v" fluid /></div>
                        </div>
                    </SeccionModal>
                    <SeccionModal paso="3" titulo="Costos y anticipo" class="nc-ancho" icono="pi pi-wallet" color="#e08a1e">
                        <div class="grid3">
                            <div class="field"><label>Costo de consulta</label><InputNumber v-model="med.cost" mode="currency" currency="MXN" locale="es-MX" fluid /></div>
                            <div class="field"><label>Tipo de anticipo</label><Select v-model="med.deposit_mode" :options="[{ l: 'Monto fijo', v: 'monto' }, { l: 'Porcentaje', v: 'porcentaje' }]" option-label="l" option-value="v" fluid /></div>
                            <div class="field"><label>{{ med.deposit_mode === 'porcentaje' ? 'Porcentaje' : 'Monto' }}</label><InputNumber v-model="med.deposit_value" :min="0" :suffix="med.deposit_mode === 'porcentaje' ? ' %' : ''" :prefix="med.deposit_mode === 'porcentaje' ? '' : '$ '" fluid /></div>
                        </div>
                        <div class="field"><label>Observaciones</label><div class="campo-ic"><i class="pi pi-pencil" /><InputText v-model="med.notes" placeholder="Opcional" fluid /></div></div>
                    </SeccionModal>
                </div>
            </template>
            <template #footer><Button label="Cancelar" text @click="med = null" /><Button label="Guardar configuración" icon="pi pi-check" @click="guardarMed" /></template>
        </Modal>

        <Modal icono="pi pi-building" color="#6b4bc9" subtitulo="Consultorios, quirófanos, salas y habitaciones" :visible="!!rec" :header="rec?.id ? 'Editar espacio' : 'Nuevo espacio'" ancho="820px" @update:visible="rec = null">
            <template v-if="rec">
                <SeccionModal titulo="Identificación" icono="pi pi-building" color="#6b4bc9">
                    <template #extra><label class="chk"><ToggleSwitch v-model="rec.active" />Activo</label></template>
                    <div class="grid3">
                        <div class="field"><label>Nombre</label><div class="campo-ic"><i class="pi pi-tag" /><InputText v-model="rec.name" fluid /></div></div>
                        <div class="field"><label>Tipo</label><Select v-model="rec.type" :options="page.props.catalogo.tiposRecurso" fluid /></div>
                        <div class="field"><label>Ubicación</label><div class="campo-ic"><i class="pi pi-map-marker" /><InputText v-model="rec.location" fluid /></div></div>
                    </div>
                </SeccionModal>
                <SeccionModal titulo="Operación" icono="pi pi-clock" color="#1f9e86">
                    <div class="grid4">
                        <div class="field"><label>Abre</label><Hora v-model="rec.opens_at" /></div>
                        <div class="field"><label>Cierra</label><Hora v-model="rec.closes_at" /></div>
                        <div class="field"><label>Capacidad</label><InputNumber v-model="rec.capacity" :min="1" suffix=" pers." fluid /></div>
                        <div class="field"><label>Costo</label><InputNumber v-model="rec.cost" mode="currency" currency="MXN" locale="es-MX" fluid /></div>
                    </div>
                    <div class="grid2">
                        <div class="field"><label>Responsable</label><div class="campo-ic"><i class="pi pi-user" /><InputText v-model="rec.responsible" fluid /></div></div>
                        <div class="field"><label>Equipamiento</label><div class="campo-ic"><i class="pi pi-wrench" /><InputText v-model="rec.equipment" fluid /></div></div>
                    </div>
                    <div class="field"><label>Reglas de uso</label><div class="campo-ic"><i class="pi pi-list" /><InputText v-model="rec.rules" fluid /></div></div>
                </SeccionModal>
            </template>
            <template #footer><Button label="Cancelar" text @click="rec = null" /><Button label="Guardar espacio" icon="pi pi-check" @click="guardarRec" /></template>
        </Modal>

        <Modal icono="pi pi-id-card" color="#2b5480" subtitulo="Se valida que el paciente no esté registrado" :visible="!!pac" :header="pac?.id ? 'Editar paciente' : 'Alta de paciente'" ancho="640px" @update:visible="pac = null">
            <template v-if="pac">
                <SeccionModal titulo="Datos personales" icono="pi pi-user" color="#2b5480">
                    <div class="grid2">
                        <div class="field"><label>Nombre completo</label><div class="campo-ic"><i class="pi pi-user" /><InputText v-model="pac.name" fluid /></div></div>
                        <div class="field"><label>Fecha de nacimiento</label><Fecha v-model="pac.birth_date" :max="hoyISO()" /></div>
                    </div>
                </SeccionModal>
                <SeccionModal titulo="Contacto" icono="pi pi-phone" color="#16a34a" ayuda="Se usa para WhatsApp, SMS y correo">
                    <div class="grid2">
                        <div class="field"><label>Teléfono</label><div class="campo-ic"><i class="pi pi-phone" /><InputText v-model="pac.phone" v-digitos="10" type="tel" placeholder="10 dígitos" fluid /></div></div>
                        <div class="field"><label>Correo</label><div class="campo-ic"><i class="pi pi-envelope" /><InputText v-model="pac.email" type="email" fluid /></div></div>
                    </div>
                </SeccionModal>
            </template>
            <template #footer><Button label="Cancelar" text @click="pac = null" /><Button label="Guardar paciente" icon="pi pi-check" @click="guardarPac" /></template>
        </Modal>

        <Modal icono="pi pi-history" color="#0891b2" subtitulo="Historial de citas del paciente" :visible="!!historial" :header="historial ? historial.patient.name : ''" ancho="820px" @update:visible="historial = null">
            <SeccionModal v-if="historial" titulo="Citas" icono="pi pi-calendar" color="#0891b2" :ayuda="historial.citas.length + ' registradas · clic para ver el detalle'">
                <div class="tbl-wrap"><table class="tbl">
                    <thead><tr><th>Fecha</th><th>Médico</th><th>Motivo</th><th>Estado</th><th>Historia</th></tr></thead>
                    <tbody>
                        <tr v-for="a in historial.citas" :key="a.id" class="click" @click="historial = null; ui.abrirCita(a.id)">
                            <td style="white-space: nowrap">{{ fmtDS(a.date) }} {{ a.time }}</td><td>{{ a.doctor }}</td><td>{{ a.reason }}</td><td><Chip :valor="a.status" /></td><td><Chip :valor="a.hc_status" tipo="hc" /></td>
                        </tr>
                        <tr v-if="!historial.citas.length"><td colspan="5"><div class="empty">Sin citas registradas</div></td></tr>
                    </tbody>
                </table></div>
            </SeccionModal>
        </Modal>
    </AppLayout>
</template>
