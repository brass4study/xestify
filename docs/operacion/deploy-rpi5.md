# Despliegue en Raspberry Pi 5

## Objetivo

Desplegar Xestify localmente por negocio con stack en contenedores.

## Requisitos base

- Raspberry Pi 5 (8GB recomendado)
- Raspberry Pi OS 64-bit
- Docker y Docker Compose
- Disco SSD o SD de calidad industrial

## Servicios sugeridos

- app-php (backend)
- web-nginx (reverse proxy)
- db-postgres (persistencia)
- scheduler (cron de updates)

## Variables de entorno minimas

- APP_ENV=production
- APP_URL=http://xestify.local
- DB_HOST=db-postgres
- DB_PORT=5432
- DB_NAME=xestify
- DB_USER=xestify
- DB_PASSWORD=change_me

## Flujo de instalacion

1. Clonar repositorio local
2. Configurar archivo .env
3. Levantar stack con docker compose up -d
4. Ejecutar migraciones core
5. Registrar usuario admin inicial
6. Verificar salud de API y UI

## Backups

- Backup diario de PostgreSQL
- Retencion minima de 7 dias
- Export opcional a almacenamiento externo

## Monitoreo minimo

- Logs de app y nginx
- Estado de contenedores
- Espacio de disco y uso de memoria

## Hardening recomendado

- Cambiar credenciales por defecto
- Bloquear puertos no usados
- Limitar acceso SSH
- Actualizaciones de seguridad del OS
