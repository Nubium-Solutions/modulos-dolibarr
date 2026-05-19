# Módulos Dolibarr — Nubium Solutions

Colección de módulos custom para [Dolibarr ERP/CRM](https://www.dolibarr.org), de código abierto.

## Instalación general

Copiar la carpeta del módulo deseado dentro del directorio `htdocs/custom/` de tu instalación Dolibarr. Activarlo desde **Configuración → Módulos**.

---

## Módulos disponibles

### Deploy Manager

Panel centralizado para desplegar módulos custom de Dolibarr a múltiples instancias de clientes de forma automatizada. En vez de copiar manualmente los archivos a cada servidor, este módulo automatiza el proceso: seleccionas el módulo, eliges las instancias destino, y el sistema se encarga del backup, copia de archivos, sincronización git, ejecución de SQL y verificación.

**Funcionalidades:**
- Gestión de servidores y conexiones SSH
- Detección automática de instancias Dolibarr (autodiscover)
- Escaneo de módulos instalados y versiones en cada instancia
- Subida de releases (ZIP) con validación de seguridad
- Wizard de despliegue: seleccionar módulo → instancias → confirmar
- Backup automático antes de cada despliegue
- Sincronización git, ejecución de SQL y verificación post-deploy
- Progreso en tiempo real y historial completo
- Dashboard con matriz de versiones instancias × módulos

#### Requisitos — Servidor del panel

| Requisito | Detalle |
|-----------|---------|
| **Dolibarr** | Versión 16+ con acceso administrador |
| **PHP** | 7.4+ con PHP-FPM (necesario para ejecución en background) |
| **Función `exec()`** | No debe estar en `disable_functions` del `php.ini` |
| **Extensión ZipArchive** | Habilitada en PHP (para procesar releases) |
| **Binarios del sistema** | `ssh`, `scp`, `rsync`, `tar` instalados y accesibles por el usuario web |
| **Clave SSH** | Par de claves dedicado para el panel (ver configuración abajo) |
| **Directorio de datos** | El módulo crea `data/deploymanager/` automáticamente; el usuario web debe tener permisos de escritura en `DOL_DATA_ROOT` |

#### Requisitos — Servidores destino

| Requisito | Detalle |
|-----------|---------|
| **Acceso SSH** | La clave pública del panel debe estar en `authorized_keys` del usuario SSH |
| **Usuario SSH** | `root` o un usuario con sudo sin contraseña para: `rsync`, `tar`, `chown`, `mysql`, `find` |
| **rsync** | Instalado en el servidor destino |
| **Cliente MySQL/MariaDB** | Necesario para ejecutar migraciones SQL |
| **Git** | Si las instancias usan repositorios git, debe estar configurado con remote `origin` |

#### Configuración de la clave SSH

```bash
# 1. Generar clave en el servidor del panel
ssh-keygen -t ed25519 -f /ruta/dolibarr/data/deploymanager_key -N ""
chmod 600 /ruta/dolibarr/data/deploymanager_key

# 2. Copiar la clave pública a cada servidor destino
ssh-copy-id -i /ruta/dolibarr/data/deploymanager_key.pub usuario@ip-servidor-destino

# 3. Verificar conexión
ssh -i /ruta/dolibarr/data/deploymanager_key usuario@ip-servidor-destino echo OK
```

La ruta de la clave se configura al añadir cada servidor en el panel, o globalmente con la constante `DEPLOYMANAGER_SSH_KEY` en Configuración → Otros.

> **Nota sobre PHP-FPM:** El módulo usa `fastcgi_finish_request()` para ejecutar despliegues en background. Esto solo funciona con PHP-FPM, no con `mod_php`. Si tu servidor usa `mod_php`, los despliegues funcionarán pero el navegador quedará esperando hasta que terminen.

#### Permisos

| Permiso | Qué permite |
|---------|-------------|
| `Leer` | Ver dashboard, instancias, módulos, historial |
| `Desplegar` | Ejecutar despliegues y subir releases |
| `Admin` | Gestionar servidores e instancias (CRUD) |

Los administradores de Dolibarr tienen acceso completo automáticamente.

#### Uso

1. **Añadir servidores**: Despliegues → Servidores → Añadir servidor (IP, usuario SSH, clave)
2. **Detectar instancias**: Despliegues → Instancias → Detectar instancias (autodiscover por SSH)
3. **Escanear módulos**: Escanear cada instancia para descubrir módulos instalados y versiones
4. **Desplegar**: Despliegues → Desplegar → Seleccionar módulo → Seleccionar instancias → Confirmar

El wizard muestra qué versión tiene cada instancia. Las que ya están actualizadas aparecen con check verde. El despliegue ejecuta automáticamente: backup → rsync → git sync → SQL → verificación.

#### Resolución de problemas

| Problema | Solución |
|----------|----------|
| Error de conexión SSH | Verificar clave SSH, IP, puerto y usuario en la ficha del servidor |
| Error rsync | Verificar que la ruta custom existe y tiene permisos de escritura |
| Error SQL (tablas ya existentes) | Normal si los .sql no usan `IF NOT EXISTS`. No afecta al funcionamiento |
| Un despliegue falla pero los demás funcionan | Cada despliegue es independiente. Revisar el log de la instancia fallida |
| OPcache no refleja los cambios | Reiniciar PHP-FPM: `systemctl restart php-fpm` |
| Quiero volver a la versión anterior | Los backups están en `data/deploymanager/backups/[dominio]/` como tar.gz |

Documentación técnica completa en [`custom/deploymanager/docs/`](custom/deploymanager/docs/).

---

## Licencia

GPLv3 — ver cada módulo para detalles.

## Autor

[Nubium Solutions](https://nubiumsolutions.com)
