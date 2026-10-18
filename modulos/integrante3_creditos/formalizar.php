<?php
require_once __DIR__ . '/../../config/funciones.php';
require_once __DIR__ . '/../../config/plantilla.php';


$db = bd();
$idSolicitud = (int)($_GET['id_solicitud'] ?? 0);
$s = $idSolicitud > 0 ? cargar_solicitud($db, $idSolicitud) : null;
if (!$s) {
    flash('danger', 'La solicitud indicada no existe.');
    header('Location: menu_principal.php');
    exit;
}

$credito  = obtener_credito($db, $idSolicitud);
$contrato = $credito ? obtener_contrato($db, (int)$credito['id_credito']) : null;

$st = $db->prepare('SELECT * FROM garantia WHERE id_solicitud = ? ORDER BY id_garantia');
$st->execute([$idSolicitud]);
$garantias = $st->fetchAll();

$st = $db->prepare('SELECT d.*, g.tipo_garantia FROM documento_garantia d JOIN garantia g ON g.id_garantia = d.id_garantia
                    WHERE g.id_solicitud = ? ORDER BY d.id_documento_garantia');
$st->execute([$idSolicitud]);
$documentos = $st->fetchAll();

$cuotasPagadas = $credito ? (int)escalar($db, "SELECT COUNT(*) FROM cuota cu JOIN cronograma cr ON cr.id_cronograma = cu.id_cronograma
                                               WHERE cr.id_credito = ? AND cr.estado = 'Activo' AND cu.estado_cuota = 'Pagada'", [$credito['id_credito']]) : 0;

$editar           = puede_editar();
$enAprobado       = $credito && $credito['estado_credito'] === ESTADO_CREDITO_APROBADO;
$modificable      = $editar && (!$credito || ($enAprobado && (!$contrato || $contrato['estado'] === 'Generado')));
$puedeCrear       = $editar && !$credito && $s['estado_solicitud'] === ESTADO_SOLICITUD_APROBADA;
$puedeDesembolsar = $editar && $enAprobado && $contrato && $contrato['estado'] === 'Firmado';

pagina_inicio('Formalizar crédito');
?>
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="menu_principal.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h4 class="fw-bold mb-0">Formalizar Crédito</h4>
</div>

<!-- Solicitud aprobada -->
<section class="tarjeta">
    <div class="tarjeta-titulo"><span>Solicitud aprobada</span><span class="badge <?= clase_estado($s['estado_solicitud']) ?>"><?= e($s['estado_solicitud']) ?></span></div>
    <div class="tarjeta-cuerpo row g-3">
        <div class="col-6 col-md-2"><div class="dato-etiqueta">N° Solicitud</div><div class="dato-valor"><?= (int)$s['id_solicitud'] ?></div></div>
        <div class="col-6 col-md-4"><div class="dato-etiqueta">Cliente</div><div class="dato-valor"><?= e($s['nombres'] . ' ' . $s['apellidos']) ?> <span class="text-secondary fw-normal">(CI <?= e($s['ci']) ?>)</span></div></div>
        <div class="col-6 col-md-2"><div class="dato-etiqueta">Tipo</div><div class="dato-valor"><?= e($s['tipo_nombre']) ?></div></div>
        <div class="col-6 col-md-2"><div class="dato-etiqueta">Monto</div><div class="dato-valor"><?= bs($s['monto_solicitado']) ?></div></div>
        <div class="col-6 col-md-2"><div class="dato-etiqueta">Plazo</div><div class="dato-valor"><?= (int)$s['plazo_meses'] ?> meses</div></div>
        <?php if ($s['destino']): ?><div class="col-12"><div class="dato-etiqueta">Destino</div><div><?= e($s['destino']) ?></div></div><?php endif; ?>
    </div>
</section>

<!-- Datos del crédito -->
<section class="tarjeta" id="datos-credito">
    <div class="tarjeta-titulo"><span>Datos del crédito</span>
        <?php if ($credito): ?><span class="badge <?= clase_estado($credito['estado_credito']) ?>"><?= e($credito['estado_credito']) ?></span><?php endif; ?>
    </div>
    <div class="tarjeta-cuerpo">
    <?php if ($credito): ?>
        <div class="row g-3">
            <div class="col-6 col-md-3"><div class="dato-etiqueta">N° Crédito</div><div class="dato-valor"><?= e($credito['numero_credito']) ?></div></div>
            <div class="col-6 col-md-3"><div class="dato-etiqueta">Tasa de interés</div><div class="dato-valor"><?= number_format((float)$credito['tasa_interes'], 2, ',', '.') ?> %</div></div>
            <div class="col-6 col-md-3"><div class="dato-etiqueta">Fecha de aprobación</div><div class="dato-valor"><?= fecha($credito['fecha_aprobacion']) ?></div></div>
            <div class="col-6 col-md-3"><div class="dato-etiqueta">Fecha de desembolso</div><div class="dato-valor"><?= fecha($credito['fecha_desembolso']) ?></div></div>
        </div>
    <?php else: ?>
        <?php if (!$puedeCrear): ?>
            <div class="alert alert-warning mb-0">
                <?= $s['estado_solicitud'] !== ESTADO_SOLICITUD_APROBADA ? 'Solo se puede crear el crédito de una solicitud aprobada.' : 'Tu rol no puede crear créditos.' ?>
            </div>
        <?php else: ?>
        <form method="post" action="acciones.php" class="row g-3 align-items-end">
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="crear_credito">
            <input type="hidden" name="id_solicitud" value="<?= $idSolicitud ?>">
            <div class="col-md-3"><label class="form-label">Tasa de interés (% anual)</label>
                <input type="number" step="0.01" min="0.01" max="100" name="tasa_interes" class="form-control" value="<?= e($s['tasa_tipo']) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Fecha de aprobación</label>
                <input type="date" name="fecha_aprobacion" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-3"><label class="form-label">Estado</label>
                <input class="form-control" value="<?= e(ESTADO_CREDITO_APROBADO) ?>" disabled></div>
            <div class="col-md-3 text-md-end"><button class="btn btn-primary"><i class="bi bi-plus-circle"></i> Crear crédito</button></div>
        </form>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</section>

<!-- Garantías -->
<section class="tarjeta" id="garantias">
    <div class="tarjeta-titulo"><span>Garantías del crédito</span>
        <?php if ($modificable): ?><button class="btn btn-success btn-sm" type="button" onclick="abrirGarantia(null)"><i class="bi bi-plus-lg"></i> Nueva garantía</button><?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead class="table-light"><tr><th>Tipo</th><th>Descripción</th><th class="text-end">Valor estimado</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($garantias as $g): ?>
                <tr>
                    <td><?= e($g['tipo_garantia']) ?></td>
                    <td><?= e($g['descripcion']) ?></td>
                    <td class="text-end"><?= $g['valor_estimado'] !== null ? bs($g['valor_estimado']) : '—' ?></td>
                    <td><span class="badge <?= clase_estado($g['estado']) ?>"><?= e($g['estado']) ?></span></td>
                    <td class="text-end text-nowrap">
                    <?php if ($modificable): ?>
                        <button type="button" class="btn btn-primary btn-sm" onclick="abrirGarantia(this)"
                                data-id="<?= (int)$g['id_garantia'] ?>" data-tipo="<?= e($g['tipo_garantia']) ?>"
                                data-descripcion="<?= e($g['descripcion']) ?>" data-valor="<?= e($g['valor_estimado']) ?>"><i class="bi bi-pencil"></i></button>
                        <form method="post" action="acciones.php" class="d-inline" onsubmit="return confirm('¿Eliminar la garantía y sus documentos?')">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="eliminar_garantia">
                            <input type="hidden" name="id_solicitud" value="<?= $idSolicitud ?>">
                            <input type="hidden" name="id_garantia" value="<?= (int)$g['id_garantia'] ?>">
                            <button class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    <?php else: ?><span class="text-secondary">—</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$garantias): ?><tr><td colspan="5" class="text-center text-secondary py-3">Sin garantías registradas.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Documentos de garantía -->
<section class="tarjeta" id="documentos">
    <div class="tarjeta-titulo"><span>Documentos de garantía</span>
        <?php if ($modificable && $garantias): ?><button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#modalDocumento"><i class="bi bi-upload"></i> Subir documento</button><?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead class="table-light"><tr><th>Nombre</th><th>Tipo de documento</th><th>Garantía</th><th>Cargado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($documentos as $d): ?>
                <tr>
                    <td><?= e($d['nombre_archivo']) ?></td>
                    <td><?= e($d['tipo_documento']) ?></td>
                    <td><?= e('#' . $d['id_garantia'] . ' ' . $d['tipo_garantia']) ?></td>
                    <td><?= fecha($d['fecha_carga']) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-outline-primary btn-sm" target="_blank" href="descargar.php?id=<?= (int)$d['id_documento_garantia'] ?>"><i class="bi bi-eye"></i></a>
                    <?php if ($modificable): ?>
                        <form method="post" action="acciones.php" class="d-inline" onsubmit="return confirm('¿Eliminar este documento?')">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="eliminar_documento">
                            <input type="hidden" name="id_solicitud" value="<?= $idSolicitud ?>">
                            <input type="hidden" name="id_documento" value="<?= (int)$d['id_documento_garantia'] ?>">
                            <button class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$documentos): ?><tr><td colspan="5" class="text-center text-secondary py-3">Sin documentos cargados.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Contrato -->
