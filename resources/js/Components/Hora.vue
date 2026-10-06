<script setup>
/** Campo de hora (HH:MM) con estilo PrimeVue. */
defineProps({ modelValue: String, disabled: Boolean, step: { type: Number, default: 600 }, id: String });
const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <input :id="id" type="time" class="p-inputtext p-component" style="width: 100%" :value="modelValue" :disabled="disabled" :step="step"
        @input="emit('update:modelValue', $event.target.value)" />
</template>
