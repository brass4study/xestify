# Prompts Efectivos Ã¢â‚¬â€ Xestify

> Registro de los prompts que mejor funcionaron durante el desarrollo.
> ÃƒÅ¡til para reutilizar y para el anÃƒÂ¡lisis acadÃƒÂ©mico.

---

## Formato

```
## [STORY] Ã¢â‚¬â€ [Objetivo del prompt]
**Prompt:** texto exacto
**Resultado:** quÃƒÂ© generÃƒÂ³ / cÃƒÂ³mo fue de ÃƒÂºtil
**Iteraciones:** cuÃƒÂ¡ntas veces se refinÃƒÂ³
```

---

## EPIC 0 Ã¢â‚¬â€ PreparaciÃƒÂ³n TÃƒÂ©cnica

### STORY 0.1 Ã¢â‚¬â€ Retomar sesiÃƒÂ³n
**Prompt:**
```
Lee docs/ia/sesion.md y retoma el desarrollo de Xestify donde lo dejamos.
```
**Resultado:** Contexto completo recuperado, continuaciÃƒÂ³n inmediata  
**Iteraciones:** 1

---

### STORY 0.2 Ã¢â‚¬â€ Container DI
**Prompt:**
```
Implementa Xestify\Core\Container en PHP 8.1 nativo sin dependencias externas.
Necesito register(id, callable), singleton(id, callable), get(id), has(id).
- register() crea nueva instancia en cada get()
- singleton() crea la instancia solo la primera vez
- El factory recibe el Container como argumento para inyecciÃƒÂ³n entre servicios
- get() lanza InvalidArgumentException si no estÃƒÂ¡ registrado
DespuÃƒÂ©s genera un test standalone PHP (sin PHPUnit) con al menos 6 casos.
```
**Resultado:** ImplementaciÃƒÂ³n completa + 8 tests al primer intento  
**Iteraciones:** 1

---

### STORY 0.3 Ã¢â‚¬â€ Router HTTP
**Prompt:**
```
Implementa Xestify\Core\Router en PHP 8.1. Recibe un Container en el constructor.
MÃƒÂ©todos: get(), post(), put(), delete(path, handler).
- Rutas dinÃƒÂ¡micas con :param Ã¢â€ â€™ named capture groups en regex
- dispatch(method, uri): retorna true si hay match, null si no
- run(): despacha la peticiÃƒÂ³n real, responde 404 JSON si no hay ruta
- Handler puede ser callable o [Controller::class, 'method']
- Si es array, resuelve el controller via Container->get() si estÃƒÂ¡ registrado, sino new
DespuÃƒÂ©s genera test standalone con trailing slash, params mÃƒÂºltiples, mÃƒÂ©todo incorrecto.
```
**Resultado:** ImplementaciÃƒÂ³n + 10 tests al primer intento  
**Iteraciones:** 1

---

### STORY 0.4 Ã¢â‚¬â€ Request/Response
**Prompt:**
```
Implementa Xestify\Core\Request y Xestify\Core\Response en PHP 8.1.

Request:
- Constructor con query[], body[], headers[], routeParams[]
- fromGlobals(routeParams): construye desde $_GET, php://input JSON, $_SERVER
- query(key, default), body(key, default), header(key) case-insensitive
- param(key), bearerToken() Ã¢â€ â€™ extrae "Bearer <token>" del header Authorization

Response:
- Envelope estÃƒÂ¡ndar: { ok:true, data, meta? } y { ok:false, error:{code,message,details?} }
- json(data, meta=[]), error(code, message, details=[])
- Shortcuts: notFound(), unauthorized(), forbidden(), unprocessable(), serverError()

Genera test standalone con 20 casos.
```
**Resultado:** ImplementaciÃƒÂ³n completa, fix necesario en `send()` por headers CLI  
**Iteraciones:** 2 (fix `PHP_SAPI !== 'cli'`)

**LecciÃƒÂ³n:** Cuando Response emite `header()` en CLI, contamina STDOUT. SoluciÃƒÂ³n: guardar el check `PHP_SAPI` en `send()`.

---

---

## EPIC 1 Ã¢â‚¬â€ AutenticaciÃƒÂ³n

