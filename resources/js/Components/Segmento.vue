<script setup>
/** Selector segmentado con icono y contador opcional. opciones: [{ l, v, i?, n? }] */
defineProps({ modelValue: null, opciones: Array, vacio: Boolean });
const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <SelectButton :model-value="modelValue" :options="opciones" option-label="l" option-value="v" :allow-empty="vacio" @update:model-value="emit('update:modelValue', $event)">
        <template #option="{ option }">
            <i v-if="option.i" :class="option.i" />
            <span>{{ option.l }}</span>
            <span v-if="option.n !== undefined" class="seg-n">{{ option.n }}</span>
        </template>
    </SelectButton>
</template>
