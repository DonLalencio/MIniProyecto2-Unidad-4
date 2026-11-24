<?php
/**
 * ============================================================================
 *  actualizar.php  ·  UPDATE (lógica)
 * ============================================================================
 *  Procesa la edición de un contacto:
 *    · verifica POST + token CSRF + id válido
 *    · valida en el servidor
 *    · si se subió una fotografía nueva, la guarda y borra la anterior
 *    · ejecuta el UPDATE con sentencia preparada
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/funciones.php';

/* ---- 1. POST y token CSRF ---------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Acceso no válido: el formulario debe enviarse desde editar.php.');
    redirigir('index.php');
}

if (!csrf_valido($_POST['csrf_token'] ?? null)) {
    flash('error', 'La sesión del formulario expiró. Vuelva a intentarlo.');
    redirigir('index.php');
}

/* ---- 2. Identificador --------------------------------------------------- */
$id = leer_id($_POST['id'] ?? null);

if ($id === 0) {
    flash('error', 'El contacto indicado no es válido.');
    redirigir('index.php');
}

$contactoActual = obtener_contacto($pdo, $id);

if ($contactoActual === null) {
    flash('error', 'El contacto que intenta actualizar no existe.');
    redirigir('index.php');
}

/* ---- 3. Validación de servidor ----------------------------------------- */
[$datos, $errores] = validar_contacto($_POST, false);

if ($errores) {
    conservar_formulario([
        'nombre'    => (string) ($_POST['nombre'] ?? ''),
        'apellido'  => (string) ($_POST['apellido'] ?? ''),
        'telefono'  => (string) ($_POST['telefono'] ?? ''),
        'email'     => (string) ($_POST['email'] ?? ''),
        'direccion' => (string) ($_POST['direccion'] ?? ''),
        'notas'     => (string) ($_POST['notas'] ?? ''),
    ], $errores);
    flash('error', 'Revise los campos marcados: hay datos incompletos o con formato inválido.');
    redirigir('editar.php?id=' . $id);
}

/* ---- 4. Fotografía (opcional) ------------------------------------------ */
$fotoFinal = $contactoActual['foto'];
$fotoNueva = null;

$hayFotoNueva = isset($_FILES['foto'])
    && is_array($_FILES['foto'])
    && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

if ($hayFotoNueva) {
    try {
        $fotoNueva = guardar_foto();
        $fotoFinal = $fotoNueva;
    } catch (RuntimeException $e) {
        error_log('[agenda] Error al guardar la nueva fotografía: ' . $e->getMessage());
        flash('error', 'La nueva fotografía no pudo guardarse. Intente con otra imagen.');
        redirigir('editar.php?id=' . $id);
    }
}

/* ---- 5. UPDATE con sentencia preparada --------------------------------- */
try {
    $sql = 'UPDATE contactos
               SET nombre    = :nombre,
                   apellido  = :apellido,
                   telefono  = :telefono,
                   email     = :email,
                   direccion = :direccion,
                   notas     = :notas,
                   foto      = :foto
             WHERE id       = :id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nombre'    => $datos['nombre'],
        ':apellido'  => $datos['apellido'],
        ':telefono'  => $datos['telefono'],
        ':email'     => $datos['email'] !== '' ? $datos['email'] : null,
        ':direccion' => $datos['direccion'] !== '' ? $datos['direccion'] : null,
        ':notas'     => $datos['notas'] !== '' ? $datos['notas'] : null,
        ':foto'      => $fotoFinal,
        ':id'        => $id,
    ]);
} catch (PDOException $e) {
    error_log('[agenda] Error al actualizar contacto: ' . $e->getMessage());
    if ($fotoNueva !== null) {
        borrar_foto($fotoNueva); // no dejar archivos huérfanos
    }
    flash('error', 'No fue posible actualizar el contacto. Intente de nuevo en unos momentos.');
    redirigir('editar.php?id=' . $id);
}

/* ---- 6. Solo después de un UPDATE exitoso se borra la foto anterior ---- */
if ($fotoNueva !== null) {
    borrar_foto((string) $contactoActual['foto']);
}

limpiar_formulario();
flash('exito', 'Los datos del contacto se actualizaron correctamente.');
redirigir('ver.php?id=' . $id);
