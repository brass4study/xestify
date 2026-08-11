# Auditoría — Core de infraestructura + Autenticación + Gestión de usuarios

**Subsistema:** Core / Auth / Users
**EPIC cubiertas:** EPIC 0 (preparación técnica), EPIC 1 (autenticación y seguridad), EPIC 8 (gestión de usuarios)
**Severidades:** 1 crítico · 5 mayor · 5 menor · 3 nit

Índice: [Auditoría consolidada](00-informe-consolidado.md)

---

## Hallazgos por severidad

### CRÍTICO

**1. Los hashes de contraseña (`password_hash`) se filtran en las respuestas JSON de la API**
- **Ficheros:** `backend/src/repositories/UserRepository.php:25,49,92` (los `SELECT`/`RETURNING` de `find()`, `all()` y `update()` incluyen siempre `password_hash`) + `backend/src/controllers/UserController.php:47` (`me()`), `:75` (`updateMe()`), `:87` (`listUsers()`), `:111` (`show()`), `:153` (`update()`), que hacen `Response::make()->json($profile/$updated/$user)` reenviando la fila completa sin filtrar campos.
- **Categoría:** Bug de correctitud / seguridad.
- **Por qué importa:** Cualquier usuario autenticado que llame a `GET /api/v1/users/me` recibe su propio hash bcrypt en el JSON; un admin que liste usuarios (`GET /api/v1/users`) recibe los hashes de **todos** los usuarios. Aunque bcrypt dificulta la inversión directa, exponer hashes de contraseña es una violación clásica de minimización de datos (CWE-200) y contradice el propio `docs/07-security/modelo-seguridad-local.md` del proyecto. Ningún test de la suite (`UserControllerTest.php`, `UserRepositoryTest.php`) comprueba la ausencia de este campo, así que el fallo pasa inadvertido.
- **Sugerencia:** Añadir un método `toPublicArray()`/DTO que excluya `password_hash` antes de responder, o quitar la columna del `SELECT` por defecto y añadir un método aparte solo para el flujo de login/verificación de contraseña.

### MAYOR

**2. No existe `AuthorizationService`; el control admin/no-admin está duplicado literalmente en 3 controladores**
- **Ficheros:** `backend/src/controllers/UserController.php:363-376` (`isAdmin`), `backend/src/controllers/ConfigurationController.php:103-112` (`isAdmin`), `backend/src/controllers/PluginManagerController.php:120-129` (`isAdminRequest`) — las tres implementaciones son código idéntico: `is_array($roles) && in_array('admin', $roles, true)`.
- **Categoría:** Redundancia / DRY / hallazgo específico solicitado.
- **Confirmación factual:** No existen las tablas `roles`, `permissions`, `role_permissions` ni ninguna clase `AuthorizationService` en el repo (grep global solo las encuentra referenciadas en `docs/11-backlog/backlog.md`). STORY 1.5/1.6 de `EPIC 1` (líneas 256-278) las planificaban, pero nunca se implementaron; el propio backlog las reformula después como `EPIC A6: Matriz de Permisos Fina (Adición post-MVP)` (líneas 1643-1670), lo que indica que fue una **simplificación consciente y documentada**, no un descuido silencioso. El control de acceso actual vive enteramente en la columna `roles JSONB` de `users` (migración `001_users.sql:9`, default `'["operador"]'`) y en checks `in_array('admin', ...)` inline.
- **Por qué importa:** Es una simplificación razonable para un TFM/MVP, pero el resultado técnico es deuda real: tres copias del mismo check de autorización, sin un único punto de verdad. Cualquier cambio futuro (p. ej. añadir un rol `supervisor` con permisos parciales) exige tocar 3 ficheros y arriesga que queden desincronizados.
- **Sugerencia:** Extraer la lógica a un método único, p. ej. `Request::hasRole(string $role): bool` en `core/Request.php`, o una clase `AuthorizationHelper::isAdmin(Request $r)`. No hace falta la matriz completa de permisos para el MVP, pero sí centralizar el check binario admin/no-admin.

