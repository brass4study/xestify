# Arquitectura MVC en Xestify

## Objetivo

Aplicar MVC en backend PHP sin acoplar la UI del negocio al servidor.

## Distribucion de responsabilidades

## Model

Representa datos core y metadata.

- SystemEntity
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

## Servicios transversales (fuera de MVC clasico)

- ValidationService
- EntityService
- PluginLoader
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
