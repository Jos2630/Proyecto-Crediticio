<?php
/**
 * Header y Navegación Aislada - Módulo Integrante 1 (Bryan Alvarado)
 * Bootstrap 5 + Estilos Corporativos del Sistema de Créditos
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generar token CSRF para formularios seguros
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Función helper para prevenir XSS en todas las salidas
if (!function_exists('e')) {
    function e(?string $valor): string {
        return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// Helper para formato de moneda (Bolivianos)
if (!function_exists('formatoBs')) {
    function formatoBs($monto): string {
        return 'Bs ' . number_format((float)$monto, 2, '.', ',');
    }
}

// Detectar ruta base relativa para enlaces
$nivelProfundidad = substr_count($_SERVER['SCRIPT_NAME'] ?? '', '/');
$rutaBaseModulo = dirname($_SERVER['SCRIPT_NAME'] ?? '');
// Normalizar ruta al módulo 1
if (basename($rutaBaseModulo) === 'clientes' || basename($rutaBaseModulo) === 'tipos_credito' || basename($rutaBaseModulo) === 'historial' || basename($rutaBaseModulo) === 'notificaciones') {
    $urlModulo = '../';
} else {
    $urlModulo = './';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($tituloPagina) ? e($tituloPagina) . ' | ' : '' ?>Módulo 1: Clientes y Datos Iniciales</title>
    
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --azul-principal: #0756a5;
            --azul-oscuro: #063b70;
            --azul-sidebar: #0f2c49;
            --fondo: #f3f7fc;
            --texto: #172b45;
            --acento: #0875e1;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--fondo);
            color: var(--texto);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .barra-superior {
            min-height: 64px;
            padding: 10px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            color: #fff;
            background: linear-gradient(90deg, var(--azul-oscuro), var(--azul-principal));
            box-shadow: 0 2px 10px rgba(10, 43, 78, .18);
        }

        .marca {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            color: #fff;
            text-decoration: none;
        }

        .marca svg { width: 30px; height: 30px; flex-shrink: 0; }

        .badge-modulo {
            background: rgba(255,255,255,0.2);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 12px;
            letter-spacing: 0.5px;
        }

        .datos-usuario {
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: right;
        }

        .avatar {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border: 2px solid rgba(255,255,255,.65);
            border-radius: 50%;
            background: rgba(255,255,255,.16);
            font-weight: 700;
            color: #fff;
        }

        .usuario-nombre { font-size: .92rem; font-weight: 700; color: #fff; }
        .usuario-rol { font-size: .78rem; opacity: .88; color: #d0e4ff; }

        .layout {
            display: flex;
            min-height: calc(100vh - 64px);
        }

        .barra-lateral {
            width: 250px;
            flex-shrink: 0;
            padding: 20px 14px;
            color: #fff;
            background: linear-gradient(180deg, #123a60, var(--azul-sidebar));
        }

        .etiqueta-menu {
            padding: 4px 12px 10px;
            color: #9cb8d6;
            font-size: .74rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .enlace-menu {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 6px;
            padding: 10px 14px;
            color: #e2edf8;
            text-decoration: none;
            border-radius: 8px;
            font-size: .92rem;
            transition: all .15s ease;
        }

        .enlace-menu:hover {
            color: #fff;
            background: rgba(255,255,255,.12);
            transform: translateX(2px);
        }

        .enlace-menu.activo {
            color: #fff;
            background: var(--acento);
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(8, 117, 225, .35);
        }

        .icono-menu {
            display: inline-grid;
            place-items: center;
            width: 22px;
            font-size: 1.1rem;
        }

        .contenido {
            flex: 1;
            min-width: 0;
            padding: 30px;
        }

        .tarjeta-custom {
            border: 1px solid #e1e9f2;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 4px 16px rgba(23, 59, 95, .05);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .tarjeta-estadistica {
            padding: 20px;
            height: 100%;
        }

        .tarjeta-estadistica:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(23, 59, 95, .08);
        }

        .estadistica-cabecera {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .estadistica-icono {
            width: 50px;
            height: 50px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            font-size: 1.4rem;
        }

        .icono-azul { background: #e3efff; color: #1769c2; }
        .icono-verde { background: #e0f7eb; color: #16834b; }
        .icono-naranja { background: #fff0dc; color: #c86b08; }
        .icono-morado { background: #ede7f6; color: #5e35b1; }
        .icono-cyan { background: #e0f7fa; color: #00838f; }

        .estadistica-etiqueta { color: #64758b; font-size: .85rem; font-weight: 500; }
        .estadistica-numero { margin-top: 4px; font-size: 1.8rem; font-weight: 750; color: #102a45; }

        .btn-principal {
            background-color: var(--acento);
            color: #fff;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            padding: 8px 18px;
            box-shadow: 0 2px 6px rgba(8, 117, 225, .25);
        }
        .btn-principal:hover {
            background-color: #0662be;
            color: #fff;
        }

        .badge-estado {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: .78rem;
            font-weight: 700;
        }
        .badge-activo { background: #def7e8; color: #137744; }
        .badge-inactivo { background: #fbeae9; color: #c0392b; }
        .badge-pendiente { background: #fff3cd; color: #856404; }
        .badge-enviado { background: #d1ecf1; color: #0c5460; }

        .score-alto { color: #137744; font-weight: 800; }
        .score-medio { color: #d35400; font-weight: 800; }
        .score-bajo { color: #c0392b; font-weight: 800; }

        @media (max-width: 768px) {
            .layout { display: block; }
            .barra-lateral { width: 100%; padding: 12px; }
            .contenido { padding: 18px 14px; }
        }
    </style>
</head>
<body>
    <header class="barra-superior">
        <a href="<?= $urlModulo ?>index.php" class="marca">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 9h18L12 3 3 9Z"></path>
                <path d="M5 10v8M9 10v8M15 10v8M19 10v8M3 20h18M2 22h20"></path>
            </svg>
            <span>Sistema de Créditos</span>
            <span class="badge-modulo">Módulo 1 · Bryan Alvarado</span>
        </a>

        <div class="datos-usuario">
            <div class="d-none d-sm-block">
                <div class="usuario-nombre">Bryan Alvarado</div>
                <div class="usuario-rol">Oficial de Crédito · Integrante 1</div>
            </div>
            <div class="avatar" title="Bryan Alvarado">BA</div>
        </div>
    </header>

    <div class="layout">
        <aside class="barra-lateral">
            <div class="etiqueta-menu">Módulo Asignado</div>
            <nav class="nav flex-column">
                <a class="enlace-menu <?= ($seccionActiva ?? '') === 'inicio' ? 'activo' : '' ?>" href="<?= $urlModulo ?>index.php">
                    <span class="icono-menu">📊</span><span>Dashboard</span>
                </a>
                <a class="enlace-menu <?= ($seccionActiva ?? '') === 'clientes' ? 'activo' : '' ?>" href="<?= $urlModulo ?>clientes/index.php">
                    <span class="icono-menu">👥</span><span>Clientes</span>
                </a>
                <a class="enlace-menu <?= ($seccionActiva ?? '') === 'nuevo_cliente' ? 'activo' : '' ?>" href="<?= $urlModulo ?>clientes/nuevo.php">
                    <span class="icono-menu">➕</span><span>Nuevo Cliente</span>
                </a>
                <a class="enlace-menu <?= ($seccionActiva ?? '') === 'tipos_credito' ? 'activo' : '' ?>" href="<?= $urlModulo ?>tipos_credito/index.php">
                    <span class="icono-menu">📑</span><span>Tipos de Crédito</span>
                </a>
                <a class="enlace-menu <?= ($seccionActiva ?? '') === 'historial' ? 'activo' : '' ?>" href="<?= $urlModulo ?>historial/index.php">
                    <span class="icono-menu">🔍</span><span>Historial Crediticio</span>
                </a>
                <a class="enlace-menu <?= ($seccionActiva ?? '') === 'notificaciones' ? 'activo' : '' ?>" href="<?= $urlModulo ?>notificaciones/index.php">
                    <span class="icono-menu">🔔</span><span>Notificaciones</span>
                </a>
            </nav>

            <div class="etiqueta-menu mt-4">Herramientas Base de Datos</div>
            <nav class="nav flex-column">
                <a class="enlace-menu" href="http://127.0.0.1:8080" target="_blank" title="Abrir phpMyAdmin para gestionar la BD">
                    <span class="icono-menu">🗄️</span><span>phpMyAdmin (BD)</span>
                </a>
            </nav>
        </aside>

        <main class="contenido">
