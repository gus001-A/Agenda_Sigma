<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Segmento from '@/Components/Segmento.vue';
import Kpi from '@/Components/Kpi.vue';
import Grafica from '@/Components/Grafica.vue';
import Fecha from '@/Components/Fecha.vue';
import { filtrar, avisar } from '@/lib/api';
import { money, hoyISO, addDays, toISO, parseISO, COLOR_ESTADO, COLOR_HC } from '@/lib/util';

const props = defineProps({ desde: String, hasta: String, kpis: Object, porMedico: Array, porEspecialidad: Array, porEstado: Array, anticipos: Object, historias: Array });
const ir = (c) => filtrar('reportes', { desde: props.desde, hasta: props.hasta, ...c });
const hoy = hoyISO();
const finMes = addDays(toISO(new Date(parseISO(hoy).getFullYear(), parseISO(hoy).getMonth() + 1, 1)), -1);
const atajos = [{ l: 'Este mes', d: hoy.slice(0, 8) + '01', h: finMes, i: 'pi pi-calendar' }, { l: 'Últimos 7 días', d: addDays(hoy, -7), h: hoy, i: 'pi pi-history' }, { l: 'Próximos 14 días', d: hoy, h: addDays(hoy, 14), i: 'pi pi-forward' }];
const atajo = computed(() => atajos.find((a) => a.d === props.desde && a.h === props.hasta)?.l || null);
// Tres barras independientes por médico o especialidad: atendidas, por atender y canceladas.
const corto = (n) => n.replace(/^(Dr\.|Dra\.|Lic\.|Psic\.)\s*/, '').split(' ').slice(0, 2).join(' ');
const series3 = (lista, cortar = false) => ({
    etiquetas: lista.map((x) => (cortar ? corto(x.l) : x.l)),
    series: [
        { nombre: 'Atendidas', datos: lista.map((x) => x.atendidas), color: '#173a5f' },
        { nombre: 'Por atender', datos: lista.map((x) => x.v - x.atendidas - x.canceladas), color: '#1f9e86' },
        { nombre: 'Canceladas', datos: lista.map((x) => x.canceladas), color: '#d64545' },
    ],
});
const imprimir = () => window.print();

// PDF: indicadores + cada gráfica de la pantalla con su tabla de datos. La librería se carga solo al exportar.
const page = usePage();
const tablero = ref(null);
const generando = ref(false);
const lienzo = (k) => tablero.value?.querySelector(`[data-pdf="${k}"] canvas`);
const fila3 = (x) => [x.l, x.atendidas, x.v - x.atendidas - x.canceladas, x.canceladas, x.v];
async function pdf() {
    generando.value = true;
    try {
        const { exportarReportePdf } = await import('@/lib/reportePdf');
        exportarReportePdf({
            clinica: page.props.app.clinica, usuario: page.props.auth.user.name, desde: props.desde, hasta: props.hasta,
            kpis: [
                { l: 'Citas en el periodo', v: props.kpis.citas }, { l: 'Atendidas', v: props.kpis.atendidas }, { l: 'Canceladas', v: props.kpis.canceladas },
                { l: 'Reagendadas', v: props.kpis.reagendadas }, { l: 'No atendidas', v: props.kpis.noAtendidas },
                { l: 'Pacientes atendidos', v: props.kpis.pacientesAtendidos }, { l: 'Pacientes nuevos', v: props.kpis.pacientesNuevos },
            ],
            graficas: [
                { titulo: 'Citas por médico', canvas: lienzo('medico'), tabla: { cabeza: ['Médico', 'Atendidas', 'Por atender', 'Canceladas', 'Total'], filas: props.porMedico.map(fila3) } },
                { titulo: 'Citas por especialidad', canvas: lienzo('especialidad'), tabla: { cabeza: ['Especialidad', 'Atendidas', 'Por atender', 'Canceladas', 'Total'], filas: props.porEspecialidad.map(fila3) } },
                { titulo: 'Citas por estado', lado: true, canvas: lienzo('estado'), tabla: { cabeza: ['Estado', 'Citas'], filas: props.porEstado.map((x) => [x.l, x.v]) } },
                { titulo: 'Anticipos', lado: true, canvas: lienzo('anticipos'), tabla: { cabeza: ['Concepto', 'Monto'], filas: [['Cobrados', money(props.anticipos.cobrados)], ['Pendientes', money(props.anticipos.pendientes)], ['Reembolsados', money(props.anticipos.reembolsados)]] } },
                { titulo: 'Historias clínicas', lado: true, canvas: lienzo('historias'), tabla: { cabeza: ['Estado', 'Historias'], filas: props.historias.map((x) => [x.l, x.v]) } },
            ],
        });
    } catch (e) {
        avisar('No se pudo generar el PDF: ' + e.message, 'error');
    } finally {
        generando.value = false;
    }
}
const exportar = computed(() => route('reportes.exportar', { desde: props.desde, hasta: props.hasta }));
</script>

