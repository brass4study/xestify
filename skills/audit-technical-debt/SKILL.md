---
name: audit-technical-debt
description: Genera una auditoría nueva de deuda técnica de Xestify (completa, incremental o acotada a un subsistema/EPIC concreto) mediante lectura estática y completa del código (caja blanca). Usar cuando el usuario pida "haz una auditoría de deuda técnica", "audita el proyecto/código", "genera un informe de deuda técnica", "compara el estado actual con la última auditoría", "audita el módulo/subsistema/EPIC X", o mencione el histórico de auditorías de skills/audit-technical-debt/archive/ sin pedir corregir nada todavía. No corrige hallazgos ya auditados — para eso usar skills/fix-technical-debt/SKILL.md.
---

# Audit Technical Debt

Genera una auditoría de deuda técnica nueva sobre el estado actual del
código de Xestify: lectura estática y completa del código fuente (caja
blanca), nunca ejecución de la app o de los tests como método de detección
— cualquier hallazgo "esto rompe en producción" se confirma a mano antes de
darlo por bueno en una defensa o entrega.

Esta skill **no corrige hallazgos**: si el usuario pide arreglar algo ya
auditado, usa `skills/fix-technical-debt/SKILL.md` en su lugar.

El histórico completo de auditorías (pasadas y las que genere esta skill)
vive en `skills/audit-technical-debt/archive/`, con una subcarpeta por fecha
`YYYYMMDD/`. Cada auditoría es una fotografía inmutable del código en un
commit/estado concreto: una vez publicada no se sobreescribe ni se borra —
si un hallazgo se corrige, se anota como resuelto en el `progreso.md` de esa
misma auditoría (ver `skills/fix-technical-debt/SKILL.md`) o se confirma en
la **siguiente** auditoría (re-auditoría incremental).

## Severidad

4 niveles:

- **Crítico** — rompe un flujo de uso normal, o es un problema de seguridad
  con impacto directo.
- **Mayor** — bug real o deuda que compromete mantenibilidad seria.
- **Menor** / **Nit** — limpieza o consistencia sin impacto funcional.

## ID de hallazgo

- Dentro de cada fichero `0N-<subsistema>.md`, numera los hallazgos de forma
  correlativa y única a través de todas las severidades (sin reiniciar por
  sección). ID completo `0N.MM`, con **padding a dos dígitos en ambas
  mitades** (`05.07`, no `05.7`) — mantiene la tabla de `progreso.md`
  alineada y ordenable como texto.
- **Estable dentro de una auditoría, no entre auditorías distintas.** El
  hallazgo `05.07` de una fecha no es necesariamente "lo mismo" que `05.07`
  de otra — los ficheros de subsistema y su numeración pueden reorganizarse
  de una auditoría a otra. Cita siempre **fecha + fichero + ID** juntos
  (p. ej. `archive/20260811/05-....md, hallazgo 05.07`).
- Cada hallazgo incluye: fichero:línea, descripción del problema y por qué
  importa, y una sugerencia de arreglo — no basta con señalar, hay que dejar
  claro el camino de solución.
- El top **"antes de la defensa"** (3-5 hallazgos de mayor impacto real:
  seguridad o rotura de un flujo de demo, en cualquier subsistema)
  referencia siempre el ID (`0N.MM`), no solo una etiqueta "P1, P2...", para
  que quede trazable en `progreso.md`.
- Identifica además 3-5 **patrones transversales** que se repitan en varios
  subsistemas a la vez — son más útiles de explicar en una defensa que
  cualquier hallazgo suelto.
- Metodología y límites del análisis siempre explícitos (p. ej. si fue
  lectura estática de código sin ejecutar tests/app) — evita que un
  hallazgo dudoso se dé por verificado sin serlo.

## Workflow

