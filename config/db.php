<?php
/**
 * ============================================================================
 *  config/db.php  ·  Conexión a MySQL / MariaDB con PDO
 * ============================================================================
 *  Mini Proyecto 2 · Unidad 4 · Agenda de Contactos
 *
 *  Este archivo centraliza la conexión a la base de datos. Todas las páginas
 *  del sistema lo incluyen con require_once, de modo que la configuración
 *  vive en un solo lugar y nunca se repite código de conexión.
 *
 *  Características:
 *   - Se usa PDO (PHP Data Objects) como capa de acceso a datos.
 *   - PDO::ATTR_EMULATE_PREPARES = false  => sentencias preparadas REALES
 *     en el servidor MySQL (no simulación del lado de PHP).
 *   - PDO::ATTR_ERRMODE = ERRMODE_EXCEPTION => los errores se capturan con
 *     try/catch y NUNCA se muestran al usuario final.
 * ============================================================================
 */

declare(strict_types=1);

/* ---------------------------------------------------------------------------
 * 1. Credenciales de conexión
 * ---------------------------------------------------------------------------
 *  Desarrollo local (XAMPP / Laragon / WAMP):
 *      host = localhost · base = agenda_contactos · usuario = root · sin clave
 *
 *  Hosting gratuito (InfinityFree / AeonFree / ByetHost):
 *      los datos los entrega el panel de control del hosting, por ejemplo:
 *      host = sql301.infinityfree.com
 *      base = if0_12345678_agenda
 *      user = if0_12345678
 *      pass = la contraseña del panel
 * ------------------------------------------------------------------------- */
const DB_HOST    = 'localhost';
const DB_NAME    = 'agenda_contactos';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

/** Cadena de conexión (DSN) construida una sola vez. */
const DB_DSN = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

/** Opciones de PDO aplicadas a la conexión. */
$opciones = [
    // Lanza excepciones ante cualquier error (permite try/catch).
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Los SELECT devuelven arreglos asociativos: $fila['nombre'].
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Sentencias preparadas nativas del servidor (prevención real de SQL Injection).
    PDO::ATTR_EMULATE_PREPARES   => false,
    // Respeta los tipos nativos (id como int, no como texto).
    PDO::ATTR_STRINGIFY_FETCHES  => false,
];

/* ---------------------------------------------------------------------------
 * 2. Creación de la conexión
 * ---------------------------------------------------------------------------
 *  Si la conexión falla, el detalle técnico se guarda en el log del servidor
 *  (error_log) y al usuario se le muestra un mensaje genérico, sin SQL.
 * ------------------------------------------------------------------------- */
try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, $opciones);
} catch (PDOException $e) {
    error_log('[agenda] Error de conexión a MySQL: ' . $e->getMessage());

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
       . '<title>Servicio no disponible</title>'
       . '<style>body{font-family:system-ui,sans-serif;background:#f5f6fb;color:#1b1d29;'
       . 'display:grid;place-items:center;min-height:100vh;margin:0}'
       . '.caja{background:#fff;padding:2.5rem;border-radius:16px;max-width:460px;'
       . 'box-shadow:0 10px 30px rgba(20,20,60,.08);text-align:center}'
       . 'h1{font-size:1.25rem;margin:0 0 .6rem}p{color:#5b6178;line-height:1.6;margin:0}</style>'
       . '</head><body><div class="caja"><h1>Servicio temporalmente no disponible</h1>'
       . '<p>No fue posible conectar con la base de datos. '
       . 'Intente de nuevo en unos minutos o comuníquese con el administrador.</p>'
       . '</div></body></html>';
    exit;
}