<template>
    <AppLayout titulo="Reportes" subtitulo="Agenda, pacientes, anticipos e historias clínicas" sin-nueva-cita>
        <template #acciones>
            <Button label="Exportar PDF" icon="pi pi-file-pdf" severity="danger" class="no-print" :loading="generando" @click="pdf" />
            <a :href="exportar" class="no-print"><Button label="Exportar CSV" icon="pi pi-download" severity="secondary" outlined /></a>
            <Button label="Imprimir" icon="pi pi-print" severity="secondary" outlined class="no-print" @click="imprimir" />
        </template>
        <div class="vh">
            <div class="field" style="width: 170px"><label>Desde</label><Fecha :model-value="desde" @update:model-value="ir({ desde: $event })" /></div>
            <div class="field" style="width: 170px"><label>Hasta</label><Fecha :model-value="hasta" @update:model-value="ir({ hasta: $event })" /></div>
            <Segmento :model-value="atajo" :opciones="atajos.map((a) => ({ l: a.l, v: a.l, i: a.i }))" vacio @update:model-value="(l) => { const a = atajos.find((x) => x.l === l); if (a) ir({ desde: a.d, hasta: a.h }); }" />
        </div>
        <div class="kpis linea">
            <Kpi etiqueta="Citas en el periodo" :valor="kpis.citas" icono="pi pi-calendar" color="#173a5f" />
            <Kpi etiqueta="Atendidas" :valor="kpis.atendidas" icono="pi pi-check-circle" color="#1f9e86" />
            <Kpi etiqueta="Canceladas" :valor="kpis.canceladas" icono="pi pi-times-circle" color="#d64545" />
            <Kpi etiqueta="Reagendadas" :valor="kpis.reagendadas" icono="pi pi-refresh" color="#8e3b69" />
            <Kpi etiqueta="No atendidas" :valor="kpis.noAtendidas" icono="pi pi-user-minus" color="#e08a1e" />
            <Kpi etiqueta="Pacientes atendidos" :valor="kpis.pacientesAtendidos" icono="pi pi-users" color="#2b5480" />
            <Kpi etiqueta="Pacientes nuevos" :valor="kpis.pacientesNuevos" icono="pi pi-user-plus" color="#6b4bc9" />
        </div>
        <div ref="tablero" class="graficas scroll-y" style="align-content: start">
            <div class="card" data-pdf="medico">
                <header><span class="hic" style="--c: #173a5f"><i class="pi pi-user" /></span><h3>Citas por médico</h3></header>
                <div class="body"><Grafica tipo="bar" v-bind="series3(porMedico, true)" alto="320px" /></div>
            </div>
            <div class="card" data-pdf="especialidad">
                <header><span class="hic" style="--c: #0891b2"><i class="pi pi-bookmark" /></span><h3>Citas por especialidad</h3></header>
                <div class="body"><Grafica tipo="bar" v-bind="series3(porEspecialidad)" alto="320px" /></div>
            </div>
            <!-- Gráficas de pastel en una sola línea -->
            <div class="pasteles">
            <div class="card" data-pdf="estado">
                <header><span class="hic" style="--c: #2b5480"><i class="pi pi-chart-pie" /></span><h3>Citas por estado</h3></header>
                <div class="body"><Grafica tipo="doughnut" :etiquetas="porEstado.map((x) => x.l)" :series="[{ nombre: 'Citas', datos: porEstado.map((x) => x.v), colores: porEstado.map((x) => COLOR_ESTADO[x.l] || '#94a3b8') }]" alto="260px" /></div>
            </div>
            <div class="card" data-pdf="anticipos">
                <header><span class="hic" style="--c: #16a34a"><i class="pi pi-wallet" /></span><h3>Anticipos</h3></header>
                <div class="body"><Grafica tipo="pie" :etiquetas="['Cobrados', 'Pendientes', 'Reembolsados']" :series="[{ nombre: 'Anticipos', datos: [anticipos.cobrados, anticipos.pendientes, anticipos.reembolsados], colores: ['#16a34a', '#e08a1e', '#8e3b69'] }]" :formato="money" alto="260px" /></div>
            </div>
            <div class="card" data-pdf="historias">
                <header><span class="hic" style="--c: #0891b2"><i class="pi pi-file-edit" /></span><h3>Historias clínicas</h3></header>
                <div class="body"><Grafica tipo="doughnut" :etiquetas="historias.map((x) => x.l)" :series="[{ nombre: 'Historias', datos: historias.map((x) => x.v), colores: historias.map((x) => COLOR_HC[x.l]) }]" alto="260px" /></div>
            </div>
            </div>
        </div>
    </AppLayout>
</template>
