# Prompts Efectivos ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â Xestify

> Registro de los prompts que mejor funcionaron durante el desarrollo.
> ÃƒÆ’Ã†â€™Ãƒâ€¦Ã‚Â¡til para reutilizar y para el anÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lisis acadÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©mico.

---

## Formato

```
## [STORY] ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â [Objetivo del prompt]
**Prompt:** texto exacto
**Resultado:** quÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â© generÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³ / cÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³mo fue de ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºtil
**Iteraciones:** cuÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡ntas veces se refinÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³
```

---

## EPIC 0 ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â PreparaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n TÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©cnica

### STORY 0.1 ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â Retomar sesiÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n
**Prompt:**
```
Lee docs/ia/sesion.md y retoma el desarrollo de Xestify donde lo dejamos.
```
**Resultado:** Contexto completo recuperado, continuaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n inmediata  
**Iteraciones:** 1

---

### STORY 0.2 ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â Container DI
**Prompt:**
```
Implementa Xestify\Core\Container en PHP 8.1 nativo sin dependencias externas.
Necesito register(id, callable), singleton(id, callable), get(id), has(id).
- register() crea nueva instancia en cada get()
- singleton() crea la instancia solo la primera vez
- El factory recibe el Container como argumento para inyecciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n entre servicios
- get() lanza InvalidArgumentException si no estÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡ registrado
DespuÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©s genera un test standalone PHP (sin PHPUnit) con al menos 6 casos.
```
**Resultado:** ImplementaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n completa + 8 tests al primer intento  
**Iteraciones:** 1

---

### STORY 0.3 ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â Router HTTP
**Prompt:**
```
Implementa Xestify\Core\Router en PHP 8.1. Recibe un Container en el constructor.
MÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©todos: get(), post(), put(), delete(path, handler).
- Rutas dinÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡micas con :param ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ named capture groups en regex
- dispatch(method, uri): retorna true si hay match, null si no
- run(): despacha la peticiÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n real, responde 404 JSON si no hay ruta
- Handler puede ser callable o [Controller::class, 'method']
- Si es array, resuelve el controller via Container->get() si estÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡ registrado, sino new
DespuÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©s genera test standalone con trailing slash, params mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºltiples, mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©todo incorrecto.
```
**Resultado:** ImplementaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n + 10 tests al primer intento  
**Iteraciones:** 1

---

### STORY 0.4 ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â Request/Response
**Prompt:**
```
Implementa Xestify\Core\Request y Xestify\Core\Response en PHP 8.1.

Request:
- Constructor con query[], body[], headers[], routeParams[]
- fromGlobals(routeParams): construye desde $_GET, php://input JSON, $_SERVER
- query(key, default), body(key, default), header(key) case-insensitive
- param(key), bearerToken() ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ extrae "Bearer <token>" del header Authorization

Response:
- Envelope estÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡ndar: { ok:true, data, meta? } y { ok:false, error:{code,message,details?} }
- json(data, meta=[]), error(code, message, details=[])
- Shortcuts: notFound(), unauthorized(), forbidden(), unprocessable(), serverError()

Genera test standalone con 20 casos.
```
**Resultado:** ImplementaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n completa, fix necesario en `send()` por headers CLI  
**Iteraciones:** 2 (fix `PHP_SAPI !== 'cli'`)

**LecciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n:** Cuando Response emite `header()` en CLI, contamina STDOUT. SoluciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n: guardar el check `PHP_SAPI` en `send()`.

---

---

## EPIC 1 ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â AutenticaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n

### STORY 1.1 ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â Database singleton + migraciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n + seeder
**Prompt:**
```
Implementa STORY 1.1 del EPIC 1. Necesito:
- backend/database/migrations/001_users.sql ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â idempotente, UUID pk, email unique, roles JSONB
- Xestify\Core\Database ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â PDO singleton, lee DB_* de $_ENV, lanza DatabaseException
- Xestify\Database\Seeders\UserSeeder::seedIfEmpty() ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â inserta admin si COUNT=0
- Test de integraciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n standalone (skip graceful si no hay PostgreSQL)
Sin Composer, PHP 8.1 nativo, PDO puro, prepared statements obligatorios.
```
**Resultado:** Todos los archivos generados al primer intento. El seeder detectÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³ automÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡ticamente que debÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­a vivir en `src/` para que el autoloader lo cubriera.
**Iteraciones:** 1  
**LecciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n:** Los tests de integraciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n que requieren BD real deben tener skip graceful (`exit(0)` si la conexiÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n falla) para no romper el pipeline en CI sin base de datos.

---

