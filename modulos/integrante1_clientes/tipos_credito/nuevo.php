<?php
/**
 * Registrar Nuevo Tipo de Crédito
 * Módulo: Integrante 1 (Bryan Alvarado)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../persistencia/TipoCreditoDAO.php';
use Modulo1\Persistencia\TipoCreditoDAO;

$errores = [];
$datos = [
    'nombre' => '', 'descripcion' => '', 'tasa_interes' => '',
    'plazo_minimo' => '6', 'plazo_maximo' => '36',
    'monto_minimo' => '1000', 'monto_maximo' => '50000',
    'garantia' => '', 'destino' => '', 'estado' => 'Activo'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $errores[] = 'Token de seguridad inválido.';
    }

    foreach ($datos as $k => $v) {
        $datos[$k] = trim($_POST[$k] ?? '');
    }

    if (empty($datos['nombre'])) $errores[] = 'El nombre del producto crediticio es obligatorio.';
    if (!is_numeric($datos['tasa_interes']) || (float)$datos['tasa_interes'] <= 0) $errores[] = 'La tasa de interés anual debe ser mayor a cero.';
    if (!is_numeric($datos['plazo_minimo']) || !is_numeric($datos['plazo_maximo']) || (int)$datos['plazo_maximo'] < (int)$datos['plazo_minimo']) {
        $errores[] = 'Los plazos mínimo y máximo deben ser válidos.';
    }
    if (!is_numeric($datos['monto_minimo']) || !is_numeric($datos['monto_maximo']) || (float)$datos['monto_maximo'] < (float)$datos['monto_minimo']) {
        $errores[] = 'Los montos mínimo y máximo deben ser válidos.';
    }

    if (empty($errores)) {
        try {
            $tipoDAO = new TipoCreditoDAO();
            $parametrosJson = json_encode([
                'garantia_requerida' => $datos['garantia'],
                'destino' => $datos['destino'],
                'moneda' => 'BOB'
            ], JSON_UNESCAPED_UNICODE);

            $idNuevo = $tipoDAO->registrar([
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'],
                'tasa_interes' => $datos['tasa_interes'],
                'plazo_minimo' => $datos['plazo_minimo'],
                'plazo_maximo' => $datos['plazo_maximo'],
                'monto_minimo' => $datos['monto_minimo'],
                'monto_maximo' => $datos['monto_maximo'],
                'parametros' => $parametrosJson,
                'estado' => $datos['estado']
            ]);

            header('Location: index.php?msg=Tipo+de+cr%C3%A9dito+creado+exitosamente');
            exit;
        } catch (\Exception $e) {
            $errores[] = 'Error al registrar: ' . $e->getMessage();
        }
    }
}

$tituloPagina = 'Crear Tipo de Crédito';
$seccionActiva = 'tipos_credito';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Tipos de Crédito</a></li>
            <li class="breadcrumb-item active" aria-current="page">Nuevo</li>
        </ol>
    </nav>
    <h1 class="h3 fw-bold mb-0">Crear Nuevo Tipo de Crédito</h1>
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

<form method="POST" action="nuevo.php" class="tarjeta-custom p-4">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <label class="form-label fw-semibold">Nombre del Tipo de Crédito *</label>
            <input type="text" name="nombre" class="form-control" placeholder="Ej. Crédito Agropecuario Productivo" required value="<?= e($datos['nombre']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Estado</label>
            <select name="estado" class="form-select">
                <option value="Activo" <?= $datos['estado'] === 'Activo' ? 'selected' : '' ?>>Activo</option>
                <option value="Inactivo" <?= $datos['estado'] === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold">Descripción del Producto</label>
            <textarea name="descripcion" class="form-control" rows="2" placeholder="Describa el público meta y propósito del financiamiento..."><?= e($datos['descripcion']) ?></textarea>
        </div>

        <div class="col-md-4">
            <label class="form-label fw-semibold">Tasa de Interés Anual (%) *</label>
            <input type="number" step="0.01" name="tasa_interes" class="form-control" placeholder="Ej. 11.50" required value="<?= e($datos['tasa_interes']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Plazo Mínimo (meses) *</label>
            <input type="number" name="plazo_minimo" class="form-control" required value="<?= e($datos['plazo_minimo']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Plazo Máximo (meses) *</label>
            <input type="number" name="plazo_maximo" class="form-control" required value="<?= e($datos['plazo_maximo']) ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Monto Mínimo (Bs) *</label>
            <input type="number" step="0.01" name="monto_minimo" class="form-control" required value="<?= e($datos['monto_minimo']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Monto Máximo (Bs) *</label>
            <input type="number" step="0.01" name="monto_maximo" class="form-control" required value="<?= e($datos['monto_maximo']) ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Tipo de Garantía Sugerida</label>
            <input type="text" name="garantia" class="form-control" placeholder="Ej. Garante Personal o Hipotecaria" value="<?= e($datos['garantia']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Destino del Financiamiento</label>
            <input type="text" name="destino" class="form-control" placeholder="Ej. Compra de insumos, capital de inversión" value="<?= e($datos['destino']) ?>">
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-principal">💾 Guardar Tipo de Crédito</button>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
