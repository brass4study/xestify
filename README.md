# Xestify

Xestify es una plataforma web local-first para pequeños negocios, pensada para ejecutarse en una Raspberry Pi 5 dentro de cada empresa. Su enfoque principal es combinar seguridad y soberanía de datos con flexibilidad funcional mediante un sistema de plugins.

---

## Estado actual del proyecto (MVP)

- **Corte funcional:** STORY 7.2 incluida (ver [backlog](docs/11-backlog/backlog.md))
- **Catálogo de entidades:** gestionado exclusivamente por la tabla `plugins` (`plugin_type = 'entity'`)
- **Arquitectura:** Core minimalista, extensible solo mediante plugins
- **Seguridad:** Pipeline protegido, autenticación JWT, roles mínimos y validación server-side
- **Frontend:** UI dinámica basada en metadata y plugins
- **Operación:** Apache+PHP en un solo origen, despliegue local en RPi5 y actualizaciones controladas

Para detalles de decisiones técnicas y cambios históricos, consulta [docs/09-history/decisiones-tecnicas.md](docs/09-history/decisiones-tecnicas.md).

---

En lugar de desarrollar una aplicacion distinta para cada rubro, Xestify ofrece un Core estable y agnostico del negocio, que se adapta por configuracion y extensiones. Esto permite que una joyeria, una optica, un taller o una ferreteria usen la misma base de producto, pero con entidades, formularios y flujos distintos.

## Visión del producto

Xestify busca resolver un problema frecuente en pequenos negocios: necesitan software personalizable, pero no quieren complejidad operativa ni depender completamente de la nube.

La propuesta de valor se apoya en cuatro pilares:

- Local-first real: datos y operacion principal en la sede del negocio.
- Arquitectura modular: nuevas capacidades sin tocar el Core.
- Evolucion controlada: actualizaciones periodicas de plugins y del sistema.
- Reutilizacion transversal: entidades base reutilizables entre verticales de negocio.

## ¿Cómo funciona?


El sistema se divide en dos capas funcionales:

1. Core
- Gestiona autenticacion, autorizacion, API, motor de entidades dinamicas, plugins y hooks.

2. Plugins
- Plugins de entidad: definen entidades base (por ejemplo, clientes o productos).
- Plugins de extension: se acoplan a una entidad base para ampliar su comportamiento (por ejemplo, optometrias sobre cliente).

Con este enfoque, una misma entidad base puede usarse en varios sectores con distintos campos y extensiones, sin duplicar codigo estructural.

## Arquitectura técnica (resumen)

- Backend: PHP orientado a API REST ([docs/01-architecture/overview.md](docs/01-architecture/overview.md))
- Frontend: JavaScript con renderizado dinámico por metadata ([docs/05-frontend/README.md](docs/05-frontend/README.md))
- Persistencia: PostgreSQL con modelo híbrido relacional + JSONB ([docs/02-entities/README.md](docs/02-entities/README.md))
- Extensión: sistema de plugins y hooks por eventos ([docs/04-plugins/README.md](docs/04-plugins/README.md))
- Operacion: Apache+PHP como runtime canonico y despliegue local en Raspberry Pi 5 ([docs/08-operations/README.md](docs/08-operations/README.md))
- Distribución funcional: tienda/repositorio central de plugins

## Runtime web y desarrollo local

Xestify se sirve ahora bajo un unico origen con Apache+PHP:

- `/` entrega la shell frontend (`frontend/src/index.html`)
- `/css/*` y `/js/*` sirven los estaticos del frontend
- `/api/*` y `/health` entran por `backend/public/index.php`
- `/plugins/*` sirve `plugin.js` y assets de plugins

La aplicacion detecta su `base path` en runtime, asi que puede colgar tanto de
la raiz del host como de una subruta Apache, por ejemplo
`http://localhost/xestify/`.

En desarrollo, los tests HTML del frontend pueden exponerse bajo `/tests/*`
activando `ENABLE_TEST=1` en Apache. La configuracion de referencia vive en
[docs/08-operations/apache-vhost-examples.md](docs/08-operations/apache-vhost-examples.md).

## Modelo de datos

Cada empresa opera con su propia base de datos. El modelo combina:

- Tablas Core para control estructural (entidades instaladas, metadata, registro de plugins, hooks).
- JSONB para campos variables y evolucion dinamica de esquemas.

Esto evita cambios destructivos frecuentes en tablas fisicas y permite que un plugin agregue campos o capacidades sin redisenar toda la base.

## Sistema de plugins

Cada plugin tiene una estructura estandar con metadatos y esquema declarativo. En terminos generales, incluye:

- Manifest con identificacion, version y compatibilidad.
- Schema con definicion de campos y reglas.
- Logica de hooks para integracion con el Core.
- Componentes de API/UI cuando aplica.

El sistema soporta ciclo de vida de plugin:

- Instalación

---

## Navegación rápida

- [Visión, convenciones y glosario](docs/00-meta/README.md)
- [Arquitectura y patrones](docs/01-architecture/README.md)
- [Modelo de datos y entidades](docs/02-entities/README.md)
- [API REST](docs/03-api/README.md)
- [Plugins y extensiones](docs/04-plugins/README.md)
- [Frontend y UI dinámica](docs/05-frontend/README.md)
- [Seguridad](docs/07-security/README.md)
- [Operaciones y despliegue](docs/08-operations/README.md)
- [Historial de decisiones](docs/09-history/README.md)
- [Productividad y flujo IA](docs/10-productivity/README.md)
- [Backlog y roadmap](docs/11-backlog/README.md)

