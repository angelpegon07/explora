# AUDITORÍA DE SEGURIDAD

Método: revisión estática de los 12 PHP y de los JS que los consumen, con OWASP Top 10 como referencia. No se realizaron pruebas de penetración ni se accedió a ninguna BD. No se reproducen credenciales.

## Resumen

| OWASP | Situación | Hallazgos |
|---|---|---|
| A01 Control de acceso roto | Sin controles en servidor; IDOR en historial, avatar y resultados | SEC-03 |
| A02 Fallos criptográficos | Hash con `password_verify` (correcto), pero se devuelve al cliente; transporte (HTTPS) no verificable | SEC-04, SEC-10 |
| A03 Inyección | SQL concatenado en 11 de 12 scripts; XSS almacenado en ranking | SEC-01, SEC-05 |
| A04 Diseño inseguro | Puntaje y respuestas dependen del cliente | SEC-06 |
| A05 Configuración incorrecta | Credenciales en repo, `root`, errores expuestos | SEC-02, SEC-07 |
| A06 Componentes vulnerables | jQuery 1.x, Bootstrap 4.5.2 | SEC-09 |
| A07 Fallos de autenticación | Sin throttling, enumeración, sesión en `localStorage` | SEC-03, SEC-04 |
| A08/A09 Integridad / logging | Sin SRI, sin registro de eventos | SEC-09, CAL-01 |

## Evaluación por endpoint

| Endpoint | Método | Entrada sin sanear | Autenticación | Observación |
|---|---|---|---|---|
| `mapplic/php/iniciarSesion.php` | POST | No (preparada) | — | Devuelve fila completa, enumera usuarios |
| `mapplic/php/cambiarAvatar.php` | POST | `avatar`, `id_usuario` | No | UPDATE sobre BD ajena `pedigital` |
| `mapplic/php/consultarInfo.php` | GET | `id` | No | Solo lectura pública (aceptable), SQLi |
| `mapplic/php/buscarPreguntas.php` | GET | `id` | No | SQLi; expone respuestas correctas |
| `mapplic/php/chat.php` | GET | `cadena` (va a IA) | No | Nombre escapado; sin rate limit; credenciales en el archivo |
| `juegos/php/guardar_resultado.php` | POST | 9 campos | No | INSERT inyectable, puntaje falsificable |
| `juegos/php/consultar_ranking.php` | POST | 3 campos | No | SQLi |
| `juegos/php/consultar_historial.php` | GET | `id_usuario` | No | SQLi + IDOR |

## Lo que está bien

- `iniciarSesion.php` usa consulta preparada y `password_verify`.
- `chat.php` aplica `real_escape_string` al nombre devuelto por la IA y limita campos mediante lista blanca de tablas/columnas.
- No hay subida de archivos en el servidor (superficie menor).

## Límites

No se verificó: configuración de Apache/PHP, HTTPS, exposición pública del MySQL ni de Ollama, permisos de archivos, existencia de `.git` accesible por web, ni vulnerabilidades concretas de las versiones de las librerías (requiere `npm audit`/base de CVE, no ejecutado).

## Acciones que requieren autorización expresa

Rotar credenciales de BD, purgar secretos del historial Git y cualquier cambio en la BD `pedigital` (ajena).
