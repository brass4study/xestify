# Prompts Efectivos ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Xestify

> Registro de los prompts que mejor funcionaron durante el desarrollo.
> ÃƒÆ’Ã…Â¡til para reutilizar y para el anÃƒÆ’Ã‚Â¡lisis acadÃƒÆ’Ã‚Â©mico.

---

## Formato

```
## [STORY] ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â [Objetivo del prompt]
**Prompt:** texto exacto
**Resultado:** quÃƒÆ’Ã‚Â© generÃƒÆ’Ã‚Â³ / cÃƒÆ’Ã‚Â³mo fue de ÃƒÆ’Ã‚Âºtil
**Iteraciones:** cuÃƒÆ’Ã‚Â¡ntas veces se refinÃƒÆ’Ã‚Â³
```

---

## EPIC 0 ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â PreparaciÃƒÆ’Ã‚Â³n TÃƒÆ’Ã‚Â©cnica

### STORY 0.1 ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Retomar sesiÃƒÆ’Ã‚Â³n
**Prompt:**
```
Lee docs/ia/sesion.md y retoma el desarrollo de Xestify donde lo dejamos.
```
**Resultado:** Contexto completo recuperado, continuaciÃƒÆ’Ã‚Â³n inmediata  
**Iteraciones:** 1

---

### STORY 0.2 ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Container DI
**Prompt:**
```
Implementa Xestify\Core\Container en PHP 8.1 nativo sin dependencias externas.
Necesito register(id, callable), singleton(id, callable), get(id), has(id).
- register() crea nueva instancia en cada get()
- singleton() crea la instancia solo la primera vez
- El factory recibe el Container como argumento para inyecciÃƒÆ’Ã‚Â³n entre servicios
- get() lanza InvalidArgumentException si no estÃƒÆ’Ã‚Â¡ registrado
DespuÃƒÆ’Ã‚Â©s genera un test standalone PHP (sin PHPUnit) con al menos 6 casos.
```
**Resultado:** ImplementaciÃƒÆ’Ã‚Â³n completa + 8 tests al primer intento  
**Iteraciones:** 1

---

### STORY 0.3 ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Router HTTP
**Prompt:**
```
Implementa Xestify\Core\Router en PHP 8.1. Recibe un Container en el constructor.
MÃƒÆ’Ã‚Â©todos: get(), post(), put(), delete(path, handler).
- Rutas dinÃƒÆ’Ã‚Â¡micas con :param ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ named capture groups en regex
- dispatch(method, uri): retorna true si hay match, null si no
- run(): despacha la peticiÃƒÆ’Ã‚Â³n real, responde 404 JSON si no hay ruta
- Handler puede ser callable o [Controller::class, 'method']
- Si es array, resuelve el controller via Container->get() si estÃƒÆ’Ã‚Â¡ registrado, sino new
DespuÃƒÆ’Ã‚Â©s genera test standalone con trailing slash, params mÃƒÆ’Ã‚Âºltiples, mÃƒÆ’Ã‚Â©todo incorrecto.
```
**Resultado:** ImplementaciÃƒÆ’Ã‚Â³n + 10 tests al primer intento  
**Iteraciones:** 1

---

### STORY 0.4 ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Request/Response
**Prompt:**
```
Implementa Xestify\Core\Request y Xestify\Core\Response en PHP 8.1.

Request:
- Constructor con query[], body[], headers[], routeParams[]
- fromGlobals(routeParams): construye desde $_GET, php://input JSON, $_SERVER
- query(key, default), body(key, default), header(key) case-insensitive
- param(key), bearerToken() ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ extrae "Bearer <token>" del header Authorization

Response:
- Envelope estÃƒÆ’Ã‚Â¡ndar: { ok:true, data, meta? } y { ok:false, error:{code,message,details?} }
- json(data, meta=[]), error(code, message, details=[])
- Shortcuts: notFound(), unauthorized(), forbidden(), unprocessable(), serverError()

Genera test standalone con 20 casos.
```
**Resultado:** ImplementaciÃƒÆ’Ã‚Â³n completa, fix necesario en `send()` por headers CLI  
**Iteraciones:** 2 (fix `PHP_SAPI !== 'cli'`)