---
- Activacion
- Actualizacion
- Desactivacion
- Desinstalacion

## Sistema de hooks

Los hooks permiten extender comportamiento sin modificar el nucleo. Se contemplan tres grupos:

- Hooks de ciclo de vida de plugin.
- Hooks de entidad (before/after en validacion, guardado y borrado).
- Hooks de UI (tabs, acciones, widgets).

Este mecanismo habilita casos como agregar pestanas y acciones personalizadas en la ficha de una entidad base.

## Actualizaciones y mantenimiento

Xestify soporta dos modos de actualizacion:

- Manual: el usuario revisa e instala actualizaciones desde el panel.
- Automatica: un proceso programado consulta versiones y prepara paquetes.

Flujo de actualizacion recomendado:

1. Consultar versiones disponibles.
2. Descargar paquete a staging.
3. Verificar integridad y compatibilidad.
4. Ejecutar migraciones de metadata/datos.
5. Activar nueva version.
6. Registrar resultado y permitir rollback.

## Seguridad

Como plataforma local de mision critica para negocio, Xestify prioriza:

- Menor privilegio por rol y accion.
- Validacion server-side obligatoria.
- Consultas SQL parametrizadas.
- Auditoria de operaciones sensibles.
- Control de procedencia e integridad de plugins.

## Casos de uso objetivo

- Gestion de clientes con campos configurables por negocio.
- Gestion de productos e inventario con metadatos propios.
- Extensiones verticales por sector (ejemplo: optometria en optica).
- Evolucion funcional por instalacion de plugins adicionales.

## Estado actual

MVP implementado hasta **STORY 7.2 incluida**:

- Login JWT y rutas API protegidas por `AuthMiddleware`.
- CRUD dinamico de entidades sobre `plugin_entity_data`.
- Catalogo de entidades basado en plugins `entity` activos en la tabla `plugins`.
- Plugin `clients` como entidad base canonica.
- Plugin `comments` como extension con tab "Comentarios" y datos en `plugin_extension_data`.
- PluginManager, deteccion de actualizaciones disponibles y flujo explicito de
  sync/update desde servicios especializados del subsistema de plugins.
- Tests backend agrupados con `php backend/tests/run.php unit|integration-db|integration-plugins|all`.

Pendiente desde STORY 7.3: configuracion avanzada de plugins,
sincronizacion explicita de plugins desde UI, rollback, operacion avanzada,
auditoria, permisos finos y marketplace.

Operaciones manuales de setup:

- `php tools/setup/seed-admin-user.php`: crea el admin inicial si la tabla `users` esta vacia
- `php tools/setup/sync-plugins.php`: registra plugins nuevos y detecta updates disponibles sin consumir la version/schema runtime de plugins ya instalados

Estas operaciones ya no se ejecutan en cada request. El runtime normal carga
plugins y hooks desde la base de datos; la sincronizacion disco -> BD es
explicita.

En Windows, si usas el `php.exe` de Apache y su configuracion esta en
`C:\apache2.4.66\config\php.ini`, ejecuta esos scripts con:

```powershell
C:\apache2.4.66\php\php.exe -c C:\apache2.4.66\config\php.ini tools/setup/sync-plugins.php
```

Para evitar latencia innecesaria en local con Apache+PHP:

- usar `DB_HOST=127.0.0.1` en vez de `localhost`
- dejar `xdebug.start_with_request = trigger` cuando no se este depurando

## Documentación del proyecto

Indice principal: [docs/README.md](docs/README.md)

Documentos clave:

- [docs/11-backlog/backlog.md](docs/11-backlog/backlog.md)
- [docs/01-architecture/overview.md](docs/01-architecture/overview.md)
- [docs/01-architecture/plugins.md](docs/01-architecture/plugins.md)
- [docs/01-architecture/hooks.md](docs/01-architecture/hooks.md)
- [docs/02-entities/README.md](docs/02-entities/README.md)
- [docs/03-api/endpoints.md](docs/03-api/endpoints.md)
- [docs/05-frontend/README.md](docs/05-frontend/README.md)
- [docs/08-operations/deploy-rpi5.md](docs/08-operations/deploy-rpi5.md)
- [docs/08-operations/apache-vhost-examples.md](docs/08-operations/apache-vhost-examples.md)
- [docs/07-security/README.md](docs/07-security/README.md)

## Roadmap resumido

1. Implementar Core MVC backend.
2. Implementar motor de metadata y CRUD dinamico.
3. Implementar subsistema de plugins y HookDispatcher.
4. Implementar frontend dinamico (formularios, tablas, tabs).
5. Implementar sistema de actualizaciones y rollback.
6. Integrar marketplace de plugins y ciclo de versionado.

## Alcance inicial (MVP)

- Entidades dinamicas con schema declarativo.
- CRUD generico validado por metadata.
- Carga de plugins de entidad.
- Primer flujo de plugin de extension sobre entidad base.
- Actualizacion de plugin con registro de ejecucion.

## Futuro

- Plantillas de verticales de negocio por sector.
- Herramientas de backup y restauracion guiada.
- Mayor automatizacion de despliegue y monitoreo en RPi5.
- Hardening avanzado de cadena de suministro de plugins.
