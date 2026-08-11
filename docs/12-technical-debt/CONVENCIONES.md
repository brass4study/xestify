# Convenciones — Auditorías de deuda técnica

← [Índice de auditorías](README.md)

Reglas, formatos y prompts reutilizables para generar y corregir **cualquier** auditoría de esta carpeta. Este fichero es el único punto de verdad: cada auditoría fechada (`YYYYMMDD/README.md`, `plan-correccion.md`, `progreso.md`) enlaza aquí en vez de repetir las reglas. Si una convención cambia, se actualiza **solo aquí** — las auditorías ya publicadas no se reescriben, pero cualquier sesión nueva (de auditoría o de corrección) sigue la versión vigente de este fichero, no lo que copiara una auditoría antigua.

---

## Estructura de cada subcarpeta fechada

```
YYYYMMDD/
  README.md                          — índice de esa auditoría + top 3-5 "antes de la defensa"
  00-informe-consolidado.md          — veredicto, prioridades, patrones transversales, tabla por subsistema
  01-<subsistema>.md ... NN-<...>.md — un fichero por subsistema auditado, hallazgos completos con fichero:línea
  plan-correccion.md                 — hoja de ruta para resolver los hallazgos en sesiones futuras, con prompts listos para reutilizar
  progreso.md                        — checklist mutable con el estado de cada hallazgo (la única pieza que se actualiza con el tiempo)
```

`plan-correccion.md` y `progreso.md` se generan junto con la auditoría, no como una tarea aparte.

## Severidad

4 niveles:

- **Crítico** — rompe un flujo de uso normal, o es un problema de seguridad con impacto directo.
- **Mayor** — bug real o deuda que compromete mantenibilidad seria.
- **Menor** / **Nit** — limpieza o consistencia sin impacto funcional.

## ID de hallazgo

- Dentro de cada fichero `0N-<subsistema>.md`, numera los hallazgos de forma correlativa y única a través de todas las severidades (sin reiniciar por sección). El ID completo es `0N.MM`, con **padding a dos dígitos en ambas mitades** (`05.07`, no `05.7`) — mantiene la tabla de `progreso.md` alineada y ordenable como texto.
- **Estable dentro de una auditoría, no entre auditorías distintas.** El hallazgo `05.07` de una fecha no es necesariamente "lo mismo" que `05.07` de otra fecha — los ficheros de subsistema y su numeración pueden reorganizarse de una auditoría a otra. Cita siempre **fecha + fichero + ID** juntos (p. ej. `20260811/05-....md, hallazgo 05.07`) — por eso el commit lleva también la fecha de la auditoría (ver más abajo).
- Cada hallazgo incluye: fichero:línea, descripción del problema y por qué importa, y una sugerencia de arreglo — no basta con señalar, hay que dejar claro el camino de solución.
- El top **"antes de la defensa"** (3-5 hallazgos de mayor impacto real: seguridad o rotura de un flujo de demo, en cualquier subsistema) referencia siempre el ID (`0N.MM`), no solo una etiqueta "P1, P2...", para que quede trazable en `progreso.md`.
- Identifica además 3-5 **patrones transversales** que se repitan en varios subsistemas a la vez — son más útiles de explicar en una defensa que cualquier hallazgo suelto.
- Metodología y límites del análisis siempre explícitos (p. ej. si fue lectura estática de código sin ejecutar tests/app) — evita que un hallazgo dudoso se dé por verificado sin serlo.

## `progreso.md` — columnas y estado

Orden de columnas: **`ID | Estado | Sev. | Resumen | Commit | Notas`**.

`Estado` va en segunda posición (justo después del ID) a propósito: es el dato que una sesión nueva necesita leer primero — "¿esto ya está resuelto?" — y ponerlo al final de la fila obliga a escanear todo para encontrarlo. `Sev.` y `Resumen` dan contexto; `Commit`/`Notas` van al final porque son detalle de seguimiento, no de triage.

Leyenda de estado: ⏳ Pendiente · 🔧 En progreso · ✅ Resuelto · 🚫 Descartado (con motivo en `Notas`).

## Reglas de sesión de corrección

