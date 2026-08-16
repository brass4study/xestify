# Xestify - Instrucciones para Agentes

Este archivo es la fuente canonica de instrucciones para cualquier agente que
trabaje en este repositorio. Si existe otro archivo especifico de una herramienta
como `.github/copilot-instructions.md`, debe apuntar a este documento y no
duplicar reglas.

## Inicio de sesion obligatorio

Al comenzar cualquier conversacion sobre este proyecto, leer siempre
`docs/10-productivity/sesion.md` antes de responder cualquier pregunta o realizar cualquier
accion. Ese archivo contiene el estado actual del proyecto, las stories
completadas, la story en progreso y las convenciones establecidas.

Despues de leerlo, confirmar brevemente en que punto esta el proyecto y que toca
hacer a continuacion.

## Idioma y comunicacion

- Responder siempre en espanol.
- Mantener explicaciones claras, directas y orientadas al estado real del proyecto.
- Si se modifica codigo, indicar al final que se ha cambiado y como se ha verificado.

## Estado del MVP

- El corte oficial del MVP esta implementado hasta la Story 6.4 incluida.
- No implementar Story 6.5 ni PluginManager salvo peticion explicita.
- Si se actualiza documentacion de estado, reflejar que 6.5+ sigue pendiente.

## REGLA OBLIGATORIA: actualizar docs/ia antes de cada commit

Esta regla es mandatoria. No hay excepciones. Nunca omitirla.

Antes de ejecutar `git commit` para cualquier story completada, DEBES actualizar
los archivos siguientes en este orden:

1. `docs/10-productivity/sesion.md`: marcar la story como completada, añadir commit hash,
  actualizar "Ultima actualizacion" y "Proxima story".
2. `docs/10-productivity/productividad.md`: añadir entrada de la story con fecha, estimado
  sin IA, tiempo real con IA, aceleracion, que hizo la IA, iteraciones y
  decision manual.
3. `docs/10-productivity/prompts.md`: añadir el prompt exacto que se uso para la story,
  resultado e iteraciones.
4. Si el commit es un `feat` de una story o de un EPIC, tambien debes revisar y
  actualizar `docs/11-backlog/backlog.md` y `docs/11-backlog/roadmap.md` para que el
  estado del backlog quede alineado con la implementacion real.
5. Si el commit es un `feat` de una story o de un EPIC, tambien debes revisar y
  actualizar `README.md` (secciones "## Estado actual del proyecto (MVP)" y
  "## Estado actual"): corte funcional, story/EPIC incluida, siguiente foco, y
  cualquier bullet de capacidades que la story haya añadido o cambiado. Este
  paso vive en el mismo checklist obligatorio que sesion.md/productividad.md/
  prompts.md/backlog.md — no es una revision aparte y opcional "si cambia
  arquitectura o contrato" (ver incidente en "Errores y lecciones aprendidas").

Flujo obligatorio para cada story:

```text
1. Implementar codigo + tests
2. Verificar que los tests pasan
3. Actualizar docs/10-productivity/sesion.md
4. Actualizar docs/10-productivity/productividad.md
5. Actualizar docs/10-productivity/prompts.md
6. Si aplica (feat de story/EPIC): actualizar docs/11-backlog/backlog.md y roadmap.md
7. Si aplica (feat de story/EPIC): actualizar README.md
8. git add -A && git commit con el formato obligatorio
```

Si el trabajo no corresponde a una story completada, no se deben tocar estos
archivos automaticamente. En ese caso, explicar en la respuesta que no se
actualiza `docs/10-productivity` porque no se esta cerrando una story.

Formato de commit obligatorio para stories y EPICs:

- Stories individuales: primera linea `feat: STORY X.X - [Titulo exacto del backlog]`,
  seguida de linea en blanco y cuerpo con lista breve de los cambios realizados:
  archivos creados/modificados y resumen de que hace cada uno.
- EPICs completos cuando se commitean en bloque: `feat: EPIC X - [Titulo del EPIC]`
  con el mismo cuerpo descriptivo.
- Nunca usar el formato `feat(scope):`; siempre `feat:` sin parentesis.

## Commits