**3. `AuthController` no usa `UserRepository`: reimplementa la consulta SQL a mano y accede a `Database` de forma estática**
- **Fichero:** `backend/src/controllers/AuthController.php:42-45`.
- **Categoría:** Duplicación / inconsistencia arquitectónica / refactor incompleto.
- **Descripción:** Todo el resto del sistema pasa dependencias por el `Container` (ver `config/app.php:212-215`, donde `AuthController` solo recibe `JwtService` y `RequestFactory`, **no** `UserRepository`). Dentro de `login()`, en cambio, se llama a `Database::connection()` directamente y se repite a mano el mismo `SELECT ... FROM users WHERE email = ...` que debería vivir en el repositorio (que no tiene ningún método `findByEmail()`). Esto rompe el patrón repositorio usado en el resto del código y crea dos SQL distintos para "buscar usuario" que pueden divergir con el tiempo.
- **Por qué importa:** Es la señal de "refactor perdido" más clara del subsistema: parece que `UserRepository` se introdujo en `EPIC 8` (STORY 8.1) sin revisar el `AuthController` de `EPIC 1` para hacerlo consistente.
- **Sugerencia:** Añadir `UserRepository::findByEmail(string $email): ?array` e inyectar `UserRepository` en `AuthController` en vez de `Database` estático.

**4. `UserRepository::update()` no traduce `PDOException` y no hay manejador de excepciones global**
- **Fichero:** `backend/src/repositories/UserRepository.php:73-116` (en concreto `execute()` bare en la línea 104), comparado con el helper privado `execute()` (líneas 145-152) que sí envuelve `find()`, `delete()` y `updatePassword()` en `try/catch (PDOException)` → `RepositoryException`.
- **Categoría:** Bug de correctitud / inconsistencia.
- **Descripción:** `update()` construye el `UPDATE ... RETURNING` a mano y llama `$stmt->execute();` sin pasar por el helper `execute()`. La tabla `users` tiene `CONSTRAINT users_email_unique UNIQUE (email)` (`001_users.sql:15`), así que un admin editando el email de un usuario a uno ya existente (`PUT /api/v1/users/{id}` o `PUT /api/v1/users/me`) dispara una violación de constraint. Confirmé por grep que **no existe ningún `set_exception_handler`, `set_error_handler` ni try/catch global** en `Router`/`bootstrap.php`/`app.php`; `ConfigurationController` sí envuelve sus llamadas en `catch (Throwable)` (líneas 41, 67, 98) pero `UserController` no lo hace en `update()`/`updateMe()`. El resultado es una excepción no capturada → error fatal de PHP sin envelope JSON limpio, en vez de un 409/422 controlado.
- **Sugerencia:** Envolver el `execute()` de `update()` con el mismo helper privado, y capturar `RepositoryException`/`PDOException` en el controlador para devolver 422/409 con mensaje "email ya en uso".

**5. `UserSeeder::seedIfEmpty()` documentado como automático en el arranque, pero nunca se invoca ahí**
- **Fichero:** `backend/src/database/seeders/UserSeeder.php:12` (docblock: *"Auto-runs on server boot only when the table is empty"*).
- **Categoría:** Comentario obsoleto / señal de refactor incompleto.
- **Verificación:** Grep en todo el repo confirma que `UserSeeder::seedIfEmpty()` solo se llama desde `tools/setup/seed-admin-user.php` (script CLI manual) y desde los tests de integración. `backend/src/bootstrap.php` y `backend/src/app.php` (que sí son el arranque real, vía `backend/public/index.php:7`) no lo invocan en ningún punto.
- **Por qué importa:** El comentario induce a error a quien lea el código (incluido un tribunal de TFM): sugiere que el sistema se auto-provisiona con un admin al primer arranque, cuando en realidad requiere un paso manual explícito (`php tools/setup/seed-admin-user.php`). Es exactamente el patrón "nombre/comentario que no casa con la arquitectura actual".
- **Sugerencia:** Corregir el docblock para reflejar el flujo real, o (si se prefiere el comportamiento automático) engancharlo efectivamente en `app.php`.

**6. `JwtService::base64UrlDecode()` calcula mal el padding — funciona "por suerte", no por diseño**
- **Fichero:** `backend/src/services/JwtService.php:92-95`, concretamente la línea `str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT)`.
- **Categoría:** Bug de correctitud (latente).
- **Descripción:** El segundo argumento de `str_pad()` es la **longitud total objetivo**, no la cantidad de padding a añadir. El código pasa `strlen($data) % 4` (un valor entre 0 y 3), que es casi siempre **menor** que la longitud real de `$data` (decenas de caracteres en un JWT real). Como el "objetivo" es más corto que el string actual, `str_pad()` no añade nada — la variable `$padded` sale idéntica a la entrada, sin ningún `=` de relleno. Lo verifiqué ejecutando la función aislada: para un payload típico de 110 caracteres, `target_len` calculado es 2 y no se añade ningún carácter de padding. El roundtrip `encode()`/`decode()` sigue funcionando porque `base64_decode()` de PHP es tolerante con base64 sin padding en modo no estricto — pero es una coincidencia de implementación de PHP, no una garantía del código.
- **Por qué importa:** Es un bug de lógica real (el cálculo de padding está invertido/incorrecto), enmascarado únicamente por la laxitud de `base64_decode()`. Es frágil: cualquier cambio de librería, de modo estricto, o de lenguaje en una futura migración rompería el decode. Para una defensa de TFM es un buen ejemplo de "código que pasa los tests pero está lógicamente mal".
- **Sugerencia:** Corregir con el idioma estándar: `$remainder = strlen($data) % 4; if ($remainder) { $data .= str_repeat('=', 4 - $remainder); }`.

