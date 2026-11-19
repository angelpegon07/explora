<?php

namespace Tests\Contract;

use Illuminate\Support\Facades\Http;

/** Utilidades comunes de las pruebas de contrato legado <-> Laravel. */
trait ContractoLegado
{
    /** Columnas internas que el legado filtraba por error y la API nueva NO debe exponer. */
    private const INTERNAS = ['remember_token', 'created_at', 'updated_at', 'update_at'];

    /**
     * Comprueba que el servidor del legado responde y devuelve su URL base; si no, omite la prueba.
     *
     * @return string URL base (LEGACY_BASE_URL, por defecto http://127.0.0.1:8081).
     */
    protected function legadoDisponible(): string
    {
        $base = rtrim(env('LEGACY_BASE_URL', 'http://127.0.0.1:8081'), '/');
        try {
            Http::timeout(3)->get($base.'/mapplic/php/chat.php');
        } catch (\Throwable) {
            $this->markTestSkipped('Servidor legado no disponible: ejecute tests/Legacy/serve.sh');
        }

        return $base;
    }

    /** Escalares -> cadena (el legado usa mysqli, que devuelve todo como texto), null se conserva; claves ordenadas. */
    protected function normalizar(mixed $v): mixed
    {
        if (is_array($v)) {
            $r = array_map(fn ($x) => $this->normalizar($x), $v);
            if (! array_is_list($r)) {
                ksort($r);
            }

            return $r;
        }

        return $v === null ? null : (string) $v;
    }

    /**
     * Elimina recursivamente las columnas internas (INTERNAS) de las filas del legado, que la API nueva no expone.
     *
     * @param  mixed  $v  Estructura decodificada del JSON del legado.
     */
    protected function sinInternas(mixed $v): mixed
    {
        if (! is_array($v)) {
            return $v;
        }
        if (! array_is_list($v)) {
            $v = array_diff_key($v, array_flip(self::INTERNAS));
        }

        return array_map(fn ($x) => $this->sinInternas($x), $v);
    }

    /** Primera ruta (a.b.0.c) donde difieren dos estructuras, o null si son iguales. */
    protected function primeraDiferencia(mixed $a, mixed $b, string $ruta = ''): ?string
    {
        if (is_array($a) && is_array($b)) {
            foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $k) {
                if (! array_key_exists($k, $a)) {
                    return "$ruta.$k (solo en la API nueva)";
                }
                if (! array_key_exists($k, $b)) {
                    return "$ruta.$k (solo en el legado)";
                }
                if (($d = $this->primeraDiferencia($a[$k], $b[$k], "$ruta.$k")) !== null) {
                    return $d;
                }
            }

            return null;
        }

        return $a === $b ? null : "$ruta (legado=".substr(var_export($a, true), 0, 60).' / nuevo='.substr(var_export($b, true), 0, 60).')';
    }

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
