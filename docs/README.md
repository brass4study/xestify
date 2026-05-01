# Documentacion Xestify

Este indice organiza la documentacion tecnica para la arquitectura MVC + plugins.

## Documentacion MVP (EMPIEZA AQUI)

Decisiones tecnicas resueltas y referencia para futuras iteraciones:

- **[mvp/decisiones-tecnicas.md](mvp/decisiones-tecnicas.md)** — Resumen ejecutivo de las 5 decisiones principales (PHP nativo, Container casero, Vanilla puro, JWT, Schema custom)
- **[mvp/historial-decisiones.md](mvp/historial-decisiones.md)** — Full context de opciones consideradas por cada decision (útil si en futuro quieres cambiar algo)
- **[mvp/consideraciones-iniciales.md](mvp/consideraciones-iniciales.md)** — Guía ejecutiva para implementación: estructura, convenciones, trampas a evitar
- **[mvp/backlog.md](mvp/backlog.md)** — Backlog ejecutable desglosado: 100+ historias con estimaciones, criterios de aceptación, dependencias y prioridad MoSCoW

## Orden de lectura recomendado (después de MVP)

1. [inicial.md](inicial.md)
2. [arquitectura/overview.md](arquitectura/overview.md)
3. [arquitectura/mvc.md](arquitectura/mvc.md)
4. [arquitectura/plugins.md](arquitectura/plugins.md)
5. [arquitectura/hooks.md](arquitectura/hooks.md)
6. [datos/postgresql-jsonb.md](datos/postgresql-jsonb.md)
7. [datos/versionado-esquemas.md](datos/versionado-esquemas.md)
8. [api/especificacion-rest.md](api/especificacion-rest.md)
9. [frontend/renderizado-dinamico.md](frontend/renderizado-dinamico.md)
10. [operacion/deploy-rpi5.md](operacion/deploy-rpi5.md)
11. [operacion/actualizaciones.md](operacion/actualizaciones.md)
12. [seguridad/modelo-seguridad-local.md](seguridad/modelo-seguridad-local.md)
13. [plugins/plantilla-plugin-entidad.md](plugins/plantilla-plugin-entidad.md)
14. [plugins/plantilla-plugin-extension.md](plugins/plantilla-plugin-extension.md)

## Objetivo de esta capa documental

- Definir arquitectura tecnica sin ambiguedades
- Separar responsabilidades por capas
- Estandarizar desarrollo de plugins
- Definir reglas de operacion y seguridad

## Convenciones

- Slugs en minusculas con guion bajo
- Versionado semantico para plugins
- Cambios estructurales guiados por metadata y migraciones
