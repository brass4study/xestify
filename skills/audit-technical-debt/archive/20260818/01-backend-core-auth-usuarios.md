# Auditoría — Backend core, auth y usuarios

**Subsistema:** Core de infraestructura, autenticación y gestión de usuarios
**EPIC cubiertas:** EPIC 0, 1, 8
**Severidades:** 0 crítico · 3 mayor · 13 menor · 9 nit

Índice: [Auditoría consolidada](00-informe-consolidado.md)

Lectura íntegra de `backend/public/index.php`, `backend/src/bootstrap.php`, `backend/src/app.php`, `backend/src/core/` (salvo HookDispatcher, auditado en [03](03-backend-motor-plugins-nucleo.md)), `backend/src/config/`, `backend/src/middleware/`, `backend/src/exceptions/`, JwtService, ProfileUpdateAuthorizer, Auth/Health/User/ConfigurationController, User/ConfigurationRepository, UserSeeder y `tools/setup/bootstrap.php`/`seed-admin-user.php`, con verificación de usos (código muerto) y contraste contra `docs/03-api/`, backlog y AGENTS.md.

---

## Resumen

El subsistema está en buen estado general: la auditoría de 2026-08-11 dejó un núcleo limpio, con rutas fail-closed por defecto, envelope consistente, secretos sin defaults adivinables y sanitización de `password_hash` en respuestas. Los problemas que quedan son de segunda generación: seguridad de sesión aplazada que interactúa mal con funcionalidades ya implementadas (borrado de usuarios vs. tokens irrevocables), ausencia de red global de excepciones, y una deriva documental clara (la tabla canónica de endpoints no conoce los 10 endpoints de users/configurations).

## Hallazgos por severidad

### MAYOR

**1. Tokens irrevocables + sesión deslizante: un usuario borrado o degradado conserva acceso indefinidamente**
- `backend/src/middleware/AuthMiddleware.php:45`
- `AuthMiddleware::handle()` valida solo firma y expiración; nunca contrasta `sub` contra la BD ni relee roles. La autorización (`Request::hasRole`, Request.php:130-134) confía por completo en las claims del token. Combinado con la sesión deslizante (AuthMiddleware.php:45-47 emite `X-Refreshed-Token`; `JwtService::refresh()`, JwtService.php:99-104, reemite *las mismas claims* con `exp` nuevo): un admin borra a un usuario (`DELETE /api/v1/users/{id}`) o le retira el rol admin, y ese usuario **mantiene acceso completo mientras siga haciendo peticiones** — cada request dentro de la segunda mitad del TTL renueva el token para siempre. Solo `GET /users/me` devolvería 404; todos los endpoints de entidades, configuración y plugins siguen funcionando con los roles viejos. El backlog reconoce "A7: Hardening de sesiones" como post-MVP (backlog.md:49), pero la interacción concreta *sliding-refresh × usuario borrado = acceso perpetuo* no está escrita en ningún sitio, y el borrado de usuarios sí es funcionalidad MVP entregada.
- Sugerencia: mitigación mínima: en `AuthMiddleware`, antes de refrescar (o en cada request), verificar que `UserRepository::find($payload['sub'])` sigue devolviendo fila no borrada, y reconstruir `roles` desde la fila — una query indexada por PK. Alternativa más ligera: comprobar solo en la rama de refresh (limita la ventana al TTL restante). Documentar la decisión en `docs/03-api/autenticacion.md` y en la story A7.

