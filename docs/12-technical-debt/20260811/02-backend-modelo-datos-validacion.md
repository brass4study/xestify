# Auditoría — Modelo de datos + Motor de entidades dinámicas + Validación

**Subsistema:** Modelo de datos / Entity Engine / Validation
**EPIC cubiertas:** EPIC 2 (modelo de datos core), EPIC 3 (motor de entidades dinámicas)
**Severidades:** 0 crítico · 4 mayor · 8 menor · 0 nit

Índice: [Auditoría consolidada](00-informe-consolidado.md)

Revisión completa (ficheros leídos íntegros) de migraciones 002-005, `GenericRepository`, `EntityService`, `ValidationService`, todo `backend/src/validation/*`, `SystemEntity`, `EntityController`, excepciones, y los 12 ficheros de test indicados, más el `Installer.php`/`schema.json` reales del plugin `clients` y `docs/09-history/decisiones-tecnicas.md` para contrastar diseño vs. implementación.

---

## Hallazgos

### MAYOR

**1. Ausencia de transacción en `EntityService::createRecord()`/`updateRecord()`**
`backend/src/services/EntityService.php:53-93`
La secuencia validar → `dispatchBefore('beforeSave', …)` (puede ejecutar código de plugin con sus propias escrituras en BD) → `repository->create()/update()` → `dispatchAfter('afterSave', …)` no está envuelta en `$pdo->beginTransaction()/commit()/rollBack()`. Si un hook `beforeSave` escribe en BD y luego `repository->create()` falla (p.ej. `RepositoryException`), esa escritura del hook queda persistida sin el registro principal: estado inconsistente. Lo llamativo es que el patrón transaccional **sí existe** en el mismo repo (`backend/src/plugins/application/PluginRollbackService.php:37,70,82` y `PluginUpdateService.php:44,96,109`), lo que indica que el motor de entidades (la ruta de escritura más crítica del sistema) quedó fuera de ese estándar — señal de inconsistencia arquitectónica, no de desconocimiento técnico.
*Sugerencia:* envolver el bloque completo (hook before + write + hook after) en una transacción, o documentar explícitamente por qué se decidió no hacerlo si fue deliberado.

**2. `ValidationService` no rechaza campos no declarados en el schema (falta allow-list estricta)**
`backend/src/services/ValidationService.php:26-37`
`validate()` itera **solo** sobre los campos que extrae `SchemaFieldExtractor::extract($schema)`; nunca recorre las claves de `$data` para detectar claves ajenas al schema. Cualquier clave arbitraria enviada por el cliente (`POST/PUT .../records`) se cuela sin pasar por ningún validador y se persiste tal cual en `content` (JSONB) vía `GenericRepository::create()/update()`. Esto contradice el propio comentario de la migración 002 ("*content is an untyped JSONB bag; schema validated at application layer*"): la validación de aplicación no cubre el caso de datos no declarados. No hay ningún test (unit ni integración) que ejercite este escenario — confirma que es un hueco real, no una omisión deliberada documentada.
*Sugerencia:* en `validate()`, comparar `array_keys($data)` contra el conjunto de campos conocidos y devolver error (`unknown_field`) o, como mínimo, filtrar (`array_intersect_key`) antes de persistir.

**3. `TimestampFieldValidator` no valida ningún formato real**
`backend/src/validation/validators/TimestampFieldValidator.php:10-20`
Solo comprueba `is_string($value)`; acepta literalmente cualquier cadena, incluida `'now'` u otra basura. Contrasta con `DateFieldValidator` (`DateFieldValidator.php:22-30`), que sí valida formato con `DateTimeImmutable::createFromFormat`. Lo más revelador es que el propio test lo codifica como comportamiento esperado: `FieldValidatorsTest.php:55-61` afirma `assertEquals([], $validator->validate('creation_stamp', 'now', []))` — el test "blinda" el bug en vez de detectarlo. En producción, el plugin `clients` usa `"type": "timestamp"` para `creation_stamp` con `"default": "now"` (`plugins/clients/schema.json:39-46`), es decir, el propio catálogo de entidades depende de este tipo sin que aporte ninguna garantía real.
*Sugerencia:* validar formato ISO-8601 (`DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, …)` o similar) igual que se hace para `date`.