### STORY 1.1 Ã¢â‚¬â€ Database singleton + migraciÃƒÂ³n + seeder
**Prompt:**
```
Implementa STORY 1.1 del EPIC 1. Necesito:
- backend/database/migrations/001_users.sql Ã¢â‚¬â€ idempotente, UUID pk, email unique, roles JSONB
- Xestify\Core\Database Ã¢â‚¬â€ PDO singleton, lee DB_* de $_ENV, lanza DatabaseException
- Xestify\Database\Seeders\UserSeeder::seedIfEmpty() Ã¢â‚¬â€ inserta admin si COUNT=0
- Test de integraciÃƒÂ³n standalone (skip graceful si no hay PostgreSQL)
Sin Composer, PHP 8.1 nativo, PDO puro, prepared statements obligatorios.
```
**Resultado:** Todos los archivos generados al primer intento. El seeder detectÃƒÂ³ automÃƒÂ¡ticamente que debÃƒÂ­a vivir en `src/` para que el autoloader lo cubriera.
**Iteraciones:** 1  
**LecciÃƒÂ³n:** Los tests de integraciÃƒÂ³n que requieren BD real deben tener skip graceful (`exit(0)` si la conexiÃƒÂ³n falla) para no romper el pipeline en CI sin base de datos.

---

### STORY 1.2 Ã¢â‚¬â€ JwtService HS256 puro PHP
**Prompt:**
```
Implementa Xestify\Services\JwtService en PHP 8.1 sin dependencias externas.
- encode(array $payload): string Ã¢â‚¬â€ aÃƒÂ±ade iat y exp automÃƒÂ¡ticamente
- decode(string $token): array Ã¢â‚¬â€ lanza AuthException si firma invÃƒÂ¡lida o expirado
- HS256: Base64URL encode header.payload, HMAC-SHA256 con $_ENV['JWT_SECRET']
- Usa hash_equals para comparaciÃƒÂ³n timing-safe
Crea tambiÃƒÂ©n Xestify\Exceptions\AuthException (dominio, extiende RuntimeException).
Genera test standalone con: roundtrip, iat/exp, firma incorrecta, token adulterado,
malformado (1 y 2 segmentos), expirado.
```
**Resultado:** 8 tests, 0 fallos al primer intento. ImplementaciÃƒÂ³n 100% pura PHP, cero dependencias.  
**Iteraciones:** 1

---

### STORY 1.3 Ã¢â‚¬â€ AuthController (POST /api/auth/login)
**Prompt:**
```
Implementa Xestify\Controllers\AuthController con mÃƒÂ©todo login(array $params): void.
- POST /api/auth/login, body JSON: { email, password }
- 422 si email o password vacÃƒÂ­os
- SELECT users WHERE email=? con prepared statement
- password_verify() Ã¢â€ â€™ 401 si no coincide
- JWT via JwtService::encode(), respuesta { ok:true, data:{ access_token } }
Registra AuthController en config/app.php (recibe JwtService del container).
AÃƒÂ±ade la ruta en config/routes.php.
```
**Resultado:** Controller, registro en container y ruta generados en un solo paso.  
**Iteraciones:** 1  
**LecciÃƒÂ³n:** Especificar el cÃƒÂ³digo de error por caso (422 vs 401) evita que IA decida por su cuenta.

---

### STORY 1.4 Ã¢â‚¬â€ AuthMiddleware + Request::user
**Prompt:**
```
Implementa Xestify\Middleware\AuthMiddleware con handle(Request $request, callable $next): void.
- Extrae bearerToken() del request
- Llama JwtService::decode(), captura AuthException Ã¢â€ â€™ Response::make()->unauthorized()
- Si vÃƒÂ¡lido: $request->setUser($payload), llama $next($request)
AÃƒÂ±ade a Xestify\Core\Request:
- private ?array $user = null
- setUser(array $payload): void
- user(): ?array
Genera test standalone con 6 casos: token vÃƒÂ¡lido, payload propagado, sin token,
expirado, firma incorrecta, malformado.
```
**Resultado:** 6 tests, 0 fallos al primer intento.  
**Iteraciones:** 1

---

## Plantilla para prÃƒÂ³ximas stories

```
## STORY X.X Ã¢â‚¬â€ [Nombre]
**Prompt:**
```
[Texto del prompt]
```
**Resultado:** 
**Iteraciones:** 
**LecciÃƒÂ³n:** (si hubo algo inesperado)
```

---

## Patrones que funcionan bien

1. **Especificar la API pÃƒÂºblica** antes de pedir implementaciÃƒÂ³n Ã¢â‚¬â€ evita que IA diseÃƒÂ±e su propia interfaz
2. **Pedir tests en el mismo prompt** Ã¢â‚¬â€ IA genera tests coherentes con la implementaciÃƒÂ³n
3. **Mencionar constraints** (sin Composer, PHP 8.1, standalone) Ã¢â‚¬â€ evita soluciones con dependencias
4. **"Genera test standalone con N casos"** Ã¢â‚¬â€ produce suites completas con edge cases

## Patrones que no funcionaron

