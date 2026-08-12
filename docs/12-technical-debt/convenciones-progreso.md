# Convenciones — Formato de `progreso.md`

← [Índice de convenciones](CONVENCIONES.md)

Formato exacto de la tabla de seguimiento que cada auditoría fechada mantiene en su propio `progreso.md`. Para las reglas de *cuándo* y *cómo* actualizarla (commit único, formato de asunto, etc.), ver [convenciones-correccion.md](convenciones-correccion.md).

## Qué es `progreso.md`

A diferencia de los informes `00`-`NN` de cada auditoría (que son la fotografía inmutable del análisis), `progreso.md` **sí se actualiza con el tiempo** — es el mecanismo para que una sesión nueva sepa qué está ya resuelto sin releer los informes completos ni repetir trabajo ya hecho.

## Tabla de hallazgos

Una tabla por fichero de subsistema `0N`, orden de columnas: **`ID | Estado | Sev. | Resumen | Commit | Notas`**.

`Estado` va en segunda posición (justo después del ID) a propósito: es el dato que una sesión nueva necesita leer primero — "¿esto ya está resuelto?" — y ponerlo al final de la fila obliga a escanear todo para encontrarlo. `Sev.` y `Resumen` dan contexto; `Commit`/`Notas` van al final porque son detalle de seguimiento, no de triage.

Leyenda de estado: ⏳ Pendiente · 🔧 En progreso · ✅ Resuelto · 🚫 Descartado (con motivo en `Notas`).

## `Notas` es corta, no un resumen del commit

`Notas` **no** es el sitio para la explicación completa de la causa raíz, el arreglo, los ficheros tocados o cómo se verificó — eso vive en el **cuerpo del commit** (ver regla 8 de [convenciones-correccion.md](convenciones-correccion.md)), no se duplica aquí. `Notas` se queda en una advertencia corta (una línea, a veces dos) y solo cuando aporta algo que otra sesión necesita ver *sin* abrir el commit:

- `⚠️ toca el mismo fichero que 0N.MM` (colisión con otro hallazgo).
- Cualquier fallo o comportamiento incorrecto detectado de pasada al implementar el arreglo y que se deja **sin corregir** — preexistente o no, da igual si lo causó este cambio o ya estaba ahí — porque no pertenece a este hallazgo ni a ningún otro ID ya catalogado. Si coincide con un ID ya existente, anótalo en la fila de ese ID, no aquí. Si es nuevo, deja constancia breve (qué es, dónde) para que no se pierda entre sesiones — no hace falta abrir un hallazgo formal para esto, basta con que quede escrito.
- El motivo de un `🚫 Descartado`.

Para el resto — "¿qué se cambió exactamente y por qué?" — la columna `Commit` ya tiene el hash; `git show <hash>` o `git log --oneline --grep "\[YYYYMMDD\]\[0N.MM\]"` dan el detalle completo sin mantener dos copias de la misma explicación.

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