<section class="tarjeta" id="contrato">
    <div class="tarjeta-titulo">Contrato de crédito</div>
    <div class="tarjeta-cuerpo">
    <?php if (!$credito): ?>
        <div class="text-secondary">Primero crea el crédito para poder generar el contrato.</div>
    <?php else: ?>
        <div class="row g-3 align-items-center">
            <div class="col-6 col-md-3"><div class="dato-etiqueta">N° Contrato</div><div class="dato-valor"><?= $contrato ? e($contrato['numero_contrato']) : '—' ?></div></div>
            <div class="col-6 col-md-3"><div class="dato-etiqueta">Fecha firma</div><div class="dato-valor"><?= $contrato ? fecha($contrato['fecha_firma']) : '—' ?></div></div>
            <div class="col-6 col-md-2"><div class="dato-etiqueta">Estado</div>
                <?= $contrato ? '<span class="badge ' . clase_estado($contrato['estado']) . '">' . e($contrato['estado']) . '</span>' : '<span class="text-secondary">Sin generar</span>' ?></div>
            <div class="col-12 col-md-4 d-flex gap-2 justify-content-md-end flex-wrap">
                <?php if ($contrato): ?>
                    <a class="btn btn-outline-primary" target="_blank" href="contrato_ver.php?id_credito=<?= (int)$credito['id_credito'] ?>"><i class="bi bi-file-earmark-pdf"></i> Ver contrato (PDF)</a>
                <?php endif; ?>
                <?php if ($editar && $enAprobado && (!$contrato || $contrato['estado'] === 'Generado')): ?>
                    <form method="post" action="acciones.php">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="generar_contrato">
                        <input type="hidden" name="id_solicitud" value="<?= $idSolicitud ?>">
                        <button class="btn btn-primary"><i class="bi bi-file-earmark-plus"></i> <?= $contrato ? 'Regenerar contrato' : 'Generar contrato' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($editar && $contrato && $contrato['estado'] === 'Generado'): ?>
            <hr>
            <form method="post" action="acciones.php" class="row g-2 align-items-end">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="registrar_firma">
                <input type="hidden" name="id_solicitud" value="<?= $idSolicitud ?>">
                <div class="col-md-3"><label class="form-label">Fecha de firma</label>
                    <input type="date" name="fecha_firma" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></div>
                <div class="col-md-3"><button class="btn btn-success"><i class="bi bi-pen"></i> Registrar firma</button></div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</section>

