<?php
/**
 * Listado y Búsqueda de Clientes
 * Módulo: Integrante 1 (Bryan Alvarado)
 */

require_once __DIR__ . '/../persistencia/ClienteDAO.php';
use Modulo1\Persistencia\ClienteDAO;

$clienteDAO = new ClienteDAO();

$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

$clientes = $clienteDAO->listarClientes($buscar, $estado);

$tituloPagina = 'Gestión de Clientes';
$seccionActiva = 'clientes';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="../index.php">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page">Clientes</li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold mb-0">Cartera de Clientes</h1>
    </div>
    <a href="nuevo.php" class="btn btn-principal">
        ➕ Registrar Nuevo Cliente
    </a>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($_GET['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Barra de Filtros y Búsqueda -->
<div class="tarjeta-custom p-3 mb-4">
    <form method="GET" action="index.php" class="row g-2 align-items-center">
        <div class="col-md-7 col-lg-8">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0">🔍</span>
                <input type="text" name="buscar" class="form-control border-start-0 ps-0" 
                       placeholder="Buscar por CI, nombres, apellidos o teléfono..." 
                       value="<?= e($buscar) ?>">
            </div>
        </div>
        <div class="col-md-3 col-lg-2">
            <select name="estado" class="form-select">
                <option value="">Todos los estados</option>
                <option value="Activo" <?= $estado === 'Activo' ? 'selected' : '' ?>>Solo Activos</option>
                <option value="Inactivo" <?= $estado === 'Inactivo' ? 'selected' : '' ?>>Solo Inactivos</option>
            </select>
        </div>
        <div class="col-md-2 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            <?php if (!empty($buscar) || !empty($estado)): ?>
                <a href="index.php" class="btn btn-outline-secondary" title="Limpiar filtros">✖</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabla de Clientes -->
<div class="tarjeta-custom p-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>CI</th>
                    <th>Cliente</th>
                    <th>Contacto</th>
                    <th>Ocupación / Empleo</th>
                    <th>Ingreso / Gastos</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clientes)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <div class="fs-1 mb-2">🔎</div>
                            No se encontraron clientes con los criterios de búsqueda especificados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($clientes as $c): ?>
                        <tr>
                            <td>
                                <strong class="text-primary-emphasis"><?= e($c['ci']) ?></strong>
                            </td>
                            <td>
                                <div class="fw-bold"><?= e($c['nombres'] . ' ' . $c['apellidos']) ?></div>
                                <div class="text-muted small"><?= e($c['direccion'] ?? 'Sin dirección') ?></div>
                            </td>
                            <td>
                                <div>📱 <?= e($c['telefono'] ?? '-') ?></div>
                                <div class="text-muted small">✉️ <?= e($c['correo'] ?? '-') ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($c['ocupacion'] ?? 'No registrada') ?></div>
                            </td>
                            <td>
                                <div><strong class="text-success"><?= formatoBs($c['ingreso_mensual'] ?? 0) ?></strong></div>
                                <div class="text-danger small">Gastos: <?= formatoBs($c['gastos_mensuales'] ?? 0) ?></div>
                            </td>
                            <td>
                                <span class="badge-estado <?= $c['estado'] === 'Activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                                    <?= e($c['estado']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="ver.php?id=<?= $c['id_cliente'] ?>" class="btn btn-outline-primary" title="Ver Ficha 360°">👁️ Ver</a>
                                    <a href="editar.php?id=<?= $c['id_cliente'] ?>" class="btn btn-outline-secondary" title="Editar">✏️</a>
                                    <a href="cambiar_estado.php?id=<?= $c['id_cliente'] ?>&token=<?= $_SESSION['csrf_token'] ?>" 
                                       class="btn btn-outline-<?= $c['estado'] === 'Activo' ? 'warning' : 'success' ?>" 
                                       title="<?= $c['estado'] === 'Activo' ? 'Desactivar Cliente' : 'Activar Cliente' ?>"
                                       onclick="return confirm('¿Está seguro de cambiar el estado de este cliente?');">
                                        <?= $c['estado'] === 'Activo' ? '⏸️' : '▶️' ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
