# Auditoría EPIC 0-10 (2026-08-18)

← [Índice histórico de auditorías](../README.md)

**Alcance:** EPIC 0 a EPIC 10 — todo el código del MVP en `main` (commit `81d0106`), incluida la EPIC 10 completa (nueva desde la auditoría anterior) y los fixes posteriores al 2026-08-11.
**Hallazgos:** 179 totales — 2 crítico · 38 mayor · 85 menor · 54 nit.
**Objetivo:** auditoría completa desde cero (a petición explícita) buscando bugs de correctitud, seguridad, redundancia, complejidad innecesaria, violaciones de clean code, refactors incompletos/perdidos, código muerto y deriva documentación↔código — de cara a la defensa del TFM. Sirve además de verificación independiente del cierre al 100% de la auditoría de 2026-08-11.

## Método (resumen)

Nueve agentes de investigación en paralelo, cada uno acotado a un subsistema (~2.000-5.000 líneas, divididos por límites naturales de EPICs), con lectura completa de los ficheros de su ámbito (no solo `grep`) y contraste contra la documentación de `docs/` y `docs/11-backlog/backlog.md`. La metodología completa y reutilizable está en [`../../SKILL.md`](../../SKILL.md).

> **Límite:** es una auditoría **estática** de lectura de código — no se ejecutó la aplicación ni la suite de tests real. Los hallazgos de "camino roto en producción" deberían confirmarse manualmente antes de citarlos en la defensa.

Existe también una versión navegable (HTML, con el top 5 y los patrones transversales resaltados y los hallazgos crítico/mayor expandibles) publicada como artifact: <https://claude.ai/code/artifact/1eee7f42-6ae3-4288-9922-77eceb48d427>.

Para abordar la corrección en sesiones acotadas, ver [plan-correccion.md](plan-correccion.md) y la skill [`skills/fix-technical-debt/SKILL.md`](../../../fix-technical-debt/SKILL.md). El estado de cada hallazgo se rastrea en [progreso.md](progreso.md) — ese fichero, a diferencia de este informe, se actualiza con el tiempo.

## Contenido

- [00-informe-consolidado.md](00-informe-consolidado.md) — Veredicto global, top 5 antes de la defensa, patrones transversales, resueltos desde 2026-08-11 y estadísticas (179 hallazgos: 2 crítico / 38 mayor / 85 menor / 54 nit)
- [01-backend-core-auth-usuarios.md](01-backend-core-auth-usuarios.md) — Core de infraestructura, autenticación y gestión de usuarios (EPIC 0, 1, 8)
- [02-backend-motor-entidades-validacion.md](02-backend-motor-entidades-validacion.md) — Motor de entidades dinámicas y validación (EPIC 2, 3)
- [03-backend-motor-plugins-nucleo.md](03-backend-motor-plugins-nucleo.md) — Motor de plugins: descubrimiento, ciclo de vida, hooks y administración (EPIC 4, 7)
- [04-backend-plugins-schema-extensiones.md](04-backend-plugins-schema-extensiones.md) — Schema/configuración de plugins y plugins extension (EPIC 6, 7, 10)
- [05-frontend-arquitectura-spa.md](05-frontend-arquitectura-spa.md) — Shell SPA, routing, sesión, API client y modelos (EPIC 9)
- [06-frontend-toolkit-ui.md](06-frontend-toolkit-ui.md) — Toolkit de componentes UI base y layouts (EPIC 9)
- [07-frontend-modulos-dinamicos.md](07-frontend-modulos-dinamicos.md) — Módulos dinámicos y de navegación de negocio (EPIC 3, 5, 9)
- [08-frontend-paginas.md](08-frontend-paginas.md) — Páginas de la SPA (EPIC 5-8, 10)
- [09-plugins-demo-seeders.md](09-plugins-demo-seeders.md) — Plugins de demostración y seeders de datos (EPIC 6.4, 10)

## Antes de la defensa (los 5 hallazgos con más impacto)

1. **`04.01` (Crítico)** — Guardar la configuración de un plugin lo marca "corrupto" en sync y bloquea sus updates — ver [04](04-backend-plugins-schema-extensiones.md)
2. **`05.01` (Crítico)** — La expiración de sesión a mitad de navegación borra el formulario de login y deja al usuario sin poder autenticarse — ver [05](05-frontend-arquitectura-spa.md)
3. **`03.01` (Mayor)** — `STDERR` no existe bajo Apache: el fallo "non-blocking" de un hook after* se convierte en un 500 tras persistir el registro — ver [03](03-backend-motor-plugins-nucleo.md)
4. **`03.02` (Mayor)** — Un plugin activo con `Hooks.php` roto tumba toda la API, incluido el endpoint para desactivarlo — ver [03](03-backend-motor-plugins-nucleo.md)
5. **`01.01` (Mayor)** — Tokens irrevocables + sesión deslizante: un usuario borrado o degradado conserva acceso indefinidamente — ver [01](01-backend-core-auth-usuarios.md)

---

Esta auditoría es un punto de partida para priorizar correcciones, no un veredicto de calidad global: ningún hallazgo invalida las decisiones de arquitectura de EPIC 0-10, y la comparación con 2026-08-11 muestra una mejora sustancial del núcleo.
