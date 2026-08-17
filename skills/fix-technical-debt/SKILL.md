---
name: fix-technical-debt
description: Corrige hallazgos ya registrados en una auditoría de deuda técnica de skills/audit-technical-debt/archive/<YYYYMMDD>/, detectando sola qué fase/sesión toca a continuación (prioritarios "antes de la defensa", barrido MAYOR por subsistema, limpieza MENOR/NIT) según plan-correccion.md y progreso.md, y ejecutándola hallazgo a hallazgo con commit y push por cada uno. Usar cuando el usuario pida "corrige la deuda técnica", "sigue con la corrección de la auditoría", "corrige los hallazgos prioritarios/antes de la defensa", "ejecuta la sesión 2.3 de corrección", "haz el barrido mayor del subsistema X", "limpia los MENOR/NIT pendientes", o continúe una sesión de corrección de deuda técnica ya empezada. No genera auditorías nuevas ni hace la re-auditoría de cierre (Fase 4) — para eso usar skills/audit-technical-debt/SKILL.md.
---

# Fix Technical Debt

Ejecuta sesiones de corrección sobre una auditoría de deuda técnica ya
generada en `skills/audit-technical-debt/archive/<YYYYMMDD>/`. Es una skill
de **ejecución**: no te quedes en proponer qué tocaría — aplica el arreglo,
el test, el commit y el push de cada hallazgo, salvo que la propia petición
del usuario sea solo "qué tocaría corregir ahora" (análisis).

El formato de `progreso.md` (columnas, leyenda de estado) y la estructura
de carpeta de una auditoría están descritos en
`skills/audit-technical-debt/SKILL.md` — no se repiten aquí.

## 1. Elegir auditoría y sesión

1. **Fecha**: si el usuario da una fecha explícita (o algo identificable
   como "la auditoría del 11 de agosto"), usa esa subcarpeta `YYYYMMDD/`
   dentro de `skills/audit-technical-debt/archive/`. Si no dice nada, usa
   la subcarpeta con la fecha numéricamente más alta. Si no existe
   ninguna, no hay nada que corregir todavía — dilo y sugiere
   `skills/audit-technical-debt/SKILL.md`.
2. Lee `progreso.md` y `plan-correccion.md` de esa fecha completos (y
   `README.md`/`00-informe-consolidado.md` si necesitas el detalle de un
   hallazgo concreto) para saber qué sigue pendiente.
