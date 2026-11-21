<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Eloquent da prioridad al ATRIBUTO sobre la relación: si una relación se llama igual que una columna de su
 * tabla, $modelo->relacion devuelve el valor de la columna y toArray() cambia la forma del JSON.
 * Esta prueba recorre todos los modelos y falla ante cualquier choque nuevo.
 */
class ModelosSinChoquesTest extends TestCase
{
    /**
     * Recorre todos los modelos y falla si una relación tiene el nombre de una columna de su tabla (Eloquent daría
     * prioridad a la columna y rompería el JSON).
     */
    public function test_ninguna_relacion_se_llama_como_una_columna_de_su_tabla(): void
    {
        $revisados = 0;
        $choques = [];
        foreach ((new Finder)->files()->in(app_path('Models'))->name('*.php') as $archivo) {
            $clase = 'App\\Models\\'.str_replace(['/', '.php'], ['\\', ''], $archivo->getRelativePathname());
            $ref = new ReflectionClass($clase);
            if ($ref->isAbstract() || ! $ref->isSubclassOf(Model::class)) {
                continue;
            }
            $modelo = new $clase;
            $columnas = Schema::connection($modelo->getConnectionName())->getColumnListing($modelo->getTable());
            $this->assertNotEmpty($columnas, "Tabla sin columnas: {$modelo->getTable()}");
            foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
                $tipo = $m->getReturnType();
                if ($m->class === $clase && $tipo && is_a($tipo->getName(), Relation::class, true)
                    && in_array($m->getName(), $columnas, true)) {
                    $choques[] = "$clase::{$m->getName()}()";
                }
            }
            $revisados++;
        }
        $this->assertGreaterThan(25, $revisados, 'Se esperaban 26 modelos concretos (25 de Explora + Usuario)');
        $this->assertSame([], $choques, 'Relaciones que chocan con columnas: '.implode(', ', $choques));
    }
}
