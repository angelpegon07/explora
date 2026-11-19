# PLAN INTEGRAL DE MEJORAMIENTO — Migración incremental a Laravel 10

Estado: **propuesta pendiente de autorización (Punto de control 3)**. Fecha: 2026-11-18.

## 1. Información general
- **Software:** Explora Colombia y aprende (sitio estático + 12 PHP planos + MySQL `explora` + lectura de `pedigital`).
- **Objetivo:** migrar a Laravel 10 por etapas, corrigiendo los 41 hallazgos de [MATRIZ_HALLAZGOS.md](MATRIZ_HALLAZGOS.md), sin perder funcionalidades ni reglas de negocio.
- **Alcance:** backend (API, autenticación, datos, IA), organización del frontend y pruebas. Fuera de alcance: rediseño visual completo, cambios en la plataforma pednia (su BD `pedigital` se consulta solo en lectura).
- **Destino técnico:** Laravel 10 · PHP 8.2 · MySQL/MariaDB · Vite · Pest o PHPUnit · Pint · Larastan.

## 2. Estrategia
Estrangulamiento progresivo: el sitio actual sigue funcionando mientras se construye un proyecto Laravel en una carpeta nueva (`laravel/`, en la rama `migracion-laravel`). Cada endpoint se reemplaza por su equivalente con **la misma ruta/forma de respuesta** o con un adaptador, y solo después se retira el PHP viejo (con tu autorización).

## 3. Fases y acciones

| Fase | Acciones (hallazgos) | Resultado esperado | Esfuerzo | Dependencias |
|---|---|---|---|---|
| **0. Preparación** | Rama de trabajo y punto de recuperación; MySQL local aislado; cargar el dump; proyecto Laravel 10 vacío; `.env` y 2 conexiones (`explora`, `pedigital` solo lectura) (SEC-02, TEC-05) | Entorno reproducible | M | Dump, autorizaciones (ver §6) |
| **1. Red de seguridad** | Pruebas de caracterización sobre las respuestas actuales de los 8 endpoints (FUN-08, CAL-01) | Línea base de comportamiento | M | Fase 0 |
| **2. Datos** | Modelos Eloquent y relaciones desde el esquema; migraciones base `utf8mb4`; seeders de prueba; corregir la relación indirecta `descripcion.id` (TEC-01, TEC-04) | Modelo de datos documentado y versionado | M–L | Esquema real |
| **3. Lectura pública** | Fichas, quiz y mapas servidos por controladores + Resources con consultas parametrizadas y `with()`; el quiz deja de enviar la respuesta correcta (SEC-01, SEC-06, FUN-02, TEC-01, TEC-03) | Endpoints seguros, JSON uniforme | M | Fase 2 |
| **4. Autenticación** | Login contra `pedigital.users` (solo lectura) con throttling; sesión/Sanctum; middleware y Policies; respuesta sin hash (SEC-03, SEC-04, SEC-10) | Sin IDOR; datos del usuario solo propios | M | Decisión del avatar (§6) |
| **5. Juegos** | Resultados con Form Request y rangos validados; ranking y historial paginados; fecha `datetime`; salida escapada (SEC-05, SEC-06, FUN-01) | Ranking íntegro | M | Fase 4 |
| **6. IA** | **APLAZADA (decisión del responsable, 2026-11-18):** el chat y el corrector se tratarán en un trabajo aparte enfocado solo en IA. Ver [PENDIENTE_TRABAJO_IA.md](PENDIENTE_TRABAJO_IA.md) | Fuera de esta migración | — | Decisión de IA |
| **7. Frontend** | Vite + npm, una sola copia de librerías y de Mapplic parametrizado; jQuery/Bootstrap actualizados por módulo; accesibilidad del quiz; optimización de medios (SEC-09, TEC-02, REN-01/02, UX-01/02) | Menos duplicación, mejor carga | L | Fases 3–5 |
| **8. Cierre** | Suite completa, documentación, informe; retiro del PHP legado y **despliegue** (requieren autorización expresa) | Sistema migrado | M | Todas |

