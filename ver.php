<?php
/**
 * ============================================================================
 *  ver.php  ·  READ (detalle individual)
 * ============================================================================
 *  Consulta un único contacto por su id mediante una sentencia preparada y
 *  muestra toda su información. Si el id no existe se informa con un mensaje
 *  y se regresa al listado (sin exponer detalles técnicos).
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
    flash('error', 'El contacto solicitado no existe o fue eliminado.');
    redirigir('index.php');
}

$nombreCompleto = $contacto['nombre'] . ' ' . $contacto['apellido'];

$titulo = $nombreCompleto;
require __DIR__ . '/includes/header.php';
?>

<div class="encabezado-pagina">
    <div>
        <h1>Detalle del contacto</h1>
        <p>Ficha completa del registro #<?= (int) $contacto['id'] ?>.</p>
    </div>
    <a class="boton boton--secundario" href="index.php">← Volver a la agenda</a>
</div>

<article class="tarjeta">
    <div class="detalle">
        <div class="detalle__perfil">
            <img class="detalle__foto"
                 src="<?= e(URL_ARCHIVOS . $contacto['foto']) ?>"
                 alt="Fotografía de <?= e($nombreCompleto) ?>"
                 width="280" height="280">
            <h2 class="detalle__nombre"><?= e($nombreCompleto) ?></h2>
            <p class="detalle__rol">Contacto de la agenda</p>
        </div>

        <div>
            <ul class="detalle__lista">
                <li class="detalle__item">
                    <span class="detalle__etiqueta">Teléfono</span>
                    <p class="detalle__valor">
                        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $contacto['telefono'])) ?>">
                            <?= e($contacto['telefono']) ?>
                        </a>
                    </p>
                </li>
                <li class="detalle__item">
                    <span class="detalle__etiqueta">Correo</span>
                    <p class="detalle__valor">
                        <?php if (!empty($contacto['email'])): ?>
                            <a href="mailto:<?= e($contacto['email']) ?>"><?= e($contacto['email']) ?></a>
                        <?php else: ?>
                            <span style="color:#8b91a8">No registrado</span>
                        <?php endif; ?>
                    </p>
                </li>
                <li class="detalle__item">
                    <span class="detalle__etiqueta">Dirección</span>
                    <p class="detalle__valor">
                        <?= $contacto['direccion'] !== null && $contacto['direccion'] !== ''
                            ? e($contacto['direccion'])
                            : '<span style="color:#8b91a8">No registrada</span>' ?>
                    </p>
                </li>
                <li class="detalle__item">
                    <span class="detalle__etiqueta">Notas</span>
                    <p class="detalle__valor detalle__valor--notas">
                        <?= $contacto['notas'] !== null && $contacto['notas'] !== ''
                            ? e($contacto['notas'])
                            : '<span style="color:#8b91a8">Sin notas</span>' ?>
                    </p>
                </li>
                <li class="detalle__item">
                    <span class="detalle__etiqueta">Registrado</span>
                    <p class="detalle__valor">
                        <?= e(date('d/m/Y H:i', strtotime((string) $contacto['creado_en']))) ?>
                    </p>
                </li>
            </ul>

            <div class="detalle__acciones">
                <a class="boton" href="editar.php?id=<?= (int) $contacto['id'] ?>">✏️ Editar contacto</a>
                <a class="boton boton--peligro" href="eliminar.php?id=<?= (int) $contacto['id'] ?>">🗑️ Eliminar</a>
            </div>
        </div>
    </div>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>
