# Brief Académico - Xestify MVP en 1 Mes

## Contexto: Proyecto de Master en Desarrollo con IA

Este documento define alcance, entregables y estrategia para completar Xestify como proyecto demostrativo de un **Master en Desarrollo Asistido por IA** en **4-5 semanas**.

---

## Objetivo Académico

Demostrar que:
1. Arquitectura modular (plugins, hooks) es viable en timeline acelerado.
2. IA (CodeVibe/Copilot) acelera 1.4-1.6x el desarrollo de sistemas repetitivos.
3. Decisiones arquitectónicas se benefician POCO de IA, pero implementación mucho.
4. Un MVP funcional + documentación de proceso > código perfecto sin contexto.

---

## Alcance Reducido: MVP "Proof of Concept"

### ✅ IN SCOPE (40 puntos MUST)

**EPIC 0 - Preparación técnica (2 semanas)**
- ✅ Setup Git + estructura carpetas
- ✅ Entorno local: PHP 8.1+ nativo + PostgreSQL (sin Docker)
- ✅ Container DI casero + Router
- ✅ Frontend skeleton

> **Nota:** Docker queda fuera del scope MVP académico (decisión 2026-05-01).
> Se añade `docker-compose.yml` documental solo si sobra tiempo (Semana 4, COULD).

**EPIC 1 - Autenticación (1 semana)**
- ✅ JWT + tabla users
- ✅ AuthController (login)
- ✅ AuthMiddleware

**EPIC 2 - Datos (3-4 días)**
- ✅ Tablas core: system_entities, entity_metadata, entity_data
- ✅ GenericRepository básico

**EPIC 3 - CRUD Dinámico (1 semana)**
- ✅ ValidationService (schema custom)
- ✅ EntityService (CRUD)
- ✅ EntityController (API REST)
- ✅ DynamicForm + DynamicTable (frontend)

**EPIC 4 - Plugins Backend (3-4 días)**
- ✅ PluginLoader minimo
- ✅ HookDispatcher básico
- ✅ Plugin entity_client de ejemplo
- ✅ Hooks beforeSave/afterSave

**EPIC 5 - Frontend Dinámico (1 semana)**
- ✅ Login página
- ✅ EntityList + EntityEdit
- ✅ Flujo E2E funcional

**BONUS (si sobra tiempo):**
- Frontend mejorado (CSS, UX)
- Tests de EPIC 3

### ❌ OUT OF SCOPE (para thesis posterior/futuro)

- ❌ EPIC 6: Extensiones complejas (tabs inyectadas dinámicas)
- ❌ EPIC 7: Actualizaciones automáticas + rollback avanzado
- ❌ EPIC 8: RPi5 hardening + backups automáticos
- ❌ EPIC 9: Marketplace central completo
- ❌ EPIC 10: QA exhaustivo (solo tests clave)

**Razón:** 70% de valor académico está en EPIC 0-5. Extensiones avanzan poco el learning.

---

## Estructura de Entregables Académicos

### 1. Código Funcional (50% de la nota)
- ✅ Repositorio GitHub con commits claros
- ✅ Demo en vivo: login → crear entidad → crear cliente → instalar plugin
- ✅ Dockerizable y reproducible en cualquier máquina

### 2. Documentación de Proceso (30% de la nota)
- ✅ [ia-productivity-template.md](ia-productivity-template.md) — análisis de cómo IA aceleró
- ✅ Comparativa: tiempo sin IA vs con IA por tarea
- ✅ Prompts efectivos guardados + análisis
- ✅ Problemas encontrados y soluciones

### 3. Análisis Técnico (20% de la nota)
- ✅ Decisiones arquitectónicas: por qué PHP nativo, Container casero, Vanilla
- ✅ Trade-offs: flexibilidad vs velocidad
- ✅ Lecciones aprendidas sobre plugins y hooks
- ✅ Limitaciones del MVP y rutas futuras

---

## Timeline de 1 mes (4-5 semanas)

### Semana 1: EPIC 0 + EPIC 1 (Fundamentos)
**Tareas:**
- Setup Git, Docker, estructura
- Container DI + Router
- JWT + AuthController
- Frontend skeleton + Login

**IA Usage:**
- Generar boilerplate HTML/CSS
- Generar JWT helpers
- Generar tests para auth

**Entregable:** Proyecto arranca, puedo hacer login

### Semana 2: EPIC 2 + EPIC 3 (Core CRUD)
**Tareas:**
- Migraciones SQL
- ValidationService (todos los tipos)
- EntityService full CRUD
- EntityController REST
- DynamicForm + DynamicTable

**IA Usage:**
- Generar validadores por tipo
- Generar CRUD SQL parametrizado
- Generar componentes frontend dinámicos
- Generar casos de test

**Entregable:** CRUD funcional sin IA en backend + interfaz

### Semana 3: EPIC 4 + EPIC 5 (Plugins + UI)
**Tareas:**
- PluginLoader
- HookDispatcher
- Plugin entity_client
- Frontend E2E integration

