# Subir la agenda al hosting compartido

URL final: **https://ric.sistema-sicad.site/agenda/**

El subdominio `ric.sistema-sicad.site` ya tiene otros sistemas (`survicos/`, `condominios/`, `ventrack/`).
La agenda va en su propia carpeta `agenda/` y no toca a los demás:

```
ric.sistema-sicad.site/          ← carpeta raíz del subdominio en el hosting
├── index.html                   ← página "¿A qué sistema quieres entrar?" (ahora con la Agenda)
├── survicos/  condominios/  ventrack/
└── agenda/
    ├── index.php  .htaccess  build/  favicon.svg      ← lo único que se sirve
    └── laravel/                                       ← código, .env y base SQLite (bloqueado)
        └── database/database.sqlite
```

`laravel/` queda bloqueada dos veces: por `agenda/.htaccess` y por su propio `.htaccess`
(`https://ric.sistema-sicad.site/agenda/laravel/.env` responde 403).

## Requisitos del hosting

- PHP **8.2 o superior** con `pdo_sqlite`, `mbstring`, `openssl`, `bcmath`, `fileinfo`, `ctype`, `xml`
  (en cPanel: *Seleccionar versión de PHP* → Extensiones).
- Apache con `mod_rewrite` (lo normal en cPanel).

## 1. Armar el paquete (en tu computadora)

Necesitas `php`, `composer`, `node`/`npm` y Git Bash (en Windows). Desde la carpeta del proyecto:

```bash
ADMIN_PASSWORD='una-contraseña-segura' deploy/empaquetar.sh instalar
```

Genera en `dist/`:

- `agenda-instalar.zip`: sistema completo con `.env` de producción (llave nueva) y la base SQLite
  ya creada con permisos por rol, especialidades, formatos clínicos y el administrador
  `gustavo@sigma.com` con la contraseña que pusiste en `ADMIN_PASSWORD` (si no pones ninguna, se
  genera una y se muestra en pantalla).
- `index.html`: la página de inicio del subdominio con la tarjeta de la Agenda.

Para subirlo con los datos de demostración (médicos, pacientes, citas y usuarios con `demo1234`):
`CON_DEMO=1 deploy/empaquetar.sh instalar`, y si quieres los botones de acceso rápido en el login
pon `ACCESOS_DEMO=true` en `agenda/laravel/.env` ya en el servidor.

## 2. Subirlo (cPanel → Administrador de archivos)

1. Entra a la carpeta raíz del subdominio (donde están `survicos/`, `condominios/`, `ventrack/`).
2. Sube `agenda-instalar.zip` ahí y usa **Extraer**. Debe quedar `agenda/index.php`.
3. Sube `index.html` y reemplaza el que ya existe (solo agrega la tarjeta de la Agenda).
4. Borra el `.zip` del servidor.
5. Revisa que `agenda/laravel/storage`, `agenda/laravel/bootstrap/cache` y `agenda/laravel/database`
   tengan permisos **775** (y `database.sqlite` **664**).

Abre https://ric.sistema-sicad.site/agenda/ y entra con el administrador.

## 3. Correo y recordatorios

- **Correo** (recuperar contraseña y avisos a pacientes): crea una cuenta de correo en cPanel y llena
  `MAIL_*` en `agenda/laravel/.env`. Cuando funcione, cambia `MENSAJERIA_CORREO=mail`.
- **Recordatorios automáticos** (72 h / 24 h / día de la cita): en cPanel → *Trabajos de Cron*, cada hora
  (`0 * * * *`):

  ```
  /usr/local/bin/php /home/USUARIO/CARPETA_DEL_SUBDOMINIO/agenda/laravel/artisan schedule:run >> /dev/null 2>&1
  ```

  Cambia `USUARIO` y la carpeta por los tuyos (la ruta completa aparece en el Administrador de
  archivos). Si el hosting usa otra ruta de PHP, cPanel la muestra en la misma pantalla de Cron.

## 4. Actualizar después de cambios

```bash
deploy/empaquetar.sh actualizar
```

`agenda-actualizar.zip` trae solo el código: **no** incluye `.env` ni `database.sqlite`, así que al
extraerlo sobre `agenda/` se conservan la configuración y los datos. Si la actualización trae
migraciones nuevas, córrelas una vez:

- Con *Terminal* de cPanel: `cd ~/CARPETA_DEL_SUBDOMINIO/agenda/laravel && php artisan migrate --force`
- Sin terminal: crea un Cron de una sola vez con
  `/usr/local/bin/php /home/USUARIO/CARPETA_DEL_SUBDOMINIO/agenda/laravel/artisan migrate --force`
  y bórralo cuando haya corrido.

Respaldo: basta con descargar `agenda/laravel/database/database.sqlite` y `agenda/laravel/.env`.

## Detalles de la configuración (`deploy/env.produccion`)

| Variable | Por qué |
|---|---|
| `APP_URL=https://ric.sistema-sicad.site/agenda` | Enlaces de los correos/SMS al portal del paciente. |
| `SESSION_COOKIE=sigma24_agenda_session`, `SESSION_PATH=/agenda` | El subdominio lo comparten otros sistemas: la sesión de la agenda no choca con la de ellos. |
| `QUEUE_CONNECTION=sync` | En hosting compartido no hay un proceso de colas corriendo. |
| `ACCESOS_DEMO=false` | Sin botones de usuarios de demostración en producción. |
| `APP_DEBUG=false` | Nunca mostrar errores internos en producción. |

Si algo falla, el detalle queda en `agenda/laravel/storage/logs/`.
