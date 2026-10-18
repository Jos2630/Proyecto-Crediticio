<?php
/**
 * Edición de Cliente e Información Económica
 * Módulo: Integrante 1 (Bryan Alvarado)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../persistencia/ClienteDAO.php';
use Modulo1\Persistencia\ClienteDAO;

$clienteDAO = new ClienteDAO();
$id_cliente = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cliente = $clienteDAO->obtenerClienteConEconomia($id_cliente);

if (!$cliente) {
    header('Location: index.php?msg=Cliente+no+encontrado');
    exit;
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $errores[] = 'Token de seguridad inválido o expirado.';
    }

    $ci = trim($_POST['ci'] ?? '');
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $estado = trim($_POST['estado'] ?? 'Activo');

    $ocupacion = trim($_POST['ocupacion'] ?? '');
    $empleador = trim($_POST['empleador'] ?? '');
    $ingreso_mensual = trim($_POST['ingreso_mensual'] ?? '0');
    $otros_ingresos = trim($_POST['otros_ingresos'] ?? '0');
    $gastos_mensuales = trim($_POST['gastos_mensuales'] ?? '0');

    if (empty($ci)) $errores[] = 'El CI es obligatorio.';
    if (empty($nombres)) $errores[] = 'El campo nombres es obligatorio.';
    if (empty($apellidos)) $errores[] = 'El campo apellidos es obligatorio.';
    if (empty($ocupacion)) $errores[] = 'La ocupación es obligatoria.';
    if (!is_numeric($ingreso_mensual) || (float)$ingreso_mensual <= 0) $errores[] = 'El ingreso mensual debe ser mayor a 0.';
    if (!is_numeric($gastos_mensuales) || (float)$gastos_mensuales < 0) $errores[] = 'Los gastos deben ser válidos.';

    if (empty($errores)) {
        try {
            $cli = compact('ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'telefono', 'correo', 'direccion', 'estado');
            $eco = compact('ocupacion', 'empleador', 'ingreso_mensual', 'otros_ingresos', 'gastos_mensuales');

            $clienteDAO->actualizarCliente($id_cliente, $cli, $eco);
            header("Location: ver.php?id={$id_cliente}&msg=Datos+actualizados+correctamente");
            exit;
        } catch (\Exception $e) {
            $errores[] = 'Error al actualizar: ' . $e->getMessage();
        }
    }
}

$tituloPagina = 'Editar Cliente';
$seccionActiva = 'clientes';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Clientes</a></li>
            <li class="breadcrumb-item"><a href="ver.php?id=<?= $id_cliente ?>"><?= e($cliente['nombres']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Editar</li>
        </ol>
    </nav>
    <h1 class="h3 fw-bold mb-0">Modificar Ficha de Cliente</h1>
</div>

<?php if (!empty($errores)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <h6 class="fw-bold mb-2">Errores al guardar:</h6>
        <ul class="mb-0 ps-3">
            <?php foreach ($errores as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form method="POST" action="editar.php?id=<?= $id_cliente ?>" class="tarjeta-custom p-4">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <h4 class="h5 fw-bold text-primary-emphasis mb-3 pb-2 border-bottom">1. Datos Personales</h4>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <label class="form-label fw-semibold">Cédula de Identidad (CI) *</label>
            <input type="text" name="ci" class="form-control" required value="<?= e($cliente['ci']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Nombres *</label>
            <input type="text" name="nombres" class="form-control" required value="<?= e($cliente['nombres']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Apellidos *</label>
            <input type="text" name="apellidos" class="form-control" required value="<?= e($cliente['apellidos']) ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label fw-semibold">Fecha de Nacimiento</label>
            <input type="date" name="fecha_nacimiento" class="form-control" value="<?= e($cliente['fecha_nacimiento']) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Teléfono</label>
            <input type="text" name="telefono" class="form-control" value="<?= e($cliente['telefono']) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Correo Electrónico</label>
            <input type="email" name="correo" class="form-control" value="<?= e($cliente['correo']) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Estado</label>
            <select name="estado" class="form-select">
                <option value="Activo" <?= $cliente['estado'] === 'Activo' ? 'selected' : '' ?>>Activo</option>
                <option value="Inactivo" <?= $cliente['estado'] === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold">Dirección</label>
            <input type="text" name="direccion" class="form-control" value="<?= e($cliente['direccion']) ?>">
        </div>
    </div>

    <h4 class="h5 fw-bold text-primary-emphasis mb-3 pb-2 border-bottom">2. Información Económica</h4>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <label class="form-label fw-semibold">Ocupación / Actividad *</label>
            <input type="text" name="ocupacion" class="form-control" required value="<?= e($cliente['ocupacion']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Empleador / Negocio</label>
            <input type="text" name="empleador" class="form-control" value="<?= e($cliente['empleador']) ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label fw-semibold">Ingreso Mensual (Bs) *</label>
            <input type="number" step="0.01" name="ingreso_mensual" class="form-control" required value="<?= e($cliente['ingreso_mensual']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Otros Ingresos (Bs)</label>
            <input type="number" step="0.01" name="otros_ingresos" class="form-control" value="<?= e($cliente['otros_ingresos']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Gastos Mensuales (Bs) *</label>
            <input type="number" step="0.01" name="gastos_mensuales" class="form-control" required value="<?= e($cliente['gastos_mensuales']) ?>">
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
        <a href="ver.php?id=<?= $id_cliente ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-principal">💾 Guardar Cambios</button>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
