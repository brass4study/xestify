# Auditoría — Motor de Entidades Dinámicas y Validación

**Subsistema:** Entity Engine + Validation
**EPIC cubiertas:** EPIC 2, 3 (modelo de datos core, motor de entidades dinámicas)
**Severidades:** 0 crítico · 4 mayor · 9 menor · 5 nit

Índice: [Auditoría consolidada](00-informe-consolidado.md)

Lectura íntegra de `backend/src/services/` (EntityService, ValidationService, EntityOptionLabelBuilder), `backend/src/validation/`, `backend/src/controllers/EntityController.php`, `backend/src/repositories/GenericRepository.php`, las 6 migraciones de `backend/database/migrations/`, router/wiring, documentación (`docs/03-api/endpoints.md`, AGENTS.md) y 15 ficheros de test del ámbito.

---

## Resumen

El motor de entidades dinámicas está en buen estado general: las cuatro deudas mayores de la auditoría de 2026-08-11 sobre este ámbito (transacciones, allow-list de campos, validación de timestamp, duplicación String/Text) están corregidas y cubiertas por tests, el SQL/JSONB está correctamente parametrizado o saneado por regex (no se encontró inyección), y las migraciones son idempotentes y coherentes. La deuda actual se concentra en los bordes del contrato: el slug de la URL no se contrasta con la entidad real del registro en `updateRecord`, dos rutas de `GET records` pueden morir con un 500 sin envelope, la validación parcial permite vaciar campos `required`, y la ordenación JSONB es siempre textual.

## Hallazgos por severidad

### MAYOR

**1. `updateRecord` no comprueba que el registro pertenezca a la entidad del slug**
- `backend/src/services/EntityService.php:99`
- Categoría: bug de correctitud / integridad
- `updateRecord(string $id, string $entitySlug, array $data)` valida `$data` contra el schema del slug de la URL (línea 102) y llama a `$this->repository->update($id, ...)` (línea 112) sin verificar nunca que `plugin_entity_data.entity_slug` del registro coincida con `$entitySlug`. `GenericRepository::update()` (línea 155) solo filtra `WHERE id = :id AND deleted_at IS NULL`. Consecuencias para cualquier usuario autenticado que cruce slug e id (`PUT /entities/products/records/{id-de-person}`): (a) el payload se valida contra el schema equivocado, con lo que se pueden mezclar claves de `products` (p. ej. `price`) en el `content` de un registro de `persons`, saltándose la allow-list `unknown_field` de su entidad real; (b) los hooks `beforeSave`/`afterSave` reciben `slug`/`plugin_name` equivocados (línea 111), de modo que reglas como la unicidad de `mail` de persons no se aplican al registro realmente modificado. `deleteRecord()` sí lo hace bien: deriva el slug del propio registro (línea 146). `show()` tiene el mismo agujero en lectura (ver nit 16).
- Sugerencia: al principio de `updateRecord()`, cargar el registro (`find($id)`) y devolver "not found" si es `null` o si `$record['entity_slug'] !== $entitySlug`; o derivar el slug del registro como hace `deleteRecord()`. Añadir test de regresión con slug/id cruzados.

**2. `GET records` con slug inexistente revienta con 500 sin envelope**
- `backend/src/controllers/EntityController.php:201`
- Categoría: bug de correctitud / contrato de API
- `index()` llama a `listRecordsPage(...)` sin try/catch; `listRecordsPage()` → `fetchCurrentPluginRow()` lanza `EntityServiceException` cuando el slug no existe (EntityService.php:349). La ruta con filtro tiene el mismo hueco: `indexByField()` (líneas 218-228) solo captura `InvalidArgumentException`, pero `findRecordsByField()` también empieza por `fetchCurrentPluginRow()`. No existe manejador global de excepciones, así que la excepción acaba en fatal de PHP: un 500 crudo que rompe el contrato de envelope `{ok:false,error:{...}}` de AGENTS.md. Todos los endpoints hermanos (`schema()`, `options()`, `create/update/destroy`) traducen este caso a 404. Alcanzable desde uso normal: un marcador con un slug antiguo tras un renombrado (STORY 10.3) recibe un fatal en vez de un 404. Ningún test cubre `index()` con slug desconocido.
- Sugerencia: envolver ambas rutas de `index()` con `catch (EntityServiceException $e) → notFound(...)` como hace `options()`, y valorar un manejador global (try/catch en `Router::dispatchWithMiddleware()` que emita `serverError()`) como red de seguridad.

