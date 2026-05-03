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

- Sin Composer ni autoload PSR-4; usar unicamente el autoloader manual de
  `bootstrap.php`.
- Sin frameworks PHP; PHP 8.1+ nativo con `Container` y `Router` propios.
- Sin build step en frontend; Vanilla JS ES2020+, sin npm, sin bundlers.
- Tests standalone; scripts PHP puros sin PHPUnit:
  `php tests/unit/FooTest.php`. En la estructura actual del repo, usar
  `php backend/tests/unit/FooTest.php`.
- Namespace raiz: `Xestify\` mapea a `backend/src/`.

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

- Al tocar backend, ejecutar la suite relevante y, si el cambio es transversal,
  `php backend\tests\run.php all`.
- Al tocar frontend sin runner automatizado, verificar al menos sintaxis con
  `node --check` cuando aplique y documentar cualquier test manual pendiente.
- Anadir tests cuando se corrija una regresion para evitar que reaparezca.

## Documentacion

- Mantener alineados `README.md`, `docs/mvp/backlog.md`,
  `docs/mvp/decisiones-tecnicas.md` y documentacion de plugins cuando cambie
  arquitectura o contrato.
- Evitar referencias nuevas a `system_entities`, `entity_metadata` o migraciones
  obsoletas salvo como contexto historico.

## Calidad de codigo PHP

Aplicar estas reglas siempre al generar o modificar codigo PHP. No esperar a que
SonarQube lo detecte.

- Complejidad ciclomatica <= 10 por funcion; extraer metodos privados si se supera.
- Comparacion estricta: usar siempre `===` y `!==`, nunca `==` ni `!=`.
- Sin `@` para suprimir errores; manejar las condiciones explicitamente.
- Sin variables no usadas; eliminarlas antes de terminar.
- Sin bloques `catch` vacios; logear o relanzar siempre la excepcion.
- Constantes en `UPPER_SNAKE_CASE`; evitar magic numbers sueltos.
- Parametros <= 5 por funcion; agrupar en array u objeto si hacen falta mas.
- Lineas <= 120 caracteres.
- Sin `var_dump`, `print_r`, `die`, `exit` en codigo de produccion.
- SQL siempre con parametros preparados PDO; nunca interpolar variables.
- Sin `eval()`.
- Declarar tipos en firmas (`string $foo`, `: bool`, etc.); el proyecto ya usa
  `declare(strict_types=1)`.
- Llaves obligatorias en todo bloque condicional; nunca `if (...) return;` ni
  `if (...) continue;` en una sola linea.
- Sin codigo muerto tras `return`, `exit` o `throw`.
- Newline al final de cada archivo.
- Sin imports `use` no usados.
- Comprobar retorno de funciones que pueden devolver `null` o `false`:
  `preg_replace`, `json_encode`, `file_get_contents`, etc.
- Sin instanciacion dinamica con variable; evitar `new $class()` y
  `$obj->$method()`. Usar un mapa explicito o factory conocido.
- Cast explicito antes de funciones con tipo estricto cuando el valor venga de
  una funcion que devuelve `mixed`.
- Sin variables globales; nunca `global $var`.
- Sin codigo duplicado entre archivos; extraer a funcion o clase compartida.
- Nombres de funciones y metodos en camelCase; nunca snake_case.
- Sin lanzar excepciones genericas en produccion; usar excepciones de dominio.
  En helpers de test, usar `\AssertionError` cuando aplique.
- Sin codigo comentado; el historial de git conserva el codigo antiguo.
- Sin parametros de funcion no usados; si la firma es fija por interfaz, suprimir
  con `// NOSONAR`.
- Sin bloques de codigo vacios; usar `fn() => null` para handlers no-op o anadir
  un `return;` con comentario.
- Usar clases de caracteres abreviadas en regex cuando aplique: `\w`, `\d`, `\s`.

## Calidad de codigo JavaScript

Aplicar estas reglas siempre al generar o modificar codigo JavaScript.

- `const` por defecto, `let` cuando haga falta reasignar, nunca `var`.
- Sin `console.log` en codigo de produccion.
- Comparacion estricta: usar siempre `===` y `!==`.
- Sin funciones anonimas inline de mas de 5 lineas; extraer a funcion nombrada.
- Sin `innerHTML` con datos de usuario; usar `textContent` o sanitizar.

## Referencias clave

- Estado del proyecto: `docs/ia/sesion.md`
- Backlog: `docs/mvp/backlog.md`
- Decisiones tecnicas: `docs/mvp/decisiones-tecnicas.md`
