# Deuda técnica — Histórico de auditorías

Esta carpeta guarda **auditorías de calidad de código / deuda técnica** del proyecto como instantáneas fechadas. Cada auditoría vive en su propia subcarpeta `YYYYMMDD/` (fecha en que se realizó el análisis), de modo que el histórico completo queda navegable y ninguna auditoría nueva sobreescribe o borra las anteriores.

Las reglas, formatos y prompts reutilizables (estructura de carpeta, severidad, formato de ID, columnas de `progreso.md`, convención de commit, metodología para generar una auditoría) viven en **[CONVENCIONES.md](CONVENCIONES.md)** — un único punto de verdad común a todas las auditorías, pasadas y futuras. Este README es solo el índice histórico; no repitas esas reglas aquí ni dentro de una subcarpeta fechada.

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

Por eso cada auditoría es inmutable una vez publicada: si un hallazgo se corrige, no se borra del informe antiguo — se anota como resuelto en `progreso.md` de esa misma auditoría (sesiones de corrección) o se confirma en la **siguiente** auditoría (re-auditoría incremental). Ver [CONVENCIONES.md](CONVENCIONES.md) para el detalle de ambos mecanismos y los prompts listos para reutilizar.
