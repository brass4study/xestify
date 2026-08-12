# Convenciones — Corregir hallazgos de una auditoría

← [Índice de convenciones](CONVENCIONES.md)

Reglas comunes a cualquier sesión que corrija hallazgos ya auditados. Los prompts listos para pegar (uno por fase) viven aparte en [plantillas/](plantillas/) para que una sesión de una fase concreta solo cargue ese fichero pequeño, no todo este documento. Para el formato exacto de la tabla `progreso.md`, ver [convenciones-progreso.md](convenciones-progreso.md).

## Reglas de sesión de corrección

1. **Lee `progreso.md` antes de tocar código** (al menos la fila o sección del hallazgo que toque), para no repetir un hallazgo ya resuelto ni chocar con un diff a medias de otra sesión. Si dos hallazgos tocan el mismo fichero, anótalo en `Notas` con `⚠️ toca el mismo fichero que 0N.MM`.
2. **Trabaja los hallazgos de la sesión uno a uno, nunca en batch.** Si la sesión cubre varios hallazgos (p. ej. varios MAYOR de un mismo fichero en Fase 2), complétalos en serie: aplica el arreglo + test de un solo hallazgo, verifica, commitea y haz push, y solo entonces pasa al siguiente. No analices ni implementes primero todos los hallazgos de la sesión para partir el diff en commits después — aunque compartan fichero o haya dependencias entre ellos (un hallazgo que se apoya en algo que introduce otro), reconstruir los commits atómicos a posteriori (p. ej. con `git stash` + reaplicar cada cambio) es trabajo evitable y más propenso a errores que ir uno a uno desde el principio.
3. **Relee fichero:línea antes de aplicar nada** — los números pudieron desplazarse desde que se escribió la auditoría.
4. **Arreglo mínimo descrito, sin refactorizar de más.**
5. **Añade o ajusta un test que habría detectado el bug** — idealmente confirmando que falla sin el fix y pasa con él.
6. **Verifica de verdad antes de cerrar el hallazgo:** ejecuta la suite de tests relevante, o usa el skill `/run` para reproducir en el navegador los bugs de frontend que se dedujeron leyendo código (no ejecutando la app).
7. **Un único commit por hallazgo — nunca un commit de fix y otro de docs aparte.** El commit incluye a la vez el código, el test añadido/ajustado, y la fila de `progreso.md` actualizada (`Estado`, `Commit`, `Notas`).
8. **La descripción verbosa va en el cuerpo del commit, no en `progreso.md`.** El asunto del commit es breve (regla siguiente); el *cuerpo* es el sitio para explicar causa raíz, el arreglo aplicado (ficheros/métodos tocados), los tests añadidos/ajustados y cómo se confirmó (reversión manual, suite ejecutada, resultado). La columna `Notas` de `progreso.md` se queda corta: solo advertencias puntuales que otra sesión necesite ver de un vistazo sin abrir el commit (`⚠️ toca el mismo fichero que 0N.MM`, un fallo preexistente detectado de pasada, un hallazgo descartado y por qué). Para el detalle completo de un hallazgo ya resuelto, se usa `git show <hash>` o `git log --oneline --grep "\[YYYYMMDD\]\[0N.MM\]"` — la columna `Commit` ya apunta ahí, no hace falta duplicarlo en la tabla.
9. **Convención de asunto de commit:** fecha de la auditoría + ID entre corchetes, en ese orden:
   ```
   fix: auditoria [YYYYMMDD][0N.MM] <resumen breve del arreglo>
   ```
   p. ej. `fix: auditoria [20260811][01.01] password_hash ya no se filtra en /api/v1/users`. La fecha identifica de qué auditoría viene el hallazgo sin abrir el commit — imprescindible en cuanto haya más de una auditoría en el histórico y los IDs se reutilicen entre fechas. Así `git log --oneline --grep "\[20260811\]\[04"` encuentra todo lo tocado de un fichero de esa auditoría de un vistazo, sin depender de que `progreso.md` esté sincronizado.
10. **Hash del propio commit en la fila `Commit`:** commitea, copia el hash corto (`git log -1 --format=%h`), rellénalo en la fila, y haz **un único** `git commit --amend --no-edit`. No repitas el amend intentando que el hash escrito coincida exacto con el hash final: un commit no puede contener su propio hash (el hash se calcula a partir del contenido, así que cada amend lo cambia de nuevo) — perseguirlo entra en bucle infinito. Un hash "una versión por detrás" es el resultado esperado y suficiente; la referencia exacta siempre está en `git log --oneline --grep`.
11. **Push justo después de completar el paso 10 (commit con el hash ya relleno en `progreso.md`), no al final de la sesión** — un hallazgo no está cerrado hasta que ese push se hace (pide confirmación al usuario en ese momento si tu entorno lo requiere, no la difieras). No dejes commits sin subir acumularse a lo largo de una sesión larga.
12. **La re-auditoría incremental de cierre es la verificación independiente**: no se limita a confiar en lo que `progreso.md` dice resuelto, relee el código y lo confirma — trátalas como una comprobación doble, no como pasos redundantes.

## Orden de ataque recomendado

1. **Los "antes de la defensa" primero** — son los más baratos y cubren lo que más duele (seguridad, roturas de demo en vivo). Prompt: [plantillas/fase-1-prioritarios.md](plantillas/fase-1-prioritarios.md).
2. **Barrido por subsistema, solo MAYOR** — una sesión por fichero `0N-*.md` (o varios fusionados si son pequeños), sin tocar MENOR/NIT todavía. Prompt: [plantillas/fase-2-barrido-mayor.md](plantillas/fase-2-barrido-mayor.md).
3. **Limpieza MENOR/NIT** — opcional, solo si queda tiempo; son mejoras de mantenibilidad, no bugs. Prompt: [plantillas/fase-3-limpieza-menor-nit.md](plantillas/fase-3-limpieza-menor-nit.md).
4. **Re-auditoría incremental** — cierra el círculo y deja constancia de qué se resolvió. Prompt: [plantillas/fase-4-cerrar-circulo.md](plantillas/fase-4-cerrar-circulo.md).

El `plan-correccion.md` de cada auditoría fechada no repite estos prompts — solo trae los datos específicos que faltan por rellenar en cada plantilla (la lista de hallazgos de la Fase 1, la tabla de sesiones de la Fase 2).
