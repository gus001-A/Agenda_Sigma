<script setup>
import { computed } from 'vue';
import { isoToDate, dateToISO } from '@/lib/util';

/** DatePicker de PrimeVue con v-model en formato ISO (YYYY-MM-DD). */
const props = defineProps({ modelValue: String, min: String, max: String, disabled: Boolean, inputId: String });
const emit = defineEmits(['update:modelValue']);
const valor = computed({ get: () => isoToDate(props.modelValue), set: (d) => emit('update:modelValue', dateToISO(d)) });
</script>

<template>
    <DatePicker v-model="valor" :min-date="isoToDate(min)" :max-date="isoToDate(max)" :disabled="disabled" :input-id="inputId"
        date-format="dd/mm/yy" show-icon icon-display="input" fluid />
</template>
