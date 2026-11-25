<?php
/**
 * ============================================================================
 *  includes/funciones.php  ·  Utilidades compartidas
 * ============================================================================
 *  Funciones de apoyo usadas por todas las páginas:
 *    · salida segura con htmlspecialchars()
 *    · manejo de sesión y mensajes flash
 *    · tokens CSRF
 *    · validación y guardado de la fotografía del contacto
 * ============================================================================
 */

declare(strict_types=1);

/** Carpeta física donde se guardan las fotografías subidas. */
const RUTA_ARCHIVOS = __DIR__ . '/../archivos';
/** Carpeta pública (URL relativa) de las fotografías. */
const URL_ARCHIVOS = 'archivos/';
/** Tamaño máximo permitido para una fotografía: 2 MB. */
const MAX_FOTO_BYTES = 2 * 1024 * 1024;
/** Formatos de imagen aceptados (MIME real verificado con finfo). */
const MIMES_PERMITIDOS = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

/* ---------------------------------------------------------------------------
 * Sesión
 * ------------------------------------------------------------------------- */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------------------------------------------------------------------------
 * Salida segura
 * ------------------------------------------------------------------------- */

/**
 * Escapa cualquier valor antes de imprimirlo en HTML.
 * Evita XSS (Cross Site Scripting) convirtiendo < > " ' & en entidades.
 */
function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------------------------------------------------------------------------
 * Mensajes flash (se muestran una sola vez, después de un redirect)
 * ------------------------------------------------------------------------- */

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function obtener_flash(): array
{
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensajes;
}

/* ---------------------------------------------------------------------------
 * Redirecciones
 * ------------------------------------------------------------------------- */

function redirigir(string $destino): never
{
    header('Location: ' . $destino);
    exit;
}

/* ---------------------------------------------------------------------------
 * Protección CSRF
 * ------------------------------------------------------------------------- */

/** Devuelve el token CSRF de la sesión (lo crea la primera vez). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Campo oculto listo para insertar dentro de un <form>. */
function csrf_campo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Comprueba el token recibido por POST con comparación en tiempo constante. */
function csrf_valido(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* ---------------------------------------------------------------------------
 * Datos de formulario
 * ------------------------------------------------------------------------- */

/** Recupera un valor enviado por POST ya recortado. */
function post(string $campo): string
{
    return trim((string) ($_POST[$campo] ?? ''));
}

/** Recupera un valor "antiguo" para repintar el formulario tras un error. */
/** Recupera un valor "antiguo" para repintar el formulario tras un error. */
function viejo(string $campo, string $porDefecto = ''): string
{
    static $valores = null;
    if ($valores === null) {               // la primera llamada los consume
        $valores = $_SESSION['form_old'] ?? [];
        unset($_SESSION['form_old']);
    }
    return (string) ($valores[$campo] ?? $porDefecto);
}

/** Errores de validación pendientes (se consumen al pintar el formulario). */
function errores_formulario(): array
{
    $errores = $_SESSION['form_errores'] ?? [];
    unset($_SESSION['form_errores']);
    return $errores;
}

/** Guarda valores y errores para repintar el formulario luego de un redirect. */
function conservar_formulario(array $valores, array $errores): void
{
    $_SESSION['form_old']     = $valores;
    $_SESSION['form_errores'] = $errores;
}

/** Limpia los datos temporales de formulario (tras un guardado exitoso). */
function limpiar_formulario(): void
{
    unset($_SESSION['form_old'], $_SESSION['form_errores']);
}

/* ---------------------------------------------------------------------------
 * Utilidades de texto y archivos (compatibles con hostings sin mbstring/fileinfo)
 * ------------------------------------------------------------------------- */

/** Longitud de una cadena en caracteres (usa mbstring si está disponible). */
function longitud(string $texto): int
{
    return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
}

/** Primer carácter de una cadena, en mayúsculas. */
function inicial(string $texto): string
{
    $letra = function_exists('mb_substr') ? mb_substr($texto, 0, 1, 'UTF-8') : substr($texto, 0, 1);
    return function_exists('mb_strtoupper') ? mb_strtoupper($letra, 'UTF-8') : strtoupper($letra);
}

/**
 * Devuelve el MIME real de un archivo. Usa finfo (extensión fileinfo) y, si el
 * hosting no la tiene habilitada, recurre a getimagesize().
 */
function mime_de_archivo(string $ruta): string
{
    if (class_exists('finfo')) {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->file($ruta);
    }
    $info = @getimagesize($ruta);
    return is_array($info) && isset($info['mime']) ? (string) $info['mime'] : '';
}

/* ---------------------------------------------------------------------------
 * Validación de los datos del contacto (validación de SERVIDOR)
 * ------------------------------------------------------------------------- */

/**
 * Valida los campos recibidos por POST.
 *
 * @return array{0: array<string,string>, 1: array<string,string>}
 *         [datos limpios, lista de errores campo => mensaje]
 */
function validar_contacto(array $entrada, bool $fotoObligatoria): array
{
    $datos = [
        'nombre'    => trim((string) ($entrada['nombre'] ?? '')),
        'apellido'  => trim((string) ($entrada['apellido'] ?? '')),
        'telefono'  => trim((string) ($entrada['telefono'] ?? '')),
        'email'     => trim((string) ($entrada['email'] ?? '')),
        'direccion' => trim((string) ($entrada['direccion'] ?? '')),
        'notas'     => trim((string) ($entrada['notas'] ?? '')),
    ];
    $errores = [];

    // --- nombre -----------------------------------------------------------
    if ($datos['nombre'] === '') {
        $errores['nombre'] = 'El nombre es obligatorio.';
    } elseif (longitud($datos['nombre']) > 100) {
        $errores['nombre'] = 'El nombre no puede superar los 100 caracteres.';
    }

    // --- apellido ---------------------------------------------------------
    if ($datos['apellido'] === '') {
        $errores['apellido'] = 'El apellido es obligatorio.';
    } elseif (longitud($datos['apellido']) > 100) {
        $errores['apellido'] = 'El apellido no puede superar los 100 caracteres.';
    }

    // --- teléfono (obligatorio y con formato válido) ----------------------
    if ($datos['telefono'] === '') {
        $errores['telefono'] = 'El teléfono es obligatorio.';
    } elseif (!preg_match('/^[0-9+\-()\s]{7,30}$/', $datos['telefono'])) {
        $errores['telefono'] = 'El teléfono solo admite números, espacios y los signos + - ( ). Debe tener entre 7 y 30 caracteres.';
    }

    // --- email (opcional, pero si viene debe ser válido) ------------------
    if ($datos['email'] !== '') {
        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores['email'] = 'El correo electrónico no tiene un formato válido.';
        } elseif (longitud($datos['email']) > 150) {
            $errores['email'] = 'El correo no puede superar los 150 caracteres.';
        }
    }

    // --- dirección / notas (opcionales, con límite de longitud) -----------
    if (longitud($datos['direccion']) > 255) {
        $errores['direccion'] = 'La dirección no puede superar los 255 caracteres.';
    }
    if (longitud($datos['notas']) > 2000) {
        $errores['notas'] = 'Las notas no pueden superar los 2000 caracteres.';
    }

    // --- fotografía -------------------------------------------------------
    $hayArchivo = isset($_FILES['foto'])
        && is_array($_FILES['foto'])
        && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($fotoObligatoria && !$hayArchivo) {
        $errores['foto'] = 'La fotografía es obligatoria.';
    }

    if ($hayArchivo) {
        $error = (int) $_FILES['foto']['error'];
        if ($error !== UPLOAD_ERR_OK) {
            $errores['foto'] = match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen excede el tamaño permitido (2 MB).',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'No fue posible guardar la imagen en el servidor.',
                default => 'Ocurrió un error al subir la imagen. Intente de nuevo.',
            };
        } elseif ((int) $_FILES['foto']['size'] > MAX_FOTO_BYTES) {
            $errores['foto'] = 'La imagen excede el tamaño permitido (2 MB).';
        } elseif (!is_dir(RUTA_ARCHIVOS) || !is_writable(RUTA_ARCHIVOS)) {
            $errores['foto'] = 'La carpeta de archivos no tiene permisos de escritura.';
        } else {
            // Se verifica el contenido real del archivo, no la extensión.
            $mime = mime_de_archivo($_FILES['foto']['tmp_name']);
            if (!isset(MIMES_PERMITIDOS[$mime])) {
                $errores['foto'] = 'Formato no permitido. Use JPG, PNG, WEBP o GIF.';
            } elseif (@getimagesize($_FILES['foto']['tmp_name']) === false) {
                $errores['foto'] = 'El archivo enviado no es una imagen válida.';
            }
        }
    }

    return [$datos, $errores];
}

