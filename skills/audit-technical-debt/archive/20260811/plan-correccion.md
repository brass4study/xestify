# Plan de corrección — auditoría 2026-08-11

← [Índice de esta auditoría](README.md) · [Progreso de corrección](progreso.md) · [Reglas de corrección](../../../fix-technical-debt/SKILL.md) · [Índice histórico de auditorías](../README.md)

Los pasos de sesión (por fase), las reglas de commit y el resto de la metodología **no viven aquí** — son comunes a cualquier auditoría y están en [`skills/fix-technical-debt/SKILL.md`](../../../fix-technical-debt/SKILL.md). Este fichero solo trae los datos específicos de esta auditoría concreta: qué hallazgos entran en cada fase, en qué sesiones se agrupan.

---

## Fase 1 — Los 5 prioritarios (1 sesión)

Usa la sección "Fase 1 — prioritarios" de [`skills/fix-technical-debt/SKILL.md`](../../../fix-technical-debt/SKILL.md), con estos datos:

- **Hallazgos, en este orden:** `01.01`/P1 (password_hash filtrado), `04.03`/P5 (comments sin control de propiedad), `07.01`/P2 (EntityEdit bloqueado tras error), `07.02`/P3 (botones de PluginManager/PluginConfig rotos), `04.01`/P4 (custom_fields cambia de significado).
- **Verificación:** suite de tests relevante para P1/P4/P5; skill `/run` para reproducir P2 y P3 en el navegador antes y después del arreglo.
- **Nota:** si P4 (`04.01`) parece necesitar un rediseño no trivial (no un simple parche), parar y proponer el enfoque en modo plan antes de tocar código.

## Fase 2 — Barrido por subsistema (MAYOR), una sesión por bloque

Usa la sección "Fase 2 — barrido por subsistema" de [`skills/fix-technical-debt/SKILL.md`](../../../fix-technical-debt/SKILL.md), una vez por fila de esta tabla (MAYOR restante tras la Fase 1):

| Sesión | Ficheros | MAYOR restante |
|---|---|---|
| 2.1 | `01-backend-core-auth-usuarios.md` | 5 |
| 2.2 | `02-backend-modelo-datos-validacion.md` | 4 |
| 2.3 | `03-backend-motor-plugins.md` + `04-...` (resto) | 3 + 3 |
| 2.4 | `05-frontend-arquitectura-spa.md` | 7 (la más grande, mejor sola) |
| 2.5 | `06-frontend-toolkit-ui.md` + `07-...` (resto) | 4 + 3 |

## Fase 3 — Limpieza MENOR/NIT (opcional)

Usa la sección "Fase 3 — limpieza MENOR/NIT" de [`skills/fix-technical-debt/SKILL.md`](../../../fix-technical-debt/SKILL.md) tal cual, sin datos adicionales que rellenar.

## Fase 4 — Cerrar el círculo

Usa `skills/audit-technical-debt/SKILL.md` en modo incremental, comparando contra `20260811/`.
