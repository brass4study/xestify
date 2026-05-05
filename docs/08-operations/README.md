# Operaciones y despliegue

Esta carpeta contiene la documentación sobre despliegue, actualizaciones y operación de Xestify en entornos locales y productivos.

---

## Buenas prácticas y recomendaciones

- Realizar backups diarios de la base de datos y retener al menos 7 días
- Cambiar credenciales por defecto y limitar acceso SSH
- Monitorear logs de app, nginx y estado de contenedores
- Aplicar actualizaciones fuera de horario y nunca auto-aplicar cambios mayores sin revisión
- Validar integridad y compatibilidad antes de aplicar updates

---

## Referencias y guías

- [deploy-rpi5.md](deploy-rpi5.md): Guía de despliegue en Raspberry Pi 5
- [actualizaciones.md](actualizaciones.md): Estrategias y procedimientos de actualización