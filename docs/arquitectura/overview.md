# Arquitectura General

## Vision

Xestify usa arquitectura micro-kernel local-first:

- Core minimo, estable y agnostico del dominio
- Plugins para entidades y extensiones de negocio
- Ejecucion local en RPi5 con sincronizacion opcional al repositorio central

## Componentes principales

1. Core Backend (PHP)
- API REST
- Autenticacion y autorizacion
- Motor de entidades dinamicas
- Dispatcher de hooks
- Gestor de plugins

2. Core Frontend (JS)
- Navegacion
- Formularios y tablas dinamicas
- Carga de componentes de plugins

3. Persistencia (PostgreSQL)
- Tablas core relacionales
- Campos variables en JSONB
- Indices por slug, owner y contenido JSONB

4. Marketplace central
- Catalogo de plugins
- Distribucion de paquetes versionados
- Endpoint de actualizaciones

## Reglas de diseno

- Nada del negocio hardcodeado en el Core
- Toda entidad vive como metadata + data
- Toda extension se registra por hook
- Toda actualizacion de plugin debe ser reversible
- **`plugins` es la unica fuente de verdad para el catalogo de entidades** — `plugin_type = 'entity'` sustituye a la antigua tabla `system_entities` (eliminada en Release B)

## Flujo base

1. Usuario abre modulo entidad
2. Frontend solicita schema al backend
3. Frontend renderiza vista dinamica
4. Backend valida payload segun schema
5. Backend persiste en entity_data y dispara hooks

## Pipeline HTTP protegido

El flujo runtime de una peticion API protegida es:

`Router -> AuthMiddleware -> Controller`

Las rutas bajo `/api/v1/entities` y `/api/v1/plugins` requieren JWT. El
`Router` construye una unica instancia `Request`, `AuthMiddleware` valida el
token y adjunta `Request::user()`, y el controller recibe esa misma request.
`/health` y `/api/v1/auth/login` permanecen publicas.

## Paradigma de registro de entidades

Todo tipo de entidad es un plugin de tipo `entity` instalado en la tabla `plugins`.
No existe tabla separada de catalogo — el filtro `WHERE plugin_type = 'entity' AND status = 'active'`
sobre la tabla `plugins` reemplaza completamente a la antigua `system_entities`.

Esta decision elimina la duplicacion de datos y convierte a `PluginLoader` en el
unico punto de registro de entidades al arranque del sistema.
