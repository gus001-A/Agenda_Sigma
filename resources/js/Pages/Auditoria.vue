<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ui } from '@/lib/ui';
import { filtrar } from '@/lib/api';
import { fmtStamp } from '@/lib/util';

const props = defineProps({ q: String, registros: Object });
const busqueda = ref(props.q || '');
let t = null;
watch(busqueda, (v) => { clearTimeout(t); t = setTimeout(() => filtrar('auditoria', { q: v }), 300); });
const pagina = (e) => router.get(route('auditoria'), { q: props.q, page: e.page + 1 }, { preserveState: true });
</script>

<template>
    <AppLayout titulo="Auditoría" subtitulo="Trazabilidad de cambios">
        <div class="vh">
            <IconField style="max-width: 360px; flex: 1"><InputIcon class="pi pi-search" /><InputText v-model="busqueda" placeholder="Filtrar por usuario, acción o paciente" fluid /></IconField>
            <div class="sp" />
            <span class="muted small">{{ registros.total }} registros</span>
        </div>
        <div class="card tbl-wrap"><table class="tbl">
            <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead>
            <tbody>
                <tr v-for="x in registros.data" :key="x.id" :class="{ click: x.appointment_id }" @click="x.appointment_id && ui.abrirCita(x.appointment_id)">
                    <td style="white-space: nowrap">{{ fmtStamp(x.at) }}</td><td>{{ x.user }}</td><td><b style="font-weight: 500">{{ x.action }}</b></td><td>{{ x.detail }}</td><td class="muted small">{{ x.ip }}</td>
                </tr>
                <tr v-if="!registros.data.length"><td colspan="5"><div class="empty"><b>Sin registros</b></div></td></tr>
            </tbody>
        </table></div>
        <Paginator v-if="registros.total > registros.per_page" :rows="registros.per_page" :total-records="registros.total" :first="(registros.current_page - 1) * registros.per_page" @page="pagina" />
    </AppLayout>
</template>