<!-- Estado del crédito -->
<section class="tarjeta" id="estado-credito">
    <div class="tarjeta-titulo">Estado del crédito</div>
    <div class="tarjeta-cuerpo">
    <?php if (!$credito): ?>
        <div class="text-secondary">Aún no existe el crédito.</div>
    <?php else: ?>
        <div class="row g-3">
            <div class="col-6 col-md-3"><div class="dato-etiqueta">Saldo inicial</div><div class="dato-valor"><?= bs($credito['monto_aprobado']) ?></div></div>
            <div class="col-6 col-md-3"><div class="dato-etiqueta">Saldo actual</div><div class="dato-valor"><?= bs($credito['saldo_actual']) ?></div></div>
            <div class="col-6 col-md-3"><div class="dato-etiqueta">Cuotas pagadas</div><div class="dato-valor"><?= $cuotasPagadas ?> / <?= (int)$credito['plazo_meses'] ?></div></div>
            <div class="col-6 col-md-3"><div class="dato-etiqueta">Estado</div><span class="badge <?= clase_estado($credito['estado_credito']) ?>"><?= e($credito['estado_credito']) ?></span></div>
        </div>
        <?php if ($enAprobado && $editar): ?>
            <hr>
            <form method="post" action="acciones.php" class="row g-2 align-items-end">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="desembolsar">
                <input type="hidden" name="id_solicitud" value="<?= $idSolicitud ?>">
                <div class="col-md-3"><label class="form-label">Fecha de desembolso</label>
                    <input type="date" name="fecha_desembolso" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required <?= $puedeDesembolsar ? '' : 'disabled' ?>></div>
                <div class="col-md-4"><button class="btn btn-primary" <?= $puedeDesembolsar ? '' : 'disabled' ?>><i class="bi bi-cash-stack"></i> Desembolsar crédito</button></div>
                <?php if (!$puedeDesembolsar): ?><div class="col-12 text-secondary small">Para desembolsar, el contrato debe estar firmado.</div><?php endif; ?>
            </form>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</section>

