<?php
/**
 * ============================================================================
 *  index.php  ·  READ (listado y búsqueda de contactos)
 * ============================================================================
 *  Muestra los contactos registrados en una rejilla de tarjetas e incluye un
 *  buscador por nombre, apellido, teléfono o correo. La consulta se construye
 *  con sentencias preparadas de PDO y paginación de 9 registros por página.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/funciones.php';

/* ---------------------------------------------------------------------------
 * 1. Parámetros de búsqueda y paginación
 * ------------------------------------------------------------------------- */
$busqueda   = trim((string) ($_GET['q'] ?? ''));
$pagina     = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina  = 9;

/* ---------------------------------------------------------------------------
 * 2. Filtro SQL (seguro: los valores viajan como parámetros, no concatenados)
 * ------------------------------------------------------------------------- */
$condicion = '';
$parametros = [];

if ($busqueda !== '') {
    $condicion = 'WHERE nombre LIKE :nombre
                     OR apellido LIKE :apellido
                     OR telefono LIKE :telefono
                     OR email LIKE :email';
    $comodin = '%' . $busqueda . '%';
    $parametros = [
        ':nombre'   => $comodin,
        ':apellido' => $comodin,
        ':telefono' => $comodin,
        ':email'    => $comodin,
    ];
}

/* ---------------------------------------------------------------------------
 * 3. Total de registros (para calcular las páginas)
 * ------------------------------------------------------------------------- */
$stmt = $pdo->prepare("SELECT COUNT(*) FROM contactos $condicion");
$stmt->execute($parametros);
$total = (int) $stmt->fetchColumn();

$totalPaginas = max(1, (int) ceil($total / $porPagina));
$pagina       = min($pagina, $totalPaginas);
$desplazamiento = ($pagina - 1) * $porPagina;

/* ---------------------------------------------------------------------------
 * 4. Consulta de la página actual (LIMIT/OFFSET con parámetros enteros)
 * ------------------------------------------------------------------------- */
$sql = "SELECT id, nombre, apellido, telefono, email, foto
          FROM contactos
          $condicion
      ORDER BY apellido ASC, nombre ASC
         LIMIT :limite OFFSET :desplazamiento";

$stmt = $pdo->prepare($sql);
foreach ($parametros as $marcador => $valor) {
    $stmt->bindValue($marcador, $valor, PDO::PARAM_STR);
}
$stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':desplazamiento', $desplazamiento, PDO::PARAM_INT);
$stmt->execute();
$contactos = $stmt->fetchAll();

$titulo = $busqueda !== '' ? 'Búsqueda: ' . $busqueda : 'Contactos';
require __DIR__ . '/includes/header.php';
?>

<div class="encabezado-pagina">
    <div>
        <h1>Agenda de contactos</h1>
        <p><?= $total ?> contacto<?= $total === 1 ? '' : 's' ?> registrado<?= $total === 1 ? '' : 's' ?> en la base de datos.</p>
    </div>
    <a class="boton" href="crear.php">➕ Nuevo contacto</a>
</div>

<!-- Buscador: método GET para que la búsqueda quede en la URL -->
<form class="buscador" action="index.php" method="get" role="search">
    <div class="buscador__campo">
        <span aria-hidden="true">🔍</span>
        <input type="search" name="q" id="q" value="<?= e($busqueda) ?>"
               placeholder="Buscar por nombre, apellido, teléfono o correo…"
               aria-label="Buscar contactos" maxlength="100">
    </div>
    <button class="boton" type="submit">Buscar</button>
    <?php if ($busqueda !== ''): ?>
        <a class="boton boton--secundario" href="index.php">Limpiar</a>
    <?php endif; ?>
</form>

<?php if ($busqueda !== ''): ?>
    <p class="resumen-busqueda">
        Resultados para <strong>“<?= e($busqueda) ?>”</strong>:
        <?= $total ?> encontrado<?= $total === 1 ? '' : 's' ?>.
    </p>
<?php endif; ?>

<?php if ($contactos): ?>
    <ul class="rejilla">
        <?php foreach ($contactos as $contacto): ?>
            <li class="contacto">
                <img class="contacto__foto"
                     src="<?= e(URL_ARCHIVOS . $contacto['foto']) ?>"
                     alt="Fotografía de <?= e($contacto['nombre'] . ' ' . $contacto['apellido']) ?>"
                     width="68" height="68" loading="lazy">

                <div class="contacto__cuerpo">
                    <h2 class="contacto__nombre">
                        <a href="ver.php?id=<?= (int) $contacto['id'] ?>">
                            <?= e($contacto['nombre'] . ' ' . $contacto['apellido']) ?>
                        </a>
                    </h2>
                    <p class="contacto__dato">
                        <span aria-hidden="true">📞</span><?= e($contacto['telefono']) ?>
                    </p>
                    <?php if (!empty($contacto['email'])): ?>
                        <p class="contacto__dato">
                            <span aria-hidden="true">✉️</span><?= e($contacto['email']) ?>
                        </p>
                    <?php endif; ?>

                    <div class="contacto__acciones">
                        <a class="boton boton--secundario" href="ver.php?id=<?= (int) $contacto['id'] ?>">Ver</a>
                        <a class="boton boton--secundario" href="editar.php?id=<?= (int) $contacto['id'] ?>">Editar</a>
                        <a class="boton boton--peligro" href="eliminar.php?id=<?= (int) $contacto['id'] ?>">Eliminar</a>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($totalPaginas > 1): ?>
        <nav class="paginacion" aria-label="Paginación de contactos">
            <?php if ($pagina > 1): ?>
                <a href="?pagina=<?= $pagina - 1 ?><?= $busqueda !== '' ? '&q=' . urlencode($busqueda) : '' ?>"
                   aria-label="Página anterior">←</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <?php if ($i === $pagina): ?>
                    <span class="actual" aria-current="page"><?= $i ?></span>
                <?php else: ?>
                    <a href="?pagina=<?= $i ?><?= $busqueda !== '' ? '&q=' . urlencode($busqueda) : '' ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($pagina < $totalPaginas): ?>
                <a href="?pagina=<?= $pagina + 1 ?><?= $busqueda !== '' ? '&q=' . urlencode($busqueda) : '' ?>"
                   aria-label="Página siguiente">→</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

<?php else: ?>
    <div class="vacio">
        <span class="vacio__icono" aria-hidden="true"><?= $busqueda !== '' ? '🔍' : '📇' ?></span>
        <?php if ($busqueda !== ''): ?>
            <h2>Sin resultados</h2>
            <p>No se encontró ningún contacto que coincida con <strong>“<?= e($busqueda) ?>”</strong>.
               Intente con otro nombre, teléfono o correo.</p>
            <a class="boton boton--secundario" href="index.php">Ver todos los contactos</a>
        <?php else: ?>
            <h2>La agenda está vacía</h2>
            <p>Registre su primer contacto para comenzar a utilizar la agenda.</p>
            <a class="boton" href="crear.php">➕ Crear el primer contacto</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