**LecciÃƒÆ’Ã‚Â³n:** Cuando Response emite `header()` en CLI, contamina STDOUT. SoluciÃƒÆ’Ã‚Â³n: guardar el check `PHP_SAPI` en `send()`.

---

---

## EPIC 1 ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â AutenticaciÃƒÆ’Ã‚Â³n

### STORY 1.1 ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Database singleton + migraciÃƒÆ’Ã‚Â³n + seeder
**Prompt:**
```
Implementa STORY 1.1 del EPIC 1. Necesito:
- backend/database/migrations/001_users.sql ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â idempotente, UUID pk, email unique, roles JSONB
- Xestify\Core\Database ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â PDO singleton, lee DB_* de $_ENV, lanza DatabaseException
- Xestify\Database\Seeders\UserSeeder::seedIfEmpty() ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â inserta admin si COUNT=0
- Test de integraciÃƒÆ’Ã‚Â³n standalone (skip graceful si no hay PostgreSQL)
Sin Composer, PHP 8.1 nativo, PDO puro, prepared statements obligatorios.
```
**Resultado:** Todos los archivos generados al primer intento. El seeder detectÃƒÆ’Ã‚Â³ automÃƒÆ’Ã‚Â¡ticamente que debÃƒÆ’Ã‚Â­a vivir en `src/` para que el autoloader lo cubriera.
**Iteraciones:** 1  
**LecciÃƒÆ’Ã‚Â³n:** Los tests de integraciÃƒÆ’Ã‚Â³n que requieren BD real deben tener skip graceful (`exit(0)` si la conexiÃƒÆ’Ã‚Â³n falla) para no romper el pipeline en CI sin base de datos.

---

### STORY 1.2 ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â JwtService HS256 puro PHP
**Prompt:**
```
Implementa Xestify\Services\JwtService en PHP 8.1 sin dependencias externas.
- encode(array $payload): string ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â aÃƒÆ’Ã‚Â±ade iat y exp automÃƒÆ’Ã‚Â¡ticamente
- decode(string $token): array ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â lanza AuthException si firma invÃƒÆ’Ã‚Â¡lida o expirado
- HS256: Base64URL encode header.payload, HMAC-SHA256 con $_ENV['JWT_SECRET']
- Usa hash_equals para comparaciÃƒÆ’Ã‚Â³n timing-safe
Crea tambiÃƒÆ’Ã‚Â©n Xestify\Exceptions\AuthException (dominio, extiende RuntimeException).
Genera test standalone con: roundtrip, iat/exp, firma incorrecta, token adulterado,
malformado (1 y 2 segmentos), expirado.
```
**Resultado:** 8 tests, 0 fallos al primer intento. ImplementaciÃƒÆ’Ã‚Â³n 100% pura PHP, cero dependencias.  
**Iteraciones:** 1

---

### STORY 1.3 ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â AuthController (POST /api/auth/login)
**Prompt:**
```
Implementa Xestify\Controllers\AuthController con mÃƒÆ’Ã‚Â©todo login(array $params): void.
- POST /api/auth/login, body JSON: { email, password }
- 422 si email o password vacÃƒÆ’Ã‚Â­os
- SELECT users WHERE email=? con prepared statement
- password_verify() ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ 401 si no coincide
- JWT via JwtService::encode(), respuesta { ok:true, data:{ access_token } }
Registra AuthController en config/app.php (recibe JwtService del container).
AÃƒÆ’Ã‚Â±ade la ruta en config/routes.php.
```
**Resultado:** Controller, registro en container y ruta generados en un solo paso.  
**Iteraciones:** 1  
**LecciÃƒÆ’Ã‚Â³n:** Especificar el cÃƒÆ’Ã‚Â³digo de error por caso (422 vs 401) evita que IA decida por su cuenta.

