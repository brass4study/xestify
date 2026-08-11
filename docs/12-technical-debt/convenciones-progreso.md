# Convenciones — Formato de `progreso.md`

← [Índice de convenciones](CONVENCIONES.md)

Formato exacto de la tabla de seguimiento que cada auditoría fechada mantiene en su propio `progreso.md`. Para las reglas de *cuándo* y *cómo* actualizarla (commit único, formato de asunto, etc.), ver [convenciones-correccion.md](convenciones-correccion.md).

## Qué es `progreso.md`

A diferencia de los informes `00`-`NN` de cada auditoría (que son la fotografía inmutable del análisis), `progreso.md` **sí se actualiza con el tiempo** — es el mecanismo para que una sesión nueva sepa qué está ya resuelto sin releer los informes completos ni repetir trabajo ya hecho.

## Tabla de hallazgos

Una tabla por fichero de subsistema `0N`, orden de columnas: **`ID | Estado | Sev. | Resumen | Commit | Notas`**.

`Estado` va en segunda posición (justo después del ID) a propósito: es el dato que una sesión nueva necesita leer primero — "¿esto ya está resuelto?" — y ponerlo al final de la fila obliga a escanear todo para encontrarlo. `Sev.` y `Resumen` dan contexto; `Commit`/`Notas` van al final porque son detalle de seguimiento, no de triage.

Leyenda de estado: ⏳ Pendiente · 🔧 En progreso · ✅ Resuelto · 🚫 Descartado (con motivo en `Notas`).

## Tabla "Resumen"

Al principio del fichero, antes de las tablas por subsistema — recuento agregado por fichero, para ver el estado global sin contar filas a mano:

```
| Fichero | Total | ✅ Resuelto | 🔧/⏳ Pendiente | 🚫 Descartado |
|---|---|---|---|---|
| [01](01-<subsistema>.md) — <nombre> | <N> | <n> | <n> | <n> |
...
| **Total** | **<N>** | **<n>** | **<n>** | **<n>** |
```

Justo debajo de la tabla Resumen, una línea que mapea las etiquetas `P1, P2...` del top "antes de la defensa" a sus IDs reales, para no tener que ir a buscarlos:

```
Los N hallazgos de la Fase 1 ("antes de la defensa") corresponden a: **P1**=`0N.MM`, **P2**=`0N.MM`, ... No los cuentes dos veces al planificar las sesiones de la Fase 2.
```
