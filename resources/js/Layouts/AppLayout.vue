<script setup>
import { computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import { useToast } from 'primevue/usetoast';
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

const props = defineProps({ titulo: String, subtitulo: String });
const page = usePage();
const toast = useToast();
const { puede } = usePermisos();
const user = computed(() => page.props.auth.user);
const menu = computed(() => page.props.menu || []);
const cnt = computed(() => page.props.contadores || {});
const actual = computed(() => page.url.split('?')[0].replace(/^\//, ''));
const grupos = computed(() => {
    const g = [];
    menu.value.forEach((m) => { let x = g.find((y) => y.n === m.g); if (!x) g.push((x = { n: m.g, items: [] })); x.items.push(m); });
    return g;
});

// Avisos (toasts) de la app y mensajes flash del servidor.
watch(() => avisos.lista.length, () => {
    avisos.lista.splice(0).forEach((a) => toast.add({ severity: a.tipo === 'error' ? 'error' : a.tipo === 'info' ? 'info' : 'success', summary: a.texto, life: a.accion ? 9000 : 3800, data: a.accion }));
});
watch(() => page.props.flash, (f) => { if (f?.success) avisar(f.success); if (f?.error) avisar(f.error, 'error'); }, { immediate: true, deep: true });

function tema() {
    const r = document.documentElement;
    r.classList.toggle('app-dark');
    try { localStorage.setItem('sigma24-tema', r.classList.contains('app-dark') ? 'oscuro' : 'claro'); } catch (e) {}
}
function salir() { router.post(route('logout')); }

// La agenda, el panel y recepción se actualizan solos cada minuto.
let reloj = null;
onMounted(() => {
    reloj = setInterval(() => {
        if (['agenda', 'panel', 'recepcion', 'espacios'].includes(actual.value) && !ui.dialogo && !document.hidden) router.reload({ preserveScroll: true, preserveState: true });
    }, 60000);
});
const quitarEscucha = router.on('navigate', () => { ui.sideAbierto = false; });
onBeforeUnmount(() => { clearInterval(reloj); quitarEscucha(); });
</script>

<template>
    <Head :title="titulo" />
    <div class="app">
        <aside class="side" :class="{ open: ui.sideAbierto }">
            <div class="brand">
                <div class="mark">S24</div>
                <div><b>SIGMA24</b><small>{{ page.props.app.clinica }}</small></div>
            </div>
            <nav class="nav" aria-label="Módulos">
                <template v-for="g in grupos" :key="g.n">
                    <div class="g">{{ g.n }}</div>
                    <Link v-for="m in g.items" :key="m.k" :href="route(m.ruta)" :class="{ on: actual === m.ruta }">
                        <i :class="m.i" /><span>{{ m.l }}</span>
                        <span v-if="cnt[m.k]" class="cnt">{{ cnt[m.k] }}</span>
                    </Link>
                </template>
            </nav>
            <div class="who">
                <div class="small" style="color: var(--side-muted)">Sesión: {{ user.role }}</div>
                <div class="row" style="gap: 6px">
                    <button type="button" @click="tema"><i class="pi pi-moon" /> Tema</button>
                    <button type="button" @click="salir"><i class="pi pi-sign-out" /> Salir</button>
                </div>
            </div>
        </aside>
        <div v-if="ui.sideAbierto" class="scrim" @click="ui.sideAbierto = false" />
        <div class="main">
            <header class="top">
                <Button class="menu-btn" icon="pi pi-bars" text rounded aria-label="Menú" @click="ui.sideAbierto = !ui.sideAbierto" />
                <div class="ttl"><h1>{{ titulo }}</h1><small>{{ subtitulo }}</small></div>
                <div class="sp" />
                <slot name="acciones" />
                <Button v-if="puede('cita')" label="Nueva cita" icon="pi pi-plus" @click="ui.abrir('nuevaCita', {})" />
                <div class="user">
                    <div class="avatar">{{ initials(user.name) }}</div>
                    <span><b style="font-weight: 500">{{ user.name }}</b><br /><small class="muted">{{ user.role }}</small></span>
                </div>
            </header>
            <section class="view"><slot /></section>
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
