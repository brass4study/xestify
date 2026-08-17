# Documentación Xestify

Este índice organiza la documentación técnica para la arquitectura MVC + plugins.

## Documentación MVP (EMPIEZA AQUI)

Decisiones técnicas resueltas y referencia para futuras iteraciones:

- **[09-history/decisiones-tecnicas.md](../09-history/decisiones-tecnicas.md)** — Resumen ejecutivo de las 5 decisiones principales (PHP nativo, Container casero, Vanilla puro, JWT, Schema custom)
- **[09-history/historial-decisiones.md](../09-history/historial-decisiones.md)** — Full context de opciones consideradas por cada decisión (útil si en futuro quieres cambiar algo)
- **[09-history/consideraciones-iniciales.md](../09-history/consideraciones-iniciales.md)** — Guía ejecutiva para implementación: estructura, convenciones, trampas a evitar
- **[11-backlog/backlog.md](../11-backlog/backlog.md)** — Backlog ejecutable desglosado: 100+ historias con estimaciones, criterios de aceptación, dependencias y prioridad MoSCoW
- **[skills/README.md](../../skills/README.md)** — Este proyecto usa Claude Code Agent Skills: automatizaciones locales (auditorías de deuda técnica, revisión de clean code, siembra de datos de demo...) que se disparan por lenguaje natural. Índice completo ahí.

## Orden de lectura recomendado (después de MVP)

1. [00-meta/plan-fundacional-gemini.md](../00-meta/plan-fundacional-gemini.md)
2. [01-architecture/overview.md](../01-architecture/overview.md)
3. [01-architecture/mvc.md](../01-architecture/mvc.md)
4. [01-architecture/plugins.md](../01-architecture/plugins.md)
5. [01-architecture/hooks.md](../01-architecture/hooks.md)
6. [02-entities/postgresql-jsonb.md](../02-entities/postgresql-jsonb.md)
7. [02-entities/versionado-esquemas.md](../02-entities/versionado-esquemas.md)
8. [03-api/especificacion-rest.md](../03-api/especificacion-rest.md)
9. [05-frontend/renderizado-dinamico.md](../05-frontend/renderizado-dinamico.md)
10. [08-operations/deploy-rpi5.md](../08-operations/deploy-rpi5.md)
11. [08-operations/actualizaciones.md](../08-operations/actualizaciones.md)
12. [07-security/modelo-seguridad-local.md](../07-security/modelo-seguridad-local.md)
13. [04-plugins/plantilla-plugin-entidad.md](../04-plugins/plantilla-plugin-entidad.md)
14. [04-plugins/plantilla-plugin-extension.md](../04-plugins/plantilla-plugin-extension.md)

## Objetivo de esta capa documental

- Definir arquitectura técnica sin ambiguedades
- Separar responsabilidades por capas
- Estandarizar desarrollo de plugins
- Definir reglas de operación y seguridad

## Convenciones

- Slugs en minusculas con guion bajo
- Versionado semantico para plugins
- Cambios estructurales guiados por metadata y migraciones
