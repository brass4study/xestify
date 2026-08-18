# Auditoría — Schema/configuración de plugins y plugins extension

**Subsistema:** Configuración de schema (entity/extension) y contenido de extensiones
**EPIC cubiertas:** EPIC 6, 7 y refuerzos de EPIC 10
**Severidades:** 1 crítico · 3 mayor · 8 menor · 5 nit

Índice: [Auditoría consolidada](00-informe-consolidado.md)

Lectura íntegra de `backend/src/plugins/schema/` (PluginConfigService, ExtensionPluginConfigService, PluginSchemaMergeService, InstalledPluginSchemaValidator, PluginConfigFieldNormalizer, RelationsPayloadCompiler, ReverseRelationTabResolver, PluginSchemaFieldNormalizer y utilidades), `PluginExtensionController`, `ExtensionPluginDataStore` y `ExtensionPluginContentService`, sus llamadores, docs (`docs/03-api/`), la auditoría archivada 20260811 (verificación de correcciones 04.01–04.11) y los tests del ámbito.

---

## Resumen

El subsistema está notablemente mejor que en la auditoría de 20260811 (los hallazgos 04.01–04.11 constan corregidos y se verificó: control de propiedad en extensión, catálogo `plugin_suggested_custom_fields`, `SchemaComparisonUtil` compartido, fachada limpia). Sin embargo, la corrección del antiguo 04.01 quedó incompleta y las features posteriores (`summaryView` editable en campos base, `layer` de STORY 10.5) la han re-roto por otra vía: **cualquier guardado de configuración deja el plugin marcado como "corrupt installed schema" en sync y bloquea sus updates**.

## Hallazgos por severidad

### CRÍTICO

**1. Guardar la config de un plugin lo marca "corrupto" en sync y bloquea sus updates para siempre**
- `backend/src/plugins/schema/InstalledPluginSchemaValidator.php:221`
- El guardado de configuración "engorda" las definiciones persistidas con claves que el `schema.json` de disco nunca tiene, y los dos verificadores de integridad comparan por igualdad estricta (solo normalizando orden de claves, `SchemaComparisonUtil::normalize`), sin lista de exclusión:
  - Entidades: `PluginConfigService::applyBaseSummaryView()` (558-570) escribe `summaryView` en **todas** las filas base de `schema_json.fields` en cada guardado, aunque sea no-op. `persons` en disco tiene `fields.name = {type, required, label}`; tras el primer PUT pasa a `{..., summaryView}`.
  - Entidades: `compileEntityConfigRows()` (494-509) reescribe `plugin_suggested_custom_fields` con entradas que llevan `origin`, `layer` y `summaryView`; el disco nunca lleva `origin` ni `layer`.
  - Extensiones: `ExtensionPluginConfigService::mergeFieldDefinition()` (245-256) añade `summaryView` y `layer` a **todos** los `fields` reconstruidos.
- Consecuencias verificadas: (1) `PluginSyncService::syncExistingInstance()` (177-182) llama a `assertContainsCanonical()` para todo plugin `unchanged` con schema; `assertDefinitionsMatch()` (221-226) no ignora ninguna clave → `DomainException("... corrupt installed schema: fields.name changed")` → el plugin sale como `error` en cada `POST /plugins/sync`. (2) `PluginUpdateService::update()` (73-79) llama a `assertCanApplyUpdate()` y `mergeAdditively()`; `mergeAssociativeSection()` (PluginSchemaMergeService.php:107-115) tampoco ignora `summaryView`/`layer` → `"update is not additive: fields.X changed"` → **imposible actualizar un plugin configurado** — exactamente la consecuencia A del antiguo 04.01 que se dio por cerrado (`dc57714`). El fix de 04.01 solo añadió `ignoreKeys: ['origin']` en el merge del catálogo y la lectura del catálogo en el validador; no cubrió `layer`/`summaryView`, y en `InstalledPluginSchemaValidator` no se ignora ni siquiera `origin`.
- Sugerencia: comparar por proyección estructural en ambos verificadores y en el merge: whitelist compartida de claves con significado de contrato (`type`, `required`, `label`, `options`, `min`, `max`, `auto_generated`, `editable`, `resortable`) excluyendo las claves de preferencia de administración (`summaryView`, `layer`, `origin`) antes de `SchemaComparisonUtil::normalize()`. Añadir el test de integración que falta: `registerNew()` → `saveConfig()` real → `syncAll()` debe dar `unchanged` y `update()` debe funcionar.

