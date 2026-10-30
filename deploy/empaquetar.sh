#!/usr/bin/env bash
# Arma el paquete para subir la agenda a un hosting compartido en una subcarpeta
# (por defecto https://ric.sistema-sicad.site/agenda). Ver deploy/LEEME.md.
#
#   deploy/empaquetar.sh instalar     primera vez: incluye .env y la base SQLite ya creada
#   deploy/empaquetar.sh actualizar   solo código: conserva el .env y la base del servidor
#
# Variables opcionales:
#   APP_URL=https://ric.sistema-sicad.site/agenda   URL pública del sistema
#   CON_DEMO=1        en la instalación carga los datos de demostración (por defecto solo
#                     catálogos, permisos y formatos)
#   COMPOSER_FLAGS    opciones extra para composer install
set -euo pipefail

MODO="${1:-}"
if [[ "$MODO" != "instalar" && "$MODO" != "actualizar" ]]; then
    sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
fi

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
APP_URL="${APP_URL:-https://ric.sistema-sicad.site/agenda}"
RUTA="/$(echo "$APP_URL" | sed -E 's#^https?://[^/]+/?##; s#/$##')"   # /agenda
CARPETA="$(basename "$RUTA")"                                        # agenda
SALIDA="$RAIZ/dist"
WEB="$SALIDA/$CARPETA"      # contenido de la carpeta pública /agenda
APP="$WEB/laravel"          # el proyecto Laravel, protegido por .htaccess

cd "$RAIZ"
echo "» Empaquetando para $APP_URL (carpeta $CARPETA/, modo $MODO)"
rm -rf "$WEB" "$SALIDA/$CARPETA-$MODO.zip"
mkdir -p "$APP"

# 1. Frontend compilado con la subcarpeta como base (fuentes, íconos y páginas cargan desde /agenda/build).
[[ -d node_modules ]] || npm ci --no-audit --no-fund
ASSET_URL="$RUTA" npm run build

# 2. Archivos del proyecto (los que no ignora git, tal como están en el directorio de trabajo).
git ls-files -z --cached --others --exclude-standard | tar --null -T - -cf - | tar -xf - -C "$APP"
(cd "$APP" && rm -rf public tests deploy .claude node_modules resources/js resources/css \
    package.json package-lock.json vite.config.js postcss.config.js tailwind.config.js jsconfig.json \
    phpunit.xml .editorconfig .gitattributes .gitignore)

# 3. Dependencias de PHP de producción.
composer install -d "$APP" --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist ${COMPOSER_FLAGS:-}
find "$APP/vendor" -type d -name .git -prune -exec rm -rf {} +
rm -f "$APP/bootstrap/cache/"*.php

# 4. Carpeta pública: lo que estaba en public/ con las rutas apuntando a laravel/.
cp -r public/. "$WEB/"
sed -e "s#__DIR__.'/../#__DIR__.'/laravel/#g" \
    -e "s#^\$app->handleRequest#\$app->usePublicPath(__DIR__);\n\n\$app->handleRequest#" public/index.php > "$WEB/index.php"
cat > "$WEB/.htaccess" <<'HTACCESS'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # El proyecto Laravel (código, .env y base de datos) nunca se sirve.
    RewriteRule ^laravel(/|$) - [F,L]
    # Ni archivos ocultos (.env, .git, .htaccess).
    RewriteRule (^|/)\. - [F,L]

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Handle X-XSRF-Token Header
    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTACCESS
cat > "$APP/.htaccess" <<'HTACCESS'
# Segunda barrera: nada de esta carpeta es accesible desde el navegador.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
HTACCESS

# 5. Primera instalación: .env de producción con llave nueva y base SQLite lista.
if [[ "$MODO" == "instalar" ]]; then
    sed "s#^APP_URL=.*#APP_URL=$APP_URL#; s#^SESSION_PATH=.*#SESSION_PATH=$RUTA#" deploy/env.produccion > "$APP/.env"
    (
        cd "$APP"
        php artisan key:generate --force --no-interaction
        touch database/database.sqlite
        php artisan migrate --force --no-interaction
        if [[ "${CON_DEMO:-0}" == "1" ]]; then
            php artisan db:seed --force --no-interaction
        else
            php artisan db:seed --class=InstalacionSeeder --force --no-interaction
        fi
        rm -f storage/logs/*.log
    )
fi

# 6. Permisos habituales en hosting compartido.
find "$WEB" -type d -exec chmod 755 {} +
find "$WEB" -type f -exec chmod 644 {} +
chmod -R 775 "$APP/storage" "$APP/bootstrap/cache" "$APP/database"

if command -v zip >/dev/null; then
    (cd "$SALIDA" && zip -qr "$CARPETA-$MODO.zip" "$CARPETA")
else
    # Git Bash en Windows no trae zip: se arma con PHP (extensión zip).
    (cd "$SALIDA" && php -r '
        [, $dir, $zip] = $argv;
        $z = new ZipArchive; $z->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) or exit(1);
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $f) { $n = str_replace("\\", "/", $f->getPathname()); $f->isDir() ? $z->addEmptyDir($n) : $z->addFile($n, $n); }
        $z->close();' "$CARPETA" "$CARPETA-$MODO.zip")
fi
# Página de inicio del subdominio con la lista de sistemas (va en la raíz, junto a survicos/, condominios/…).
cp deploy/index-subdominio.html "$SALIDA/index.html"
echo
echo "✔ Paquete listo: dist/$CARPETA-$MODO.zip ($(du -h "$SALIDA/$CARPETA-$MODO.zip" | cut -f1))"
echo "  Descomprímelo dentro de la carpeta del subdominio para que quede como .../$CARPETA/index.php"
echo "  dist/index.html es la página de inicio del subdominio con la tarjeta de la agenda"