1. **Determina la variante** antes de lanzar ningún agente:
   - El usuario menciona una carpeta/subsistema/EPIC concreto ("audita el
     módulo de plugins", "audita solo lo que tocamos en STORY X.X") →
     **acotada**.
   - Ya existe al menos una subcarpeta `YYYYMMDD/` en
     `skills/audit-technical-debt/archive/` y el usuario no pide
     explícitamente arrancar desde cero → **incremental**.
   - No existe ninguna subcarpeta `YYYYMMDD/` todavía, o el usuario pide
     explícitamente una auditoría completa/desde cero → **completa**.
   - Si es ambiguo (p. ej. hay una auditoría previa pero también ha pasado
     mucho trabajo nuevo) — pregunta antes de lanzar agentes en paralelo;
     es una operación cara de repetir. Antes de lanzarla, mira también el
     `progreso.md` y el top "antes de la defensa" de la más reciente — puede
     que ya tengas la respuesta a mano sin gastar una pasada completa.

2. **Completa** (arranca desde cero, útil tras un salto grande de trabajo):
   - Divide el código en subsistemas acotados (~2.000-4.000 líneas cada
     uno) siguiendo los límites naturales de EPICs relacionadas, no por
     carpeta mecánica.
   - Lanza agentes de investigación **en paralelo**, uno por subsistema,
     cada uno instruido para:
     - Leer los ficheros **completos** de su ámbito (no solo `grep`).
     - Buscar explícitamente: bugs de correctitud, redundancia/duplicación,
       complejidad innecesaria, violaciones de clean code, señales de
       refactors incompletos o perdidos (patrones inconsistentes entre
       ficheros similares, comentarios/tests que ya no casan con el
       código), código muerto o inalcanzable.
     - Contrastar contra la documentación en `docs/` y
       `docs/11-backlog/backlog.md` para detectar deriva
       documentación↔código.
     - Devolver hallazgos con fichero:línea, severidad, descripción y
       arreglo sugerido, más una nota de cobertura de tests del subsistema.
   - Sintetiza los informes individuales en un informe consolidado:
     veredicto global, top 3-5 "antes de la defensa", patrones
     transversales, y una tabla resumen por subsistema.
   - Usa la fecha de hoy en formato `YYYYMMDD` para la subcarpeta nueva.

3. **Incremental** (compara contra la última auditoría en vez de repetir
   todo):
   - Localiza la subcarpeta `YYYYMMDD/` más reciente (mayor fecha
     numéricamente) dentro de `skills/audit-technical-debt/archive/`.
   - Usa su `progreso.md` como punto de partida, pero no te fíes solo de
     él: para cada hallazgo crítico/mayor de esa auditoría relee el código
     y confirma independientemente si sigue abierto, si se corrigió, o si
     cambió de forma — si confirmas una corrección, marca esa fila como
     `✅ Resuelto` en el `progreso.md` de la auditoría anterior (con el
     commit si lo encuentras por `git log`/`git blame`).
   - Busca también hallazgos nuevos que no estaban en la auditoría
     anterior.
   - Guarda el resultado como una auditoría nueva fechada hoy (con su
     propio informe consolidado, ficheros por subsistema,
     `plan-correccion.md` y `progreso.md` propios para lo que siga abierto
     o sea nuevo), sin modificar ni borrar la auditoría anterior, y añade
     una sección "Resueltos desde la última auditoría" en el informe
     consolidado nuevo.

4. **Acotada** (más rápida, para revisar solo lo que se acaba de tocar):
   - Audita solo el subsistema o carpeta concreta con el mismo criterio:
     correctitud, redundancia, complejidad innecesaria, clean code,
     refactors perdidos, código muerto, y contraste contra `docs/`.
   - No hace falta publicar artifact ni crear una subcarpeta nueva — basta
     con el informe en el chat, citando fichero:línea igual que las
     auditorías archivadas.

5. **Completa e incremental únicamente** — publica y archiva:
   - Publica el informe consolidado también como página HTML navegable
     (artifact).
   - Archiva todo el conjunto (consolidado + informes individuales) en
     Markdown, en `skills/audit-technical-debt/archive/<YYYYMMDD>/`.
   - Genera `plan-correccion.md` de la subcarpeta nueva: solo los datos
     específicos de esta auditoría (qué hallazgos van en cada fase de
     corrección, en qué sesiones se agrupan) — las reglas y los pasos de
     cada fase ya viven en `skills/fix-technical-debt/SKILL.md`, no los
     repitas aquí.
   - Genera `progreso.md` (checklist con una fila por hallazgo, todas en
     estado pendiente al arrancar) con el formato de la sección siguiente.
   - Actualiza `skills/audit-technical-debt/archive/README.md` con la fila
     nueva en la tabla de auditorías.

## Estructura de cada subcarpeta fechada

```
skills/audit-technical-debt/archive/YYYYMMDD/
  README.md                          — índice de esa auditoría + top 3-5 "antes de la defensa"
  00-informe-consolidado.md          — veredicto, prioridades, patrones transversales, tabla por subsistema
  01-<subsistema>.md ... NN-<...>.md — un fichero por subsistema auditado, hallazgos completos con fichero:línea
  plan-correccion.md                 — qué hallazgos van en cada fase de corrección (datos específicos de esta auditoría)
  progreso.md                        — checklist mutable con el estado de cada hallazgo (la única pieza que se actualiza con el tiempo)
```

`plan-correccion.md` y `progreso.md` se generan junto con la auditoría, no
como una tarea aparte.

## Formato de `progreso.md`

A diferencia de los informes `00`-`NN` de cada auditoría (fotografía
inmutable del análisis), `progreso.md` **sí se actualiza con el tiempo** —
es el mecanismo para que una sesión de corrección nueva sepa qué está ya
resuelto sin releer los informes completos.

Una tabla por fichero de subsistema `0N`, columnas: **`ID | Estado | Sev. |
Resumen | Commit | Notas`**. `Estado` va en segunda posición a propósito —
es el dato que una sesión nueva necesita leer primero.

Leyenda de estado: ⏳ Pendiente · 🔧 En progreso · ✅ Resuelto · 🚫 Descartado
(con motivo en `Notas`).

`Notas` es corta, no un resumen del commit: solo advertencias puntuales que
otra sesión necesite ver de un vistazo sin abrir el commit (`⚠️ toca el
mismo fichero que 0N.MM`, un fallo detectado de pasada y dejado sin
corregir, el motivo de un `🚫 Descartado`). El detalle completo de un
hallazgo resuelto vive en el cuerpo del commit (`git show <hash>` o
`git log --oneline --grep "\[YYYYMMDD\]\[0N.MM\]"`), no se duplica aquí.

Al principio del fichero, antes de las tablas por subsistema — recuento
agregado por fichero:

```
| Fichero | Total | ✅ Resuelto | 🔧/⏳ Pendiente | 🚫 Descartado |
|---|---|---|---|---|
| [01](01-<subsistema>.md) — <nombre> | <N> | <n> | <n> | <n> |
...
| **Total** | **<N>** | **<n>** | **<n>** | **<n>** |
```

Justo debajo, una línea que mapea las etiquetas `P1, P2...` del top "antes
de la defensa" a sus IDs reales:

```
Los N hallazgos de la Fase 1 ("antes de la defensa") corresponden a: **P1**=`0N.MM`, **P2**=`0N.MM`, ... No los cuentes dos veces al planificar las sesiones de la Fase 2.
```

## Cuándo NO usar esta skill

- Corregir hallazgos ya registrados en un `progreso.md` existente → usa
  `skills/fix-technical-debt/SKILL.md`.
- Revisar clean code con SonarQube/SonarLint sobre cambios locales → usa
  `skills/review-sonarqube-clean-code/SKILL.md`.

## Test Prompts

Usa `evals/evals.json` como conjunto de validación ligero de esta skill.
Cubre las tres variantes (completa, incremental, acotada) y el caso
ambiguo que debe preguntar antes de lanzar agentes.
