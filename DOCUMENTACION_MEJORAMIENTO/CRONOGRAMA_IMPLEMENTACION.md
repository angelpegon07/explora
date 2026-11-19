# CRONOGRAMA DE IMPLEMENTACIÓN

Sin fechas de inicio ni personal asignado (no se conoce la disponibilidad). Duraciones orientativas para una persona a tiempo completo; se recalcularán con el esquema real.

| Orden | Fase | Duración estimada | Depende de | Hito |
|---|---|---|---|---|
| 1 | 0. Preparación | 1–2 días | Dump, autorizaciones | Laravel 10 corriendo con BD de prueba |
| 2 | 1. Red de seguridad | 2–3 días | 1 | Pruebas de caracterización verdes sobre el sistema actual |
| 3 | 2. Datos | 3–5 días | 1 | Modelos y migraciones validados contra el dump |
| 4 | 3. Lectura pública | 3–4 días | 3 | Fichas y quiz en Laravel, SQL concatenado eliminado en lectura |
| 5 | 4. Autenticación | 3–4 días | 3 | Login + Policies; IDOR cerrado |
| 6 | 5. Juegos | 3–4 días | 5 | Resultados/ranking/historial seguros |
| 7 | 6. IA | 2–3 días | 4 | Chat y corrector en servidor |
| 8 | 7. Frontend | 8–12 días | 4–6 | Vite, librerías unificadas, accesibilidad |
| 9 | 8. Cierre | 3–4 días | todas | Informe final y propuesta de despliegue |

Fases 5 y 6 pueden solaparse tras la 4. Puntos de control con el responsable: tras la Fase 0, tras la 4 (autenticación) y antes del despliegue. Total orientativo: 28–41 días-persona, sujeto a revisión.
