# Módulos Dolibarr — Nubium Solutions

Colección de módulos custom para [Dolibarr ERP/CRM](https://www.dolibarr.org), de código abierto.

## Instalación

Copiar la carpeta del módulo deseado dentro del directorio `htdocs/custom/` de tu instalación Dolibarr. Activarlo desde **Configuración → Módulos**.

## Módulos disponibles

### Deploy Manager

Panel centralizado para desplegar módulos custom de Dolibarr a múltiples instancias de clientes de forma automatizada.

**Funcionalidades:**
- Gestión de servidores y conexiones SSH
- Detección automática de instancias Dolibarr (autodiscover)
- Escaneo de módulos instalados y versiones en cada instancia
- Subida de releases (ZIP) con validación de seguridad
- Wizard de despliegue: seleccionar módulo → instancias → confirmar
- Backup automático antes de cada despliegue
- Sincronización git, ejecución de SQL y verificación post-deploy
- Progreso en tiempo real y historial completo

**Requisitos:**
- Dolibarr 16+
- PHP 7.4+ con PHP-FPM y `exec()` habilitado
- Extensión ZipArchive
- Binarios: `ssh`, `scp`, `rsync`, `tar`
- Clave SSH dedicada para conectar con los servidores destino

Documentación completa en [`custom/deploymanager/docs/`](custom/deploymanager/docs/).

## Licencia

GPLv3 — ver cada módulo para detalles.

## Autor

[Nubium Solutions](https://nubiumsolutions.com)
