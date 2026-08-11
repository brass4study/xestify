# Plan de corrección — auditoría 2026-08-11

← [Índice de esta auditoría](README.md) · [Progreso de corrección](progreso.md) · [Índice histórico de auditorías](../README.md)

Con la sesión de auditoría ya muy cargada, la resolución de los 85 hallazgos se aborda en sesiones nuevas y acotadas — cada una con contexto fresco, apuntando directamente al fichero de auditoría en vez de rehacer el análisis desde cero.

Cada hallazgo tiene un ID estable (`01.1`, `07.2`, ...) que se rastrea en [`progreso.md`](progreso.md) — ese fichero, a diferencia de los informes `00`-`07`, sí se va actualizando sesión a sesión. **Toda sesión de corrección debe leerlo antes de empezar y actualizarlo al terminar**, para no repetir trabajo ya hecho ni pisar un diff a medias de otra sesión.

## Orden de ataque

1. **Los 5 "antes de la defensa" primero** — son baratos (4 de 5 son cambios de pocas líneas) y cubren lo que más duele: dos de seguridad, dos que rompen una demo en vivo.
2. **Barrido por subsistema, solo MAYOR** — una sesión por fichero `0N-*.md` (o dos fusionados si son pequeños), sin tocar MENOR/NIT todavía.
3. **Limpieza MENOR/NIT** — opcional, solo si queda tiempo antes de la defensa; son mejoras de mantenibilidad, no bugs.
4. **Re-auditoría incremental** — cierra el círculo y deja constancia de qué se resolvió.

Regla general para cada sesión nueva: que empiece leyendo `progreso.md` (o la fila del hallazgo que toque) para confirmar que nadie lo tocó ya; que relea fichero:línea antes de tocar nada (los números pudieron desplazarse desde la auditoría); que aplique el arreglo mínimo descrito sin aprovechar para refactorizar de más; que añada/ajuste el test que debería haber cazado el bug; que verifique de verdad antes de dar por cerrado el hallazgo — con `/run` para los dos bugs de frontend, que se dedujeron leyendo código, no ejecutando la app; y que actualice su fila en `progreso.md` (estado, commit, notas) **en el mismo commit que el arreglo** (código + test + `progreso.md` juntos; nunca un commit de docs aparte) antes de pasar al siguiente. Convención de commit: el asunto incluye el ID entre corchetes con padding a dos dígitos, p. ej. `fix: [01.01] password_hash ya no se filtra en /api/v1/users`. Para el hash en la fila `Commit`: commitea, copia el hash corto (`git log -1 --format=%h`), rellénalo en `progreso.md`, y **un único** `git commit --amend --no-edit`. No repitas el amend intentando que coincida exacto — un commit no puede contener su propio hash final (cada amend lo cambia de nuevo), así que un hash "una versión por detrás" es el resultado esperado y suficiente; la referencia exacta siempre está en `git log --oneline --grep`.

---

## Fase 1 — Los 5 prioritarios (1 sesión)

```
Lee primero docs/12-technical-debt/20260811/progreso.md para
confirmar que ninguno de estos 5 hallazgos está ya resuelto o en
progreso por otra sesión. Vamos a corregir los 5 hallazgos de "Antes
de la defensa" en docs/12-technical-debt/20260811/README.md (detalle
completo en 00-informe-consolidado.md). Ábordalos uno a uno, en este
orden: 01.01/P1 (password_hash filtrado), 04.03/P5 (comments sin
control de propiedad), 07.01/P2 (EntityEdit bloqueado tras error),
07.02/P3 (botones de PluginManager/PluginConfig rotos), 04.01/P4
(custom_fields cambia de significado).

Para cada uno:
1. Relee el fichero y línea citados para confirmar que el hallazgo
   sigue vigente (las líneas pueden haberse movido).
2. Aplica el arreglo mínimo descrito, sin refactorizar nada más.
3. Añade o ajusta un test que habría detectado el bug.
4. Verifica: ejecuta la suite de tests relevante para P1/P4/P5, y usa
   el skill /run para reproducir P2 y P3 en el navegador antes y
   después del arreglo.
5. Haz UN ÚNICO commit por hallazgo que incluya el código, el test Y
   la fila actualizada de progreso.md (estado, commit, notas) — nunca
   un commit de docs aparte. Asunto con el ID en el asunto, p. ej.
   "fix: [01.01] password_hash ya no se filtra en /api/v1/users".
   Para el hash en la fila `Commit`: commitea, copia el hash corto
   (`git log -1 --format=%h`), rellénalo en progreso.md, y un único
   `git commit --amend --no-edit`. No repitas el amend intentando que
   coincida exacto (un commit no puede contener su propio hash final);
   un hash "una versión por detrás" es suficiente, la referencia
   exacta siempre está en `git log --oneline --grep`.

Si P4 (04.1) te parece que necesita un rediseño no trivial (no un
simple parche), párate y proponme el enfoque en modo plan antes de
tocar código.
```

## Fase 2 — Barrido por subsistema (MAYOR), una sesión por bloque

Plantilla reutilizable — cambia el número de fichero y, si quieres, agrupa dos ficheros pequeños en la misma sesión:

```
Lee primero docs/12-technical-debt/20260811/progreso.md (sección
0N) para ver qué está ya resuelto o en progreso. Vamos a corregir
los hallazgos MAYOR pendientes de docs/12-technical-debt/20260811/
0N-<nombre>.md (deja MENOR y NIT para otra sesión). Antes de tocar
nada, relee cada fichero:línea citado para confirmar que sigue
vigente. Aplica arreglos mínimos y acotados, añade o ajusta el test
que debería haberlo cazado, y verifica con la suite de tests del
subsistema (o /run si es un hallazgo de frontend). Un único commit
por hallazgo (o por grupo pequeño y relacionado) que incluya código,
test y la fila actualizada de progreso.md a la vez (estado, commit,
notas) — nunca un commit de docs aparte. Asunto con el ID en el
asunto con padding a dos dígitos (p. ej. "fix: [0N.MM] ..."); usa un
único `git commit --amend --no-edit` justo después para rellenar el
hash en progreso.md (no lo repitas persiguiendo el hash exacto: un
commit no puede contener su propio hash final).
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
Lee primero docs/12-technical-debt/20260811/progreso.md para ver qué
MENOR/NIT siguen pendientes (⏳). Prioriza los de arreglo trivial y
bajo riesgo (código muerto, docblocks desactualizados, duplicación
pequeña) sobre los que requieran tocar varios ficheros. No hace falta
cubrirlos todos en una sola sesión; para y resume qué queda si el
alcance crece demasiado. Commit con el ID en el asunto y actualiza
progreso.md según avances, igual que en las fases anteriores.
```

## Fase 4 — Cerrar el círculo

Una vez terminadas las fases que decidas abordar, usa el prompt de "Auditoría incremental" que ya quedó guardado en [`docs/12-technical-debt/README.md`](../README.md) — compara contra `20260811/`, marca qué se resolvió, y archiva una nueva auditoría fechada. `progreso.md` te da el estado que tú (o las sesiones de corrección) habéis registrado; la re-auditoría incremental es la verificación independiente de esa misma información releyendo el código, así que úsalas juntas como doble comprobación antes de dar algo por cerrado en la memoria del TFM. Eso da además una narrativa de progreso útil para la memoria ("en agosto había 4 críticos, tras N sesiones de corrección quedan 0").
