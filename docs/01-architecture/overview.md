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

4. Distribucion de plugins
- Carpeta `plugins/` en disco, sincronizada a la tabla `plugins` con `PluginSyncService`
- Actualizacion y rollback explicitos por administrador (ver `docs/08-operations/actualizaciones.md`)

## Reglas de diseno

- Nada del negocio hardcodeado en el Core
- Toda entidad vive como metadata + data
- Toda extension se registra por hook
- Toda actualizacion de plugin debe ser reversible
- **`plugins` es la unica fuente de verdad para el catalogo de entidades** — filtro `manifest_json->>'type' = 'entity' AND status = 'active'`, sin tabla de catalogo paralela

## Flujo base

1. Usuario abre modulo entidad
2. Frontend solicita schema al backend
3. Frontend renderiza vista dinamica
4. Backend valida payload segun schema
5. Backend persiste en `plugin_entity_data` y dispara hooks

## Pipeline HTTP protegido

El flujo runtime de una peticion API protegida es:

`Router -> AuthMiddleware -> Controller`

Toda ruta requiere JWT por defecto: el `Router` marca cada ruta como
protegida salvo que se declare explicitamente lo contrario, por lo que una
ruta nueva nunca queda desprotegida por omision. `Router` construye una
unica instancia `Request`, `AuthMiddleware` valida el token y adjunta
`Request::user()`, y el controller recibe esa misma request. Las unicas
rutas publicas son `/health` y `/api/v1/auth/login`.

## Paradigma de registro de entidades

Todo tipo de entidad es un plugin de tipo `entity` instalado en la tabla `plugins`.
El filtro
`WHERE manifest_json->>'type' = 'entity' AND status = 'active'`
sobre la tabla `plugins` es la unica fuente del catalogo de entidades.

Las columnas reales de `plugins` son `id, slug, status, manifest_json,
schema_json, sort_order, installed_at, updated_at`. El tipo, nombre tecnico,
version y descripcion del plugin viven dentro de `manifest_json`, que
refleja el `manifest.json` real del plugin en disco.

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
