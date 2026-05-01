# Roadmap de Implementacion - Xestify

## 1. Objetivo del roadmap

Este documento traduce las funcionalidades definidas en la documentacion actual a un plan de implementacion ejecutable, incremental y con control de riesgo.

El enfoque prioriza:

- Entrega temprana de valor funcional (MVP operable).
- Base tecnica estable para evolucion por plugins.
- Seguridad y mantenibilidad desde el inicio.
- Compatibilidad con despliegue local en Raspberry Pi 5.

## 2. Resumen de funcionalidades definidas

## Funcionalidades nucleares (Core)

- Autenticacion y autorizacion local.
- API REST para entidades dinamicas.
- Motor metadata-driven para schema y validacion.
- Persistencia en PostgreSQL con JSONB.
- Cargador de plugins y registro de hooks.
- CRUD generico de entidades.

## Funcionalidades de extensibilidad

- Plugins de entidad base.
- Plugins de extension sobre entidad base.
- Hooks de backend y hooks de UI.
- Ciclo de vida de plugins (install/activate/update/deactivate/uninstall).

## Funcionalidades operativas

- Despliegue con contenedores en RPi5.
- Actualizaciones manuales y automaticas de plugins.
- Integridad de paquetes y rollback.
- Auditoria minima de acciones sensibles.

## 3. Estrategia de implementacion

Se propone una estrategia por fases con puertas de calidad (gates):

- Cada fase deja un entregable verificable.
- No se avanza si no se cumplen criterios de aceptacion.
- La complejidad de plugins avanzados se posterga hasta estabilizar Core.

## 4. Fases de implementacion

## Fase 0 - Preparacion tecnica (Semana 1)

Objetivo: dejar entorno de desarrollo reproducible y baseline de arquitectura.

Entregables:

- Estructura inicial de repositorio backend/frontend/docker.
- Convenciones de codigo y versionado.
- Plantillas base de modulos MVC y servicios.
- Pipeline local de calidad (lint/tests basicos).

Criterios de aceptacion:

- El proyecto arranca localmente con un comando estandar.
- Existen scripts de arranque para entorno dev.

Riesgos:

- Divergencia de stack si no se decide framework rapidamente.

## Fase 1 - Core de autenticacion y seguridad minima (Semanas 2-3)

Objetivo: habilitar acceso seguro antes del CRUD dinamico.

Entregables:

- AuthController y middleware de autenticacion.
- Modelo de roles base (admin, operador, lectura).
- Politica de permisos por accion de entidad.
- Registro de auditoria para login y cambios criticos.

Criterios de aceptacion:

- Endpoints protegidos por token/sesion.
- Usuario sin permisos recibe error consistente.

Dependencias:

- Fase 0.

## Fase 2 - Modelo de datos Core + migraciones (Semanas 3-4)

Objetivo: implementar base relacional + JSONB estable.

Entregables:

- Migraciones para system_entities, entity_metadata, entity_data, plugins_registry y plugin_hook_registry.
- Repositorios/modelos para operaciones base.
- Indices JSONB y de consulta frecuente.

Criterios de aceptacion:

- Migraciones idempotentes en entorno limpio.
- Insercion y consulta de registros con content JSONB.

Dependencias:

- Fase 0.

## Fase 3 - Motor de entidades dinamicas (Semanas 5-7)

Objetivo: CRUD generico con validacion por schema.

Entregables:

- EntityController y EntityService.
- ValidationService basado en schema_json.
- Endpoints REST de entidades (list/schema/records CRUD).
- Manejo de errores uniforme.

Criterios de aceptacion:

- Se crea una entidad via metadata sin tocar codigo de dominio.
- Payload invalido devuelve VALIDATION_ERROR estructurado.

Dependencias:

- Fase 1 y Fase 2.

## Fase 4 - Sistema de plugins y hooks backend (Semanas 8-10)

Objetivo: habilitar extensibilidad sin modificar Core.

Entregables:

- PluginLoader (descubrir, validar, registrar plugins).
- HookDispatcher con prioridades y control de fallos.
- Implementacion de hooks before/after de entidad.
- Ciclo de vida de plugin en backend.

Criterios de aceptacion:

- Plugin de entidad instalable y activable.
- Hook beforeSave puede bloquear operacion con error controlado.

Dependencias:

- Fase 3.

## Fase 5 - Frontend dinamico base (Semanas 9-12)

Objetivo: UI funcional para entidades dinamicas.

Entregables:

- DynamicForm y DynamicTable.
- Vistas EntityList y EntityEdit.
- Integracion con API REST y manejo de errores de campo.
- Cache basica de metadata en cliente.

Criterios de aceptacion:

- Crear/editar/listar registros de cualquier entidad declarada.
- Errores de validacion visibles por campo.

Dependencias:

- Fase 3.

## Fase 6 - Extensiones de entidad y hooks de UI (Semanas 12-14)

Objetivo: soportar plugins que amplian entidades base.

Entregables:

- DynamicTabs y carga de componentes de extension.
- Hooks registerTabs/registerActions.
- Caso completo: extension_optometria sobre clients.

Criterios de aceptacion:

- Al abrir cliente, se monta pestana de extension si plugin activo.
- Operaciones CRUD de extension respetan owner_id.

Dependencias:

- Fase 4 y Fase 5.

