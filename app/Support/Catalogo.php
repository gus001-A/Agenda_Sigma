<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Catálogos de valores fijos del sistema (estados, tipos, roles y permisos).
 * Se comparten con el frontend a través de HandleInertiaRequests.
 */
class Catalogo
{
    public const ESTADOS_CITA = [
        'Disponible' => 'Espacio libre',
        'Agendada' => 'Cita registrada',
        'Confirmada' => 'Paciente confirmó',
        'Anticipo pendiente' => 'Requiere pago',
        'En consulta' => 'Atención en curso',
        'Atendida' => 'Atención concluida',
        'Cancelada' => 'Cita cancelada',
        'Reagendada' => 'Cita trasladada',
        'Bloqueada' => 'Espacio bloqueado',
        'No disponible' => 'Fuera de horario',
    ];

    /** Estados que el usuario puede asignar manualmente desde el detalle de la cita. */
    public const ESTADOS_MANUALES = ['Agendada', 'Confirmada', 'Anticipo pendiente', 'En consulta', 'Atendida'];

    public const ESTADOS_HC = ['Pendiente', 'En proceso', 'Completado', 'Revisado', 'Requiere actualización'];

    public const ESTADOS_ANTICIPO = ['No requerido', 'Pendiente', 'Parcial', 'Pagado', 'Reembolsado'];

    public const TIPOS_CONSULTA = ['Primera consulta', 'Consulta subsecuente', 'Valoración preoperatoria', 'Procedimiento', 'Cirugía', 'Seguimiento', 'Terapia', 'Estudios'];

    /** Tipos de consulta que usan el formato "subsecuente" de la especialidad. */
    public const TIPOS_SUBSECUENTES = ['Consulta subsecuente', 'Seguimiento', 'Terapia'];

    public const TIPOS_BLOQUEO = ['Comida', 'Médico no disponible', 'Reunión', 'Procedimiento', 'Mantenimiento', 'Evento', 'Vacaciones', 'Fin de agenda', 'Otro'];

    public const FORMAS_PAGO = ['Efectivo', 'Tarjeta de débito', 'Tarjeta de crédito', 'Transferencia'];

    public const TIPOS_RECURSO = ['Consultorio', 'Quirófano', 'Sala de procedimientos', 'Sala de juntas', 'Habitación', 'Sala de rehabilitación', 'Otro'];

    public const CANALES = ['WhatsApp', 'SMS', 'Correo electrónico', 'Sistema'];

    public const MOTIVOS_CANCELACION = ['Solicitud del paciente', 'Médico no disponible', 'Paciente no confirmó', 'Cita duplicada', 'Otro'];

    public const TIPOS_CAMPO = [
        'text' => 'Texto corto', 'textarea' => 'Texto largo', 'number' => 'Número', 'date' => 'Fecha', 'time' => 'Hora',
        'yesno' => 'Sí/No', 'single' => 'Selección única', 'multi' => 'Selección múltiple', 'select' => 'Lista desplegable',
        'scale' => 'Escala de valoración', 'allergies' => 'Alergias', 'meds' => 'Medicamentos', 'history' => 'Antecedentes',
        'files' => 'Archivos', 'photo' => 'Fotografías', 'signature' => 'Firma electrónica', 'consent' => 'Consentimiento',
    ];

    public const TIPOS_CON_OPCIONES = ['single', 'multi', 'select', 'history'];

    public const ROLES = ['Administrador', 'Coordinador', 'Recepción', 'Médico', 'Consulta'];

    /** Permisos de acción por rol (independientes del acceso a módulos). */
    public const ACCIONES = [
        'Administrador' => ['cita', 'bloqueo', 'espacio', 'config', 'operativo', 'clinico', 'anticipo', 'verclinico', 'consulta'],
        'Coordinador' => ['cita', 'bloqueo', 'espacio', 'operativo', 'anticipo', 'consulta'],
        'Recepción' => ['cita', 'espacio', 'anticipo', 'consulta'],
        'Médico' => ['clinico', 'verclinico', 'consulta'],
        'Consulta' => [],
    ];

