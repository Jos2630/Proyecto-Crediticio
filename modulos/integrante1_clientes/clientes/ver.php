<?php
/**
 * Ficha Integral 360° de Cliente
 * Módulo: Integrante 1 (Bryan Alvarado)
 */

require_once __DIR__ . '/../persistencia/ClienteDAO.php';
require_once __DIR__ . '/../persistencia/HistorialDAO.php';
require_once __DIR__ . '/../persistencia/NotificacionDAO.php';

use Modulo1\Persistencia\ClienteDAO;
use Modulo1\Persistencia\HistorialDAO;
use Modulo1\Persistencia\NotificacionDAO;

$id_cliente = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$clienteDAO = new ClienteDAO();
$historialDAO = new HistorialDAO();
$notificacionDAO = new NotificacionDAO();

$cliente = $clienteDAO->obtenerClienteConEconomia($id_cliente);

if (!$cliente) {
    header('Location: index.php?msg=Cliente+no+encontrado');
    exit;
}

$historiales = $historialDAO->obtenerPorCliente($id_cliente);
$notificaciones = $notificacionDAO->obtenerPorCliente($id_cliente);

// Cálculo de Capacidad de Pago
$ingresoTotal = (float)($cliente['ingreso_mensual'] ?? 0) + (float)($cliente['otros_ingresos'] ?? 0);
$gastos = (float)($cliente['gastos_mensuales'] ?? 0);
$capacidadNeta = $ingresoTotal - $gastos;
$porcentajeCompromiso = $ingresoTotal > 0 ? round(($gastos / $ingresoTotal) * 100, 1) : 0;