### STORY 1.2 ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â JwtService HS256 puro PHP
**Prompt:**
```
Implementa Xestify\Services\JwtService en PHP 8.1 sin dependencias externas.
- encode(array $payload): string ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â aÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â±ade iat y exp automÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡ticamente
- decode(string $token): array ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â lanza AuthException si firma invÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lida o expirado
- HS256: Base64URL encode header.payload, HMAC-SHA256 con $_ENV['JWT_SECRET']
- Usa hash_equals para comparaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n timing-safe
Crea tambiÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©n Xestify\Exceptions\AuthException (dominio, extiende RuntimeException).
Genera test standalone con: roundtrip, iat/exp, firma incorrecta, token adulterado,
malformado (1 y 2 segmentos), expirado.
```
**Resultado:** 8 tests, 0 fallos al primer intento. ImplementaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n 100% pura PHP, cero dependencias.  
**Iteraciones:** 1

---

### STORY 1.3 ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â AuthController (POST /api/auth/login)
**Prompt:**
```
Implementa Xestify\Controllers\AuthController con mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©todo login(array $params): void.
- POST /api/auth/login, body JSON: { email, password }
- 422 si email o password vacÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­os
- SELECT users WHERE email=? con prepared statement
- password_verify() ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ 401 si no coincide
- JWT via JwtService::encode(), respuesta { ok:true, data:{ access_token } }
Registra AuthController en config/app.php (recibe JwtService del container).
AÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â±ade la ruta en config/routes.php.
```
**Resultado:** Controller, registro en container y ruta generados en un solo paso.  
**Iteraciones:** 1  
**LecciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n:** Especificar el cÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³digo de error por caso (422 vs 401) evita que IA decida por su cuenta.

---

### STORY 1.4 ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â AuthMiddleware + Request::user
**Prompt:**
```
Implementa Xestify\Middleware\AuthMiddleware con handle(Request $request, callable $next): void.
- Extrae bearerToken() del request
- Llama JwtService::decode(), captura AuthException ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ Response::make()->unauthorized()
- Si vÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lido: $request->setUser($payload), llama $next($request)
AÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â±ade a Xestify\Core\Request:
- private ?array $user = null
- setUser(array $payload): void
- user(): ?array
Genera test standalone con 6 casos: token vÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lido, payload propagado, sin token,
expirado, firma incorrecta, malformado.
```
**Resultado:** 6 tests, 0 fallos al primer intento.  
**Iteraciones:** 1

---

## Plantilla para prÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³ximas stories

```
## STORY X.X ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â [Nombre]
**Prompt:**
```
[Texto del prompt]
```
**Resultado:** 
**Iteraciones:** 
**LecciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n:** (si hubo algo inesperado)
```

---

## Patrones que funcionan bien

1. **Especificar la API pÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºblica** antes de pedir implementaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â evita que IA diseÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â±e su propia interfaz
2. **Pedir tests en el mismo prompt** ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â IA genera tests coherentes con la implementaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n
3. **Mencionar constraints** (sin Composer, PHP 8.1, standalone) ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â evita soluciones con dependencias
4. **"Genera test standalone con N casos"** ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â produce suites completas con edge cases

## Patrones que no funcionaron

- Pedir "implementa el sistema de auth" sin detallar la API ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ demasiado genÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©rico
- No mencionar "sin PHPUnit" ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ generÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³ tests con dependencia de Composer
- No pedir tests de integraciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n para controllers desde el primer prompt ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ se aÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â±adieron en iteraciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n posterior
- No pedir `?Request $request = null` en el constructor del controller desde el inicio ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ obligÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³ a refactor para testabilidad

---

## EPIC 2 ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â Modelo de Datos Core

### STORY 2.1 ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â Tabla system_entities
**Prompt:**
```
implementa STORY 2.1 y 2.4
[Con plan previo detallado en contexto: SQL idempotente IF NOT EXISTS, campos id/slug/name/source_plugin_slug/is_active/created_at/updated_at, UNIQUE en slug, test con connectivity probe y skip graceful]
```
**Resultado:** `002_core.sql` + `SystemEntitiesTableTest.php` generados, 3 tests pasando al primer intento
**Iteraciones:** 1

---

### STORY 2.4 ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â Tabla plugins_registry
**Prompt:** (mismo turno que 2.1)
```
implementa STORY 2.1 y 2.4
[Con plan previo: SQL con CHECK plugin_type IN ('entity','extension'), CHECK status IN ('active','inactive','error'), UNIQUE plugin_slug, 5 tests incluyendo verificaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n de constraints]
```
**Resultado:** `002_core.sql` ampliado + `PluginsRegistryTableTest.php` con 5 tests, pasando al primer intento
**Iteraciones:** 1
**LecciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n:** AÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â±adir CHECK constraints directamente en SQL (no en PHP) hace que los tests de constraint sean mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡s simples ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â se verifica contra `information_schema.check_constraints`.

