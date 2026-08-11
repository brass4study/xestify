# Auditoría EPIC 0-9 (2026-08-11)

← [Índice histórico de auditorías](../README.md)

**Alcance:** EPIC 0 a EPIC 9 — corte completo del MVP construido hasta esta fecha (asistido por GitHub Copilot, con refactors manuales puntuales).
**Hallazgos:** 85 totales — 4 crítico · 30 mayor · 40 menor · 11 nit.
**Objetivo:** revisar el trabajo construido de forma incremental buscando bugs de correctitud, redundancia, complejidad innecesaria, violaciones de clean code, refactors incompletos/perdidos y código muerto o inalcanzable — de cara a la defensa del TFM.

## Método (resumen)

Siete agentes de investigación en paralelo, cada uno acotado a un subsistema, con lectura completa de los ficheros de su ámbito (no solo `grep`) y contraste contra la documentación de `docs/` y `docs/11-backlog/backlog.md`. La metodología completa y reutilizable (para lanzar la próxima auditoría) está en el [índice histórico](../README.md).

> **Límite:** es una auditoría **estática** de lectura de código — no se ejecutó la aplicación ni la suite de tests real en este entorno. Los hallazgos de "camino roto en producción" deberían confirmarse manualmente antes de citarlos en la defensa.

Existe también una versión navegable (HTML, con hallazgos priorizados y patrones transversales resaltados) publicada como artifact: ver [00-informe-consolidado.md](00-informe-consolidado.md) para el enlace y el resumen ejecutivo completo.

## Contenido

- [00-informe-consolidado.md](00-informe-consolidado.md) — Resumen ejecutivo, prioridades antes de la defensa, patrones transversales y estadísticas globales (85 hallazgos: 4 crítico / 30 mayor / 40 menor / 11 nit)
- [01-backend-core-auth-usuarios.md](01-backend-core-auth-usuarios.md) — Core de infraestructura, autenticación y gestión de usuarios (EPIC 0, 1, 8)
- [02-backend-modelo-datos-validacion.md](02-backend-modelo-datos-validacion.md) — Modelo de datos, motor de entidades dinámicas y validación (EPIC 2, 3)
- [03-backend-motor-plugins.md](03-backend-motor-plugins.md) — Motor de plugins y hooks — núcleo (EPIC 4)
- [04-backend-plugins-actualizacion-extension.md](04-backend-plugins-actualizacion-extension.md) — Actualización de plugins, rollback, extensiones y configuración (EPIC 6, 7)
- [05-frontend-arquitectura-spa.md](05-frontend-arquitectura-spa.md) — Arquitectura del shell SPA (EPIC 9, bases EPIC 3/5)
- [06-frontend-toolkit-ui.md](06-frontend-toolkit-ui.md) — Toolkit de componentes UI y layouts (base EPIC 5, ampliado EPIC 9)
- [07-frontend-paginas-modulos.md](07-frontend-paginas-modulos.md) — Páginas y módulos de negocio (EPIC 3, 5, 6, 7, 8)

## Antes de la defensa (resumen de los 5 hallazgos con más impacto)

1. `password_hash` se filtra en las respuestas JSON de `/api/v1/users*` — ver [01](01-backend-core-auth-usuarios.md)
2. `EntityEdit.js` bloquea el formulario para siempre tras el primer error de validación — ver [07](07-frontend-paginas-modulos.md)
3. Los botones de `PluginManager`/`PluginConfig` dejan de responder tras usar el toolbar de la tabla — ver [07](07-frontend-paginas-modulos.md)
4. `custom_fields` cambia de significado tras guardar configuración y puede bloquear futuras actualizaciones de un plugin — ver [04](04-backend-plugins-actualizacion-extension.md)
5. Cualquier usuario autenticado puede editar/reasignarse comentarios ajenos (plugin `comments`) — ver [04](04-backend-plugins-actualizacion-extension.md)

---

Esta auditoría es un punto de partida para priorizar correcciones, no un veredicto de calidad global: ningún hallazgo invalida las decisiones de arquitectura tomadas en EPIC 0-9.
