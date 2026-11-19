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

    /* Resultados que puede guardar por minuto cada usuario (o cada IP, en el modo invitado). */
    'resultados_por_minuto' => (int) env('RESULTADOS_POR_MINUTO', 10),

    /* Máximo de filas que devuelve el historial de un usuario (las más recientes). */
    'historial_max' => 500,
];