1. **Lee `progreso.md` antes de tocar código** (al menos la fila o sección del hallazgo que toque), para no repetir un hallazgo ya resuelto ni chocar con un diff a medias de otra sesión. Si dos hallazgos tocan el mismo fichero, anótalo en `Notas` con `⚠️ toca el mismo fichero que 0N.MM`.
2. **Relee fichero:línea antes de aplicar nada** — los números pudieron desplazarse desde que se escribió la auditoría.
3. **Arreglo mínimo descrito, sin refactorizar de más.**
4. **Añade o ajusta un test que habría detectado el bug** — idealmente confirmando que falla sin el fix y pasa con él.
5. **Verifica de verdad antes de cerrar el hallazgo:** ejecuta la suite de tests relevante, o usa el skill `/run` para reproducir en el navegador los bugs de frontend que se dedujeron leyendo código (no ejecutando la app).
6. **Un único commit por hallazgo — nunca un commit de fix y otro de docs aparte.** El commit incluye a la vez el código, el test añadido/ajustado, y la fila de `progreso.md` actualizada (`Estado`, `Commit`, `Notas`).
7. **Convención de asunto de commit:** fecha de la auditoría + ID entre corchetes, en ese orden:
   ```
   fix: auditoria [YYYYMMDD][0N.MM] <resumen breve del arreglo>
   ```
   p. ej. `fix: auditoria [20260811][01.01] password_hash ya no se filtra en /api/v1/users`. La fecha identifica de qué auditoría viene el hallazgo sin abrir el commit — imprescindible en cuanto haya más de una auditoría en el histórico y los IDs se reutilicen entre fechas. Así `git log --oneline --grep "\[20260811\]\[04"` encuentra todo lo tocado de un fichero de esa auditoría de un vistazo, sin depender de que `progreso.md` esté sincronizado.
8. **Hash del propio commit en la fila `Commit`:** commitea, copia el hash corto (`git log -1 --format=%h`), rellénalo en la fila, y haz **un único** `git commit --amend --no-edit`. No repitas el amend intentando que el hash escrito coincida exacto con el hash final: un commit no puede contener su propio hash (el hash se calcula a partir del contenido, así que cada amend lo cambia de nuevo) — perseguirlo entra en bucle infinito. Un hash "una versión por detrás" es el resultado esperado y suficiente; la referencia exacta siempre está en `git log --oneline --grep`.
9. **La re-auditoría incremental de cierre es la verificación independiente**: no se limita a confiar en lo que `progreso.md` dice resuelto, relee el código y lo confirma — trátalas como una comprobación doble, no como pasos redundantes.

## Orden de ataque recomendado

1. **Los "antes de la defensa" primero** — son los más baratos y cubren lo que más duele (seguridad, roturas de demo en vivo).
2. **Barrido por subsistema, solo MAYOR** — una sesión por fichero `0N-*.md` (o varios fusionados si son pequeños), sin tocar MENOR/NIT todavía.
3. **Limpieza MENOR/NIT** — opcional, solo si queda tiempo; son mejoras de mantenibilidad, no bugs.
4. **Re-auditoría incremental** — cierra el círculo y deja constancia de qué se resolvió.

---

## Metodología para generar una auditoría nueva

1. Divide el código en subsistemas acotados (~2.000-4.000 líneas cada uno) siguiendo los límites naturales de EPICs relacionadas, no por carpeta mecánica.
2. Lanza agentes de investigación **en paralelo**, uno por subsistema, cada uno instruido para:
   - Leer los ficheros **completos** de su ámbito (no solo `grep`).
   - Buscar explícitamente: bugs de correctitud, redundancia/duplicación, complejidad innecesaria, violaciones de clean code, señales de refactors incompletos o perdidos (patrones inconsistentes entre ficheros similares, comentarios/tests que ya no casan con el código), código muerto o inalcanzable.
   - Contrastar contra la documentación en `docs/` y `docs/11-backlog/backlog.md` para detectar deriva documentación↔código.
   - Devolver hallazgos con fichero:línea, severidad, descripción y arreglo sugerido, más una nota de cobertura de tests del subsistema.
3. Sintetiza los informes individuales en un informe consolidado: veredicto global, top 3-5 "antes de la defensa", patrones transversales, y una tabla resumen por subsistema.
4. Publica el informe consolidado también como página HTML navegable (artifact), y archiva todo el conjunto (consolidado + informes individuales) en Markdown, en la subcarpeta fechada.

## Prompts reutilizables

**Auditoría completa nueva** (arranca desde cero, útil tras un salto grande de trabajo):

