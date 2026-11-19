# IMPLEMENTACIÓN DE MEJORAS

Resumen por fase de lo implementado en `laravel/` (detalle fila a fila en [REGISTRO_CAMBIOS.md](REGISTRO_CAMBIOS.md), pruebas en [INFORME_PRUEBAS.md](INFORME_PRUEBAS.md)). Rama `migracion-laravel`, sin commits aún; el sistema legado sigue funcionando sin cambios.

| Fase | Estado | Qué se implementó | Hallazgos relacionados |
|---|---|---|---|
| 0 Preparación | Completa | Proyecto Laravel 10.50.4; bases `explora_test` y `pedigital_test` anonimizadas; usuarios MySQL de mínimo privilegio; conexión `pedigital` de solo lectura; 5 avisos de Composer ignorados de forma explícita | TEC-05, SEC-02 (parcial: sin secretos en el código nuevo) |
| 1 Red de seguridad | Completa | 20 pruebas de caracterización de los 8 endpoints legados; arnés que sirve una copia contra bases de prueba | FUN-08, CAL-01 |
| 2 Datos | Completa | Migración base idempotente (39 tablas), 26 modelos + `Usuario` de solo lectura, `perfiles_explora` para el avatar | TEC-01/03/05, DAT-02..05 (documentados, sin corregir en BD) |
| 3 Lectura pública | **Completa (sin conectar al frontend)** | `/api/departamentos/{id}/ficha` y `/preguntas` con Resources de lista blanca, ids numéricos, límite de peticiones, JSON siempre; 15 pruebas de contrato en 33 departamentos | SEC-01, SEC-07, FUN-02, TEC-03 (resueltos en el código nuevo); TEC-01, SEC-06 (parciales); REN-03 / FUN-11 / DAT-07 (hallados, de datos) |
| 4 Autenticación | **Completa (sin conectar al frontend)** | Sesión de servidor + CSRF contra `pedigital` (solo lectura), límite de intentos, respuesta sin hash, cuenta inactiva rechazada, avatar en `perfiles_explora` con identidad tomada de la sesión | SEC-03 (avatar), SEC-04, DAT-01, SEC-10 (resueltos en el código nuevo); SEC-03 sigue abierto en historial/resultados (Fase 5) |
| 5 Juegos | **Completa (sin conectar al frontend)** | Guardar resultado (identidad desde la sesión, modo invitado conservado y validado, listas cerradas de juegos y niveles, rangos y límite de uso), ranking público de 5 campos e historial solo propio | SEC-01, SEC-03, SEC-05 (servidor), FUN-09 (resueltos en el código nuevo); SEC-06 (límite aceptado: el navegador calcula el puntaje); FUN-01, DAT-03 (parciales: la fecha sigue siendo texto) |
| 6 IA | **Aplazada** (decisión del responsable: chat y corrector irán en un trabajo aparte enfocado en IA) | — | SEC-08, FUN-03, FUN-04, FUN-05 y la parte de SEC-02 del chat legado quedan **abiertos** (ver PENDIENTE_TRABAJO_IA.md) |
| 7 Frontend | Pendiente | — | SEC-09, TEC-02, REN-01/02, UX-01 |
| 8 Cierre | Pendiente | Retiro del legado y despliegue (requieren autorización) | — |

**Pruebas actuales:** 99, 1.096 aserciones, todas correctas.

**Documentación del código:** todo el código nuevo está documentado en español: cada clase, método público (con `@return`/`@param` descriptivos, porque Pint elimina los que solo repiten el tipo) y método de prueba (qué comprueba y por qué); `laravel/README.md` describe estructura, API, variables de entorno, pruebas y convenciones. Verificado por un escáner propio y por un agente auditor; es un requisito permanente del proyecto.
