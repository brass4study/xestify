# Deuda técnica — Histórico de auditorías

Esta carpeta guarda **auditorías de calidad de código / deuda técnica** del proyecto como instantáneas fechadas. Cada auditoría vive en su propia subcarpeta `YYYYMMDD/` (fecha en que se realizó el análisis), de modo que el histórico completo queda navegable y ninguna auditoría nueva sobreescribe o borra las anteriores.

## Auditorías realizadas

| Fecha | Alcance | Hallazgos (crítico / mayor / menor / nit) | Informe |
|---|---|---|---|
| 2026-08-11 | EPIC 0 a EPIC 9 (corte completo del MVP hasta esa fecha) | 4 / 30 / 40 / 11 (85 total) | [20260811/](20260811/README.md) |

---

## Por qué un histórico y no un único informe que se actualiza

Una auditoría de deuda técnica es una fotografía del código en un commit/estado concreto. Si se sobreescribe con cada nueva pasada:

- Se pierde la capacidad de mostrar progreso ("en agosto había 4 hallazgos críticos; en la siguiente auditoría quedaban 0") — muy útil para la narrativa de un TFM.
- No se puede diferenciar "esto ya se sabía y se decidió no arreglar" de "esto es nuevo".
- Se repite trabajo: sin registro, cada auditoría vuelve a descubrir (y volver a explicar) los mismos hallazgos desde cero.

Por eso cada auditoría es inmutable una vez publicada: si un hallazgo se corrige, no se borra del informe antiguo — se anota como resuelto en la **siguiente** auditoría (ver plantilla de prompt incremental más abajo).

## Estructura de cada subcarpeta fechada

```
YYYYMMDD/
  README.md                          — índice de esa auditoría + top 5 "antes de la defensa"
  00-informe-consolidado.md          — veredicto, prioridades, patrones transversales, tabla por subsistema
  01-<subsistema>.md ... NN-<...>.md — un fichero por subsistema auditado, hallazgos completos con fichero:línea
```

Convenciones a mantener en auditorías futuras (para que el histórico sea comparable entre fechas):

- **Severidad en 4 niveles:** crítico / mayor / menor / nit. Un hallazgo es *crítico* si rompe un flujo de uso normal o es un problema de seguridad con impacto directo; *mayor* si es un bug real o deuda que compromete mantenibilidad seria; *menor* y *nit* son limpieza/consistencia sin impacto funcional.
- **Cada hallazgo incluye:** fichero:línea, descripción del problema y por qué importa, y una sugerencia de arreglo — no basta con señalar, hay que dejar claro el camino de solución.
- **"Antes de la defensa":** siempre un top 3-5 con los hallazgos de mayor impacto real (seguridad o rotura de un flujo de demo), independientemente de en qué subsistema caigan.
- **Patrones transversales:** además de los hallazgos por subsistema, identificar 3-5 patrones que se repiten en varios sitios a la vez (son más útiles de explicar en una defensa que cualquier hallazgo suelto).
- **Metodología y límites siempre explícitos:** si el análisis fue estático (solo lectura de código, sin ejecutar la app/tests), decirlo — evita que un hallazgo dudoso se dé por verificado sin serlo.

---

## Cómo se generó la auditoría de 2026-08-11 (metodología reutilizable)

1. Se dividió el código en subsistemas acotados (~2.000-4.000 líneas cada uno) siguiendo los límites naturales de EPICs relacionadas, no por carpeta mecánica.
2. Se lanzaron agentes de investigación **en paralelo**, uno por subsistema, cada uno instruido para:
   - Leer los ficheros **completos** de su ámbito (no solo `grep`).
   - Buscar explícitamente: bugs de correctitud, redundancia/duplicación, complejidad innecesaria, violaciones de clean code, señales de refactors incompletos o perdidos (patrones inconsistentes entre ficheros similares, comentarios/tests que ya no casan con el código), código muerto o inalcanzable.
   - Contrastar contra la documentación en `docs/` y `docs/11-backlog/backlog.md` para detectar deriva documentación↔código.
   - Devolver hallazgos con fichero:línea, severidad, descripción y arreglo sugerido, más una nota de cobertura de tests del subsistema.
3. Se sintetizaron los informes individuales en un informe consolidado: veredicto global, top 5 "antes de la defensa", patrones transversales, y una tabla resumen por subsistema.
4. El informe consolidado se publicó también como página HTML navegable (artifact), y todo el conjunto (consolidado + informes individuales) se archivó aquí en Markdown, en la subcarpeta fechada.

## Prompts de ejemplo para reproducir o automatizar esto

**Auditoría completa nueva** (arranca desde cero, útil tras un salto grande de trabajo):

> Analiza en profundidad el estado actual del proyecto (desde `<punto de partida>` hasta `<punto actual>`) para saber si el trabajo hecho es correcto. Comprueba que no haya redundancia, complejidad innecesaria, violaciones de clean code, refactorizaciones perdidas ni código inalcanzable. Divide el análisis en subsistemas acotados y usa agentes en paralelo para leer el código completo de cada uno. Al terminar, sintetiza en un informe consolidado (veredicto, top 5 antes de la defensa, patrones transversales, tabla por subsistema), publícalo como artifact navegable, y guarda el consolidado + un fichero por subsistema en `docs/12-technical-debt/<YYYYMMDD>/` siguiendo el mismo formato y convenciones que las auditorías anteriores en esta carpeta (usa `docs/12-technical-debt/20260811/` como referencia de estilo). Actualiza también este README con la nueva fila en la tabla de auditorías.

**Auditoría incremental** (compara contra la última auditoría en vez de repetir todo):

> Compara el estado actual del código con la auditoría más reciente en `docs/12-technical-debt/` (la subcarpeta con la fecha más alta). Para cada hallazgo crítico/mayor de esa auditoría: dime si sigue abierto, si se corrigió, o si cambió de forma. Busca también hallazgos nuevos que no estaban en la auditoría anterior. Guarda el resultado como una auditoría nueva fechada hoy, sin modificar ni borrar la auditoría anterior, y añade una sección "Resueltos desde la última auditoría" en el informe consolidado.

**Auditoría acotada a un subsistema o EPIC concreto** (más rápida, para revisar solo lo que se acaba de tocar):

> Audita solo `<subsistema o carpeta concreta>` con el mismo criterio que las auditorías de `docs/12-technical-debt/`: correctitud, redundancia, complejidad innecesaria, clean code, refactors perdidos, código muerto, y contraste contra `docs/`. No hace falta publicar artifact ni crear una subcarpeta nueva si es una revisión puntual — basta con el informe en el chat, citando fichero:línea igual que las auditorías archivadas.

### Consejos

- Antes de lanzar una auditoría completa nueva, mira el top 5 "antes de la defensa" de la más reciente — puede que ya tengas la respuesta a mano sin gastar una pasada completa.
- Las auditorías son de **lectura estática de código**: cualquier hallazgo sobre "esto rompe en producción" debería confirmarse manualmente (navegador, tests reales) antes de darlo por bueno en una defensa o entrega.
- Si vas a citar hallazgos concretos en la memoria del TFM, referencia el fichero de subsistema y la fecha de la auditoría (p. ej. "ver `docs/12-technical-debt/20260811/07-frontend-paginas-modulos.md`, hallazgo #1"), no solo el número de hallazgo — el número no es estable entre auditorías distintas.