- Los mensajes de commit deben estar siempre en espanol.
- Para stories y EPICs, usar el formato obligatorio definido en la regla anterior.
- Antes de ejecutar cualquier `git commit`, mostrar al usuario el mensaje completo
  propuesto, incluyendo titulo y descripcion/cuerpo, y esperar confirmacion
  explicita. Esto aplica siempre, sin excepcion, incluso para cambios masivos,
  de solo documentacion o de limpieza del arbol de trabajo — no hay categoria de
  commit exenta.
- No crear commits sin confirmacion previa del usuario. Una confirmacion ya dada
  no cubre commits futuros, aunque sean del mismo tipo o continuen la misma
  tarea: cada `git commit` necesita su propia confirmacion explicita. Ante
  cualquier duda sobre si aplica una excepcion, pausar y preguntar antes de
  actuar.
- Antes de comitear una story, releer explicitamente la REGLA OBLIGATORIA de
  `docs/10-productivity/` (arriba, incluye `README.md`) y tratarla como checklist
  a verificar, en vez de inferir su alcance copiando el commit de documentacion
  vivo mas reciente: el commit anterior es evidencia de un alcance valido para
  ese caso, no del alcance completo exigido en general.
- Despues de crear un commit confirmado, ejecutar `git push` para sincronizar el
  repositorio local con el remoto, salvo que el usuario indique explicitamente lo
  contrario.

Para fixes, documentacion o tareas tecnicas fuera de una story concreta:

```text
{fix|docs|feat}: {$title}
{$descripcion}
```

El titulo debe ser breve y descriptivo. La descripcion debe explicar el motivo
del cambio y el impacto principal.

Ejemplo de commit de story:

