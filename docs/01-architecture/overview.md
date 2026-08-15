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
- **`plugins` es la unica fuente de verdad para el catalogo de entidades** — `manifest_json->>'type' = 'entity'` sustituye a la antigua tabla `system_entities` (eliminada en Release B)

## Flujo base

1. Usuario abre modulo entidad
2. Frontend solicita schema al backend
3. Frontend renderiza vista dinamica
4. Backend valida payload segun schema
5. Backend persiste en `plugin_entity_data` y dispara hooks

## Pipeline HTTP protegido

El flujo runtime de una peticion API protegida es:

`Router -> AuthMiddleware -> Controller`

Las rutas bajo `/api/v1/entities` y `/api/v1/plugins` requieren JWT. El
`Router` construye una unica instancia `Request`, `AuthMiddleware` valida el
token y adjunta `Request::user()`, y el controller recibe esa misma request.
`/health` y `/api/v1/auth/login` permanecen publicas.

## Paradigma de registro de entidades

Todo tipo de entidad es un plugin de tipo `entity` instalado en la tabla `plugins`.
No existe tabla separada de catálogo: el filtro
`WHERE manifest_json->>'type' = 'entity' AND status = 'active'`
sobre la tabla `plugins` reemplaza completamente a la antigua `system_entities` (eliminada en Release B).

La tabla `plugins` no tiene columnas propias `plugin_type`/`name`/`version`/
`description`/`schema_version` (STORY 10.3 §2bis): son `id, slug, status,
manifest_json, schema_json, installed_at, updated_at`, y `manifest_json` refleja
el `manifest.json` real del plugin en disco.

**Consulta ejemplo:**
```sql
SELECT slug, manifest_json->>'label' AS label, schema_json
FROM plugins
WHERE manifest_json->>'type' = 'entity' AND status = 'active' AND schema_json IS NOT NULL;
```

Esta decision elimina la duplicacion de datos y hace que el catalogo de
entidades dependa unicamente de los plugins instalados y activos. La
sincronizacion desde disco a base de datos es explicita (`PluginSyncService`) y
el boot solo registra hooks activos (`PluginHookRegistrar`).
