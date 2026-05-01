# Prompts Efectivos â€” Xestify

> Registro de los prompts que mejor funcionaron durante el desarrollo.
> Ãštil para reutilizar y para el anÃ¡lisis acadÃ©mico.

---

## Formato

```
## [STORY] â€” [Objetivo del prompt]
**Prompt:** texto exacto
**Resultado:** quÃ© generÃ³ / cÃ³mo fue de Ãºtil
**Iteraciones:** cuÃ¡ntas veces se refinÃ³
```

---

## EPIC 0 â€” PreparaciÃ³n TÃ©cnica

### STORY 0.1 â€” Retomar sesiÃ³n
**Prompt:**
```
Lee docs/ia/sesion.md y retoma el desarrollo de Xestify donde lo dejamos.
```
**Resultado:** Contexto completo recuperado, continuaciÃ³n inmediata  
**Iteraciones:** 1

---

### STORY 0.2 â€” Container DI
**Prompt:**
```
Implementa Xestify\Core\Container en PHP 8.1 nativo sin dependencias externas.
Necesito register(id, callable), singleton(id, callable), get(id), has(id).
- register() crea nueva instancia en cada get()
- singleton() crea la instancia solo la primera vez
- El factory recibe el Container como argumento para inyecciÃ³n entre servicios
- get() lanza InvalidArgumentException si no estÃ¡ registrado
DespuÃ©s genera un test standalone PHP (sin PHPUnit) con al menos 6 casos.
```
**Resultado:** ImplementaciÃ³n completa + 8 tests al primer intento  
**Iteraciones:** 1

---

### STORY 0.3 â€” Router HTTP
**Prompt:**
```
Implementa Xestify\Core\Router en PHP 8.1. Recibe un Container en el constructor.
MÃ©todos: get(), post(), put(), delete(path, handler).
- Rutas dinÃ¡micas con :param â†’ named capture groups en regex
- dispatch(method, uri): retorna true si hay match, null si no
- run(): despacha la peticiÃ³n real, responde 404 JSON si no hay ruta
- Handler puede ser callable o [Controller::class, 'method']
- Si es array, resuelve el controller via Container->get() si estÃ¡ registrado, sino new
DespuÃ©s genera test standalone con trailing slash, params mÃºltiples, mÃ©todo incorrecto.
```
**Resultado:** ImplementaciÃ³n + 10 tests al primer intento  
**Iteraciones:** 1

---

### STORY 0.4 â€” Request/Response
**Prompt:**
```
Implementa Xestify\Core\Request y Xestify\Core\Response en PHP 8.1.

Request:
- Constructor con query[], body[], headers[], routeParams[]
- fromGlobals(routeParams): construye desde $_GET, php://input JSON, $_SERVER
- query(key, default), body(key, default), header(key) case-insensitive
- param(key), bearerToken() â†’ extrae "Bearer <token>" del header Authorization

Response:
- Envelope estÃ¡ndar: { ok:true, data, meta? } y { ok:false, error:{code,message,details?} }
- json(data, meta=[]), error(code, message, details=[])
- Shortcuts: notFound(), unauthorized(), forbidden(), unprocessable(), serverError()

Genera test standalone con 20 casos.
```
**Resultado:** ImplementaciÃ³n completa, fix necesario en `send()` por headers CLI  
**Iteraciones:** 2 (fix `PHP_SAPI !== 'cli'`)

**LecciÃ³n:** Cuando Response emite `header()` en CLI, contamina STDOUT. SoluciÃ³n: guardar el check `PHP_SAPI` en `send()`.

---

---

## EPIC 1 â€” AutenticaciÃ³n

### STORY 1.1 â€” Database singleton + migraciÃ³n + seeder
**Prompt:**
```
Implementa STORY 1.1 del EPIC 1. Necesito:
- backend/database/migrations/001_users.sql â€” idempotente, UUID pk, email unique, roles JSONB
- Xestify\Core\Database â€” PDO singleton, lee DB_* de $_ENV, lanza DatabaseException
- Xestify\Database\Seeders\UserSeeder::seedIfEmpty() â€” inserta admin si COUNT=0
- Test de integraciÃ³n standalone (skip graceful si no hay PostgreSQL)
Sin Composer, PHP 8.1 nativo, PDO puro, prepared statements obligatorios.
```
**Resultado:** Todos los archivos generados al primer intento. El seeder detectÃ³ automÃ¡ticamente que debÃ­a vivir en `src/` para que el autoloader lo cubriera.
**Iteraciones:** 1  
**LecciÃ³n:** Los tests de integraciÃ³n que requieren BD real deben tener skip graceful (`exit(0)` si la conexiÃ³n falla) para no romper el pipeline en CI sin base de datos.

---

### STORY 1.2 â€” JwtService HS256 puro PHP
**Prompt:**
```
Implementa Xestify\Services\JwtService en PHP 8.1 sin dependencias externas.
- encode(array $payload): string â€” aÃ±ade iat y exp automÃ¡ticamente
- decode(string $token): array â€” lanza AuthException si firma invÃ¡lida o expirado
- HS256: Base64URL encode header.payload, HMAC-SHA256 con $_ENV['JWT_SECRET']
- Usa hash_equals para comparaciÃ³n timing-safe
Crea tambiÃ©n Xestify\Exceptions\AuthException (dominio, extiende RuntimeException).
Genera test standalone con: roundtrip, iat/exp, firma incorrecta, token adulterado,
malformado (1 y 2 segmentos), expirado.
```
**Resultado:** 8 tests, 0 fallos al primer intento. ImplementaciÃ³n 100% pura PHP, cero dependencias.  
**Iteraciones:** 1

---

