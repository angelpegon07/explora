<?php

// Sustituto SOLO PARA PRUEBAS de mapplic/php/conexion.php y juegos/php/conexion.php (conexión del legado a `explora`):
// apunta a `explora_test`. tests/Legacy/serve.sh lo copia en lugar de los originales, que no se modifican.
$conexion = new mysqli(getenv('LEGACY_DB_HOST'), getenv('LEGACY_DB_USER'), getenv('LEGACY_DB_PASS'), 'explora_test');
if ($conexion->connect_error) {
    exit('Error de conexion de pruebas');
}
$conexion->set_charset('utf8');
