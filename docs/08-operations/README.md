# Operaciones y despliegue

Esta carpeta contiene la documentacion sobre despliegue, actualizaciones y
operacion de Xestify en entornos locales y productivos.

---

## Runtime canonico

El runtime canonico de Xestify es Apache+PHP sirviendo frontend, API y assets de
plugins bajo un solo origen desde la raiz del repositorio.

- Produccion: Apache+PHP como runtime principal
- Desarrollo local: Apache+PHP con exposicion adicional de `/tests/*`
- Nginx: alternativa avanzada o historica, no requisito base del proyecto

---

## Buenas practicas y recomendaciones

- Realizar backups diarios de la base de datos y retener al menos 7 dias
- Cambiar credenciales por defecto y limitar acceso SSH
- Monitorear logs de app, Apache y estado de los servicios implicados
- Aplicar actualizaciones fuera de horario y nunca auto-aplicar cambios mayores sin revision
- Validar integridad y compatibilidad antes de aplicar updates
- En Windows, preferir `127.0.0.1` frente a `localhost` para DB y pruebas HTTP locales
- Si Xdebug esta cargado en Apache, usar `xdebug.start_with_request = trigger` salvo cuando se este depurando

---

## Referencias y guias

- [INSTALL.md](../../INSTALL.md): guia de instalacion paso a paso (raiz del repo) — punto de entrada canonico
- [deploy-rpi5.md](deploy-rpi5.md): notas especificas de produccion/Raspberry Pi 5 (servicios, backups, monitoreo, hardening)
- [actualizaciones.md](actualizaciones.md): Estrategias y procedimientos de actualizacion
- [apache-vhost-examples.md](apache-vhost-examples.md): ejemplos de VirtualHost Apache para produccion, desarrollo y alias/subruta
