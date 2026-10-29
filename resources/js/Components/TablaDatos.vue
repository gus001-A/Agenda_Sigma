<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';

/**
 * Tabla estándar del sistema: filtro de búsqueda al pie de cada columna (debajo de los registros),
 * paginación con los registros que quepan en pantalla (sin hueco entre la última fila y la paginación) y desplazamiento interno
 * (la página no se mueve, solo los registros).
 *
 * columnas: [{ campo, titulo, filtro?: 'texto' | 'lista', opciones?: [], ancho?, alinear?: 'right', ordenar?: bool, envolver?: bool }]
 * Las celdas van en una sola línea (con "…"); envolver: true deja que el texto ocupe varias líneas.
 * Celdas personalizadas con el slot "c-<campo>" ({ fila }).
 * Modo servidor (lazy): emite "cambio" con { pagina, filas, filtros } y recibe "total".
 */
const props = defineProps({
    filas: { type: Array, default: () => [] },
    columnas: { type: Array, required: true },
    clave: { type: String, default: 'id' },
    lazy: Boolean,
    total: Number,
    primero: { type: Number, default: 0 },
    porPagina: { type: Number, default: 12 },
    // Registros por página fijos (no se ajustan al alto de la pantalla).
    filasFijas: Number,
    filtrosIniciales: { type: Object, default: () => ({}) },
    clicable: Boolean,
    claseFila: Function,
    vacio: { type: String, default: 'Sin registros con estos filtros' },
    cargando: Boolean,
});
const emit = defineEmits(['fila', 'cambio']);

const hayFiltrables = computed(() => props.columnas.some((c) => c.filtro));
// El ancho de cada columna es una proporción (las que no lo indican pesan 110 px), así la tabla
// siempre ocupa el 100 % del espacio sin desplazamiento horizontal ni columnas aplastadas.
const anchos = computed(() => {
    const pesos = props.columnas.map((c) => parseFloat(c.ancho) || 110);
    const total = pesos.reduce((a, b) => a + b, 0);
    return Object.fromEntries(props.columnas.map((c, i) => [c.campo, ((pesos[i] * 100) / total).toFixed(2) + '%']));
});
const crearFiltros = () => Object.fromEntries(props.columnas.filter((c) => c.filtro).map((c) => [c.campo, {
    value: props.filtrosIniciales[c.campo] ?? null,
    matchMode: c.filtro === 'lista' ? FilterMatchMode.EQUALS : FilterMatchMode.CONTAINS,
}]));
const filtros = ref(crearFiltros());
const porPag = ref(props.filasFijas || props.porPagina);
const activos = computed(() => Object.values(filtros.value).filter((f) => f.value !== null && f.value !== '').length);
// Si cambian las columnas (p. ej. otra pestaña), se reconstruyen los filtros.
watch(() => props.columnas.map((c) => c.campo).join('|'), () => { filtros.value = crearFiltros(); });

const valor = (fila, campo) => campo.split('.').reduce((o, k) => (o == null ? o : o[k]), fila);
const filtrosPlanos = () => Object.fromEntries(Object.entries(filtros.value).filter(([, f]) => f.value !== null && f.value !== '').map(([k, f]) => [k, f.value]));

let t = null;
function avisarCambio(pagina = 0, espera = 350) {
    if (!props.lazy) return;
    clearTimeout(t);
    t = setTimeout(() => emit('cambio', { pagina: pagina + 1, filas: porPag.value, filtros: filtrosPlanos() }), espera);
}
// Se reemplaza el objeto completo para que la tabla vuelva a filtrar.
function setFiltro(campo, v) {
    filtros.value = { ...filtros.value, [campo]: { ...filtros.value[campo], value: v === '' ? null : v } };
    avisarCambio(0);
}
function limpiar() {
    filtros.value = Object.fromEntries(Object.entries(filtros.value).map(([k, f]) => [k, { ...f, value: null }]));
    avisarCambio(0);
}
defineExpose({ limpiar });

// Registros por página: los que quepan en el alto disponible, y el sobrante (menos de una fila) se reparte
// entre las filas para que no quede hueco entre la última fila y la paginación.
const raiz = ref(null);
let observador = null;
function ajustarFilas() {
    if (props.filasFijas) return;
    const cont = raiz.value?.querySelector('.p-datatable-table-container');
    const filas = cont ? [...cont.querySelectorAll('.p-datatable-tbody > tr:not(.p-datatable-empty-message)')] : [];
    if (!cont || !filas.length) return;
    raiz.value.style.removeProperty('--fila-h'); // se mide la altura natural, sin el estiramiento anterior
    const fijo = (cont.querySelector('.p-datatable-thead')?.offsetHeight || 0) + (cont.querySelector('.p-datatable-tfoot')?.offsetHeight || 0);
    const disponible = cont.clientHeight - fijo;
    const alto = Math.max(...filas.map((f) => f.offsetHeight));
    const n = Math.min(40, Math.max(5, Math.floor(disponible / alto)));
    // Solo se estira cuando la página está llena; la última página puede traer menos registros.
    if (filas.length >= n) raiz.value.style.setProperty('--fila-h', Math.floor(disponible / n) + 'px');
    if (n !== porPag.value) {
        porPag.value = n;
        avisarCambio(0, 0);
        nextTick(ajustarFilas);
    }
}
// Al cambiar de página o de datos, se vuelve a medir.
watch(() => props.filas, () => nextTick(ajustarFilas));
onMounted(() => {
    nextTick(ajustarFilas);
    observador = new ResizeObserver(() => { clearTimeout(observador.t); observador.t = setTimeout(ajustarFilas, 120); });
    if (raiz.value) observador.observe(raiz.value);
});
onBeforeUnmount(() => observador?.disconnect());

