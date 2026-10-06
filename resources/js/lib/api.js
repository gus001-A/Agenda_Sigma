import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

/** Cola de avisos consumida por <Toast> en el layout. */
export const avisos = reactive({ lista: [] });
export function avisar(texto, tipo = 'success', accion = null) {
    avisos.lista.push({ id: Date.now() + Math.random(), texto, tipo, accion });
}

/** Mensaje legible a partir de un error de axios (validación 422, permisos 403, etc.). */
export function mensajeError(e) {
    const d = e?.response?.data;
    if (d?.errors) return Object.values(d.errors).flat()[0];
    if (d?.message) return d.message;
    if (e?.response?.status === 419) return 'La sesión expiró. Recarga la página.';
    return 'Ocurrió un error. Intenta de nuevo.';
}

async function llamar(metodo, url, datos, opciones = {}) {
    try {
        const r = await axios({ method: metodo, url, ...(metodo === 'get' ? { params: datos } : { data: datos }), ...opciones });
        return r.data;
    } catch (e) {
        if (!opciones.silencioso) avisar(mensajeError(e), 'error');
        throw e;
    }
}

export const api = {
    get: (url, params, o) => llamar('get', url, params, o),
    post: (url, data, o) => llamar('post', url, data, o),
    put: (url, data, o) => llamar('put', url, data, o),
    patch: (url, data, o) => llamar('patch', url, data, o),
    del: (url, data, o) => llamar('delete', url, data, o),
};

/** Recarga los datos de la página actual sin perder scroll ni estado local. */
export function refrescar(only) {
    router.reload({ preserveScroll: true, preserveState: true, ...(only ? { only } : {}) });
}

/** Navega a la misma página cambiando parámetros de consulta (filtros). */
export function filtrar(ruta, params) {
    const limpio = Object.fromEntries(Object.entries(params).filter(([, v]) => v !== null && v !== '' && v !== undefined && v !== false));
    router.get(route(ruta), limpio, { preserveScroll: true, preserveState: true, replace: true });
}
