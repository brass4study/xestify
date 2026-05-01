# Consideraciones Iniciales - Implementacion MVP

## Objetivo

Guía ejecutiva para iniciar desarrollo del MVP con las decisiones ya tomadas. Incluye checklist, estructura inicial, convenciones y trampas comunes a evitar.

---

## Checklist previo a Fase 0

- [ ] Decisiones técnicas aprobadas (ver [decisiones-tecnicas.md](decisiones-tecnicas.md))
- [ ] Equipo alineado en stack: PHP nativo, Vanilla JS, JWT, Schema custom
- [x] PHP 8.1+ instalado localmente (`php --version`)
- [x] PostgreSQL 13+ instalado localmente (`psql --version`)
- [ ] Repositorio Git creado con .gitignore base
- [ ] Base de datos `xestify_dev` creada y accesible
- [ ] `backend/.env` configurado con credenciales locales
- [ ] CD/CI pipeline planificado (tests mínimos por fase)

> **Sin Docker para MVP:** Desarrollo sobre PHP nativo + PostgreSQL local. Docker se añadirá
> como archivo documental al final del proyecto para el deployment futuro en RPi5.

---

## Estructura inicial esperada (Fase 0)

```
xestify/
├── backend/
│   ├── src/
│   │   ├── Core/
│   │   │   ├── Container.php          (DI container casero)
│   │   │   ├── Router.php             (ruteo HTTP)
│   │   │   ├── Database.php           (conexión PDO)
│   │   │   └── Request.php            (objeto request)
│   │   ├── Services/
│   │   │   ├── EntityService.php      (CRUD dinámico)
│   │   │   ├── ValidationService.php  (schema validation)
│   │   │   ├── PluginLoader.php       (cargador plugins)
│   │   │   ├── HookDispatcher.php     (ejecutor hooks)
│   │   │   └── AuthService.php        (JWT)
│   │   ├── Controllers/
│   │   │   ├── EntityController.php
│   │   │   ├── AuthController.php
│   │   │   └── HealthController.php   (ping)
│   │   ├── Middleware/
│   │   │   ├── AuthMiddleware.php
│   │   │   └── ErrorHandler.php
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── SystemEntity.php
│   │   │   ├── EntityData.php
│   │   │   └── Plugin.php
│   │   └── config/
│   │       ├── database.php
│   │       ├── app.php
│   │       └── jwt.php
│   ├── public/
│   │   └── index.php                  (entrada)
│   ├── database/
│   │   ├── migrations/
│   │   │   └── 001_init.sql
│   │   └── seeders/
│   │       └── CoreSeeder.php
│   ├── tests/
│   │   ├── unit/
│   │   └── integration/
│   ├── .env.example
│   ├── composer.json
│   └── README.md
│
├── frontend/
│   ├── src/
│   │   ├── js/
│   │   │   ├── modules/
│   │   │   │   ├── DynamicForm.js
│   │   │   │   ├── DynamicTable.js
│   │   │   │   ├── DynamicTabs.js
│   │   │   │   ├── Api.js
│   │   │   │   └── State.js
│   │   │   ├── pages/
│   │   │   │   ├── EntityList.js
│   │   │   │   ├── EntityEdit.js
│   │   │   │   ├── Login.js
│   │   │   │   └── PluginManager.js
│   │   │   └── main.js
│   │   ├── html/
│   │   │   ├── index.html
│   │   │   └── partials/
│   │   └── css/
│   │       ├── reset.css
│   │       ├── layout.css
│   │       └── components.css
│   ├── tests/
│   │   ├── unit/
│   │   └── e2e/
│   ├── package.json
│   └── README.md
│
├── docker/                     (deployment futuro RPi5)
│
├── docs/
│   ├── mvp/
│   │   ├── decisiones-tecnicas.md      (TÚ ESTÁS AQUÍ)
│   │   ├── historial-decisiones.md
│   │   └── consideraciones-iniciales.md
│   ├── arquitectura/
│   ├── datos/
│   ├── api/
│   ├── frontend/
│   ├── operacion/
│   ├── seguridad/
│   └── plugins/
│
├── .gitignore
├── README.md
├── roadmap.md
└── CONTRIBUTING.md
```

---

## Convenciones clave

### Backend (PHP nativo)

**Namespacing:**
```php
namespace Xestify\Core;
namespace Xestify\Services;
namespace Xestify\Controllers;
```

**Convención de nombres:**
- Clases: `PascalCase` (ej. `EntityService`)
- Métodos: `camelCase` (ej. `createRecord()`)
- Constantes: `UPPER_SNAKE_CASE` (ej. `DB_HOST`)

**Error handling:**
```php
try {
    $entity = $entityService->create($payload);
} catch (ValidationException $e) {
    return response()->json(['error' => $e->getMessage()], 400);
} catch (\Exception $e) {
    // Log
    return response()->json(['error' => 'Internal error'], 500);
}
```