### MAYOR

**2. Un payload parcial borra sugerencias del catálogo de forma permanente**
- `backend/src/plugins/schema/PluginConfigService.php:95`
- `applyConfigPayload()` reemplaza `plugin_suggested_custom_fields` en bloque con `$compiled['suggested_catalog']`, construido **solo** con las filas presentes en el payload (475-514). Para los campos base hay aserción de completitud (81-85), para las sugerencias no: un cliente API que omita la fila de `phone` (o un bug de estado en `PluginConfig.js`) elimina esa sugerencia del catálogo sin aviso y sin vuelta atrás (el catálogo solo se re-siembra en update de versión, no en sync). El estado resultante es además el que `assertContainsCanonical()` denuncia como corrupto, enlazando con el crítico.
- Sugerencia: espejo de la regla de campos base (exigir que toda entrada del catálogo aparezca en el payload) o, más robusto, construir el nuevo catálogo como merge del actual con las filas recibidas.

**3. Las `options` de un select base de extensión sí son editables vía API pese al invariante "base no editable"**
- `backend/src/plugins/schema/ExtensionPluginConfigService.php:348`
- `assertImmutableBaseField()` (348-368) compara solo `type`/`label`/`required`/`active`; no `options`. Pero `mergeFieldDefinition()` toma las options **del payload** cuando `type === 'select'` (258-260) y `options` está excluida del passthrough de disco (268). Una petición PUT manipulada puede reescribir las opciones de un select base (p. ej. `od_wear_schedule` de `contact_lenses`), cambiando qué valores acepta `SelectFieldValidator`, cuando el proyecto declara que los campos base no deben poder editarse desde UI/API. Endpoint admin-only (riesgo rebajado), pero rompe el invariante que el resto del código defiende.
- Sugerencia: comparar también `options` (normalizadas con `normalizeSelectOptions()`) cuando el campo base es `select`, o ignorar `row['options']` en `mergeFieldDefinition()` para campos no-`additional`.

**4. Cada PUT reescribe `stamp` con la fecha actual: editar un comentario pierde su fecha original**
- `backend/src/controllers/ExtensionPluginContentService.php:160`
- En `normalizeContentBySchema()` con `isUpdate = true` solo se salta `author_id` (53-56, con comentario "Authorship is set once at creation and must stay immutable on update"); `stamp` sigue entrando en `resolveAutoGeneratedValue()`, cuya rama `if ($key === 'stamp') { $value = date('c'); }` (160-162) es incondicional. Como `updateRow()` hace merge JSONB (`content || :content`), cada edición sobrescribe el campo "Fecha" con el momento de la edición; la tabla solo tiene `created_at`, así que el valor original se pierde sin rastro. `CommentsPluginTest` cubre la inmutabilidad de `author_id` en PUT (695) pero no la de `stamp`.
- Sugerencia: tratar `stamp` como `author_id` en updates, o renombrar/documentarlo como "última modificación" con su test. Idealmente sustituir la magia por nombre de clave por un metadato del schema (`immutable: true` / `default: "now"`).

### MENOR

**5. El camino entity pierde metadatos author-locked del catálogo al guardar (asimetría con extension)**
- `backend/src/plugins/schema/PluginConfigService.php:494`
- `compileEntityConfigRows()` construye cada `$candidate` solo con `key/type/label/required/summaryView/origin/layer(/options)`; a diferencia de `ExtensionPluginConfigService::mergeFieldDefinition()` (267-273), no hay passthrough de metadatos de la definición existente. Si un plugin entity declarase `resortable: false`, `min`/`max` o `editable` en sus `custom_fields`, el primer guardado los eliminaría: `assertLayerRespectsResortable()` (585-596) dejaría de proteger la capa y la validación perdería los límites. Latente (ningún plugin entity del repo los declara hoy), pero es pérdida silenciosa de datos.
- Sugerencia: replicar el bucle de passthrough desde `$catalogByKey[$key]` (excluyendo las claves controladas por el payload), igual que la vía extension.