**4. `StringFieldValidator` y `TextFieldValidator` son código duplicado al 100%**
`backend/src/validation/validators/StringFieldValidator.php` y `TextFieldValidator.php`
Ambas clases son idénticas salvo el texto del mensaje de error (`'Expected string'` vs `'Expected text'`). Esto respalda directamente la sospecha de sobre-ingeniería en `backend/src/validation/`: la granularidad de "un validador = una clase" no aporta nada aquí, solo duplica mantenimiento (cualquier cambio de comportamiento de "string" hay que replicarlo en "text").
*Sugerencia:* una sola clase parametrizable por mensaje/tipo, o registrar el mismo objeto para ambas claves en `DefaultFieldValidatorRegistryFactory` (`'text' => new StringFieldValidator()`), salvo que exista un plan concreto de divergencia futura (p.ej. `text` sin `maxLength` por defecto) que hoy no existe.

### MENOR

**5. Comentarios/docblocks obsoletos que referencian una tabla `plugin_entity_metadata` inexistente**
`backend/src/services/EntityService.php:17-18` ("*Fetches the current schema from plugin_entity_metadata*") y `backend/tests/integration/EntityServiceTest.php:7-8` ("*A test schema is seeded into plugin_entity_metadata*"). El código real consulta y escribe en `plugins.schema_json` (`EntityService::SCHEMA_QUERY`, líneas 30-34). Es un resto textual de un diseño anterior (tabla de metadata separada) que fue absorbido por `plugins` pero cuya documentación en código no se actualizó — patrón de "refactor incompleto".
*Sugerencia:* actualizar docblocks para reflejar `plugins.schema_json` como fuente única, tal como ya hace `SystemEntity.php`.

**6. `ORDER BY schema_version DESC LIMIT 1` sobre una columna con `UNIQUE(slug)`**
`backend/src/services/EntityService.php:30-34` junto con `plugins_slug_unique UNIQUE (slug)` en `003_plugins.sql:21`. Como solo puede existir una fila por `slug`, el `ORDER BY … LIMIT 1` es código muerto/aspiracional: sugiere un histórico de versiones de schema que `docs/02-entities/versionado-esquemas.md` describe ("*Se mantiene historial de schema_json por version*", tabla sugerida `plugin_migrations`) pero que nunca se implementó. No es incorrecto, pero es engañoso para quien lea el código esperando encontrar ese versionado.
*Sugerencia:* simplificar a `SELECT schema_json FROM plugins WHERE slug = :slug` o, si se planea versionado real, dejarlo anotado como TODO explícito.

**7. Inconsistencia entre `SchemaFieldExtractor` y `EntityService::sortableSchemaFields()` sobre qué bloques del contrato de 4 bloques se recorren**
`SchemaFieldExtractor::fieldSections()` (`schema/SchemaFieldExtractor.php:28-38`) recorre `fields`, `custom_fields` **e `identities`** para validación. `EntityService::sortableSchemaFields()` (`EntityService.php:153-169`) solo recorre `fields` y `custom_fields`. Resultado: un campo de `identities` editable (p.ej. `tax_id` del propio test `SchemaFieldExtractorTest.php:38-50`) se valida pero no se puede usar como criterio de orden en el listado paginado. No es grave, pero es una divergencia de criterio entre dos piezas que deberían compartir la misma noción de "campo de schema".
*Sugerencia:* extraer una única fuente de "campos ordenables/validables" reutilizando `SchemaFieldExtractor` desde `EntityService`.