**3. Un update parcial puede vaciar un campo `required` (y `''` se salta toda validación de tipo)**
- `backend/src/services/ValidationService.php:112`
- Categoría: bug de correctitud / validación
- En `validateField()`, el chequeo de obligatoriedad solo corre `if ($requireAll && ...)` (línea 112); en updates (`validate(..., false)`) se omite. Acto seguido, `shouldValidateValue()` (líneas 130-133) devuelve `false` para `null` y `''`, saltándose también el validador de tipo. Resultado: `PUT {"name": null}` (o `""`) sobre un schema con `name.required=true` pasa la validación, y `GenericRepository::update()` (línea 153, `content || :content`) fusiona la clave dejando `name: null` persistido — el estado del registro viola su propio schema. El docblock de `updateRecord()` ampara los campos *ausentes*, no el vaciado explícito de uno presente. Derivada menor: en cualquier operación, un campo no requerido con `''` se persiste sin pasar por el validador de tipo/formato (`mail: ''` se guarda tal cual).
- Sugerencia: cuando el campo esté presente y sea `required`, aplicar `isMissing()` aunque `$requireAll` sea `false`. Definir la semántica de `''` para campos opcionales (p. ej. normalizarlo a "sin valor" antes de persistir). Tests de ambos casos.

**4. La ordenación por campos de `content` es siempre textual: los `number` ordenan mal**
- `backend/src/repositories/GenericRepository.php:100`
- Categoría: bug de correctitud / UX de listados
- `paginate()` construye `ORDER BY content->>'{$safeSort}'` (líneas 98-102). El operador `->>` devuelve `text`, así que un campo `type: number` ordena lexicográficamente: ascendente produce `10, 100, 11, 2, 9`. Lo agrava `NumberFieldValidator` (línea 14), que acepta strings numéricos (`"42.5"`), de modo que la misma columna puede mezclar `42` y `"42.5"` en JSONB. Defecto funcional visible en cualquier listado ordenado por columna numérica desde `EntityList`. Las fechas `YYYY-MM-DD` solo ordenan bien por casualidad del formato. La sanitización está bien (regex + allow-list): no hay inyección, solo orden incorrecto.
- Sugerencia: `listRecordsPage()` ya tiene el schema: pasar el tipo del campo de orden al repositorio y castear (`(content->>'x')::numeric` cuando `type=number`). Test de orden numérico (crear 2, 9, 10 y verificar la secuencia).

### MENOR

**5. El CRUD de registros no filtra por plugin entity activo (deriva con el catálogo)**
- `backend/src/services/EntityService.php:36`
- `SCHEMA_QUERY` (líneas 36-38) resuelve el schema con `WHERE slug = :slug` a secas, mientras `listEntities()` define el catálogo como `type='entity' AND status='active'` — la convención de AGENTS.md. Consecuencia: se pueden crear/actualizar/listar registros en `plugin_entity_data` para (a) entidades desactivadas y (b) slugs de plugins **extension** con `schema_json` de configuración (p. ej. `comments`), creando filas espurias `entity_slug='comments'`. `EntityController::schema()` tampoco filtra (quizá deseable en lectura), pero la escritura no debería compartir esa laxitud.
- Sugerencia: añadir `AND manifest_json->>'type' = 'entity'` a `SCHEMA_QUERY` y decidir la política de `status` (lectura sí, escritura solo activos), dejando constancia.