**2. Sin manejador global de excepciones: cualquier Throwable no capturado rompe el contrato de envelope**
- `backend/src/bootstrap.php:41`
- `$app->run()` no está envuelto en try/catch y no hay `set_exception_handler` en ningún punto de `backend/src`. Rutas de fallo reales y alcanzables: (a) `xestifyBootPluginHooks()` (config/app.php:279-284, invocado en app.php:356) toca la BD **en cada request**, así que una caída de PostgreSQL lanza `DatabaseException` (con host/detalle del mensaje PDO) sin capturar; (b) `EnvironmentException` si falta `JWT_SECRET`; (c) `RepositoryException` desde `UserController::updatePasswordIfNeeded()` (hallazgo 6). El cliente recibe un fatal de PHP en lugar del `{ok:false,error:{...}}` que `ApiClientModel` espera, y con `APP_DEBUG` mal configurado puede filtrar rutas de fichero y detalles de conexión.
- Sugerencia: en `bootstrap.php` (o `public/index.php`), envolver `$app->run()` en `try/catch (Throwable)` que haga `error_log()` del detalle y emita `Response::make()->serverError()` genérico (mensaje detallado solo si `AppDebug::enabled()`).

**3. La tabla canónica de endpoints omite los 10 endpoints de users y configurations**
- `docs/03-api/endpoints.md:1`
- `endpoints.md` se presenta como el catálogo completo pero no lista **ninguno** de los endpoints implementados en routes.php:26-37: `GET/PUT /api/v1/configurations[/{key}]` (3 rutas) ni `GET/PUT /api/v1/users/me`, `GET /api/v1/users`, `GET/PUT/DELETE /api/v1/users/{id}`, `PUT /api/v1/users/{id}/password` (7 rutas). Tampoco existe contrato en `docs/03-api/contratos/` para users ni configuration. El detalle vive únicamente en backlog.md:904-913. Para un proyecto API-first, el documento de referencia de la API lleva un EPIC entero (el 8) de retraso.
- Sugerencia: añadir las 10 filas con su columna de autenticación y crear `contratos/users.md` y `contratos/configuration.md` con payloads de `updateMe` (`current_password`, semántica de seed users, 409 por email duplicado) y de `ui-preferences`.

### MENOR

**4. Enumeración de usuarios por canal de tiempo en login**
- `backend/src/controllers/AuthController.php:49`
- `if ($user === null || $isBlockedSeedUser || !password_verify(...))` cortocircuita: para un email inexistente **no se ejecuta `password_verify`**, así que la respuesta llega ~100 ms antes (coste bcrypt) que para un email existente con contraseña errónea. El mensaje es idéntico (hay test que lo asegura, AuthControllerTest.php:162-171), pero el tiempo delata qué emails existen.
- Sugerencia: cuando `$user === null`, verificar contra un hash bcrypt fijo de sacrificio y descartar el resultado, de forma que ambas ramas paguen el mismo coste.

**5. Update vacío sobre usuario inexistente devuelve 200 con data vacía**
- `backend/src/repositories/UserRepository.php:98`
- `if ($fields === []) { return $this->find($id) ?? []; }` — con payload sin campos actualizables y usuario inexistente devuelve `[]` en vez de lanzar `RepositoryException` como la rama con campos (117-119). `PUT /api/v1/users/{id-inexistente}` con body `{}` pasa `assertEditableTarget()` (UserController.php:157-158) y responde `{ok:true,data:[]}` con 200 — mientras el mismo PUT con `{"name":"x"}` responde 404. Misma asimetría en `updateMe` si el usuario fue borrado entre emisión del token y la petición.
- Sugerencia: en `UserRepository::update()`, lanzar `RepositoryException` cuando `find()` devuelve null; o en `assertEditableTarget()` responder 404 ante null.

**6. `updatePasswordIfNeeded()` sin try/catch y actualización de perfil no atómica**
- `backend/src/controllers/UserController.php:93`
- En `updateMe`, `applyUpdate()` captura `RepositoryException`, pero `updatePasswordIfNeeded()` (308-315) llama a `repository->updatePassword()` **sin captura**: si lanza, el resultado es un Throwable sin manejar *después* de haber persistido ya name/email/avatar — actualización partida en dos escrituras sin transacción y sin respuesta coherente.
- Sugerencia: envolver ambas escrituras en una transacción PDO (o capturar `RepositoryException` y responder envelopado), mismo patrón que `resetPassword()` (217-222).