**6. Sin bypass de rol en `guardOwnership`: un admin no puede moderar contenido ajeno**
- `backend/src/controllers/PluginExtensionController.php:395`
- `guardOwnership()` solo autoriza cuando `requesterId === authorId` (410-418); no hay excepción para `admin`. Un administrador no puede editar ni borrar el comentario de otro usuario (403), y como el borrado del registro padre arrastra sus comentarios, la única "moderación" posible es destruir el registro entero. Puede ser decisión de producto, pero no está documentada.
- Sugerencia: decidir y documentar: bypass explícito con `hasRole('admin')`, o nota en `docs/03-api/endpoints.md` de que el contenido con autoría es inmutable para terceros, admin incluido.

**7. `target_entity: '*'` no valida que la entidad exista/esté activa; se puede escribir extensión sobre registros de entidades desactivadas**
- `backend/src/controllers/ExtensionPluginContentService.php:101`
- `isEntityAllowedByPluginConfig()` devuelve `true` para cualquier string cuando el target es `*` (104-109), y `parentRecordExists()` (PluginExtensionController.php:435-447) solo comprueba `plugin_entity_data` con `deleted_at IS NULL` — no que el plugin de la entidad padre esté `active`. Con un plugin entity desactivado, cualquier usuario puede seguir creando/editando/borrando items de extensión sobre sus registros por API.
- Sugerencia: en `guardExtensionRequest()`, validar `{entity}` contra `listActiveEntitySlugs()` también cuando el target es `*`.

**8. Extensión activa sin schema persiste contenido arbitrario sin filtrar**
- `backend/src/controllers/ExtensionPluginContentService.php:47`
- Si `schema_json` está ausente/corrupto, `loadExtensionSchema()` devuelve `[]`, `validate()` no valida nada y `normalizeContentBySchema()` hace `return $data;` sin whitelist (47-49). Un plugin extension activo sin schema acepta y persiste cualquier JSON en `plugin_extension_data.content`. Contradice `docs/03-api/endpoints.md:38` ("validan `content` contra el schema del plugin antes de persistir").
- Sugerencia: con schema vacío, rechazar la escritura (409/422 "extension schema missing") o vaciar el contenido no declarado.

**9. `author_name` expone el email de los usuarios a cualquier autenticado**
- `backend/src/controllers/PluginExtensionController.php:259`
- `loadAuthorsById()` selecciona `email` y lo publica como `author_name` en cada GET/POST/PUT de extensión (258-277). Todo usuario autenticado que vea un registro con comentarios obtiene los emails de los demás usuarios. Es PII sin campo "display name" alternativo que lo justifique.
- Sugerencia: devolver un nombre visible si existe (o la parte local del email) y documentar la decisión.

**10. Deriva doc/test↔código: el guardado ya no "versiona" nada, pero doc y test siguen afirmándolo**
- `docs/03-api/endpoints.md:25`
- `schema_version` fue eliminado deliberadamente (decisión en `docs/09-history/decisiones-tecnicas.md:609`, backlog.md:1212); ni `updateSchemaConfig()` ni `updateExtensionConfig()` incrementan versión, y la respuesta real de `buildConfigResponse()` no incluye `schema_version`. Sin embargo: (a) endpoints.md:25 sigue diciendo "Guardar configuración … **y versionar schema**"; (b) `PluginManagerApiTest.php:895-942` ("updates config and bumps schema version") asserta `schema_version === 2` contra un **fake** que implementa el bump por su cuenta — el test pasa mientras documenta una API que ya no existe.
- Sugerencia: corregir la fila de endpoints.md; eliminar `schema_version` del fake y del assert.

