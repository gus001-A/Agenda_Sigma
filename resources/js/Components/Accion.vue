<script setup>
/**
 * Botón de acción para tablas: cuadro con tinte del color de la acción e icono.
 * color: 'azul' | 'verde' | 'ambar' | 'rojo' | 'morado' | 'cian' | 'gris'
 * Con "texto" se muestra también la etiqueta (acción principal de la fila).
 */
defineProps({ icono: String, tip: String, color: { type: String, default: 'azul' }, texto: String, deshabilitado: Boolean });
defineEmits(['click']);
</script>

<template>
    <button v-tooltip.top="texto ? null : tip" type="button" class="acc" :class="['acc-' + color, { 'acc-txt': texto }]" :aria-label="tip || texto"
        :disabled="deshabilitado" @click.stop="$emit('click', $event)">
        <i :class="icono" /><span v-if="texto">{{ texto }}</span>
    </button>
</template>
