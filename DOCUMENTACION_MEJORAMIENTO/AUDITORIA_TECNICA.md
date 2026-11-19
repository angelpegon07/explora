# AUDITORÍA TÉCNICA

Alcance: PHP plano, modelo de datos inferido y organización del código. Las secciones propias de Laravel (Eloquent, Form Requests, Policies) se evalúan como **brecha respecto al destino**, no como defectos del sistema actual.

## 1. Arquitectura
- Sin capas: cada script mezcla entrada, SQL y salida JSON. HTML embebido en `chat.php`.
- Duplicación significativa (ver TEC-02). El mapa se mantiene en 3 copias de Mapplic modificadas; cualquier corrección debe hacerse tres veces.
- Escalabilidad: aceptable para uso escolar local; el consumo de IA (hasta 120 s por consulta) y el diseño de 19 consultas por ficha limitan la concurrencia.
- Adecuación: la arquitectura simple es proporcionada al tamaño; **lo que falla es la ausencia de seguridad y pruebas, no la simplicidad**.

## 2. Rutas y controladores (equivalente actual)
- Rutas = archivos `.php` accesibles por URL; sin protección ni versión. Un endpoint de lectura pública (fichas) es razonable; los de escritura no.
- Respuestas inconsistentes: unos usan `success`, otros `status`; `mensaje` vs `message`; solo `chat.php` fija Content-Type JSON.

## 3. Modelo de datos (inferido)
- Tablas `departamento_*` enlazan al id de `departamento_descripcion` y no al de `list_departamentos` → relación indirecta propensa a error (TEC-01).
- Tablas de lista con patrón repetido (`*_list`) → buenas candidatas a relaciones Eloquent `hasMany`.
- Nombres con errores tipográficos (`opciones_verfal`, `pasword_usuario`) → requieren mapeo.
- `resultado_juegos` desnormalizada (jugador, grado y avatar copiados por registro); fecha como texto.
- Dos bases separadas: `explora` y `pedigital`; sin integridad referencial entre ellas (`id_usuario` sin FK posible).
- Índices, claves y restricciones: **no verificables** sin el esquema.

## 4. Validación
Inexistente en servidor. Solo se comprueba que usuario/contraseña no estén vacíos en el cliente (`iniciarSesion.js`).

## 5. Transacciones e integridad
Sin transacciones. Las operaciones son de una sola sentencia, por lo que el riesgo actual es bajo, pero un fallo parcial en `guardar_resultado` no se reintenta ni se registra.

## 6. Brecha frente a Laravel 10 (guía para el plan)

| Actual | Destino Laravel |
|---|---|
| `conexion*.php` | `config/database.php` + `.env`, dos conexiones (`explora`, `pedigital` solo lectura) |
| Scripts PHP | Controladores + Form Requests + API Resources |
| SQL directo | Modelos Eloquent con relaciones |
| `localStorage` como sesión | Sanctum / sesión + middleware `auth` |
| `chat.php` + Ollama | Servicio `ConsultaIAService` con cola, timeout y lista blanca |
| Mapplic ×3, librerías copiadas | Vite + npm, una copia parametrizada |
| Sin pruebas | PHPUnit/Pest + Pint + Larastan |

## 7. Limitaciones
Sin esquema real, sin ejecución y sin revisión del código interno de Mapplic ni de los juegos.