### STORY 2.2 ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Tabla entity_metadata
**Prompt:** (turno "Si" ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â continuaciÃƒÆ’Ã‚Â³n secuencial de EPIC 2)
`
STORY 2.2 ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Crear tabla entity_metadata con:
- id UUID PK, entity_slug VARCHAR(100), schema_version INT DEFAULT 1, schema_json JSONB NOT NULL, created_at TIMESTAMPTZ
- CHECK constraint: schema_json ? 'fields' (objeto con clave fields obligatoria)
- ÃƒÆ’Ã‚Ândice compuesto (entity_slug, schema_version)
- Test de integraciÃƒÆ’Ã‚Â³n: table exists, expected columns, ÃƒÆ’Ã‚Â­ndice, CHECK constraint rechaza schema_json sin fields
`
**Resultado:** SQL + test 4/4 en iteraciones ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â 1 ajuste en test de constraint para verificar causa exacta del error
**Iteraciones:** 2
**LecciÃƒÆ’Ã‚Â³n:** Al testear CHECK constraints de PostgreSQL, verificar que el PDOException incluye el nombre de la constraint en su mensaje para distinguir el fallo correcto de otro error inesperado.

### STORY 2.3 Ã¢â‚¬â€ Tabla entity_data
**Prompt:** (turno "Si" Ã¢â‚¬â€ continuaciÃƒÂ³n secuencial de EPIC 2)
```
STORY 2.3 Ã¢â‚¬â€ Crear tabla entity_data con:
- id UUID PK, entity_slug VARCHAR(100), owner_id UUID NULL, content JSONB DEFAULT '{}', created_at, updated_at, deleted_at
- ÃƒÂndices: BTREE(entity_slug), BTREE(owner_id), GIN(content)
- Soft delete via deleted_at NULL
- 5 tests: table exists, 7 columns, deleted_at nullable, GIN index, BTREE slug index
```
**Resultado:** SQL + test 5/5 en la primera iteraciÃƒÂ³n
**Iteraciones:** 1
**LecciÃƒÂ³n:** GIN index es esencial para queries JSONB (@>, ?, etc.); declarar `owner_id` como NULL permite registros sin propietario explÃƒÂ­cito.


### STORY 2.5 â€” Tabla plugin_hook_registry
**Prompt:** (turno "Si" â€” continuaciÃ³n secuencial de EPIC 2)
```
STORY 2.5 â€” Crear tabla plugin_hook_registry con:
- id UUID PK, plugin_slug VARCHAR(100), target_entity_slug VARCHAR(100), hook_name VARCHAR(50), priority INT DEFAULT 10, enabled BOOL DEFAULT true
- Ãndice compuesto (target_entity_slug, hook_name)
- Sin FK a plugins_registry (desacoplamiento intencional)
- 5 tests: table exists, 6 columns, priority default 10, enabled default true, composite index
```
**Resultado:** SQL + test 5/5 en la primera iteraciÃ³n
**Iteraciones:** 1
**LecciÃ³n:** Omitir FK a plugins_registry es una decisiÃ³n deliberada â€” permite registrar hooks de plugins que aÃºn no estÃ¡n instalados, lo que facilita el bootstrap del sistema.


### STORY 2.6 — GenericRepository (CRUD JSONB)
**Prompt:** (turno "Si" — continuación secuencial de EPIC 2)
```
STORY 2.6 — Crear Xestify\Repositories\GenericRepository con:
- Métodos: find(id), all(slug, includeDeleted), create(slug, content, ownerId), update(id, content), delete(id), restore(id)
- Operaciones en entity_data con JSONB
- Update usa merge JSONB (operador ||) no reemplazo
- Soft delete via deleted_at
- Parámetros preparados PDO — nunca interpolación
- RepositoryException separada de DatabaseException
- 7 tests de integración con cleanup por test
```
**Resultado:** RepositoryException + GenericRepository + test 7/7 en la primera iteración
**Iteraciones:** 1
**Lección:** El operador JSONB `||` en PostgreSQL hace merge de objetos (shallow), ideal para update parcial sin sobrescribir campos no enviados.

## Lecciones acumuladas

- **Testabilidad desde el diseÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â±o:** siempre pedir `?Dependency $dep = null` o inyecciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n de constructor en controllers para que los tests no dependan de globals
- **BOM UTF-8 en Windows:** VS Code puede guardar con BOM; PHP rompe con `strict_types` si hay bytes antes de `<?php`. SoluciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n: `[System.IO.File]::WriteAllText(..., UTF8Encoding(false))`
- **Extensiones PHP:** `pdo_pgsql` viene comentada por defecto en instalaciones manuales de PHP en Windows ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â verificar antes de asumir que PostgreSQL es accesible
- **Tests de integraciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n para controllers:** usar `ob_start()/ob_get_clean()` para capturar el output de `Response::make()->json()` y decodificarlo como array
