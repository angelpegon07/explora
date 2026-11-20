<?php

namespace Tests\Contract;

/**
 * Utilidades comunes de las pruebas de contrato de la API (Ficha y Quiz).
 *
 * Hasta la Fase 15 también comparaban la API con el PHP legado en vivo; ese servidor y sus comparaciones se retiraron
 * (el nombre del trait se conserva por historia). Quedan los ayudantes que usan las pruebas actuales.
 */
trait ContractoLegado
{
    /** Columnas internas que el legado filtraba por error y la API nueva NO debe exponer. */
    private const INTERNAS = ['remember_token', 'created_at', 'updated_at', 'update_at'];

    /**
     * Indica si existe la ruta de claves anidadas indicada (la clave puede valer null).
     *
     * @param  array<mixed>  $datos  Estructura a recorrer.
     * @param  array<int, string>  $ruta  Claves sucesivas, p. ej. ['geografia', 'ubicacion'].
     */
    protected function contieneClave(array $datos, array $ruta): bool
    {
        foreach ($ruta as $clave) {
            if (! is_array($datos) || ! array_key_exists($clave, $datos)) {
                return false;
            }
            $datos = $datos[$clave];
        }

        return true;
    }

    /**
     * Busca recursivamente alguna columna interna (remember_token, marcas de tiempo) y devuelve su ruta, o null si no hay.
     *
     * @param  mixed  $v  Estructura a revisar.
     * @param  string  $ruta  Ruta acumulada (uso interno).
     */
    protected function claveInterna(mixed $v, string $ruta = ''): ?string
    {
        if (! is_array($v)) {
            return null;
        }
        foreach ($v as $k => $x) {
            if (is_string($k) && in_array($k, self::INTERNAS, true)) {
                return "$ruta.$k";
            }
            if (($r = $this->claveInterna($x, "$ruta.$k")) !== null) {
                return $r;
            }
        }

        return null;
    }
}
