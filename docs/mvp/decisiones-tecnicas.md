# Decisiones Tecnicas - MVP Xestify

## Resumen ejecutivo

Documento que consolida las 5 decisiones tecnicas tomadas para el MVP. Cada decision incluye justificacion, implicaciones y referencias al historial completo.

**Fecha de resolucion:** Mayo 1, 2026  
**Responsable de decisiones:** [Usuario]  
**Estado:** Aprobadas y listas para implementacion

---

## DECISION 1: Backend - PHP Nativo

**Seleccionado:** PHP nativo  
**Alternativas consideradas:** Laravel, Symfony  
**Fecha:** Mayo 1, 2026

### Justificacion
- Máximo control y visibilidad del flujo de entidades dinámicas.
- Ningún overhead innecesario para un Core minimalista metadata-driven.
- RPi5 vuela sin problemas.
- Ideal para entender cada línea de lógica de plugins.

### Implicaciones
- Responsabilidad de implementar: routing manual, DI container, migraciones, eventos/hooks.
- Estructura esperada: `backend/src/Core/`, `backend/src/Services/`, `backend/src/Controllers/`.
- No hay convenciones automáticas: cada componente requiere decisión explícita.

### Riesgos mitigados
- Abstracción innecesaria.
- Lock-in a framework.

### Cambio futuro
Si en beta emerge complejidad no prevista, migración a Laravel es viable sin romper lógica de negocio (1-2 semanas).

---

## DECISION 2: Inyección de Dependencias - Contenedor Casero

**Seleccionado:** Contenedor casero  
**Alternativas consideradas:** Pimple, PHP-DI  
**Fecha:** Mayo 1, 2026

### Justificacion
- Máximo control sobre cómo se inyectan plugins en runtime.
- Cero overhead, cero magia.
- Permite registrar hooks directamente al construir servicios.
- Ideal para debugging.

### Estructura esperada
```php
class Container {
    private $services = [];      // Singletons
    private $factories = [];     // Factories
    
    public function register($name, callable $factory) { ... }
    public function singleton($name, callable $factory) { ... }
    public function get($name) { ... }
}
```

### Implicaciones
- ~200-300 líneas de código inicial.
- Resolución manual de dependencias entre servicios.
- Gestión de ciclo de vida (init/boot/shutdown).

### Cambio futuro
Si necesidad de autowiring emerge, upgrade a PHP-DI es directo.

---

## DECISION 3: Frontend - Vanilla PURO

**Seleccionado:** Vanilla JavaScript puro  
**Alternativas consideradas:** Vue 3, React, Vanilla + Alpine/htmx  
**Fecha:** Mayo 1, 2026

### Justificacion
- Cero dependencias externas = máxima transparencia.
- Cada componente es una clase reutilizable.
- Debugging trivial.
- RPi5 respira (zero overhead).
- Ideal para sistemas altamente dinámicos.

### Estructura esperada
```
frontend/src/
  js/
    modules/
      DynamicForm.js      (clase)
      DynamicTable.js     (clase)
      DynamicTabs.js      (clase)
      Api.js              (cliente HTTP)
      State.js            (estado global)
    pages/
      EntityList.js
      EntityEdit.js
  html/
    index.html
  css/
    style.css
```

### Implicaciones
- Responsabilidad de: validación UX, estado global, manejo de componentes dinámicos.
- Más líneas de código que Vue/React, pero 100% transparente.
- Componentización por clases reutilizables.

### Riesgos mitigados
- Build step innecesario.
- Complejidad de bundler.

### Cambio futuro
Si UX crece explosivamente, transición a Vue 3 es factible sin reescribir lógica (componentes dinámicos aplican igual).

---

## DECISION 4: Autenticación - JWT

**Seleccionado:** JWT (JSON Web Token)  
**Alternativas consideradas:** Sesión local (Session + Cookie HTTP-only)  
**Fecha:** Mayo 1, 2026

### Justificacion
- Stateless en servidor = escalabilidad.
- Funciona bien con marketplace remoto (futuro).
- Token enviado en cada request en header `Authorization: Bearer <token>`.
- Compatible con múltiples clientes (desktop, mobile, etc.).

### Estructura esperada
```json
{
  "sub": "user_id_uuid",
  "email": "admin@xestify.local",
  "roles": ["admin"],
  "iat": 1234567890,
  "exp": 1234571490
}
```

### Flujo esperado
1. Login → Backend valida credenciales → Emite JWT.
2. Cliente almacena en localStorage.
3. Cada request incluye header JWT.
4. Backend valida firma del token.

### Implicaciones
- Necesidad de blacklist para revocación (tabla en BD).
- Tokens refresh: access_token (1-2h) + refresh_token (7d).
- Cliente debe manejar renovación automática.

### Riesgos mitigados
- XSS puede leer localStorage (mitigar con CSP headers).

### Cambio futuro
Transición a sesión local es reversible (cambio de ~300 líneas en backend y frontend).

---

## DECISION 5: Schema de Entidades - Custom Minimalista

**Seleccionado:** Schema custom minimalista  
**Alternativas consideradas:** JSON Schema estándar, Schema custom con soporte futuro a JSON Schema  
**Fecha:** Mayo 1, 2026

### Justificacion
- Ultra simple, fácil parsear.
- Validador casero = ~100 líneas de PHP.
- Controlamos exactamente qué soportamos.
- MVP rápido.

### Estructura esperada
```json
{
  "entity": "client",
  "version": "1.0.0",
  "fields": [
    {
      "name": "nombre",
      "type": "string",
      "required": true,
      "label": "Nombre completo",
      "validation": {"minLength": 1, "maxLength": 255}
    },
    {
      "name": "email",
      "type": "email",
      "required": false,
      "label": "Email"
    }
  ]
}
```

### Tipos soportados (MVP)
- `string`, `email`, `phone`
- `number`, `integer`
- `boolean`
- `date`, `datetime`
- `select` (con array de opciones)

### Futuro
- `array`, `object` (para extensiones complejas)

### Implicaciones
- Documentación propia: 1 página.
- Validador PHP casero: <100 líneas.
- Si crece, migración a JSON Schema es cambio puro de parser.

### Riesgos mitigados
- Over-engineering inicial.

### Cambio futuro
Migración a JSON Schema es viable sin romper (1 semana de refactor puro).

---

## Matriz de decisiones

| Componente | Decision | MVP Ready | Risk Level |
|-----------|----------|-----------|-----------|
| Backend | PHP nativo | ✅ Si | 🟡 Medio |
| DI Container | Casero | ✅ Si | 🟡 Medio |
| Frontend | Vanilla JS | ✅ Si | 🔴 Alto |
| Autenticación | JWT | ✅ Si | 🟢 Bajo |
| Schema | Custom | ✅ Si | 🟢 Bajo |

---

## Proximo paso

Ver [historial-decisiones.md](historial-decisiones.md) para contexto completo de opciones consideradas.

Ver [consideraciones-iniciales.md](consideraciones-iniciales.md) para guía de implementación rápida.
