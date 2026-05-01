# Prompts Efectivos — Xestify

> Registro de los prompts que mejor funcionaron durante el desarrollo.
> Útil para reutilizar y para el análisis académico.

---

## Formato

```
## [STORY] — [Objetivo del prompt]
**Prompt:** texto exacto
**Resultado:** qué generó / cómo fue de útil
**Iteraciones:** cuántas veces se refinó
```

---

## EPIC 0 — Preparación Técnica

### STORY 0.1 — Retomar sesión
**Prompt:**
```
Lee docs/ia/sesion.md y retoma el desarrollo de Xestify donde lo dejamos.
```
**Resultado:** Contexto completo recuperado, continuación inmediata  
**Iteraciones:** 1

---

### STORY 0.2 — Container DI
**Prompt:**
```
Implementa Xestify\Core\Container en PHP 8.1 nativo sin dependencias externas.
Necesito register(id, callable), singleton(id, callable), get(id), has(id).
- register() crea nueva instancia en cada get()
- singleton() crea la instancia solo la primera vez
- El factory recibe el Container como argumento para inyección entre servicios
- get() lanza InvalidArgumentException si no está registrado
Después genera un test standalone PHP (sin PHPUnit) con al menos 6 casos.
```
**Resultado:** Implementación completa + 8 tests al primer intento  
**Iteraciones:** 1

---

### STORY 0.3 — Router HTTP
**Prompt:**
```
Implementa Xestify\Core\Router en PHP 8.1. Recibe un Container en el constructor.
Métodos: get(), post(), put(), delete(path, handler).
- Rutas dinámicas con :param → named capture groups en regex
- dispatch(method, uri): retorna true si hay match, null si no
- run(): despacha la petición real, responde 404 JSON si no hay ruta
- Handler puede ser callable o [Controller::class, 'method']
- Si es array, resuelve el controller via Container->get() si está registrado, sino new
Después genera test standalone con trailing slash, params múltiples, método incorrecto.
```
**Resultado:** Implementación + 10 tests al primer intento  
**Iteraciones:** 1

---

### STORY 0.4 — Request/Response
**Prompt:**
```
Implementa Xestify\Core\Request y Xestify\Core\Response en PHP 8.1.

Request:
- Constructor con query[], body[], headers[], routeParams[]
- fromGlobals(routeParams): construye desde $_GET, php://input JSON, $_SERVER
- query(key, default), body(key, default), header(key) case-insensitive
- param(key), bearerToken() → extrae "Bearer <token>" del header Authorization

Response:
- Envelope estándar: { ok:true, data, meta? } y { ok:false, error:{code,message,details?} }
- json(data, meta=[]), error(code, message, details=[])
- Shortcuts: notFound(), unauthorized(), forbidden(), unprocessable(), serverError()

Genera test standalone con 20 casos.
```
**Resultado:** Implementación completa, fix necesario en `send()` por headers CLI  
**Iteraciones:** 2 (fix `PHP_SAPI !== 'cli'`)

**Lección:** Cuando Response emite `header()` en CLI, contamina STDOUT. Solución: guardar el check `PHP_SAPI` en `send()`.

---

---

## EPIC 1 — Autenticación

### STORY 1.1 — Database singleton + migración + seeder
**Prompt:**
```
Implementa STORY 1.1 del EPIC 1. Necesito:
- backend/database/migrations/001_users.sql — idempotente, UUID pk, email unique, roles JSONB
- Xestify\Core\Database — PDO singleton, lee DB_* de $_ENV, lanza DatabaseException
- Xestify\Database\Seeders\UserSeeder::seedIfEmpty() — inserta admin si COUNT=0
- Test de integración standalone (skip graceful si no hay PostgreSQL)
Sin Composer, PHP 8.1 nativo, PDO puro, prepared statements obligatorios.
```
**Resultado:** Todos los archivos generados al primer intento. El seeder detectó automáticamente que debía vivir en `src/` para que el autoloader lo cubriera.
**Iteraciones:** 1  
**Lección:** Los tests de integración que requieren BD real deben tener skip graceful (`exit(0)` si la conexión falla) para no romper el pipeline en CI sin base de datos.

