<script setup>
import { ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chip from '@/Components/Chip.vue';
import Fecha from '@/Components/Fecha.vue';
import Hora from '@/Components/Hora.vue';
import { ui, usePermisos } from '@/lib/ui';
import { api, avisar, refrescar, filtrar } from '@/lib/api';
import { money, fmtDS, fmtDL, DIAS, DSH, CLASE_ESTADO, hoyISO } from '@/lib/util';

const props = defineProps({ tab: String, formatos: Array, especialidades: Array, medicos: Array, espacios: Array, pacientes: Object, q: String });
const page = usePage();
const { puede } = usePermisos();
const op = computed(() => page.props.opciones);
const tabs = [['esp', 'Especialidades'], ['med', 'Médicos y horarios'], ['rec', 'Consultorios y espacios'], ['pac', 'Pacientes']];
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
const vista = ref(null);
function abrirMed(d) {
    med.value = d ? { id: d.id, name: d.name, specialty_id: d.specialty_id, phone: d.phone, resource_id: d.resource_id, cost: d.cost, duration: d.duration, deposit_mode: d.deposit_mode,
        deposit_value: d.deposit_value, days: [...d.days], start_time: d.start_time, end_time: d.end_time, notes: d.notes, active: d.active }
        : { name: '', specialty_id: op.value.especialidades[0]?.id, phone: '', resource_id: null, cost: 800, duration: 30, deposit_mode: 'monto', deposit_value: 0, days: [1, 3, 5], start_time: '08:00', end_time: '14:00', notes: '', active: true, email: '' };
}
function toggleDia(x, on) { const s = new Set(med.value.days); on ? s.add(x) : s.delete(x); med.value.days = [...s]; }
let t = null;
watch(med, (m) => {
    if (!m) { vista.value = null; return; }
    clearTimeout(t);
    t = setTimeout(async () => { vista.value = await api.post(route('api.medicos.preview'), m, { silencioso: true }).catch(() => null); }, 250);
}, { deep: true });
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
const busqueda = ref(props.q || '');
let tq = null;
watch(busqueda, (v) => { clearTimeout(tq); tq = setTimeout(() => filtrar('catalogos', { tab: 'pac', q: v }), 300); });
const abrirPac = (p) => { pac.value = p ? { id: p.id, sigma_id: p.sigma_id, name: p.name, birth_date: p.birth_date, phone: p.phone, email: p.email } : { name: '', birth_date: null, phone: '', email: '' }; };
async function guardarPac() {
    try {
        const r = pac.value.id ? await api.put(route('api.pacientes.update', pac.value.id), pac.value) : await api.post(route('api.pacientes.store'), pac.value);
        avisar(r.message); pac.value = null; refrescar();
    } catch {}
}
const historial = ref(null);
async function verHistorial(p) { historial.value = await api.get(route('api.pacientes.historial', p.id)); }
const pagina = (e) => router.get(route('catalogos'), { tab: 'pac', q: props.q, page: e.page + 1 }, { preserveScroll: true, preserveState: true });
</script>

<template>
    <AppLayout titulo="Catálogos" subtitulo="Especialidades, médicos, espacios y pacientes">
        <Tabs :value="tab" style="margin-bottom: 16px"><TabList><Tab v-for="[k, l] in tabs" :key="k" :value="k" @click="irTab(k)">{{ l }}</Tab></TabList></Tabs>

        <!-- ESPECIALIDADES -->
        <template v-if="tab === 'esp'">
            <div class="vh"><div class="sp" /><Button v-if="puede('config')" label="Nueva especialidad" icon="pi pi-plus" @click="abrirEsp()" /></div>
            <div class="card tbl-wrap"><table class="tbl">
                <thead><tr><th>Especialidad</th><th>Médicos</th><th class="num">Costo base</th><th>Primera consulta</th><th>Subsecuente</th><th>Estatus</th><th></th></tr></thead>
                <tbody><tr v-for="e in especialidades" :key="e.id">
                    <td><b style="font-weight: 500">{{ e.name }}</b><div class="muted small">{{ e.description }}</div></td>
                    <td>{{ e.doctors_count }}</td><td class="num">{{ money(e.default_cost) }}</td>
                    <td class="small">{{ e.first_form?.name || '—' }}</td><td class="small">{{ e.sub_form?.name || '—' }}</td>
                    <td><span class="chip" :class="e.active ? 'hcom' : 's-aten'">{{ e.active ? 'Activa' : 'Inactiva' }}</span></td>
                    <td><Button v-if="puede('config')" label="Editar" size="small" severity="secondary" outlined @click="abrirEsp(e)" /></td>
                </tr></tbody>
            </table></div>
        </template>

        <!-- MÉDICOS -->
        <template v-else-if="tab === 'med'">
            <div class="vh"><p class="muted">La agenda se genera automáticamente a partir de estos horarios.</p><div class="sp" /><Button v-if="puede('config')" label="Nuevo médico" icon="pi pi-plus" @click="abrirMed()" /></div>
            <div class="card tbl-wrap"><table class="tbl">
                <thead><tr><th>Médico</th><th>Especialidad</th><th>Días</th><th>Horario</th><th>Duración</th><th class="num">Costo</th><th class="num">Anticipo</th><th>Consultorio</th><th>Estatus</th><th></th></tr></thead>
                <tbody><tr v-for="d in medicos" :key="d.id">
                    <td><b style="font-weight: 500">{{ d.name }}</b><div class="muted small">{{ d.phone }}</div></td>
                    <td>{{ d.specialty?.name }}</td>
                    <td>{{ [...d.days].sort((a, b) => (a || 7) - (b || 7)).map((x) => DSH[x]).join(', ') }}</td>
                    <td>{{ d.start_time }} a {{ d.end_time }}</td><td>{{ d.duration }} min</td>
                    <td class="num">{{ money(d.cost) }}</td><td class="num">{{ anticipo(d) }}</td><td>{{ d.room?.name }}</td>
                    <td><span class="chip" :class="d.active ? 'hcom' : 's-aten'">{{ d.active ? 'Activo' : 'Inactivo' }}</span></td>
                    <td><Button v-if="puede('config')" label="Configurar" size="small" severity="secondary" outlined @click="abrirMed(d)" /></td>
                </tr></tbody>
            </table></div>
        </template>

        <!-- ESPACIOS -->
        <template v-else-if="tab === 'rec'">
            <div class="vh"><div class="sp" /><Button v-if="puede('operativo')" label="Nuevo espacio" icon="pi pi-plus" @click="abrirRec()" /></div>
            <div class="card tbl-wrap"><table class="tbl">
                <thead><tr><th>Nombre</th><th>Tipo</th><th>Ubicación</th><th>Capacidad</th><th>Horario</th><th class="num">Costo</th><th>Equipamiento</th><th>Responsable</th><th>Estatus</th><th></th></tr></thead>
                <tbody><tr v-for="r in espacios" :key="r.id">
                    <td><b style="font-weight: 500">{{ r.name }}</b><div v-if="r.rules" class="muted small">{{ r.rules }}</div></td>
                    <td>{{ r.type }}</td><td>{{ r.location }}</td><td>{{ r.capacity }}</td><td>{{ r.opens_at }} a {{ r.closes_at }}</td>
                    <td class="num">{{ r.cost ? money(r.cost) : '—' }}</td><td class="small">{{ r.equipment }}</td><td>{{ r.responsible }}</td>
                    <td><span class="chip" :class="r.active ? 'hcom' : 's-aten'">{{ r.active ? 'Activo' : 'Inactivo' }}</span></td>
                    <td><Button v-if="puede('operativo')" label="Editar" size="small" severity="secondary" outlined @click="abrirRec(r)" /></td>
                </tr></tbody>
            </table></div>
        </template>

        <!-- PACIENTES -->
        <template v-else>
            <div class="vh">
                <IconField style="max-width: 340px; flex: 1"><InputIcon class="pi pi-search" /><InputText v-model="busqueda" placeholder="Buscar por nombre o ID SIGMA24" fluid /></IconField>
                <div class="sp" />
                <Button v-if="puede('cita')" label="Alta de paciente" icon="pi pi-plus" @click="abrirPac()" />
            </div>
            <div class="card tbl-wrap"><table class="tbl">
                <thead><tr><th>ID SIGMA24</th><th>Nombre</th><th>Edad</th><th>Teléfono</th><th>Correo</th><th class="num">Citas</th><th>Última historia clínica</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="p in pacientes.data" :key="p.id">
                        <td><code>{{ p.sigma_id }}</code></td><td>{{ p.name }}</td><td>{{ p.age }}</td><td>{{ p.phone }}</td><td class="small">{{ p.email }}</td>
                        <td class="num">{{ p.citas }}</td>
                        <td><template v-if="p.ultima">{{ fmtDS(p.ultima.date) }} <span class="muted small">{{ p.ultima.specialty }}</span></template><span v-else class="muted">—</span></td>
                        <td style="white-space: nowrap">
                            <Button label="Citas" size="small" text @click="verHistorial(p)" />
                            <template v-if="puede('cita')">
                                <Button label="Agendar" size="small" severity="secondary" outlined @click="ui.abrir('nuevaCita', { patient_id: p.id })" />
                                <Button label="Editar" size="small" text @click="abrirPac(p)" />
                            </template>
                        </td>
                    </tr>
                    <tr v-if="!pacientes.data.length"><td colspan="8"><div class="empty"><b>Sin pacientes</b></div></td></tr>
                </tbody>
            </table></div>
            <Paginator v-if="pacientes.total > pacientes.per_page" :rows="pacientes.per_page" :total-records="pacientes.total" :first="(pacientes.current_page - 1) * pacientes.per_page" @page="pagina" />
            <p class="muted small" style="margin-top: 8px">Los pacientes se vinculan por el identificador único de SIGMA24 para evitar duplicados.</p>
        </template>

        <!-- Diálogos -->
        <Dialog :visible="!!esp" modal :header="esp?.id ? 'Editar especialidad' : 'Nueva especialidad'" :style="{ width: '600px' }" :breakpoints="{ '640px': '96vw' }" @update:visible="esp = null">
            <template v-if="esp">
                <div class="grid2">
                    <div class="field"><label>Nombre</label><InputText v-model="esp.name" /></div>
                    <div class="field"><label>Costo predeterminado</label><InputNumber v-model="esp.default_cost" mode="currency" currency="MXN" locale="es-MX" fluid /></div>
                </div>
                <div class="field"><label>Descripción</label><InputText v-model="esp.description" /></div>
                <div class="grid2">
                    <div class="field"><label>Formato de primera consulta</label><Select v-model="esp.first_form_id" :options="formatos" option-label="name" option-value="id" placeholder="Sin formato" show-clear /></div>
                    <div class="field"><label>Formato de consulta subsecuente</label><Select v-model="esp.sub_form_id" :options="formatos" option-label="name" option-value="id" placeholder="Sin formato" show-clear /></div>
                </div>
                <label class="chk"><Checkbox v-model="esp.active" binary />Activa</label>
            </template>
            <template #footer><Button label="Cancelar" text @click="esp = null" /><Button label="Guardar" @click="guardarEsp" /></template>
        </Dialog>

        <Dialog :visible="!!med" modal :header="med?.id ? 'Configurar a ' + med.name : 'Nuevo médico'" :style="{ width: '860px' }" :breakpoints="{ '900px': '96vw' }" @update:visible="med = null">
            <template v-if="med">
                <div class="grid3">
                    <div class="field"><label>Nombre completo</label><InputText v-model="med.name" /></div>
                    <div class="field"><label>Especialidad</label><Select v-model="med.specialty_id" :options="op.especialidades" option-label="name" option-value="id" /></div>
                    <div class="field"><label>Teléfono</label><InputText v-model="med.phone" /></div>
                </div>
                <div class="field"><label>Días de atención</label>
                    <div class="pills"><label v-for="x in diasOrden" :key="x"><input type="checkbox" :checked="med.days.includes(x)" @change="toggleDia(x, $event.target.checked)" />{{ DIAS[x] }}</label></div>
                </div>
                <div class="grid4">
                    <div class="field"><label>Hora inicial</label><Hora v-model="med.start_time" /></div>
                    <div class="field"><label>Hora final</label><Hora v-model="med.end_time" /></div>
                    <div class="field"><label>Duración de consulta</label><Select v-model="med.duration" :options="[15, 20, 30, 40, 45, 50, 60].map((x) => ({ l: x + ' min', v: x }))" option-label="l" option-value="v" /></div>
                    <div class="field"><label>Consultorio</label><Select v-model="med.resource_id" :options="op.recursos" option-label="name" option-value="id" show-clear /></div>
                </div>
                <div class="grid4">
                    <div class="field"><label>Costo de consulta</label><InputNumber v-model="med.cost" mode="currency" currency="MXN" locale="es-MX" fluid /></div>
                    <div class="field"><label>Anticipo</label><Select v-model="med.deposit_mode" :options="[{ l: 'Monto fijo', v: 'monto' }, { l: 'Porcentaje', v: 'porcentaje' }]" option-label="l" option-value="v" /></div>
                    <div class="field"><label>Valor del anticipo</label><InputNumber v-model="med.deposit_value" :min="0" fluid /></div>
                    <div class="field"><label>&nbsp;</label><label class="chk" style="min-height: 38px"><Checkbox v-model="med.active" binary />Activo</label></div>
                </div>
                <div class="grid2">
                    <div class="field"><label>Observaciones</label><InputText v-model="med.notes" /></div>
                    <div v-if="!med.id" class="field"><label>Correo del usuario del médico (opcional)</label><InputText v-model="med.email" type="email" /></div>
                </div>
                <div>
                    <div class="sec-t">Vista previa de la agenda generada</div>
                    <p v-if="!vista || !vista.valido" class="note warn small">Selecciona al menos un día y un horario válido.</p>
                    <template v-else>
                        <p class="small" style="margin-bottom: 8px">{{ vista.porDia }} consultas por día, {{ vista.porSemana }} por semana. Anticipo calculado: <b>{{ money(vista.anticipo) }}</b>. Próximo día: {{ fmtDL(vista.siguiente) }}.</p>
                        <div class="times"><span v-for="s in vista.espacios" :key="s.time" class="chip plain" :class="CLASE_ESTADO[s.state]" :title="s.state">{{ s.time }} {{ s.state === 'Disponible' ? '' : s.state.toLowerCase() }}</span></div>
                        <p v-if="vista.fuera" class="note warn small" style="margin-top: 8px">{{ vista.fuera }} citas futuras quedan fuera del nuevo horario. Seguirán visibles marcadas como fuera de horario para que las reagendes.</p>
                    </template>
                </div>
            </template>
            <template #footer><Button label="Cancelar" text @click="med = null" /><Button label="Guardar configuración" @click="guardarMed" /></template>
        </Dialog>

        <Dialog :visible="!!rec" modal :header="rec?.id ? 'Editar espacio' : 'Nuevo espacio'" :style="{ width: '760px' }" :breakpoints="{ '800px': '96vw' }" @update:visible="rec = null">
            <template v-if="rec">
                <div class="grid3">
                    <div class="field"><label>Nombre</label><InputText v-model="rec.name" /></div>
                    <div class="field"><label>Tipo</label><Select v-model="rec.type" :options="page.props.catalogo.tiposRecurso" /></div>
                    <div class="field"><label>Ubicación</label><InputText v-model="rec.location" /></div>
                    <div class="field"><label>Capacidad</label><InputNumber v-model="rec.capacity" :min="1" fluid /></div>
                    <div class="field"><label>Abre</label><Hora v-model="rec.opens_at" /></div>
                    <div class="field"><label>Cierra</label><Hora v-model="rec.closes_at" /></div>
                    <div class="field"><label>Costo</label><InputNumber v-model="rec.cost" mode="currency" currency="MXN" locale="es-MX" fluid /></div>
                    <div class="field"><label>Responsable</label><InputText v-model="rec.responsible" /></div>
                    <div class="field"><label>&nbsp;</label><label class="chk" style="min-height: 38px"><Checkbox v-model="rec.active" binary />Activo</label></div>
                </div>
                <div class="field"><label>Equipamiento</label><InputText v-model="rec.equipment" /></div>
                <div class="field"><label>Reglas de uso</label><InputText v-model="rec.rules" /></div>
            </template>
            <template #footer><Button label="Cancelar" text @click="rec = null" /><Button label="Guardar" @click="guardarRec" /></template>
        </Dialog>

        <Dialog :visible="!!pac" modal :header="pac?.id ? 'Editar paciente' : 'Alta de paciente'" :style="{ width: '600px' }" :breakpoints="{ '640px': '96vw' }" @update:visible="pac = null">
            <template v-if="pac">
                <p v-if="pac.id" class="muted small">ID SIGMA24: <code>{{ pac.sigma_id }}</code> (no editable)</p>
                <div class="grid2">
                    <div class="field"><label>Nombre completo</label><InputText v-model="pac.name" /></div>
                    <div class="field"><label>Fecha de nacimiento</label><Fecha v-model="pac.birth_date" :max="hoyISO()" /></div>
                    <div class="field"><label>Teléfono</label><InputText v-model="pac.phone" /></div>
                    <div class="field"><label>Correo</label><InputText v-model="pac.email" type="email" /></div>
                </div>
            </template>
            <template #footer><Button label="Cancelar" text @click="pac = null" /><Button label="Guardar" @click="guardarPac" /></template>
        </Dialog>

        <Dialog :visible="!!historial" modal :header="historial ? `${historial.patient.name} (${historial.patient.sigma_id})` : ''" :style="{ width: '760px' }" :breakpoints="{ '800px': '96vw' }" @update:visible="historial = null">
            <div v-if="historial" class="card tbl-wrap"><table class="tbl">
                <thead><tr><th>Fecha</th><th>Médico</th><th>Motivo</th><th>Estado</th><th>Historia</th></tr></thead>
                <tbody>
                    <tr v-for="a in historial.citas" :key="a.id" class="click" @click="historial = null; ui.abrirCita(a.id)">
                        <td style="white-space: nowrap">{{ fmtDS(a.date) }} {{ a.time }}</td><td>{{ a.doctor }}</td><td>{{ a.reason }}</td><td><Chip :valor="a.status" /></td><td><Chip :valor="a.hc_status" tipo="hc" /></td>
                    </tr>
                    <tr v-if="!historial.citas.length"><td colspan="5"><div class="empty">Sin citas registradas</div></td></tr>
                </tbody>
            </table></div>
        </Dialog>
    </AppLayout>
</template>