**6. `restore()` es código muerto y la cascada hard-delete lo dejaría cojo; docblock de cabecera invertido**
- `backend/src/repositories/GenericRepository.php:250`
- `restore()` (líneas 250-264) no tiene llamador en `src/` (solo su test). Además, `EntityService::deleteRecord()` borra **físicamente** los `plugin_extension_data` del registro (línea 156), así que un hipotético restore devolvería el registro sin sus comentarios/historial: el soft-delete es de facto irreversible. El docblock de cabecera dice "hard deletes are not exposed" (líneas 14-15), contradicho por `deleteByEntitySlug()` (líneas 212-220), y la lista de métodos (18-24) omite `paginate`, `renameEntitySlug`, `deleteByEntitySlug` y `findByFieldValue`.
- Sugerencia: decidir si el restore es caso de uso real (exponerlo y suavizar la cascada) o eliminarlo con su test; corregir el docblock de cabecera y su lista de métodos.

**7. Comentario que promete una resolución server-side de entidades que no existe**
- `backend/src/services/ValidationService.php:97`
- El comentario de `validateField()` (líneas 97-104) justifica saltarse `auto_generated`/`auto_populated` porque "ExtensionPluginContentService::resolveAutoGeneratedValue() **and equivalent entity-side resolution** overwrite it". La parte de extensiones es cierta; la "equivalent entity-side resolution" no existe: `EntityService` persiste `$context['data']` tal cual. Hueco latente — ningún schema de entidad declara hoy `auto_generated` en `fields`/`custom_fields` — pero si un admin añade uno vía PluginConfig, el valor crudo del cliente se persistirá sin validar ni resolver.
- Sugerencia: implementar la resolución entity-side (o rechazar `auto_generated` en schemas de entidad al guardar config) y corregir el comentario.

**8. Soporte asimétrico de `relations` en forma mapa vs lista**
- `backend/src/services/ValidationService.php:79`
- `relationKeys()` acepta mapa `{clave: def}` y lista `[{key: ...}]`, pero `EntityService::knownContentKeys()` (290-296) y `ReverseRelationTabResolver::relationsTargeting()` (94-99) solo entienden la forma lista. Una relación en forma mapa se aceptaría en payloads de escritura y, sin embargo: no generaría pestaña inversa, no sería filtrable por `?field=` y **no bloquearía el borrado del registro objetivo** (`guardNoDependentRecords()` depende del resolver). Todos los fixtures reales usan la forma lista: la rama mapa es un resto sin consumidor que solo puede producir divergencias.
- Sugerencia: elegir la forma lista con `key` como canónica: normalizar al guardar el schema y eliminar la rama mapa, o extraer un helper compartido de "claves de relación" usado por las tres piezas.

**9. `respondEntityWriteFailure` mapea errores de persistencia a 404**
- `backend/src/controllers/EntityController.php:403`
- El `else` final (línea 411) convierte cualquier `RepositoryException`/`EntityServiceException` en `notFound()`. Un fallo de `encodeJson()` (UTF-8 inválido, NAN) responde `404 "Failed to encode content as JSON"` — semánticamente incorrecto y confuso. El test que cubre este camino solo exige "un error JSON controlado", así que no fija el código.
- Sugerencia: distinguir en el mapeo: "not found/deleted" → 404; fallos de encoding/consulta → 422 o 500. Ajustar el test para fijar el código elegido.

**10. El registro de validadores está duplicado respecto a `DefaultFieldValidatorRegistryFactory`**
- `backend/src/config/app.php:106`
- Las líneas 106-117 construyen a mano el mismo mapa de 10 validadores que `DefaultFieldValidatorRegistryFactory::create()`. Producción usa la copia de `app.php`; el constructor por defecto de `ValidationService` y los tests usan la factory. Al añadir un tipo hay que tocar dos sitios y una divergencia pasaría inadvertida (los tests seguirían en verde contra la factory).
- Sugerencia: `$container->singleton(FieldValidatorRegistry::class, fn() => (new DefaultFieldValidatorRegistryFactory())->create());` y borrar el mapa inline.

