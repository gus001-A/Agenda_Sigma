<script setup>
import { computed } from 'vue';
import { CLASE_ESTADO, CLASE_HC, CLASE_DEP, CLASE_ESPERA, CLASE_MENSAJE } from '@/lib/util';

const props = defineProps({ valor: String, tipo: { type: String, default: 'cita' } });
const mapas = { cita: CLASE_ESTADO, hc: CLASE_HC, dep: CLASE_DEP, espera: CLASE_ESPERA, msg: CLASE_MENSAJE };
const clase = computed(() => (mapas[props.tipo] || {})[props.valor] || '');
</script>

<template>
    <span class="chip" :class="clase">{{ valor }}<slot /></span>
</template>
