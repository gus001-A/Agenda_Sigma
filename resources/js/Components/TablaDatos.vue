<script setup>
import { ref, watch, computed } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';

/**
 * Tabla estándar del sistema: filtro de búsqueda al pie de cada columna (debajo de los registros),
 * paginación de 10 o 15 registros y desplazamiento interno (la página no se mueve, solo los registros).
 *
 * columnas: [{ campo, titulo, filtro?: 'texto' | 'lista', opciones?: [], ancho?, alinear?: 'right', ordenar?: bool }]
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
    porPagina: { type: Number, default: 10 },
    filtrosIniciales: { type: Object, default: () => ({}) },
    clicable: Boolean,
    claseFila: Function,
    vacio: { type: String, default: 'Sin registros con estos filtros' },
    cargando: Boolean,
});
const emit = defineEmits(['fila', 'cambio']);

const crearFiltros = () => Object.fromEntries(props.columnas.filter((c) => c.filtro).map((c) => [c.campo, {
    value: props.filtrosIniciales[c.campo] ?? null,
    matchMode: c.filtro === 'lista' ? FilterMatchMode.EQUALS : FilterMatchMode.CONTAINS,
}]));
const filtros = ref(crearFiltros());
const porPag = ref(props.porPagina);
const hayFiltros = computed(() => Object.values(filtros.value).some((f) => f.value !== null && f.value !== ''));
const hayFiltrables = computed(() => props.columnas.some((c) => c.filtro));
// Si cambian las columnas (p. ej. otra pestaña), se reconstruyen los filtros.
watch(() => props.columnas.map((c) => c.campo).join('|'), () => { filtros.value = crearFiltros(); });

const valor = (fila, campo) => campo.split('.').reduce((o, k) => (o == null ? o : o[k]), fila);
const filtrosPlanos = () => Object.fromEntries(Object.entries(filtros.value).filter(([, f]) => f.value !== null && f.value !== '').map(([k, f]) => [k, f.value]));

let t = null;
function avisarCambio(pagina = 0) {
    if (!props.lazy) return;
    clearTimeout(t);
    t = setTimeout(() => emit('cambio', { pagina: pagina + 1, filas: porPag.value, filtros: filtrosPlanos() }), 350);
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
</script>

<template>
    <div class="tabla-datos">
        <DataTable
            :filters="filtros"
            v-model:rows="porPag"
            :value="props.filas"
            :data-key="clave"
            paginator
            :rows-per-page-options="[10, 15]"
            paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
            current-page-report-template="{first}–{last} de {totalRecords}"
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
            @page="(e) => avisarCambio(e.page)"
        >
            <Column v-for="c in columnas" :key="c.campo" :field="c.campo" :header="c.titulo" :sortable="!lazy && c.ordenar !== false"
                :style="c.ancho ? { minWidth: c.ancho, width: c.ancho } : {}" :header-class="c.alinear === 'right' ? 'td-der' : ''"
                :body-class="c.alinear === 'right' ? 'td-der' : ''" footer-class="td-pie">
                <template #body="{ data }">
                    <slot :name="'c-' + c.campo" :fila="data">{{ valor(data, c.campo) ?? '—' }}</slot>
                </template>
                <!-- Filtro al pie de la columna, debajo de los registros -->
                <template v-if="hayFiltrables" #footer>
                    <Select v-if="c.filtro === 'lista'" :model-value="filtros[c.campo]?.value" :options="c.opciones" placeholder="Todos" show-clear size="small"
                        class="td-filtro" :class="{ activo: filtros[c.campo]?.value }" append-to="body" @update:model-value="setFiltro(c.campo, $event)" />
                    <IconField v-else-if="c.filtro" class="td-filtro" :class="{ activo: filtros[c.campo]?.value }">
                        <InputIcon class="pi pi-search" />
                        <InputText :model-value="filtros[c.campo]?.value" placeholder="Buscar" size="small" @update:model-value="setFiltro(c.campo, $event)" />
                    </IconField>
                </template>
            </Column>
            <template #empty>
                <div class="empty"><i class="pi pi-inbox" style="font-size: 26px; color: #b6c3d1" /><b style="margin-top: 6px">{{ vacio }}</b></div>
            </template>
            <template #paginatorstart>
                <Button v-if="hayFiltros" label="Limpiar filtros" icon="pi pi-filter-slash" size="small" text @click="limpiar" />
            </template>
        </DataTable>
    </div>
</template>