**11. El guard de dependientes va fuera de la transacción y solo ve plugins entity activos**
- `backend/src/services/EntityService.php:149`
- `guardNoDependentRecords()` se ejecuta antes de `beginTransaction()` (línea 151): entre el chequeo y el commit otro request puede crear un dependiente (TOCTOU). Más relevante: el resolver itera `listActiveEntitySlugs()`, así que si el plugin fuente está *inactivo* sus registros no bloquean el borrado del objetivo → referencias huérfanas al reactivarlo. El backlog documenta el límite equivalente para extensiones pero no este.
- Sugerencia: mover el guard dentro de la transacción y decidir/documentar si las relaciones de plugins inactivos cuentan como bloqueo (probablemente sí: los datos siguen en `plugin_entity_data`).

**12. Falta `GET /api/v1/entities/{slug}/options` en la tabla de endpoints**
- `docs/03-api/endpoints.md:11`
- El endpoint existe, está ruteado (routes.php:44), implementado (`options()`, 153-171) y testeado (EntityOptionsTest, 9 casos), pero la tabla no lo lista — el único endpoint de `/entities/*` ausente. (Adyacente: también faltan `POST /plugins/{slug}/move-up|move-down`, routes.php:70-71.)
- Sugerencia: añadir la fila de `options` (y las de move-up/move-down si se aprovecha el commit).

**13. La convención de claves de `persons` en AGENTS.md no casa con el schema real (`email` vs `mail`)**
- `AGENTS.md:161`
- La sección "Schemas y datos" ordena usar `email`, `creation_stamp`, `is_active`; el schema real (plugins/persons/schema.json:61) usa la clave **`mail`** (tipo `mail`) y ya no contiene `creation_stamp` ni `is_active`; el hook de unicidad opera sobre `'mail'`. Es el tipo de inversión doc↔código que un lector (o un agente siguiendo AGENTS.md) reproduciría al crear fixtures, obteniendo `unknown_field`.
- Sugerencia: actualizar AGENTS.md a las claves vigentes, o si `email` es la convención deseada, planificar el renombrado con migración de datos; en cualquier caso, alinear ambos.

### NIT

**14. Docblocks de clase desactualizados en las tres piezas**
- `backend/src/services/EntityService.php:27`, `backend/src/repositories/GenericRepository.php:18`, `backend/src/controllers/EntityController.php:24`
- La lista de métodos de `EntityService` omite `listRecordsPage`, `findRecordsByField` y `listOptions`; la de `GenericRepository` omite cuatro métodos; el docblock de rutas de `EntityController` no lista `GET /api/v1/entities`, `/tabs` ni `/actions`. Mismo patrón de "docblock-inventario que caduca" ya corregido en agosto.
- Sugerencia: actualizar las tres listas o sustituirlas por una frase de propósito sin inventario.

**15. Patrón `$hasError` innecesario en create/update**
- `backend/src/controllers/EntityController.php:246`
- `create()` (246-260) y `update()` (303-317) usan `$hasError = false; ... if ($hasError) return;` cuando un `return` dentro del `catch` (como hace `destroy()`) elimina el flag y el `$record = null`.
- Sugerencia: simplificar con early-return en el catch.

**16. `show()` ignora el slug de la URL**
- `backend/src/controllers/EntityController.php:267`
- `GET /entities/cualquier-cosa/records/{id}` devuelve el registro de otra entidad — versión en lectura del hallazgo mayor 1; mismo arreglo de pertenencia aplicable.
- Sugerencia: al arreglar la pertenencia en `updateRecord`, aplicar el mismo criterio en `show()`.

