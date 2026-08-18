# Auditoría — Plugins de demostración y seeders de datos

**Subsistema:** `plugins/` (los 9 plugins de disco) + seeders de negocio + tools de setup
**EPIC cubiertas:** EPIC 6.4, 10 (stories 10.4-10.6)
**Severidades:** 0 crítico · 4 mayor · 5 menor · 4 nit

Índice: [Auditoría consolidada](00-informe-consolidado.md)

Lectura íntegra de las 9 carpetas de `plugins/` (persons, comments, orders, invoices, basic, optometries, contact_lenses, demoinventory, products — manifest.json, schema.json, PHP y JS de cada una), `backend/src/database/seeders/` (CoreEntitySeeder, ExtensionDataSeeder, BusinessDataSeeder y support/), `tools/setup/seed-business-data.php` y `switch-demoinventory-version.php`, con investigación del origen de demoinventory/products (git log, Grep de referencias) y contraste con backlog, README y AGENTS.md.

---

## Resumen

El subsistema está en buen estado funcional: los schemas usan solo tipos que el motor registra (`DefaultFieldValidatorRegistryFactory` cubre los 10 tipos empleados), la idempotencia "todo o nada por grupo" del seeder es sólida (transacción por grupo + reutilización de ids existentes), y los rangos numéricos/fechas sembrados son coherentes con los `min`/`max` de los schemas y con el AC de STORY 10.6 (`issue_date >= order_date` garantizado por construcción). La deuda principal es de dos tipos: una contradicción normativa frontal entre `AGENTS.md` y la implementación real (slug `clients` y clave `mail`), y duplicación copy-paste masiva entre los `Hooks.php` (y en menor medida los `plugin.js`) de los plugins de extensión.

## Hallazgos por severidad

### MAYOR

**1. AGENTS.md prohíbe exactamente lo que el código hace con `clients`**
- `AGENTS.md:150-153` vs `plugins/optometries/manifest.json:7`
- `AGENTS.md` (fuente canónica de instrucciones) ordena: *"No reintroducir `client`/`clients` como slug funcional, fixture o dato demo"*. Pero la implementación real de EPIC 10 hace exactamente eso, de forma deliberada y documentada en el backlog: `plugins/optometries/manifest.json:7` y `plugins/contact_lenses/manifest.json:7` declaran `"target_entity": "clients"`; `BusinessDataSeeder.php:28` empieza `REQUIRED_ACTIVE_SLUGS` por `'clients'`; `CoreEntitySeeder.php:176` siembra 200 filas con `entity_slug='clients'`; `ExtensionDataSeeder.php:90,114,138` ata fichas y comentarios a `'clients'`; y `skills/seed-business-data/SKILL.md` + `README.md:181` documentan `clients` como grupo demo. El backlog (STORY 10.5 nota 3, backlog.md:1268) justifica la decisión ("la instancia activa se renombró a `clients`"), pero nadie actualizó `AGENTS.md`. Consecuencia práctica grave: cualquier agente/desarrollador que obedezca la regla canónica "limpiará" `clients` y romperá la demo completa, y la STORY 11.1 (backlog.md:1307, "Sin ningún rastro de `clients` que debiera ser `persons`") es literalmente inauditable con las dos normas vigentes a la vez.
- Sugerencia: actualizar `AGENTS.md` para distinguir `plugin_name` (identidad técnica, `persons`, no reintroducir `clients` aquí) de slugs de instancia (editables por instalación; la demo usa `clients`/`distributors`/`ophthalmologists`), o renombrar la instancia demo y los `target_entity` a un slug no prohibido. Una de las dos fuentes tiene que ceder.

