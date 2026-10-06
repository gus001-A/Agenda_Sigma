<script setup>
/** Bloque de un modal: encabezado con icono teñido, título y ayuda opcional. */
defineProps({ titulo: String, icono: String, color: { type: String, default: '#173a5f' }, ayuda: String, paso: [String, Number] });
</script>

<template>
    <section class="msec" :style="{ '--sc': color }">
        <header class="msec-h">
            <span class="msec-ic"><i :class="icono" /></span>
            <div class="msec-t"><b><span v-if="paso" class="msec-paso">{{ paso }}</span>{{ titulo }}</b><small v-if="ayuda">{{ ayuda }}</small></div>
            <div class="msec-x"><slot name="extra" /></div>
        </header>
        <div class="msec-b"><slot /></div>
    </section>
</template>
