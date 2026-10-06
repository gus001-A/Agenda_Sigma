<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TablaDatos from '@/Components/TablaDatos.vue';
import { ui } from '@/lib/ui';

const props = defineProps({ filtros: Object, acciones: Array, registros: Object });
const cargando = ref(false);
const columnas = computed(() => [
    { campo: 'fecha', titulo: 'Fecha y hora', filtro: 'texto', ancho: '150px' },
    { campo: 'user', titulo: 'Usuario', filtro: 'texto', ancho: '190px' },
    { campo: 'action', titulo: 'Acción', filtro: 'lista', opciones: props.acciones, ancho: '220px' },
    { campo: 'detail', titulo: 'Detalle', filtro: 'texto' },
    { campo: 'ip', titulo: 'IP', filtro: 'texto', ancho: '120px' },
]);
const ICONOS = [['Cita', 'pi pi-calendar', '#2b5480'], ['Anticipo', 'pi pi-wallet', '#e08a1e'], ['Reembolso', 'pi pi-replay', '#8e3b69'], ['Historia', 'pi pi-file-edit', '#0891b2'],
    ['documento', 'pi pi-paperclip', '#6b4bc9'], ['Bloqueo', 'pi pi-ban', '#64748b'], ['Reservación', 'pi pi-building', '#6b4bc9'], ['Formato', 'pi pi-sliders-h', '#7c3aed'],
    ['paciente', 'pi pi-user', '#1f9e86'], ['Usuario', 'pi pi-users', '#4f46e5'], ['Permiso', 'pi pi-lock', '#4f46e5'], ['Notificación', 'pi pi-send', '#db2777'], ['Recordatorio', 'pi pi-bell', '#db2777']];
const icono = (a) => ICONOS.find(([k]) => a.toLowerCase().includes(k.toLowerCase())) || ['', 'pi pi-circle-fill', '#94a3b8'];

function cambio({ pagina, filas, filtros }) {
    cargando.value = true;
    router.get(route('auditoria'), { f: filtros, page: pagina, per_page: filas }, { preserveState: true, preserveScroll: true, replace: true, onFinish: () => (cargando.value = false) });
}
</script>

<template>
    <AppLayout titulo="Auditoría" subtitulo="Trazabilidad de cambios, consultas de historias clínicas y documentos">
        <TablaDatos :filas="registros.data" :columnas="columnas" lazy :total="registros.total" :primero="(registros.current_page - 1) * registros.per_page"
            :por-pagina="registros.per_page" :filtros-iniciales="filtros" :cargando="cargando" :clicable="true" vacio="Sin registros con estos filtros"
            @cambio="cambio" @fila="(x) => x.appointment_id && ui.abrirCita(x.appointment_id)">
            <template #c-fecha="{ fila }"><span style="white-space: nowrap">{{ fila.fecha }}</span></template>
            <template #c-user="{ fila }"><b style="font-weight: 600">{{ fila.user }}</b></template>
            <template #c-action="{ fila }">
                <span class="row" style="gap: 8px; flex-wrap: nowrap"><span class="hic" :style="{ '--c': icono(fila.action)[2], width: '26px', height: '26px' }"><i :class="icono(fila.action)[1]" /></span><b style="font-weight: 600">{{ fila.action }}</b></span>
            </template>
            <template #c-ip="{ fila }"><span class="muted small">{{ fila.ip || '—' }}</span></template>
        </TablaDatos>
    </AppLayout>
</template>
