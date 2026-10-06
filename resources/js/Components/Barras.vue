<script setup>
import { computed } from 'vue';

/**
 * Barras horizontales. items: [{ l, v, max?, c?, clase?, partes?: [{ v, c }] }]
 * formato: función que recibe el item y devuelve el texto de la derecha.
 */
const props = defineProps({ items: Array, formato: Function, maximo: Number });
const mx = computed(() => props.maximo || Math.max(1, ...props.items.map((i) => i.max ?? i.v)));
</script>

<template>
    <div class="bars">
        <div v-for="(i, k) in items" :key="k" class="bar" :class="i.clase">
            <span class="n" :title="i.l">{{ i.l }}</span>
            <span class="t"><i v-for="(p, j) in i.partes || [{ v: i.v, c: i.c }]" :key="j" :style="{ width: (p.v * 100) / mx + '%', background: p.c || 'var(--primary)' }" /></span>
            <span class="v">{{ formato ? formato(i) : i.v }}</span>
        </div>
        <p v-if="!items.length" class="muted small">Sin datos en el periodo.</p>
    </div>
</template>
