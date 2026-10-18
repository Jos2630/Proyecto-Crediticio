<?php
/**
 * Registro de Nuevo Cliente e Información Económica
 * Módulo: Integrante 1 (Bryan Alvarado)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../persistencia/ClienteDAO.php';
use Modulo1\Persistencia\ClienteDAO;

$errores = [];
$datos = [
    'ci' => '', 'nombres' => '', 'apellidos' => '', 'fecha_nacimiento' => '',
    'telefono' => '', 'correo' => '', 'direccion' => '', 'estado' => 'Activo',
    'ocupacion' => '', 'empleador' => '', 'ingreso_mensual' => '',
    'otros_ingresos' => '0', 'gastos_mensuales' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar token CSRF
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $errores[] = 'Token de seguridad inválido o expirado. Por favor recargue el formulario.';
    }

    // 2. Extraer y sanear entradas
    foreach ($datos as $campo => $def) {
        $datos[$campo] = trim($_POST[$campo] ?? '');
    }

    // 3. Validaciones
    if (empty($datos['ci'])) {
        $errores[] = 'El número de Cédula de Identidad (CI) es obligatorio.';
    } else {
        $clienteDAO = new ClienteDAO();
        if ($clienteDAO->existeCi($datos['ci'])) {
            $errores[] = "Ya existe un cliente registrado con el CI '{$datos['ci']}'.";
        }
    }
    if (empty($datos['nombres'])) {
        $errores[] = 'El campo nombres es obligatorio.';
    }
    if (empty($datos['apellidos'])) {
        $errores[] = 'El campo apellidos es obligatorio.';
    }
    if (empty($datos['ocupacion'])) {
        $errores[] = 'Debe indicar la ocupación o actividad laboral del cliente.';
    }
    if (!is_numeric($datos['ingreso_mensual']) || (float)$datos['ingreso_mensual'] <= 0) {
        $errores[] = 'El ingreso mensual debe ser un monto numérico mayor a cero.';
    }
    if (!is_numeric($datos['gastos_mensuales']) || (float)$datos['gastos_mensuales'] < 0) {
        $errores[] = 'Los gastos mensuales deben ser un valor numérico válido.';
    }

    // 4. Si no hay errores, persistir en BD
    if (empty($errores)) {
        try {
            $clienteDAO = new ClienteDAO();
            $datosCli = [
                'ci' => $datos['ci'],
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'],
                'fecha_nacimiento' => $datos['fecha_nacimiento'],
                'telefono' => $datos['telefono'],
                'correo' => $datos['correo'],
                'direccion' => $datos['direccion'],
                'estado' => $datos['estado']
            ];
            $datosEco = [
                'ocupacion' => $datos['ocupacion'],
                'empleador' => $datos['empleador'],
                'ingreso_mensual' => $datos['ingreso_mensual'],
                'otros_ingresos' => $datos['otros_ingresos'],
                'gastos_mensuales' => $datos['gastos_mensuales']
            ];

            $idNuevo = $clienteDAO->registrarCliente($datosCli, $datosEco);
            header("Location: ver.php?id={$idNuevo}&msg=Cliente+registrado+con+%C3%A9xito");
            exit;
        } catch (\Exception $e) {
            $errores[] = 'Error al registrar en la base de datos: ' . $e->getMessage();
        }
    }
}

$tituloPagina = 'Nuevo Cliente';
$seccionActiva = 'nuevo_cliente';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Clientes</a></li>
            <li class="breadcrumb-item active" aria-current="page">Nuevo Cliente</li>
        </ol>
    </nav>
    <h1 class="h3 fw-bold mb-0">Registro de Cliente y Capacidad Económica</h1>
</div>

<?php if (!empty($errores)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <h6 class="fw-bold mb-2">Por favor corrija los siguientes errores:</h6>
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

    <!-- Sección 1: Datos Personales -->
    <h4 class="h5 fw-bold text-primary-emphasis mb-3 pb-2 border-bottom">1. Datos Personales del Cliente</h4>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <label class="form-label fw-semibold">Cédula de Identidad (CI) *</label>
            <input type="text" name="ci" class="form-control" placeholder="Ej. 6849201 LP" required value="<?= e($datos['ci']) ?>">
            <div class="form-text">Incluir extensión (ej. LP, CB, SC).</div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Nombres *</label>
            <input type="text" name="nombres" class="form-control" placeholder="Ej. Juan Carlos" required value="<?= e($datos['nombres']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Apellidos *</label>
            <input type="text" name="apellidos" class="form-control" placeholder="Ej. Perez Gomez" required value="<?= e($datos['apellidos']) ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label fw-semibold">Fecha de Nacimiento</label>
            <input type="date" name="fecha_nacimiento" class="form-control" value="<?= e($datos['fecha_nacimiento']) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Teléfono / Celular</label>
            <input type="text" name="telefono" class="form-control" placeholder="Ej. 71589412" value="<?= e($datos['telefono']) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Correo Electrónico</label>
            <input type="email" name="correo" class="form-control" placeholder="ejemplo@correo.com" value="<?= e($datos['correo']) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Estado Inicial</label>
            <select name="estado" class="form-select">
                <option value="Activo" <?= $datos['estado'] === 'Activo' ? 'selected' : '' ?>>Activo</option>
                <option value="Inactivo" <?= $datos['estado'] === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold">Dirección de Domicilio</label>
            <input type="text" name="direccion" class="form-control" placeholder="Av., Calle, Número, Zona, Ciudad" value="<?= e($datos['direccion']) ?>">
        </div>
    </div>

    <!-- Sección 2: Información Económica Inicial -->
    <h4 class="h5 fw-bold text-primary-emphasis mb-3 pb-2 border-bottom">2. Información Económica y Capacidad de Pago</h4>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <label class="form-label fw-semibold">Ocupación / Actividad Económica *</label>
            <input type="text" name="ocupacion" class="form-control" placeholder="Ej. Comerciante, Asalariado, Profesional Independiente" required value="<?= e($datos['ocupacion']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Empleador o Empresa (opcional)</label>
            <input type="text" name="empleador" class="form-control" placeholder="Nombre de la empresa o negocio propio" value="<?= e($datos['empleador']) ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label fw-semibold">Ingreso Mensual Principal (Bs) *</label>
            <input type="number" step="0.01" name="ingreso_mensual" class="form-control" placeholder="0.00" required value="<?= e($datos['ingreso_mensual']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Otros Ingresos (Bs)</label>
            <input type="number" step="0.01" name="otros_ingresos" class="form-control" placeholder="0.00" value="<?= e($datos['otros_ingresos']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Gastos Mensuales Estimados (Bs) *</label>
            <input type="number" step="0.01" name="gastos_mensuales" class="form-control" placeholder="0.00" required value="<?= e($datos['gastos_mensuales']) ?>">
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-principal">💾 Guardar Cliente y Datos Económicos</button>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
