# Generador del reporte de evidencias

Script de apoyo que produce `docs/Reporte_Evidencias_Agenda.pdf` a partir del sistema
**en ejecución**. Toma capturas reales del navegador (con su URL), de la consola de MySQL
y de los archivos PHP del repositorio.

## Requisitos

- Node.js 20 o superior.
- Microsoft Edge o Google Chrome instalado.
- El sistema funcionando (local o publicado) y la base de datos importada.
- Cliente `mariadb`/`mysql` y `php` disponibles en el `PATH` (o indicados por variable de entorno).

## Instalación

```bash
cd tools/reporte
npm install puppeteer-core shiki
```

## Uso

```bash
# Sistema local en http://localhost/agenda-contactos
node generar.mjs

# Sistema publicado (las capturas mostrarán la URL real)
BASE_URL="https://tu-dominio.rf.gd" node generar.mjs
```

Variables de entorno opcionales:

| Variable | Uso |
|----------|-----|
| `BASE_URL` | URL del sistema en ejecución (por defecto `http://localhost/agenda-contactos`) |
| `PROYECTO_DIR` | Ruta del proyecto (por defecto, dos niveles arriba del script) |
| `EDGE_PATH` | Ruta del navegador Chromium/Edge |
| `MYSQL_BIN` | Ruta del cliente `mariadb`/`mysql` |
| `PHP_BIN` | Ruta del ejecutable `php` |

## Qué genera

- `docs/capturas/` → capturas de pantalla, de consola y de código (PNG).
- `docs/Reporte_Evidencias_Agenda.pdf` → documento A4 con portada, índice, introducción,
  las 10 especificaciones (código + ejecución + explicación) y anexos.

> Cada especificación queda en páginas independientes, con **máximo una captura por página**,
> según lo solicita la lista de verificación.
