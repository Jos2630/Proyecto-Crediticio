<?php
/**
 * Modificar Tipo de Crédito Existente
 * Módulo: Integrante 1 (Bryan Alvarado)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../persistencia/TipoCreditoDAO.php';
use Modulo1\Persistencia\TipoCreditoDAO;

$tipoDAO = new TipoCreditoDAO();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tipo = $tipoDAO->obtenerPorId($id);

if (!$tipo) {
    header('Location: index.php?msg=Tipo+de+cr%C3%A9dito+no+encontrado');
    exit;
}

$parametros = json_decode($tipo['parametros'] ?? '{}', true) ?: [];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $errores[] = 'Token de seguridad inválido.';
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tasa_interes = trim($_POST['tasa_interes'] ?? '');
    $plazo_minimo = trim($_POST['plazo_minimo'] ?? '');
    $plazo_maximo = trim($_POST['plazo_maximo'] ?? '');
    $monto_minimo = trim($_POST['monto_minimo'] ?? '');
    $monto_maximo = trim($_POST['monto_maximo'] ?? '');
    $garantia = trim($_POST['garantia'] ?? '');
    $destino = trim($_POST['destino'] ?? '');
    $estado = trim($_POST['estado'] ?? 'Activo');

    if (empty($nombre)) $errores[] = 'El nombre es obligatorio.';
    if (!is_numeric($tasa_interes) || (float)$tasa_interes <= 0) $errores[] = 'La tasa de interés debe ser mayor a 0.';
    if (!is_numeric($plazo_minimo) || !is_numeric($plazo_maximo) || (int)$plazo_maximo < (int)$plazo_minimo) {
        $errores[] = 'Los plazos ingresados son inválidos.';
    }
    if (!is_numeric($monto_minimo) || !is_numeric($monto_maximo) || (float)$monto_maximo < (float)$monto_minimo) {
        $errores[] = 'Los montos ingresados son inválidos.';
    }

    if (empty($errores)) {
        try {
            $parametrosActualizados = json_encode([
                'garantia_requerida' => $garantia,
                'destino' => $destino,
                'moneda' => 'BOB'
            ], JSON_UNESCAPED_UNICODE);

            $tipoDAO->actualizar($id, [
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'tasa_interes' => $tasa_interes,
                'plazo_minimo' => $plazo_minimo,
                'plazo_maximo' => $plazo_maximo,
                'monto_minimo' => $monto_minimo,
                'monto_maximo' => $monto_maximo,
                'parametros' => $parametrosActualizados,
                'estado' => $estado
            ]);

            header('Location: index.php?msg=Tipo+de+cr%C3%A9dito+actualizado+correctamente');
            exit;
        } catch (\Exception $e) {
            $errores[] = 'Error al actualizar: ' . $e->getMessage();
        }
    }
}

$tituloPagina = 'Editar Tipo de Crédito';
$seccionActiva = 'tipos_credito';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Tipos de Crédito</a></li>
            <li class="breadcrumb-item active" aria-current="page">Editar</li>
        </ol>
    </nav>
    <h1 class="h3 fw-bold mb-0">Modificar Tipo de Crédito</h1>
</div>

<?php if (!empty($errores)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0 ps-3">
            <?php foreach ($errores as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form method="POST" action="editar.php?id=<?= $id ?>" class="tarjeta-custom p-4">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <label class="form-label fw-semibold">Nombre del Tipo de Crédito *</label>
            <input type="text" name="nombre" class="form-control" required value="<?= e($tipo['nombre']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Estado</label>
            <select name="estado" class="form-select">
                <option value="Activo" <?= $tipo['estado'] === 'Activo' ? 'selected' : '' ?>>Activo</option>
                <option value="Inactivo" <?= $tipo['estado'] === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="2"><?= e($tipo['descripcion']) ?></textarea>
        </div>

        <div class="col-md-4">
            <label class="form-label fw-semibold">Tasa de Interés Anual (%) *</label>
            <input type="number" step="0.01" name="tasa_interes" class="form-control" required value="<?= e($tipo['tasa_interes']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Plazo Mínimo (meses) *</label>
            <input type="number" name="plazo_minimo" class="form-control" required value="<?= e($tipo['plazo_minimo']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Plazo Máximo (meses) *</label>
            <input type="number" name="plazo_maximo" class="form-control" required value="<?= e($tipo['plazo_maximo']) ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Monto Mínimo (Bs) *</label>
            <input type="number" step="0.01" name="monto_minimo" class="form-control" required value="<?= e($tipo['monto_minimo']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Monto Máximo (Bs) *</label>
            <input type="number" step="0.01" name="monto_maximo" class="form-control" required value="<?= e($tipo['monto_maximo']) ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Tipo de Garantía Sugerida</label>
            <input type="text" name="garantia" class="form-control" value="<?= e($parametros['garantia_requerida'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Destino del Financiamiento</label>
            <input type="text" name="destino" class="form-control" value="<?= e($parametros['destino'] ?? '') ?>">
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-principal">💾 Guardar Cambios</button>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
