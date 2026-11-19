# AUDITORÍA DE CALIDAD Y MANTENIBILIDAD

_Estado al cierre de la auditoría (2026-11-18, antes de la Fase 1). Desde entonces `laravel/` tiene 34 pruebas automatizadas y Pint; el legado sigue sin ellas._

## Herramientas existentes
Ninguna: sin PHPUnit/Pest, Pint, PHPStan/Larastan, `composer audit`, cobertura, pruebas de navegador, CI ni gestor de dependencias raíz.

## Evaluación
| Aspecto | Estado |
|---|---|
| Pruebas unitarias / integración / funcionales | Inexistentes (CAL-01) |
| Manejo de excepciones | Inexistente (`die`, errores de BD al cliente) |
| Logging y monitoreo | Inexistentes |
| Documentación | Sin README; esta carpeta es la primera documentación (CAL-02) |
| Mantenibilidad | Baja por duplicación (TEC-02) y ausencia de capas |
| Versionado | Git con 2 commits; secretos en el historial (SEC-02) |

## Compatibilidad verificada para el destino
En este equipo: PHP 8.2.31, Composer 2.10.0, Node 24. Laravel 10 requiere PHP ≥ 8.1 → compatible con 8.2.31. MySQL 8.0.41 sí está instalado y en ejecución en `/usr/local/mysql` (actualizado tras la primera auditoría; acceso con credenciales del responsable). Herramientas recomendadas para el proyecto Laravel: Pest o PHPUnit, Pint, Larastan. **No se instaló nada** en esta fase.

## Recomendación
Crear la red de seguridad de pruebas **antes** de migrar cada módulo (pruebas de caracterización sobre las respuestas actuales de los endpoints), de modo que la migración incremental pueda comprobar equivalencia.
