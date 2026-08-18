# Xestify

Xestify es una plataforma web local-first para pequeños negocios, pensada para ejecutarse en una Raspberry Pi 5 dentro de cada empresa. Su enfoque principal es combinar seguridad y soberanía de datos con flexibilidad funcional mediante un sistema de plugins.

---

## Estado actual del proyecto (MVP)

- **Corte funcional:** EPIC 9 y EPIC 10 cerradas al completo (STORY 10.1-10.6); STORY 11.1 (auditoría de código limpio) y STORY 11.2 (verificación funcional E2E final) completadas; siguiente foco STORY 11.3 (`EPIC 11`, auditoría de coherencia de documentación) (ver [backlog](docs/11-backlog/backlog.md))
- **Catálogo de entidades:** gestionado exclusivamente por la tabla `plugins` (`manifest_json->>'type' = 'entity'`)
- **Arquitectura:** Core minimalista, extensible solo mediante plugins
- **Seguridad:** Pipeline protegido, autenticación JWT, roles mínimos, validación server-side y usuarios seed protegidos frente a edición/borrado/autoservicio
- **Frontend:** SPA MVC con shell persistente, layouts reutilizables, routing hash bidireccional, feedback global, base de i18n, theming visual persistido por cliente y suite E2E Playwright contra el runtime real
- **Operación:** Apache+PHP en un solo origen, despliegue local en RPi5 y actualizaciones controladas
- **Estado actual del MVP:** la base funcional del producto está consolidada, la capa transversal de frontend está implementada para notificaciones, errores amigables, confirmaciones modales y preferencias visuales compartidas, la pantalla de login quedó rediseñada (identidad visual, validación, accesos rápidos de desarrollo) tras STORY 10.1, los plugins de demostración de entidad (`orders`, `sales`, `invoices`, `basic`) ya cubren el primer uso real end-to-end de las relaciones `belongs_to` del schema tras STORY 10.4, los plugins de demostración de extensión (`optometries`, `contact_lenses`) añaden historial de fichas con relaciones propias, validación server-side y la convención `layers` tras STORY 10.5, y tras STORY 10.6 la base de datos de demostración puede poblarse con un seeder de negocio idempotente (`php tools/setup/seed-business-data.php`, ver [skills/seed-business-data](skills/seed-business-data/SKILL.md)) con ~2500 registros coherentes entre sí (clientes, distribuidores, oftalmólogos, marcas, fabricantes, pedidos, ventas, facturas, fichas clínicas y comentarios)

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
- Estilos UI: Tailwind CSS generado localmente desde `frontend/tailwind.config.cjs` y `frontend/src/css/tailwind.src.css`
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

- [Guía de instalación](INSTALL.md)
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

MVP implementado hasta **STORY 11.2 incluida** (EPIC 9 y EPIC 10 cerradas al completo; EPIC 11 en progreso):

