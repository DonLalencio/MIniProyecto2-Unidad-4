# 🌐 Guía de publicación en Internet (InfinityFree)

El sistema debe estar accesible en línea en un hosting con **PHP + MySQL/MariaDB**
(no se acepta `localhost` ni túneles). Esta guía usa **InfinityFree** porque es gratuito,
soporta PHP 8, MySQL, `.htaccess` y no necesita tarjeta de crédito. Los pasos son
equivalentes en AeonFree, ByetHost o AwardSpace.

---

## 1. Crear la cuenta y el sitio

1. Entrar a <https://infinityfree.com> → **Sign Up** y registrar la cuenta con un correo real
   (hay que confirmar el correo y resolver un captcha).
2. En el panel (**Client Area**) → **Create Account**.
3. Elegir un **subdominio gratuito**, por ejemplo `agenda-contactos.rf.gd`.
4. Anotar el **dominio temporal** que asigna el panel (tipo `if0_12345678`), se usa como
   usuario de FTP y de MySQL.
5. Esperar a que el estado del sitio pase a **Active** (puede tardar unos minutos).

> La URL pública quedará así: `https://agenda-contactos.rf.gd/`

---

## 2. Crear la base de datos MySQL

1. En el panel del sitio → **MySQL Databases** → **Create Database**.
2. Nombre corto: `agenda` → el sistema crea `if0_XXXXXXXX_agenda`.
3. Anotar los cuatro datos que muestra el panel:

| Dato | Ejemplo |
|------|---------|
| Host de MySQL | `sql301.infinityfree.com` |
| Nombre de la base | `if0_12345678_agenda` |
| Usuario | `if0_12345678` |
| Contraseña | la que se define al crear la base |

---

## 3. Importar la tabla `contactos`

1. En el panel → **phpMyAdmin** → seleccionar la base recién creada.
2. Pestaña **Importar** → **Elegir archivo** → `database/agenda.sql`.
3. **Importante:** el script ya viene preparado para ejecutarse sobre una base existente
   (las líneas `CREATE DATABASE` y `USE` están comentadas). Si su versión las tiene activas,
   elimine esas dos líneas antes de importar.
4. Verificar que aparezcan la tabla `contactos` y sus 6 registros de ejemplo.

---

## 4. Subir los archivos

### Opción A · FTP (recomendado)

1. Descargar **FileZilla** (<https://filezilla-project.org>).
2. Conectar con:
   - **Servidor:** `ftpupload.net`
   - **Usuario:** el `if0_XXXXXXXX` del panel
   - **Contraseña:** la del panel
   - **Puerto:** `21`
3. Entrar a la carpeta `htdocs/` (¡ahí va el proyecto, no en la raíz!).
4. Arrastrar **todo el contenido** del proyecto (no la carpeta contenedora):

```
htdocs/
├── index.php
├── crear.php
├── guardar.php
├── editar.php
├── actualizar.php
├── ver.php
├── eliminar.php
├── .htaccess
├── config/
├── includes/
├── css/
├── archivos/
└── database/
```

### Opción B · File Manager del panel

**File Manager** → `htdocs` → **Upload** → subir un `.zip` del proyecto → clic derecho →
**Extract**. Borrar el `.zip` después.

> ⚠️ No suba la carpeta `docs/` ni `tools/` si desea una instalación más ligera;
> no son necesarias para que el sistema funcione.

---

## 5. Configurar la conexión

Editar `config/db.php` **en el servidor** (File Manager → clic derecho → *Edit*):

```php
const DB_HOST    = 'sql301.infinityfree.com';
const DB_NAME    = 'if0_12345678_agenda';
const DB_USER    = 'if0_12345678';
const DB_PASS    = 'la_contraseña_de_la_base';
```

Guardar y abrir `https://agenda-contactos.rf.gd/`.

---

## 6. Permisos de la carpeta de fotos

1. File Manager → clic derecho sobre `archivos/` → **Permissions**.
2. Aplicar `755` a la carpeta (y `644` a los archivos).
3. Si al subir una foto aparece “no fue posible guardar la fotografía”,
   probar `775` en la carpeta `archivos/`.

---

## 7. Verificación final (checklist)

- [ ] `https://TU-DOMINIO/index.php` muestra los contactos de ejemplo.
- [ ] Crear un contacto con fotografía → aparece el mensaje de éxito.
- [ ] Ver el detalle del contacto recién creado.
- [ ] Editar el contacto y comprobar los cambios.
- [ ] Buscar por nombre, apellido, teléfono y correo.
- [ ] Eliminar el contacto de prueba (pide confirmación).
- [ ] `/config/db.php` **no** muestra el código (debe responder 403).
- [ ] `/archivos/` no muestra un listado de archivos (debe responder 403).

---

## 8. Problemas frecuentes

| Síntoma | Causa / solución |
|---------|------------------|
| “Servicio temporalmente no disponible” | Credenciales incorrectas en `config/db.php`, o el host no es `sqlXXX.infinityfree.com`. |
| Error 403 en todo el sitio | El proyecto quedó en la raíz y no en `htdocs/`, o el `index.php` no se subió. |
| Las fotos no se ven | Faltó subir `archivos/` o sus permisos no permiten escritura. |
| Las fotos no se guardan | Límite de subida del hosting; reducir la imagen a menos de 2 MB. |
| Error 500 al abrir una página | Revisar que la versión de PHP del panel sea **8.x** (menú *PHP Configuration*). |
| Error 500 solo al subir una foto | Activar la extensión `fileinfo` en *PHP Configuration* → *Extensions*. |
| El sitio tarda en abrir la primera vez | Normal en hosting gratuito: el primer acceso “despierta” el servicio. |

---

## 9. Después de publicar

1. Copiar la URL final y actualizarla en:
   - `README.md` (tabla de enlaces).
   - El reporte de evidencias (portada, índice y anexos).
2. Regenerar el PDF con la URL pública:

```bash
# con el sistema ya publicado
BASE_URL="https://agenda-contactos.rf.gd" node tools/reporte/generar.mjs
```

> El generador del reporte toma las capturas directamente del sitio publicado,
> de modo que las imágenes muestran la URL real en la barra del navegador.