$tituloPagina = 'Ficha: ' . $cliente['nombres'] . ' ' . $cliente['apellidos'];
$seccionActiva = 'clientes';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="../index.php">Inicio</a></li>
                <li class="breadcrumb-item"><a href="index.php">Clientes</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($cliente['ci']) ?></li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold mb-0">Ficha Integral del Cliente (360°)</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="../notificaciones/nueva.php?id_cliente=<?= $id_cliente ?>" class="btn btn-outline-primary">
            🔔 Enviar Notificación
        </a>
        <a href="editar.php?id=<?= $id_cliente ?>" class="btn btn-principal">
            ✏️ Editar Cliente
        </a>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($_GET['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Columna Izquierda: Datos Personales y Estado -->
    <div class="col-lg-5">
        <div class="tarjeta-custom p-4 mb-4">
            <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                <div class="avatar" style="width: 55px; height: 55px; font-size: 1.3rem; background: #0756a5; color: #fff;">
                    <?= strtoupper(substr($cliente['nombres'], 0, 1) . substr($cliente['apellidos'], 0, 1)) ?>
                </div>
                <div>
                    <h2 class="h5 fw-bold mb-1 text-primary-emphasis"><?= e($cliente['nombres'] . ' ' . $cliente['apellidos']) ?></h2>
                    <span class="badge-estado <?= $cliente['estado'] === 'Activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                        <?= e($cliente['estado']) ?>
                    </span>
                    <span class="text-muted small ms-2">ID: #<?= $cliente['id_cliente'] ?></span>
                </div>
            </div>

            <table class="table table-sm table-borderless mb-0">
                <tbody>
                    <tr>
                        <td class="text-muted fw-semibold" style="width: 40%;">Cédula de Identidad:</td>
                        <td><strong class="text-dark"><?= e($cliente['ci']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Fecha Nacimiento:</td>
                        <td><?= e($cliente['fecha_nacimiento'] ?? 'No registrada') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Teléfono / Celular:</td>
                        <td><?= e($cliente['telefono'] ?? 'Sin teléfono') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Correo Electrónico:</td>
                        <td><?= e($cliente['correo'] ?? 'Sin correo') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Dirección:</td>
                        <td><?= e($cliente['direccion'] ?? 'No especificada') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Fecha de Registro:</td>
                        <td><small class="text-muted"><?= e($cliente['fecha_registro']) ?></small></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Evaluación de Capacidad de Pago -->
        <div class="tarjeta-custom p-4">
            <h3 class="h6 fw-bold text-uppercase text-secondary mb-3">Evaluación de Capacidad de Pago</h3>
            <div class="p-3 rounded mb-3 <?= $capacidadNeta > 0 ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?>">
                <div class="small fw-semibold">Margen Disponible Mensual:</div>
                <div class="fs-3 fw-bold"><?= formatoBs($capacidadNeta) ?></div>
            </div>

            <div class="mb-2 d-flex justify-content-between small">
                <span>Nivel de Endeudamiento/Gastos:</span>
                <strong><?= $porcentajeCompromiso ?>%</strong>
            </div>
            <div class="progress mb-3" style="height: 8px;">
                <div class="progress-bar <?= $porcentajeCompromiso > 60 ? 'bg-danger' : ($porcentajeCompromiso > 40 ? 'bg-warning' : 'bg-success') ?>" 
                     role="progressbar" style="width: <?= min(100, $porcentajeCompromiso) ?>%"></div>
            </div>
            <p class="text-muted small mb-0">
                * Calculado a partir de los ingresos declarados (<?= formatoBs($ingresoTotal) ?>) menos gastos mensuales (<?= formatoBs($gastos) ?>).
            </p>
        </div>
    </div>

    <!-- Columna Derecha: Información Económica, Historial Crediticio y Notificaciones -->
    <div class="col-lg-7">
        <!-- Información Económica Detallada -->
        <div class="tarjeta-custom p-4 mb-4">
            <h3 class="h6 fw-bold text-uppercase text-secondary mb-3 pb-2 border-bottom">Información Económica y Laboral</h3>
            <div class="row g-3">
                <div class="col-sm-6">
                    <div class="text-muted small">Ocupación / Actividad:</div>
                    <div class="fw-bold"><?= e($cliente['ocupacion'] ?? 'No registrada') ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="text-muted small">Empleador / Negocio:</div>
                    <div class="fw-bold"><?= e($cliente['empleador'] ?? 'Independiente / No especificado') ?></div>
                </div>
                <div class="col-sm-4">
                    <div class="text-muted small">Ingreso Principal:</div>
                    <div class="fw-bold text-success"><?= formatoBs($cliente['ingreso_mensual'] ?? 0) ?></div>
                </div>
                <div class="col-sm-4">
                    <div class="text-muted small">Otros Ingresos:</div>
                    <div class="fw-bold text-secondary"><?= formatoBs($cliente['otros_ingresos'] ?? 0) ?></div>
                </div>
                <div class="col-sm-4">
                    <div class="text-muted small">Gastos Mensuales:</div>
                    <div class="fw-bold text-danger"><?= formatoBs($cliente['gastos_mensuales'] ?? 0) ?></div>
                </div>
            </div>
        </div>

        <!-- Consultas de Historial Crediticio (Buró / ASFI) -->
        <div class="tarjeta-custom p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 fw-bold text-uppercase text-secondary mb-0">Récord en Buró de Crédito</h3>
                <span class="badge bg-secondary"><?= count($historiales) ?> consulta(s)</span>
            </div>

            <?php if (empty($historiales)): ?>
                <div class="alert alert-light border mb-0 text-muted small">
                    No se han registrado consultas de buró para solicitudes de este cliente aún.
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($historiales as $h): ?>
                        <?php 
                            $puntaje = (int)$h['puntaje'];
                            $claseScore = $puntaje >= 750 ? 'score-alto' : ($puntaje >= 650 ? 'score-medio' : 'score-bajo');
                        ?>
                        <div class="list-group-item px-0 py-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="text-primary-emphasis"><?= e($h['entidad_consultada']) ?></strong>
                                <span class="fs-5 <?= $claseScore ?>">Score: <?= $puntaje ?> pts</span>
                            </div>
                            <div class="small text-secondary mb-1">
                                <strong>Obligaciones:</strong> <?= e($h['obligaciones_vigentes'] ?? 'Sin obligaciones reportadas') ?>
                            </div>
                            <?php if (!empty($h['observaciones'])): ?>
                                <div class="small text-muted fst-italic">
                                    Nota: <?= e($h['observaciones']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="text-muted" style="font-size: 0.75rem;">
                                Fecha de consulta: <?= e($h['fecha_consulta']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Notificaciones Asociadas -->
        <div class="tarjeta-custom p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 fw-bold text-uppercase text-secondary mb-0">Notificaciones y Alertas</h3>
                <a href="../notificaciones/nueva.php?id_cliente=<?= $id_cliente ?>" class="btn btn-sm btn-outline-primary">+ Enviar</a>
            </div>

            <?php if (empty($notificaciones)): ?>
                <div class="alert alert-light border mb-0 text-muted small">
                    No existen notificaciones registradas para este cliente.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Mensaje</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notificaciones as $n): ?>
                                <tr>
                                    <td class="fw-semibold small"><?= e($n['tipo_notificacion']) ?></td>
                                    <td class="small"><?= e($n['mensaje']) ?></td>
                                    <td>
                                        <span class="badge-estado <?= $n['estado'] === 'Enviado' ? 'badge-enviado' : 'badge-pendiente' ?>">
                                            <?= e($n['estado']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
