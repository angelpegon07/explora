# PROPUESTA DE DESPLIEGUE → sustituida por la guía definitiva

Este documento era la propuesta de despliegue (no ejecutada) de las fases 9 a 16B. **Desde la Fase 16C la guía vigente es
[`GUIA_DESPLIEGUE.md`](GUIA_DESPLIEGUE.md)**, verificada con un ensayo completo sobre Apache + PHP-FPM reales (2026-11-23), con plantillas y
scripts en [`../deploy/`](../deploy/). Aquí no se mantiene ningún procedimiento: seguirlo podría dar un orden o unos comandos obsoletos
(por ejemplo, `php artisan migrate --force` a secas, que además aplicaría la migración de Sanctum).

Para quien busque el texto anterior (puntos de control, pasos 1-8, «Fase 16B», marcha atrás y autorizaciones), está en el historial de git
(`git log -- DOCUMENTACION_MEJORAMIENTO/PROPUESTA_DESPLIEGUE.md`). Lo que sigue pendiente de decisión del responsable (autorización sobre la base real,
credenciales nuevas, chat legado, ventana de despliegue) está en la sección 10 de la guía.
