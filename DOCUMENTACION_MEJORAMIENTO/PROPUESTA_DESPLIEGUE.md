# PROPUESTA DE DESPLIEGUE (no ejecutada)

Nada de lo siguiente se ha hecho: requiere autorización y acceso al servidor real, que no se tienen. Complementa DESPLIEGUE_APACHE.md.

## Antes de empezar (puntos de control)
1. **Respaldo** completo de las bases `explora` y `pedigital` y del código actual (la etiqueta `pre-migracion-laravel` ya marca el punto de retorno del repositorio).
2. **Rotar las credenciales de MySQL** expuestas en el código y crear usuarios de mínimo privilegio: lectura en `pedigital`; lectura y escritura solo de lo necesario en `explora` (incluye `perfiles_explora`).
3. Confirmar versiones del servidor: PHP ≥ 8.1 (probado con 8.2) con `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`; MySQL 8; Apache con `mod_rewrite`.
4. Decidir qué pasa con el chat legado (sigue activo y con credenciales antiguas) y con `corrector.html`.

## Pasos
1. Subir el código (rama `migracion-laravel` revisada y fusionada por el responsable).
2. En `laravel/`: `composer install --no-dev --optimize-autoloader`. (Composer avisa de 5 incidencias de seguridad de Laravel 10 aceptadas por decisión del responsable.)
3. Crear `laravel/.env` de producción: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` nueva (`php artisan key:generate`), credenciales nuevas, `SESSION_SECURE_COOKIE=true`, `API_POR_MINUTO` según el colegio (IP compartida).
4. Crear la tabla propia: `php artisan migrate --force` (solo añade `perfiles_explora`; es idempotente; no toca datos existentes).
5. `php artisan config:cache route:cache` y permisos de escritura solo en `storage/` y `bootstrap/cache/`.
6. Apache: vhost con HTTPS, `AllowOverride All` y DocumentRoot en la raíz (ver DESPLIEGUE_APACHE.md); opcional `npm run build:sitio` y superponer `dist/`.
7. Verificar con `curl` la tabla de bloqueos del informe de pruebas (403/404/410 y `/api/sesion`) y recorrer el flujo: iniciar sesión → ficha → quiz → juego → perfil.

## Marcha atrás
- Código: volver a `pre-migracion-laravel`. Los PHP legados vuelven a funcionar (con sus defectos de seguridad).
- Datos: `perfiles_explora` es una tabla aparte; se puede dejar o eliminar (`down()` solo borra si está vacía). Ninguna tabla existente se modifica.
- Las sesiones de Laravel son independientes del legado: no hay estado compartido que revertir.

## Autorizaciones que necesito del responsable
Acceso al servidor y a las bases reales · permiso para crear `perfiles_explora` · decisión sobre el chat legado · confirmación de credenciales nuevas · ventana de despliegue.
