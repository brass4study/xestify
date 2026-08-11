# Plantilla — Fase 2: barrido por subsistema (MAYOR)

← [Convenciones de corrección](../convenciones-correccion.md)

Una sesión por bloque — cambia el número de fichero y, si quieres, agrupa dos ficheros pequeños en la misma sesión. El `plan-correccion.md` de la auditoría trae la tabla de sesiones (qué ficheros, cuántos MAYOR restantes) que decide cuántas veces se pega este prompt y con qué fichero.

```
Lee primero docs/12-technical-debt/convenciones-correccion.md y
docs/12-technical-debt/<YYYYMMDD>/progreso.md (sección 0N) para ver
qué está ya resuelto o en progreso. Vamos a corregir los hallazgos
MAYOR pendientes de docs/12-technical-debt/<YYYYMMDD>/0N-<nombre>.md
(deja MENOR y NIT para otra sesión). Sigue las reglas de
convenciones-correccion.md (commit único, formato de asunto, columnas
de progreso.md) para cada hallazgo o grupo pequeño y relacionado.
Verifica con la suite de tests del subsistema (o /run si es un
hallazgo de frontend).
```
