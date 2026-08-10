# Contexto documental Xestify

Esta carpeta centraliza toda la documentación estructurada del proyecto, organizada por flujo de lectura recomendado y numerada para facilitar el onboarding, la consulta asistida por IA y la evolución del sistema.

---

## Estado actual y referencias clave

- **Corte funcional:** STORY 9.5 incluida; EPIC 9 en progreso y siguiente foco STORY 9.6 ([ver backlog](11-backlog/backlog.md))
- **Catálogo de entidades:** gestionado solo por la tabla `plugins` (`plugin_type = 'entity'`)
- **Decisiones técnicas:** [09-history/decisiones-tecnicas.md](09-history/decisiones-tecnicas.md)
- **Productividad y flujo IA:** [10-productivity/README.md](10-productivity/README.md)

---

## Tabla de contenidos

1. [00-meta](00-meta/README.md) — Visión, convenciones y glosario
2. [01-architecture](01-architecture/README.md) — Arquitectura, patrones y extensibilidad
3. [02-entities](02-entities/README.md) — Modelo de datos y versionado de entidades
4. [03-api](03-api/README.md) — Especificación y contratos de la API REST
5. [04-plugins](04-plugins/README.md) — Plantillas y desarrollo de plugins/extensiones
6. [05-frontend](05-frontend/README.md) — UI dinámica y componentes frontend
7. [06-backend](06-backend/README.md) — Backend PHP, responsabilidades y referencias técnicas
8. [07-security](07-security/README.md) — Seguridad y modelo local
9. [08-operations](08-operations/README.md) — Despliegue, actualizaciones y operación
10. [09-history](09-history/README.md) — Historial de decisiones y migraciones
11. [10-productivity](10-productivity/README.md) — Productividad, IA y prompts
12. [11-backlog](11-backlog/README.md) — Backlog, roadmap y estado del MVP

---

Cada subcarpeta contiene un README/index con índice y guía de navegación propia.

> Sigue este orden para comprender el proyecto de forma progresiva y estructurada.