**17. Los combos prioritarios de `EntityOptionLabelBuilder` ignoran `summaryView` y los `fields` en forma lista no generan label**
- `backend/src/services/EntityOptionLabelBuilder.php:55`
- `summaryKeysToDisplay()` (55-59) elige `type+name+surnames` / `type+name+description` solo por existencia, sin respetar `summaryView:false` (un campo excluido explícitamente aparecería en la etiqueta), mientras la vía genérica (línea 142) sí filtra. Y `collectFieldsDefinitions()` (72-83) descarta `fields` en forma lista (clave int), forma que `SchemaFieldExtractor` sí soporta: una entidad declarada así caería siempre al id como label.
- Sugerencia: si esto replica a `EntityRecordModel.recordSummaryLabel()` del frontend, documentarlo como espejo deliberado; si no, filtrar `summaryView` también en los combos y reutilizar la resolución de `SchemaFieldExtractor`.

**18. `listRecords()` e `includeDeleted` sin consumidor de producción**
- `backend/src/services/EntityService.php:208`
- `listRecords()` solo lo llama su test (el controlador usa `listRecordsPage()`), y el parámetro `includeDeleted` de `listRecords()`/`all()`/`paginate()` jamás se pasa a `true` en todo `backend/src` ni en tests: API aspiracional sin caso de uso.
- Sugerencia: eliminar `listRecords()` e `includeDeleted` hasta que exista la story de papelera, o anotar el TODO que los justifica.

## Cobertura de tests

La cobertura es amplia y honesta con el código actual: `ValidationServiceTest` (16 casos), `FieldValidatorsTest` (los 10 validadores, incluidos los negativos de timestamp que antes blindaban el bug — ya corregido), `SchemaFieldExtractorTest`, `EntityServiceHooksTest` (ciclo completo before/after con stubs), `EntityServiceTest` de integración (CRUD, cascada a `plugin_extension_data`, bloqueo por dependientes, y dos tests notables de rollback transaccional), `EntityControllerTest` (envelope, 201/422/404, paginación, orden por identity editable), `GenericRepositoryTest`, `EntityDataTableTest`, `MigrationIdempotenceTest` (ya portable vía `PSQL_PATH`/`DB_*`, cubre las 6 migraciones), `EntityOptionsTest` (9 casos de labels) y `ReverseRelationTest` (tabs, desambiguación, filtro y rechazo de campo desconocido). Los tests-guardián `SystemEntitiesTableTest`/`EntityMetadataTableTest` siguen siendo válidos (solo el *nombre* de un caso aún dice "after migration 010", migración inexistente tras el squash; el docblock sí lo explica). **Huecos importantes**, alineados con los hallazgos: nadie ejercita slug/id cruzados en update/show, ni `GET records` con slug inexistente (el 500), ni el vaciado de un campo `required` por PUT, ni la ordenación numérica, ni el mapeo select→label y `ui_field_order` en `listOptions`, ni escrituras sobre slugs de plugins extension/inactivos. No hay tests que afirmen comportamientos ya inexistentes.

## Observaciones transversales

- **El slug de la URL como parámetro decorativo.** `show()`, `update()` y (mitigado) `destroy()` repiten el patrón de no contrastar el registro con la entidad de la ruta; falta un helper único "cargar registro de esta entidad o 404". Raíz común de los hallazgos 1 y 16.
- **Manejo de errores por capas, sin red final.** Cada endpoint decide qué capturar y el mapeo a HTTP es desigual (404 comodín, dos rutas sin captura, ningún manejador global). Una tabla única excepción→status y un catch global en el Router cerrarían la clase entera de fallos.
- **JSONB sin política de tipos.** Number como string aceptado por el validador, `''` que se persiste sin validar, y orden textual en `paginate()` son tres caras del mismo vacío: no hay normalización de tipos antes de persistir en `content`.
- **Integridad referencial 100 % en aplicación.** Coherente con el diseño sin FKs, pero los guards tienen huecos sistemáticos en los bordes: plugins inactivos, plugins extension y TOCTOU del guard de dependientes.
- **Docblocks-inventario que caducan.** El estilo narrativo vuelve a desincronizarse meses después de la limpieza de agosto.
- **En positivo:** el ciclo de deuda funciona — los 12 hallazgos de la auditoría 2026-08-11 sobre este ámbito aparecen corregidos y con tests de regresión, y no queda resto de `system_entities`/`entity_metadata` en código de producción.