```text
feat: STORY 5.1 - Frontend - Crear pagina Login

- frontend/src/js/pages/Login.js: nueva pagina con form email/password, validacion, POST /auth/login y callback onSuccess
- frontend/src/js/main.js: flujo bootstrap con render condicional Login/Dashboard y logout
- frontend/src/css/main.css: estilos base login y shell
- frontend/tests/LoginTest.html: 5 tests (render, validacion, exito, error)
- docs/08-operations/apache-vhost-examples.md: ejemplo de configuracion Apache para desarrollo same-origin con `/tests/*`
```

## Convenciones del proyecto

- Las convenciones tecnicas y reglas de calidad de codigo viven en
  `CONTRIBUTING.md`.
- Antes de cambiar PHP, JavaScript, tests o estructura tecnica, revisar y aplicar
  `CONTRIBUTING.md`.
- No duplicar en este archivo las reglas detalladas de calidad; si cambian, deben
  actualizarse en `CONTRIBUTING.md`.

## Skills locales del proyecto

- Las skills propias de Xestify viven en `skills/`.
- Seguir el estandar Anthropic Agent Skills: cada skill debe ser una carpeta con
  `SKILL.md`, frontmatter YAML con `name` y `description`, instrucciones Markdown
  concisas y recursos opcionales en `scripts/`, `references/`, `assets/` o
  `evals/` cuando aporten valor.
- Cuando una peticion encaje con una skill local, leer su `SKILL.md` antes de
  actuar y aplicar su flujo junto con este `AGENTS.md` y `CONTRIBUTING.md`.
- Para revisiones de clean code con SonarQube for IDE/SonarLint, usar
  `skills/review-sonarqube-clean-code/SKILL.md`.

## Convenciones de entidades y plugins

- `persons` es el slug canonico de personas: unifica clientes, distribuidores
  y oculistas bajo un unico modelo (STORY 10.2 renombro el plugin desde
  `clients` con este objetivo). No reintroducir `client`/`clients` como slug
  funcional, fixture o dato demo.
- Los plugins de tipo `entity` son la fuente de verdad del catalogo de entidades.
- El catalogo debe salir de plugins instalados y activos, no de seeders de entidades.
- Los seeders deben limitarse a usuario admin y datos demo explicitos cuando se pidan.

## Schemas y datos

- Las claves tecnicas de schemas, payloads y DB deben ir en ingles.
- Para `persons`, usar:
  - `name`
  - `surnames`
  - `email`
  - `phone`
  - `creation_stamp`
  - `is_active`
- Las labels visibles para UI pueden ir en espanol.
- No mezclar claves tecnicas en espanol como `nombre`, `apellidos`, `telefono`
  o `activo`.

## Base de datos local

- No añadir migraciones, seeders ni automatismos permanentes para arreglos
  puntuales de una instalacion local salvo peticion explicita.
- Si hay que corregir datos locales puntuales, hacerlo como operacion puntual y
  documentarlo en la respuesta.

## Desarrollo local

- En Windows, preferir `127.0.0.1` frente a `localhost` para evitar latencias por
  resolucion o fallback IPv6.
- El entorno canonico de desarrollo es Apache+PHP sirviendo todo Xestify en un
  solo origen desde la raiz del repo.
- Usar `docs/08-operations/apache-vhost-examples.md` como base para habilitar
  `/`, `/api/*`, `/health`, `/plugins/*`, `/css/*`, `/js/*` y, solo en
  desarrollo, `/tests/*`.
- La exposicion de tests frontend bajo Apache se activa con `SetEnvIf ... ENABLE_TEST=1`.
- Evitar `tools/dev/frontend-router.php`; ya no forma parte del flujo soportado.

## Politica de finales de linea (CRLF/LF)

- El repositorio usa LF como formato canonico de fin de linea, definido en
  `.gitattributes`.
- En Windows, configurar SIEMPRE el repo local con:

```text
git config --local core.autocrlf false
git config --local core.eol lf
```

- No cambiar `.gitattributes` para permitir CRLF globalmente.
- Si aparece el warning `CRLF will be replaced by LF`, corregir la configuracion
  local anterior y continuar; no ignorar el warning en commits recurrentes.

## Arquitectura

- Mantener el pipeline real `Router -> Middleware -> Controller`.
- Las rutas protegidas deben pasar por `AuthMiddleware`.
- `/health` y `/api/v1/auth/login` permanecen publicas.
- `EntityService` debe usar el `HookDispatcher` compartido del contenedor.
- Evitar caminos paralelos para ejecutar requests protegidas.

## Tests

- Seguir la estrategia de verificacion definida en `CONTRIBUTING.md`.
- Añadir tests cuando se corrija una regresion para evitar que reaparezca.
- Para tests frontend HTML (`frontend/tests/integration/*.html`), la verificacion canonica y prioritaria debe hacerse abriendo el test en una pestaña del navegador integrado de VS Code, sirviendolo por Apache del proyecto o, en su defecto, por un servidor HTTP local equivalente.
- No usar navegador headless como via principal cuando el objetivo sea validar o enseñar resultados de tests HTML frontend al usuario. El modo headless solo puede usarse como apoyo tecnico adicional, nunca como sustituto del navegador integrado cuando este disponible.
- Cuando se valide un test HTML frontend, mantener abierta la pestaña del navegador integrado para que el usuario pueda ver el resultado directamente en VS Code.
- `frontend/tests/e2e/` contiene la suite E2E (Playwright) contra el runtime Apache+PHP real, backend y BD reales. Se ejecuta con `npx playwright test` (ver `docs/05-frontend/testing-ui.md`); no sustituye la verificacion manual en navegador integrado de los runners de `frontend/tests/integration/`, cubre un nivel distinto (flujos reales de extremo a extremo).

## Documentacion

- Mantener alineados `README.md`, `docs/11-backlog/backlog.md`,
  `docs/09-history/decisiones-tecnicas.md` y documentacion de plugins cuando cambie
  arquitectura o contrato, sea o no cierre de story. Para el cierre de una story
  o EPIC, `README.md` no es una revision opcional bajo este criterio: es un paso
  numerado de la REGLA OBLIGATORIA de `docs/10-productivity/` (arriba).
- Evitar referencias nuevas a `system_entities`, `entity_metadata` o migraciones
  obsoletas salvo como contexto historico.  

## Referencias clave

- Estado del proyecto: `docs/10-productivity/sesion.md`
- Backlog: `docs/11-backlog/backlog.md`
- Decisiones tecnicas: `docs/09-history/decisiones-tecnicas.md`
- Calidad y contribucion: `CONTRIBUTING.md`
- Skills locales: `skills/`

# Errores y lecciones aprendidas

Historial de incidentes de procedimiento reales: que ocurrió, la causa raíz y
el remedio aplicado. La regla que queda vigente tras cada incidente se integra
en la sección de este archivo a la que corresponda (`## Commits`, `## Tests`,
etc.); aquí solo se archiva el caso concreto como referencia.