**IA Usage:**
- Generar esqueleto de plugin
- Generar listeners de hooks
- Integrar API con frontend

**Entregable:** Flujo completo: login → crear cliente → datos guardados

### Semana 4: Polish + Documentación Académica
**Tareas:**
- Mejorar CSS/UX si aplica
- Escribir análisis de productividad
- Compilar prompts + análisis
- Escribir README y guía académica

**Entregable:** Código limpio + documentación lista para defensa

### Semana 5: Contingencia / Bonus
- Tests adicionales
- Performance optimization en RPi5 (opcional)
- Preparación de demo en vivo

---

## Entregables Finales para Defensa

### 📦 Package 1: Código
```
xestify/
├── backend/                    (PHP nativo + plugins)
├── frontend/                   (Vanilla JS puro)
├── docker/                     (Docker Compose - deployment futuro RPi5)
├── docs/
│   ├── README.md
│   ├── roadmap.md
│   └── mvp/
│       ├── decisiones-tecnicas.md
│       ├── ia-productivity-analysis.md  ← NUEVO: tu análisis
│       └── prompts-efectivos.md         ← NUEVO: prompts que usaste
└── .git/                       (con commits descriptivos)
```

### 📄 Package 2: Documentación Académica
- `MASTER-brief.md` (este archivo)
- `ia-productivity-analysis.md` (completado con datos reales)
- `tecnico-thesis.md` (opción: paper corto sobre decisiones)
- `README-DEMO.md` (cómo reproducir la demo)

### 🎬 Package 3: Demo
- Video de 10-15min mostrando:
  1. Loginear
  2. Crear entidad "Cliente"
  3. Crear registro de cliente
  4. Instalar plugin "Optometría"
  5. Crear revisión optométrica
  6. Ver que datos se guardaron

---

## Criterios de Éxito

| Criterio | Cumple | Evidencia |
|----------|--------|-----------|
| **Funcionalidad** | ✅ | CRUD dinámico funciona E2E |
| **Arquitectura** | ✅ | Plugins loadable, hooks ejecutables |
| **Proceso con IA** | ✅ | Análisis de productividad documentado |
| **Documentación** | ✅ | Cada decisión justificada |
| **Reproducible** | ✅ | Docker Compose funciona |
| **Git visible** | ✅ | Commits muestran progreso semana a semana |

---

## Presentación en Clase

### Estructura sugerida (20-30 min)
1. **Problema:** Arquitectura modular en pequeños negocios (5 min)
2. **Solución:** Xestify micro-kernel con plugins (5 min)
3. **Proceso IA:** Cómo usaste IA, qué aceleró, qué no (5 min)
4. **Demo en vivo:** Mostrar flujo completo (5-10 min)
5. **Análisis:** Comparativa sin IA vs con IA (3-5 min)

---

## Métricas de Aceleración a Documentar

Antes de terminar, mide:

```
Tarea: ValidationService
- Estimado sin IA: 5 puntos (40 horas developer típico)
- Tiempo REAL con IA: X horas (documenta)
- Aceleración: X%

Tarea: DynamicForm
- Estimado sin IA: 5 puntos
- Tiempo REAL con IA: X horas
- Aceleración: X%

(... repetir para 10-15 tareas clave)

Factor promedio: Y%
```

Esto es lo que defenderás académicamente.

---

## Cambios frente a MVP "producción"

| Item | MVP Producción | MVP Master |
|------|---|---|
| Scope | Full 10 EPIC | Solo EPIC 0-5 |
| Timeline | 12-24 semanas | 4-5 semanas |
| IA | Accesible | **Primario** |
| Testing | 80% cobertura | Tests críticos |
| Documentación | Operativa | **Académica + técnica** |
| Plugins | Extensiones complejas | Solo entidad base + 1 extensión simple |

---

## Próximo Paso

1. Lee [ia-productivity-template.md](ia-productivity-template.md) para entender qué documentar.
2. Revisa [backlog.md](backlog.md) **versión Master reducida** (40 puntos).
3. **Semana 1:** Empieza EPIC 0 inmediatamente.
4. **Cada semana:** Actualiza [ia-productivity-analysis.md](ia-productivity-analysis.md) con tiempos reales.

---

## Preguntas Frecuentes

**P: ¿Cuántas horas por semana dedicar?**  
R: Full-time (40 horas) es lo ideal. Con IA, espera 30-35 horas efectivas (sin contar interrupciones).

**P: ¿Qué pasa si fallo algo en Semana 2?**  
R: Semana 5 es contingencia. Recorta EPIC 5 (frontend) antes que EPIC 3 (core).

**P: ¿Necesito aprender PHP de cero?**  
R: No. IA genera 80% del código repetitivo. Enfócate en lógica y decisiones.

**P: ¿Cómo demuestro que IA aceleró?**  
R: Commits + tiempos reales vs estimados. Si generaste validador en 2 horas (vs 8 estimado), eso es 75% aceleración.