### MENOR

**7. Lista de prefijos protegidos hardcodeada en el Router, desincronizada manualmente de `routes.php`**
- **Fichero:** `backend/src/core/Router.php:20-21` (`protectedPrefixes`) y `157-166` (`requiresAuth`).
- **Categoría:** Acoplamiento / mantenibilidad.
- **Descripción:** La protección de rutas no se declara junto a cada ruta en `config/routes.php`, sino en un array separado de prefijos en el Router. Si en el futuro se añade una familia de rutas nueva y se olvida añadir su prefijo aquí, quedará **sin protección** silenciosamente (fallo abierto).
- **Sugerencia:** Declarar la protección por ruta (p. ej. tercer parámetro `protected: true` en `$router->get(...)`) en vez de una lista paralela.

**8. `UserController::destroy()` usa una bandera `$shouldStop` en vez de retornos tempranos**
- **Fichero:** `backend/src/controllers/UserController.php:186-224`.
- **Categoría:** Complejidad innecesaria.
- **Descripción:** Encadena tres `if (!$shouldStop && ...)` con una variable de control, cuando el mismo comportamiento se logra con `return` directos tras cada `Response::make()->...`. Es sobre-ingeniería local, sin impacto funcional, pero dificulta la lectura.

**9. Normalización de `avatar` binario duplicada 3 veces en `UserRepository`**
- **Fichero:** `backend/src/repositories/UserRepository.php:36-38` (`find`), `60-64` (`all`), `111-113` (`update`) — el patrón `if (array_key_exists('avatar', $row)) { $row['avatar'] = $this->normalizeBinaryValue($row['avatar']); }` se repite igual en los tres métodos.
- **Categoría:** Duplicación menor.
- **Sugerencia:** Extraer a un `normalizeRow(array $row): array` privado y llamarlo una vez por punto de salida.

**10. Desfase entre documentación y comportamiento real (varios puntos)**
- `docs/03-api/autenticacion.md:5,25` documenta la respuesta de login como `{ data: { accessToken } }` (camelCase), pero `AuthController.php:60-63` devuelve `access_token` (snake_case) más un campo `email` no documentado.
- `backlog.md` STORY 1.3 (línea 237) promete `access_token + refresh_token`; no existe ningún mecanismo de refresh token en el código — solo expiración fija vía `JWT_EXPIRY`.
- `backlog.md` STORY 8.2 (línea 914) documenta `PUT /api/v1/users/me/password` como endpoint independiente; en la implementación real el cambio de contraseña propia va embebido en `PUT /api/v1/users/me` (`UserController::updateMe`), sin ruta separada.
- **Por qué importa:** Ninguno es un bug de código, pero en una defensa de TFM un tribunal que compare `docs/` contra el código puede detectar estas discrepancias; es preferible dejarlas resueltas o anotadas como decisión de diseño.

**11. No existe endpoint de creación de usuarios (`POST /api/v1/users`)**
- **Fichero:** `backend/src/config/routes.php:30-36` (rutas de usuario) — no hay ningún `$router->post('/api/v1/users', ...)`.
- **Categoría:** Hueco funcional (posiblemente intencional).
- **Descripción:** El único usuario que existe al arrancar es el admin sembrado (`UserSeeder`); la gestión de usuarios permite editar, resetear contraseña y borrar, pero no dar de alta. Esto coincide exactamente con el alcance literal de STORY 8.2 del backlog (que no lista una ruta de creación), así que probablemente es intencional — pero vale la pena que quede explícito en la memoria del TFM como decisión de alcance, no como olvido.

### NIT

**12. `RuntimePathNormalizer` es más elaborado de lo que su lógica requiere**
- **Fichero:** `backend/src/core/RuntimePathNormalizer.php` (fichero completo, ~60 líneas).
- **Categoría:** Complejidad ligeramente por encima de lo necesario.
- Divide la lógica en `isDirectRuntimePath`/`extractApiPath`/`extractHealthPath` para resolver, en esencia, "quita cualquier prefijo de alias antes de `/api` o `/health`". Está bien testeado (`RuntimePathNormalizerTest.php`) y es correcto, así que no es un problema real — solo algo más fragmentado de lo que ameritaría su tamaño.

