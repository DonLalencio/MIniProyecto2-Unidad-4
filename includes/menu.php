<?php
/**
 * includes/menu.php · Menú de navegación común.
 * La opción activa se calcula con el nombre del archivo actual.
 */
$archivoActual = basename((string) $_SERVER['PHP_SELF']);

$opciones = [
    'index.php'  => ['texto' => 'Contactos', 'icono' => '👥'],
    'crear.php'  => ['texto' => 'Nuevo contacto', 'icono' => '➕'],
];
?>
<nav class="menu" aria-label="Navegación principal">
    <?php foreach ($opciones as $archivo => $opcion): ?>
        <?php $activo = $archivoActual === $archivo; ?>
        <a href="<?= e($archivo) ?>"
           class="menu__enlace<?= $activo ? ' menu__enlace--activo' : '' ?>"
           <?= $activo ? 'aria-current="page"' : '' ?>>
            <span aria-hidden="true"><?= $opcion['icono'] ?></span>
            <?= e($opcion['texto']) ?>
        </a>
    <?php endforeach; ?>
</nav>