> Analiza en profundidad el estado actual del proyecto (desde `<punto de partida>` hasta `<punto actual>`) para saber si el trabajo hecho es correcto. Comprueba que no haya redundancia, complejidad innecesaria, violaciones de clean code, refactorizaciones perdidas ni código inalcanzable. Divide el análisis en subsistemas acotados y usa agentes en paralelo para leer el código completo de cada uno. Al terminar, sintetiza en un informe consolidado (veredicto, top 3-5 antes de la defensa, patrones transversales, tabla por subsistema), publícalo como artifact navegable, y guarda el consolidado + un fichero por subsistema en `docs/12-technical-debt/<YYYYMMDD>/`. Sigue las convenciones de `docs/12-technical-debt/CONVENCIONES.md` (severidad, formato de ID `0N.MM`, estructura de carpeta) — no las repitas, solo referéncialas. Genera también `plan-correccion.md` (hoja de ruta por fases con prompts reutilizables, sin repetir las reglas de `CONVENCIONES.md`) y `progreso.md` (checklist con una fila por hallazgo, todas en estado pendiente al arrancar, con las columnas en el orden de `CONVENCIONES.md`). Actualiza también `docs/12-technical-debt/README.md` con la nueva fila en la tabla de auditorías.

**Auditoría incremental** (compara contra la última auditoría en vez de repetir todo):

> Compara el estado actual del código con la auditoría más reciente en `docs/12-technical-debt/` (la subcarpeta con la fecha más alta). Usa su `progreso.md` como punto de partida, pero no te fíes solo de él: para cada hallazgo crítico/mayor de esa auditoría relee el código y confirma independientemente si sigue abierto, si se corrigió, o si cambió de forma — si confirmas una corrección, marca esa fila como `✅ Resuelto` en el `progreso.md` de la auditoría anterior (con el commit si lo encuentras por `git log`/`git blame`). Busca también hallazgos nuevos que no estaban en la auditoría anterior. Guarda el resultado como una auditoría nueva fechada hoy (con su propio `00-informe-consolidado.md`, ficheros por subsistema, `plan-correccion.md` y `progreso.md` propios para lo que siga abierto o sea nuevo, siguiendo `docs/12-technical-debt/CONVENCIONES.md`), sin modificar ni borrar la auditoría anterior, y añade una sección "Resueltos desde la última auditoría" en el informe consolidado nuevo.

**Auditoría acotada a un subsistema o EPIC concreto** (más rápida, para revisar solo lo que se acaba de tocar):

> Audita solo `<subsistema o carpeta concreta>` con el mismo criterio que las auditorías de `docs/12-technical-debt/` (ver `CONVENCIONES.md`): correctitud, redundancia, complejidad innecesaria, clean code, refactors perdidos, código muerto, y contraste contra `docs/`. No hace falta publicar artifact ni crear una subcarpeta nueva si es una revisión puntual — basta con el informe en el chat, citando fichero:línea igual que las auditorías archivadas.

**Sesión de corrección** (resolver hallazgos ya auditados, de una fase del `plan-correccion.md` de una auditoría concreta):

> Lee primero `docs/12-technical-debt/CONVENCIONES.md` (reglas de sesión, commit único, formato de asunto, columnas de `progreso.md`) y `docs/12-technical-debt/<YYYYMMDD>/progreso.md` para confirmar qué sigue pendiente. Vamos a corregir `<hallazgos o fichero de subsistema concreto>`. Sigue las reglas de `CONVENCIONES.md` para cada uno: relee fichero:línea, arreglo mínimo, test que habría cazado el bug, verificación real, y un commit único por hallazgo con la fila de `progreso.md` actualizada.

### Consejos

- Antes de lanzar una auditoría completa nueva, mira `progreso.md` y el top "antes de la defensa" de la más reciente — puede que ya tengas la respuesta a mano sin gastar una pasada completa.
- Las auditorías son de **lectura estática de código**: cualquier hallazgo sobre "esto rompe en producción" debería confirmarse manualmente (navegador, tests reales) antes de darlo por bueno en una defensa o entrega.
- Si vas a citar hallazgos concretos en la memoria del TFM, referencia fecha + fichero + ID juntos (p. ej. "ver `docs/12-technical-debt/20260811/07-frontend-paginas-modulos.md`, hallazgo `07.01`").