---

### STORY 1.4 ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â AuthMiddleware + Request::user
**Prompt:**
```
Implementa Xestify\Middleware\AuthMiddleware con handle(Request $request, callable $next): void.
- Extrae bearerToken() del request
- Llama JwtService::decode(), captura AuthException ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ Response::make()->unauthorized()
- Si vÃƒÆ’Ã‚Â¡lido: $request->setUser($payload), llama $next($request)
AÃƒÆ’Ã‚Â±ade a Xestify\Core\Request:
- private ?array $user = null
- setUser(array $payload): void
- user(): ?array
Genera test standalone con 6 casos: token vÃƒÆ’Ã‚Â¡lido, payload propagado, sin token,
expirado, firma incorrecta, malformado.
```
**Resultado:** 6 tests, 0 fallos al primer intento.  
**Iteraciones:** 1

---

## Plantilla para prÃƒÆ’Ã‚Â³ximas stories

```
## STORY X.X ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â [Nombre]
**Prompt:**
```
[Texto del prompt]
```
**Resultado:** 
**Iteraciones:** 
**LecciÃƒÆ’Ã‚Â³n:** (si hubo algo inesperado)
```

---

## Patrones que funcionan bien

1. **Especificar la API pÃƒÆ’Ã‚Âºblica** antes de pedir implementaciÃƒÆ’Ã‚Â³n ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â evita que IA diseÃƒÆ’Ã‚Â±e su propia interfaz
2. **Pedir tests en el mismo prompt** ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â IA genera tests coherentes con la implementaciÃƒÆ’Ã‚Â³n
3. **Mencionar constraints** (sin Composer, PHP 8.1, standalone) ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â evita soluciones con dependencias
4. **"Genera test standalone con N casos"** ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â produce suites completas con edge cases

## Patrones que no funcionaron

- Pedir "implementa el sistema de auth" sin detallar la API ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ demasiado genÃƒÆ’Ã‚Â©rico
- No mencionar "sin PHPUnit" ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ generÃƒÆ’Ã‚Â³ tests con dependencia de Composer
- No pedir tests de integraciÃƒÆ’Ã‚Â³n para controllers desde el primer prompt ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ se aÃƒÆ’Ã‚Â±adieron en iteraciÃƒÆ’Ã‚Â³n posterior
- No pedir `?Request $request = null` en el constructor del controller desde el inicio ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ obligÃƒÆ’Ã‚Â³ a refactor para testabilidad

---

## EPIC 2 ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Modelo de Datos Core

### STORY 2.1 ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Tabla system_entities
**Prompt:**
```
implementa STORY 2.1 y 2.4
[Con plan previo detallado en contexto: SQL idempotente IF NOT EXISTS, campos id/slug/name/source_plugin_slug/is_active/created_at/updated_at, UNIQUE en slug, test con connectivity probe y skip graceful]
```
**Resultado:** `002_core.sql` + `SystemEntitiesTableTest.php` generados, 3 tests pasando al primer intento
**Iteraciones:** 1

---

### STORY 2.4 ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Tabla plugins_registry
**Prompt:** (mismo turno que 2.1)
```
implementa STORY 2.1 y 2.4
[Con plan previo: SQL con CHECK plugin_type IN ('entity','extension'), CHECK status IN ('active','inactive','error'), UNIQUE plugin_slug, 5 tests incluyendo verificaciÃƒÆ’Ã‚Â³n de constraints]
```
**Resultado:** `002_core.sql` ampliado + `PluginsRegistryTableTest.php` con 5 tests, pasando al primer intento
**Iteraciones:** 1
**LecciÃƒÆ’Ã‚Â³n:** AÃƒÆ’Ã‚Â±adir CHECK constraints directamente en SQL (no en PHP) hace que los tests de constraint sean mÃƒÆ’Ã‚Â¡s simples ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â se verifica contra `information_schema.check_constraints`.