**11. El contrato niega las relaciones editables en extensiones (obsoleto desde STORY 10.5) y omite `layers`**
- `docs/03-api/contratos/plugins.md:136`
- Las líneas 136 y 140 afirman "`relations` solo aparece/se procesa para plugins `entity`", pero desde STORY 10.5 `ExtensionPluginConfigService::buildConfigPayload()` devuelve `relations` (123) y `saveConfig()` las compila con el mismo `RelationsPayloadCompiler` (48-53); hay test que lo cubre (`PluginRelationsConfigTest:224`). Tampoco documenta `layers` ni `layer`/`resortable`/`locked`/`source` en las filas de `fields`.
- Sugerencia: actualizar `contratos/plugins.md` al contrato real.

**12. Flecos tras el fix e1df7d0: auto-relaciones invisibles (tab y guarda de borrado), N+1 y keys de relación sin formato restringido**
- `backend/src/plugins/schema/ReverseRelationTabResolver.php:31`
- El fix del separador (`relation-{source}-{key}`) está bien cerrado. Quedan tres flecos: (1) `resolve()` salta `$sourceSlug === $targetEntitySlug` (31-33) — nada impide al admin crear una relación de una entidad hacia sí misma (`RelationsPayloadCompiler` valida el target contra todas las entidades activas, incluida la propia), y esa relación ni genera tab inversa ni protege el borrado, porque `EntityService::guardNoDependentRecords()` reutiliza `resolve()`: se puede borrar un registro con dependientes de su misma entidad, dejando huérfanos. (2) `resolve()` hace `findBySlug()` por cada entidad activa (N+1) y corre también en cada delete. (3) `compileRow()` (RelationsPayloadCompiler.php:67-70) acepta cualquier key no vacía — con `:`/espacios/mayúsculas se generan ids de tab y URLs que solo funcionan gracias al parser tolerante del frontend, cuando toda clave técnica sigue `[a-z][a-z0-9_]*`.
- Sugerencia: (1) decidir y documentar el caso auto-referencial (soportarlo o rechazarlo en `compileRow()`); (2) una consulta que traiga los schemas de todas las entidades activas de una vez; (3) validar la key con `[a-z][a-z0-9_]*`.

### NIT

**13. Fallback de `author_id` al payload si el JWT no trae `sub`**
- `backend/src/controllers/ExtensionPluginContentService.php:170`
- `resolveAutoGeneratedValue()` acepta el `author_id` del payload cuando `user['sub']` está vacío (164-173). Hoy inalcanzable (AuthController siempre firma `sub`), pero es un agujero latente de suplantación de autoría.
- Sugerencia: eliminar el fallback: sin `sub`, `author_id = null`.

**14. Mensaje engañoso cuando la entidad no está permitida por `target_entity`**
- `backend/src/controllers/PluginExtensionController.php:354`
- `guardExtensionRequest()` responde `404 "Extension plugin is not active"` tanto cuando el plugin está inactivo como cuando la entidad no coincide con el `target_entity` (349-357) — diagnósticos distintos, mismo texto.
- Sugerencia: constante propia ("Entity not allowed for this extension plugin") para la segunda rama.

**15. Docblocks con inversiones sutiles**
- `backend/src/plugins/schema/PluginConfigService.php:553`
- (a) `applyBaseSummaryView()` documenta `@param $rawFields raw schema['fields'] as stored on disk`, pero recibe los fields ya **normalizados** por `decodePluginSchema()`, y esa forma normalizada es la que se persiste. (b) `PluginSchemaMergeService::mergeAdditively()` lanza `"schema.json not found for entity plugin: {$slug}"` (30) también para extensiones.
- Sugerencia: corregir ambos textos.

**16. El spread conserva `name`/`key` dentro de la definición y se llega a persistir**
- `backend/src/plugins/schema/PluginSchemaFieldNormalizer.php:32`
- En la rama lista, `[...$entry, ...]` deja `name`/`key` dentro del value del mapa resultante (32-37; ídem claves extra en la rama mapa), y como el guardado entity persiste los fields normalizados (hallazgo 1), esa redundancia acaba en `schema_json`.
- Sugerencia: excluir `name`/`key` del spread.

