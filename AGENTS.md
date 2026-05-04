# Xestify - Instrucciones para Agentes

Este archivo es la fuente canonica de instrucciones para cualquier agente que
trabaje en este repositorio. Si existe otro archivo especifico de una herramienta
como `.github/copilot-instructions.md`, debe apuntar a este documento y no
duplicar reglas.

## Inicio de sesion obligatorio

Al comenzar cualquier conversacion sobre este proyecto, leer siempre
`docs/ia/sesion.md` antes de responder cualquier pregunta o realizar cualquier
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
los tres archivos siguientes en este orden:

1. `docs/ia/sesion.md`: marcar la story como completada, anadir commit hash,
   actualizar "Ultima actualizacion" y "Proxima story".
2. `docs/ia/productividad.md`: anadir entrada de la story con fecha, estimado
   sin IA, tiempo real con IA, aceleracion, que hizo la IA, iteraciones y
   decision manual.
3. `docs/ia/prompts.md`: anadir el prompt exacto que se uso para la story,
   resultado e iteraciones.

Flujo obligatorio para cada story:

```text
1. Implementar codigo + tests
2. Verificar que los tests pasan
3. Actualizar docs/ia/sesion.md
4. Actualizar docs/ia/productividad.md
5. Actualizar docs/ia/prompts.md
6. git add -A && git commit con el formato obligatorio
```

Si el trabajo no corresponde a una story completada, no se deben tocar estos
archivos automaticamente. En ese caso, explicar en la respuesta que no se
actualiza `docs/ia` porque no se esta cerrando una story.

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
  explicita.
- No crear commits sin confirmacion previa del usuario.
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
- tools/dev/frontend-router.php: proxy local para servir frontend y API en mismo origen
```

## Convenciones del proyecto

- Las convenciones tecnicas y reglas de calidad de codigo viven en
  `CONTRIBUTING.md`.
- Antes de cambiar PHP, JavaScript, tests o estructura tecnica, revisar y aplicar
  `CONTRIBUTING.md`.
- No duplicar en este archivo las reglas detalladas de calidad; si cambian, deben
  actualizarse en `CONTRIBUTING.md`.

## Convenciones de entidades y plugins

- `clients` es el slug canonico de clientes.
- No reintroducir `client` como slug funcional, fixture o dato demo.
- Los plugins de tipo `entity` son la fuente de verdad del catalogo de entidades.
- El catalogo debe salir de plugins instalados y activos, no de seeders de entidades.
- Los seeders deben limitarse a usuario admin y datos demo explicitos cuando se pidan.

## Schemas y datos

- Las claves tecnicas de schemas, payloads y DB deben ir en ingles.
- Para `clients`, usar:
  - `name`
  - `email`
  - `phone`
  - `creation_stamp`
  - `is_active`
- Las labels visibles para UI pueden ir en espanol.
- No mezclar claves tecnicas en espanol como `nombre`, `apellidos`, `telefono`
  o `activo`.

## Base de datos local

- No anadir migraciones, seeders ni automatismos permanentes para arreglos
  puntuales de una instalacion local salvo peticion explicita.
- Si hay que corregir datos locales puntuales, hacerlo como operacion puntual y
  documentarlo en la respuesta.

## Desarrollo local

- En Windows, preferir `127.0.0.1` frente a `localhost` para evitar latencias por
  resolucion o fallback IPv6.
- El proxy frontend de desarrollo debe apuntar a `http://127.0.0.1:8080`.
- Puertos habituales:
  - Backend PHP: `http://127.0.0.1:8080`
  - Frontend/proxy: `http://127.0.0.1:8081`

## Arquitectura

- Mantener el pipeline real `Router -> Middleware -> Controller`.
- Las rutas protegidas deben pasar por `AuthMiddleware`.
- `/health` y `/api/v1/auth/login` permanecen publicas.
- `EntityService` debe usar el `HookDispatcher` compartido del contenedor.
- Evitar caminos paralelos para ejecutar requests protegidas.

## Tests

- Seguir la estrategia de verificacion definida en `CONTRIBUTING.md`.
- Anadir tests cuando se corrija una regresion para evitar que reaparezca.

## Documentacion

- Mantener alineados `README.md`, `docs/mvp/backlog.md`,
  `docs/mvp/decisiones-tecnicas.md` y documentacion de plugins cuando cambie
  arquitectura o contrato.
- Evitar referencias nuevas a `system_entities`, `entity_metadata` o migraciones
  obsoletas salvo como contexto historico.

## Referencias clave

- Estado del proyecto: `docs/ia/sesion.md`
- Backlog: `docs/mvp/backlog.md`
- Decisiones tecnicas: `docs/mvp/decisiones-tecnicas.md`
- Calidad y contribucion: `CONTRIBUTING.md`
