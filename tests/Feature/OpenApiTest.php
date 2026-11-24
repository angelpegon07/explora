<?php

namespace Tests\Feature;

use App\Enums\SeccionFicha;
use App\Models\Explora\ResultadoJuego;
use Illuminate\Routing\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Fase 17E: el contrato `docs/openapi.yaml` (OpenAPI 3.1) debe coincidir con la API real.
 *
 * Tres familias de comprobaciones:
 *  1. Cobertura: cada ruta `v1.*` está documentada y cada operación documentada existe como ruta.
 *  2. Calidad del documento: `operationId` únicos, etiquetas, resumen, respuestas 2xx y las respuestas de error que
 *     exigen los middleware de la ruta (429 por límite de peticiones, 401 por autenticación o cuenta activa);
 *     todos los `$ref` internos resuelven y los ejemplos cumplen su propio esquema.
 *  3. Contraste con respuestas REALES (ficha, preguntas, ranking y un 404): claves de primer nivel y tipos
 *     principales, con un validador propio y sencillo (no se usa ninguna librería JSON Schema externa).
 */
class OpenApiTest extends TestCase
{
    /** Verbos HTTP que pueden aparecer como operaciones de un path. */
    private const VERBOS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    /** Prefijo de las categorías de las filas de ranking que crea esta prueba (para poder borrarlas). */
    private const MARCA = 'Zz Prueba OpenApi ';

    /** @var array<string, mixed>|null Spec decodificado (se lee una sola vez por prueba). */
    private ?array $spec = null;

