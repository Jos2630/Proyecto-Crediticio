<?php
require_once __DIR__ . '/../../config/funciones.php';

$db = bd();
$st = $db->prepare("SELECT ct.numero_contrato, ct.fecha_generacion, ct.fecha_firma, ct.condiciones, ct.estado,
                           c.numero_credito, c.monto_aprobado, c.plazo_meses, c.tasa_interes,
                           cl.ci, cl.nombres, cl.apellidos, cl.direccion, tc.nombre AS tipo,
                           CONCAT(u.nombres, ' ', u.apellidos) AS aprobador
                    FROM contrato ct
                    JOIN credito c ON c.id_credito = ct.id_credito
                    JOIN solicitud_credito s ON s.id_solicitud = c.id_solicitud
                    JOIN cliente cl ON cl.id_cliente = s.id_cliente
                    JOIN tipo_credito tc ON tc.id_tipo_credito = s.id_tipo_credito
                    LEFT JOIN usuario u ON u.id_usuario = c.id_usuario_aprobador
                    WHERE ct.id_credito = ?");
$st->execute([(int)($_GET['id_credito'] ?? 0)]);
$d = $st->fetch();
if (!$d) { http_response_code(404); exit('Contrato no encontrado.'); }
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Contrato <?= e($d['numero_contrato']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #eef1f6; }
        .hoja { max-width: 820px; margin: 1.5rem auto; background: #fff; padding: 3rem; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
        .clausulas { white-space: pre-line; text-align: justify; line-height: 1.6; }
        .firma { border-top: 1px solid #000; padding-top: .4rem; margin-top: 4rem; text-align: center; }
        @media print { body { background: #fff; } .hoja { box-shadow: none; margin: 0; padding: 0; } .no-imprimir { display: none !important; } }
    </style>
</head>
<body>
<div class="hoja">
    <div class="no-imprimir d-flex justify-content-between mb-4">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.close()">Cerrar</button>
        <button class="btn btn-primary btn-sm" onclick="window.print()">Imprimir / Guardar como PDF</button>
    </div>
    <h3 class="text-center fw-bold">CONTRATO DE CRÉDITO</h3>
    <p class="text-center text-secondary">N° <?= e($d['numero_contrato']) ?> · Crédito <?= e($d['numero_credito']) ?></p>

    <table class="table table-sm my-4">
        <tr><th style="width:30%">Cliente</th><td><?= e($d['nombres'] . ' ' . $d['apellidos']) ?></td></tr>
        <tr><th>Cédula de identidad</th><td><?= e($d['ci']) ?></td></tr>
        <tr><th>Dirección</th><td><?= e($d['direccion'] ?: '—') ?></td></tr>
        <tr><th>Tipo de crédito</th><td><?= e($d['tipo']) ?></td></tr>
        <tr><th>Monto</th><td><?= bs($d['monto_aprobado']) ?></td></tr>
        <tr><th>Plazo</th><td><?= (int)$d['plazo_meses'] ?> meses</td></tr>
        <tr><th>Tasa de interés</th><td><?= number_format((float)$d['tasa_interes'], 2, ',', '.') ?> % anual</td></tr>
        <tr><th>Fecha de firma</th><td><?= fecha($d['fecha_firma']) ?></td></tr>
    </table>

    <div class="clausulas"><?= e($d['condiciones']) ?></div>

    <div class="row mt-5">
        <div class="col-6"><div class="firma"><?= e($d['nombres'] . ' ' . $d['apellidos']) ?><br><small>CLIENTE · CI <?= e($d['ci']) ?></small></div></div>
        <div class="col-6"><div class="firma"><?= e($d['aprobador'] ?: 'Representante del banco') ?><br><small>BANCO</small></div></div>
    </div>
    <p class="text-secondary small mt-5 mb-0">Documento modelo con fines académicos.</p>
</div>
</body>
</html>