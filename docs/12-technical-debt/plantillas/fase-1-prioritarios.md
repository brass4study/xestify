# Plantilla — Fase 1: prioritarios ("antes de la defensa")

← [Convenciones de corrección](../convenciones-correccion.md)

Los hallazgos "antes de la defensa" de una auditoría concreta, en 1 sesión. El `plan-correccion.md` de esa auditoría trae la lista de hallazgos y las notas específicas que rellenan `<...>` en este prompt.

```
Lee primero docs/12-technical-debt/convenciones-correccion.md (reglas de
sesión, commit único, formato de asunto, columnas de progreso.md) y
docs/12-technical-debt/<YYYYMMDD>/progreso.md para confirmar que
ninguno de estos hallazgos está ya resuelto o en progreso por otra
sesión. Vamos a corregir los hallazgos de "Antes de la defensa" en
docs/12-technical-debt/<YYYYMMDD>/README.md (detalle completo en
00-informe-consolidado.md). Ábordalos uno a uno, en este orden:
<lista de IDs con resumen breve, p. ej. "01.01/P1 (password_hash
filtrado), 04.03/P5 (...), ...">.

Sigue las reglas de convenciones-correccion.md para cada uno. Para la
verificación en concreto: <ajusta según el tipo de hallazgo — suite
de tests relevante para bugs de backend, skill /run para reproducir
en el navegador los bugs de frontend deducidos leyendo código>.

Si algún hallazgo te parece que necesita un rediseño no trivial (no
un simple parche), párate y propón el enfoque en modo plan antes de
tocar código.
```
