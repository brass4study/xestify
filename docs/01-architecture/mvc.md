# Arquitectura MVC en Xestify

## Objetivo

Aplicar MVC en backend PHP y frontend JS sin acoplar la UI del negocio al servidor.

## Distribucion de responsabilidades

## Model

Representa datos core y metadata.

- El catálogo de entidades se lee directamente de `plugins WHERE manifest_json->>'type' = 'entity' AND status = 'active'`
  (`manifest_json` reemplaza a las antiguas columnas `plugin_type`/`plugin_name`/`version` — STORY 10.3 §2bis). No existe un
  modelo `SystemEntity` (fue eliminado por completo, no queda como facade) ni la tabla `system_entities`.
- EntityMetadata
- EntityData
- PluginRegistry
- PluginHookRegistry
- User

Responsabilidades:

- Acceso a BD
- Reglas de integridad
- Operaciones CRUD base

## Controller

Expone endpoints API y orquesta servicios.

- EntityController — expone además `ReverseRelationTabResolver` (STORY 10.3 §9):
  `GET /entities/{slug}/tabs` mezcla las tabs registradas por plugins con las tabs
  automáticas de "relación inversa" cuando otra entidad declara una relación
  `belongs_to` hacia esta.
- PluginManagerController
- AuthController
- UpdateController

Responsabilidades:

- Parseo request/response
- Invocacion de servicios
- Manejo de errores HTTP

## View

La vista real se implementa en frontend JS consumiendo API.

- DynamicForm
- DynamicTable
- EntityDetail
- DynamicTabs

Responsabilidades:

- Renderizado por metadata
- Reaccion a hooks UI
- Validaciones de experiencia de usuario

## MVC estricto en frontend

El frontend tambien sigue MVC estricto dentro de `frontend/src/js`. El archivo
raiz `app.js` es un bootstrap tecnico minimo: localiza `#app`, instancia
`AppController` y le delega el arranque, sin contener logica de aplicacion.

- `controllers/`: arranque de aplicacion, router hash, traduccion entre hash e
	identificadores internos de pagina y orquestacion de vistas.
- `views/`: `layout/`, `components/`, `modules/` y `pages/` para todo el
	renderizado y comportamiento de interfaz.
- `models/`: estado global, sesion, cliente API, helpers de base path y
	runtime/registro de plugins frontend.

No deben existir carpetas o capas paralelas de primer nivel fuera de
`controllers/`, `views/` y `models/`; el bootstrap raiz `app.js` no constituye
una capa adicional.

### Shell y layouts de pagina

`AppController` crea una única instancia persistente de `ShellLayout` para las
páginas autenticadas. La shell registra navegación, cabecera, notificaciones,
contenido, acciones principales y footer, pero no construye contenido propio de
las páginas.

`PageLayout` recibe la shell activa y compone breadcrumbs, título, descripción,
toolbar y zonas de extensión. `ListLayout` y `FormLayout` especializan listados y
formularios sin crear shells adicionales. Login queda fuera de la shell
autenticada y usa la plantilla standalone `login` de `PageLayout`.

El contrato completo y sus targets se documentan en
[`../05-frontend/layouts-guide.md`](../05-frontend/layouts-guide.md).

## Servicios transversales (fuera de MVC clasico)

- ValidationService
- EntityService
- PluginSyncService / PluginUpdateService / PluginStatusService
- HookDispatcher
- UpdateManager

Nota: se usan servicios para evitar controladores gordos y mantener logica de negocio en una capa testeable.

## Flujo CRUD dinamico

1. Controller recibe POST de entidad
2. EntityService obtiene schema vigente
3. ValidationService valida campos y tipos
4. Model persiste registro JSONB
5. HookDispatcher ejecuta beforeSave y afterSave
6. Controller retorna estado y payload normalizado
