# Arquitectura MVC en Xestify

## Objetivo

Aplicar MVC en backend PHP y frontend JS sin acoplar la UI del negocio al servidor.

## Distribucion de responsabilidades

## Model

Representa datos core y metadata.

- SystemEntity — facade de lectura sobre `plugins WHERE plugin_type='entity'` (no usa system_entities)
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

- EntityController
- PluginController
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
