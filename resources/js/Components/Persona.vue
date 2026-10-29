<script setup>
import { computed } from 'vue';
import { initials } from '@/lib/util';

/** Avatar con iniciales, nombre y una línea de detalle. El color sale del nombre, así cada persona conserva el suyo. */
const props = defineProps({ nombre: String, detalle: String, color: String });
const PALETA = ['#2b5480', '#1f9e86', '#6b4bc9', '#db2777', '#e08a1e', '#0e7490', '#16a34a', '#c2410c', '#7c3aed', '#0891b2'];
const tono = computed(() => {
    if (props.color) return props.color;
    let h = 0;
    for (const ch of props.nombre || '') h = (h * 31 + ch.charCodeAt(0)) >>> 0;
    return PALETA[h % PALETA.length];
});
</script>

<template>
    <span class="persona" :style="{ '--pc': tono }">
        <span class="persona-av">{{ initials(nombre) }}</span>
        <span><b :title="nombre">{{ nombre }}</b><small v-if="detalle || $slots.default"><slot>{{ detalle }}</slot></small></span>
    </span>
</template>
