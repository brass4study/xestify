# Xestify

Xestify es una plataforma web local-first para pequenos negocios, pensada para ejecutarse en una Raspberry Pi 5 dentro de cada empresa. Su enfoque principal es combinar seguridad y soberania de datos con flexibilidad funcional mediante un sistema de plugins.

En lugar de desarrollar una aplicacion distinta para cada rubro, Xestify ofrece un Core estable y agnostico del negocio, que se adapta por configuracion y extensiones. Esto permite que una joyeria, una optica, un taller o una ferreteria usen la misma base de producto, pero con entidades, formularios y flujos distintos.

## Vision del producto

Xestify busca resolver un problema frecuente en pequenos negocios: necesitan software personalizable, pero no quieren complejidad operativa ni depender completamente de la nube.

La propuesta de valor se apoya en cuatro pilares:

- Local-first real: datos y operacion principal en la sede del negocio.
- Arquitectura modular: nuevas capacidades sin tocar el Core.
- Evolucion controlada: actualizaciones periodicas de plugins y del sistema.
- Reutilizacion transversal: entidades base reutilizables entre verticales de negocio.

## Como funciona

El sistema se divide en dos capas funcionales:

1. Core
- Gestiona autenticacion, autorizacion, API, motor de entidades dinamicas, plugins y hooks.

2. Plugins
- Plugins de entidad: definen entidades base (por ejemplo, clientes o productos).
- Plugins de extension: se acoplan a una entidad base para ampliar su comportamiento (por ejemplo, optometrias sobre cliente).

Con este enfoque, una misma entidad base puede usarse en varios sectores con distintos campos y extensiones, sin duplicar codigo estructural.

## Arquitectura tecnica (resumen)

- Backend: PHP orientado a API REST.
- Frontend: JavaScript con renderizado dinamico por metadata.
- Persistencia: PostgreSQL con modelo hibrido relacional + JSONB.
- Extension: sistema de plugins y hooks por eventos.
- Operacion: despliegue en contenedores para Raspberry Pi 5.
- Distribucion funcional: tienda/repositorio central de plugins.

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

- Instalacion
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

MVP implementado hasta **STORY 6.4 incluida**:

- Login JWT y rutas API protegidas por `AuthMiddleware`.
- CRUD dinamico de entidades sobre `plugin_entity_data`.
- Catalogo de entidades basado en plugins `entity` activos en la tabla `plugins`.
- Plugin `clients` como entidad base canonica.
- Plugin `comments` como extension con tab "Comentarios" y datos en `plugin_extension_data`.
- Tests backend agrupados con `php backend/tests/run.php unit|integration-db|integration-plugins|all`.

Pendiente desde STORY 6.5: pagina PluginManager, activacion/desactivacion desde UI, configuracion de plugins, updates/rollback, operacion avanzada, auditoria, permisos finos y marketplace.

## Documentacion del proyecto

Indice principal: [docs/README.md](docs/README.md)

Documentos clave:

- [docs/roadmap.md](docs/roadmap.md)
- [docs/arquitectura/overview.md](docs/arquitectura/overview.md)
- [docs/arquitectura/mvc.md](docs/arquitectura/mvc.md)
- [docs/arquitectura/plugins.md](docs/arquitectura/plugins.md)
- [docs/arquitectura/hooks.md](docs/arquitectura/hooks.md)
- [docs/datos/postgresql-jsonb.md](docs/datos/postgresql-jsonb.md)
- [docs/api/especificacion-rest.md](docs/api/especificacion-rest.md)
- [docs/frontend/renderizado-dinamico.md](docs/frontend/renderizado-dinamico.md)
- [docs/operacion/deploy-rpi5.md](docs/operacion/deploy-rpi5.md)
- [docs/operacion/actualizaciones.md](docs/operacion/actualizaciones.md)
- [docs/seguridad/modelo-seguridad-local.md](docs/seguridad/modelo-seguridad-local.md)

## Roadmap resumido

1. Implementar Core MVC backend.
2. Implementar motor de metadata y CRUD dinamico.
3. Implementar PluginLoader y HookDispatcher.
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
