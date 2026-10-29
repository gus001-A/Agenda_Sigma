<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AnticiposController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\CatalogosController;
use App\Http\Controllers\CitasController;
use App\Http\Controllers\DisponibilidadController;
use App\Http\Controllers\EspaciosController;
use App\Http\Controllers\EsperaController;
use App\Http\Controllers\FormatosController;
use App\Http\Controllers\HistoriasController;
use App\Http\Controllers\NotificacionesController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PreconsultaController;
use App\Http\Controllers\RecepcionController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\UsuariosController;
use App\Support\Catalogo;
use Illuminate\Support\Facades\Route;

// ---------------- Portal del paciente (enlace seguro, público) ----------------
Route::prefix('p/{token}')->name('portal.')->middleware('throttle:60,1')->group(function () {
    Route::get('/', [PortalController::class, 'show'])->name('show');
    Route::post('confirmar', [PortalController::class, 'confirmar'])->name('confirmar');
    Route::post('guardar', [PortalController::class, 'guardar'])->name('guardar');
    Route::post('enviar', [PortalController::class, 'enviar'])->name('enviar');
    Route::post('reutilizar', [PortalController::class, 'reutilizar'])->name('reutilizar');
    Route::post('archivos', [PortalController::class, 'subirArchivo'])->name('archivos.store');
    Route::delete('archivos/{archivo}', [PortalController::class, 'borrarArchivo'])->name('archivos.destroy');
});

