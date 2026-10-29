<script setup>
import { computed } from 'vue';
import { MESES, pad } from '@/lib/util';

/**
 * Trazabilidad de un registro: quién lo registró y cuándo (fecha y hora).
 * Si alguien lo modificó después, se muestra en una segunda línea.
 * Recibe el objeto con created_by / created_at / updated_by / updated_at.
 */
const props = defineProps({ r: { type: Object, default: () => ({}) }, etiqueta: { type: String, default: '' }, linea: Boolean });

const fmt = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    return `${d.getDate()} ${MESES[d.getMonth()].slice(0, 3)} ${d.getFullYear()} · ${pad(d.getHours())}:${pad(d.getMinutes())}`;
};
// Solo cuenta como modificación si la hizo alguien y en un momento distinto al alta.
const modificado = computed(() => props.r.updated_by && props.r.updated_at && fmt(props.r.updated_at) !== fmt(props.r.created_at));
const titulo = computed(() => [
    `Registró: ${props.r.created_by || 'sin registro'}${props.r.created_at ? ', ' + fmt(props.r.created_at) : ''}`,
    modificado.value ? `Modificó: ${props.r.updated_by}, ${fmt(props.r.updated_at)}` : null,
].filter(Boolean).join('\n'));
</script>

<template>
    <span class="traza" :class="{ linea }" :title="titulo">
        <span class="traza-l"><i class="pi pi-user-edit" /><span v-if="etiqueta" class="traza-k">{{ etiqueta }}</span><b :class="{ vacio: !r.created_by }">{{ r.created_by || 'Sin registro' }}</b></span>
        <small v-if="r.created_at"><i class="pi pi-clock" />{{ fmt(r.created_at) }}</small>
        <small v-if="modificado" class="traza-mod"><i class="pi pi-pencil" />{{ r.updated_by }} · {{ fmt(r.updated_at) }}</small>
    </span>
</template>