---

### STORY 1.2 — JwtService HS256 puro PHP
**Prompt:**
```
Implementa Xestify\Services\JwtService en PHP 8.1 sin dependencias externas.
- encode(array $payload): string — añade iat y exp automáticamente
- decode(string $token): array — lanza AuthException si firma inválida o expirado
- HS256: Base64URL encode header.payload, HMAC-SHA256 con $_ENV['JWT_SECRET']
- Usa hash_equals para comparación timing-safe
Crea también Xestify\Exceptions\AuthException (dominio, extiende RuntimeException).
Genera test standalone con: roundtrip, iat/exp, firma incorrecta, token adulterado,
malformado (1 y 2 segmentos), expirado.
```
**Resultado:** 8 tests, 0 fallos al primer intento. Implementación 100% pura PHP, cero dependencias.  
**Iteraciones:** 1

---

### STORY 1.3 — AuthController (POST /api/auth/login)
**Prompt:**
```
Implementa Xestify\Controllers\AuthController con método login(array $params): void.
- POST /api/auth/login, body JSON: { email, password }
- 422 si email o password vacíos
- SELECT users WHERE email=? con prepared statement
- password_verify() → 401 si no coincide
- JWT via JwtService::encode(), respuesta { ok:true, data:{ access_token } }
Registra AuthController en config/app.php (recibe JwtService del container).
Añade la ruta en config/routes.php.
```
**Resultado:** Controller, registro en container y ruta generados en un solo paso.  
**Iteraciones:** 1  
**Lección:** Especificar el código de error por caso (422 vs 401) evita que IA decida por su cuenta.

---

### STORY 1.4 — AuthMiddleware + Request::user
**Prompt:**
```
Implementa Xestify\Middleware\AuthMiddleware con handle(Request $request, callable $next): void.
- Extrae bearerToken() del request
- Llama JwtService::decode(), captura AuthException → Response::make()->unauthorized()
- Si válido: $request->setUser($payload), llama $next($request)
Añade a Xestify\Core\Request:
- private ?array $user = null
- setUser(array $payload): void
- user(): ?array
Genera test standalone con 6 casos: token válido, payload propagado, sin token,
expirado, firma incorrecta, malformado.
```
**Resultado:** 6 tests, 0 fallos al primer intento.  
**Iteraciones:** 1

---

## Plantilla para próximas stories

```
## STORY X.X — [Nombre]
**Prompt:**
```
[Texto del prompt]
```
**Resultado:** 
**Iteraciones:** 
**Lección:** (si hubo algo inesperado)
```

---

## Patrones que funcionan bien

1. **Especificar la API pública** antes de pedir implementación — evita que IA diseñe su propia interfaz
2. **Pedir tests en el mismo prompt** — IA genera tests coherentes con la implementación
3. **Mencionar constraints** (sin Composer, PHP 8.1, standalone) — evita soluciones con dependencias
4. **"Genera test standalone con N casos"** — produce suites completas con edge cases

## Patrones que no funcionaron

- Pedir "implementa el sistema de auth" sin detallar la API → demasiado genérico
- No mencionar "sin PHPUnit" → generó tests con dependencia de Composer
- No pedir tests de integración para controllers desde el primer prompt → se añadieron en iteración posterior
- No pedir `?Request $request = null` en el constructor del controller desde el inicio → obligó a refactor para testabilidad

## Lecciones acumuladas

- **Testabilidad desde el diseño:** siempre pedir `?Dependency $dep = null` o inyección de constructor en controllers para que los tests no dependan de globals
- **BOM UTF-8 en Windows:** VS Code puede guardar con BOM; PHP rompe con `strict_types` si hay bytes antes de `<?php`. Solución: `[System.IO.File]::WriteAllText(..., UTF8Encoding(false))`
- **Extensiones PHP:** `pdo_pgsql` viene comentada por defecto en instalaciones manuales de PHP en Windows — verificar antes de asumir que PostgreSQL es accesible
- **Tests de integración para controllers:** usar `ob_start()/ob_get_clean()` para capturar el output de `Response::make()->json()` y decodificarlo como array
