<?php
/**
 * ============================================================================
 *  editar.php  ·  UPDATE (formulario)
 * ============================================================================
 *  Carga el contacto solicitado (SELECT con sentencia preparada), muestra el
 *  formulario con los datos actuales y permite cambiar la fotografía.
 *  Si la validación de actualizar.php falla, se repintan los valores enviados.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/funciones.php';

$id = leer_id($_GET['id'] ?? null);

if ($id === 0) {
    flash('error', 'El contacto solicitado no es válido.');
    redirigir('index.php');
}

$contacto = obtener_contacto($pdo, $id);

if ($contacto === null) {
    flash('error', 'El contacto que intenta editar no existe.');
    redirigir('index.php');
}

$errores = errores_formulario();

/* Valor del campo: lo enviado previamente (si hubo error) o el dato de la BD. */
$valor = static fn (string $campo): string => viejo($campo, (string) ($contacto[$campo] ?? ''));

$titulo = 'Editar contacto';
require __DIR__ . '/includes/header.php';
?>

<div class="encabezado-pagina">
    <div>
        <h1>Editar contacto</h1>
        <p>Modifique los datos de <strong><?= e($contacto['nombre'] . ' ' . $contacto['apellido']) ?></strong>.</p>
    </div>
    <a class="boton boton--secundario" href="ver.php?id=<?= (int) $contacto['id'] ?>">← Volver al detalle</a>
</div>

<?php if ($errores): ?>
    <div class="resumen-errores" role="alert">
        <strong>No se pudieron aplicar los cambios:</strong>
        <ul>
            <?php foreach ($errores as $mensaje): ?>
                <li><?= e($mensaje) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="tarjeta">
    <form class="formulario" action="actualizar.php" method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="id" value="<?= (int) $contacto['id'] ?>">

        <div class="formulario__rejilla">
            <div class="campo">
                <label for="nombre">Nombre <span class="obligatorio">*</span></label>
                <input type="text" id="nombre" name="nombre" value="<?= e($valor('nombre')) ?>"
                       maxlength="100" required autocomplete="given-name"
                       <?= isset($errores['nombre']) ? 'aria-invalid="true"' : '' ?>>
                <?php if (isset($errores['nombre'])): ?>
                    <p class="campo__error"><?= e($errores['nombre']) ?></p>
                <?php endif; ?>
            </div>

            <div class="campo">
                <label for="apellido">Apellido <span class="obligatorio">*</span></label>
                <input type="text" id="apellido" name="apellido" value="<?= e($valor('apellido')) ?>"
                       maxlength="100" required autocomplete="family-name"
                       <?= isset($errores['apellido']) ? 'aria-invalid="true"' : '' ?>>
                <?php if (isset($errores['apellido'])): ?>
                    <p class="campo__error"><?= e($errores['apellido']) ?></p>
                <?php endif; ?>
            </div>

            <div class="campo">
                <label for="telefono">Teléfono <span class="obligatorio">*</span></label>
                <input type="tel" id="telefono" name="telefono" value="<?= e($valor('telefono')) ?>"
                       maxlength="30" required autocomplete="tel"
                       pattern="[0-9+\-()\s]{7,30}"
                       title="Entre 7 y 30 caracteres: números, espacios y los signos + - ( )"
                       <?= isset($errores['telefono']) ? 'aria-invalid="true"' : '' ?>>
                <p class="campo__ayuda">Obligatorio. Solo números, espacios y + - ( ).</p>
                <?php if (isset($errores['telefono'])): ?>
                    <p class="campo__error"><?= e($errores['telefono']) ?></p>
                <?php endif; ?>
            </div>

            <div class="campo">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" value="<?= e($valor('email')) ?>"
                       maxlength="150" autocomplete="email"
                       <?= isset($errores['email']) ? 'aria-invalid="true"' : '' ?>>
                <?php if (isset($errores['email'])): ?>
                    <p class="campo__error"><?= e($errores['email']) ?></p>
                <?php endif; ?>
            </div>

            <div class="campo campo--ancho">
                <label for="direccion">Dirección</label>
                <input type="text" id="direccion" name="direccion" value="<?= e($valor('direccion')) ?>"
                       maxlength="255" autocomplete="street-address">
                <?php if (isset($errores['direccion'])): ?>
                    <p class="campo__error"><?= e($errores['direccion']) ?></p>
                <?php endif; ?>
            </div>

            <div class="campo campo--ancho">
                <label for="notas">Notas</label>
                <textarea id="notas" name="notas" maxlength="2000"><?= e($valor('notas')) ?></textarea>
                <?php if (isset($errores['notas'])): ?>
                    <p class="campo__error"><?= e($errores['notas']) ?></p>
                <?php endif; ?>
            </div>

            <div class="campo campo--ancho">
                <label for="foto">Fotografía</label>
                <div class="vista-foto">
                    <img src="<?= e(URL_ARCHIVOS . $contacto['foto']) ?>"
                         alt="Fotografía actual de <?= e($contacto['nombre']) ?>" width="84" height="84">
                    <div class="campo">
                        <input type="file" id="foto" name="foto"
                               accept="image/jpeg,image/png,image/webp,image/gif"
                               <?= isset($errores['foto']) ? 'aria-invalid="true"' : '' ?>>
                        <p class="campo__ayuda">Opcional. Si no elige otra imagen se conserva la actual. Máximo 2 MB.</p>
                        <?php if (isset($errores['foto'])): ?>
                            <p class="campo__error"><?= e($errores['foto']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="formulario__acciones">
            <a class="boton boton--secundario" href="ver.php?id=<?= (int) $contacto['id'] ?>">Cancelar</a>
            <button class="boton" type="submit">💾 Actualizar contacto</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