### STORY 1.3 â€” AuthController (POST /api/auth/login)
**Prompt:**
```
Implementa Xestify\Controllers\AuthController con mÃ©todo login(array $params): void.
- POST /api/auth/login, body JSON: { email, password }
- 422 si email o password vacÃ­os
- SELECT users WHERE email=? con prepared statement
- password_verify() â†’ 401 si no coincide
- JWT via JwtService::encode(), respuesta { ok:true, data:{ access_token } }
Registra AuthController en config/app.php (recibe JwtService del container).
AÃ±ade la ruta en config/routes.php.
```
**Resultado:** Controller, registro en container y ruta generados en un solo paso.  
**Iteraciones:** 1  
**LecciÃ³n:** Especificar el cÃ³digo de error por caso (422 vs 401) evita que IA decida por su cuenta.

---

### STORY 1.4 â€” AuthMiddleware + Request::user
**Prompt:**
```
Implementa Xestify\Middleware\AuthMiddleware con handle(Request $request, callable $next): void.
- Extrae bearerToken() del request
- Llama JwtService::decode(), captura AuthException â†’ Response::make()->unauthorized()
- Si vÃ¡lido: $request->setUser($payload), llama $next($request)
AÃ±ade a Xestify\Core\Request:
- private ?array $user = null
- setUser(array $payload): void
- user(): ?array
Genera test standalone con 6 casos: token vÃ¡lido, payload propagado, sin token,
expirado, firma incorrecta, malformado.
```
**Resultado:** 6 tests, 0 fallos al primer intento.  
**Iteraciones:** 1

---

## Plantilla para prÃ³ximas stories

```
## STORY X.X â€” [Nombre]
**Prompt:**
```
[Texto del prompt]
```
**Resultado:** 
**Iteraciones:** 
**LecciÃ³n:** (si hubo algo inesperado)
```

---

## Patrones que funcionan bien

1. **Especificar la API pÃºblica** antes de pedir implementaciÃ³n â€” evita que IA diseÃ±e su propia interfaz
2. **Pedir tests en el mismo prompt** â€” IA genera tests coherentes con la implementaciÃ³n
3. **Mencionar constraints** (sin Composer, PHP 8.1, standalone) â€” evita soluciones con dependencias
4. **"Genera test standalone con N casos"** â€” produce suites completas con edge cases

## Patrones que no funcionaron

- Pedir "implementa el sistema de auth" sin detallar la API â†’ demasiado genÃ©rico
- No mencionar "sin PHPUnit" â†’ generÃ³ tests con dependencia de Composer
- No pedir tests de integraciÃ³n para controllers desde el primer prompt â†’ se aÃ±adieron en iteraciÃ³n posterior
- No pedir `?Request $request = null` en el constructor del controller desde el inicio â†’ obligÃ³ a refactor para testabilidad

---

## EPIC 2 â€” Modelo de Datos Core

### STORY 2.1 â€” Tabla system_entities
**Prompt:**
```
implementa STORY 2.1 y 2.4
[Con plan previo detallado en contexto: SQL idempotente IF NOT EXISTS, campos id/slug/name/source_plugin_slug/is_active/created_at/updated_at, UNIQUE en slug, test con connectivity probe y skip graceful]
```
**Resultado:** `002_core.sql` + `SystemEntitiesTableTest.php` generados, 3 tests pasando al primer intento
**Iteraciones:** 1

---

### STORY 2.4 â€” Tabla plugins_registry
**Prompt:** (mismo turno que 2.1)
```
implementa STORY 2.1 y 2.4
[Con plan previo: SQL con CHECK plugin_type IN ('entity','extension'), CHECK status IN ('active','inactive','error'), UNIQUE plugin_slug, 5 tests incluyendo verificaciÃ³n de constraints]
```
**Resultado:** `002_core.sql` ampliado + `PluginsRegistryTableTest.php` con 5 tests, pasando al primer intento
**Iteraciones:** 1
**LecciÃ³n:** AÃ±adir CHECK constraints directamente en SQL (no en PHP) hace que los tests de constraint sean mÃ¡s simples â€” se verifica contra `information_schema.check_constraints`.

### STORY 2.2 — Tabla entity_metadata
**Prompt:** (turno "Si" — continuación secuencial de EPIC 2)
`
STORY 2.2 — Crear tabla entity_metadata con:
- id UUID PK, entity_slug VARCHAR(100), schema_version INT DEFAULT 1, schema_json JSONB NOT NULL, created_at TIMESTAMPTZ
- CHECK constraint: schema_json ? 'fields' (objeto con clave fields obligatoria)
- Índice compuesto (entity_slug, schema_version)
- Test de integración: table exists, expected columns, índice, CHECK constraint rechaza schema_json sin fields
`
**Resultado:** SQL + test 4/4 en iteraciones — 1 ajuste en test de constraint para verificar causa exacta del error
**Iteraciones:** 2
**Lección:** Al testear CHECK constraints de PostgreSQL, verificar que el PDOException incluye el nombre de la constraint en su mensaje para distinguir el fallo correcto de otro error inesperado.
## Lecciones acumuladas

- **Testabilidad desde el diseÃ±o:** siempre pedir `?Dependency $dep = null` o inyecciÃ³n de constructor en controllers para que los tests no dependan de globals
- **BOM UTF-8 en Windows:** VS Code puede guardar con BOM; PHP rompe con `strict_types` si hay bytes antes de `<?php`. SoluciÃ³n: `[System.IO.File]::WriteAllText(..., UTF8Encoding(false))`
- **Extensiones PHP:** `pdo_pgsql` viene comentada por defecto en instalaciones manuales de PHP en Windows â€” verificar antes de asumir que PostgreSQL es accesible
- **Tests de integraciÃ³n para controllers:** usar `ob_start()/ob_get_clean()` para capturar el output de `Response::make()->json()` y decodificarlo como array
