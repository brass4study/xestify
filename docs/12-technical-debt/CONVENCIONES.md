# Convenciones — Auditorías de deuda técnica

← [Índice de auditorías](README.md)

Índice corto: qué fichero leer según la tarea. El objetivo es que cada sesión (de auditoría o de corrección) cargue **solo** el contexto que necesita, en vez de un único documento monolítico con todo mezclado.

## Índice

### [convenciones-auditoria.md](convenciones-auditoria.md)

Generar una auditoría nueva (completa, incremental o acotada): estructura de carpeta, severidad, formato de ID, metodología, prompts.

### [convenciones-correccion.md](convenciones-correccion.md)

Corregir hallazgos ya auditados: reglas de sesión, commit único, formato de asunto, orden de ataque.

### [plantillas/](plantillas/)

Un prompt listo por fase de corrección: [fase-1-prioritarios.md](plantillas/fase-1-prioritarios.md), [fase-2-barrido-mayor.md](plantillas/fase-2-barrido-mayor.md), [fase-3-limpieza-menor-nit.md](plantillas/fase-3-limpieza-menor-nit.md), [fase-4-cerrar-circulo.md](plantillas/fase-4-cerrar-circulo.md).

### [convenciones-progreso.md](convenciones-progreso.md)

Formato de la tabla `progreso.md` de una auditoría: columnas, leyenda de estado, tabla resumen.

Si una convención cambia, se actualiza **solo en el fichero correspondiente** — las auditorías ya publicadas no se reescriben, pero cualquier sesión nueva sigue la versión vigente, no lo que copiara una auditoría antigua. Ninguno de estos ficheros se repite dentro de una subcarpeta `YYYYMMDD/`: el `plan-correccion.md`, `progreso.md` y `README.md` de cada auditoría enlazan aquí en vez de duplicar texto.

## Estructura de cada subcarpeta fechada

```
YYYYMMDD/
  README.md                          — índice de esa auditoría + top 3-5 "antes de la defensa"
  00-informe-consolidado.md          — veredicto, prioridades, patrones transversales, tabla por subsistema
  01-<subsistema>.md ... NN-<...>.md — un fichero por subsistema auditado, hallazgos completos con fichero:línea
  plan-correccion.md                 — qué hallazgos van en cada fase de corrección (datos específicos; los prompts viven en plantillas/)
  progreso.md                        — checklist mutable con el estado de cada hallazgo (la única pieza que se actualiza con el tiempo)
```

`plan-correccion.md` y `progreso.md` se generan junto con la auditoría, no como una tarea aparte.