**7. Sin validación de formato ni tipos en payloads de usuarios**
- `backend/src/controllers/UserController.php:317`
- Ni `updateMe` ni el `update` admin validan el payload: (a) `email` acepta cualquier string no vacío; (b) si `email`/`name` llegan como array, los casts `(string)` de `ProfileSecretVerifier::isEmailChange()` producen warning "Array to string conversion" y el bind PDO acaba en `RepositoryException` → respuesta **404** "Query failed: ..." (código equivocado y mensaje interno); (c) la contraseña nueva de `updateMe` acepta 1 carácter sin política mínima, mientras el reset admin genera 14. El proyecto tiene `ValidationService` con validador `mail` que aquí no se usa.
- Sugerencia: validar tipos y formato al inicio (`is_string`, `filter_var(..., FILTER_VALIDATE_EMAIL)`, longitud mínima de password), respondiendo 422 con details por campo.

**8. Fuga de detalles internos en errores 500 de configuración**
- `backend/src/controllers/ConfigurationController.php:42`
- Los tres métodos capturan `Throwable` y responden `serverError('Error: ' . $e->getMessage())` (42, 68, 99). `RepositoryException` encadena el mensaje de `PDOException` (ConfigurationRepository.php:111), así que un fallo de BD expone SQLSTATE, fragmentos de SQL o detalles del driver a cualquier usuario autenticado (`show()` no exige admin).
- Sugerencia: `error_log($e)` y mensaje genérico al cliente (detalle solo bajo `AppDebug::enabled()`).

**9. `password_hash` se selecciona siempre y se confía en sanitización de frontera**
- `backend/src/repositories/UserRepository.php:26`
- Las cuatro consultas (`find` :26, `findByEmail` :42, `all` :57, `update ... RETURNING` :102) devuelven `password_hash`, y la única barrera es `UserController::sanitizeUser()` aplicada llamada a llamada. El hallazgo **crítico** 01.01 de la auditoría anterior fue precisamente un `password_hash` filtrado por olvidar esta sanitización; el diseño actual reconstruye la misma trampa: cualquier consumidor nuevo del repositorio recibe hashes por defecto. Solo `findByEmail` (login) y el `find` de `ProfileSecretVerifier::matches()` necesitan el hash.
- Sugerencia: proyección sin `password_hash` por defecto y métodos explícitos `findWithSecret()`/`findByEmailWithSecret()` para los dos puntos que lo necesitan. Elimina la clase entera de bug en origen.

**10. Docblocks de `apiSuccess`/`apiError` prometen "and exit" pero `send()` no termina la ejecución**
- `backend/src/core/Response.php:31`
- "Static shortcut: emit a success envelope and exit" (línea 31; ídem apiError, 42), pero `send()` (158-169) solo hace `echo`. Un handler que se crea la promesa emitirá **dos JSON concatenados** sin que nada lo detecte. Además `apiSuccess`/`apiError` no tienen ningún consumidor en `backend/src` — API muerta en producción, viva solo en `RequestResponseTest`.
- Sugerencia: corregir los docblocks (o hacer el exit real); decidir si `apiSuccess`/`apiError` se eliminan o se documentan como azúcar legítimo.

**11. `ignoreParams($params)` invocado en métodos que sí usan `$params`**
- `backend/src/controllers/UserController.php:111`
- El helper (293-296) existe para silenciar el aviso de parámetro sin uso, pero se llama también en `show()` (:111), `update()` (:135), `resetPassword()` (:203) y `destroy()` (:232), que **usan `$params['id']` dos líneas después**. El nombre afirma algo falso sobre el flujo de datos y desorienta a quien refactoriza.
- Sugerencia: eliminar la llamada en los cuatro métodos que usan `$params`; en `me`/`updateMe`/`listUsers`, valorar quitar el parámetro de la firma.

