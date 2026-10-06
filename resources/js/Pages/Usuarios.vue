<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermisos } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';

const props = defineProps({ usuarios: Array, modulos: Array, permisos: Object, medicosSinUsuario: Array });
const page = usePage();
const { puede } = usePermisos();
const cfg = computed(() => puede('config'));
const roles = computed(() => page.props.catalogo.roles);
const u = ref(null);
const medicos = computed(() => (u.value?.id ? page.props.opciones.medicos : props.medicosSinUsuario));

const abrir = (x) => { u.value = x ? { id: x.id, name: x.name, email: x.email, role: x.role, doctor_id: x.doctor_id, active: x.active, password: '' } : { name: '', email: '', role: 'Recepción', doctor_id: null, password: '' }; };
async function guardar() {
    const r = u.value.id ? await api.put(route('api.usuarios.update', u.value.id), u.value) : await api.post(route('api.usuarios.store'), u.value);
    avisar(r.message); u.value = null; refrescar();
}
async function activo(x, v) { await api.put(route('api.usuarios.update', x.id), { ...x, active: v }); avisar(v ? 'Usuario activado' : 'Usuario desactivado'); refrescar(); }
async function permiso(rol, mod, v) { await api.post(route('api.permisos.update'), { role: rol, module: mod, value: v }); refrescar(); }
</script>

<template>
    <AppLayout titulo="Usuarios y permisos" subtitulo="Roles y acceso por módulo">
        <div class="split">
            <div class="card">
                <header><h2>Usuarios</h2><Button v-if="cfg" label="Nuevo usuario" icon="pi pi-plus" size="small" severity="secondary" outlined @click="abrir()" /></header>
                <div class="tbl-wrap"><table class="tbl">
                    <thead><tr><th>Nombre</th><th>Rol</th><th>Correo</th><th>Estatus</th><th></th></tr></thead>
                    <tbody><tr v-for="x in usuarios" :key="x.id">
                        <td>{{ x.name }}<div v-if="x.doctor" class="muted small">Agenda: {{ x.doctor.name }}</div></td>
                        <td>{{ x.role }}</td><td class="small">{{ x.email }}</td>
                        <td><label class="chk"><ToggleSwitch :model-value="x.active" :disabled="!cfg || x.id === page.props.auth.user.id" @update:model-value="activo(x, $event)" />{{ x.active ? 'Activo' : 'Inactivo' }}</label></td>
                        <td><Button v-if="cfg" label="Editar" size="small" text @click="abrir(x)" /></td>
                    </tr></tbody>
                </table></div>
            </div>
            <div class="card">
                <header><h2>Acceso por módulo</h2></header>
                <div class="tbl-wrap"><table class="tbl matrix">
                    <thead><tr><th>Módulo</th><th v-for="r in roles" :key="r">{{ r }}</th></tr></thead>
                    <tbody><tr v-for="m in modulos" :key="m.k">
                        <td>{{ m.l }}</td>
                        <td v-for="r in roles" :key="r"><Checkbox :model-value="r === 'Administrador' || (permisos[r] || []).includes(m.k)" binary :disabled="!cfg || r === 'Administrador'" :aria-label="m.l + ': ' + r" @update:model-value="permiso(r, m.k, $event)" /></td>
                    </tr></tbody>
                </table></div>
                <div class="body"><p class="muted small">Los cambios aplican de inmediato. Además del acceso al módulo, cada rol tiene permisos de acción: Recepción no ve el contenido clínico, el Médico solo ve a sus pacientes y el rol Consulta es de solo lectura.</p></div>
            </div>
        </div>

        <Dialog :visible="!!u" modal :header="u?.id ? 'Editar usuario' : 'Nuevo usuario'" :style="{ width: '560px' }" :breakpoints="{ '600px': '96vw' }" @update:visible="u = null">
            <template v-if="u">
                <div class="field"><label>Nombre</label><InputText v-model="u.name" /></div>
                <div class="grid2">
                    <div class="field"><label>Rol</label><Select v-model="u.role" :options="roles" /></div>
                    <div class="field"><label>Correo</label><InputText v-model="u.email" type="email" /></div>
                </div>
                <div v-if="u.role === 'Médico'" class="field"><label>Agenda del médico</label><Select v-model="u.doctor_id" :options="medicos" option-label="name" option-value="id" placeholder="Elige médico" /></div>
                <div class="field"><label>{{ u.id ? 'Nueva contraseña (opcional)' : 'Contraseña (opcional)' }}</label><Password v-model="u.password" toggle-mask fluid :feedback="false" /></div>
                <p v-if="!u.id" class="muted small">Si no defines contraseña, el usuario recibirá un correo para definirla.</p>
            </template>
            <template #footer><Button label="Cancelar" text @click="u = null" /><Button label="Guardar" @click="guardar" /></template>
        </Dialog>
    </AppLayout>
</template>