/* ---------------------------------------------------------------------------
 * Almacenamiento de la fotografía
 * ------------------------------------------------------------------------- */

/**
 * Mueve la imagen subida a /archivos con un nombre único y seguro.
 *
 * @return string Nombre del archivo guardado (se almacena en la BD).
 * @throws RuntimeException cuando el archivo no se puede mover.
 */
function guardar_foto(): string
{
    $mime   = mime_de_archivo($_FILES['foto']['tmp_name']);
    $ext    = MIMES_PERMITIDOS[$mime] ?? 'jpg';
    $nombre = 'contacto_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destino = RUTA_ARCHIVOS . '/' . $nombre;

    if (!is_dir(RUTA_ARCHIVOS)) {
        mkdir(RUTA_ARCHIVOS, 0755, true);
    }

    if (!move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
        throw new RuntimeException('No se pudo mover la imagen al destino.');
    }

    @chmod($destino, 0644);
    return $nombre;
}

/** Elimina del disco una fotografía anterior (si existe y está dentro de /archivos). */
function borrar_foto(?string $archivo): void
{
    if (!$archivo) {
        return;
    }
    $ruta = RUTA_ARCHIVOS . '/' . basename($archivo);
    if (is_file($ruta)) {
        @unlink($ruta);
    }
}

/* ---------------------------------------------------------------------------
 * Consultas de apoyo
 * ------------------------------------------------------------------------- */

/** Busca un contacto por su id. Devuelve null si no existe. */
function obtener_contacto(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, nombre, apellido, telefono, email, direccion, notas, foto, creado_en
           FROM contactos
          WHERE id = :id
          LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $contacto = $stmt->fetch();
    return $contacto ?: null;
}

/** Lee un id numérico desde GET/POST. Devuelve 0 cuando no es válido. */
function leer_id(mixed $valor): int
{
    return filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
}

/** Iniciales del contacto para el avatar de respaldo. */
function iniciales(array $contacto): string
{
    return inicial((string) $contacto['nombre']) . inicial((string) $contacto['apellido']);
}

