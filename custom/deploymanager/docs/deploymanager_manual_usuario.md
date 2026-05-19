# Deploy Manager — Manual de Usuario

## Qué es Deploy Manager

Deploy Manager es un panel centralizado para desplegar módulos custom de Dolibarr a todas las instancias de clientes. En vez de copiar manualmente los archivos a cada servidor, este módulo automatiza el proceso: seleccionas el módulo, eliges las instancias destino, y el sistema se encarga del backup, copia de archivos, sincronización git, ejecución de SQL y verificación.

---

## Requisitos e instalación

### Servidor del panel (donde se instala el módulo)

| Requisito | Detalle |
|-----------|---------|
| **Dolibarr** | Versión 16+ con acceso administrador |
| **PHP** | 7.4+ con PHP-FPM (necesario para ejecución en background) |
| **Función `exec()`** | No debe estar en `disable_functions` del `php.ini` |
| **Extensión ZipArchive** | Habilitada en PHP (para procesar releases) |
| **Binarios del sistema** | `ssh`, `scp`, `rsync`, `tar` instalados y accesibles por el usuario web |
| **Clave SSH** | Par de claves dedicado para el panel (ver sección configuración) |
| **Directorio de datos** | El módulo crea `data/deploymanager/` automáticamente; el usuario web debe tener permisos de escritura en `DOL_DATA_ROOT` |

### Servidores destino (donde se despliegan los módulos)

| Requisito | Detalle |
|-----------|---------|
| **Acceso SSH** | La clave pública del panel debe estar en `authorized_keys` del usuario SSH |
| **Usuario SSH** | `root` o un usuario con sudo sin contraseña para: `rsync`, `tar`, `chown`, `mysql`, `find` |
| **rsync** | Instalado en el servidor destino |
| **Cliente MySQL/MariaDB** | Necesario para ejecutar migraciones SQL |
| **Git** | Si las instancias usan repositorios git, debe estar configurado con remote `origin` |

### Configuración de la clave SSH

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

### Nota sobre PHP-FPM

El módulo usa `fastcgi_finish_request()` para ejecutar despliegues en background tras enviar la respuesta al navegador. **Esto solo funciona con PHP-FPM**, no con `mod_php` de Apache. Si tu servidor usa `mod_php`, los despliegues funcionarán pero el navegador quedará esperando hasta que terminen.

---

## Acceso y permisos

**URL:** Panel Dolibarr donde esté instalado el módulo → menú **Despliegues**

**Permisos disponibles:**
| Permiso | Qué permite |
|---------|-------------|
| `Leer` | Ver dashboard, instancias, módulos, historial |
| `Desplegar` | Ejecutar despliegues y subir releases |
| `Admin` | Gestionar servidores e instancias (CRUD) |

Los administradores de Dolibarr tienen acceso completo automáticamente.

---

## Servidores

### Añadir un servidor

1. Ir a **Despliegues → Servidores**
2. Click en **Añadir servidor**
3. Rellenar:
   - **Nombre**: nombre identificativo (ej: "VPS-Produccion-1")
   - **Host**: IP del servidor (ej: 192.168.1.10)
   - **Usuario SSH**: usuario para conectar (ej: root o deployer)
   - **Puerto SSH**: normalmente 22
   - **Ruta clave SSH**: ruta a la clave privada en el panel (ej: /ruta/dolibarr/data/deploymanager_key)
   - **Es local**: marcar si el servidor es el mismo donde está el panel
4. Click en **Guardar**

### Probar conexión SSH

En la lista de servidores o en la ficha del servidor, pulsar el botón **Test** (icono de enchufe). Si aparece "Conexión SSH correcta", todo funciona. Si falla, verificar:
- Que la clave SSH esté en la ruta indicada
- Que la clave pública esté en el `authorized_keys` del servidor destino
- Que el puerto SSH sea correcto
- Que el firewall permita la conexión

---

## Instancias

### Detectar instancias automáticamente (Autodiscover)

En vez de añadir instancias una a una:

1. Ir a **Despliegues → Instancias**
2. Click en **Detectar instancias (nombre-servidor)**
3. El sistema conecta por SSH al servidor, busca todos los directorios con `conf/conf.php` (instalaciones Dolibarr) y las registra automáticamente
4. Las instancias que ya existen se saltan

### Añadir instancia manualmente

1. Ir a **Despliegues → Instancias → Añadir instancia**
2. Rellenar:
   - **Servidor**: seleccionar el VPS donde está
   - **Nombre**: nombre identificativo
   - **Dominio**: dominio de la instancia (ej: erp.micliente.com)
   - **Ruta custom**: ruta al directorio custom (ej: /var/www/html/dolibarr/htdocs/custom)
   - **Ruta conf.php**: ruta al archivo de configuración (ej: /var/www/html/dolibarr/htdocs/conf/conf.php)
   - **Entorno**: production, staging o development

### Escanear módulos de una instancia

