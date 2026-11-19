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
];
