<?php
/**
 * ============================================================================
 *  eliminar.php  ·  DELETE
 * ============================================================================
 *  Flujo de eliminación en dos pasos:
 *    1. GET  → muestra la pantalla de confirmación (no borra nada).
 *    2. POST → después de validar el token CSRF, ejecuta el DELETE con una
 *              sentencia preparada y elimina también la fotografía del disco.
 *  Se usa POST para que el borrado no pueda dispararse con un simple enlace.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/funciones.php';

$id = leer_id($_GET['id'] ?? $_POST['id'] ?? null);

if ($id === 0) {
    flash('error', 'El contacto solicitado no es válido.');
    redirigir('index.php');
}

$contacto = obtener_contacto($pdo, $id);

if ($contacto === null) {
    flash('error', 'El contacto que intenta eliminar no existe o ya fue eliminado.');
    redirigir('index.php');
}

/* ---------------------------------------------------------------------------
 * POST → confirmación aceptada: se ejecuta el DELETE
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_valido($_POST['csrf_token'] ?? null)) {
        flash('error', 'La confirmación expiró. Vuelva a intentarlo.');
        redirigir('eliminar.php?id=' . $id);
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM contactos WHERE id = :id');
        $stmt->execute([':id' => $id]);
    } catch (PDOException $e) {
        error_log('[agenda] Error al eliminar contacto: ' . $e->getMessage());
        flash('error', 'No fue posible eliminar el contacto. Intente de nuevo en unos momentos.');
        redirigir('eliminar.php?id=' . $id);
    }

    // La fotografía ya no se necesita: se borra del servidor.
    borrar_foto((string) $contacto['foto']);

    flash('exito', 'El contacto ' . $contacto['nombre'] . ' ' . $contacto['apellido'] . ' fue eliminado de la agenda.');
    redirigir('index.php');
}

/* ---------------------------------------------------------------------------
 * GET → pantalla de confirmación con el resumen del contacto
 * ------------------------------------------------------------------------- */
$titulo = 'Eliminar contacto';
require __DIR__ . '/includes/header.php';
?>

<div class="tarjeta confirmar">
    <div class="confirmar__icono" aria-hidden="true">⚠️</div>

    <img class="confirmar__foto"
         src="<?= e(URL_ARCHIVOS . $contacto['foto']) ?>"
         alt="Fotografía de <?= e($contacto['nombre'] . ' ' . $contacto['apellido']) ?>"
         width="96" height="96">

    <h1>¿Eliminar este contacto?</h1>
    <p>
        Está a punto de eliminar a
        <strong><?= e($contacto['nombre'] . ' ' . $contacto['apellido']) ?></strong>
        (<?= e($contacto['telefono']) ?>).
        Esta acción no se puede deshacer y la fotografía también será eliminada.
    </p>

    <!-- La eliminación se ejecuta mediante POST + token CSRF -->
    <form action="eliminar.php?id=<?= (int) $contacto['id'] ?>" method="post" class="confirmar__acciones">
        <?= csrf_campo() ?>
        <input type="hidden" name="id" value="<?= (int) $contacto['id'] ?>">
        <a class="boton boton--secundario" href="ver.php?id=<?= (int) $contacto['id'] ?>">Cancelar</a>
        <button class="boton boton--peligro" type="submit">🗑️ Sí, eliminar contacto</button>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
