<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Regresiones halladas en la ronda de pruebas de navegador con agentes (Fase 8):
 * ids enormes, emoji en el login y mensajes en español de la API.
 */
class DefectosQaTest extends TestCase
{
    /** Un id desbordado debe dar 404 (antes 500 por desbordamiento de entero). */
    public function test_id_enorme_responde_404(): void
    {
        $this->getJson('/api/departamentos/99999999999999999999/ficha')->assertStatus(404);
        $this->getJson('/api/departamentos/9223372036854775808/preguntas')->assertStatus(404);
    }

    /** Un usuario con emoji (4 bytes) no debe provocar un 500: se rechaza como entrada no válida. */
    public function test_login_con_emoji_no_da_500(): void
    {
        $this->postJson('/api/sesion', ['usuario' => "a\u{1F600}", 'contrasena' => 'x'])->assertStatus(422);
    }

    /** Los 404 de la API llevan mensaje en español y forma JSON uniforme. */
    public function test_404_de_la_api_es_json_en_espanol(): void
    {
        $this->getJson('/api/no-existe')->assertStatus(404)->assertJsonPath('mensaje', 'Recurso no encontrado.');
        $this->get('/api')->assertStatus(404)->assertJsonPath('success', false);
    }
}