    /** Borra las filas de ranking sembradas por esta prueba, pase lo que pase. */
    protected function tearDown(): void
    {
        ResultadoJuego::where('categoria', 'like', self::MARCA.'%')->delete();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ utilidades

    /**
     * Lee y decodifica `docs/openapi.yaml`.
     *
     * @return array<string, mixed>
     */
    private function spec(): array
    {
        $this->assertTrue(class_exists(Yaml::class), 'Falta symfony/yaml en vendor.');

        return $this->spec ??= Yaml::parseFile(base_path('docs/openapi.yaml'));
    }

    /**
     * Operaciones del documento como mapa «MÉTODO path» => operación.
     *
     * @return array<string, array<string, mixed>>
     */
    private function operacionesDelSpec(): array
    {
        $ops = [];
        foreach ($this->spec()['paths'] as $path => $item) {
            foreach (self::VERBOS as $verbo) {
                if (isset($item[$verbo])) {
                    $ops[strtoupper($verbo).' '.$path] = $item[$verbo];
                }
            }
        }

        return $ops;
    }

    /**
     * Rutas reales con nombre `v1.*`, como mapa «MÉTODO /path» => ruta (sin HEAD). Las rutas temporales que registran
     * otras pruebas no tienen nombre y quedan fuera.
     *
     * @return array<string, Route>
     */
    private function rutasV1(): array
    {
        $rutas = [];
        foreach (app('router')->getRoutes() as $ruta) {
            $nombre = (string) $ruta->getName();
            if (! str_starts_with($nombre, 'v1.')) {
                continue;
            }
            $path = '/'.ltrim(substr($ruta->uri(), strlen('api/v1')), '/');
            foreach ($ruta->methods() as $metodo) {
                if ($metodo !== 'HEAD') {
                    $rutas["$metodo $path"] = $ruta;
                }
            }
        }

        return $rutas;
    }

    /**
     * Middleware resueltos de una ruta (grupos expandidos, p. ej. `api` aporta `ThrottleRequests:api`).
     *
     * @return array<int, string>
     */
    private function middlewareDe(Route $ruta): array
    {
        return array_map('strval', app('router')->gatherRouteMiddleware($ruta));
    }

    /**
     * Resuelve un `$ref` interno («#/components/...») en el spec.
     *
     * @return array<string, mixed>|null null si no existe.
     */
    private function resolver(string $ref): ?array
    {
        if (! str_starts_with($ref, '#/')) {
            return null;
        }
        $nodo = $this->spec();
        foreach (explode('/', substr($ref, 2)) as $parte) {
            $parte = str_replace(['~1', '~0'], ['/', '~'], $parte);
            if (! is_array($nodo) || ! array_key_exists($parte, $nodo)) {
                return null;
            }
            $nodo = $nodo[$parte];
        }

        return is_array($nodo) ? $nodo : null;
    }

    /**
     * Esquema de `components/schemas/{nombre}`.
     *
     * @return array<string, mixed>
     */
    private function esquema(string $nombre): array
    {
        return $this->resolver('#/components/schemas/'.$nombre) ?? $this->fail("No existe el esquema $nombre.");
    }

    /**
     * Valida un dato contra un esquema con un subconjunto de JSON Schema: `$ref`, `type` (también como lista), `const`,
     * `enum`, `required`, `properties`, `items` y `oneOf`. Lo que no entiende (formatos, patrones, rangos) lo ignora.
     *
     * @param  mixed  $dato  Valor ya decodificado de JSON.
     * @param  array<string, mixed>  $esquema  Esquema (puede ser un `$ref`).
     * @param  string  $ruta  Ruta legible del dato, para los mensajes.
     * @return array<int, string> Lista de incumplimientos (vacía si cumple).
     */
    private function validar(mixed $dato, array $esquema, string $ruta = '$'): array
    {
        if (isset($esquema['$ref'])) {
            $destino = $this->resolver($esquema['$ref']);

            return $destino === null ? ["$ruta: \$ref sin resolver {$esquema['$ref']}"] : $this->validar($dato, $destino, $ruta);
        }

        if (isset($esquema['oneOf'])) {
            foreach ($esquema['oneOf'] as $alternativa) {
                if ($this->validar($dato, $alternativa, $ruta) === []) {
                    return [];
                }
            }

            return ["$ruta: no cumple ninguna alternativa de oneOf"];
        }

        $errores = [];
        if (isset($esquema['type'])) {
            $tipos = (array) $esquema['type'];
            if (! array_filter($tipos, fn ($t) => $this->tieneTipo($dato, (string) $t))) {
                return ["$ruta: se esperaba ".implode('|', $tipos).', llegó '.get_debug_type($dato)];
            }
        }
        if (array_key_exists('const', $esquema) && $dato !== $esquema['const']) {
            $errores[] = "$ruta: debe valer ".json_encode($esquema['const']);
        }
        if (isset($esquema['enum']) && ! in_array($dato, $esquema['enum'], true)) {
            $errores[] = "$ruta: valor fuera de enum";
        }

        if (is_array($dato) && array_is_list($dato) === false) {
            foreach ($esquema['required'] ?? [] as $clave) {
                if (! array_key_exists($clave, $dato)) {
                    $errores[] = "$ruta: falta la clave obligatoria «{$clave}»";
                }
            }
            foreach ($esquema['properties'] ?? [] as $clave => $sub) {
                if (array_key_exists($clave, $dato)) {
                    array_push($errores, ...$this->validar($dato[$clave], $sub, "$ruta.$clave"));
                }
            }
        } elseif (is_array($dato) && isset($esquema['items'])) {
            foreach ($dato as $i => $elemento) {
                array_push($errores, ...$this->validar($elemento, $esquema['items'], "{$ruta}[$i]"));
            }
        }

        return $errores;
    }

    /**
     * Indica si un valor decodificado de JSON tiene el tipo JSON Schema dado.
     *
     * @param  mixed  $v  Valor a comprobar.
     * @param  string  $tipo  object, array, string, integer, number, boolean o null.
     */
    private function tieneTipo(mixed $v, string $tipo): bool
    {
        return match ($tipo) {
            'object' => is_array($v) && ($v === [] || ! array_is_list($v)),
            'array' => is_array($v) && ($v === [] || array_is_list($v)),
            'string' => is_string($v),
            'integer' => is_int($v),
            'number' => is_int($v) || is_float($v),
            'boolean' => is_bool($v),
            'null' => $v === null,
            default => false,
        };
    }

    /**
     * Recorre el documento y entrega cada `$ref` encontrado.
     *
     * @param  mixed  $nodo  Fragmento del documento.
     * @param  array<int, string>  $refs  Acumulador (por referencia).
     */
    private function recogerRefs(mixed $nodo, array &$refs): void
    {
        if (! is_array($nodo)) {
            return;
        }
        foreach ($nodo as $clave => $valor) {
            if ($clave === '$ref' && is_string($valor)) {
                $refs[] = $valor;
            } else {
                $this->recogerRefs($valor, $refs);
            }
        }
    }

    // ------------------------------------------------------------------ cobertura

    /** El documento es OpenAPI 3.1 con los bloques mínimos y declara `/api/v1` como servidor. */
    public function test_el_documento_tiene_la_estructura_basica(): void
    {
        $s = $this->spec();
        $this->assertStringStartsWith('3.1', $s['openapi']);
        $this->assertNotEmpty($s['info']['title']);
        $this->assertNotEmpty($s['info']['version']);
        $this->assertSame('/api/v1', $s['servers'][0]['url']);
        $this->assertNotEmpty($s['tags']);
        $this->assertStringContainsString('deprecad', $s['info']['description'], 'Debe avisar del legado deprecado.');
    }

    /** Cada ruta con nombre `v1.*` está documentada con su método y path. */
    public function test_cada_ruta_v1_esta_documentada(): void
    {
        $this->assertNotEmpty($this->rutasV1());
        $documentadas = array_keys($this->operacionesDelSpec());
        foreach (array_keys($this->rutasV1()) as $clave) {
            $this->assertContains($clave, $documentadas, "La ruta $clave no está en docs/openapi.yaml");
        }
    }

    /** Cada operación del documento existe como ruta real `v1.*`. */
    public function test_cada_operacion_documentada_existe(): void
    {
        $reales = array_keys($this->rutasV1());
        foreach (array_keys($this->operacionesDelSpec()) as $clave) {
            $this->assertContains($clave, $reales, "La operación $clave no existe como ruta v1");
        }
    }

    // ------------------------------------------------------------------ calidad de cada operación

    /** `operationId` único, `tags` declaradas, `summary` y al menos una respuesta 2xx. */
    public function test_cada_operacion_tiene_los_campos_obligatorios(): void
    {
        $etiquetas = array_column($this->spec()['tags'], 'name');
        $ids = [];
        foreach ($this->operacionesDelSpec() as $clave => $op) {
            $this->assertNotEmpty($op['operationId'] ?? null, "$clave sin operationId");
            $this->assertArrayNotHasKey($op['operationId'], $ids, "operationId repetido: {$op['operationId']}");
            $ids[$op['operationId']] = true;

            $this->assertNotEmpty($op['tags'] ?? null, "$clave sin tags");
            foreach ($op['tags'] as $etiqueta) {
                $this->assertContains($etiqueta, $etiquetas, "$clave usa una etiqueta no declarada");
            }
            $this->assertNotEmpty($op['summary'] ?? null, "$clave sin summary");

            $exitos = array_filter(array_keys($op['responses'] ?? []), fn ($c) => str_starts_with((string) $c, '2'));
            $this->assertNotEmpty($exitos, "$clave sin respuesta 2xx");
        }
    }

    /** Las respuestas de error que imponen los middleware de la ruta (429, 401) y el 500 están documentadas. */
    public function test_las_respuestas_de_error_siguen_a_los_middleware(): void
    {
        $ops = $this->operacionesDelSpec();
        foreach ($this->rutasV1() as $clave => $ruta) {
            $codigos = array_map('strval', array_keys($ops[$clave]['responses']));
            $middleware = implode('|', $this->middlewareDe($ruta));

            if (str_contains($middleware, 'ThrottleRequests') || str_contains($middleware, 'throttle:')) {
                $this->assertContains('429', $codigos, "$clave lleva throttle y no documenta 429");
            }
            if (str_contains($middleware, 'Authenticate:web') || str_contains($middleware, 'auth:web')
                || str_contains($middleware, 'CuentaActiva') || str_contains($middleware, 'cuenta.activa')) {
                $this->assertContains('401', $codigos, "$clave exige sesión o cuenta activa y no documenta 401");
            }
            $this->assertContains('500', $codigos, "$clave no documenta 500");
        }
    }

    /** Las operaciones que modifican estado documentan el 419 (CSRF) y declaran la cabecera CSRF como seguridad. */
    public function test_las_operaciones_de_escritura_documentan_csrf(): void
    {
        foreach ($this->operacionesDelSpec() as $clave => $op) {
            if (str_starts_with($clave, 'GET ')) {
                continue;
            }
            $this->assertArrayHasKey('419', $op['responses'], "$clave no documenta 419");
            $esquemas = [];
            foreach ($op['security'] ?? [] as $requisito) {
                $esquemas = array_merge($esquemas, array_keys($requisito));
            }
            $this->assertContains('csrfHeader', $esquemas, "$clave no declara csrfHeader");
        }
    }

    /** Todo `$ref` interno resuelve, y todo esquema de seguridad usado está definido. */
    public function test_todos_los_refs_internos_resuelven(): void
    {
        $refs = [];
        $this->recogerRefs($this->spec(), $refs);
        $this->assertNotEmpty($refs);
        foreach (array_unique($refs) as $ref) {
            $this->assertNotNull($this->resolver($ref), "\$ref sin resolver: $ref");
        }

        $definidos = array_keys($this->spec()['components']['securitySchemes']);
        foreach ($this->operacionesDelSpec() as $clave => $op) {
            foreach ($op['security'] ?? [] as $requisito) {
                foreach (array_keys($requisito) as $nombre) {
                    $this->assertContains($nombre, $definidos, "$clave usa un esquema de seguridad no definido");
                }
            }
        }
    }

    /** Todo `example`/`examples` de una respuesta o petición cumple el esquema de su mismo tipo de contenido. */
    public function test_los_ejemplos_cumplen_sus_esquemas(): void
    {
        $revisar = function (array $contenido, string $donde): void {
            foreach ($contenido as $tipo => $media) {
                if (! isset($media['schema'])) {
                    continue;
                }
                $ejemplos = [];
                if (array_key_exists('example', $media)) {
                    $ejemplos['example'] = $media['example'];
                }
                foreach ($media['examples'] ?? [] as $nombre => $ej) {
                    $ejemplos[$nombre] = $ej['value'];
                }
                foreach ($ejemplos as $nombre => $valor) {
                    $this->assertSame([], $this->validar($valor, $media['schema']), "Ejemplo «{$nombre}» de {$donde} ({$tipo}) no cumple su esquema");
                }
            }
        };

        foreach ($this->spec()['components']['responses'] as $nombre => $resp) {
            $revisar($resp['content'] ?? [], "components/responses/$nombre");
        }
        foreach ($this->operacionesDelSpec() as $clave => $op) {
            $revisar($op['requestBody']['content'] ?? [], "$clave (petición)");
            foreach ($op['responses'] as $codigo => $resp) {
                $revisar($resp['content'] ?? [], "$clave ($codigo)");
            }
        }
    }

    /** Los enums y los límites del documento coinciden con la configuración real (`config/explora.php`). */
    public function test_los_catalogos_coinciden_con_la_configuracion(): void
    {
        $this->assertSame(config('explora.juegos'), $this->esquema('Juego')['enum']);
        $this->assertSame(config('explora.niveles'), $this->esquema('Nivel')['enum']);
        $resultado = $this->esquema('PeticionResultado')['properties'];
        $this->assertSame((int) config('explora.puntaje_max'), $resultado['puntaje']['maximum']);
        $this->assertSame((int) config('explora.tiempo_max'), $resultado['tiempo']['maximum']);
        $this->assertSame((int) config('explora.avatares_max'), $this->esquema('PeticionAvatar')['properties']['avatar']['maximum']);
        $this->assertSame((int) config('explora.historial_max'), $this->spec()['paths']['/historial']['get']['responses']['200']['content']['application/json']['schema']['maxItems']);
    }

    // ------------------------------------------------------------------ contraste con respuestas reales

    /** Ficha del departamento 2: las 16 claves coinciden con el esquema, con SeccionFicha y con los tipos declarados. */
    public function test_la_ficha_real_cumple_el_esquema(): void
    {
        $ficha = $this->getJson('/api/v1/departamentos/2/ficha')->assertOk()->json();
        $esquema = $this->esquema('Ficha');

        $claves = array_map(fn (SeccionFicha $s) => $s->value, SeccionFicha::cases());
        $this->assertCount(16, $claves);
        $this->assertSame($claves, array_keys($ficha), 'Claves y orden reales vs. SeccionFicha');
        $this->assertEqualsCanonicalizing($claves, array_keys($esquema['properties']), 'Propiedades del esquema vs. SeccionFicha');
        $this->assertEqualsCanonicalizing($claves, $esquema['required']);
        $this->assertSame([], $this->validar($ficha, $esquema));

        // Las columnas documentadas de cada sección de fila única coinciden con la lista blanca de la aplicación.
        foreach (SeccionFicha::cases() as $seccion) {
            $propiedad = $esquema['properties'][$seccion->value];
            $objeto = isset($propiedad['oneOf']) ? $propiedad['oneOf'][0] : $propiedad;
            $documentadas = array_keys($objeto['properties'] ?? $propiedad['items']['properties'] ?? []);
            $esperadas = match ($seccion) {
                SeccionFicha::Etnografia => [...$seccion->columnas(), 'lista'],
                SeccionFicha::FaunaFlora => [...$seccion->columnas(), 'lista_flora', 'lista_fauna'],
                default => $seccion->columnas(),
            };
            if ($seccion->esLista()) {
                $this->assertEqualsCanonicalizing(SeccionFicha::COLUMNAS_LISTA, array_keys($this->esquema('ElementoLista')['properties']), $seccion->value);

                continue;
            }
            $this->assertEqualsCanonicalizing($esperadas, $documentadas, "Columnas documentadas de «{$seccion->value}»");
        }
    }

    /** Preguntas del departamento 2: claves del quiz, de cada pregunta y de cada opción dentro de lo documentado. */
    public function test_las_preguntas_reales_cumplen_el_esquema(): void
    {
        $j = $this->getJson('/api/v1/departamentos/2/preguntas')->assertOk()->json();
        $esquema = $this->esquema('Preguntas');

        $this->assertEqualsCanonicalizing(array_keys($esquema['properties']), array_keys($j));
        $this->assertSame([], $this->validar($j, $esquema));
        $this->assertNotEmpty($j['preguntas']);

        $propiedadesPregunta = array_keys($this->esquema('Pregunta')['properties']);
        $propiedadesOpcion = array_keys($this->esquema('OpcionPregunta')['properties']);
        foreach ($j['preguntas'] as $p) {
            $this->assertEmpty(array_diff(array_keys($p), $propiedadesPregunta), 'Clave de pregunta no documentada');
            foreach ($p['opciones'] as $o) {
                $this->assertEmpty(array_diff(array_keys($o), $propiedadesOpcion), 'Clave de opción no documentada');
            }
        }
    }

    /** Ranking sembrado: un arreglo de filas con exactamente las cinco claves documentadas. */
    public function test_el_ranking_real_cumple_el_esquema(): void
    {
        $categoria = self::MARCA.uniqid();
        ResultadoJuego::create([
            'nombre_juego' => 'laberinto', 'nivel' => 'Principiante', 'categoria' => $categoria,
            'puntaje' => 9, 'tiempo' => 100, 'jugador' => 'Jugador OpenApi', 'avatar' => '1.png', 'grado' => '6',
            'id_usuario' => 0, 'fecha' => '01-01-2026 10:00:00',
        ]);

        $filas = $this->getJson('/api/v1/ranking?'.http_build_query(['juego' => 'laberinto', 'nivel' => 'Principiante', 'categoria' => $categoria]))
            ->assertOk()->json();
        $esquemaRespuesta = $this->spec()['paths']['/ranking']['get']['responses']['200']['content']['application/json']['schema'];

        $this->assertCount(1, $filas);
        $this->assertSame([], $this->validar($filas, $esquemaRespuesta));
        $this->assertEqualsCanonicalizing(array_keys($this->esquema('FilaRanking')['properties']), array_keys($filas[0]));
    }

    /** Un 404 real de la v1 es `application/problem+json` y cumple el esquema `Problema` (también la URL desconocida). */
    public function test_un_404_real_cumple_el_esquema_problema(): void
    {
        foreach (['/api/v1/departamentos/999999/ficha', '/api/v1/departamentos/999999/preguntas', '/api/v1/no-existe'] as $url) {
            $r = $this->getJson($url)->assertStatus(404);
            $this->assertStringStartsWith('application/problem+json', (string) $r->headers->get('Content-Type'));
            $this->assertSame([], $this->validar($r->json(), $this->esquema('Problema')), $url);
            $this->assertSame(404, $r->json('status'));
        }
    }

    /** Un 401 y un 422 reales (sin sesión / cuerpo vacío) también cumplen `Problema` y traen las extensiones documentadas. */
    public function test_un_401_y_un_422_reales_cumplen_el_esquema_problema(): void
    {
        $r401 = $this->getJson('/api/v1/sesion')->assertStatus(401);
        $this->assertSame([], $this->validar($r401->json(), $this->esquema('Problema')));
        $this->assertFalse($r401->json('success'));

        $r422 = $this->postJson('/api/v1/resultados', [])->assertStatus(422);
        $this->assertSame([], $this->validar($r422->json(), $this->esquema('Problema')));
        $this->assertIsArray($r422->json('campos'));
    }
}
