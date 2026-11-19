<?php

// Sustituto SOLO PARA PRUEBAS de mapplic/php/conexion_pedigital.php (la conexión del legado a la BD de usuarios):
// apunta a `pedigital_test`. tests/Legacy/serve.sh lo copia en lugar del original, que no se modifica.
$conexion = new mysqli(getenv('LEGACY_DB_HOST'), getenv('LEGACY_DB_USER'), getenv('LEGACY_DB_PASS'), 'pedigital_test');
if ($conexion->connect_error) {
    exit('Error de conexion de pruebas');
}
$conexion->set_charset('utf8');