## Fase 7 - Actualizaciones y rollback de plugins (Semanas 14-16)

Objetivo: evolucion segura del ecosistema local.

Entregables:

- UpdateManager (check/download/apply/rollback).
- Validacion de checksum y compatibilidad.
- Registro de historial de actualizaciones.
- Politica de no auto-aplicar versiones major.

Criterios de aceptacion:

- Se actualiza plugin de version N a N+1 con registro.
- Se ejecuta rollback funcional ante fallo controlado.

Dependencias:

- Fase 4.

## Fase 8 - Operacion en RPi5 y hardening inicial (Semanas 16-18)

Objetivo: asegurar que el producto funcione de forma estable en escenario real.

Entregables:

- Docker Compose para app + postgres + scheduler.
- Backups automatizados y prueba de restauracion.
- Hardening basico de red y credenciales.
- Guia operativa de instalacion y mantenimiento.

Criterios de aceptacion:

- Instalacion limpia en RPi5 documentada y repetible.
- Restore de backup exitoso en ambiente de prueba.

Dependencias:

- Fases 1 a 7.

## Fase 9 - Marketplace central minimo viable (Semanas 18-20)

Objetivo: habilitar distribucion de plugins versionados.

Entregables:

- Catalogo de plugins (metadata y versiones).
- Endpoint de consulta de actualizaciones.
- Paquetes versionados para descarga.

Criterios de aceptacion:

- Nodo local detecta version nueva disponible.
- Paquete descargado se puede aplicar en staging.

Dependencias:

- Fase 7.

## Fase 10 - QA integral y beta controlada (Semanas 20-24)

Objetivo: validar estabilidad, seguridad y experiencia de uso.

Entregables:

- Suite de pruebas unitarias/integracion/E2E del flujo principal.
- Test de regresion sobre plugins y hooks.
- Checklist de seguridad y auditoria.
- Beta con 2-3 negocios piloto.

Criterios de aceptacion:

- Flujo principal estable: entidad base + extension + actualizacion.
- Incidencias criticas cerradas antes de release.

Dependencias:

- Fases 1 a 9.

## 5. Priorizacion MVP (lo minimo para producir valor)

Para obtener un MVP util en menor tiempo, el corte recomendado incluye:

- Fase 0 a Fase 6 completas.
- De Fase 7, solo flujo manual de actualizacion + rollback basico.
- Sin marketplace completo; actualizacion desde fuente controlada inicial.

Resultado MVP esperado:

- Negocio puede gestionar al menos 2 entidades dinamicas.
- Puede instalar un plugin de extension sobre cliente.
- Puede actualizar ese plugin manualmente con trazabilidad.

## 6. Consideraciones iniciales clave

## Decisiones tecnicas pendientes (bloqueantes suaves)

1. Framework backend:
- Opcion A: Laravel (productividad alta).
- Opcion B: Symfony (estructura estricta).
- Opcion C: PHP nativo estructurado (maximo control, mas esfuerzo).

2. Framework frontend:
- Opcion A: Vue (simple para componentes dinamicos).
- Opcion B: React (ecosistema amplio).

3. Formato de autentificacion:
- Token JWT o sesion local segun topologia de red y uso real.

4. Contrato formal de schema:
- Definir si se usa JSON Schema estandar completo o subset propio.

## Riesgos tempranos y mitigaciones

1. Riesgo: explosion de complejidad en plugins.
- Mitigacion: reglas estrictas de contrato, plantillas y tests de compatibilidad.

2. Riesgo: regresiones por actualizaciones.
- Mitigacion: staging, checksum, backups y rollback obligatorio.

3. Riesgo: rendimiento degradado por consultas JSONB sin estrategia.
- Mitigacion: indices GIN y consultas observables desde el inicio.

4. Riesgo: acoplamiento accidental Core-negocio.
- Mitigacion: revisiones de arquitectura y prohibicion de logica vertical en Core.

5. Riesgo: seguridad insuficiente en entorno local.
- Mitigacion: hardening base, control de accesos y auditoria activa.

## 7. Definicion de listo por fase (DoD)

Una fase se considera completa cuando:

- Tiene funcionalidad demostrable en entorno local.
- Tiene pruebas minimas automatizadas del flujo agregado.
- Tiene documentacion actualizada en docs/.
- No introduce deuda critica de seguridad o integridad de datos.

## 8. Hitos recomendados

- Hito A (Semana 7): CRUD dinamico funcional de extremo a extremo.
- Hito B (Semana 10): Plugins y hooks backend operativos.
- Hito C (Semana 14): Extensiones de entidad visibles en UI.
- Hito D (Semana 16): Actualizacion y rollback de plugins funcionando.
- Hito E (Semana 24): Beta controlada lista para validacion comercial.

## 9. Metricas iniciales de seguimiento

- Tiempo de alta de nueva entidad sin codigo.
- Cantidad de errores de validacion por release.
- Tiempo medio de instalacion/actualizacion de plugin.
- Tasa de rollback por fallos de actualizacion.
- Latencia p95 de endpoints CRUD en RPi5.

## 10. Proximo paso recomendado

Iniciar inmediatamente Fase 0 y Fase 1 en paralelo ligero:

- Definir stack final (backend/frontend).
- Crear esqueleto de proyecto y autenticacion base.
- Dejar preparada la capa de migraciones para arrancar Fase 2 sin friccion.
