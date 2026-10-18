<?php
require_once __DIR__ . '/../../config/funciones.php';
require_once __DIR__ . '/../../config/plantilla.php';

$db = bd();
$q = trim((string)($_GET['q'] ?? ''));
$estado = (string)($_GET['estado'] ?? '');
$estadosValidos = ['Aprobado', 'Vigente', 'En mora', 'Reprogramado', 'Cancelado', 'Refinanciado'];
if (!in_array($estado, $estadosValidos, true)) $estado = '';

$kpi = [
    'pendientes' => (int)escalar($db, "SELECT COUNT(*) FROM solicitud_credito s LEFT JOIN credito c ON c.id_solicitud = s.id_solicitud
                                       WHERE s.estado_solicitud = ? AND c.id_credito IS NULL", [ESTADO_SOLICITUD_APROBADA]),
    'aprobados'  => (int)escalar($db, 'SELECT COUNT(*) FROM credito WHERE estado_credito = ?', [ESTADO_CREDITO_APROBADO]),
    'vigentes'   => (int)escalar($db, 'SELECT COUNT(*) FROM credito WHERE estado_credito = ?', [ESTADO_CREDITO_VIGENTE]),
    'por_firmar' => (int)escalar($db, "SELECT COUNT(*) FROM contrato WHERE estado = 'Generado'"),
];

$st = $db->prepare("SELECT s.id_solicitud, s.monto_solicitado, s.plazo_meses, s.fecha_decision, cl.ci, cl.nombres, cl.apellidos, tc.nombre AS tipo
                    FROM solicitud_credito s
                    JOIN cliente cl ON cl.id_cliente = s.id_cliente
                    JOIN tipo_credito tc ON tc.id_tipo_credito = s.id_tipo_credito
                    LEFT JOIN credito c ON c.id_solicitud = s.id_solicitud
                    WHERE s.estado_solicitud = ? AND c.id_credito IS NULL
                    ORDER BY s.fecha_decision DESC LIMIT 50");
$st->execute([ESTADO_SOLICITUD_APROBADA]);
$pendientes = $st->fetchAll();

$sql = "SELECT c.id_solicitud, c.numero_credito, c.monto_aprobado, c.saldo_actual, c.plazo_meses, c.estado_credito,
               cl.ci, cl.nombres, cl.apellidos, tc.nombre AS tipo, ct.estado AS estado_contrato
        FROM credito c
        JOIN solicitud_credito s ON s.id_solicitud = c.id_solicitud
        JOIN cliente cl ON cl.id_cliente = s.id_cliente
        JOIN tipo_credito tc ON tc.id_tipo_credito = s.id_tipo_credito
        LEFT JOIN contrato ct ON ct.id_credito = c.id_credito
        WHERE  1=1";
$params = [];
if ($q !== '') {
    $sql .= " AND (c.numero_credito LIKE ? OR cl.ci LIKE ? OR CONCAT(cl.nombres, ' ', cl.apellidos) LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($estado !== '') { $sql .= ' AND c.estado_credito = ?'; $params[] = $estado; }
$sql .= ' ORDER BY c.id_credito DESC LIMIT 200';
$st = $db->prepare($sql);
$st->execute($params);
$creditos = $st->fetchAll();

pagina_inicio('Créditos');
?>
<h4 class="fw-bold mb-1">Créditos, garantías y contratos</h4>
<p class="text-secondary mb-4">Formaliza solicitudes aprobadas y da seguimiento a los créditos.</p>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['azul',    'bi-hourglass-split',   $kpi['pendientes'], 'Por formalizar'],
        ['naranja', 'bi-file-earmark-check', $kpi['aprobados'],  'Aprobados sin desembolso'],
        ['verde',   'bi-bank2',             $kpi['vigentes'],   'Créditos vigentes'],
        ['morado',  'bi-pen',               $kpi['por_firmar'], 'Contratos por firmar'],
    ] as [$color, $icono, $num, $texto]): ?>
        <div class="col-6 col-xl-3">
            <div class="tarjeta kpi mb-0 <?= $color ?>">
                <div class="icono"><i class="bi <?= $icono ?>"></i></div>
                <div><div class="numero"><?= $num ?></div><div class="text-secondary small"><?= $texto ?></div></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<section class="tarjeta">
    <div class="tarjeta-titulo">Solicitudes aprobadas por formalizar</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>N° Solicitud</th><th>Cliente</th><th>CI</th><th>Tipo</th><th class="text-end">Monto</th><th>Plazo</th><th>Aprobada el</th><th class="text-end">Acción</th></tr></thead>
            <tbody>
            <?php foreach ($pendientes as $p): ?>
                <tr>
                    <td><?= (int)$p['id_solicitud'] ?></td>
                    <td><?= e($p['nombres'] . ' ' . $p['apellidos']) ?></td>
                    <td><?= e($p['ci']) ?></td>
                    <td><?= e($p['tipo']) ?></td>
                    <td class="text-end"><?= bs($p['monto_solicitado']) ?></td>
                    <td><?= (int)$p['plazo_meses'] ?> meses</td>
                    <td><?= fecha($p['fecha_decision']) ?></td>
                    <td class="text-end"><a class="btn btn-primary btn-sm" href="formalizar.php?id_solicitud=<?= (int)$p['id_solicitud'] ?>">Formalizar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pendientes): ?><tr><td colspan="8" class="text-center text-secondary py-4">No hay solicitudes aprobadas pendientes.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="tarjeta">
    <div class="tarjeta-titulo">
        <span>Créditos</span>
        <form class="d-flex gap-2" method="get">
            <input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="N° crédito, CI o nombre">
            <select class="form-select form-select-sm" name="estado">
                <option value="">Todos</option>
                <?php foreach ($estadosValidos as $ev): ?><option <?= $ev === $estado ? 'selected' : '' ?>><?= e($ev) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>N° Crédito</th><th>Cliente</th><th>Tipo</th><th class="text-end">Monto</th><th class="text-end">Saldo</th><th>Plazo</th><th>Contrato</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($creditos as $c): ?>
                <tr>
                    <td class="fw-semibold"><?= e($c['numero_credito']) ?></td>
                    <td><?= e($c['nombres'] . ' ' . $c['apellidos']) ?><div class="text-secondary small">CI <?= e($c['ci']) ?></div></td>
                    <td><?= e($c['tipo']) ?></td>
                    <td class="text-end"><?= bs($c['monto_aprobado']) ?></td>
                    <td class="text-end"><?= bs($c['saldo_actual']) ?></td>
                    <td><?= (int)$c['plazo_meses'] ?> m</td>
                    <td><?= $c['estado_contrato'] ? '<span class="badge ' . clase_estado($c['estado_contrato']) . '">' . e($c['estado_contrato']) . '</span>' : '<span class="text-secondary">—</span>' ?></td>
                    <td><span class="badge <?= clase_estado($c['estado_credito']) ?>"><?= e($c['estado_credito']) ?></span></td>
                    <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="formalizar.php?id_solicitud=<?= (int)$c['id_solicitud'] ?>"><i class="bi bi-eye"></i> Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$creditos): ?><tr><td colspan="9" class="text-center text-secondary py-4">No se encontraron créditos.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php pagina_fin();