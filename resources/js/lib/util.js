// Utilidades de fecha, dinero y clases de estado (port del prototipo).
export const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
export const DSH = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
export const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

export const pad = (n) => String(n).padStart(2, '0');
export const toISO = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
export const parseISO = (s) => { const [y, m, d] = String(s).slice(0, 10).split('-').map(Number); return new Date(y, m - 1, d); };
export const addDays = (s, n) => { const d = parseISO(s); d.setDate(d.getDate() + n); return toISO(d); };
export const dow = (s) => parseISO(s).getDay();
export const tm = (s) => { const [h, m] = String(s).split(':').map(Number); return h * 60 + (m || 0); };
export const mt = (m) => `${pad(Math.floor(m / 60))}:${pad(m % 60)}`;
export const mondayOf = (s) => { const d = dow(s); return addDays(s, d === 0 ? -6 : 1 - d); };
export const hoyISO = () => toISO(new Date());

export const fmtD = (s) => { if (!s) return ''; const d = parseISO(s); return `${DSH[d.getDay()]} ${d.getDate()} ${MESES[d.getMonth()].slice(0, 3)}`; };
export const fmtDL = (s) => { if (!s) return ''; const d = parseISO(s); return `${DIAS[d.getDay()]} ${d.getDate()} de ${MESES[d.getMonth()]} de ${d.getFullYear()}`; };
export const fmtDS = (s) => { if (!s) return ''; const d = parseISO(s); return `${d.getDate()} ${MESES[d.getMonth()].slice(0, 3)} ${d.getFullYear()}`; };
export const fmtStamp = (iso) => { if (!iso) return ''; const d = new Date(iso); return `${d.getDate()} ${MESES[d.getMonth()].slice(0, 3)} ${pad(d.getHours())}:${pad(d.getMinutes())}`; };
export const money = (n) => '$' + Math.round(Number(n || 0)).toLocaleString('es-MX');
export const initials = (n) => String(n || '').replace(/^(Dr\.|Dra\.|Lic\.|Psic\.)\s*/, '').split(' ').slice(0, 2).map((x) => x[0] || '').join('');
export const ageOf = (b) => { if (!b) return ''; const d = parseISO(b), t = new Date(); let a = t.getFullYear() - d.getFullYear(); if (t.getMonth() < d.getMonth() || (t.getMonth() === d.getMonth() && t.getDate() < d.getDate())) a--; return a; };

export const CLASE_ESTADO = { Disponible: 's-disp', Agendada: 's-agen', Confirmada: 's-conf', 'Anticipo pendiente': 's-anti', 'En consulta': 's-enco', Atendida: 's-aten', Cancelada: 's-canc', Reagendada: 's-reag', Bloqueada: 's-bloq', 'No disponible': 's-nodi' };
export const CLASE_HC = { Pendiente: 'hpen', 'En proceso': 'hpro', Completado: 'hcom', Revisado: 'hrev', 'Requiere actualización': 'hupd' };
export const CLASE_DEP = { 'No requerido': 'dnor', Pendiente: 'dpen', Parcial: 'dpar', Pagado: 'dpag', Reembolsado: 'dree' };
export const CLASE_ESPERA = { 'En espera': 'hpen', Contactado: 'hpro', Agendado: 'hcom', Descartado: 's-aten' };
export const CLASE_MENSAJE = { Enviado: 'hpro', Entregado: 'hrev', 'Leído': 'hcom', Error: 'hupd' };
export const COLOR_HC = { Pendiente: '#e08a1e', 'En proceso': '#2b5480', Completado: '#1f9e86', Revisado: '#173a5f', 'Requiere actualización': '#d64545' };

/** Convierte Date <-> 'YYYY-MM-DD' para DatePicker. */
export const dateToISO = (d) => (d instanceof Date && !isNaN(d) ? toISO(d) : null);
export const isoToDate = (s) => (s ? parseISO(s) : null);

/** Opciones {label, value} a partir de una lista. */
export const opciones = (lista, label = 'name', value = 'id') => (lista || []).map((x) => (typeof x === 'object' ? { label: x[label], value: x[value] } : { label: x, value: x }));