**12. Tres estrategias distintas de fallback de `Request` y docblock invertido**
- `backend/src/controllers/AuthController.php:29`
- El docblock de `login()` dice "Injected in tests; built from globals in production", pero es al revés: en producción el Router **siempre** inyecta la Request (Router.php:84), así que la rama `$request ??= $this->requestFactory()->fromGlobals($params)` y el `requestFactory()` perezoso (68-75) son código muerto en producción, que existe solo para tests. `ConfigurationController` duplica el patrón (108-115) mientras `UserController` hace `$request ??= new Request()` (línea 40 y 7 más). Tres soluciones para el mismo problema en tres controladores hermanos = refactor perdido.
- Sugerencia: hacer `Request $request` obligatorio en los tres controladores y borrar fallbacks y factorías perezosas. Corregir el docblock.

**13. `seedIfEmpty()` ya no hace lo que su nombre dice**
- `backend/src/database/seeders/UserSeeder.php:19`
- El método no comprueba si la tabla está vacía: inserta siempre las dos cuentas con `ON CONFLICT (email) DO NOTHING` (el propio docblock, 11-15, describe el comportamiento nuevo). El nombre es un resto del comportamiento antiguo y contradice al docblock; hay tests cuyos títulos arrastran la confusión (DatabaseTest.php:164).
- Sugerencia: renombrar a `seedFixedAccounts()`, actualizando `tools/setup/seed-admin-user.php:9`, `DatabaseTest.php` (:141, :173, :190) y `AuthControllerTest.php:72`.

**14. Dos versiones de core hardcodeadas y contradictorias**
- `backend/src/controllers/HealthController.php:15`
- `/health` publica `'version' => '0.1.0'` mientras `PluginCompatibilityValidator::CORE_VERSION = '1.0.0'` es la versión contra la que se valida compatibilidad de plugins. Un operador que mire `/health` para diagnosticar un rechazo de plugin por versión verá un número que no participa en esa decisión.
- Sugerencia: constante única (p. ej. `Xestify\core\CoreVersion::VERSION`) consumida por ambos.

**15. STORY 1.5/1.6/1.7 con criterios ✅ pero sin implementación ni nota SUPERSEDED**
- `docs/11-backlog/backlog.md:256`
- Las stories 1.5 (tablas `roles`/`permissions`/`role_permissions`), 1.6 (`AuthorizationService::can()`) y 1.7 (`audit_logs`) llevan todos sus criterios con ✅ dentro del EPIC 1 (in-scope MVP), pero no existe nada de eso en el código. Su contenido se movió de facto a A5/A6 post-MVP (backlog.md:1647-1739), pero a diferencia de STORY 2.1 —que sí lleva "~~SUPERSEDED~~" y nota— aquí no hay marca, y el ✅ en criterios se usa también en stories post-MVP sin implementar (A5.1), volviendo el símbolo inservible como indicador de estado.
- Sugerencia: marcar 1.5-1.7 como superseded con puntero a A5/A6, y documentar qué significa ✅ en criterios (definición vs. implementación).

**16. Sección "Estado del MVP" de AGENTS.md congelada en Story 6.4 y contradicha por el código**
- `AGENTS.md:24`
- AGENTS.md ordena: "El corte oficial del MVP esta implementado hasta la Story 6.4 incluida. No implementar Story 6.5 ni PluginManager salvo peticion explicita". El repo tiene `PluginManagerController` con 16 rutas, y el backlog fija el corte en 9.7 con EPICs 10-11 implementados. Un agente que obedezca AGENTS.md literalmente rechazará trabajo legítimo sobre la mitad del sistema.
- Sugerencia: actualizar la sección al corte real (o sustituirla por una referencia a `docs/10-productivity/sesion.md`).

### NIT

**17. `buildPattern()` no escapa los segmentos literales y mantiene doble sintaxis**
- `backend/src/core/Router.php:111`
- El path se incrusta en la regex sin `preg_quote`: una ruta con metacaracteres se interpretaría como regex. Hoy inocuo, pero mina para rutas futuras (p. ej. de plugins). Además se soportan dos sintaxis (`{param}` y `:param`) cuando producción usa solo `{param}` — `:param` vive únicamente en RouterTest.
- Sugerencia: trocear por `/`, `preg_quote` de segmentos literales, y retirar una sintaxis.

