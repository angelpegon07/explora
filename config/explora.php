<?php

return [
    /*
     * El frontend actual (mapplic/js/quiz.js) corrige las respuestas EN EL NAVEGADOR, por lo que necesita recibir
     * `correcta` / `respuesta`. Es el defecto SEC-06 y se mantiene SOLO de forma transitoria: al migrar el cliente
     * a verificación en servidor se pondrá QUIZ_INCLUIR_RESPUESTAS=false.
     */
    'quiz_incluir_respuestas' => (bool) env('QUIZ_INCLUIR_RESPUESTAS', true),

    /*
     * Cantidad de avatares disponibles (imágenes juegos/images/avatars/1.png … N.png). Un avatar válido es un
     * entero entre 1 y este valor (ver App\Http\Requests\CambiarAvatarRequest).
     */
    'avatares_max' => (int) env('AVATARES_MAX', 35),

    /*
     * Juegos que pueden guardar resultados: los textos EXACTOS que envía el frontend (con tildes y mayúsculas), tal
     * como aparecen en las llamadas a guardar_resultado() de juegos/. Un juego nuevo debe añadirse aquí.
     */
    'juegos' => [
        'laberinto', 'Sopa de letras', 'Completa la oración', 'Arrastra y suelta',
        'Rompecabezas', 'Ahorcado', 'Pinta la bandera', 'Adivina la bandera',
    ],

    /* Niveles de dificultad que usan los juegos («Normal» lo usan los juegos de banderas, que no tienen niveles). */
    'niveles' => ['Principiante', 'Intermedio', 'Experto', 'Normal'],

    /*
     * Rangos aceptados al guardar un resultado (SEC-06). Son límites GENEROSOS: el puntaje y el tiempo los calcula el
     * navegador, así que el servidor solo puede descartar valores imposibles, no garantizar que sean honestos.
     * Puntaje: el mayor observado es 10 por pregunta en «Completa la oración». Tiempo: en segundos (4 horas).
     */
    'puntaje_max' => 1000,
    'tiempo_max' => 14400,

    /*
     * Peticiones por minuto que admite el grupo `api` (lectura pública y sesión), por usuario o por IP. Las aulas
     * comparten una misma IP por NAT, así que 60 (valor anterior) cortaba a un curso entero; por eso el valor
     * por defecto es 300 y se ajusta con API_POR_MINUTO.
     */
    'api_por_minuto' => (int) env('API_POR_MINUTO', 300),

    /* Resultados que puede guardar por minuto cada usuario (o cada IP, en el modo invitado). */
    'resultados_por_minuto' => (int) env('RESULTADOS_POR_MINUTO', 10),

    /* Máximo de filas que devuelve el historial de un usuario (las más recientes). */
    'historial_max' => 500,

    /*
     * Hosts ADICIONALES que se aceptan en la cabecera `Host` (además del de APP_URL), separados por comas en
     * TRUSTED_HOSTS: nombres exactos como `www.explora.edu.co` (sin esquema ni puerto; no son expresiones regulares).
     * Ver App\Http\Middleware\TrustHosts. Se lee aquí (y no con env() en el middleware) para que funcione con
     * `php artisan config:cache`.
     */
    'hosts_confiables' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_HOSTS', ''))))),

    /*
     * Imágenes de las fichas fuera de la base (Fase 16A; comando `php artisan explora:externalizar-imagenes`).
     * `contenido_dir`: carpeta donde el comando guarda los archivos (debe quedar DENTRO de `public/`, para que Apache los
     * sirva; se valida al ejecutar). `contenido_url`: prefijo público que queda en el HTML de la base (raíz del sitio, sin
     * barra final). El nombre de cada archivo es el sha256 de su contenido: `<dir>/<2 primeros hex>/<sha256>.<ext>`.
     * Los archivos NO se versionan (`/public/contenido/` está en .gitignore): se generan a partir de los datos reales.
     */
    'contenido_dir' => public_path('contenido/fichas'),
    'contenido_url' => '/contenido/fichas',

    /*
     * Columnas que el comando NUNCA recorre, como `tabla.columna` o `tabla.*`. Por defecto, tablas técnicas de Laravel cuyo
     * contenido es PHP serializado: cambiar la longitud de una cadena dentro de un valor serializado lo corrompería.
     */
    'externalizar_excluir' => ['sessions.*', 'cache.*', 'cache_locks.*', 'jobs.*', 'failed_jobs.*', 'migrations.*'],

    /*
     * Límite de memoria de PHP para la ruta de la ficha (ver App\Http\Middleware\MemoriaParaFichas). 512M mientras las fichas
     * lleven imágenes en base64 (DAT-07); tras aplicar `explora:externalizar-imagenes` en producción puede bajarse a 128M.
     */
    'memoria_fichas' => env('FICHAS_MEMORIA', '512M'),

    /*
     * Caché de lectura pública (ficha y preguntas; ver App\Http\Middleware\CacheLecturaPublica).
     * `cache_lectura_ttl`: segundos que el servidor conserva la respuesta (0 = desactivada). `cache_lectura_navegador`: segundos
     * que el navegador la reutiliza sin preguntar (después revalida con ETag). `cache_lectura_max_bytes`: mayor cuerpo que se
     * guarda en el servidor (las fichas con base64 pesan hasta 48 MB y no se guardan).
     */
    'cache_lectura_ttl' => (int) env('EXPLORA_CACHE_TTL', 3600),
    'cache_lectura_navegador' => (int) env('EXPLORA_CACHE_NAVEGADOR', 300),
    'cache_lectura_max_bytes' => (int) env('EXPLORA_CACHE_MAX_BYTES', 2 * 1024 * 1024),

    /*
     * Caché del servidor del ranking (ver App\Http\Controllers\Api\RankingController y App\Support\CacheRanking): segundos que
     * se conserva la respuesta (0 = desactivada). Guardar una partida la invalida al instante, así que el TTL solo acota el
     * espacio ocupado; se mantiene corto porque la categoría es texto libre.
     */
    'ranking_cache_ttl' => (int) env('EXPLORA_RANKING_CACHE_TTL', 30),
];
