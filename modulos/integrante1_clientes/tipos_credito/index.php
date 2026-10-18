<?php
/**
 * Catálogo de Tipos de Crédito
 * Módulo: Integrante 1 (Bryan Alvarado)
 * 
 * Este catálogo es la base que consumirán el Integrante 2 (Solicitudes) y 3 (Créditos).
 */

require_once __DIR__ . '/../persistencia/TipoCreditoDAO.php';
use Modulo1\Persistencia\TipoCreditoDAO;

$tipoDAO = new TipoCreditoDAO();
$tipos = $tipoDAO->listar();

$tituloPagina = 'Catálogo de Tipos de Crédito';
$seccionActiva = 'tipos_credito';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="../index.php">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page">Tipos de Crédito</li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold mb-0">Catálogo de Productos Crediticios</h1>
    </div>
    <a href="nuevo.php" class="btn btn-principal">
        ➕ Crear Tipo de Crédito
    </a>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($_GET['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Tarjetas Visuales de Productos Crediticios -->
<div class="row g-4 mb-4">
    <?php foreach ($tipos as $t): ?>
        <?php 
            $parametros = json_decode($t['parametros'] ?? '{}', true) ?: [];
        ?>
        <div class="col-md-6 col-xl-6">
            <div class="tarjeta-custom p-4 h-100 d-flex flex-column justify-content-between position-relative">
                <div>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h2 class="h5 fw-bold text-primary-emphasis mb-0"><?= e($t['nombre']) ?></h2>
                        <span class="badge-estado <?= $t['estado'] === 'Activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                            <?= e($t['estado']) ?>
                        </span>
                    </div>

                    <p class="text-secondary small mb-3"><?= e($t['descripcion'] ?? 'Sin descripción.') ?></p>

                    <div class="bg-light p-3 rounded-3 mb-3">
                        <div class="row g-2 text-center">
                            <div class="col-4 border-end">
                                <div class="text-muted small">Tasa Anual</div>
                                <div class="fs-5 fw-bold text-primary"><?= number_format($t['tasa_interes'], 2) ?>%</div>
                            </div>
                            <div class="col-4 border-end">
                                <div class="text-muted small">Plazo</div>
                                <div class="fw-bold"><?= $t['plazo_minimo'] ?> - <?= $t['plazo_maximo'] ?> m.</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">Monto Límite</div>
                                <div class="fw-bold text-success small"><?= formatoBs($t['monto_maximo']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="small text-muted mb-2">
                        <strong>Rango de montos:</strong> <?= formatoBs($t['monto_minimo']) ?> a <?= formatoBs($t['monto_maximo']) ?>
                    </div>

                    <?php if (!empty($parametros)): ?>
                        <div class="small text-secondary mb-3">
                            <?php if (isset($parametros['garantia_requerida'])): ?>
                                <div>🛡️ <strong>Garantía:</strong> <?= e($parametros['garantia_requerida']) ?></div>
                            <?php endif; ?>
                            <?php if (isset($parametros['destino'])): ?>
                                <div>🎯 <strong>Destino:</strong> <?= e($parametros['destino']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-2">
                    <span class="text-muted small">ID: #<?= $t['id_tipo_credito'] ?></span>
                    <a href="editar.php?id=<?= $t['id_tipo_credito'] ?>" class="btn btn-sm btn-outline-secondary">
                        ✏️ Configurar Parámetros
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="alert alert-secondary border-0 small">
    💡 <strong>Integración con el equipo:</strong> Los tipos de crédito activos aquí configurados aparecerán directamente en el selector de tipo de crédito que el <em>Integrante 2</em> usará en el formulario de solicitudes de crédito.
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
