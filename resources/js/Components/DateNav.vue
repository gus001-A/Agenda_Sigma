<script setup>
import { computed } from 'vue';
import { addDays, fmtDL, hoyISO, isoToDate, dateToISO } from '@/lib/util';

const props = defineProps({ fecha: String, paso: { type: Number, default: 1 }, etiqueta: String });
const emit = defineEmits(['cambiar']);
const valor = computed({ get: () => isoToDate(props.fecha), set: (d) => d && emit('cambiar', dateToISO(d)) });
</script>

<template>
    <div class="row" style="gap: 6px">
        <Button icon="pi pi-chevron-left" size="small" severity="secondary" outlined aria-label="Anterior" @click="emit('cambiar', addDays(fecha, -paso))" />
        <Button label="Hoy" size="small" severity="secondary" outlined @click="emit('cambiar', hoyISO())" />
        <Button icon="pi pi-chevron-right" size="small" severity="secondary" outlined aria-label="Siguiente" @click="emit('cambiar', addDays(fecha, paso))" />
        <DatePicker v-model="valor" date-format="dd/mm/yy" show-icon icon-display="input" size="small" style="width: 150px" aria-label="Fecha" />
        <span class="muted small" style="margin-left: 4px">{{ etiqueta || fmtDL(fecha) }}</span>
    </div>
</template>