### STORY 2.2 Ã¢â‚¬â€ Tabla entity_metadata
**Prompt:** (turno "Si" Ã¢â‚¬â€ continuaciÃƒÂ³n secuencial de EPIC 2)
`
STORY 2.2 Ã¢â‚¬â€ Crear tabla entity_metadata con:
- id UUID PK, entity_slug VARCHAR(100), schema_version INT DEFAULT 1, schema_json JSONB NOT NULL, created_at TIMESTAMPTZ
- CHECK constraint: schema_json ? 'fields' (objeto con clave fields obligatoria)
- ÃƒÂndice compuesto (entity_slug, schema_version)
- Test de integraciÃƒÂ³n: table exists, expected columns, ÃƒÂ­ndice, CHECK constraint rechaza schema_json sin fields
`
**Resultado:** SQL + test 4/4 en iteraciones Ã¢â‚¬â€ 1 ajuste en test de constraint para verificar causa exacta del error
**Iteraciones:** 2
**LecciÃƒÂ³n:** Al testear CHECK constraints de PostgreSQL, verificar que el PDOException incluye el nombre de la constraint en su mensaje para distinguir el fallo correcto de otro error inesperado.

### STORY 2.3 â€” Tabla entity_data
**Prompt:** (turno "Si" â€” continuaciÃ³n secuencial de EPIC 2)
```
STORY 2.3 â€” Crear tabla entity_data con:
- id UUID PK, entity_slug VARCHAR(100), owner_id UUID NULL, content JSONB DEFAULT '{}', created_at, updated_at, deleted_at
- Ãndices: BTREE(entity_slug), BTREE(owner_id), GIN(content)
- Soft delete via deleted_at NULL
- 5 tests: table exists, 7 columns, deleted_at nullable, GIN index, BTREE slug index
```
**Resultado:** SQL + test 5/5 en la primera iteraciÃ³n
**Iteraciones:** 1
**LecciÃ³n:** GIN index es esencial para queries JSONB (@>, ?, etc.); declarar `owner_id` como NULL permite registros sin propietario explÃ­cito.


### STORY 2.5 — Tabla plugin_hook_registry
**Prompt:** (turno "Si" — continuación secuencial de EPIC 2)
```
STORY 2.5 — Crear tabla plugin_hook_registry con:
- id UUID PK, plugin_slug VARCHAR(100), target_entity_slug VARCHAR(100), hook_name VARCHAR(50), priority INT DEFAULT 10, enabled BOOL DEFAULT true
- Índice compuesto (target_entity_slug, hook_name)
- Sin FK a plugins_registry (desacoplamiento intencional)
- 5 tests: table exists, 6 columns, priority default 10, enabled default true, composite index
```
**Resultado:** SQL + test 5/5 en la primera iteración
**Iteraciones:** 1
**Lección:** Omitir FK a plugins_registry es una decisión deliberada — permite registrar hooks de plugins que aún no están instalados, lo que facilita el bootstrap del sistema.

## Lecciones acumuladas

- **Testabilidad desde el diseÃƒÆ’Ã‚Â±o:** siempre pedir `?Dependency $dep = null` o inyecciÃƒÆ’Ã‚Â³n de constructor en controllers para que los tests no dependan de globals
- **BOM UTF-8 en Windows:** VS Code puede guardar con BOM; PHP rompe con `strict_types` si hay bytes antes de `<?php`. SoluciÃƒÆ’Ã‚Â³n: `[System.IO.File]::WriteAllText(..., UTF8Encoding(false))`
- **Extensiones PHP:** `pdo_pgsql` viene comentada por defecto en instalaciones manuales de PHP en Windows ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â verificar antes de asumir que PostgreSQL es accesible
- **Tests de integraciÃƒÆ’Ã‚Â³n para controllers:** usar `ob_start()/ob_get_clean()` para capturar el output de `Response::make()->json()` y decodificarlo como array