<!-- Modal garantía -->
<div class="modal fade" id="modalGarantia" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="acciones.php" id="formGarantia">
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="guardar_garantia">
            <input type="hidden" name="id_solicitud" value="<?= $idSolicitud ?>">
            <input type="hidden" name="id_garantia" value="">
            <div class="modal-header"><h5 class="modal-title" id="tituloGarantia">Nueva garantía</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body vstack gap-3">
                <div><label class="form-label">Tipo</label>
                    <select name="tipo_garantia" class="form-select" required><?php foreach (TIPOS_GARANTIA as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></div>
                <div><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="3" maxlength="1000" required></textarea></div>
                <div><label class="form-label">Valor estimado (Bs)</label><input type="number" name="valor_estimado" class="form-control" step="0.01" min="0">
                    <div class="form-text">Obligatorio para Inmueble y Vehículo.</div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar</button></div>
        </form>
    </div>
</div>

<!-- Modal documento -->
<div class="modal fade" id="modalDocumento" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="acciones.php" enctype="multipart/form-data">
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="subir_documento">
            <input type="hidden" name="id_solicitud" value="<?= $idSolicitud ?>">
            <div class="modal-header"><h5 class="modal-title">Subir documento de garantía</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body vstack gap-3">
                <div><label class="form-label">Garantía</label>
                    <select name="id_garantia" class="form-select" required>
                        <?php foreach ($garantias as $g): ?><option value="<?= (int)$g['id_garantia'] ?>"><?= e('#' . $g['id_garantia'] . ' ' . $g['tipo_garantia'] . ' - ' . mb_strimwidth($g['descripcion'], 0, 40, '…')) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label class="form-label">Tipo de documento</label>
                    <select name="tipo_documento" class="form-select" required><?php foreach (TIPOS_DOC_GARANTIA as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></div>
                <div><label class="form-label">Archivo</label><input type="file" name="archivo" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                    <div class="form-text">PDF, JPG o PNG. Máximo 5 MB.</div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Subir</button></div>
        </form>
    </div>
</div>

<?php
pagina_fin(<<<'JS'
function abrirGarantia(btn) {
    const f = document.getElementById('formGarantia');
    f.id_garantia.value     = btn ? btn.dataset.id : '';
    f.tipo_garantia.value   = btn ? btn.dataset.tipo : 'Inmueble';
    f.descripcion.value     = btn ? btn.dataset.descripcion : '';
    f.valor_estimado.value  = btn ? btn.dataset.valor : '';
    document.getElementById('tituloGarantia').textContent = btn ? 'Editar garantía' : 'Nueva garantía';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalGarantia')).show();
}
JS);