- Pedir "implementa el sistema de auth" sin detallar la API Ã¢â€ â€™ demasiado genÃƒÂ©rico
- No mencionar "sin PHPUnit" Ã¢â€ â€™ generÃƒÂ³ tests con dependencia de Composer
- No pedir tests de integraciÃƒÂ³n para controllers desde el primer prompt Ã¢â€ â€™ se aÃƒÂ±adieron en iteraciÃƒÂ³n posterior
- No pedir `?Request $request = null` en el constructor del controller desde el inicio Ã¢â€ â€™ obligÃƒÂ³ a refactor para testabilidad

---

## EPIC 2 Ã¢â‚¬â€ Modelo de Datos Core

### STORY 2.1 Ã¢â‚¬â€ Tabla system_entities
**Prompt:**
```
implementa STORY 2.1 y 2.4
[Con plan previo detallado en contexto: SQL idempotente IF NOT EXISTS, campos id/slug/name/source_plugin_slug/is_active/created_at/updated_at, UNIQUE en slug, test con connectivity probe y skip graceful]
```
**Resultado:** `002_core.sql` + `SystemEntitiesTableTest.php` generados, 3 tests pasando al primer intento
**Iteraciones:** 1

---

### STORY 2.4 Ã¢â‚¬â€ Tabla plugins_registry
**Prompt:** (mismo turno que 2.1)
```
implementa STORY 2.1 y 2.4
[Con plan previo: SQL con CHECK plugin_type IN ('entity','extension'), CHECK status IN ('active','inactive','error'), UNIQUE plugin_slug, 5 tests incluyendo verificaciÃƒÂ³n de constraints]
```
**Resultado:** `002_core.sql` ampliado + `PluginsRegistryTableTest.php` con 5 tests, pasando al primer intento
**Iteraciones:** 1
**LecciÃƒÂ³n:** AÃƒÂ±adir CHECK constraints directamente en SQL (no en PHP) hace que los tests de constraint sean mÃƒÂ¡s simples Ã¢â‚¬â€ se verifica contra `information_schema.check_constraints`.

### STORY 2.2 â€” Tabla entity_metadata
**Prompt:** (turno "Si" â€” continuaciÃ³n secuencial de EPIC 2)
`
STORY 2.2 â€” Crear tabla entity_metadata con:
- id UUID PK, entity_slug VARCHAR(100), schema_version INT DEFAULT 1, schema_json JSONB NOT NULL, created_at TIMESTAMPTZ
- CHECK constraint: schema_json ? 'fields' (objeto con clave fields obligatoria)
- Ãndice compuesto (entity_slug, schema_version)
- Test de integraciÃ³n: table exists, expected columns, Ã­ndice, CHECK constraint rechaza schema_json sin fields
`
**Resultado:** SQL + test 4/4 en iteraciones â€” 1 ajuste en test de constraint para verificar causa exacta del error
**Iteraciones:** 2
**LecciÃ³n:** Al testear CHECK constraints de PostgreSQL, verificar que el PDOException incluye el nombre de la constraint en su mensaje para distinguir el fallo correcto de otro error inesperado.

### STORY 2.3 — Tabla entity_data
**Prompt:** (turno "Si" — continuación secuencial de EPIC 2)
```
STORY 2.3 — Crear tabla entity_data con:
- id UUID PK, entity_slug VARCHAR(100), owner_id UUID NULL, content JSONB DEFAULT '{}', created_at, updated_at, deleted_at
- Índices: BTREE(entity_slug), BTREE(owner_id), GIN(content)
- Soft delete via deleted_at NULL
- 5 tests: table exists, 7 columns, deleted_at nullable, GIN index, BTREE slug index
```
**Resultado:** SQL + test 5/5 en la primera iteración
**Iteraciones:** 1
**Lección:** GIN index es esencial para queries JSONB (@>, ?, etc.); declarar `owner_id` como NULL permite registros sin propietario explícito.

## Lecciones acumuladas

- **Testabilidad desde el diseÃƒÂ±o:** siempre pedir `?Dependency $dep = null` o inyecciÃƒÂ³n de constructor en controllers para que los tests no dependan de globals
- **BOM UTF-8 en Windows:** VS Code puede guardar con BOM; PHP rompe con `strict_types` si hay bytes antes de `<?php`. SoluciÃƒÂ³n: `[System.IO.File]::WriteAllText(..., UTF8Encoding(false))`
- **Extensiones PHP:** `pdo_pgsql` viene comentada por defecto en instalaciones manuales de PHP en Windows Ã¢â‚¬â€ verificar antes de asumir que PostgreSQL es accesible
- **Tests de integraciÃƒÂ³n para controllers:** usar `ob_start()/ob_get_clean()` para capturar el output de `Response::make()->json()` y decodificarlo como array