**Acceso a BD:**
```php
// SIEMPRE parametrizado
$stmt = $db->prepare("SELECT * FROM entity_data WHERE entity_slug = ? AND deleted_at IS NULL");
$stmt->execute([$slug]);
```

### Frontend (Vanilla JS)

**Modularidad por clases:**
```javascript
class DynamicForm {
    constructor(schema, container) {
        this.schema = schema;
        this.container = container;
    }
    
    render() { ... }
    validate() { ... }
    getData() { ... }
}

// Uso
const form = new DynamicForm(clientSchema, '#form-container');
form.render();
```

**Estado global simple:**
```javascript
const AppState = {
    user: null,
    entities: {},
    metadata: {},
    
    setUser(user) { this.user = user; },
    getUser() { return this.user; }
};
```

**Convención de nombres:**
- Clases: `PascalCase` (ej. `DynamicForm`)
- Funciones/métodos: `camelCase` (ej. `fetchSchema()`)
- Constantes: `UPPER_SNAKE_CASE` (ej. `API_BASE_URL`)

### Migraciones SQL

**Formato:**
```sql
-- 001_init.sql
-- Fecha: 2026-05-01
-- Descripcion: Tablas core del sistema

CREATE TABLE system_entities (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    slug VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    ...
);
```

---

## Trampas comunes a evitar

### Trap 1: Lógica de negocio en Controllers
❌ MAL:
```php
public function createClient($data) {
    $db->insert('entity_data', ...);
    // cálculos, validaciones, hooks aquí
}
```

✅ BIEN:
```php
public function createClient($data) {
    return $this->entityService->create('client', $data);
}
```

### Trap 2: Hardcodear tipos de entidad
❌ MAL:
```php
if ($entitySlug == 'client') { ... }
if ($entitySlug == 'product') { ... }
```

✅ BIEN:
```php
$schema = $this->metadataService->getSchema($entitySlug);
// Procesa genéricamente
```

### Trap 3: Validación solo en cliente
❌ MAL: Confiar en validación JS.

✅ BIEN: Backend valida SIEMPRE contra schema.

### Trap 4: Comunicación API sin estructura
❌ MAL: Cada endpoint devuelve formato distinto.

✅ BIEN: Envelope estándar:
```json
{
  "ok": true,
  "data": {...},
  "meta": {"schema_version": "1.0.0"}
}
```

### Trap 5: Plugins sin aislamiento
❌ MAL: Plugin accede directamente a tabla de otro plugin.

✅ BIEN: Todo vía HookDispatcher, hooks explícitos.

---

## Decisiones pequeñas que vienen

Antes de empezar cada fase, confirmar:

1. **Fase 1 (Auth):** Estructura de tabla `users` (salt, hash, roles)
2. **Fase 2 (Datos):** UUID vs auto-increment (recomendación: UUID para portabilidad)
3. **Fase 3 (CRUD):** Soft delete vs hard delete por defecto (recomendación: soft)
4. **Fase 4 (Plugins):** Precedencia de hooks si múltiples plugins registran mismo hook
5. **Fase 5 (Frontend):** DOM selectors estándar (ej. `data-js-*` attributes)

---

## Stack minimo confirmado

| Layer | Technology | Version | Justificación |
|-------|-----------|---------|---------------|
| Backend | PHP | 8.1+ | Tipos, attributes |
| Database | PostgreSQL | 13+ | JSONB, ARM64 |
| Frontend | JavaScript | ES2020+ | Classes, fetch, async/await |
| Auth | JWT | RS256 | Standard, secure |
| Deployment | Docker | Latest | Reproducible, RPi5 friendly |

---

## Metricas de exito por Fase (MVP)

**Fase 0:**
- ✅ `npm start` levanta backend + frontend en localhost.
- ✅ Endpoints básicos responden en JSON.

**Fase 1:**
- ✅ Usuario puede hacer login y recibe JWT.
- ✅ Endpoints protegidos rechazan sin token.

**Fase 2:**
- ✅ Tablas core existen, migraciones idempotentes.
- ✅ Se puede insertar/leer registros con JSONB.

**Fase 3:**
- ✅ Crear cliente sin tocar código backend (solo metadata).
- ✅ Validación rechaza payload inválido con error por campo.

**Fase 4:**
- ✅ Plugin instalable, hook beforeSave bloquea operación.

**Fase 5:**
- ✅ Formulario dinámico renderiza campos de cualquier entidad.
- ✅ Crear/editar/listar funciona E2E.

**Fase 6:**
- ✅ Extension de plugin aparece como tab en cliente.
- ✅ Operaciones CRUD de extension respetan owner_id.

---

## Próximos pasos

1. Leer [decisiones-tecnicas.md](decisiones-tecnicas.md) como referencia rápida.
2. Leer [historial-decisiones.md](historial-decisiones.md) si necesitas cambiar alguna decisión.
3. Empezar Fase 0: setup del repositorio.
4. Ejecutar [roadmap.md](../../roadmap.md) fase por fase.