**2. La demo no es reproducible desde el repo: el seeder asume instancias y relations que nada en el repo crea ni documenta**
- `backend/src/database/seeders/BusinessDataSeeder.php:88-103`
- `assertRequiredPluginsActive()` exige 11 slugs activos, pero 6 (`clients`, `distributors`, `ophthalmologists`, `brands`, `manufacturers`, `sales`) no existen como carpeta en `plugins/`: son instancias adicionales creadas a mano vía PluginManager en la BD concreta del usuario. `tools/setup/sync-plugins.php` solo registra carpetas de disco, así que en una instalación limpia el seeder aborta siempre — y el prerrequisito de `skills/seed-business-data/SKILL.md:41-43` es engañoso porque ese script no puede crear esas instancias. Además, `CoreEntitySeeder` siembra claves que ningún `schema.json` de disco declara y cuya existencia no se valida: `id_distributor` (203) e `id_client` (255) dependen de relations configuradas por instalación, y `buildDistributorContent()`/`buildOphthalmologistContent()` (268-318) siembran `legal_name`, `phone_orders`, `fax`, `web`, `client_code`, `contact`, `phone_mobile`, `type`, `numero`, que solo existen como campos adicionales en la BD del usuario. Si esas configuraciones faltan, el seeder inserta igualmente (escribe directo a BD, sin pasar por la validación de la API) y los datos quedan invisibles/huérfanos en la UI, degradando en silencio el AC "datos coherentes entre sí".
- Sugerencia: (a) documentar en la skill/README el procedimiento completo de aprovisionamiento (crear las 6 instancias, configurar las relations y campos adicionales) o, mejor, un script de aprovisionamiento idempotente; (b) ampliar `assertRequiredPluginsActive()` para verificar también que `schema_json` de `orders`/`sales` contiene las relations que el seeder va a poblar, abortando con mensaje claro.

**3. `contact_lenses/Hooks.php` es copy-paste casi íntegro de `optometries/Hooks.php` (y el patrón se repite en comments)**
- `plugins/contact_lenses/Hooks.php:27-262`
- 262 líneas que son copia de `optometries/Hooks.php` (263) cambiando solo los literales `'contact_lenses'`/`'optometries'`, `'Lentillas'`/`'Optometrías'` y `'fa-glasses'`/`'fa-eye'`: `resolvePriority()`, `allowedInstances()`, `decodeSchemaSection()`, `stringOr()`, `floatOrNull()`, `extractRelations()`, `extractFields()` y `extractSummaryFields()` son idénticos línea a línea (optometries:83-262 vs contact_lenses:82-261). `comments/Hooks.php:76-134` duplica además el mismo `resolvePriority()`/`allowedInstances()` por tercera vez. El propio repo ya resolvió este problema para los hooks de unicidad extrayendo `backend/src/plugins/contracts/AbstractUniqueFieldHook.php` (que persons/invoices/products heredan en ~30 líneas); los hooks de pestañas de extensión no recibieron el mismo tratamiento. Cualquier fix en la lógica de instancias/prioridad hay que aplicarlo hoy en 3 sitios.
- Sugerencia: extraer un `AbstractExtensionTabHook` en `backend/src/plugins/contracts/` con métodos abstractos `pluginName()`, `tabLabel()`, `tabIcon()` y un gancho opcional para el payload extra, dejando cada `Hooks.php` de extensión en ~25 líneas.

**4. La convención canónica de claves de `persons` (`email`, `creation_stamp`, `is_active`) no coincide con el schema real (`mail`)**
- `AGENTS.md:161-167` vs `plugins/persons/schema.json:61-63`
- `AGENTS.md` ordena "Para `persons`, usar: `name`, `surnames`, `email`, `phone`, `creation_stamp`, `is_active`". El schema real usa `mail` (tipo `mail`), no `email`, y no existen `creation_stamp` ni `is_active` en ningún sitio del plugin. La clave `mail` está fijada por `persons/Hooks.php:24` (`fieldName(): 'mail'`), por `PersonsPluginTest.php:150` y por el seeder (`CoreEntitySeeder.php:340`). El código es consistente consigo mismo; es la fuente canónica la que está desactualizada, con el mismo riesgo del hallazgo 1: un agente que siga `AGENTS.md` generará payloads/fixtures con `email` que el hook de unicidad y la UI ignorarán silenciosamente.
- Sugerencia: actualizar la lista de `AGENTS.md` a las claves reales (`mail`, `phone`, `surnames`, `identity_document_number`, ...) o, si `email` es la meta, planificar la migración (schema + hook + tests + datos) como story explícita.

### MENOR

**5. `sales` listado en README como plugin de entidad de demostración cuando fue descartado como plugin**
- `README.md:15`
- "Estado actual del MVP" dice "los plugins de demostración de entidad (`orders`, `sales`, `invoices`, `basic`)". `sales` no existe en `plugins/`: STORY 10.4 lo descartó como plugin (backlog.md:1247,1250) y STORY 10.6 lo reintrodujo solo como segunda *instancia* BD de `orders`. La línea 179 del mismo README lo cuenta bien; la 15 contradice al backlog y al disco.
- Sugerencia: sustituir por "(`orders`, `invoices`, `basic`)" y mencionar `sales` como instancia demo de `orders`.