1. En la lista de instancias, click en el icono de lupa de la instancia
2. O entrar en la ficha de la instancia → **Escanear módulos**
3. El sistema conecta por SSH, lista los directorios en `/custom/`, lee la versión de cada `mod*.class.php` y actualiza la tabla de módulos instalados

### Escanear todas las instancias

En la lista de instancias, el botón **Escanear todas** ejecuta el escaneo una a una secuencialmente, mostrando el progreso.

---

## Módulos

La sección **Módulos** muestra todos los módulos descubiertos en las instancias escaneadas. Para cada módulo se ve:
- En cuántas instancias está instalado
- La versión más alta encontrada
- Cuántas versiones diferentes hay

Click en un módulo para ver el detalle: en qué instancias está, qué versión tiene cada una, y si hay actualizaciones pendientes.

---

## Releases (subir nueva versión)

### Subir un ZIP

1. Ir a **Despliegues → Releases**
2. En la sección de subida:
   - Seleccionar el archivo ZIP del módulo
   - Opcionalmente escribir un changelog
   - Click en **Subir release**
3. El sistema:
   - Valida que el ZIP contenga `core/modules/mod*.class.php`
   - Extrae el slug y la versión del módulo
   - Verifica que no exista ya esa versión
   - Almacena el ZIP con hash SHA-256

**Requisitos del ZIP:**
- Debe contener la estructura estándar de un módulo Dolibarr
- No puede contener archivos ejecutables (.sh, .py, .phar, .exe)
- No puede contener rutas con `..` (path traversal)
- El directorio raíz del ZIP debe ser el slug del módulo

---

## Desplegar un módulo

### Paso a paso con el Wizard

1. Ir a **Despliegues → Desplegar** (icono cohete)

2. **Paso 1 — Seleccionar módulo(s):**
   - Seleccionar uno o varios módulos del desplegable
   - Se muestra la versión más alta encontrada y la instancia origen
   - Click en **Siguiente**

3. **Paso 2 — Seleccionar instancias:**
   - Se muestra la tabla de instancias con la versión actual de cada módulo
   - Las instancias con la misma versión aparecen con check verde (no seleccionables)
   - Las instancias sin el módulo aparecen en gris
   - Seleccionar las instancias destino con los checkboxes
   - Click en **Siguiente**

4. **Paso 3 — Confirmar:**
   - Resumen de qué se va a desplegar y dónde
   - Click en **Desplegar ahora** (botón rojo)

5. **Progreso:**
   - Se redirige a la pantalla de estado
   - Se ve en tiempo real el estado de cada instancia: pendiente → backup → desplegando → SQL → verificando → completado/fallido
   - Los logs se actualizan cada 3 segundos

### Qué hace el despliegue internamente

Para cada instancia destino:

1. **Backup**: Comprime el módulo actual en un tar.gz y lo descarga al panel
2. **Rsync**: Copia los archivos del módulo desde la instancia origen a la destino
3. **Git sync**: Hace fetch + reset del repo, reaplica el rsync, commit y push
4. **SQL**: Ejecuta todos los archivos .sql del directorio sql/ del módulo
5. **Verificar**: Lee la versión del módulo desplegado y actualiza la BD

Si algún paso falla, la instancia se marca como "failed" pero las demás continúan.

---

## Historial de despliegues

En **Despliegues → Historial** se ve el registro de todos los despliegues realizados:
- Módulo desplegado
- Fecha
- Quién lo hizo
- Número de instancias exitosas/fallidas
- Estado general del lote

Click en un despliegue para ver el detalle por instancia con los logs completos.

---

## Dashboard

El dashboard muestra:
- Estadísticas generales: servidores, instancias, módulos, actualizaciones pendientes
- Matriz de versiones: instancias × módulos con badges de color (verde = actualizado, naranja = desactualizado)
- Últimos despliegues

---

## Resolución de problemas

### "Error de conexión SSH"
- Verificar que la clave SSH del panel está en `authorized_keys` del servidor destino
- Verificar IP, puerto y usuario SSH en la ficha del servidor
- Probar conexión manual: `ssh -i /ruta/clave usuario@host`

### "Error rsync"
- Verificar que la ruta custom de la instancia destino existe
- Verificar permisos de escritura en la ruta destino
- Revisar el log del despliegue para ver el error exacto

### "Error SQL (tablas ya existentes)"
- Es normal. Los archivos .sql suelen tener `CREATE TABLE IF NOT EXISTS` pero algunos usan `CREATE TABLE` sin el IF NOT EXISTS. No afecta al funcionamiento.

### Un despliegue falla pero los demás funcionan
- Cada despliegue es independiente. Un fallo en una instancia no afecta a las demás.
- Revisar el log específico de la instancia fallida.

### Quiero volver a la versión anterior
- Los backups se guardan en el panel en `data/deploymanager/backups/[dominio]/`
- El archivo es un tar.gz que se puede descomprimir manualmente sobre la instancia

### OPcache no refleja los cambios
- Algunos servidores cachean los archivos PHP. Reiniciar PHP-FPM: `systemctl restart php-fpm` (o el servicio correspondiente a tu versión de PHP)
