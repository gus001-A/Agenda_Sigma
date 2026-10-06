<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { avisos, avisar } from '@/lib/api';
import { ui, usePermisos } from '@/lib/ui';
import { initials } from '@/lib/util';
import CitaDrawer from '@/Components/Dialogos/CitaDrawer.vue';
import NuevaCita from '@/Components/Dialogos/NuevaCita.vue';
import Pago from '@/Components/Dialogos/Pago.vue';
import Cancelar from '@/Components/Dialogos/Cancelar.vue';
import Reagendar from '@/Components/Dialogos/Reagendar.vue';
import EditarCita from '@/Components/Dialogos/EditarCita.vue';
import Finalizar from '@/Components/Dialogos/Finalizar.vue';
import Reembolso from '@/Components/Dialogos/Reembolso.vue';
import Bloqueo from '@/Components/Dialogos/Bloqueo.vue';
import BloqueoVer from '@/Components/Dialogos/BloqueoVer.vue';
import Historia from '@/Components/Dialogos/Historia.vue';
import Liberado from '@/Components/Dialogos/Liberado.vue';
import NuevaEspera from '@/Components/Dialogos/NuevaEspera.vue';

defineProps({ titulo: String, subtitulo: String });
const page = usePage();
const toast = useToast();
const confirm = useConfirm();
const { puede } = usePermisos();
const user = computed(() => page.props.auth.user);
const cnt = computed(() => page.props.contadores || {});
const actual = computed(() => page.url.split('?')[0].replace(/^\//, ''));
const modulo = computed(() => (page.props.menu || []).find((m) => m.ruta === actual.value));

// Menú lateral agrupado. Solo se muestran los módulos permitidos para el rol.
const GRUPOS = [
    { k: 'g-operacion', l: 'Operación', mods: ['panel', 'agenda', 'recepcion', 'disponibilidad', 'espera', 'espacios', 'anticipos', 'notificaciones'] },
    { k: 'g-clinico', l: 'Clínico', mods: ['historias', 'preconsulta'] },
    { k: 'g-admin', l: 'Administración', mods: ['formatos', 'catalogos', 'reportes', 'usuarios', 'auditoria'] },
];
const grupos = computed(() => {
    const permitidos = Object.fromEntries((page.props.menu || []).map((m) => [m.k, m]));
    return GRUPOS.map((g) => {
        const items = g.mods.filter((k) => permitidos[k]).map((k) => permitidos[k]);
        return { ...g, items };
    }).filter((g) => g.items.length);
});

// Menú de usuario
const abierto = ref(null);
const alternar = (k) => { abierto.value = abierto.value === k ? null : k; };
const cerrarMenus = (e) => { if (!e.target.closest('.side-user')) abierto.value = null; };
const lateral = ref(false); // menú lateral abierto en pantallas chicas

// Sidebar contraído (solo iconos); la preferencia se recuerda en el navegador.
const leerColapsado = () => { try { return localStorage.getItem('sigma24-sidebar') === 'min'; } catch { return false; } };
const colapsado = ref(leerColapsado());
function alternarSidebar() {
    colapsado.value = !colapsado.value;
    try { localStorage.setItem('sigma24-sidebar', colapsado.value ? 'min' : 'max'); } catch {}
}
const tip = (texto) => (colapsado.value ? { value: texto, showDelay: 150 } : null);

// Avisos (toasts) de la app y mensajes flash del servidor.
watch(() => avisos.lista.length, () => {
    avisos.lista.splice(0).forEach((a) => toast.add({ severity: a.tipo === 'error' ? 'error' : a.tipo === 'info' ? 'info' : 'success', summary: a.texto, life: a.accion ? 9000 : 3800, data: a.accion }));
});
watch(() => page.props.flash, (f) => { if (f?.success) avisar(f.success); if (f?.error) avisar(f.error, 'error'); }, { immediate: true, deep: true });

function salir() {
    abierto.value = null;
    confirm.require({
        header: 'Cerrar sesión', message: '¿Seguro que quieres salir? Tendrás que iniciar sesión de nuevo para continuar.',
        acceptLabel: 'Cerrar sesión', rejectLabel: 'Cancelar', acceptProps: { severity: 'danger' }, rejectProps: { severity: 'secondary', outlined: true },
        accept: () => router.post(route('logout')),
    });
}

// La agenda, el panel, recepción y espacios se actualizan solos cada minuto.
let reloj = null;
onMounted(() => {
    document.addEventListener('click', cerrarMenus);
    reloj = setInterval(() => {
        if (['agenda', 'panel', 'recepcion', 'espacios'].includes(actual.value) && !ui.dialogo && !document.hidden) router.reload({ preserveScroll: true, preserveState: true });
    }, 60000);
});
const quitarEscucha = router.on('navigate', () => { abierto.value = null; lateral.value = false; });
onBeforeUnmount(() => { clearInterval(reloj); quitarEscucha(); document.removeEventListener('click', cerrarMenus); });
</script>

<template>
    <Head :title="titulo" />
    <div class="app" :class="{ min: colapsado }">
        <aside class="side" :class="{ open: lateral }">
            <Link :href="route('inicio')" class="brand">
                <div class="mark">S24</div>
                <div class="brand-txt"><b>SIGMA24</b><small>{{ page.props.app.clinica }}</small></div>
            </Link>
            <button type="button" class="side-toggle" :aria-label="colapsado ? 'Expandir menú' : 'Contraer menú'" v-tooltip.right="colapsado ? 'Expandir menú' : 'Contraer menú'" @click="alternarSidebar">
                <i class="pi" :class="colapsado ? 'pi-angle-double-right' : 'pi-angle-double-left'" />
            </button>
            <nav class="nav" aria-label="Módulos">
                <template v-for="g in grupos" :key="g.k">
                    <div class="g"><span>{{ g.l }}</span></div>
                    <Link v-for="m in g.items" :key="m.k" v-tooltip.right="tip(m.l)" :href="route(m.ruta)" :class="{ on: actual === m.ruta }" :style="{ '--c': m.c }">
                        <i :class="m.i" /><span class="txt">{{ m.l }}</span><span v-if="cnt[m.k]" class="cnt">{{ cnt[m.k] }}</span>
                    </Link>
                </template>
            </nav>
            <div class="side-user">
                <div v-if="abierto === 'user'" class="menu-pop">
                    <div class="info">{{ user.email }}</div>
                    <Link v-if="user.modulos.includes('auditoria')" :href="route('auditoria')"><i class="pi pi-shield" />Auditoría</Link>
                    <button type="button" class="salir" @click="salir"><i class="pi pi-sign-out" />Cerrar sesión</button>
                </div>
                <button v-tooltip.right="tip(user.name)" type="button" :aria-expanded="abierto === 'user'" @click="alternar('user')">
                    <div class="avatar">{{ initials(user.name) }}</div>
                    <span class="nombre">{{ user.name }}<small>{{ user.role }}</small></span>
                    <i class="pi pi-ellipsis-v" style="font-size: 12px; color: #94a3b8" />
                </button>
            </div>
        </aside>
        <div v-if="lateral" class="scrim" @click="lateral = false" />

        <div class="main">
            <main class="contenido">
                <div class="page-head">
                    <div class="row" style="flex-wrap: nowrap; min-width: 0; gap: 12px">
                        <Button class="burger" icon="pi pi-bars" text rounded aria-label="Abrir menú" @click="lateral = true" />
                        <div v-if="modulo" class="page-ic" :style="{ '--c': modulo.c }"><i :class="modulo.i" /></div>
                        <div style="min-width: 0">
                            <h1 class="page-title">{{ titulo }}</h1>
                            <p v-if="subtitulo" class="page-desc">{{ subtitulo }}</p>
                        </div>
                    </div>
                    <div class="page-acts">
                        <slot name="acciones" />
                        <Button v-if="puede('cita')" label="Nueva cita" icon="pi pi-plus" @click="ui.abrir('nuevaCita', {})" />
                    </div>
                </div>
                <section class="view"><slot /></section>
            </main>
        </div>
    </div>

    <CitaDrawer />
    <NuevaCita /><Pago /><Cancelar /><Reagendar /><EditarCita /><Finalizar /><Reembolso />
    <Bloqueo /><BloqueoVer /><Historia /><Liberado /><NuevaEspera />
    <ConfirmDialog />
    <Toast position="bottom-right">
        <template #message="{ message }">
            <div class="row" style="flex: 1; flex-wrap: nowrap">
                <span style="flex: 1">{{ message.summary }}</span>
                <Button v-if="message.data" :label="message.data.label" size="small" outlined @click="message.data.fn(); toast.remove(message)" />
            </div>
        </template>
    </Toast>
</template>
