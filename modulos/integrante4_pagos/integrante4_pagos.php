<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Integración pendiente con el login del equipo: proteger esta página con la sesión
// y el rol Cajero cuando el integrante 5 entregue su autenticación.

function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirectWith(string $type, string $message): never {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    header('Location: integrante4_pagos.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_GET['pdf_cronograma'])) {
    $idPdf = filter_input(INPUT_GET, 'pdf_cronograma', FILTER_VALIDATE_INT);
    if (!$idPdf || $idPdf < 1) { http_response_code(400); exit('Cronograma inválido.'); }
    $st = $pdo->prepare("SELECT cr.numero_version, cr.fecha_inicio, cr.fecha_fin, cr.metodo_amortizacion, c.numero_credito, c.monto_aprobado, cl.nombres, cl.apellidos
        FROM CRONOGRAMA cr JOIN CREDITO c ON c.id_credito=cr.id_credito JOIN SOLICITUD_CREDITO s ON s.id_solicitud=c.id_solicitud JOIN CLIENTE cl ON cl.id_cliente=s.id_cliente WHERE cr.id_cronograma=?");
    $st->execute([$idPdf]); $meta = $st->fetch();
    if (!$meta) { http_response_code(404); exit('Cronograma no encontrado.'); }
    $st = $pdo->prepare('SELECT numero_cuota, fecha_vencimiento, capital, interes, monto_total, monto_pagado, saldo_pendiente, estado_cuota FROM CUOTA WHERE id_cronograma=? ORDER BY numero_cuota');
    $st->execute([$idPdf]); $lista = $st->fetchAll();
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><title>Cronograma <?= h($meta['numero_credito']) ?></title><style>body{font-family:Arial,sans-serif;margin:28px;color:#172b45}h1{font-size:22px}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #ccd5df;padding:7px;text-align:right}th{background:#edf3f9}th:first-child,td:first-child{text-align:center}.btn{padding:10px 14px;background:#0756a5;color:white;border:0;border-radius:5px;cursor:pointer;margin-bottom:18px}@media print{.btn{display:none}body{margin:0}}</style></head><body><button class="btn" onclick="window.print()">Imprimir / Guardar como PDF</button><h1>Cronograma de pagos</h1><p><b>Crédito:</b> <?= h($meta['numero_credito']) ?> | <b>Cliente:</b> <?= h($meta['nombres'].' '.$meta['apellidos']) ?><br><b>Monto aprobado:</b> Bs <?= number_format((float)$meta['monto_aprobado'],2,',','.') ?> | <b>Versión:</b> <?= (int)$meta['numero_version'] ?><br><b>Periodo:</b> <?= h($meta['fecha_inicio']) ?> a <?= h($meta['fecha_fin']) ?> | <b>Método:</b> <?= h($meta['metodo_amortizacion']) ?></p><table><thead><tr><th>Cuota</th><th>Vencimiento</th><th>Capital (Bs)</th><th>Interés (Bs)</th><th>Total (Bs)</th><th>Pagado (Bs)</th><th>Saldo (Bs)</th><th>Estado</th></tr></thead><tbody><?php foreach($lista as $q): ?><tr><td><?= (int)$q['numero_cuota'] ?></td><td><?= h($q['fecha_vencimiento']) ?></td><td><?= number_format((float)$q['capital'],2,',','.') ?></td><td><?= number_format((float)$q['interes'],2,',','.') ?></td><td><?= number_format((float)$q['monto_total'],2,',','.') ?></td><td><?= number_format((float)$q['monto_pagado'],2,',','.') ?></td><td><?= number_format((float)$q['saldo_pendiente'],2,',','.') ?></td><td><?= h($q['estado_cuota']) ?></td></tr><?php endforeach; ?></tbody></table><script>window.addEventListener('load',()=>window.print());</script></body></html><?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        redirectWith('danger', 'La solicitud no pudo validarse. Actualiza la página e inténtalo otra vez.');
    }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'generar_cronograma') {
            $idCredito = filter_input(INPUT_POST, 'id_credito', FILTER_VALIDATE_INT);
            if (!$idCredito || $idCredito < 1) {
                throw new RuntimeException('Selecciona un crédito válido.');
            }

            $stmt = $pdo->prepare("SELECT c.*, s.id_cliente, cl.nombres, cl.apellidos
                FROM CREDITO c
                INNER JOIN SOLICITUD_CREDITO s ON s.id_solicitud = c.id_solicitud
                INNER JOIN CLIENTE cl ON cl.id_cliente = s.id_cliente
                WHERE c.id_credito = ?");
            $stmt->execute([$idCredito]);
            $credito = $stmt->fetch();
            if (!$credito) throw new RuntimeException('El crédito seleccionado no existe.');
            if ((float)$credito['monto_aprobado'] <= 0 || (int)$credito['plazo_meses'] <= 0 || (float)$credito['tasa_interes'] < 0) {
                throw new RuntimeException('El crédito tiene monto, tasa o plazo inválido. Revisa sus datos.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(numero_version), 0) + 1 FROM CRONOGRAMA WHERE id_credito = ?');
            $stmt->execute([$idCredito]);
            $version = (int)$stmt->fetchColumn();

            $fechaInicio = new DateTime('today');
            if (!empty($credito['fecha_desembolso'])) {
                $fechaInicio = new DateTime($credito['fecha_desembolso']);
            }
            $fechaPrimeraCuota = (clone $fechaInicio)->modify('+1 month');
            $fechaFin = (clone $fechaPrimeraCuota)->modify('+' . max(0, (int)$credito['plazo_meses'] - 1) . ' months');

            $stmt = $pdo->prepare("INSERT INTO CRONOGRAMA
                (id_credito, numero_version, fecha_inicio, fecha_fin, frecuencia, metodo_amortizacion, estado)
                VALUES (?, ?, ?, ?, 'Mensual', 'Frances', 'Activo')");
            $stmt->execute([$idCredito, $version, $fechaPrimeraCuota->format('Y-m-d'), $fechaFin->format('Y-m-d')]);
            $idCronograma = (int)$pdo->lastInsertId();

            $principal = (float)$credito['monto_aprobado'];
            $tasaMensual = ((float)$credito['tasa_interes'] / 100) / 12;
            $n = (int)$credito['plazo_meses'];
            $cuotaBase = $tasaMensual > 0
                ? $principal * ($tasaMensual / (1 - pow(1 + $tasaMensual, -$n)))
                : $principal / $n;
            $saldoCapital = $principal;
            $insertCuota = $pdo->prepare("INSERT INTO CUOTA
                (id_cronograma, numero_cuota, fecha_vencimiento, capital, interes, monto_total, monto_pagado, saldo_pendiente, estado_cuota)
                VALUES (?, ?, ?, ?, ?, ?, 0, ?, 'Pendiente')");

            for ($i = 1; $i <= $n; $i++) {
                $interes = round($saldoCapital * $tasaMensual, 2);
                $capital = round($cuotaBase - $interes, 2);
                if ($i === $n || $capital > $saldoCapital) $capital = round($saldoCapital, 2);
                $total = round($capital + $interes, 2);
                $saldoCapital = round(max(0, $saldoCapital - $capital), 2);
                $fechaVencimiento = (clone $fechaPrimeraCuota)->modify('+' . ($i - 1) . ' months')->format('Y-m-d');
                $insertCuota->execute([$idCronograma, $i, $fechaVencimiento, $capital, $interes, $total, $total]);
            }
            $pdo->commit();
            redirectWith('success', "Cronograma generado correctamente (versión {$version}) con {$n} cuotas.");
        }

        if ($action === 'registrar_pago') {
            $idCredito = filter_input(INPUT_POST, 'id_credito_pago', FILTER_VALIDATE_INT);
            $monto = filter_input(INPUT_POST, 'monto_pago', FILTER_VALIDATE_FLOAT);
            $metodo = trim((string)($_POST['metodo_pago'] ?? ''));
            $referencia = trim((string)($_POST['numero_referencia'] ?? ''));
            $idUsuario = filter_input(INPUT_POST, 'id_usuario_registra', FILTER_VALIDATE_INT);
            if (!$idCredito || $idCredito < 1) throw new RuntimeException('Selecciona un crédito válido.');
            if ($monto === false || $monto === null || $monto <= 0) throw new RuntimeException('El monto del pago debe ser mayor que cero.');
            $metodosPermitidos = ['Efectivo', 'Transferencia', 'Tarjeta', 'Depósito'];
            if (!in_array($metodo, $metodosPermitidos, true)) throw new RuntimeException('Selecciona una forma de pago válida.');
            if (!$idUsuario || $idUsuario < 1) throw new RuntimeException('Ingresa el ID del usuario cajero registrado en USUARIO.');

            $stmt = $pdo->prepare("SELECT id_usuario FROM USUARIO WHERE id_usuario = ? AND estado = 'Activo'");
            $stmt->execute([$idUsuario]);
            if (!$stmt->fetch()) throw new RuntimeException('No existe un usuario activo con ese ID.');

            $stmt = $pdo->prepare("SELECT c.id_credito FROM CREDITO c WHERE c.id_credito = ?");
            $stmt->execute([$idCredito]);
            if (!$stmt->fetch()) throw new RuntimeException('El crédito no existe.');

            $tipoPago = (($_POST['tipo_pago'] ?? 'normal') === 'abono_capital') ? 'abono_capital' : 'normal';
            $pdo->beginTransaction();

            // Abono extraordinario a capital: conserva el cronograma anterior como historial
            // y crea una nueva versión reduciendo el plazo, manteniendo una cuota similar.
            if ($tipoPago === 'abono_capital') {
                $stmt = $pdo->prepare("SELECT cr.id_cronograma, c.monto_aprobado, c.tasa_interes, c.plazo_meses,
                    COALESCE(SUM(CASE WHEN q.monto_total > 0 THEN q.capital * (q.saldo_pendiente / q.monto_total) ELSE 0 END), 0) AS saldo_capital,
                    COALESCE(MAX(CASE WHEN q.saldo_pendiente > 0 THEN q.monto_total END), 0) AS cuota_referencia
                    FROM CRONOGRAMA cr INNER JOIN CREDITO c ON c.id_credito = cr.id_credito
                    LEFT JOIN CUOTA q ON q.id_cronograma = cr.id_cronograma
                    WHERE cr.id_credito = ? AND cr.estado = 'Activo'
                    GROUP BY cr.id_cronograma, c.monto_aprobado, c.tasa_interes, c.plazo_meses
                    ORDER BY cr.numero_version DESC LIMIT 1 FOR UPDATE");
                $stmt->execute([$idCredito]);
                $base = $stmt->fetch();
                if (!$base) throw new RuntimeException('Genera primero un cronograma activo para este crédito.');
                $saldoCapital = round((float)$base['saldo_capital'], 2);
                if ($monto >= $saldoCapital) throw new RuntimeException('El abono debe ser menor que el capital pendiente; para liquidar todo el crédito se requiere un proceso de cancelación total.');
                if ($monto <= 0) throw new RuntimeException('El abono debe ser mayor que cero.');
                $nuevoCapital = round($saldoCapital - $monto, 2);
                $cuotaObjetivo = (float)$base['cuota_referencia'];
                $tasaMensual = ((float)$base['tasa_interes'] / 100) / 12;
                if ($cuotaObjetivo <= 0) $cuotaObjetivo = $nuevoCapital / max(1, (int)$base['plazo_meses']);
                if ($tasaMensual > 0 && $cuotaObjetivo <= $nuevoCapital * $tasaMensual) {
                    throw new RuntimeException('La cuota actual no cubre los intereses; no se puede recalcular el plazo automáticamente.');
                }
                if ($tasaMensual > 0) {
                    $den = 1 - ($nuevoCapital * $tasaMensual / $cuotaObjetivo);
                    $nuevoPlazo = $den <= 0 ? (int)$base['plazo_meses'] : max(1, (int)ceil(-log($den) / log(1 + $tasaMensual)));
                } else {
                    $nuevoPlazo = max(1, (int)ceil($nuevoCapital / $cuotaObjetivo));
                }
                $nuevoPlazo = min($nuevoPlazo, (int)$base['plazo_meses']);
                $stmt = $pdo->prepare("INSERT INTO PAGO (id_usuario_registra, monto, metodo_pago, numero_referencia, estado) VALUES (?, ?, ?, ?, 'Abono a capital')");
                $refAbono = 'ABONO_CAPITAL' . ($referencia !== '' ? ' | ' . $referencia : '');
                $stmt->execute([$idUsuario, round($monto, 2), $metodo, $refAbono]);
                $idPago = (int)$pdo->lastInsertId();
                $numeroComprobante = 'CP-' . date('Ymd') . '-' . str_pad((string)$idPago, 6, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("INSERT INTO COMPROBANTE (id_pago, numero_comprobante, estado) VALUES (?, ?, 'Emitido')");
                $stmt->execute([$idPago, $numeroComprobante]);

                $stmt = $pdo->prepare("UPDATE CRONOGRAMA SET estado = 'Reemplazado' WHERE id_credito = ? AND estado = 'Activo'");
                $stmt->execute([$idCredito]);
                $stmt = $pdo->prepare('SELECT COALESCE(MAX(numero_version),0)+1 FROM CRONOGRAMA WHERE id_credito = ?');
                $stmt->execute([$idCredito]);
                $version = (int)$stmt->fetchColumn();
                $inicio = new DateTime('today');
                $primera = (clone $inicio)->modify('+1 month');
                $fin = (clone $primera)->modify('+' . max(0, $nuevoPlazo - 1) . ' months');
                $stmt = $pdo->prepare("INSERT INTO CRONOGRAMA (id_credito, numero_version, fecha_inicio, fecha_fin, frecuencia, metodo_amortizacion, estado) VALUES (?, ?, ?, ?, 'Mensual', 'Frances', 'Activo')");
                $stmt->execute([$idCredito, $version, $primera->format('Y-m-d'), $fin->format('Y-m-d')]);
                $idNuevoCronograma = (int)$pdo->lastInsertId();
                $cuotaNueva = $tasaMensual > 0 ? $nuevoCapital * ($tasaMensual / (1 - pow(1 + $tasaMensual, -$nuevoPlazo))) : $nuevoCapital / $nuevoPlazo;
                $saldo = $nuevoCapital;
                $insCuota = $pdo->prepare("INSERT INTO CUOTA (id_cronograma, numero_cuota, fecha_vencimiento, capital, interes, monto_total, monto_pagado, saldo_pendiente, estado_cuota) VALUES (?, ?, ?, ?, ?, ?, 0, ?, 'Pendiente')");
                for ($i=1; $i <= $nuevoPlazo; $i++) {
                    $interes = round($saldo * $tasaMensual, 2);
                    $capitalCuota = round($cuotaNueva - $interes, 2);
                    if ($i === $nuevoPlazo || $capitalCuota > $saldo) $capitalCuota = round($saldo, 2);
                    $totalCuota = round($capitalCuota + $interes, 2);
                    $saldo = round(max(0, $saldo - $capitalCuota), 2);
                    $vence = (clone $primera)->modify('+' . ($i-1) . ' months')->format('Y-m-d');
                    $insCuota->execute([$idNuevoCronograma, $i, $vence, $capitalCuota, $interes, $totalCuota, $totalCuota]);
                }
                $pdo->prepare('UPDATE CREDITO SET saldo_actual = ? WHERE id_credito = ?')->execute([$nuevoCapital, $idCredito]);
                $pdo->commit();
                redirectWith('success', "Abono a capital registrado. Se creó el cronograma versión {$version} con {$nuevoPlazo} cuotas y plazo reducido. Comprobante: {$numeroComprobante}.");
            }

            $stmt = $pdo->prepare("SELECT q.id_cuota, q.numero_cuota, q.monto_total, q.monto_pagado, q.saldo_pendiente
                FROM CUOTA q
                INNER JOIN CRONOGRAMA cr ON cr.id_cronograma = q.id_cronograma
                WHERE cr.id_credito = ? AND cr.estado = 'Activo' AND q.saldo_pendiente > 0
                ORDER BY q.fecha_vencimiento, q.numero_cuota FOR UPDATE");
            $stmt->execute([$idCredito]);
            $cuotas = $stmt->fetchAll();
            if (!$cuotas) throw new RuntimeException('Este crédito no tiene cuotas pendientes en un cronograma activo.');
            $saldoTotal = array_sum(array_map(fn($q) => (float)$q['saldo_pendiente'], $cuotas));
            if ($monto > $saldoTotal + 0.001) throw new RuntimeException('El pago supera el saldo pendiente de las cuotas (' . number_format($saldoTotal, 2, ',', '.') . ').');

            $stmt = $pdo->prepare("INSERT INTO PAGO (id_usuario_registra, monto, metodo_pago, numero_referencia, estado) VALUES (?, ?, ?, ?, 'Registrado')");
            $stmt->execute([$idUsuario, round($monto, 2), $metodo, $referencia !== '' ? $referencia : null]);
            $idPago = (int)$pdo->lastInsertId();
            $restante = round($monto, 2);
            $insertDetalle = $pdo->prepare('INSERT INTO DETALLE_PAGO (id_pago, id_cuota, monto_aplicado) VALUES (?, ?, ?)');
            $updateCuota = $pdo->prepare("UPDATE CUOTA SET monto_pagado = ?, saldo_pendiente = ?, estado_cuota = ? WHERE id_cuota = ?");

            foreach ($cuotas as $cuota) {
                if ($restante <= 0) break;
                $saldoCuota = round((float)$cuota['saldo_pendiente'], 2);
                $aplicado = min($restante, $saldoCuota);
                $nuevoPagado = round((float)$cuota['monto_pagado'] + $aplicado, 2);
                $nuevoSaldo = round(max(0, $saldoCuota - $aplicado), 2);
                $estadoCuota = $nuevoSaldo <= 0 ? 'Pagada' : 'Parcial';
                $insertDetalle->execute([$idPago, $cuota['id_cuota'], $aplicado]);
                $updateCuota->execute([$nuevoPagado, $nuevoSaldo, $estadoCuota, $cuota['id_cuota']]);
                $restante = round($restante - $aplicado, 2);
            }

            $numeroComprobante = 'CP-' . date('Ymd') . '-' . str_pad((string)$idPago, 6, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("INSERT INTO COMPROBANTE (id_pago, numero_comprobante, estado) VALUES (?, ?, 'Emitido')");
            $stmt->execute([$idPago, $numeroComprobante]);

            // Actualiza un saldo referencial con el capital pendiente calculado proporcionalmente.
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN q.monto_total > 0 THEN q.capital * (q.saldo_pendiente / q.monto_total) ELSE 0 END), 0)
                FROM CUOTA q INNER JOIN CRONOGRAMA cr ON cr.id_cronograma = q.id_cronograma
                WHERE cr.id_credito = ? AND cr.estado = 'Activo'");
            $stmt->execute([$idCredito]);
            $saldoCapitalEstimado = round((float)$stmt->fetchColumn(), 2);
            $stmt = $pdo->prepare('UPDATE CREDITO SET saldo_actual = ? WHERE id_credito = ?');
            $stmt->execute([$saldoCapitalEstimado, $idCredito]);

            $pdo->commit();
            redirectWith('success', "Pago registrado. Comprobante: {$numeroComprobante}.");
        }

        if ($action === 'actualizar_mora') {
            $hoy = date('Y-m-d');
            $insertadas = 0;
            $actualizadas = 0;
            $cerradas = 0;
            $pdo->beginTransaction();

            // Cierra las moras abiertas cuyas cuotas ya fueron pagadas completamente.
            // No modifica fechas de vencimiento ni elimina registros: conserva el historial.
            $cerrar = $pdo->prepare("UPDATE MORA m
                INNER JOIN CUOTA q ON q.id_cuota = m.id_cuota
                SET m.fecha_fin = ?, m.estado = 'Pagada', m.dias_atraso = GREATEST(m.dias_atraso, 0)
                WHERE m.fecha_fin IS NULL AND q.saldo_pendiente <= 0");
            $cerrar->execute([$hoy]);
            $cerradas = $cerrar->rowCount();

            $stmt = $pdo->prepare("SELECT q.id_cuota, q.fecha_vencimiento, q.saldo_pendiente
                FROM CUOTA q INNER JOIN CRONOGRAMA cr ON cr.id_cronograma = q.id_cronograma
                WHERE cr.estado = 'Activo' AND q.saldo_pendiente > 0 AND q.fecha_vencimiento < ?");
            $stmt->execute([$hoy]);
            $vencidas = $stmt->fetchAll();
            foreach ($vencidas as $cuota) {
                $dias = max(1, (int)((new DateTime($cuota['fecha_vencimiento']))->diff(new DateTime($hoy))->days));
                $stmt = $pdo->prepare("SELECT id_mora FROM MORA WHERE id_cuota = ? AND fecha_fin IS NULL ORDER BY id_mora DESC LIMIT 1");
                $stmt->execute([$cuota['id_cuota']]);
                $idMora = $stmt->fetchColumn();
                if ($idMora) {
                    $upd = $pdo->prepare("UPDATE MORA SET dias_atraso = ?, estado = 'Pendiente' WHERE id_mora = ?");
                    $upd->execute([$dias, $idMora]);
                    $actualizadas++;
                } else {
                    $ins = $pdo->prepare("INSERT INTO MORA (id_cuota, fecha_inicio, dias_atraso, interes_moratorio, estado, observaciones)
                        VALUES (?, ?, ?, 0, 'Pendiente', 'Mora detectada por vencimiento de cuota')");
                    $ins->execute([$cuota['id_cuota'], $cuota['fecha_vencimiento'], $dias]);
                    $insertadas++;
                }
                $updCuota = $pdo->prepare("UPDATE CUOTA SET estado_cuota = 'Vencida' WHERE id_cuota = ? AND estado_cuota <> 'Pagada'");
                $updCuota->execute([$cuota['id_cuota']]);
            }
            $pdo->commit();
            redirectWith('success', "Control de mora actualizado. Nuevos registros: {$insertadas}; revisados: {$actualizadas}; moras cerradas por pago completo: {$cerradas}.");
        }

        throw new RuntimeException('Acción no reconocida.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirectWith('danger', $e instanceof PDOException ? 'No se pudo completar la operación. Verifica las tablas y los datos relacionados.' : $e->getMessage());
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$creditos = $pdo->query("SELECT c.id_credito, c.numero_credito, c.monto_aprobado, c.tasa_interes, c.plazo_meses, c.estado_credito,
    cl.nombres, cl.apellidos
    FROM CREDITO c INNER JOIN SOLICITUD_CREDITO s ON s.id_solicitud = c.id_solicitud
    INNER JOIN CLIENTE cl ON cl.id_cliente = s.id_cliente ORDER BY c.id_credito DESC")->fetchAll();

$cronogramas = $pdo->query("SELECT cr.id_cronograma, cr.numero_version, cr.fecha_inicio, cr.fecha_fin, cr.estado,
    c.numero_credito, cl.nombres, cl.apellidos, COUNT(q.id_cuota) AS total_cuotas,
    SUM(CASE WHEN q.estado_cuota = 'Pagada' THEN 1 ELSE 0 END) AS cuotas_pagadas,
    (SELECT COALESCE(SUM(qa.saldo_pendiente), 0) FROM CRONOGRAMA cra
       INNER JOIN CUOTA qa ON qa.id_cronograma = cra.id_cronograma
       WHERE cra.id_credito = c.id_credito AND cra.estado = 'Activo') AS saldo_total_credito
    FROM CRONOGRAMA cr INNER JOIN CREDITO c ON c.id_credito = cr.id_credito
    INNER JOIN SOLICITUD_CREDITO s ON s.id_solicitud = c.id_solicitud
    INNER JOIN CLIENTE cl ON cl.id_cliente = s.id_cliente
    LEFT JOIN CUOTA q ON q.id_cronograma = cr.id_cronograma
    GROUP BY cr.id_cronograma, cr.numero_version, cr.fecha_inicio, cr.fecha_fin, cr.estado, c.numero_credito, cl.nombres, cl.apellidos
    ORDER BY cr.id_cronograma DESC LIMIT 12")->fetchAll();

$moras = $pdo->query("SELECT m.id_mora, m.fecha_inicio, m.dias_atraso, m.interes_moratorio, m.estado,
    q.numero_cuota, q.fecha_vencimiento, q.saldo_pendiente, c.numero_credito, cl.nombres, cl.apellidos
    FROM MORA m INNER JOIN CUOTA q ON q.id_cuota = m.id_cuota
    INNER JOIN CRONOGRAMA cr ON cr.id_cronograma = q.id_cronograma
    INNER JOIN CREDITO c ON c.id_credito = cr.id_credito
    INNER JOIN SOLICITUD_CREDITO s ON s.id_solicitud = c.id_solicitud
    INNER JOIN CLIENTE cl ON cl.id_cliente = s.id_cliente
    WHERE m.fecha_fin IS NULL ORDER BY m.dias_atraso DESC LIMIT 20")->fetchAll();

$stats = [
    'cronogramas' => (int)$pdo->query("SELECT COUNT(*) FROM CRONOGRAMA")->fetchColumn(),
    'cuotas_pendientes' => (int)$pdo->query("SELECT COUNT(*) FROM CUOTA WHERE saldo_pendiente > 0")->fetchColumn(),
    'pagos_hoy' => (int)$pdo->query("SELECT COUNT(*) FROM PAGO WHERE DATE(fecha_pago) = CURDATE() AND estado IN ('Registrado','Abono a capital')")->fetchColumn(),
    'moras' => (int)$pdo->query("SELECT COUNT(*) FROM MORA WHERE fecha_fin IS NULL")->fetchColumn(),
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Amortización y pagos | Sistema de Gestión de Créditos</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{--azul:#0756a5;--oscuro:#082f52;--fondo:#f3f7fc;--texto:#172b45}body{margin:0;background:var(--fondo);color:var(--texto);font-family:Arial,Helvetica,sans-serif}.topbar{background:linear-gradient(90deg,var(--oscuro),var(--azul));color:white;padding:15px 22px;display:flex;justify-content:space-between;align-items:center;gap:12px}.brand{font-weight:700}.shell{display:flex;min-height:calc(100vh - 60px)}.sidebar{width:235px;background:#102f4d;padding:18px 12px;flex-shrink:0}.sidebar a{display:block;color:#eaf3ff;text-decoration:none;padding:11px 12px;border-radius:7px;margin-bottom:5px}.sidebar a:hover,.sidebar a.active{background:#0875e1;color:#fff}.content{padding:26px;flex:1;min-width:0}.stat,.panel{background:white;border:1px solid #e1e9f2;border-radius:12px;box-shadow:0 4px 14px #173b5f0a}.stat{padding:18px;height:100%}.stat small{color:#64758b}.stat strong{display:block;font-size:1.7rem}.panel{margin-top:22px;overflow:hidden}.panel-head{padding:16px 19px;border-bottom:1px solid #e6edf5;font-weight:700}.panel-body{padding:19px}.table{margin-bottom:0;font-size:.87rem}.table thead th{white-space:nowrap;background:#f5f8fc;color:#425872}.hint{font-size:.84rem;color:#64758b}@media(max-width:800px){.shell{display:block}.sidebar{width:100%;display:flex;gap:5px;overflow:auto;padding:8px}.sidebar a{white-space:nowrap;margin:0}.content{padding:14px}.topbar{padding:13px}}
</style>
</head><body>
<header class="topbar"><div class="brand">▤ Sistema de Gestión de Créditos</div><div class="small">Integrante 4 · Amortización, cronogramas y pagos</div></header>
<div class="shell"><aside class="sidebar"><a href="#resumen" class="active">⌂ Resumen</a><a href="#cronogramas">▦ Cronogramas</a><a href="#pagos">▣ Registrar pago</a><a href="#mora">◷ Mora y vencimientos</a><a href="../../index.html">↪ Cerrar sesión</a></aside>
<main class="content"><h1 class="h3 fw-bold" id="resumen">Amortización, cronogramas y pagos</h1><p class="text-secondary mb-4">Genera cuotas, registra pagos y revisa vencimientos de los créditos.</p>
<?php if ($flash): ?><div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert"><span><?= h($flash['message']) ?></span><?php if ($flash['type'] === 'success' && preg_match('/Comprobante:\s*(CP-[A-Za-z0-9-]+)/', (string)$flash['message'], $match)): ?><button type="button" class="btn btn-outline-success btn-sm" disabled title="La impresión física se integrará cuando se conecte la impresora.">Imprimir</button><?php endif; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button></div><?php endif; ?>
<div class="row g-3"><div class="col-6 col-xl-3"><div class="stat"><small>Cronogramas generados</small><strong><?= $stats['cronogramas'] ?></strong></div></div><div class="col-6 col-xl-3"><div class="stat"><small>Cuotas con saldo</small><strong><?= $stats['cuotas_pendientes'] ?></strong></div></div><div class="col-6 col-xl-3"><div class="stat"><small>Pagos de hoy</small><strong><?= $stats['pagos_hoy'] ?></strong></div></div><div class="col-6 col-xl-3"><div class="stat"><small>Moras abiertas</small><strong><?= $stats['moras'] ?></strong></div></div></div>
<section class="panel" id="cronogramas"><div class="panel-head">1. Generar cronograma de amortización</div><div class="panel-body"><form method="post" class="row g-3 align-items-end"><input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="generar_cronograma"><div class="col-md-9"><label for="id_credito" class="form-label">Crédito aprobado</label><select class="form-select" name="id_credito" id="id_credito" required><option value="">Selecciona un crédito...</option><?php foreach ($creditos as $c): ?><option value="<?= (int)$c['id_credito'] ?>"><?= h($c['numero_credito']) ?> — <?= h($c['nombres'].' '.$c['apellidos']) ?> · Bs <?= number_format((float)$c['monto_aprobado'],2,',','.') ?> · <?= (int)$c['plazo_meses'] ?> meses</option><?php endforeach; ?></select></div><div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Generar cronograma</button></div></form><p class="hint mt-3 mb-0">Se utiliza amortización francesa, tasa anual convertida a tasa mensual y vencimientos mensuales. Generar otra versión no borra las anteriores.</p>
<div class="table-responsive mt-4"><table class="table table-hover"><thead><tr><th>Crédito</th><th>Cliente</th><th>Versión</th><th>Inicio</th><th>Fin</th><th>Cuotas</th><th>Pagadas</th><th>Saldo total pendiente (Bs)</th><th>Estado</th></tr></thead><tbody><?php if (!$cronogramas): ?><tr><td colspan="9" class="text-center text-secondary py-3">Todavía no hay cronogramas.</td></tr><?php else: foreach ($cronogramas as $cr): ?><tr><td><?= h($cr['numero_credito']) ?></td><td><?= h($cr['nombres'].' '.$cr['apellidos']) ?></td><td><?= (int)$cr['numero_version'] ?></td><td><?= h($cr['fecha_inicio']) ?></td><td><?= h($cr['fecha_fin']) ?></td><td><?= (int)$cr['total_cuotas'] ?></td><td><?= (int)$cr['cuotas_pagadas'] ?></td><td><strong>Bs <?= number_format((float)$cr['saldo_total_credito'],2,',','.') ?></strong></td><td><span class="badge text-bg-<?= $cr['estado']==='Activo'?'success':'secondary' ?>"><?= h($cr['estado']) ?></span><div class="d-flex gap-2 mt-2 flex-wrap"><button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#detalle<?= (int)$cr['id_cronograma'] ?>">Ver cuotas</button><a class="btn btn-sm btn-outline-secondary" target="_blank" href="?pdf_cronograma=<?= (int)$cr['id_cronograma'] ?>">Descargar PDF</a></div></td></tr><tr class="collapse" id="detalle<?= (int)$cr['id_cronograma'] ?>"><td colspan="9"><div class="table-responsive p-2"><table class="table table-sm table-bordered"><thead><tr><th>Cuota</th><th>Vencimiento</th><th>Capital</th><th>Interés</th><th>Total</th><th>Pagado</th><th>Saldo</th><th>Estado</th></tr></thead><tbody><?php $stDetalle=$pdo->prepare('SELECT numero_cuota,fecha_vencimiento,capital,interes,monto_total,monto_pagado,saldo_pendiente,estado_cuota FROM CUOTA WHERE id_cronograma=? ORDER BY numero_cuota'); $stDetalle->execute([$cr['id_cronograma']]); foreach($stDetalle->fetchAll() as $dq): ?><tr><td><?= (int)$dq['numero_cuota'] ?></td><td><?= h($dq['fecha_vencimiento']) ?></td><td><?= number_format((float)$dq['capital'],2,',','.') ?></td><td><?= number_format((float)$dq['interes'],2,',','.') ?></td><td><?= number_format((float)$dq['monto_total'],2,',','.') ?></td><td><?= number_format((float)$dq['monto_pagado'],2,',','.') ?></td><td><?= number_format((float)$dq['saldo_pendiente'],2,',','.') ?></td><td><?= h($dq['estado_cuota']) ?></td></tr><?php endforeach; ?></tbody></table></div></td></tr><?php endforeach; endif; ?></tbody></table></div></div></section>
<section class="panel" id="pagos"><div class="panel-head">2. Registrar pago</div><div class="panel-body"><form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="registrar_pago"><div class="col-md-6"><label class="form-label" for="id_credito_pago">Crédito</label><select class="form-select" id="id_credito_pago" name="id_credito_pago" required><option value="">Selecciona un crédito...</option><?php foreach ($creditos as $c): ?><option value="<?= (int)$c['id_credito'] ?>"><?= h($c['numero_credito'].' — '.$c['nombres'].' '.$c['apellidos']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label" for="id_usuario_registra">ID del usuario cajero (USUARIO)</label><input class="form-control" type="number" min="1" id="id_usuario_registra" name="id_usuario_registra" required></div><div class="col-md-4"><label class="form-label" for="monto_pago">Monto (Bs)</label><input class="form-control" type="number" id="monto_pago" name="monto_pago" min="0.01" step="0.01" required></div><div class="col-md-4"><label class="form-label" for="tipo_pago">Tipo de operación</label><select class="form-select" id="tipo_pago" name="tipo_pago"><option value="normal">Pago de cuotas</option><option value="abono_capital">Abono extraordinario a capital (reduce plazo)</option></select></div><div class="col-md-4"><label class="form-label" for="metodo_pago">Forma de pago</label><select class="form-select" id="metodo_pago" name="metodo_pago" required><option value="">Selecciona...</option><option>Efectivo</option><option>Transferencia</option><option>Tarjeta</option><option>Depósito</option></select></div><div class="col-md-4"><label class="form-label" for="numero_referencia">Referencia (opcional)</label><input class="form-control" id="numero_referencia" name="numero_referencia" maxlength="100"></div><div class="col-12"><button class="btn btn-success" type="submit">Registrar pago y emitir comprobante</button></div></form><p class="hint mt-3 mb-0">El pago normal se aplica a las cuotas pendientes más antiguas. El abono extraordinario se aplica al capital pendiente y genera una nueva versión con menos cuotas; el cronograma anterior queda como historial.</p></div></section>
<section class="panel" id="mora"><div class="panel-head d-flex justify-content-between align-items-center flex-wrap gap-2"><span>3. Mora y vencimientos</span><form method="post" class="m-0"><input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="actualizar_mora"><button class="btn btn-outline-danger btn-sm" type="submit">Detectar cuotas vencidas</button></form></div><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Crédito</th><th>Cliente</th><th>Cuota</th><th>Vencimiento</th><th>Días atraso</th><th>Saldo pendiente</th><th>Estado mora</th></tr></thead><tbody><?php if (!$moras): ?><tr><td colspan="7" class="text-center text-secondary py-3">No hay moras abiertas registradas.</td></tr><?php else: foreach ($moras as $m): ?><tr><td><?= h($m['numero_credito']) ?></td><td><?= h($m['nombres'].' '.$m['apellidos']) ?></td><td><?= (int)$m['numero_cuota'] ?></td><td><?= h($m['fecha_vencimiento']) ?></td><td><span class="badge text-bg-danger"><?= (int)$m['dias_atraso'] ?></span></td><td>Bs <?= number_format((float)$m['saldo_pendiente'],2,',','.') ?></td><td><?= h($m['estado']) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<p class="hint mt-4">Proyecto académico. Antes de usarlo con datos reales, integrar la sesión y permisos del login, revisar la fórmula de intereses con la política de la entidad y probar las operaciones con datos ficticios.</p>
</main></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>
