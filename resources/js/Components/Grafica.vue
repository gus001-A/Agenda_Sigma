<script setup>
import { computed } from 'vue';
import Chart from 'primevue/chart';

/**
 * Gráfica de pastel/dona o de barras con la paleta del sistema.
 * tipo: 'pie' | 'doughnut' | 'bar' | 'barh'
 * etiquetas: ['A', 'B']; series: [{ nombre, datos: [1, 2], colores?: [] | color?: '' }]
 */
const props = defineProps({
    tipo: { type: String, default: 'bar' },
    etiquetas: { type: Array, default: () => [] },
    series: { type: Array, default: () => [] },
    apilada: Boolean,
    alto: { type: String, default: '240px' },
    formato: { type: Function, default: null },
    sinLeyenda: Boolean,
});

const PALETA = ['#173a5f', '#1f9e86', '#e08a1e', '#6b4bc9', '#d64545', '#0891b2', '#db2777', '#16a34a', '#2b5480', '#ea580c', '#64748b', '#7c3aed'];
const circular = computed(() => ['pie', 'doughnut'].includes(props.tipo));
const vacia = computed(() => !props.series.some((s) => (s.datos || []).some((v) => Number(v) > 0)));

const data = computed(() => ({
    labels: props.etiquetas,
    datasets: props.series.map((s, i) => {
        const colores = s.colores || (circular.value ? props.etiquetas.map((_, j) => PALETA[j % PALETA.length]) : s.color || PALETA[i % PALETA.length]);
        return {
            label: s.nombre,
            data: s.datos,
            backgroundColor: colores,
            hoverBackgroundColor: colores,
            borderColor: circular.value ? '#ffffff' : 'transparent',
            borderWidth: circular.value ? 2 : 0,
            borderRadius: circular.value ? 0 : 6,
            borderSkipped: false,
            maxBarThickness: 28,
            hoverOffset: circular.value ? 8 : 0,
        };
    }),
}));

const fmt = (v) => (props.formato ? props.formato(v) : Number(v).toLocaleString('es-MX'));
const options = computed(() => {
    const leyenda = {
        display: !props.sinLeyenda && (circular.value || props.series.length > 1),
        position: 'bottom',
        labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 14, color: '#42566b', font: { family: 'Inter', size: 12, weight: 600 } },
    };
    const tooltip = {
        backgroundColor: '#0f2c4a', padding: 10, cornerRadius: 10, titleFont: { family: 'Inter', weight: 700 }, bodyFont: { family: 'Inter' }, boxPadding: 4,
        callbacks: {
            label: (c) => {
                const v = c.parsed?.y ?? c.parsed?.x ?? c.parsed;
                if (circular.value) {
                    const total = c.dataset.data.reduce((a, b) => a + Number(b), 0) || 1;
                    return ` ${c.label}: ${fmt(c.parsed)} (${Math.round((c.parsed * 100) / total)}%)`;
                }
                return ` ${c.dataset.label ? c.dataset.label + ': ' : ''}${fmt(props.tipo === 'barh' ? c.parsed.x : v)}`;
            },
        },
    };
    if (circular.value) {
        return { maintainAspectRatio: false, cutout: props.tipo === 'doughnut' ? '62%' : 0, plugins: { legend: leyenda, tooltip } };
    }
    const eje = { grid: { color: '#eef2f6', drawTicks: false }, border: { display: false }, ticks: { color: '#5a6b7d', padding: 6, font: { family: 'Inter', size: 11 } } };
    const ejeValor = { ...eje, beginAtZero: true, stacked: props.apilada, ticks: { ...eje.ticks, callback: (v) => fmt(v), precision: 0 } };
    const ejeCat = { ...eje, grid: { display: false }, stacked: props.apilada };
    return {
        maintainAspectRatio: false,
        indexAxis: props.tipo === 'barh' ? 'y' : 'x',
        plugins: { legend: leyenda, tooltip },
        scales: props.tipo === 'barh' ? { x: ejeValor, y: ejeCat } : { x: ejeCat, y: ejeValor },
    };
});
</script>

<template>
    <div class="grafica" :style="{ height: alto }">
        <Chart v-if="!vacia" :type="tipo === 'barh' ? 'bar' : tipo" :data="data" :options="options" style="height: 100%" />
        <div v-else class="empty" style="height: 100%; justify-content: center"><i class="pi pi-chart-pie" style="font-size: 26px; color: #b6c3d1" /><b>Sin datos en el periodo</b></div>
    </div>
</template>
