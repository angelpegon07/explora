# DIAGNÓSTICO FUNCIONAL

Evaluación por lectura de código. **Ningún flujo se ejecutó** (sin BD ni servidor), por lo que "funciona" significa "el código es coherente", no "probado". Hallazgos detallados en [MATRIZ_HALLAZGOS.md](MATRIZ_HALLAZGOS.md).

| Módulo | Flujo evaluado | Resultado de la revisión | Hallazgos |
|---|---|---|---|
| M02 Fichas | clic → `consultarInfo.php` → render | Flujo completo y coherente; maneja departamento inexistente con error | TEC-01, SEC-01 |
| M03 Quiz | `buscarPreguntas.php` → preguntas múltiples y V/F | Flujo coherente; sin respuesta cuando no hay datos; contador "de 10" fijo; respuesta correcta en el cliente | FUN-02, FUN-06, SEC-06 |
| M04 Chat | `chat.js` → `chat.php` → Ollama → BD | Diseño razonable (IA extrae intención, BD responde); URL sin codificar, sin validación de salida de IA; depende de Ollama local | FUN-03, FUN-04, FUN-05 |
| M05 Login | `iniciarSesion.php` → `localStorage` | Verificación de hash correcta; sesión solo en cliente; fuga del hash | SEC-03, SEC-04 |
| M06 Perfil | historial + cambio de avatar | Funciona sobre `id_usuario` del cliente (suplantable) | SEC-03 |
| M13 Juegos | guardar resultado, ranking top 5, historial | Cadena completa implementada; datos aceptados sin validar; fecha como texto | SEC-01, SEC-06, FUN-01 |
| M24 Corrector | navegador → Ollama `localhost` | Solo opera en el equipo con Ollama | FUN-05 |
| M01, M07–M12 | mapas y tutorial | No evaluados en profundidad (solo estructura y dependencias) | UX-02 |
| M14–M18 | juegos | No evaluados internamente | — |
| M19–M21 | juegos sin enlace | Pendiente de información | FUN-07 |
| M22, M23 | línea de tiempo, ViajaPED | No evaluados internamente | — |

## Consistencia entre módulos
- Login (M05) alimenta M06 y M13 solo mediante `localStorage`; no hay verificación cruzada de que el usuario exista al guardar resultados.
- El avatar se guarda en `pedigital.users` (BD ajena) y se copia a `resultado_juegos` por registro: cambiar el avatar no actualiza resultados antiguos (comportamiento a confirmar si es intencionado).

## Información necesaria para completar
Dump/esquema de BD, entorno ejecutable (PHP + MySQL + Ollama), credenciales de prueba, y decisión sobre M19–M21.
