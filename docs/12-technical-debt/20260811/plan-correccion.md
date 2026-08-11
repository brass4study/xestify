# Plan de corrección — auditoría 2026-08-11

← [Índice de esta auditoría](README.md) · [Progreso de corrección](progreso.md) · [Convenciones (reglas comunes)](../CONVENCIONES.md) · [Índice histórico de auditorías](../README.md)

Con la sesión de auditoría ya muy cargada, la resolución de los 85 hallazgos se aborda en sesiones nuevas y acotadas — cada una con contexto fresco, apuntando directamente al fichero de auditoría en vez de rehacer el análisis desde cero.

Cada hallazgo tiene un ID estable (`01.01`, `07.02`, ...) que se rastrea en [`progreso.md`](progreso.md). **Toda sesión de corrección debe leer primero [`../CONVENCIONES.md`](../CONVENCIONES.md)** — ahí están las reglas comunes a cualquier auditoría (formato de ID, columnas de `progreso.md`, commit único por hallazgo, formato del asunto, orden de ataque recomendado) — y no se repiten aquí. Este fichero solo trae lo específico de esta auditoría: qué hallazgos, en qué orden, y en qué sesiones.

---

## Fase 1 — Los 5 prioritarios (1 sesión)

```
Lee primero docs/12-technical-debt/CONVENCIONES.md (reglas de sesión,
commit único, formato de asunto, columnas de progreso.md) y
docs/12-technical-debt/20260811/progreso.md para confirmar que
ninguno de estos 5 hallazgos está ya resuelto o en progreso por otra
sesión. Vamos a corregir los 5 hallazgos de "Antes de la defensa" en
docs/12-technical-debt/20260811/README.md (detalle completo en
00-informe-consolidado.md). Ábordalos uno a uno, en este orden:
01.01/P1 (password_hash filtrado), 04.03/P5 (comments sin control de
propiedad), 07.01/P2 (EntityEdit bloqueado tras error), 07.02/P3
(botones de PluginManager/PluginConfig rotos), 04.01/P4 (custom_fields
cambia de significado).

Sigue las reglas de CONVENCIONES.md para cada uno. Para la
verificación en concreto: ejecuta la suite de tests relevante para
P1/P4/P5, y usa el skill /run para reproducir P2 y P3 en el navegador
antes y después del arreglo.

Si P4 (04.01) te parece que necesita un rediseño no trivial (no un
simple parche), párate y proponme el enfoque en modo plan antes de
tocar código.
```

## Fase 2 — Barrido por subsistema (MAYOR), una sesión por bloque

Plantilla reutilizable — cambia el número de fichero y, si quieres, agrupa dos ficheros pequeños en la misma sesión:

```
Lee primero docs/12-technical-debt/CONVENCIONES.md y
docs/12-technical-debt/20260811/progreso.md (sección 0N) para ver qué
está ya resuelto o en progreso. Vamos a corregir los hallazgos MAYOR
pendientes de docs/12-technical-debt/20260811/0N-<nombre>.md (deja
MENOR y NIT para otra sesión). Sigue las reglas de CONVENCIONES.md
(commit único, formato de asunto, columnas de progreso.md) para cada
hallazgo o grupo pequeño y relacionado. Verifica con la suite de
tests del subsistema (o /run si es un hallazgo de frontend).
```

Orden sugerido y tamaño de cada sesión (MAYOR restante tras la Fase 1):

| Sesión | Ficheros | MAYOR restante |
|---|---|---|
| 2.1 | `01-backend-core-auth-usuarios.md` | 5 |
| 2.2 | `02-backend-modelo-datos-validacion.md` | 4 |
| 2.3 | `03-backend-motor-plugins.md` + `04-...` (resto) | 3 + 3 |
| 2.4 | `05-frontend-arquitectura-spa.md` | 7 (la más grande, mejor sola) |
| 2.5 | `06-frontend-toolkit-ui.md` + `07-...` (resto) | 4 + 3 |

## Fase 3 — Limpieza MENOR/NIT (opcional)

```
Lee primero docs/12-technical-debt/CONVENCIONES.md y
docs/12-technical-debt/20260811/progreso.md para ver qué MENOR/NIT
siguen pendientes (⏳). Prioriza los de arreglo trivial y bajo riesgo
(código muerto, docblocks desactualizados, duplicación pequeña) sobre
los que requieran tocar varios ficheros. No hace falta cubrirlos
todos en una sola sesión; para y resume qué queda si el alcance crece
demasiado. Sigue las reglas de CONVENCIONES.md igual que en las fases
anteriores.
```

## Fase 4 — Cerrar el círculo

Una vez terminadas las fases que decidas abordar, usa el prompt de "Auditoría incremental" de [`../CONVENCIONES.md`](../CONVENCIONES.md) — compara contra `20260811/`, marca qué se resolvió, y archiva una nueva auditoría fechada. `progreso.md` te da el estado que tú (o las sesiones de corrección) habéis registrado; la re-auditoría incremental es la verificación independiente de esa misma información releyendo el código, así que úsalas juntas como doble comprobación antes de dar algo por cerrado en la memoria del TFM. Eso da además una narrativa de progreso útil para la memoria ("en agosto había 4 críticos, tras N sesiones de corrección quedan 0").
