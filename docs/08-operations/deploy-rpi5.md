# Despliegue en Raspberry Pi 5

## Objetivo

Desplegar Xestify localmente por negocio con Apache+PHP como runtime
principal, en una Raspberry Pi 5.

Para el procedimiento de instalación paso a paso (Apache, PHP, PostgreSQL,
esquema de base de datos, administrador, sincronización de plugins, etc.), consulta
[INSTALL.md](../../INSTALL.md) en la raíz del repositorio. Este documento
cubre únicamente lo específico de producción/Raspberry Pi 5.

## Requisitos de hardware específicos

- Raspberry Pi 5 (8GB recomendado)
- Raspberry Pi OS 64-bit
- Disco SSD o SD de calidad industrial

## Servicios sugeridos

- Apache+PHP (frontend, API y assets de plugins en un solo origen)
- db-postgres (persistencia)
- scheduler (cron de updates)

## Runtime web canonico

La app se sirve como un unico origen Apache+PHP desde la raiz del
repositorio. Ver "Runtime web y desarrollo local" en
[README.md](../../README.md) para el mapa completo de rutas y el soporte de
alias/subruta.

## Backups

- Backup diario de PostgreSQL
- Retencion minima de 7 dias
- Export opcional a almacenamiento externo

## Monitoreo minimo

- Logs de app y Apache
- Estado de los servicios
- Espacio de disco y uso de memoria

## Hardening recomendado

- Cambiar credenciales por defecto
- Bloquear puertos no usados
- Limitar acceso SSH
- Actualizaciones de seguridad del OS
