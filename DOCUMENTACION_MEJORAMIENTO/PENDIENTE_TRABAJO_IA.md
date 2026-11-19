# TRABAJO PENDIENTE: IA (chat y corrector)

Estado: **aplazado** por decisión del responsable (2026-11-18) para un trabajo posterior enfocado solo en IA. Este documento
reúne lo que ya se sabe para no repetir el análisis. Nada de esto se ha implementado en `laravel/`.

## Qué existe hoy (legado, sin modificar)
| Pieza | Archivo | Cómo funciona |
|---|---|---|
| Chat de consulta | `mapplic/php/chat.php` (+ cliente `mapplic/js/chat.js`) | Recibe `?cadena=`; pide a Ollama (`qwen2.5:7b`, `http://localhost:11434`, tiempo máximo 120 s) que devuelva JSON con `departamento`, `categoria` y `consulta_corregida`; con eso consulta tablas `departamento_*` y responde. Si la categoría o el departamento no son válidos, falla (FUN-04). |
| Corrector de texto | `corrector.html` | Llama a Ollama **directamente desde el navegador** (`localhost:11434`): solo funciona en el equipo que ejecuta Ollama (FUN-05); el texto va dentro del prompt (inyección de prompt). |
| Copia del chat | `mapplic/php/chat copy.php` | Duplicado de `chat.php` con las mismas credenciales de MySQL escritas en el código. |

## Riesgos que quedan abiertos mientras tanto (aceptados)
- **SEC-02:** `chat.php` y `chat copy.php` tienen una conexión `mysqli` con usuario `root` y contraseña escritos en el archivo, y siguen publicados.
- **SEC-08:** sin límite de uso; cada consulta puede ocupar un proceso hasta 120 s (denegación de servicio).
- **FUN-03/04/05, SEC-11:** URL sin codificar (`&`, `#`), salida de la IA sin validar, Ollama solo local, HTML armado con texto del usuario.
- El chat construye consultas con la respuesta de la IA (el nombre del departamento se escapa; la categoría solo se acota por una lista de columnas).

## Qué conviene decidir al empezar
1. **Dónde corre el modelo en producción** (hoy `localhost`; ¿el mismo servidor, otro equipo, un proveedor externo?) y con qué recursos: `qwen2.5:7b` en CPU es lento.
2. **Modelo/proveedor** y privacidad: el chat recibe texto de menores; si se usa un servicio externo hay que decidir qué se envía.
3. **Límites de uso** (por usuario/IP) y **tiempo máximo corto** (no 120 s) con cola o respuesta asíncrona.
4. **Alcance del chat:** ¿seguir con «IA extrae intención + BD responde» o reemplazarlo por búsqueda sin IA para las preguntas comunes (más barato y determinista)?
5. **Si el corrector debe seguir existiendo** y dónde se usa (hoy es una página suelta).

## Diseño sugerido (ya validado en las fases anteriores)
- Servicio `ConsultaIAService` en `laravel/` con **timeout corto, lista blanca de salida** (departamento y categoría contra listas cerradas) y registro de errores sin exponer mensajes internos; endpoints bajo `/api` con el mismo estilo (Form Request, Resource de lista blanca, `throttle`).
- El chat debe leer los datos con los **modelos Eloquent que ya existen** (`DepartamentoDescripcion` y sus secciones), sin SQL propio.
- El corrector pasa a un endpoint de servidor con límite de uso; el navegador no habla con el modelo.
- Pruebas: **doble de la IA** en las pruebas (sin depender de Ollama) más pruebas de la lista blanca, el límite de uso y los mensajes de error; mantener el estándar de documentación del proyecto.
- Al cerrar: **retirar `chat.php`, `chat copy.php` y el cliente viejo, y rotar las credenciales de MySQL** (requiere autorización y coordinación con quien administra el servidor).

## Hallazgos relacionados
SEC-02 (parte del chat), SEC-08, SEC-11, FUN-03, FUN-04, FUN-05 en [MATRIZ_HALLAZGOS.md](MATRIZ_HALLAZGOS.md).
