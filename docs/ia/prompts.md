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