**6. `"editable": false` sobre `body` de comments es un flag muerto contradicho por la propia UI del plugin**
- `plugins/comments/schema.json:7`
- `body` declara `"editable": false`, pero el motor solo respeta `editable` en `identities` (`SchemaFieldExtractor.php:64-72`; en `fields` se ignora) y el propio `comments/plugin.js` implementa edición de comentarios con PUT del `body` (136-153 y botones de edición). El flag documenta lo contrario de lo que el plugin hace.
- Sugerencia: eliminar `"editable": false` de `body` (y valorar si `auto_generated` sobre `author_id`/`stamp` en `fields` significa algo para el motor o es también decorativo — hoy el autogenerado real lo hace el controller de extensiones).

**7. `demoinventory` y `products`: plugins vivos pero invisibles para el backlog y presentes en el catálogo de cualquier instalación**
- `plugins/demoinventory/` + `plugins/products/`
- Investigado el origen: ninguno es huérfano. `demoinventory` es el fixture de STORY 7.5 (su README propio lo documenta, y `frontend/tests/e2e/tests/plugin-manager.spec.js` lo usa para probar sync/update/rollback junto con `switch-demoinventory-version.php`). `products` nació como plantilla mínima (commit `77021b8`) y lo referencian `docs/04-plugins/plantilla-plugin-entidad.md:219`, `ProductsPluginTest.php` y el docblock de `AbstractUniqueFieldHook`. Pero ninguno aparece en el backlog (la única mención de products es de pasada, backlog.md:1253), y ambos se registran como entidades reales del catálogo en cuanto se ejecuta `sync-plugins.php`, mezclando fixtures de test/plantillas con plugins de producto (la skill de seed tiene que excluirlos explícitamente, SKILL.md:31-32).
- Sugerencia: documentar su rol (una línea en backlog/README) y valorar moverlos a un directorio de fixtures fuera del discovery por defecto, o marcarlos en el manifest como demo para poder filtrarlos.

**8. Clave técnica en español `numero` sembrada en JSONB**
- `backend/src/database/seeders/CoreEntitySeeder.php:307`
- `buildOphthalmologistContent()` siembra `'numero' => FakeDataGenerator::ophthalmologistNumero()`. AGENTS.md ("Las claves tecnicas de schemas, payloads y DB deben ir en ingles") lo prohíbe expresamente. Probablemente replica el nombre real del campo adicional en la BD del usuario, pero inmortaliza en el repo una clave en español para el dato demo insignia (y `FakeDataGenerator::ophthalmologistNumero()`, 276, propaga el nombre).
- Sugerencia: renombrar a `license_number` (o `registration_number`) coordinadamente con el campo adicional de la instancia real, o dejar constancia del motivo junto a la clave.

**9. Clase Panel de `contact_lenses/plugin.js` duplicada de `optometries/plugin.js`**
- `plugins/contact_lenses/plugin.js:420-543`
- Los helpers de capas sí se compartieron en `ExtensionLayerFields.js` (bien), pero `ContactLensesPanel.#build()` es copia de `OptometriesPanel.#build()` (367-490) cambiando solo textos: tabla resumen con `summaryFields`/`uiFieldOrder`, `deleteItem` con confirm+notificación, botón "Añadir" y carga inicial idénticos (~120 líneas). Tercer eco parcial en `comments/plugin.js`.
- Sugerencia: extraer un `ExtensionHistoryPanel` compartido (parametrizado por textos y `buildDetailForm`) junto a `ExtensionLayerFields.js`, dejando cada `plugin.js` con su formulario específico y el registro en `PluginPanelRegistry`.

### NIT

**10. Resto `client` en singular en un docblock de comments**
- `plugins/comments/plugin.js:47`
- El ejemplo dice `e.g. /plugins/comments/client/{id}` — slug `client` pre-STORY 10.2 que ya no existe en ninguna forma (los docblocks de optometries/contact_lenses usan `clients`).
- Sugerencia: actualizar a `/plugins/comments/clients/{id}`.