- Login JWT y rutas API protegidas por `AuthMiddleware`.
- CRUD dinámico de entidades sobre `plugin_entity_data`.
- Catálogo de entidades basado en plugins `entity` activos en la tabla `plugins`.
- Plugin `persons` como entidad base canónica (clientes/distribuidores/oculistas).
- Plugin `comments` como extensión con tab "Comentarios" y datos en `plugin_extension_data`.
- PluginManager, detección de actualizaciones disponibles y flujo explícito de sync/update desde servicios especializados del subsistema de plugins.
- Página de configuración de plugins activos con identidad editable (slug/nombre/descripción), campos configurables, grid de relaciones `belongs_to` editable y soporte de `target_entity` para plugins `extension`.
- Gestión de perfil propio y administración de usuarios con rutas hash `#/profile`, `#/users` y `#/users/:id`.
- Base visual frontend consolidada: tablas unificadas vía `DynamicTable`, tabs alineadas con patrón Ant Design y hoja Tailwind generada localmente sin CDN runtime.
- Frontend organizado bajo MVC estricto, con `ShellLayout` persistente para páginas autenticadas y `PageLayout`, `ListLayout` y `FormLayout` como plantillas reutilizables.
- Routing SPA hash completo con navegación programática, entrada directa, refresh y back/forward preservando el contexto de vistas parametrizadas.
- Infraestructura transversal de frontend implementada con estado global ampliado, feedback compartido, notificaciones/error handling, confirmaciones modales, base de i18n y preferencias visuales persistidas por cliente.
- Estados unificados de loading/vacío/error/éxito, confirmaciones consistentes en acciones sensibles, prevención de doble submit y foco/accesibilidad básica en modales y notificaciones (STORY 9.8).
- Suite de tests reorganizada en `frontend/tests/integration/` (componente/integración con `fetch` mockeado) y `frontend/tests/e2e/` (Playwright contra el runtime real Apache+PHP+Postgres) (STORY 9.9).
- Login rediseñado: identidad visual propia (`Logo`/`BrandLogo`/`Loader`), zona de feedback unificada con validación cliente y foco automático, interceptor centralizado de sesión caducada, accesos rápidos de desarrollo condicionados a `APP_DEBUG`, y dos usuarios seed (admin/normal) protegidos frente a edición, borrado y autoservicio (STORY 10.1).
- Plugin `clients` renombrado a `persons` (carpeta, namespace PHP, manifest/schema y datos existentes), generalizando el modelo a clientes/distribuidores/oculistas sin ampliar sus campos (STORY 10.2).
- `plugin_name` (identidad técnica fija = carpeta) desacoplado de `slug` (editable); tabla `plugins` consolidada en una columna `manifest_json` viva que refleja el manifest.json real en disco, eliminando columnas redundantes y `schema_version` (residual); alta manual de plugin (activo por defecto), borrado en cascada, grid de relaciones `belongs_to` editable y tab automática de relación inversa en `EntityEdit` (STORY 10.3).
- Plugins de demostración de entidad `orders` (pedidos), `invoices` (facturas, `belongs_to orders` obligatorio con `invoice_number` único) y `basic` (plantilla mínima solo con `name`, sin activar); primer uso real end-to-end del bloque `relations` del schema. La relación `orders → persons` no se fija en el schema de disco: se configura por instalación desde el grid "Relaciones" de `PluginConfig` (STORY 10.4).
- Plugins de demostración de extensión `optometries` (ficha de graduación óptica) y `contact_lenses` (ficha de adaptación de lentillas), ambos con historial de varias fichas por persona, relaciones `belongs_to` propias hacia catálogos reales (`ophthalmologists`, `distributors`, `brands`, `manufacturers`), gauge visual del eje (`AxisGauge`, SVG compartido) y tabla de medidas por ojo (`DynamicTable`); página independiente de ficha (`PluginItemEdit.js`) en vez de formulario inline. Ampliaron capacidades de núcleo: `relations` en plugins `extension` (antes solo en `entity`), validación server-side de `content` contra schema, y la convención general `layers`/`resortable` para organizar la UI de cualquier plugin (STORY 10.5).
- Seeder de datos de negocio idempotente (`BusinessDataSeeder`, `php tools/setup/seed-business-data.php`) para poblar una demo en vivo realista: 200 `clients`, 25 `distributors`, 100 `ophthalmologists`, 30 `brands`, 15 `manufacturers`, 300 `orders` a distribuidor, ~270 `invoices`, 250 `sales` a cliente, fichas `optometries`/`contact_lenses` al 100% de los clientes (con correlación de actividad entre clientes) y `comments`; idempotencia "todo o nada por grupo" (STORY 10.6).
- Auditoría de código limpio: pase de SonarQube sobre backend y frontend (38→0 hallazgos pendientes, sin críticos/bloqueantes), sin código muerto ni TODOs obsoletos pendientes, `docs/09-history/decisiones-tecnicas.md` sin decisiones superadas marcadas como vigentes, sin rastro de `clients` que debiera ser `persons`, y naming técnico consistente en inglés (`AGENTS.md` corregido: `mail` en vez de `email`, claves reales de `persons`) (STORY 11.1).
- Verificación funcional E2E final: checklist de flujos críticos (login normal/admin/accesos rápidos, CRUD completo de persona incluido borrado, pedido+factura relacionados, gestión de plugins activar/desactivar/desinstalar, fichas de optometría/lentillas) cubierto por la suite Playwright completa contra el runtime Apache+PHP real; exportar CSV, búsqueda en tablas y selector de idioma visible quedan fuera de alcance del MVP por diseño (reservados a EPIC A1 post-MVP). Corrigió al pasar tres bugs reales de la aplicación (dos variantes de la misma condición de carrera en navegación tras guardar o cambiar de entidad, y un bug de posicionamiento del selector de relaciones fuera del viewport en formularios cortos), cada uno con su propio test de regresión dedicado (STORY 11.2).
- Tests backend agrupados con `php backend/tests/run.php unit|integration-db|integration-plugins|all` (72 archivos) y suite E2E Playwright de 8 specs/21 tests contra el runtime real, además de las suites frontend HTML para gestión de usuarios, perfil, tema, resiliencia, login y plugins.