**8. `plugins.schema_json` no tiene ningún `CHECK` de estructura, pese a que STORY 4.7 lo exige como criterio de aceptación**
`backend/database/migrations/003_plugins.sql:9-24`. El backlog (`docs/11-backlog/backlog.md:636`) enumera como criterio: "*`entity_metadata.schema_json` CHECK constraint sigue validando solo `fields` (retrocompatible)*". En las migraciones 001-007 no existe ningún `CHECK` sobre `schema_json` (ni en `plugins` ni en ninguna otra tabla). O bien el criterio nunca se implementó, o se decidió descartarlo al fusionar `entity_metadata` en `plugins` sin actualizar el backlog/las decisiones técnicas. Vale la pena decidir conscientemente y dejar constancia, de cara a la defensa del TFM.

**9. `MigrationIdempotenceTest.php` no es portable y tiene alcance desactualizado**
`backend/tests/integration/MigrationIdempotenceTest.php:82,94,127-129`. Usa una ruta absoluta de Windows hardcodeada (`C:\Program Files\PostgreSQL\18\bin\psql.exe`) y usuario/BD fijos (`-U postgres -d xestify_dev`), ignorando las variables `DB_*` de `.env` que la propia prueba de conectividad (líneas 44-52) sí usa vía `Database::connection()`. En cualquier máquina distinta a la del autor (otra versión de PostgreSQL, Linux/CI, otro nombre de BD) el test falla o apunta a una BD equivocada — justo lo contrario de lo que promete el docblock ("*critical for deployment safety*"). Además, la lista de migraciones cubiertas (líneas 58-68, 84-90) llega solo hasta `006_plugin_update_history.sql`; `007_configuration.sql` existe en el repo pero no está incluida ni en la comprobación de tablas ni en el re-run de idempotencia.
*Sugerencia:* resolver `psql` vía `PATH` o variable de entorno configurable, reutilizar `$_ENV['DB_*']`, y añadir `007` a las listas.

**10. Referencias a nombres de migración obsoletos/inexistentes en varios ficheros de test**
`GenericRepositoryTest.php:53`, `EntityDataTableTest.php:50`, `EntityServiceTest.php:60`, `EntityControllerTest.php:68` mencionan `002_core.sql` (el fichero real es `002_plugin_entity_data.sql`). `SystemEntitiesTableTest.php:6-7,60` y `EntityMetadataTableTest.php:6,61` refieren migraciones `010_drop_system_entities.sql` y una "*refactor migration*" que no existen como ficheros en `backend/database/migrations` (solo hay 001-007). Los tests siguen siendo correctos en lo que comprueban (aserción de ausencia de tablas), pero el rastro documental de qué migración hizo qué se perdió — probablemente por una consolidación/squash de migraciones no reflejada en comentarios copiados entre ficheros de test.

**11. Asimetría de manejo de excepciones entre `create()` y `update()`/`destroy()` en `EntityController`**
`backend/src/controllers/EntityController.php:198-203` (create) no captura `RepositoryException`, mientras `update()` (líneas 255-260) y `destroy()` (líneas 283-288) sí lo hacen. Un fallo de `GenericRepository::create()` (p.ej. `encodeJson` fallido) se propagaría sin control hasta el manejador global, mientras el mismo tipo de fallo en `update` se traduce a un 404 controlado. Probablemente no intencional.

**12. No existe validador para `type: "uuid"`**
`backend/src/validation/DefaultFieldValidatorRegistryFactory.php:20-29` no registra `'uuid'`, pese a que el contrato de `identities` lo usa por convención (`plugins/clients/schema.json:6-11`, `SchemaFieldExtractorTest.php:38-50`). Hoy es inofensivo porque `SchemaFieldExtractor::shouldSkip()` descarta las identidades no editables/autogeneradas antes de llegar a validarse, pero cualquier identidad futura editable de tipo `uuid` produciría `unknown_type` de forma silenciosa hasta que alguien lo pruebe. Caso límite no cubierto por tests.

---

### Verificación específica solicitada

