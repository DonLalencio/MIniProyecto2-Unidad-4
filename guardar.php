<?php
/**
 * ============================================================================
 *  guardar.php  ·  CREATE (lógica)
 * ============================================================================
 *  Procesa el formulario de crear.php:
 *    1. Solo acepta POST.
 *    2. Verifica el token CSRF.
 *    3. Valida los datos en el servidor (validar_contacto()).
 *    4. Guarda la fotografía en /archivos con un nombre único.
 *    5. Ejecuta el INSERT con una sentencia preparada de PDO.
 *    6. Redirige (patrón POST → Redirect → GET) para evitar reenvíos.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/funciones.php';

/* ---- 1. Solo POST ------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Acceso no válido: el formulario debe enviarse desde crear.php.');
    redirigir('index.php');
}

/* ---- 2. Token CSRF ------------------------------------------------------ */
if (!csrf_valido($_POST['csrf_token'] ?? null)) {
    flash('error', 'La sesión del formulario expiró. Vuelva a intentarlo.');
    redirigir('crear.php');
}

/* ---- 3. Validación de servidor ----------------------------------------- */
[$datos, $errores] = validar_contacto($_POST, true);

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
    redirigir('crear.php');
}

/* ---- 4. Fotografía ------------------------------------------------------ */
try {
    $foto = guardar_foto();
} catch (RuntimeException $e) {
    error_log('[agenda] Error al guardar la fotografía: ' . $e->getMessage());
    conservar_formulario($datos, ['foto' => 'No fue posible guardar la fotografía en el servidor.']);
    flash('error', 'La fotografía no pudo guardarse. Intente con otra imagen.');
    redirigir('crear.php');
}

/* ---- 5. INSERT con sentencia preparada --------------------------------- */
try {
    $sql = 'INSERT INTO contactos (nombre, apellido, telefono, email, direccion, notas, foto)
            VALUES (:nombre, :apellido, :telefono, :email, :direccion, :notas, :foto)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nombre'    => $datos['nombre'],
        ':apellido'  => $datos['apellido'],
        ':telefono'  => $datos['telefono'],
        ':email'     => $datos['email'] !== '' ? $datos['email'] : null,
        ':direccion' => $datos['direccion'] !== '' ? $datos['direccion'] : null,
        ':notas'     => $datos['notas'] !== '' ? $datos['notas'] : null,
        ':foto'      => $foto,
    ]);

    $nuevoId = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    // El detalle técnico va al log, nunca al navegador.
    error_log('[agenda] Error al insertar contacto: ' . $e->getMessage());
    borrar_foto($foto);

    conservar_formulario($datos, []);
    flash('error', 'No fue posible guardar el contacto. Intente de nuevo en unos momentos.');
    redirigir('crear.php');
}

/* ---- 6. Éxito ----------------------------------------------------------- */
limpiar_formulario();
flash('exito', 'El contacto ' . $datos['nombre'] . ' ' . $datos['apellido'] . ' se guardó correctamente.');
redirigir('ver.php?id=' . $nuevoId);
