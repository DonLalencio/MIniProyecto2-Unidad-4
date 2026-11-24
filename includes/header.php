<?php
/**
 * includes/header.php · Cabecera común de todas las páginas.
 * Variables opcionales: $titulo (título de la pestaña) y $subtitulo.
 */
if (!isset($titulo)) {
    $titulo = 'Agenda de Contactos';
}
$flash = obtener_flash();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Agenda de contactos desarrollada con PHP, PDO y MySQL. CRUD completo.">
    <title><?= e($titulo) ?> · Agenda de Contactos</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📇</text></svg>">
</head>
<body>

<a class="salto-contenido" href="#contenido">Saltar al contenido</a>

<header class="cabecera">
    <div class="cabecera__interior">
        <a class="marca" href="index.php">
            <span class="marca__icono" aria-hidden="true">📇</span>
            <span class="marca__texto">
                <strong>Agenda de Contactos</strong>
                <small>PHP · PDO · MySQL</small>
            </span>
        </a>
        <?php require __DIR__ . '/menu.php'; ?>
    </div>
</header>

<main class="contenido" id="contenido">

<?php if ($flash): ?>
    <div class="avisos" role="status" aria-live="polite">
        <?php foreach ($flash as $aviso): ?>
            <div class="aviso aviso--<?= e($aviso['tipo']) ?>">
                <span class="aviso__icono" aria-hidden="true">
                    <?= $aviso['tipo'] === 'exito' ? '✓' : ($aviso['tipo'] === 'error' ? '!' : 'i') ?>
                </span>
                <p><?= e($aviso['mensaje']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
