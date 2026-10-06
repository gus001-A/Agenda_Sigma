<script setup>
import { ref, onMounted, watch } from 'vue';

/** Área de firma electrónica simple (trazo en canvas, se guarda como PNG en base64). */
const props = defineProps({ modelValue: String, disabled: Boolean });
const emit = defineEmits(['update:modelValue']);
const canvas = ref(null);
let ctx = null;
let dibujando = false;

function pintarGuardada() {
    if (!ctx) return;
    ctx.clearRect(0, 0, canvas.value.width, canvas.value.height);
    if (props.modelValue) {
        const im = new Image();
        im.onload = () => ctx.drawImage(im, 0, 0, canvas.value.width, canvas.value.height);
        im.src = props.modelValue;
    }
}
onMounted(() => {
    ctx = canvas.value.getContext('2d');
    ctx.strokeStyle = '#13262D';
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    pintarGuardada();
});
watch(() => props.modelValue, (v, old) => { if (v && !old) pintarGuardada(); });

const pos = (e) => { const r = canvas.value.getBoundingClientRect(); return [((e.clientX - r.left) * canvas.value.width) / r.width, ((e.clientY - r.top) * canvas.value.height) / r.height]; };
function abajo(e) { if (props.disabled) return; dibujando = true; canvas.value.setPointerCapture(e.pointerId); const [x, y] = pos(e); ctx.beginPath(); ctx.moveTo(x, y); }
function mover(e) { if (!dibujando) return; const [x, y] = pos(e); ctx.lineTo(x, y); ctx.stroke(); }
function arriba() { if (!dibujando) return; dibujando = false; emit('update:modelValue', canvas.value.toDataURL('image/png')); }
function borrar() { ctx.clearRect(0, 0, canvas.value.width, canvas.value.height); emit('update:modelValue', null); }
</script>

<template>
    <canvas ref="canvas" class="sig" width="600" height="200" aria-label="Área de firma" @pointerdown="abajo" @pointermove="mover" @pointerup="arriba" />
    <Button v-if="!disabled" label="Borrar firma" size="small" text style="justify-self: start" @click="borrar" />
</template>