// Páginas visibles: primera, última y dos alrededor de la actual (-1 = puntos suspensivos).
function ventana(p, total) {
    if (total <= 7) return [...Array(total).keys()];
    const set = new Set([0, total - 1, p - 1, p, p + 1].filter((x) => x >= 0 && x < total));
    const orden = [...set].sort((a, b) => a - b);
    const out = [];
    orden.forEach((n, i) => { if (i && n - orden[i - 1] > 1) out.push(-i); out.push(n); });
    return out;
}
</script>

<template>
    <div ref="raiz" class="tabla-datos">
        <DataTable
            :filters="filtros"
            v-model:rows="porPag"
            :value="props.filas"
            :data-key="clave"
            paginator
            scrollable
            scroll-height="flex"
            removable-sort
            striped-rows
            size="small"
            :lazy="lazy"
            :total-records="lazy ? total : undefined"
            :first="lazy ? primero : undefined"
            :loading="cargando"
            :row-class="(f) => [clicable ? 'fila-clic' : '', claseFila ? claseFila(f) : ''].join(' ')"
            class="td"
            @row-click="(e) => clicable && emit('fila', e.data)"
            @page="(e) => { avisarCambio(e.page); nextTick(ajustarFilas); }"
        >
            <Column v-for="c in columnas" :key="c.campo" :field="c.campo" :header="c.titulo" :sortable="!lazy && c.ordenar !== false"
                :style="{ width: anchos[c.campo] }" :header-class="c.alinear === 'right' ? 'td-der' : ''"
                :body-class="[c.alinear === 'right' ? 'td-der' : '', c.envolver ? 'td-envolver' : ''].join(' ')" footer-class="td-pie">
                <template #body="{ data }">
                    <slot :name="'c-' + c.campo" :fila="data"><span :title="c.envolver ? null : valor(data, c.campo)">{{ valor(data, c.campo) ?? '—' }}</span></slot>
                </template>
                <!-- Filtro al pie de la columna, debajo de los registros -->
                <template v-if="hayFiltrables" #footer>
                    <Select v-if="c.filtro === 'lista'" :model-value="filtros[c.campo]?.value" :options="c.opciones" :placeholder="c.titulo" show-clear size="small"
                        class="td-filtro" :class="{ activo: filtros[c.campo]?.value }" append-to="body"
                        :aria-label="'Filtrar ' + c.titulo" @update:model-value="setFiltro(c.campo, $event)" />
                    <IconField v-else-if="c.filtro" class="td-filtro" :class="{ activo: filtros[c.campo]?.value }">
                        <InputIcon class="pi pi-search" />
                        <InputText :model-value="filtros[c.campo]?.value" :placeholder="c.titulo" size="small" :aria-label="'Buscar ' + c.titulo" @update:model-value="setFiltro(c.campo, $event)" />
                    </IconField>
                    <span v-else-if="c === columnas[columnas.length - 1] && activos" class="td-limpiar" title="Quitar filtros" @click="limpiar"><i class="pi pi-filter-slash" /></span>
                </template>
            </Column>
            <template #empty>
                <div class="empty"><i class="pi pi-inbox" style="font-size: 26px; color: #b6c3d1" /><b style="margin-top: 6px">{{ vacio }}</b>
                    <Button v-if="activos" label="Quitar filtros" icon="pi pi-filter-slash" size="small" text style="margin-top: 6px" @click="limpiar" />
                </div>
            </template>
            <template #paginatorcontainer="{ first, last, page, pageCount, totalRecords, firstPageCallback, prevPageCallback, nextPageCallback, lastPageCallback, changePageCallback }">
                <div class="pag">
                    <div class="pag-info">
                        <span class="pag-ic"><i class="pi pi-list" /></span>
                        <span>Mostrando <b>{{ totalRecords ? first : 0 }}–{{ last }}</b> de <b>{{ totalRecords }}</b> registros</span>
                        <span v-if="activos" class="pag-filtro"><i class="pi pi-filter" /> {{ activos }} {{ activos === 1 ? 'filtro' : 'filtros' }}</span>
                        <Button v-if="activos" label="Limpiar filtros" icon="pi pi-filter-slash" size="small" severity="danger" text @click="limpiar" />
                    </div>
                    <div v-if="pageCount > 1" class="pag-nav">
                        <button type="button" class="pag-b" :disabled="page === 0" aria-label="Primera página" @click="firstPageCallback"><i class="pi pi-angle-double-left" /></button>
                        <button type="button" class="pag-b" :disabled="page === 0" aria-label="Página anterior" @click="prevPageCallback"><i class="pi pi-angle-left" /></button>
                        <template v-for="n in ventana(page, pageCount)" :key="n">
                            <span v-if="n < 0" class="pag-gap">…</span>
                            <button v-else type="button" class="pag-b pag-n" :class="{ on: n === page }" @click="changePageCallback(n)">{{ n + 1 }}</button>
                        </template>
                        <button type="button" class="pag-b" :disabled="page >= pageCount - 1" aria-label="Página siguiente" @click="nextPageCallback"><i class="pi pi-angle-right" /></button>
                        <button type="button" class="pag-b" :disabled="page >= pageCount - 1" aria-label="Última página" @click="lastPageCallback"><i class="pi pi-angle-double-right" /></button>
                    </div>
                    <span v-else class="pag-una">Página 1 de 1</span>
                </div>
            </template>
        </DataTable>
    </div>
</template>
