import { reactive } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Estado global de diálogos y panel de detalle. Los componentes se montan una sola vez
 * en AppLayout y cualquier página puede abrirlos con ui.abrir('nombre', datos).
 */
export const ui = reactive({
    cita: null, // id de la cita abierta en el panel lateral
    dialogo: null, // nombre del diálogo abierto
    datos: {}, // carga útil del diálogo
    version: 0, // se incrementa para pedir al panel que recargue
    sideAbierto: false,

    abrirCita(id) { this.cita = id; this.version++; },
    cerrarCita() { this.cita = null; },
    abrir(nombre, datos = {}) { this.dialogo = nombre; this.datos = datos; },
    cerrar() { this.dialogo = null; this.datos = {}; },
    recargarCita() { this.version++; },
});

/** Permisos de acción del usuario actual. */
export function usePermisos() {
    const page = usePage();
    return {
        puede: (accion) => (page.props.auth?.user?.acciones || []).includes(accion),
        rol: () => page.props.auth?.user?.role,
        usuario: () => page.props.auth?.user,
    };
}
