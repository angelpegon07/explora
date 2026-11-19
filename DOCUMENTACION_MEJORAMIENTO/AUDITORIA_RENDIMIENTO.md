# AUDITORÍA DE RENDIMIENTO

**Sin mediciones reales**: no hay entorno en ejecución. No se afirman porcentajes de mejora; todo es evidencia estática.

## Comprobado por inspección
| Elemento | Evidencia | Observación |
|---|---|---|
| Ficha de departamento | `consultarInfo.php`: ≈19 consultas `SELECT *` secuenciales por apertura | Reducible con relaciones/`with()` y selección de columnas |
| Historial | `fetch_all` + `usort` en PHP, sin límite | Crece con el uso |
| IA | cURL con timeout de 120 s en `chat.php` | Bloquea un proceso por consulta |
| Medios pesados | `images/victoria.gif` 11 MB, `derrota.gif` 3,6 MB, `pensando.gif`, `ventana_mundo.mp4`, `fondo.mp3` | Convertir a WebP/MP4 |
| Datos | `parques_naturales.json` 6,2 MB; `jsons/` 35 MB; `maps_parques/` 16 MB | Compresión/simplificación |
| JS | Mapplic ×3 (~1.300 líneas), jQuery 1.11.3, sin bundler ni minificación propia | Vite + división por página |
| Repositorio | `juegos/` 307 MB (banderas: 2.217 archivos) | Carga bajo demanda |

## No verificable
Compresión y caché del servidor, índices de BD, tiempos de respuesta, uso de memoria.

## Línea base propuesta (para el plan)
Medir antes de cambiar: tiempo de `consultarInfo.php`, tamaño total de la primera carga de `index.html`, tiempo del chat con Ollama.
