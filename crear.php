<?php
/**
 * ============================================================================
 *  crear.php  ·  CREATE (formulario)
 * ============================================================================
 *  Muestra el formulario HTML5 para registrar un contacto. Los errores de
 *  validación del servidor y los valores escritos previamente se recuperan de
 *  la sesión (los coloca guardar.php cuando algo falla).
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/funciones.php';

$errores = errores_formulario();

$titulo = 'Nuevo contacto';
require __DIR__ . '/includes/header.php';
?>

<div class="encabezado-pagina">
    <div>
        <h1>Nuevo contacto</h1>
        <p>Complete los datos. Los campos marcados con <span style="color:#dc2626">*</span> son obligatorios.</p>
    </div>
    <a class="boton boton--secundario" href="index.php">← Volver a la agenda</a>
</div>

<?php if ($errores): ?>
    <div class="resumen-errores" role="alert">
        <strong>No se pudo guardar el contacto:</strong>
        <ul>
            <?php foreach ($errores as $mensaje): ?>
                <li><?= e($mensaje) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="tarjeta">
    <!-- multipart/form-data es obligatorio para enviar archivos -->
    <form class="formulario" action="guardar.php" method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_campo() ?>

        <div class="formulario__rejilla">
            <div class="campo">
                <label for="nombre">Nombre <span class="obligatorio">*</span></label>
                <input type="text" id="nombre" name="nombre" value="<?= e(viejo('nombre')) ?>"
                       maxlength="100" required autocomplete="given-name" autofocus
                       <?= isset($errores['nombre']) ? 'aria-invalid="true"' : '' ?>>
                <?php if (isset($errores['nombre'])): ?>
                    <p class="campo__error"><?= e($errores['nombre']) ?></p>
                <?php endif; ?>
            </div>

            <div class="campo">
                <label for="apellido">Apellido <span class="obligatorio">*</span></label>
                <input type="text" id="apellido" name="apellido" value="<?= e(viejo('apellido')) ?>"
                       maxlength="100" required autocomplete="family-name"
                       <?= isset($errores['apellido']) ? 'aria-invalid="true"' : '' ?>>
                <?php if (isset($errores['apellido'])): ?>
                    <p class="campo__error"><?= e($errores['apellido']) ?></p>
                <?php endif; ?>
            </div>

            <div class="campo">
                <label for="telefono">Teléfono <span class="obligatorio">*</span></label>
                <input type="tel" id="telefono" name="telefono" value="<?= e(viejo('telefono')) ?>"
                       maxlength="30" required autocomplete="tel"
                       placeholder="Ej. 7771-2345"
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
                <input type="email" id="email" name="email" value="<?= e(viejo('email')) ?>"
                       maxlength="150" autocomplete="email" placeholder="nombre@dominio.com"
                       <?= isset($errores['email']) ? 'aria-invalid="true"' : '' ?>>
                <p class="campo__ayuda">Opcional, pero debe tener un formato válido.</p>
                <?php if (isset($errores['email'])): ?>
                    <p class="campo__error"><?= e($errores['email']) ?></p>
                <?php endif; ?>
            </div>

            <div class="campo campo--ancho">
                <label for="direccion">Dirección</label>
                <input type="text" id="direccion" name="direccion" value="<?= e(viejo('direccion')) ?>"
                       maxlength="255" autocomplete="street-address" placeholder="Opcional">
                <?php if (isset($errores['direccion'])): ?>
                    <p class="campo__error"><?= e($errores['direccion']) ?></p>
                <?php endif; ?>
            </div>

            <div class="campo campo--ancho">
                <label for="notas">Notas</label>
                <textarea id="notas" name="notas" maxlength="2000"
                          placeholder="Información adicional del contacto (opcional)"><?= e(viejo('notas')) ?></textarea>
                <?php if (isset($errores['notas'])): ?>
                    <p class="campo__error"><?= e($errores['notas']) ?></p>
                <?php endif; ?>
            </div>

            <div class="campo campo--ancho">
                <label for="foto">Fotografía <span class="obligatorio">*</span></label>
                <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp,image/gif" required
                       <?= isset($errores['foto']) ? 'aria-invalid="true"' : '' ?>>
                <p class="campo__ayuda">Obligatoria. Formatos JPG, PNG, WEBP o GIF · máximo 2 MB.</p>
                <?php if (isset($errores['foto'])): ?>
                    <p class="campo__error"><?= e($errores['foto']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="formulario__acciones">
            <a class="boton boton--secundario" href="index.php">Cancelar</a>
            <button class="boton" type="submit">💾 Guardar contacto</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
