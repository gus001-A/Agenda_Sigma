/**
 * v-digitos: el campo solo acepta números (al teclear, pegar o autocompletar).
 * El valor opcional es el máximo de dígitos: v-digitos="10".
 * Funciona sobre <input> o sobre un componente cuyo raíz contenga uno (InputText de PrimeVue).
 */
const campo = (el) => (el.tagName === 'INPUT' ? el : el.querySelector('input'));

export default {
    mounted(el, binding) {
        const input = campo(el);
        if (!input) return;
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('autocomplete', 'off');
        // Se registra después del listener de v-model: si el texto trae otra cosa, se limpia y se vuelve a avisar.
        input.addEventListener('input', () => {
            const max = Number(binding.value) || undefined;
            const limpio = input.value.replace(/\D/g, '').slice(0, max);
            if (limpio !== input.value) {
                input.value = limpio;
                input.dispatchEvent(new Event('input'));
            }
        });
    },
};
