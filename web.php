<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\CodigoPostalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\MensajeController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProspectoController;
use App\Http\Controllers\RecuperarController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VisitaController;
use Illuminate\Support\Facades\Route;

$gestion = 'perfil:Administrador,Supervisor';
$internos = 'perfil:Administrador,Supervisor,Auditor,Promotor';

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');

    // Recuperación de acceso por correo
    Route::post('/olvide-contrasena', [RecuperarController::class, 'enviar'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/restablecer/{token}', [RecuperarController::class, 'formulario'])->name('password.reset');
    Route::post('/restablecer', [RecuperarController::class, 'restablecer'])->middleware('throttle:10,1')->name('password.update');
});

// Presentaciones de productos compartidas por WhatsApp / correo (enlace público por UUID)
Route::get('/p/{uuid}', [DocumentoController::class, 'publico'])->name('documentos.publico');

Route::middleware('auth')->group(function () use ($gestion, $internos) {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    // Mi perfil (usuario autenticado)
    Route::put('/perfil/correo', [PerfilController::class, 'correo'])->middleware('throttle:10,1')->name('perfil.correo');
    Route::put('/perfil/contrasena', [PerfilController::class, 'contrasena'])->middleware('throttle:10,1')->name('perfil.contrasena');

    Route::get('/', DashboardController::class)->name('panel');
    Route::get('/agenda', AgendaController::class)->middleware($internos)->name('agenda');

    // Reportes en pantalla, PDF y Excel
    Route::get('/reportes', [ReporteController::class, 'index'])->middleware($internos)->name('reportes');
    Route::get('/reportes/{tipo}/{formato}', [ReporteController::class, 'exportar'])->middleware($internos)
        ->whereIn('tipo', ['visitas', 'promotores', 'prospectos'])->whereIn('formato', ['pdf', 'excel'])->name('reportes.exportar');

    // Visitas (workflow comercial)
    Route::get('/visitas', [VisitaController::class, 'index'])->name('visitas.index');
    Route::get('/visitas/{visita}/detalle', [VisitaController::class, 'show'])->name('visitas.show');
    Route::post('/visitas', [VisitaController::class, 'store'])->middleware($gestion)->name('visitas.store');
    Route::put('/visitas/{visita}', [VisitaController::class, 'update'])->middleware($gestion)->name('visitas.update');
    Route::delete('/visitas/{visita}', [VisitaController::class, 'destroy'])->middleware($gestion)->name('visitas.destroy');
    Route::post('/visitas/{visita}/avanzar', [VisitaController::class, 'avanzar'])->name('visitas.avanzar');
    Route::post('/visitas/{visita}/cerrar', [VisitaController::class, 'cerrar'])->name('visitas.cerrar');
    Route::post('/visitas/{visita}/ubicacion', [VisitaController::class, 'ubicacion'])->name('visitas.ubicacion');
    Route::post('/visitas/{visita}/foto', [VisitaController::class, 'foto'])->name('visitas.foto');
    Route::post('/visitas/{visita}/observaciones', [VisitaController::class, 'observacion'])->middleware($internos)->name('visitas.observacion');
    Route::get('/evidencias/{evidencia}/archivo', [VisitaController::class, 'archivo'])->name('evidencias.archivo');

    // Prospectos
    Route::get('/prospectos', [ProspectoController::class, 'index'])->middleware($internos)->name('prospectos.index');
    Route::post('/prospectos', [ProspectoController::class, 'store'])->middleware($gestion)->name('prospectos.store');
    Route::put('/prospectos/{prospecto}', [ProspectoController::class, 'update'])->middleware($gestion)->name('prospectos.update');
    Route::delete('/prospectos/{prospecto}', [ProspectoController::class, 'destroy'])->middleware($gestion)->name('prospectos.destroy');

    // Sucursales
    Route::get('/sucursales', [SucursalController::class, 'index'])->middleware('perfil:Administrador,Supervisor,Auditor')->name('sucursales.index');
    Route::post('/sucursales', [SucursalController::class, 'store'])->middleware('perfil:Administrador')->name('sucursales.store');
    Route::put('/sucursales/{sucursal}', [SucursalController::class, 'update'])->middleware('perfil:Administrador')->name('sucursales.update');
    Route::delete('/sucursales/{sucursal}', [SucursalController::class, 'destroy'])->middleware('perfil:Administrador')->name('sucursales.destroy');

    // Usuarios y accesos
    Route::middleware('perfil:Administrador')->group(function () {
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');

        Route::get('/catalogos', [CatalogoController::class, 'index'])->name('catalogos.index');
        Route::post('/catalogos', [CatalogoController::class, 'store'])->name('catalogos.store');
        Route::put('/catalogos/{catalogo}', [CatalogoController::class, 'update'])->name('catalogos.update');
        Route::delete('/catalogos/{catalogo}', [CatalogoController::class, 'destroy'])->name('catalogos.destroy');
    });

    // Productos y servicios
    Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
    Route::post('/productos', [ProductoController::class, 'store'])->middleware('perfil:Administrador')->name('productos.store');
    Route::put('/productos/{producto}', [ProductoController::class, 'update'])->middleware('perfil:Administrador')->name('productos.update');
    Route::delete('/productos/{producto}', [ProductoController::class, 'destroy'])->middleware('perfil:Administrador')->name('productos.destroy');
    Route::post('/productos/{producto}/compartir', [ProductoController::class, 'compartir'])->middleware($internos)->name('productos.compartir');

    // Documentos (expedientes, convenios, brochures, presentaciones)
    Route::get('/documentos/{documento}/archivo', [DocumentoController::class, 'archivo'])->whereNumber('documento')->name('documentos.archivo');
    Route::get('/documentos/{tipo}/{id}', [DocumentoController::class, 'index'])->whereIn('tipo', ['sucursal', 'usuario', 'prospecto', 'producto'])->whereNumber('id')->name('documentos.index');
    Route::post('/documentos/{tipo}/{id}', [DocumentoController::class, 'store'])->whereIn('tipo', ['sucursal', 'usuario', 'prospecto', 'producto'])->whereNumber('id')->name('documentos.store');
    Route::delete('/documentos/{documento}', [DocumentoController::class, 'destroy'])->name('documentos.destroy');

    // Mensajes prospecto ⇄ promotor
    Route::get('/mensajes', [MensajeController::class, 'index'])->middleware('perfil:Promotor,Prospecto')->name('mensajes.index');
    Route::post('/mensajes', [MensajeController::class, 'store'])->middleware('perfil:Promotor,Prospecto')->name('mensajes.store');

    // Bitácora / trazabilidad
    Route::get('/bitacora', [BitacoraController::class, 'index'])->middleware('perfil:Administrador,Supervisor,Auditor')->name('bitacora.index');
    Route::get('/bitacora/registro', [BitacoraController::class, 'registro'])->name('bitacora.registro');
    Route::get('/bitacora/modulo', [BitacoraController::class, 'modulo'])->name('bitacora.modulo');

    // Autocompletado de dirección por código postal
    Route::get('/codigo-postal/{cp}', CodigoPostalController::class)->middleware('throttle:60,1')->name('codigo-postal');
});
