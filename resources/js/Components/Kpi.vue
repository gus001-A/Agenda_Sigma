<script setup>
/** Tarjeta-indicador con icono y color de acento. */
defineProps({ etiqueta: String, valor: [String, Number], extra: String, icono: String, color: { type: String, default: "#173a5f" }, clic: Boolean, seleccionado: Boolean, doble: Boolean });
defineEmits(['click']);
</script>

<template>
    <div class="kpi" :class="{ click: clic, sel: seleccionado, doble }" :style="{ '--acc': color }" :title="etiqueta" @click="clic && $emit('click')">
        <span class="kpi-ic"><i :class="icono" /></span>
        <div>
            <span>{{ etiqueta }}</span>
            <b>{{ valor }}</b><em v-if="extra">{{ extra }}</em>
        </div>
    </div>
</template>
