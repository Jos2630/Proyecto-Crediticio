<?php
function pagina_inicio(string $titulo, string $activo = 'Créditos'): void
{
    $nombre = $_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Usuario';
    $rol    = $_SESSION['rol'] ?? '';
    $menu = [
        ['Inicio',            'bi-house-door-fill',        '../../index.html'],
        ['Clientes',          'bi-people-fill',            '../integrante1_clientes/menu_principal.php'],
        ['Solicitudes',       'bi-file-earmark-text-fill', '../integrante2_solicitudes/menu_principal.php'],
        ['Créditos',          'bi-bank2',                  'menu_principal.php'],
        ['Pagos',             'bi-cash-coin',              '../integrante4_pagos/menu_principal.php'],
        ['Reprogramaciones',  'bi-calendar2-check',        '../integrante5_seguridad/menu_principal.php'],
        ['Refinanciamientos', 'bi-arrow-repeat',           '../integrante5_seguridad/menu_principal.php'],
        ['Reportes',          'bi-bar-chart-fill',         '../../reportes/'],
        ['Usuarios',          'bi-person-gear',            '../integrante5_seguridad/menu_principal.php'],
    ];
    ?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> | Sistema de Gestión de Créditos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../assets/css/credito.css" rel="stylesheet">
</head>
<body>
<header class="topbar">
    <button class="btn btn-link text-white p-0 d-lg-none" id="btnMenu" type="button"><i class="bi bi-list fs-3"></i></button>
    <span class="fw-semibold fs-5">Sistema de Gestión de Créditos</span>
    <span class="ms-auto d-flex align-items-center gap-2">
        <i class="bi bi-person-circle fs-4"></i>
        <span class="lh-sm small"><?= e($nombre) ?><br><span class="text-white-50"><?= e($rol) ?></span></span>
    </span>
</header>
<aside class="sidebar">
    <?php foreach ($menu as [$texto, $icono, $href]): ?>
        <a href="<?= e($href) ?>" class="<?= $texto === $activo ? 'activo' : '' ?>"><i class="bi <?= e($icono) ?>"></i><?= e($texto) ?></a>
    <?php endforeach; ?>
</aside>
<main class="contenido">
    <?= flash_html() ?>
<?php
}

function pagina_fin(string $js = ''): void
{
    ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('btnMenu')?.addEventListener('click', () => document.body.classList.toggle('menu-abierto'));
<?= $js ?>
</script>
</body>
</html>
<?php
}