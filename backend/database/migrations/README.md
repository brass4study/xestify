# Migraciones incrementales

Carpeta reservada para **migraciones incrementales futuras** del esquema de
Xestify. Hoy está vacía a propósito.

- La **definición inicial del esquema** (tablas core `users`, `plugins`,
  `plugin_entity_data`, `plugin_extension_data`, `plugin_update_history`,
  `configuration`) vive en [`../schema/`](../schema/): ficheros SQL numerados e
  idempotentes (`CREATE TABLE IF NOT EXISTS`, `CREATE INDEX IF NOT EXISTS`) que
  aplica `php tools/setup/install.php` (o, manualmente, `psql` en orden — ver
  `INSTALL.md`). No son migraciones: describen el esquema completo de una
  instalación nueva y se pueden re-ejecutar sin efecto.
- Cuando el esquema base tenga que **evolucionar en una instalación ya
  existente** (nueva columna, cambio de tipo, transformación de datos), la
  migración correspondiente se añadirá aquí como `NNN_descripcion.sql`,
  numerada, idempotente y aplicada de forma explícita por el operador — nunca en
  cada request. El mecanismo de registro de migraciones aplicadas (tabla de
  tracking, orden, rollback) se decidirá con la primera migración real; no se
  pre-diseña ahora.
- La convención está recogida en `docs/09-history/decisiones-tecnicas.md`
  (DECISION 11).
