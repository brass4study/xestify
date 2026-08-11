# Auditoría de calidad de código — Xestify EPIC 0 a EPIC 9

**Fecha:** 2026-08-11
**Alcance:** backend PHP + frontend vanilla JS, ~20.100 líneas de código fuente más las suites de test asociadas
**Subsistemas auditados:** 7
**Hallazgos totales:** 85 (4 crítico · 30 mayor · 40 menor · 11 nit)
**Versión interactiva:** [artifact HTML publicado](https://claude.ai/code/artifact/0ab4d639-7719-4311-a0bf-311f246a61ff) (navegación por secciones, hallazgos colapsables)

Este documento es la síntesis de los siete informes individuales de esta carpeta. Cada hallazgo con referencia a fichero:línea tiene su detalle completo (descripción larga, cita de código, test relacionado) en el informe del subsistema correspondiente.

---

## Veredicto

El código demuestra entendimiento arquitectónico real: contenedor de DI e inyección explícita, un router con protección de rutas, un motor de entidades dirigido por JSON schema con validadores tipo Strategy, un dispatcher de hooks con antes/después bien pensado, y un frontend con separación MVC consistente. Nada de lo encontrado invalida esas decisiones de diseño.

La deuda que sí aparece es la esperable de nueve EPICs construidos de forma incremental con asistencia de IA y sin una pasada final de consolidación: utilidades reimplementadas en vez de reutilizadas, ampliaciones tardías (STORY 7.3, STORY 9.7) que no se propagaron a todo lo que dependía de la versión anterior, y —el patrón más repetido de los siete informes— suites de test amplias en superficie pero con un punto ciego sistemático alrededor de los *caminos de error y las segundas interacciones*, que es justo donde viven los cuatro hallazgos críticos.

Ninguno de los 85 hallazgos es un problema de "no saber programar". Son exactamente el tipo de cosas que un tribunal valorará que se puedan señalar, explicar y priorizar con criterio.

---

## Antes de la defensa

Los cinco hallazgos con impacto real en una demo en vivo o en seguridad. Los cuatro primeros son arreglos pequeños y localizados; el cuarto (#4) es el único que pide un rediseño acotado.

> **Verificación pendiente:** estos hallazgos vienen de lectura de código por agentes en paralelo, no de ejecutar la aplicación en este entorno. Confirma especialmente #2 y #3 manualmente en el navegador antes de darlos por buenos para la defensa.

### 1. `password_hash` filtrado en las respuestas JSON de usuario — CRÍTICO · seguridad
`GET /api/v1/users/me` y `GET /api/v1/users` reenvían la fila completa de la tabla `users`, hash bcrypt incluido. Cualquier usuario ve su propio hash; un admin ve los de todos.
**Referencia:** `UserRepository.php:25,49,92` · `UserController.php`
**Arreglo:** excluir `password_hash` antes de responder (DTO o `unset()`).
Detalle completo: [01-backend-core-auth-usuarios.md](01-backend-core-auth-usuarios.md)

### 2. El formulario de alta/edición se bloquea para siempre tras el primer error — CRÍTICO
En `EntityEdit.submit()`, el `return` de la rama de validación fallida cae fuera del `try/finally` que resetea `#isSubmitting`. Un campo obligatorio vacío en el primer intento deja el botón "Guardar" inerte el resto de la sesión, sin ningún error visible. Es el flujo CRUD más usado de la app.
**Referencia:** `EntityEdit.js:62-86`
**Arreglo:** mover el `return` de la rama de validación dentro del `try`.
Detalle completo: [07-frontend-paginas-modulos.md](07-frontend-paginas-modulos.md)

### 3. Los botones de PluginManager/PluginConfig dejan de responder al usar el toolbar de la tabla — CRÍTICO
Activar/Desactivar/Actualizar/Revertir se conectan una sola vez por `querySelectorAll` tras el primer render. En cuanto el usuario ordena, cambia densidad o toca columnas, `DynamicTable.render()` reconstruye el DOM y el binding no se repite: los botones quedan visualmente normales pero muertos. `EntityList`/`UserManager` ya usan el patrón correcto (handler directo).
**Referencia:** `PluginManager.js:195-230,294-307` · `PluginConfig.js:345-380`
**Arreglo:** pasar el handler real directamente en `renderCell`, como ya hace `EntityList.js`.
Detalle completo: [07-frontend-paginas-modulos.md](07-frontend-paginas-modulos.md)

### 4. `custom_fields` cambia de significado tras guardar configuración y puede bloquear futuras actualizaciones — CRÍTICO
`saveConfig()` reescribe `plugins.schema_json.custom_fields` para que pase de "catálogo de sugerencias" a "campos activos", moviendo el catálogo real a `plugin_suggested_custom_fields` — clave que ni `PluginSchemaMergeService` ni `InstalledPluginSchemaValidator` conocen. Resultado: editar un campo sugerido ya activo puede bloquear *permanentemente* la próxima actualización del plugin, y un campo nuevo en una versión nueva puede activarse sin que el admin lo pida. No cubierto por ningún test.
**Referencia:** `PluginSchemaMergeService.php:64-70,143-176` · `PluginAdministrationService.php:161-164`
**Arreglo:** la validación/merge de `custom_fields` debe compararse siempre contra el catálogo canónico (`plugin_suggested_custom_fields`), nunca contra la lista de campos activos.
Detalle completo: [04-backend-plugins-actualizacion-extension.md](04-backend-plugins-actualizacion-extension.md)

### 5. Cualquier usuario autenticado puede editar y reasignarse comentarios ajenos — MAYOR · seguridad
El campo `author_id` del plugin `comments` está marcado `editable:false`, pero se recalcula a partir del JWT del solicitante en *cada* `PUT`, y no hay ninguna verificación de propiedad antes de permitir editar/borrar un comentario de otro usuario. `CommentsPluginTest.php` nunca ejercita `update()`.
**Referencia:** `ExtensionPluginContentService.php:129-157` · `PluginExtensionController.php:97-125`
**Arreglo:** no recalcular campos `auto_generated` de autoría en `update`, y verificar `author_id === usuario_solicitante` antes de permitir editar/borrar.
Detalle completo: [04-backend-plugins-actualizacion-extension.md](04-backend-plugins-actualizacion-extension.md)

---

## Patrones transversales

Cinco hallazgos individuales no explican tanto como estos cinco patrones que se repiten en varios subsistemas a la vez. Son los que vale la pena poder nombrar en la defensa.

### Patrón 1 — Falsos verdes
Tres ficheros de test sin `exit()` final que el runner agrupado no puede fallar (`HookFilterTest.php`, `HookFilterApiTest.php`, `PluginHookRegistryTableTest.php`); un test de `TimestampFieldValidator` que certifica la debilidad del validador en vez de detectarla; y los cuatro críticos (#1-#4) comparten algo: ninguno tiene un test que combine "error + reintento" o "configurar + actualizar". Hay mucho test, pero sistemáticamente falta el segundo paso de la interacción.

### Patrón 2 — Ampliaciones que no se propagan
`UserRepository` (EPIC 8) nunca llegó a `AuthController` (EPIC 1); STORY 7.3 amplió la configuración a plugins `extension` pero STORY 7.2 (actualización) se quedó solo en `entity`; `PluginManager`/`PluginConfig` (EPIC 6-7) no adoptaron el patrón de binding correcto que ya usaban `EntityList`/`UserManager` (EPIC 3-8).

### Patrón 3 — Copiar-y-cambiar-el-nombre
`plugins/clients` y `plugins/products` duplican ~90 líneas; `StringFieldValidator`/`TextFieldValidator` son idénticos; `normalizeRoleList()` se repite entre `UserManager`/`UserConfig`; la clase Tailwind base se repite literal en 9 ficheros `Input*.js`; la comparación de schema se reimplementa 3 veces en el subsistema de plugin update.

### Patrón 4 — Deriva documentación ↔ código
`decisiones-tecnicas.md` y varios tests citan migraciones `009`/`010` y `002_core.sql` que no existen como ficheros (el repo actual solo tiene `001` a `007`) — rastro de un squash de migraciones no documentado. El propio `backlog.md` (línea 10) dice "001-005" cuando ya hay hasta `007`. `renderizado-dinamico.md` nombra componentes que ya cambiaron de nombre.

### Patrón 5 — Transacciones aprendidas a medio camino
`EntityService` (escritura de registros) y `syncAll()`/`activate()`/`deactivate()` de plugins no usan transacción, mientras `update()`/`rollback()` de plugins sí — la disciplina transaccional parece haberse adoptado a partir de EPIC 7 sin aplicarse retroactivamente a lo anterior.

---

## Resumen por subsistema

| # | Subsistema | EPIC | Crítico | Mayor | Menor | Nit | Informe |
|---|---|---|---|---|---|---|---|
| 1 | Core / Auth / Users | 0, 1, 8 | 1 | 5 | 5 | 3 | [01](01-backend-core-auth-usuarios.md) |
| 2 | Modelo de datos / Motor de entidades / Validación | 2, 3 | 0 | 4 | 8 | 0 | [02](02-backend-modelo-datos-validacion.md) |
| 3 | Motor de plugins y hooks — núcleo | 4 | 0 | 3 | 9 | 1 | [03](03-backend-motor-plugins.md) |
| 4 | Actualización de plugins, rollback, extensiones y configuración | 6, 7 | 1 | 4 | 5 | 1 | [04](04-backend-plugins-actualizacion-extension.md) |
| 5 | Arquitectura del shell SPA | 9 (bases 3, 5) | 0 | 7 | 6 | 3 | [05](05-frontend-arquitectura-spa.md) |
| 6 | Toolkit de componentes UI y layouts | base 5, ampliado 9 | 0 | 4 | 5 | 3 | [06](06-frontend-toolkit-ui.md) |
| 7 | Páginas y módulos de negocio | 3, 5, 6, 7, 8 | 2 | 3 | 2 | 0 | [07](07-frontend-paginas-modulos.md) |
| | **Total** | | **4** | **30** | **40** | **11** | |

Verdicto de una línea por subsistema:

1. **Core/Auth/Users** — núcleo de infraestructura sólido y bien testeado; la capa de autorización/usuarios es el punto débil.
2. **Modelo de datos** — buena separación de responsabilidades; dos gaps reales de integridad de datos (sin transacción, sin allow-list de campos).
3. **Motor de plugins (núcleo)** — dispatcher bien escrito; el problema es coherencia entre lo documentado/scaffoldeado y lo realmente ejecutado.
4. **Plugin update/extension/config** — núcleo transaccional de update/rollback sólido; la ampliación a "extension" no se propagó de forma coherente al resto.
5. **Arquitectura SPA** — buena separación MVC y router sólido; la "fontanería transversal" de notificaciones/errores/sesión acumula mecanismos redundantes.
6. **Toolkit UI** — arquitectura de base sólida; el defecto transversal es "cada componente reimplementa en vez de heredar".
7. **Páginas/módulos** — el motor de renderizado dinámico es la parte más sólida; las páginas muestran señales de haberse construido en oleadas distintas.

---

## Metodología

Siete agentes de investigación en paralelo, cada uno acotado a un subsistema, con lectura completa de los ficheros de su ámbito (no solo `grep`) y contraste contra la documentación en `docs/` y `docs/11-backlog/backlog.md`. La sesión se interrumpió una vez por límite de uso a mitad de la ejecución en paralelo; los agentes con progreso parcial se reanudaron desde su transcripción y el resto se relanzó desde cero.

**Límite importante:** es una auditoría estática de lectura de código, no una ejecución real de la aplicación ni de la suite de tests en este entorno. Los hallazgos de "camino roto en producción" (prioridades #2 y #3 sobre todo) se dedujeron leyendo el código — confírmalos manualmente antes de apoyarte en ellos para la defensa. Los conteos de severidad reflejan el juicio de cada agente sobre su propio subsistema, no una escala normalizada entre los siete.