**18. Instancia obsoleta retenida al re-registrar sobre un singleton**
- `backend/src/core/Container.php:41`
- `singleton()` guarda la instancia en `$instances[$id]` pero `register()` con el mismo id solo reemplaza el binding: la instancia vieja queda huérfana, y un tercer `singleton($id, ...)` la **resucita** en lugar de ejecutar la nueva factory. El ciclo singleton→register→singleton no está definido en ningún sitio.
- Sugerencia: `unset($this->instances[$id])` al principio de `register()` y `singleton()`.

**19. `PHP_AUTH_DIGEST` como fallback de Authorization**
- `backend/src/core/RequestFactory.php:12`
- `AUTHORIZATION_FALLBACK_KEYS` incluye `PHP_AUTH_DIGEST`, que en Apache contiene el digest de HTTP Digest — nunca un `Bearer ...` válido. Cargo-cult heredado; solo `REDIRECT_HTTP_AUTHORIZATION` tiene test que lo respalde.
- Sugerencia: dejar `REDIRECT_HTTP_AUTHORIZATION` (y `Authorization` si algún SAPI lo necesita) y borrar `PHP_AUTH_DIGEST`.

**20. Parser de .env y autoloader duplicados en 3+ sitios, sin soporte de comillas**
- `backend/src/bootstrap.php:6`
- El bloque parser de `.env` + autoloader (5-37) está copiado casi literal en `tools/setup/bootstrap.php` (7-35), en `AppWiringTest.php` y el parser además en ~6 tests de integración. Ninguna copia soporta valores entrecomillados ni comentarios inline: `JWT_SECRET="x#y"` guardaría las comillas y todo el sufijo. Si se corrige el parser habrá que cazarlo en 8 sitios.
- Sugerencia: extraer `Xestify\support\Env::load(string $file)` (y un `autoload.php` único), consumido por los dos bootstraps y los tests.

**21. `JWT_ALGORITHM`, `PLUGINS_PATH` y `APP_URL` son configuración muerta**
- `backend/.env.example:12`
- Ninguna de las tres se lee en producción (`JwtService` hardcodea HS256, config/app.php:348 hardcodea `dirname(BASE_PATH).'/plugins'`, `APP_URL` no aparece en `src/`). Cambiar `PLUGINS_PATH` en `.env` no tiene efecto.
- Sugerencia: eliminarlas del example o conectarlas de verdad (la más útil sería `PLUGINS_PATH`).

**22. `index()` de configuración devuelve claves que `show()`/`update()` rechazan, y estilo de envelope distinto al de users**
- `backend/src/controllers/ConfigurationController.php:29`
- `index()` lista todas las filas sin filtrar por `SUPPORTED_KEYS`, mientras `show`/`update` responden 404 para cualquier clave ≠ `ui-preferences`; el test lo consagra (ConfigurationControllerTest.php:74-86). Además `index` envuelve en `data.configurations` mientras `listUsers` devuelve el array directo en `data`.
- Sugerencia: decidir: o `index` filtra por whitelist, o la whitelist se elimina. Unificar el estilo de listado.

**23. `exp` opcional en decode y TTL sin validar**
- `backend/src/services/JwtService.php:73`
- `decode()` solo comprueba expiración si `isset($payload['exp'])`: un token firmado sin `exp` sería eterno (hoy inalcanzable porque `encode()` siempre lo añade, pero es la única defensa si el secreto se filtrase). Y un `JWT_EXPIRY` no numérico en `.env` se convierte en `(int) 0`: tokens que expiran al instante y `shouldRefresh` que nunca dispara.
- Sugerencia: rechazar en `decode()` payloads sin `exp` y validar `ttl > 0` en el constructor.

**24. El binding `Database::class` devuelve un `PDO`, y los mensajes de error mezclan idiomas**
- `backend/src/config/app.php:75`
- `$container->singleton(Database::class, fn() => Database::connection())` registra bajo el id `Database` un valor que es `PDO` — el id miente sobre el tipo. Aparte, los mensajes de cara al cliente mezclan inglés (`'Invalid credentials.'`, `'Missing authorization token.'`) y español (los `MSG_*` de User/ConfigurationController), y el frontend los muestra tal cual.
- Sugerencia: registrar el binding como `PDO::class` y fijar un idioma único para mensajes de API (el i18n real está en A1).