// ---------------- Sistema interno ----------------
Route::middleware(['auth', 'activo'])->group(function () {
    // Inicio: primer módulo permitido para el rol.
    Route::get('/', function () {
        $u = auth()->user();
        $m = collect(Catalogo::MODULOS)->first(fn ($m) => $u->canModule($m['k']));

        return $m ? redirect()->route($m['ruta']) : abort(403, 'Tu rol no tiene módulos asignados.');
    })->name('inicio');

    Route::get('panel', PanelController::class)->middleware('modulo:panel')->name('panel');
    Route::get('agenda', AgendaController::class)->middleware('modulo:agenda')->name('agenda');
    Route::get('recepcion', RecepcionController::class)->middleware('modulo:recepcion')->name('recepcion');
    Route::get('disponibilidad', DisponibilidadController::class)->middleware('modulo:disponibilidad')->name('disponibilidad');
    Route::get('espera', [EsperaController::class, 'index'])->middleware('modulo:espera')->name('espera');
    Route::get('espacios', [EspaciosController::class, 'index'])->middleware('modulo:espacios')->name('espacios');
    Route::get('anticipos', AnticiposController::class)->middleware('modulo:anticipos')->name('anticipos');
    Route::get('notificaciones', [NotificacionesController::class, 'index'])->middleware('modulo:notificaciones')->name('notificaciones');
    Route::get('historias', HistoriasController::class)->middleware('modulo:historias')->name('historias');
    Route::get('preconsulta', PreconsultaController::class)->middleware(['modulo:preconsulta', 'accion:verclinico'])->name('preconsulta');
    Route::get('formatos', [FormatosController::class, 'index'])->middleware('modulo:formatos')->name('formatos');
    Route::get('catalogos', [CatalogosController::class, 'index'])->middleware('modulo:catalogos')->name('catalogos');
    Route::get('reportes', [ReportesController::class, 'index'])->middleware('modulo:reportes')->name('reportes');
    Route::get('reportes/exportar', [ReportesController::class, 'exportar'])->middleware('modulo:reportes')->name('reportes.exportar');
    Route::get('usuarios', [UsuariosController::class, 'index'])->middleware('modulo:usuarios')->name('usuarios');
    Route::get('auditoria', AuditoriaController::class)->middleware('modulo:auditoria')->name('auditoria');

    // ---------------- Acciones (JSON) ----------------
    Route::prefix('api')->name('api.')->group(function () {
        // Citas
        Route::get('citas/{cita}', [CitasController::class, 'show'])->name('citas.show');
        Route::post('citas', [CitasController::class, 'store'])->middleware('accion:cita')->name('citas.store');
        Route::put('citas/{cita}', [CitasController::class, 'update'])->middleware('accion:cita')->name('citas.update');
        Route::post('citas/{cita}/confirmar', [CitasController::class, 'confirmar'])->middleware('accion:cita')->name('citas.confirmar');
        Route::post('citas/{cita}/estado', [CitasController::class, 'estado'])->middleware('accion:cita|consulta')->name('citas.estado');
        Route::post('citas/{cita}/pago', [CitasController::class, 'pagar'])->middleware('accion:anticipo')->name('citas.pago');
        Route::post('citas/{cita}/reembolso', [CitasController::class, 'reembolsar'])->middleware('accion:anticipo')->name('citas.reembolso');
        Route::post('citas/{cita}/cancelar', [CitasController::class, 'cancelar'])->middleware('accion:cita')->name('citas.cancelar');
        Route::post('citas/{cita}/reagendar', [CitasController::class, 'reagendar'])->middleware('accion:cita')->name('citas.reagendar');
        Route::post('citas/{cita}/finalizar', [CitasController::class, 'finalizar'])->middleware('accion:consulta')->name('citas.finalizar');
        Route::post('citas/{cita}/reenviar', [CitasController::class, 'reenviar'])->middleware('accion:cita')->name('citas.reenviar');
        Route::get('citas/{cita}/mensaje', [CitasController::class, 'mensaje'])->middleware('accion:cita')->name('citas.mensaje');
        Route::post('citas/{cita}/mensaje', [CitasController::class, 'registrarMensaje'])->middleware('accion:cita')->name('citas.mensaje.store');
        Route::get('citas/{cita}/historia', [CitasController::class, 'historia'])->middleware('accion:verclinico')->name('citas.historia');
        Route::post('citas/{cita}/revisada', [CitasController::class, 'revisada'])->middleware('accion:clinico')->name('citas.revisada');
        Route::post('citas/{cita}/actualizacion', [CitasController::class, 'actualizacion'])->middleware('accion:clinico')->name('citas.actualizacion');
        Route::get('archivos/{archivo}', [CitasController::class, 'archivo'])->middleware('accion:verclinico')->name('archivos.show');

        // Auxiliares de agenda
        Route::get('agenda/horarios', [CitasController::class, 'horarios'])->name('agenda.horarios');
        Route::get('agenda/proxima', [CitasController::class, 'proxima'])->name('agenda.proxima');
        Route::get('agenda/formato', [CitasController::class, 'formato'])->name('agenda.formato');
        Route::get('pacientes/buscar', [CitasController::class, 'buscarPacientes'])->name('pacientes.buscar');

        // Pacientes
        Route::post('pacientes', [CatalogosController::class, 'guardarPaciente'])->middleware('accion:cita')->name('pacientes.store');
        Route::put('pacientes/{paciente}', [CatalogosController::class, 'guardarPaciente'])->middleware('accion:cita')->name('pacientes.update');
        Route::get('pacientes/{paciente}/historial', [CatalogosController::class, 'historialPaciente'])->name('pacientes.historial');

        // Lista de espera
        Route::post('espera', [EsperaController::class, 'store'])->middleware('accion:cita')->name('espera.store');
        Route::patch('espera/{espera}', [EsperaController::class, 'estado'])->middleware('accion:cita')->name('espera.estado');
        Route::get('espera/{espera}/espacios', [EsperaController::class, 'espacios'])->name('espera.espacios');
        Route::get('espera-candidatos', [EsperaController::class, 'candidatos'])->name('espera.candidatos');

        // Bloqueos
        Route::get('bloqueos/{bloqueo}', [EspaciosController::class, 'verBloqueo'])->name('bloqueos.show');
        Route::post('bloqueos', [EspaciosController::class, 'guardarBloqueo'])->middleware('accion:bloqueo')->name('bloqueos.store');
        Route::delete('bloqueos/{bloqueo}', [EspaciosController::class, 'eliminarBloqueo'])->middleware('accion:bloqueo')->name('bloqueos.destroy');

        // Reservaciones de espacios
        Route::get('reservaciones/{reservacion}', [EspaciosController::class, 'verReserva'])->name('reservaciones.show');
        Route::post('reservaciones', [EspaciosController::class, 'guardarReserva'])->middleware('accion:espacio')->name('reservaciones.store');
        Route::put('reservaciones/{reservacion}', [EspaciosController::class, 'guardarReserva'])->middleware('accion:espacio')->name('reservaciones.update');
        Route::post('reservaciones/{reservacion}/cancelar', [EspaciosController::class, 'cancelarReserva'])->middleware('accion:espacio')->name('reservaciones.cancelar');

        // Notificaciones
        Route::post('recordatorios/ejecutar', [NotificacionesController::class, 'ejecutar'])->middleware('accion:cita')->name('recordatorios.ejecutar');
        Route::put('recordatorios/{regla}', [NotificacionesController::class, 'guardarRecordatorio'])->middleware('accion:config')->name('recordatorios.update');
        Route::put('canales', [NotificacionesController::class, 'guardarCanales'])->middleware('accion:config')->name('canales.update');

        // Formatos clínicos
        Route::middleware('accion:config')->group(function () {
            Route::post('formatos', [FormatosController::class, 'store'])->name('formatos.store');
            Route::put('formatos/{formato}', [FormatosController::class, 'update'])->name('formatos.update');
            Route::put('formatos/{formato}/borrador', [FormatosController::class, 'borrador'])->name('formatos.borrador');
            Route::delete('formatos/{formato}/borrador', [FormatosController::class, 'descartar'])->name('formatos.descartar');
            Route::post('formatos/{formato}/publicar', [FormatosController::class, 'publicar'])->name('formatos.publicar');

            // Catálogos de configuración
            Route::post('especialidades', [CatalogosController::class, 'guardarEspecialidad'])->name('especialidades.store');
            Route::put('especialidades/{especialidad}', [CatalogosController::class, 'guardarEspecialidad'])->name('especialidades.update');
            Route::post('medicos', [CatalogosController::class, 'guardarMedico'])->name('medicos.store');
            Route::put('medicos/{medico}', [CatalogosController::class, 'guardarMedico'])->name('medicos.update');

            // Usuarios y permisos
            Route::post('usuarios', [UsuariosController::class, 'store'])->name('usuarios.store');
            Route::put('usuarios/{usuario}', [UsuariosController::class, 'update'])->name('usuarios.update');
            Route::post('permisos', [UsuariosController::class, 'permiso'])->name('permisos.update');
        });
        Route::post('espacios', [CatalogosController::class, 'guardarEspacio'])->middleware('accion:operativo')->name('espacios.store');
        Route::put('espacios/{espacio}', [CatalogosController::class, 'guardarEspacio'])->middleware('accion:operativo')->name('espacios.update');
    });
});

require __DIR__.'/auth.php';