**17. Redundancias menores y duplicación asumida entre los dos servicios de config**
- `backend/src/plugins/schema/ExtensionPluginConfigService.php:104`
- (a) `'summaryView' => $normalized['summaryView'] ?? true` — `normalizeFieldDefinition()` siempre la establece; `??` muerto (ídem `resortable`/`layer`, 107-108). (b) `PluginConfigFieldNormalizer::normalizePayloadRows()` recalcula `summaryView` desde `$entry` cuando `$field['summaryView']` ya lo trae. (c) `relationsFromSchema()` y `normalizeLayers()` duplicados literalmente entre ambos servicios (345-398 vs 141-192, reconocido en docblocks) y `assertLayerRespectsResortable()` en dos variantes casi idénticas. (d) `saveConfig()` de extensión consulta `listActiveEntitySlugs()` dos veces por petición.
- Sugerencia: limpiar los `??` muertos y la doble consulta; valorar un `SchemaReadProjections` compartido si aparece una tercera copia.

## Cobertura de tests

Lo cubierto está bien elegido: unit para normalizadores y utilidades (`PluginConfigFieldNormalizerTest` con 14 casos incl. `layer`/`resortable`, `SchemaComparisonUtilTest`, `PluginSchemaMergeServiceTest` — incluye el catálogo—, `PluginTypeGuardTest`, `PluginSchemaReaderTest`), e integración real para relaciones (`PluginRelationsConfigTest`: 7 casos entity+extension, target inactivo, colisión de keys, layers/resortable), `summaryView` (`PluginFieldsConfigTest`, entity y extension con doble guardado), sync (`PluginSyncServiceTest`), update/rollback, registro, comments (`CommentsPluginTest`: 23 casos, incl. propiedad en PUT/DELETE e inmutabilidad de `author_id` — el antiguo 04.03 quedó bien cubierto), validación de extensiones (`ExtensionRelationsTest`) y tabs inversas (`ReverseRelationTest`, actualizado al id nuevo). No hay test unitario dedicado de `InstalledPluginSchemaValidator` (solo indirecto). **Huecos:** (a) no existe ningún test `saveConfig()` real → `syncAll()`/`update()` sobre el mismo plugin; peor: el test "configured plugin with an inactive suggested field" (PluginSyncServiceTest:291) siembra a mano un catálogo **sin** `origin`/`layer`/`summaryView`, una forma que `PluginConfigService` jamás produce — el test que da falsa confianza sobre el crítico; (b) `PluginManagerApiTest` prueba el controller contra un fake que aún implementa y asserta `schema_version`; (c) sin test de payload parcial que omita sugerencias; (d) sin test que intente cambiar `options` de un select base; (e) sin test del comportamiento de `stamp` en PUT; (f) sin test de admin-no-autor ni de escritura sobre entidad desactivada.

## Observaciones transversales

1. **Normalización que engorda vs. comparación estricta.** Patrón raíz del crítico: los caminos de guardado añaden claves default mientras los verificadores de integridad comparan definiciones completas. Cualquier clave de preferencia futura reabrirá el mismo bug. Conviene un único punto de verdad ("qué claves son contrato estructural") compartido por `InstalledPluginSchemaValidator`, `PluginSchemaMergeService` y los servicios de config.
2. **Dualidad entity/extension a medio unificar.** `PluginConfigService` despacha por tipo en tres sitios y a la vez implementa la lógica entity; `ExtensionPluginConfigService` es su gemelo con duplicación literal reconocida en docblocks. La simetría completa reduciría el fichero de 597 líneas y la tentación de arreglar un lado y olvidar el otro (como pasó con el passthrough y con `options`).
3. **SQL crudo en la capa controller.** `PluginExtensionController` consulta `users`, `plugins` y `plugin_entity_data` directamente pese a existir los repositorios; único rincón del subsistema que ignora esa capa.
4. **Convenciones mágicas por nombre de clave.** `stamp` y `author_id` tienen semántica hardcodeada por nombre en lugar de metadatos del schema (`immutable`, `default`); ya produjo una asimetría real.
5. **Los docs de contratos van una story por detrás del código** (relations de extensiones, `layers`, `schema_version`), mientras los de arquitectura/historia están al día — actualizar `docs/03-api` en el cierre de story es el eslabón débil.