**25. Fixtures con slug `client`, prohibido por AGENTS.md**
- `backend/tests/unit/RouterTest.php:128`
- `dispatchCapture($router, 'GET', '/entities/client')` (:128, :140-142) y `RequestResponseTest.php:84-91` (`['slug' => 'client']`) reintroducen `client` como fixture, contra la convención explícita de AGENTS.md.
- Sugerencia: renombrar a `persons` en ambos tests (cambio mecánico, cero riesgo).

## Cobertura de tests

Cobertura notablemente buena y fiel al código actual. **Cubierto:** Container (register/singleton/sobrescritura), Router (4 métodos, params múltiples, trailing slash, alias `/xestify`, y el default fail-closed de rutas nuevas), RuntimePathNormalizer (incluye edge cases del refactor 01.12 de la auditoría anterior), Request/Response (envelope completo, headers case-insensitive, bearer, hasRole), RequestFactory, JwtService (roundtrip, padding, firma inválida, expiración, política completa de sliding refresh), AuthMiddleware, AuthController (éxito, 401, 422, anti-enumeración, usuarios borrados, seed users × APP_DEBUG), UserRepository (CRUD, normalizaciones, 23505→RepositoryException), UserController (perfil, `current_password`, protecciones seed en 5 flujos, 409, reset, destroy con self-delete), Configuration (con doble y con repositorio real), Database y AppWiring (401 sin token, JWT_SECRET obligatorio, hooks compartidos). **Huecos:** ningún test de `UserController::show()` (único endpoint de EPIC 8 sin cobertura); el camino feliz de cambio de email con `current_password` correcto solo a nivel unitario del authorizer; el 200-vacío del hallazgo 5; `X-Refreshed-Token` no verificado extremo a extremo; faltan el 422 de `value` no-array y el 404 de clave no soportada en ConfigurationController; AppWiring no ejercita rutas de users con token válido. **Desalineados:** ninguno afirma hechos falsos, pero varios *blindan* decisiones discutibles — ConfigurationControllerTest consagra que `index()` devuelva claves no soportadas, ContainerTest consagra el no-reinicio de singletons re-registrados, y los fixtures `client` contradicen la convención `persons`.

## Observaciones transversales

1. **Confianza total en las claims del JWT sin re-validación en BD.** `hasRole()`, la protección seed y el borrado de usuarios operan sobre un token que nunca se contrasta con la tabla `users` y que la sesión deslizante renueva indefinidamente. Raíz común del hallazgo 1 y del aplazamiento A6/A7.
2. **Parámetros opcionales "para tests" que son código muerto en producción.** El patrón `?Request $request = null` + fallback aparece en los tres controladores con tres implementaciones distintas; el Router siempre inyecta.
3. **Estrategia de excepciones heterogénea y sin red final.** ConfigurationController captura `Throwable` (y filtra el mensaje), UserController solo `RepositoryException` (con hueco en `updatePasswordIfNeeded`), AuthController nada, y no existe manejador global.
4. **Sanitización de datos sensibles en la frontera equivocada.** `password_hash` viaja por defecto en todas las lecturas del repositorio — el mismo diseño que ya produjo el crítico 01.01 de la auditoría anterior.
5. **Duplicación de infraestructura de arranque.** Parser de `.env` y autoloader copiados en 8 sitios.
6. **La documentación no acompañó a EPIC 8 ni al estado real del MVP.** `endpoints.md` sin users/configurations, stories 1.5-1.7 "fantasma" en el backlog y AGENTS.md congelado en la Story 6.4: síntomas del mismo fallo de proceso (el checklist de cierre de story cubre `sesion.md`/`backlog.md`/`README.md` pero no `docs/03-api/`).
