<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Segmento from '@/Components/Segmento.vue';
import TablaDatos from '@/Components/TablaDatos.vue';
import { usePermisos } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';
import { initials } from '@/lib/util';

const props = defineProps({ usuarios: Array, modulos: Array, permisos: Object, medicosSinUsuario: Array });
const page = usePage();
const { puede } = usePermisos();
const cfg = computed(() => puede('config'));
const roles = computed(() => page.props.catalogo.roles);
const vista = ref('usuarios');
const u = ref(null);
const medicos = computed(() => (u.value?.id ? page.props.opciones.medicos : props.medicosSinUsuario));
const COLOR_ROL = { Administrador: '#173a5f', Coordinador: '#6b4bc9', 'Recepción': '#1f9e86', 'Médico': '#d64545', Consulta: '#64748b' };
const ICONO_ROL = { Administrador: 'pi pi-star', Coordinador: 'pi pi-sitemap', 'Recepción': 'pi pi-desktop', 'Médico': 'pi pi-heart', Consulta: 'pi pi-eye' };

const filas = computed(() => props.usuarios.map((x) => ({ ...x, estatus: x.active ? 'Activo' : 'Inactivo', agenda: x.doctor?.name || '' })));
const columnas = computed(() => [
    { campo: 'name', titulo: 'Nombre', filtro: 'texto', ancho: '260px' },
    { campo: 'role', titulo: 'Rol', filtro: 'lista', opciones: roles.value, ancho: '170px' },
    { campo: 'email', titulo: 'Correo', filtro: 'texto' },
    { campo: 'estatus', titulo: 'Estatus', filtro: 'lista', opciones: ['Activo', 'Inactivo'], ancho: '150px' },
    { campo: 'acc', titulo: '', ordenar: false, ancho: '70px' },
]);

const abrir = (x) => { u.value = x ? { id: x.id, name: x.name, email: x.email, role: x.role, doctor_id: x.doctor_id, active: x.active, password: '' } : { name: '', email: '', role: 'Recepción', doctor_id: null, password: '' }; };
async function guardar() {
    const r = u.value.id ? await api.put(route('api.usuarios.update', u.value.id), u.value) : await api.post(route('api.usuarios.store'), u.value);
    avisar(r.message); u.value = null; refrescar();
}
async function activo(x, v) { await api.put(route('api.usuarios.update', x.id), { name: x.name, email: x.email, role: x.role, doctor_id: x.doctor_id, active: v }); avisar(v ? 'Usuario activado' : 'Usuario desactivado'); refrescar(); }
async function permiso(rol, mod, v) { await api.post(route('api.permisos.update'), { role: rol, module: mod, value: v }); refrescar(); }
</script>

<template>
    <AppLayout titulo="Usuarios y permisos" subtitulo="Cuentas del personal, roles y acceso por módulo">
        <template #acciones><Button v-if="cfg" label="Nuevo usuario" icon="pi pi-user-plus" severity="secondary" outlined @click="abrir()" /></template>
        <div class="vh">
            <Segmento v-model="vista" :opciones="[{ l: 'Usuarios', v: 'usuarios', i: 'pi pi-users', n: usuarios.length }, { l: 'Acceso por módulo', v: 'permisos', i: 'pi pi-lock' }]" />
        </div>

        <TablaDatos v-if="vista === 'usuarios'" :filas="filas" :columnas="columnas">
            <template #c-name="{ fila }">
                <span class="row" style="gap: 10px; flex-wrap: nowrap">
                    <span class="avatar" style="width: 30px; height: 30px; font-size: 11px">{{ initials(fila.name) }}</span>
                    <span><b style="font-weight: 600">{{ fila.name }}</b><div v-if="fila.agenda" class="muted small">Agenda: {{ fila.agenda }}</div></span>
                </span>
            </template>
            <template #c-role="{ fila }"><span class="chip plain" :style="{ '--sb': COLOR_ROL[fila.role] + '1a', '--sf': COLOR_ROL[fila.role] }"><i :class="ICONO_ROL[fila.role]" />{{ fila.role }}</span></template>
            <template #c-email="{ fila }"><span class="small">{{ fila.email }}</span></template>
            <template #c-estatus="{ fila }">
                <label class="chk" @click.stop><ToggleSwitch :model-value="fila.active" :disabled="!cfg || fila.id === page.props.auth.user.id" @update:model-value="activo(fila, $event)" />{{ fila.estatus }}</label>
            </template>
            <template #c-acc="{ fila }"><Button v-if="cfg" v-tooltip.top="'Editar'" icon="pi pi-pencil" size="small" severity="info" text rounded @click="abrir(fila)" /></template>
        </TablaDatos>

        <div v-else class="card" style="flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; overflow: hidden">
            <div class="tbl-wrap scroll-y" style="flex: 1">
                <table class="tbl matrix">
                    <thead style="position: sticky; top: 0; z-index: 2"><tr><th>Módulo</th><th v-for="r in roles" :key="r"><span class="chip plain" :style="{ '--sb': COLOR_ROL[r] + '1a', '--sf': COLOR_ROL[r] }"><i :class="ICONO_ROL[r]" />{{ r }}</span></th></tr></thead>
                    <tbody><tr v-for="m in modulos" :key="m.k">
                        <td><span class="row" style="gap: 10px; flex-wrap: nowrap"><span class="hic" :style="{ '--c': m.c }"><i :class="m.i" /></span><b style="font-weight: 600">{{ m.l }}</b></span></td>
                        <td v-for="r in roles" :key="r"><Checkbox :model-value="r === 'Administrador' || (permisos[r] || []).includes(m.k)" binary :disabled="!cfg || r === 'Administrador'" :aria-label="m.l + ': ' + r" @update:model-value="permiso(r, m.k, $event)" /></td>
                    </tr></tbody>
                </table>
            </div>
            <div class="body" style="border-top: 1px solid var(--line-2)"><p class="muted small"><i class="pi pi-info-circle" /> Los cambios aplican de inmediato. Además del acceso al módulo, cada rol tiene permisos de acción: Recepción no ve el contenido clínico, el Médico solo ve a sus pacientes y el rol Consulta es de solo lectura.</p></div>
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
            <template #footer><Button label="Cancelar" text @click="u = null" /><Button label="Guardar" icon="pi pi-check" @click="guardar" /></template>
        </Dialog>
    </AppLayout>
</template>