Pendiente tras STORY 11.2: auditoría de coherencia de documentación y guion de defensa del TFM (EPIC 11).

Operaciones de setup (CLI; guía completa en [INSTALL.md](INSTALL.md)):

- `php tools/setup/install.php`: instalación completa e idempotente — requisitos, `backend/.env`, rol/BD opcional (`--create-db`), esquema base (`backend/database/schema/`), administrador real, usuarios seed solo en debug, sincronización de plugins y datos demo opcionales
- `php tools/setup/create-admin-user.php`: crea un administrador real (`is_seed=false`) en una instalación existente (la app aún no tiene alta de usuarios desde la UI, ver STORY A1.8)
- `php tools/setup/check-install.php --url=...`: comprobación post-instalación de que las rutas públicas responden y ninguna ruta interna (`tools/`, `backend/.env`, PHP/JSON de plugins, docs) se sirve por web
- `php tools/setup/seed-admin-user.php`: crea los usuarios seed de demostración (`admin@xestify.local` / `usuario@xestify.local`, solo pueden iniciar sesión con `APP_DEBUG=true`)
- `php tools/setup/sync-plugins.php`: registra plugins nuevos y detecta updates disponibles sin consumir la version/schema runtime de plugins ya instalados
- `php tools/setup/seed-business-data.php`: siembra datos de negocio de demostración de forma idempotente (ver [skills/seed-business-data](skills/seed-business-data/SKILL.md))

Estas operaciones ya no se ejecutan en cada request. El runtime normal carga
plugins y hooks desde la base de datos; la sincronizacion disco -> BD es
explicita. Todos los scripts de `tools/` son exclusivamente de línea de
comandos: `tools/setup/bootstrap.php` rechaza cualquier otro SAPI, y los
`.htaccess` por directorio impiden servirlos por web (ver "Modelo de seguridad
de la instalación" en `INSTALL.md`).

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

## 🔒 Licencia y Términos de Uso

Este proyecto es el resultado de un Trabajo de Fin de Máster (TFM) y se publica bajo una **Licencia Propietaria de Fuente Disponible (Source-Available / Read-Only)**.

* **Permitido:** Consulta visual, lectura y auditoría del código fuente con fines académicos o de evaluación.
* **Estrictamente Prohibido:** La ejecución, compilación, despliegue, modificación, redistribución o uso del software, ya sea para **fines comerciales o personales**, sin la autorización previa y por escrito del autor.

Para más detalles, consulta el archivo completo [`LICENSE`](./LICENSE.md).

---
*Si deseas obtener una licencia de uso comercial o evaluación extendida, ponte en contacto en: brass4study@gmail.com*