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

## Flujo base

1. Usuario abre modulo entidad
2. Frontend solicita schema al backend
3. Frontend renderiza vista dinamica
4. Backend valida payload segun schema
5. Backend persiste en entity_data y dispara hooks