    public const MODULOS = [
        ['k' => 'panel', 'l' => 'Panel', 'i' => 'pi pi-th-large', 'g' => 'Operación', 'ruta' => 'panel', 'c' => '#173a5f'],
        ['k' => 'agenda', 'l' => 'Agenda médica', 'i' => 'pi pi-calendar', 'g' => 'Operación', 'ruta' => 'agenda', 'c' => '#1f9e86'],
        ['k' => 'recepcion', 'l' => 'Recepción del día', 'i' => 'pi pi-desktop', 'g' => 'Operación', 'ruta' => 'recepcion', 'c' => '#2b5480'],
        ['k' => 'disponibilidad', 'l' => 'Próxima disponibilidad', 'i' => 'pi pi-search', 'g' => 'Operación', 'ruta' => 'disponibilidad', 'c' => '#0e7490'],
        ['k' => 'espera', 'l' => 'Lista de espera', 'i' => 'pi pi-clock', 'g' => 'Operación', 'ruta' => 'espera', 'c' => '#e08a1e'],
        ['k' => 'espacios', 'l' => 'Quirófanos y espacios', 'i' => 'pi pi-building', 'g' => 'Operación', 'ruta' => 'espacios', 'c' => '#6b4bc9'],
        ['k' => 'anticipos', 'l' => 'Anticipos', 'i' => 'pi pi-wallet', 'g' => 'Operación', 'ruta' => 'anticipos', 'c' => '#16a34a'],
        ['k' => 'notificaciones', 'l' => 'Notificaciones', 'i' => 'pi pi-bell', 'g' => 'Operación', 'ruta' => 'notificaciones', 'c' => '#db2777'],
        ['k' => 'historias', 'l' => 'Historias clínicas', 'i' => 'pi pi-file-edit', 'g' => 'Clínico', 'ruta' => 'historias', 'c' => '#0891b2'],
        ['k' => 'preconsulta', 'l' => 'Preconsulta médica', 'i' => 'pi pi-heart', 'g' => 'Clínico', 'ruta' => 'preconsulta', 'c' => '#d64545'],
        ['k' => 'formatos', 'l' => 'Formatos clínicos', 'i' => 'pi pi-sliders-h', 'g' => 'Administración', 'ruta' => 'formatos', 'c' => '#7c3aed'],
        ['k' => 'catalogos', 'l' => 'Catálogos', 'i' => 'pi pi-book', 'g' => 'Administración', 'ruta' => 'catalogos', 'c' => '#1c6b78'],
        ['k' => 'reportes', 'l' => 'Reportes', 'i' => 'pi pi-chart-bar', 'g' => 'Administración', 'ruta' => 'reportes', 'c' => '#ea580c'],
        ['k' => 'usuarios', 'l' => 'Usuarios y permisos', 'i' => 'pi pi-users', 'g' => 'Administración', 'ruta' => 'usuarios', 'c' => '#4f46e5'],
        ['k' => 'auditoria', 'l' => 'Auditoría', 'i' => 'pi pi-shield', 'g' => 'Administración', 'ruta' => 'auditoria', 'c' => '#475569'],
    ];

    public const PERMISOS_DEFAULT = [
        'Administrador' => ['panel', 'agenda', 'recepcion', 'disponibilidad', 'espera', 'espacios', 'anticipos', 'notificaciones', 'historias', 'preconsulta', 'formatos', 'catalogos', 'reportes', 'usuarios', 'auditoria'],
        'Coordinador' => ['panel', 'agenda', 'recepcion', 'disponibilidad', 'espera', 'espacios', 'anticipos', 'notificaciones', 'historias', 'catalogos', 'reportes', 'auditoria'],
        'Recepción' => ['panel', 'agenda', 'recepcion', 'disponibilidad', 'espera', 'espacios', 'anticipos', 'notificaciones', 'historias'],
        'Médico' => ['agenda', 'preconsulta'],
        'Consulta' => ['panel', 'agenda', 'reportes'],
    ];

    public const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    public const DIAS_CORTOS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

    public const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public const ETIQUETAS_MENSAJE = [
        'registro' => 'Cita registrada', 'reagenda' => 'Cita reagendada', 'cancelacion' => 'Cita cancelada',
        'r72' => 'Recordatorio 72 h', 'r24' => 'Recordatorio 24 h', 'r0' => 'Recordatorio del día',
        'actualizar' => 'Solicitud de actualización', 'confirmacion' => 'Confirmación recibida', 'anticipo' => 'Anticipo registrado',
    ];

    public static function fechaLarga(string $iso): string
    {
        $d = Carbon::parse($iso);

        return self::DIAS[$d->dayOfWeek].' '.$d->day.' de '.self::MESES[$d->month - 1].' de '.$d->year;
    }

    public static function fechaCorta(string $iso): string
    {
        $d = Carbon::parse($iso);

        return self::DIAS_CORTOS[$d->dayOfWeek].' '.$d->day.' '.mb_substr(self::MESES[$d->month - 1], 0, 3);
    }

    public static function dinero(float|int|string|null $n): string
    {
        return '$'.number_format(round((float) $n), 0, '.', ',');
    }
}
