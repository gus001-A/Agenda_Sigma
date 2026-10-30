# SIGMA24 · Agenda, preconsulta e historias clínicas

Sistema integral de agenda médica, espacios (quirófanos, consultorios, salas), anticipos, lista de espera,
notificaciones y **historia clínica digital previa a la consulta** para Clínica San Antonio.

**Stack:** Laravel 12 · Inertia.js · Vue 3 · PrimeVue 4 (tema Aura personalizado) · Tailwind · SQLite.

## Instalación local

```bash
composer install
npm install
cp .env.example .env   # ajustar APP_URL
php artisan key:generate
touch database/database.sqlite   # base de datos SQLite (si no existe)
php artisan migrate --seed
npm run build
php artisan serve --port=8030
```

Recordatorios automáticos (72 h / 24 h / día de la cita), cada hora:

```bash
php artisan schedule:work      # en producción: cron con "php artisan schedule:run"
```

## Publicar en el hosting (https://ric.sistema-sicad.site/agenda/)

```bash
ADMIN_PASSWORD='una-contraseña-segura' deploy/empaquetar.sh instalar   # primera vez
deploy/empaquetar.sh actualizar                                        # cambios posteriores
```

Pasos para subirlo, Cron de recordatorios y correo: [`deploy/LEEME.md`](deploy/LEEME.md).

## Usuarios de demostración (contraseña `demo1234`)

| Rol | Correo |
|---|---|
| Administrador | cortiz@clinicasanantonio.mx |
| Coordinador | mfuentes@clinicasanantonio.mx |
| Recepción | atorres@clinicasanantonio.mx |
| Médico | medico1@ … medico9@clinicasanantonio.mx |
| Consulta | contraloria@clinicasanantonio.mx |

## Módulos

| Área | Módulo | Ruta |
|---|---|---|
| Operación | Panel del día (KPIs) | `/panel` |
| | Agenda médica (día / semana / mes, filtros, bloqueos) | `/agenda` |
| | Recepción del día | `/recepcion` |
| | Próxima disponibilidad | `/disponibilidad` |
| | Lista de espera (candidatos al liberarse un horario) | `/espera` |
| | Quirófanos y espacios (línea de tiempo + validación de reservaciones) | `/espacios` |
| | Anticipos (pagos parciales, reembolsos) | `/anticipos` |
| | Notificaciones y recordatorios | `/notificaciones` |
| Clínico | Historias clínicas (seguimiento del llenado) | `/historias` |
| | Preconsulta médica | `/preconsulta` |
| Administración | Constructor de formatos clínicos con versionado | `/formatos` |
| | Catálogos: especialidades, médicos y horarios, espacios, pacientes | `/catalogos` |
| | Reportes (+ exportación CSV) | `/reportes` |
| | Usuarios y matriz de permisos por módulo | `/usuarios` |
| | Auditoría | `/auditoria` |
| Paciente | Portal por enlace seguro (confirmar, llenar por secciones, guardar avance, adjuntar, firmar, enviar) | `/p/{token}` |

## Reglas de negocio implementadas (propuesta §44)

- La disponibilidad se calcula de las reglas de horario del médico + citas + bloqueos (`App\Services\Agenda`).
- No se registra una cita sobre un horario ocupado, bloqueado o fuera de agenda (con bloqueo de fila para concurrencia).
- No se crea un bloqueo sobre citas/reservaciones existentes.
- Reservaciones de espacios validan: recurso activo, horario permitido, traslapes, bloqueos, consultas asignadas y agenda del médico.
- El formato clínico se asigna por especialidad + tipo de consulta (exacto → subsecuente/primera vez de la especialidad → general).
- Publicar un formato crea una versión nueva; las historias contestadas conservan su versión.
- Reagendar conserva anticipos, respuestas, archivos e historial; cancelar libera el horario y propone candidatos de la lista de espera.
- Acceso a contenido clínico solo para roles autorizados; el médico solo ve a sus pacientes; consulta de historias y documentos queda en auditoría.

## Integraciones pendientes de definir (etapa técnica)

- **WhatsApp / SMS:** `config/mensajeria.php` (driver `log` por defecto). Agregar el proveedor en `App\Services\Mensajeria::despachar()`.
- **Correo:** `MENSAJERIA_CORREO=mail` usa el mailer de Laravel.
- **SIGMA24:** los pacientes se identifican por `sigma_id` (único); la sincronización con el expediente existente se conecta en `Patient`.
- **Base de datos:** la agenda usa SQLite (`database/database.sqlite`). Solo la importación desde la base heredada de SIGMA (`sigma:importar-*`) lee de MySQL mediante la conexión `sigma` (`SIGMA_DB_*`).

## Pruebas

```bash
php artisan test
```
