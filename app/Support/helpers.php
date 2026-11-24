<?php

use App\Support\Assets;

if (! function_exists('asset_v')) {
    /**
     * Como `asset()`, pero con `?v=<hash del contenido>` para invalidar la caché del navegador al cambiar el archivo.
     * Usar SOLO en `<link rel="stylesheet">` y `<script src>` con rutas literales de public/ (no imágenes ni fuentes).
     * Ver {@see Assets::url()}.
     *
     * @param  string  $ruta  Ruta relativa a public/ (p. ej. `js/inicio.js`).
     * @return string URL del asset, con versión si el archivo existe.
     */
    function asset_v(string $ruta): string
    {
        return Assets::url($ruta);
    }
}
