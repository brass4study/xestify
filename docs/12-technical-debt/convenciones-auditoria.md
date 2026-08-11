# Convenciones — Generar una auditoría de deuda técnica

← [Índice de convenciones](CONVENCIONES.md)

Reglas y prompts para producir una auditoría nueva (completa, incremental o acotada). Ver [convenciones-correccion.md](convenciones-correccion.md) para corregir hallazgos ya auditados.

## Severidad

4 niveles:

- **Crítico** — rompe un flujo de uso normal, o es un problema de seguridad con impacto directo.
- **Mayor** — bug real o deuda que compromete mantenibilidad seria.
- **Menor** / **Nit** — limpieza o consistencia sin impacto funcional.

## ID de hallazgo

- Dentro de cada fichero `0N-<subsistema>.md`, numera los hallazgos de forma correlativa y única a través de todas las severidades (sin reiniciar por sección). El ID completo es `0N.MM`, con **padding a dos dígitos en ambas mitades** (`05.07`, no `05.7`) — mantiene la tabla de `progreso.md` alineada y ordenable como texto.
- **Estable dentro de una auditoría, no entre auditorías distintas.** El hallazgo `05.07` de una fecha no es necesariamente "lo mismo" que `05.07` de otra fecha — los ficheros de subsistema y su numeración pueden reorganizarse de una auditoría a otra. Cita siempre **fecha + fichero + ID** juntos (p. ej. `20260811/05-....md, hallazgo 05.07`) — por eso el commit de corrección lleva también la fecha de la auditoría (ver [convenciones-correccion.md](convenciones-correccion.md)).
- Cada hallazgo incluye: fichero:línea, descripción del problema y por qué importa, y una sugerencia de arreglo — no basta con señalar, hay que dejar claro el camino de solución.
- El top **"antes de la defensa"** (3-5 hallazgos de mayor impacto real: seguridad o rotura de un flujo de demo, en cualquier subsistema) referencia siempre el ID (`0N.MM`), no solo una etiqueta "P1, P2...", para que quede trazable en `progreso.md`.
- Identifica además 3-5 **patrones transversales** que se repitan en varios subsistemas a la vez — son más útiles de explicar en una defensa que cualquier hallazgo suelto.
- Metodología y límites del análisis siempre explícitos (p. ej. si fue lectura estática de código sin ejecutar tests/app) — evita que un hallazgo dudoso se dé por verificado sin serlo.

## Metodología para generar una auditoría nueva

1. Divide el código en subsistemas acotados (~2.000-4.000 líneas cada uno) siguiendo los límites naturales de EPICs relacionadas, no por carpeta mecánica.
2. Lanza agentes de investigación **en paralelo**, uno por subsistema, cada uno instruido para:
   - Leer los ficheros **completos** de su ámbito (no solo `grep`).
   - Buscar explícitamente: bugs de correctitud, redundancia/duplicación, complejidad innecesaria, violaciones de clean code, señales de refactors incompletos o perdidos (patrones inconsistentes entre ficheros similares, comentarios/tests que ya no casan con el código), código muerto o inalcanzable.
   - Contrastar contra la documentación en `docs/` y `docs/11-backlog/backlog.md` para detectar deriva documentación↔código.
   - Devolver hallazgos con fichero:línea, severidad, descripción y arreglo sugerido, más una nota de cobertura de tests del subsistema.
3. Sintetiza los informes individuales en un informe consolidado: veredicto global, top 3-5 "antes de la defensa", patrones transversales, y una tabla resumen por subsistema.
4. Publica el informe consolidado también como página HTML navegable (artifact), y archiva todo el conjunto (consolidado + informes individuales) en Markdown, en la subcarpeta fechada.
5. Genera `plan-correccion.md` y `progreso.md` de la nueva subcarpeta enlazando a estas convenciones (ver estructura en [CONVENCIONES.md](CONVENCIONES.md)) en vez de repetir reglas.

## Prompts reutilizables para generar auditorías

**Auditoría completa nueva** (arranca desde cero, útil tras un salto grande de trabajo):

> Analiza en profundidad el estado actual del proyecto (desde `<punto de partida>` hasta `<punto actual>`) para saber si el trabajo hecho es correcto. Comprueba que no haya redundancia, complejidad innecesaria, violaciones de clean code, refactorizaciones perdidas ni código inalcanzable. Divide el análisis en subsistemas acotados y usa agentes en paralelo para leer el código completo de cada uno. Al terminar, sintetiza en un informe consolidado (veredicto, top 3-5 antes de la defensa, patrones transversales, tabla por subsistema), publícalo como artifact navegable, y guarda el consolidado + un fichero por subsistema en `docs/12-technical-debt/<YYYYMMDD>/`. Sigue las convenciones de `docs/12-technical-debt/convenciones-auditoria.md` (severidad, formato de ID `0N.MM`, estructura de carpeta) — no las repitas, solo referéncialas. Genera también `plan-correccion.md` (solo los datos específicos de esta auditoría por fase, enlazando a `docs/12-technical-debt/plantillas/`) y `progreso.md` (checklist con una fila por hallazgo, todas en estado pendiente al arrancar, con las columnas de `docs/12-technical-debt/convenciones-progreso.md`). Actualiza también `docs/12-technical-debt/README.md` con la nueva fila en la tabla de auditorías.

**Auditoría incremental** (compara contra la última auditoría en vez de repetir todo):

> Compara el estado actual del código con la auditoría más reciente en `docs/12-technical-debt/` (la subcarpeta con la fecha más alta). Usa su `progreso.md` como punto de partida, pero no te fíes solo de él: para cada hallazgo crítico/mayor de esa auditoría relee el código y confirma independientemente si sigue abierto, si se corrigió, o si cambió de forma — si confirmas una corrección, marca esa fila como `✅ Resuelto` en el `progreso.md` de la auditoría anterior (con el commit si lo encuentras por `git log`/`git blame`). Busca también hallazgos nuevos que no estaban en la auditoría anterior. Guarda el resultado como una auditoría nueva fechada hoy (con su propio `00-informe-consolidado.md`, ficheros por subsistema, `plan-correccion.md` y `progreso.md` propios para lo que siga abierto o sea nuevo, siguiendo `docs/12-technical-debt/CONVENCIONES.md`), sin modificar ni borrar la auditoría anterior, y añade una sección "Resueltos desde la última auditoría" en el informe consolidado nuevo.

**Auditoría acotada a un subsistema o EPIC concreto** (más rápida, para revisar solo lo que se acaba de tocar):

> Audita solo `<subsistema o carpeta concreta>` con el mismo criterio que las auditorías de `docs/12-technical-debt/` (ver `docs/12-technical-debt/convenciones-auditoria.md`): correctitud, redundancia, complejidad innecesaria, clean code, refactors perdidos, código muerto, y contraste contra `docs/`. No hace falta publicar artifact ni crear una subcarpeta nueva si es una revisión puntual — basta con el informe en el chat, citando fichero:línea igual que las auditorías archivadas.

## Consejos

- Antes de lanzar una auditoría completa nueva, mira `progreso.md` y el top "antes de la defensa" de la más reciente — puede que ya tengas la respuesta a mano sin gastar una pasada completa.
- Las auditorías son de **lectura estática de código**: cualquier hallazgo sobre "esto rompe en producción" debería confirmarse manualmente (navegador, tests reales) antes de darlo por bueno en una defensa o entrega.
- Si vas a citar hallazgos concretos en la memoria del TFM, referencia fecha + fichero + ID juntos (p. ej. "ver `docs/12-technical-debt/20260811/07-frontend-paginas-modulos.md`, hallazgo `07.01`").