**11. Labels sin tilde en persons, inconsistentes con el resto de plugins**
- `plugins/persons/schema.json:36,57,71`
- "Direccion", "Codigo postal", "Telefono" (y `products/manifest.json:4` con `label_singular: "producto"` en minúscula) frente a invoices/optometries que sí acentúan. Las labels visibles en español son la cara de la demo.
- Sugerencia: homogeneizar tildes y capitalización en persons y products (y añadir newline final a products/schema.json:40).

**12. `origin: "suggested"` explícito y redundante en demoinventory**
- `plugins/demoinventory/schema.json:23,31,39`
- Es el único plugin que declara `origin` en sus `custom_fields` de disco; el motor ya asume `'suggested'` por defecto (`PluginConfigService.php:256`). Solo añade ruido y la falsa sensación de que el resto de plugins hacen algo distinto.
- Sugerencia: eliminar los tres `origin` (v1/v2/actual) o declararlo en todos los plugins por igual.

**13. `sampleWithoutReplacement` degrada en silencio si el pool es corto**
- `backend/src/database/seeders/support/RandomPrimitives.php:62-68`
- Si `count` supera el tamaño del pool devuelve menos elementos sin avisar. Hoy no ocurre (BRANDS 30≤36, MANUFACTURERS 15≤20), pero quien suba `BRANDS_COUNT` obtendrá menos marcas de las pedidas sin error, rompiendo el AC de volumen sin señal.
- Sugerencia: lanzar `SeederException` cuando `count > count($pool)`.

## Cobertura de tests

Los plugins de disco están bien cubiertos por tests de contrato: `PersonsPluginTest` (9: manifest, schema con set exacto de custom_fields, hook de unicidad de `mail` con stub PDO, incluido slug renombrado de STORY 10.3), `OrdersPluginTest` (5, incluida la aserción explícita de `relations: []` y de la ausencia de Hooks.php), `InvoicesPluginTest` y `ProductsPluginTest` (11 c/u, con unicidad de `invoice_number`/`sku`), `BasicPluginTest` (5), y `OptometriesPluginTest`/`ContactLensesPluginTest` (17 c/u: set exacto de campos, layers, resortable, relations y el payload de `registerTabs`). `CommentsPluginTest` (integración, 23 tests) cubre el CRUD real contra BD, multi-instancia, prioridad por `sort_order`, permisos de autor y resolución de `author_name`. `demoinventory` se ejercita solo vía E2E (plugin-manager.spec.js), sin test de contrato unitario. **Huecos grandes: cero tests para todo el subsistema de seeders de STORY 10.6** — ni `BusinessDataSeeder` (aborto por plugins faltantes, resolución de admin), ni la idempotencia por grupo de `CoreEntitySeeder`/`ExtensionDataSeeder` (el mecanismo más delicado), ni los generadores (`PersonDataGenerator::randomDni` con letra de control, unicidad de apellidos/emails, `DateRangeGenerator` con rango invertido) — y tampoco para `switch-demoinventory-version.php`. Los `plugin.js` de optometries/contact_lenses/comments no tienen suite de integración frontend propia (solo apariciones incidentales en DynamicTableTest/DynamicTabsTest/EntityEditTest) ni spec E2E de fichas.

## Observaciones transversales

1. **La deuda dominante no es de código sino de gobernanza documental:** `AGENTS.md` (fuente canónica) contradice al backlog y al código en los dos puntos más sensibles del subsistema (`clients` y `email`/`mail`). Las decisiones reales están bien razonadas en las notas del backlog, pero nunca se retropropagaron a la norma; los hallazgos 1 y 4 son el mismo patrón.
2. **El patrón "extraer contrato base" se aplicó a medias:** `AbstractUniqueFieldHook` demuestra que el equipo sabe eliminar copy-paste entre plugins, pero los hooks de pestañas (3 copias) y los paneles de historial (2-3 copias) quedaron fuera del mismo refactor.
3. **El seeder está acoplado a una instalación concreta no reconstruible desde el repo:** slugs de instancia, relations por instalación y campos adicionales viven solo en la BD del usuario del TFM; el repo contiene el generador de datos pero no la receta de la topología que asume.
4. **Calidad interna de los seeders alta:** separación limpia en 4 helpers estáticos, transacción por grupo, prepared statements reutilizados, comentarios que explican decisiones no obvias. El contraste con la falta total de tests de esa misma capa es notable.
