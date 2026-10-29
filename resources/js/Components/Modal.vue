<script setup>
/**
 * Modal estándar del sistema (estilo RIC + SIGAM): encabezado con icono teñido, título y
 * subtítulo, franja de color superior, cuerpo con desplazamiento y pie con acciones.
 * Acepta los mismos atributos que <Dialog> de PrimeVue (v-model:visible, :style, :breakpoints...).
 */
defineOptions({ inheritAttrs: false });
defineProps({
    titulo: String,
    header: String,
    subtitulo: String,
    icono: { type: String, default: 'pi pi-window-maximize' },
    color: { type: String, default: '#173a5f' },
    ancho: { type: String, default: '600px' },
});
</script>

<template>
    <Dialog v-bind="$attrs" modal :dismissable-mask="true" :draggable="false" :style="{ width: ancho, maxWidth: '96vw' }" :breakpoints="{ '760px': '96vw' }"
        class="modal-pro" :pt="{ root: { style: { '--mc': color } } }">
        <template #header>
            <div class="mp-head">
                <span class="mp-ic"><i :class="icono" /></span>
                <div class="mp-tt">
                    <div class="mp-title">{{ titulo || header }}</div>
                    <div v-if="subtitulo || $slots.subtitulo" class="mp-sub"><slot name="subtitulo">{{ subtitulo }}</slot></div>
                </div>
            </div>
        </template>
        <slot />
        <template v-if="$slots.footer" #footer><slot name="footer" /></template>
    </Dialog>
</template>