## 4. Priorización
- **Indispensables:** SEC-01, SEC-02, SEC-03 (Fases 0, 3, 4, 5).
- **Alta prioridad:** SEC-04, SEC-05, SEC-06, FUN-05, FUN-08, TEC-05, CAL-01.
- **Recomendadas:** hallazgos medios restantes (TEC, REN, UX, FUN).
- **Opcionales:** hallazgos bajos (FUN-06, FUN-10, CAL-02, CAL-03, CAL-04, DAT-05) y decisión sobre M19–M21.

## 5. Indicadores
Línea base y meta: hallazgos críticos abiertos 3 → 0; endpoints con SQL concatenado 11 → 0; endpoints de escritura sin autenticación 3 → 0; cobertura de pruebas 0 % → medir (meta a acordar); consultas por ficha ≈19 → medir; peso de la primera carga: medir antes y después. Sin metas porcentuales inventadas.

## 6. Qué se necesita (bloqueos)
1. ~~Dump o esquema~~ **Recibido** (2026-11-18): ver [ESQUEMA_BASE_DATOS.md](ESQUEMA_BASE_DATOS.md). Pendiente solo confirmar qué aplicación escribe las tablas.
2. ~~MySQL local~~ **Ya instalado** (8.0.41 en `/usr/local/mysql`). `explora_test` y `pedigital_test` ya existen (Fase 0) con datos anonimizados.
3. **Autorización para crear la rama** `migracion-laravel` y el proyecto Laravel 10 (`composer create-project`, descarga de dependencias). Los 3 `conexion*.php` con cambios ajenos **no se tocarán ni se confirmarán**.
4. ~~**Decisión sobre el avatar:**~~ **Resuelta (2026-11-18): tabla `perfiles_explora` en `explora`.** Texto original: hoy se escribe en `pedigital.users.avatar_explora`. Con `pedigital` en solo lectura, propongo una tabla `perfiles_explora` en `explora`. ¿Se acepta, o se permite escribir esa columna?
5. **Ollama:** ¿se mantiene (y dónde correrá en producción) o se evalúa otro proveedor?
6. **Entorno de producción:** versión de PHP/MySQL y tipo de servidor, para asegurar compatibilidad.
7. **M19–M21** (Arrastra, Completa, `mapas`): ¿migrar, archivar o descartar?
8. **Rotación de credenciales** y purga del historial Git (SEC-02): autorización y coordinación con quien administra el servidor.

## 7. Riesgos y mitigación
| Riesgo | Mitigación |
|---|---|
| Diferencias entre el esquema real y lo inferido | Pedir el dump antes de modelar; marcar PV |
| Regresiones al reemplazar endpoints | Pruebas de caracterización (Fase 1), migración por endpoint |
| Cambio de formato de respuestas rompe el JS actual | Mantener forma compatible o adaptador hasta migrar el frontend |
| Escritura accidental en `pedigital` | Conexión con usuario de solo lectura, prueba que lo verifique |
| Datos personales de menores en el dump | Solo entorno aislado, anonimizar, no versionar |
| Cambios ajenos sin confirmar en el repo | Rama aparte, no confirmar esos archivos |
| Laravel 10 ya no recibe correcciones de seguridad del proyecto (fin de soporte anunciado: seguridad hasta feb-2025; verificar vigencia) | Aceptado por decisión del responsable para alinearse con `pedigital`; diseñar el código sin APIs obsoletas y planificar la actualización conjunta de ambos sistemas a una versión con soporte |
| Ollama no disponible | Degradación controlada con mensaje, no error PHP |

## 8. Restricciones asumidas
Sin migraciones destructivas, sin tocar BD productiva, sin despliegue y sin eliminar funcionalidades hasta recibir autorización individual.
