# STORY 9.2 - Fundamentos de navegacion y anatomia de paginas

## Objetivo

Definir el contrato base de navegacion SPA y la anatomia objetivo de paginas para que
las siguientes stories del EPIC 9 construyan componentes, shell y router sobre un
mapa consistente, sin volver a hardcodear rutas o jerarquias de contenido en cada vista.

## Fuente de verdad

- El mapa de rutas hash y los identificadores internos de pagina viven en `frontend/src/js/controllers/RouteMapController.js`, con el soporte especifico de plugins en `frontend/src/js/controllers/PluginRouteController.js`.
- La app sigue usando hash routing como convencion oficial: `#/ruta`.
- Las vistas trabajan con identificadores internos (`profile`, `users:<id>`, `entity:<slug>`) y el modulo de rutas traduce entre ese estado y la URL visible.

## Arquitectura de informacion

### Areas principales

- `home`: alias de entrada reservado para una futura pagina de inicio; mientras no exista, redirige a la primera entidad activa.
- `operations`: entidades de negocio y sus registros.
- `system`: administracion del sistema, plugins y usuarios.
- `account`: perfil y preferencias del usuario autenticado.
- `feedback`: estados de resultado, vacio o error.

### Navegacion principal

- Navbar superior persistente con acceso a entidades activas.
- Entradas administrativas visibles solo para administradores: plugins y usuarios.
- Menu de usuario desacoplado de la navbar para acciones de cuenta (`Mi perfil`, `Cerrar sesion`).

### Breadcrumbs objetivo

- Home: sin breadcrumb o con un unico nivel de contexto.
- List pages: `Area > Recurso`.
- Detail/form pages: `Area > Recurso > Registro`.
- Plugin config: `Sistema > Plugins > Configuracion`.
- Result states: `Area > Estado` cuando el contexto exista; si no, pantalla aislada.

Estos breadcrumbs forman parte del contrato base definido en STORY 9.2. La
implementacion visual reusable ya se materializa desde STORY 9.5 mediante
`PageLayout` y el componente `Breadcrumb`, usando esta jerarquia como guia de
render dentro del shell SPA.

## Mapa de rutas hash

### Rutas actuales del MVP

| Vista | Hash | Template objetivo |
|------|------|-------------------|
| Login | `#/login` | `login` |
| Inicio (alias temporal) | `#/home` y `#/` | Redireccion a `#/entity/:slug` |
| Mi perfil | `#/profile` | `detail` |
| Gestion de usuarios | `#/users` | `list` |
| Ficha de usuario | `#/users/:id` | `detail` |
| PluginManager | `#/plugins` | `plugin-management` |
| Configuracion de plugin | `#/plugins/:slug` | `plugin-management` |
| Listado de entidad | `#/entity/:slug` | `list` |
| Alta de ítem de plugin de extensión | `#/entity/:slug/:id/:tab/#new` | `plugin-item-edit` |
| Ficha de ítem de plugin de extensión | `#/entity/:slug/:id/:tab/:itemId` | `plugin-item-edit` |

Las dos últimas (STORY 10.5) navegan **siempre reconstruyendo la página**
— a diferencia de un cambio de tab normal dentro de `EntityEdit` (que
reutiliza la instancia sin remontar), ir a la ficha de un ítem de plugin
instancia `PluginItemEdit.js` de nuevo. Solo aplican a plugins `extension`
con historial de varios registros por owner (ej. `optometries`,
`contact_lenses`) — un plugin con panel inline simple (ej. `comments`) no
las usa.

### Rutas reservadas para stories siguientes

| Vista | Hash | Uso previsto |
|------|------|--------------|
| Alta de registro | `#/entity/:slug/new` | Formulario en modo creacion |
| Edicion de registro | `#/entity/:slug/:id` | Formulario/detalle de un registro |
| Tab de registro | `#/entity/:slug/:id/:tab` | Datos (`data`) y extensiones por plugins |
| Estado vacio | `#/result/empty` | Pantalla reusable sin datos |
| Estado error | `#/result/error` | Error recuperable con CTA |
| Estado prohibido | `#/resultado/403` | Acceso denegado |

Al seleccionar una pestaña en la edicion de un registro, `EntityEdit` debe navegar
mediante el router a su subruta. La pestaña base usa el id tecnico `data`; las
extensiones usan su slug, por ejemplo `comments`. Esto mantiene entrada directa,
refresh y back/forward sincronizados con la pestaña visible.

Los cambios entre tabs del mismo registro no deben reconstruir la pagina. El
router actualiza el historial sin volver a despachar `EntityEdit`; la instancia
activa cambia solo el panel y los breadcrumbs. El formulario y los paneles de
plugins se crean una vez, quedan precargados y conservan su estado sin guardar.

## Plantillas objetivo de pagina

### `login`

- Layout centrado, mensaje de valor corto, formulario y feedback inmediato.
- Sin navbar principal.

### `home`

- Plantilla reservada para una futura vista de entrada con resumen, accesos rapidos y bloques de contexto.
- Hasta que esa vista exista, el router reemplaza `#/home`, `#/` o un hash vacio por la ruta de la primera entidad activa del menu.
- Si no hay entidades activas, el fallback administrativo es `#/plugins`; para usuarios sin acceso administrativo se mantiene el estado vacio de `home`.

### `list`

- Cabecera con titulo, descripcion, acciones primarias y filtros/contexto.
- Cuerpo con tabla o estado vacio.

### `detail`

- Cabecera con breadcrumb, titulo, subtitulo y acciones secundarias.
- Cuerpo con formulario, tabs o paneles laterales.

### `plugin-management`

- Cabecera administrativa, alertas de estado y bloques de acciones por plugin.
- Debe admitir config, sync, update y rollback sin layouts paralelos.

### `result-empty`, `result-error`, `result-forbidden`

- Mensaje principal, contexto corto, accion recomendada y posible retorno.
- El contenido debe ser reutilizable y sin dependencia del modulo origen.

## Convenciones de copy preparadas para i18n

- Separar la intencion del texto de su literal: usar claves como `users.list.title` o `plugins.management.description` en contratos y componentes futuros.
- Mantener estructura consistente por pagina: `eyebrow` opcional, `title`, `description`, `primaryAction`, `secondaryActions`, `emptyState`, `errorState`.
- Evitar decisiones de idioma incrustadas en nombres tecnicos, props o rutas.
- Usar frases breves y accionables; los mensajes de error deben describir el problema y la siguiente accion posible.
- Reservar terminologia tecnica estable en ingles para claves y payloads, y dejar el idioma visible a la capa de presentacion.

Estas convenciones no externalizan textos todavia; solo dejan el contrato y la
estructura listos para que STORY 9.7 aplique i18n y theming sin rehacer la
anatomia de paginas definida aqui.

## Estado de implementacion y stories siguientes

- STORY 9.3 implementó los componentes `Page`, `PageHeader`, `Breadcrumb`, `Empty` y `Alert` sobre estas plantillas.
- STORY 9.5 montó el shell SPA y los layouts de página usando estas áreas y jerarquías.
- STORY 9.6 formalizó el router sobre el mapa centralizado en `RouteMapController.js` y `PluginRouteController.js`, con navegación programática, entrada directa, refresh y back/forward.
- STORY 9.7 debe consolidar estado transversal, resiliencia, i18n y theming sobre este contrato de navegación ya cerrado.