**13. Secreto JWT por defecto `'changeme'` si falta la variable de entorno**
- **Fichero:** `backend/src/config/app.php:64`.
- Mitigado porque `.env.example:11` documenta claramente que debe configurarse (`JWT_SECRET=change_me_random_secret_min_32_chars`), pero el fallback en código es "fail-open" a un secreto adivinable en vez de fallar de forma ruidosa. Aceptable para un entorno de desarrollo/TFM.

**14. Supresiones `// NOSONAR S5992` para instanciación/invocación dinámica**
- **Fichero:** `backend/src/core/Router.php:181, 196, 201`.
- Uso de `new $target()` e `$instance->$method(...)` con nombres de clase/método que provienen únicamente de las rutas registradas por el propio código (no de input de usuario), por lo que el riesgo real es bajo — pero es el tipo de patrón que un revisor de seguridad marcaría a primera vista; la supresión explícita con justificación ya mitiga la señal de alarma.

---

## Resumen de salud del subsistema

El núcleo de infraestructura (`Container`, `Router`, `Request`/`Response`, `RequestFactory`, `Database`) está limpio, bien tipado, con responsabilidades claras y buena cobertura de tests unitarios — es la parte más sólida del subsistema y demuestra buen entendimiento de DI e inyección explícita sin sobreingeniería. `JwtService` y `AuthMiddleware` son correctos y están bien testeados, salvo el bug latente de padding en `base64UrlDecode` (hallazgo 6), enmascarado por la tolerancia de PHP. El punto más débil es la capa de autorización y el flujo de login/gestión de usuarios: no existe el `AuthorizationService`/tablas de permisos previstas en STORY 1.5-1.6 del backlog —una simplificación consciente y documentada más tarde como `EPIC A6` post-MVP, lo cual es defendible—, pero su consecuencia técnica sí es un problema real: el check `is_admin` está copiado literalmente en tres controladores, `AuthController` rompe el patrón repositorio usado en el resto del sistema, y sobre todo, los endpoints de usuario filtran `password_hash` en las respuestas JSON, que es el hallazgo más serio de esta auditoría y debería corregirse antes de cualquier entrega o demo pública. El manejo de errores es inconsistente entre repositorios/controladores (algunos capturan `Throwable`, `UserRepository::update()` no), y no hay un manejador de excepciones global, lo que deja rutas sin cubrir ante fallos de base de datos (violación de constraint de email único). En conjunto, el código muestra una arquitectura consistente y deliberada en su núcleo, con la deuda técnica esperable de un proyecto construido incrementalmente por EPICs con asistencia de IA, concentrada sobre todo en el módulo de autenticación/usuarios más que en la infraestructura base.

## Nota de cobertura de tests

La cobertura unitaria de la capa core es notablemente buena para un proyecto sin framework: `ContainerTest`, `RouterTest`, `RequestResponseTest`, `RequestFactoryTest`, `RuntimePathNormalizerTest`, `JwtServiceTest` y `AuthMiddlewareTest` cubren casos felices y de borde reales (tokens expirados/manipulados/con firma incorrecta, rutas dinámicas, prefijos de alias, envelopes de error, singleton vs. transient). La integración (`AuthControllerTest`, `UserControllerTest`, `UserRepositoryTest`, `DatabaseTest`, `AppWiringTest`) también es sustancial y no superficial: prueba autorización (admin vs. no-admin, 403), auto-eliminación bloqueada, prevención de enumeración de usuarios en login (mismo mensaje de error para email inexistente vs. password incorrecta — buena práctica, explícitamente testeada), soft-delete respetado en login y en lecturas, y wiring real del contenedor + router + middleware en `AppWiringTest`. Los tests de integración requieren PostgreSQL vivo y se auto-saltan (`[SKIP]`, exit 0) si no lo encuentran — razonable para portabilidad, pero implica que una suite "en verde" podría en realidad haber ejecutado cero aserciones si la base de datos no está configurada en el entorno de CI/evaluación. El framework de test es una mini-implementación propia (`tests/unit/helpers.php`, sin PHPUnit), coherente con la filosofía "zero dependencias" del backend, aunque sin mocking ni métricas de cobertura cuantificadas. La brecha más relevante: **ningún test verifica la ausencia de `password_hash` en las respuestas** (por eso el hallazgo crítico #1 pasó inadvertido), y tampoco hay un test que ejercite el conflicto de email duplicado en `UserRepository::update()` (hallazgo #4), ni tests dedicados a `RepositoryException` en rutas "not found" de `update()`/`delete()`/`updatePassword()` sobre IDs inexistentes.