3. **Si el usuario pide una fase/sesión concreta explícitamente** ("fase 2",
   "sesión 2.3", "los prioritarios", "antes de la defensa", "limpieza
   menor/nit") — usa esa directamente, sin aplicar la detección automática
   del punto 4. Si esa fase/sesión ya está completa en `progreso.md`, dilo y
   pregunta cómo seguir en vez de recorrerla igualmente.
4. **Si no especifica nada, detecta la siguiente sesión en este orden**
   (excluyendo de "pendiente" tanto `✅ Resuelto` como `🚫 Descartado`):
   a. ¿Queda algún ID de la lista "antes de la defensa" (top de `README.md`
      de esa fecha, detallado en `plan-correccion.md` Fase 1) sin resolver
      ni descartar? → toca **Fase 1**, en el orden en que aparecen
      listados.
   b. Si no, recorre la tabla de sesiones de la Fase 2 de
      `plan-correccion.md` en orden (2.1, 2.2, ...) y ejecuta la
      **primera** cuyo(s) fichero(s) de subsistema todavía tengan algún
      hallazgo MAYOR pendiente, acotada exactamente a ese fichero (o
      pareja de ficheros).
   c. La **Fase 3 (MENOR/NIT) nunca se dispara sola** por ser opcional —
      solo entra si el usuario la pide explícitamente (ver punto 3).
   d. Si la Fase 1 completa y toda la Fase 2 quedan resueltas/descartadas
      (y la Fase 3, si el usuario la había pedido, también) — no toques
      código. Informa que corresponde **Fase 4** (re-auditoría
      incremental) y que esta skill no la cubre: sugiere invocar
      `skills/audit-technical-debt/SKILL.md` en modo incremental.
5. **Comprueba `git status`**: si hay cambios sin commitear de otra tarea,
   avisa y pregunta cómo seguir antes de mezclar diffs de otra sesión con
   los de esta.
6. **Confirma en una línea** qué sesión concreta vas a ejecutar (fecha +
   fase + IDs o fichero) antes de tocar código — salvo que el usuario ya lo
   haya especificado él mismo con ese mismo nivel de detalle.

## 2. Las 4 fases

- **Fase 1 — prioritarios ("antes de la defensa")**: los hallazgos más
  baratos de corregir y los que más duelen (seguridad, roturas de demo en
  vivo). Ábordalos uno a uno, en el orden del top de esa auditoría.
  Verificación ajustada al tipo de hallazgo: suite de tests relevante para
  bugs de backend, skill `/run` para reproducir en el navegador los bugs de
  frontend deducidos leyendo código (no ejecutando la app). Si algún
  hallazgo parece necesitar un rediseño no trivial (no un simple parche),
  párate y propón el enfoque en modo plan antes de tocar código.
- **Fase 2 — barrido por subsistema (solo MAYOR)**: una sesión por bloque —
  un fichero `0N-<subsistema>.md` de `plan-correccion.md`, o dos ficheros
  pequeños fusionados si `plan-correccion.md` los agrupa así. Deja MENOR y
  NIT para otra sesión. Verifica con la suite de tests del subsistema (o
  `/run` si es un hallazgo de frontend).
- **Fase 3 — limpieza MENOR/NIT (opcional)**: solo si queda tiempo; son
  mejoras de mantenibilidad, no bugs. Prioriza los de arreglo trivial y
  bajo riesgo (código muerto, docblocks desactualizados, duplicación
  pequeña) sobre los que requieran tocar varios ficheros. No hace falta
  cubrirlos todos en una sola sesión; para y resume qué queda si el
  alcance crece demasiado.
- **Fase 4 — cerrar el círculo**: no tiene lógica propia en esta skill —
  usa `skills/audit-technical-debt/SKILL.md` en modo incremental,
  comparando contra la subcarpeta `YYYYMMDD` que se acaba de trabajar.
  `progreso.md` da el estado que las sesiones de corrección han
  registrado; la re-auditoría incremental es la verificación
  independiente de esa misma información releyendo el código — úsalas
  juntas como doble comprobación antes de dar algo por cerrado.

## 3. Ejecutar la sesión

Reglas de sesión, hallazgo a hallazgo:

1. Lee `progreso.md` de la fila/hallazgo que toque **antes de tocar
   código**, para no repetir un hallazgo ya resuelto ni chocar con un
   diff a medias de otra sesión. Si dos hallazgos tocan el mismo fichero,
   anótalo en `Notas` con `⚠️ toca el mismo fichero que 0N.MM`.
2. **Trabaja los hallazgos uno a uno, nunca en batch.** Si la sesión cubre
   varios (p. ej. varios MAYOR de un mismo fichero en Fase 2), complétalos
   en serie: aplica el arreglo + test de un solo hallazgo, verifica,
   commitea y haz push, y solo entonces pasa al siguiente. No implementes
   primero todos los hallazgos de la sesión para partir el diff en commits
   después, aunque compartan fichero o haya dependencias entre ellos.
3. **Relee fichero:línea antes de aplicar nada** — los números pudieron
   desplazarse desde que se escribió la auditoría.
4. **Arreglo mínimo descrito, sin refactorizar de más.**
5. **Añade o ajusta un test que habría detectado el bug** — idealmente
   confirmando que falla sin el fix y pasa con él.
6. **Verifica de verdad antes de cerrar el hallazgo:** ejecuta la suite de
   tests relevante, o usa la skill `/run` para reproducir en el navegador
   los bugs de frontend que se dedujeron leyendo código.
7. **Un único commit por hallazgo — nunca un commit de fix y otro de docs
   aparte.** El commit incluye a la vez el código, el test
   añadido/ajustado, y la fila de `progreso.md` actualizada (`Estado`,
   `Commit`, `Notas`).
8. **La descripción verbosa va en el cuerpo del commit, no en
   `progreso.md`.** El asunto es breve (regla siguiente); el cuerpo explica
   causa raíz, el arreglo aplicado (ficheros/métodos tocados), los tests
   añadidos/ajustados y cómo se confirmó. `Notas` en `progreso.md` se
   queda corta: solo advertencias puntuales que otra sesión necesite ver
   sin abrir el commit.
9. **Asunto de commit:** fecha de la auditoría + ID entre corchetes:
   ```
   fix: auditoria [YYYYMMDD][0N.MM] <resumen breve del arreglo>
   ```
   p. ej. `fix: auditoria [20260811][01.01] password_hash ya no se filtra
   en /api/v1/users`. La fecha identifica de qué auditoría viene el
   hallazgo sin abrir el commit, imprescindible en cuanto haya más de una
   auditoría en el histórico y los IDs se reutilicen entre fechas.
10. **Muestra el mensaje completo y espera confirmación explícita antes de
    cada `git commit`**, incluido el amend del paso siguiente — regla
    general del proyecto, sin excepción, se aplica encima de estas reglas
    de auditoría.
11. **Hash del propio commit en la fila `Commit`:** commitea, copia el
    hash corto (`git log -1 --format=%h`), rellénalo en la fila, y haz
    **un único** `git commit --amend --no-edit` (con su propia
    confirmación). No repitas el amend persiguiendo que el hash coincida
    exacto con el hash final — un hash "una versión por detrás" es el
    resultado esperado y suficiente.
12. **Push justo después de completar el paso 11**, no al final de la
    sesión — un hallazgo no está cerrado hasta que ese push se hace (se
    ejecuta automáticamente tras un commit ya confirmado, sin
    confirmación aparte, salvo que el usuario indique lo contrario). No
    dejes commits sin subir acumularse a lo largo de una sesión larga.

## 4. Fin de sesión

Al terminar los hallazgos de la sesión actual (o al parar por rediseño no
trivial o por alcance), resume el resultado con esta estructura:

```text
Sesión de corrección — <YYYYMMDD>, <Fase N / sesión X.X / "prioritarios" / "limpieza menor-nit">

Resueltos
- <ID> (<commit corto>): <resumen breve>

Pendiente de esta sesión
- <ID>: <motivo si no se terminó> / "ninguno, sesión completa"

Siguiente sesión sugerida
- <fase/sesión siguiente según la lógica de la sección 1, o "Fase 4 (re-auditoría incremental) vía skills/audit-technical-debt/SKILL.md">
```

**Nunca encadenes automáticamente la siguiente sesión**, ni si el usuario
pide explícitamente "hazlo todo seguido" — cada sesión es una unidad
revisable aparte, el mismo motivo que obliga el commit único por hallazgo.
Si insiste, explica por qué te paras igual y ofrece relanzar la skill para
la siguiente sesión.

## Cuándo NO usar esta skill

- Generar una auditoría nueva (completa, incremental o acotada) → usa
  `skills/audit-technical-debt/SKILL.md`.
- Fase 4 / cierre de una auditoría (re-auditoría incremental) → delega en
  `skills/audit-technical-debt/SKILL.md` en modo incremental; esta skill no
  re-audita.
- No existe todavía ninguna subcarpeta `YYYYMMDD/` en
  `skills/audit-technical-debt/archive/` → no hay nada que corregir; usa
  `skills/audit-technical-debt/SKILL.md` primero.

## Test Prompts

Usa `evals/evals.json` como conjunto de validación ligero de esta skill.
Cubre: detección automática de fecha+fase sin que el usuario especifique
nada, sesión explícita saltando la detección, Fase 3 solo bajo petición
explícita, y el caso "todo resuelto" que debe delegar en Fase 4 sin tocar
código.