**Contrato de 4 bloques (STORY 4.7) — `identities`/`fields`/`custom_fields`/`relations`:** `SchemaFieldExtractor` sí procesa de forma consistente `fields`, `custom_fields` e `identities` (con la lógica correcta de excluir identidades autogeneradas/no-editables, cubierta por test). `relations` queda **deliberadamente** fuera de la validación de tipos — esto es coherente con `docs/09-history/decisiones-tecnicas.md` (DECISION 6, línea 402: "*ValidationService no valida existencia del registro relacionado — eso es responsabilidad del Hook `beforeSave` del plugin*"), no es un resto de esquema plano olvidado. El `Installer.php` real (`plugins/clients/Installer.php:62-92`) persiste el contrato completo de 4 bloques tal cual en `plugins.schema_json`, sin aplanarlo a solo `fields` como sugiere el ejemplo de "schema vivo" en `decisiones-tecnicas.md:279-304` — es decir, la documentación describe una fase de "configuración del admin que aplana el schema" que **no está implementada**; el código usa siempre el contrato completo de 4 bloques. Dicho esto, sí hay fixtures de test (`EntityServiceTest.php:72-84`, `EntityControllerTest.php:80-87`) que usan un schema con solo `fields`/`custom_fields` — es válido porque esas secciones son opcionales (`isset()` en `fieldSections()`), no un bug, pero conviene saber que no ejercitan `identities`/`relations` en esa capa.

**`system_entities` / `SystemEntity.php`:** confirmado que `SystemEntity.php` (líneas 23-35) consulta exclusivamente `plugins WHERE plugin_type = 'entity'`, sin ningún resto de la tabla vieja. Los tests (`SystemEntityTest.php`, `SystemEntitiesTableTest.php`) tampoco tocan `system_entities`, solo verifican su ausencia. Coherente con la decisión documentada. El único matiz es el hallazgo #10: los migraciones 009/010 que la documentación cita como responsables de ese cambio no existen como ficheros en el repo actual (solo 001-007), así que la trazabilidad histórica exacta no es reconstruible desde el propio repositorio.

---

## Resumen de salud del subsistema

El núcleo del motor de entidades (repositorio, servicio, controlador, validadores) está razonablemente bien estructurado, con separación de responsabilidades clara y buen uso de sentencias preparadas/PDO en modo excepción. El hallazgo más serio de cara a la defensa es la ausencia de transacciones en las operaciones de escritura de `EntityService` combinada con la validación "solo verifica lo declarado, nunca rechaza lo no declarado" — ambos son gaps de integridad de datos reales, no cosméticos, y el segundo contradice la premisa de diseño explícita del propio código ("*schema validated at application layer*"). La granularidad de `backend/src/validation/` está en general justificada (Strategy pattern limpio, fácil de extender con nuevos tipos) salvo por la duplicación exacta `String`/`Text`, que sí es sobre-ingeniería sin beneficio. Hay varias señales claras de "refactor perdido" (comentarios y test headers que aún nombran `plugin_entity_metadata`, `002_core.sql` o migraciones 009/010 inexistentes) que no afectan al comportamiento pero sí a la credibilidad de la documentación técnica ante un tribunal que la contraste con el código.

**Cobertura de tests:** buena en amplitud (unit para validadores/ValidationService/SchemaFieldExtractor, integración para repositorio/servicio/controlador, incluyendo paginación y hooks), pero con dos puntos débiles concretos: (a) el test de `TimestampFieldValidator` certifica el comportamiento débil en vez de detectarlo, y (b) `MigrationIdempotenceTest.php` es frágil/no portable (ruta de Windows hardcodeada), lo que en la práctica lo inhabilita como red de seguridad en cualquier entorno distinto al del autor. No hay ningún test que cubra el escenario de campos desconocidos en el payload, ni ninguno que ejercite fallo parcial (hook OK + persistencia KO) para exponer la falta de transacción — ambos huecos son consistentes con que esos dos problemas pasaran desapercibidos.
