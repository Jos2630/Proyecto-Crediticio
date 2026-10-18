<?php
require_once __DIR__ . '/../../config/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: menu_principal.php'); exit; }

function volver(int $id, string $ancla = ''): never
{
    header('Location: formalizar.php?id_solicitud=' . $id . ($ancla !== '' ? '#' . $ancla : ''));
    exit;
}

function verificar_modificable(PDO $db, array $s): void
{
    $c = obtener_credito($db, (int)$s['id_solicitud']);
    if (!$c) return;
    if ($c['estado_credito'] !== ESTADO_CREDITO_APROBADO) {
        throw new ReglaNegocio('El crédito ya fue desembolsado: las garantías y sus documentos no se pueden modificar.');
    }
    $ct = obtener_contrato($db, (int)$c['id_credito']);
    if ($ct && $ct['estado'] !== 'Generado') {
        throw new ReglaNegocio('El contrato ya fue firmado: las garantías y sus documentos no se pueden modificar.');
    }
}

function garantia_de_solicitud(PDO $db, int $idGarantia, int $idSolicitud): array
{
    $st = $db->prepare('SELECT * FROM garantia WHERE id_garantia = ? AND id_solicitud = ?');
    $st->execute([$idGarantia, $idSolicitud]);
    return $st->fetch() ?: throw new ReglaNegocio('La garantía no existe o no pertenece a esta solicitud.');
}

// ------------------------------------------------------------------ crédito
function accion_crear_credito(PDO $db, array $s): void
{
    $tasa  = filter_var($_POST['tasa_interes'] ?? '', FILTER_VALIDATE_FLOAT);
    $fecha = validar_fecha((string)($_POST['fecha_aprobacion'] ?? ''), 'fecha de aprobación');
    if ($tasa === false || $tasa <= 0 || $tasa > 100) throw new ReglaNegocio('La tasa de interés debe ser mayor a 0 y no superar 100 %.');
    if ($s['fecha_decision'] && $fecha < substr((string)$s['fecha_decision'], 0, 10)) {
        throw new ReglaNegocio('La fecha de aprobación no puede ser anterior a la decisión de la solicitud.');
    }

    $db->beginTransaction();
    $estado = escalar($db, 'SELECT estado_solicitud FROM solicitud_credito WHERE id_solicitud = ? FOR UPDATE', [$s['id_solicitud']]);
    if ($estado !== ESTADO_SOLICITUD_APROBADA) throw new ReglaNegocio('Solo se puede crear el crédito de una solicitud aprobada.');
    if (obtener_credito($db, (int)$s['id_solicitud'], true)) throw new ReglaNegocio('Esta solicitud ya tiene un crédito.');

    if ($s['monto_solicitado'] < $s['monto_minimo'] || $s['monto_solicitado'] > $s['monto_maximo']) {
        throw new ReglaNegocio('El monto está fuera de los límites del tipo de crédito (' . bs($s['monto_minimo']) . ' a ' . bs($s['monto_maximo']) . ').');
    }
    if ($s['plazo_meses'] < $s['plazo_minimo'] || $s['plazo_meses'] > $s['plazo_maximo']) {
        throw new ReglaNegocio("El plazo está fuera de los límites del tipo de crédito ({$s['plazo_minimo']} a {$s['plazo_maximo']} meses).");
    }

    $numero = siguiente_numero($db, 'credito', 'numero_credito', 'CR');
    $db->prepare('INSERT INTO credito (id_solicitud, id_usuario_aprobador, numero_credito, fecha_aprobacion, monto_aprobado, tasa_interes, plazo_meses, saldo_actual, estado_credito)
                  VALUES (?,?,?,?,?,?,?,0,?)')
       ->execute([$s['id_solicitud'], $s['id_usuario_decide'], $numero, $fecha, $s['monto_solicitado'], $tasa, $s['plazo_meses'], ESTADO_CREDITO_APROBADO]);
    $id = (int)$db->lastInsertId();
    auditar($db, 'CREAR', 'credito', $id, "Crédito $numero creado desde la solicitud #{$s['id_solicitud']} por " . bs($s['monto_solicitado']));
    $db->commit();
    flash('success', "Crédito $numero creado correctamente.");
}

// ---------------------------------------------------------------- garantías
function accion_guardar_garantia(PDO $db, array $s): void
{
    verificar_modificable($db, $s);
    $id    = (int)($_POST['id_garantia'] ?? 0);
    $tipo  = (string)($_POST['tipo_garantia'] ?? '');
    $desc  = trim((string)($_POST['descripcion'] ?? ''));
    $vTxt  = trim((string)($_POST['valor_estimado'] ?? ''));
    $valor = $vTxt === '' ? null : filter_var($vTxt, FILTER_VALIDATE_FLOAT);

    if (!in_array($tipo, TIPOS_GARANTIA, true)) throw new ReglaNegocio('Tipo de garantía no válido.');
    if ($desc === '' || mb_strlen($desc) > 1000) throw new ReglaNegocio('La descripción es obligatoria (máximo 1000 caracteres).');
    if ($vTxt !== '' && ($valor === false || $valor < 0)) throw new ReglaNegocio('El valor estimado no es válido.');
    if (in_array($tipo, ['Inmueble', 'Vehículo'], true) && ($valor === null || $valor <= 0)) {
        throw new ReglaNegocio("El valor estimado es obligatorio para garantías de tipo $tipo.");
    }

    if ($id > 0) {
        garantia_de_solicitud($db, $id, (int)$s['id_solicitud']);
        $db->prepare('UPDATE garantia SET tipo_garantia = ?, descripcion = ?, valor_estimado = ? WHERE id_garantia = ?')->execute([$tipo, $desc, $valor, $id]);
        auditar($db, 'ACTUALIZAR', 'garantia', $id, "Garantía $tipo actualizada en la solicitud #{$s['id_solicitud']}");
        flash('success', 'Garantía actualizada.');
    } else {
        $db->prepare("INSERT INTO garantia (id_solicitud, tipo_garantia, descripcion, valor_estimado, estado) VALUES (?,?,?,?,'Registrada')")
           ->execute([$s['id_solicitud'], $tipo, $desc, $valor]);
        auditar($db, 'CREAR', 'garantia', (int)$db->lastInsertId(), "Garantía $tipo registrada en la solicitud #{$s['id_solicitud']}");
        flash('success', 'Garantía registrada.');
    }
}

function accion_eliminar_garantia(PDO $db, array $s): void
{
    verificar_modificable($db, $s);
    $id = (int)($_POST['id_garantia'] ?? 0);
    $g  = garantia_de_solicitud($db, $id, (int)$s['id_solicitud']);

    $db->beginTransaction();
    $st = $db->prepare('SELECT ruta_archivo FROM documento_garantia WHERE id_garantia = ?');
    $st->execute([$id]);
    $rutas = $st->fetchAll(PDO::FETCH_COLUMN);
    $db->prepare('DELETE FROM documento_garantia WHERE id_garantia = ?')->execute([$id]);
    $db->prepare('DELETE FROM garantia WHERE id_garantia = ?')->execute([$id]);
    auditar($db, 'ELIMINAR', 'garantia', $id, "Garantía {$g['tipo_garantia']} eliminada de la solicitud #{$s['id_solicitud']}");
    $db->commit();

    foreach ($rutas as $r) borrar_archivo_garantia($r);
    flash('success', 'Garantía eliminada.');
}

// --------------------------------------------------------------- documentos
function mensaje_subida(int $codigo): string
{
    return match ($codigo) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido.',
        UPLOAD_ERR_NO_FILE                        => 'Selecciona un archivo.',
        default                                   => 'No se pudo subir el archivo.',
    };
}

function accion_subir_documento(PDO $db, array $s): void
{
    verificar_modificable($db, $s);
    $idG = (int)($_POST['id_garantia'] ?? 0);
    garantia_de_solicitud($db, $idG, (int)$s['id_solicitud']);
    $tipoDoc = (string)($_POST['tipo_documento'] ?? '');
    if (!in_array($tipoDoc, TIPOS_DOC_GARANTIA, true)) throw new ReglaNegocio('Tipo de documento no válido.');

    $f = $_FILES['archivo'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) throw new ReglaNegocio(mensaje_subida((int)($f['error'] ?? UPLOAD_ERR_NO_FILE)));
    if ($f['size'] > MAX_ARCHIVO) throw new ReglaNegocio('El archivo supera los 5 MB.');

    $permitidos = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png']];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!isset($permitidos[$ext])) throw new ReglaNegocio('Solo se permiten archivos PDF, JPG o PNG.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, $permitidos[$ext], true)) throw new ReglaNegocio('El contenido del archivo no coincide con su extensión.');

    $dir = carpeta_garantias();
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new ReglaNegocio('No se pudo crear la carpeta de documentos.');
    $guardado = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $guardado)) throw new ReglaNegocio('No se pudo guardar el archivo en el servidor.');

    try {
        $original = mb_substr(basename(str_replace('\\', '/', $f['name'])), 0, 255);
        $db->prepare("INSERT INTO documento_garantia (id_garantia, tipo_documento, nombre_archivo, ruta_archivo, estado) VALUES (?,?,?,?, 'Pendiente')")
           ->execute([$idG, $tipoDoc, $original, 'documentos/garantias/' . $guardado]);
        auditar($db, 'CREAR', 'documento_garantia', (int)$db->lastInsertId(), "Documento '$tipoDoc' cargado en la garantía #$idG");
    } catch (Throwable $ex) {
        borrar_archivo_garantia('documentos/garantias/' . $guardado);
        throw $ex;
    }
    flash('success', 'Documento cargado.');
}

function accion_eliminar_documento(PDO $db, array $s): void
{
    verificar_modificable($db, $s);
    $st = $db->prepare('SELECT d.id_documento_garantia, d.ruta_archivo FROM documento_garantia d
                        JOIN garantia g ON g.id_garantia = d.id_garantia WHERE d.id_documento_garantia = ? AND g.id_solicitud = ?');
    $st->execute([(int)($_POST['id_documento'] ?? 0), $s['id_solicitud']]);
    $d = $st->fetch() ?: throw new ReglaNegocio('El documento no existe.');

    $db->prepare('DELETE FROM documento_garantia WHERE id_documento_garantia = ?')->execute([$d['id_documento_garantia']]);
    auditar($db, 'ELIMINAR', 'documento_garantia', (int)$d['id_documento_garantia'], 'Documento de garantía eliminado');
    borrar_archivo_garantia($d['ruta_archivo']);
    flash('success', 'Documento eliminado.');
}

// ----------------------------------------------------------------- contrato
function accion_generar_contrato(PDO $db, array $s): void
{
    $db->beginTransaction();
    $c = obtener_credito($db, (int)$s['id_solicitud'], true) ?: throw new ReglaNegocio('Primero debes crear el crédito.');
    if ($c['estado_credito'] !== ESTADO_CREDITO_APROBADO) throw new ReglaNegocio('El crédito ya fue desembolsado.');
    $ct = obtener_contrato($db, (int)$c['id_credito'], true);
    if ($ct && $ct['estado'] !== 'Generado') throw new ReglaNegocio('El contrato ya fue firmado y no puede regenerarse.');

    $errores = errores_garantia($db, (int)$s['id_solicitud'], $s['tipo_nombre'], (float)$c['monto_aprobado'], false);
    if ($errores) throw new ReglaNegocio(implode(' ', $errores));

    $st = $db->prepare('SELECT tipo_garantia, descripcion, valor_estimado FROM garantia WHERE id_solicitud = ? ORDER BY id_garantia');
    $st->execute([$s['id_solicitud']]);
    $texto = texto_contrato([
        'tipo' => $s['tipo_nombre'], 'numero_credito' => $c['numero_credito'], 'destino' => $s['destino'],
        'monto_aprobado' => $c['monto_aprobado'], 'plazo_meses' => $c['plazo_meses'], 'tasa_interes' => $c['tasa_interes'],
    ], $st->fetchAll());

    if (!$ct) {
        $numero = siguiente_numero($db, 'contrato', 'numero_contrato', 'CT');
        $db->prepare("INSERT INTO contrato (id_credito, numero_contrato, condiciones, estado) VALUES (?,?,?, 'Generado')")
           ->execute([$c['id_credito'], $numero, $texto]);
        auditar($db, 'GENERAR', 'contrato', (int)$db->lastInsertId(), "Contrato $numero generado para el crédito {$c['numero_credito']}");
        flash('success', "Contrato $numero generado.");
    } else {
        $db->prepare('UPDATE contrato SET condiciones = ?, fecha_generacion = NOW() WHERE id_contrato = ?')->execute([$texto, $ct['id_contrato']]);
        auditar($db, 'REGENERAR', 'contrato', (int)$ct['id_contrato'], "Contrato {$ct['numero_contrato']} regenerado");
        flash('success', 'Contrato regenerado con los datos actuales.');
    }
    $db->commit();
}

function accion_registrar_firma(PDO $db, array $s): void
{
    $fecha = validar_fecha((string)($_POST['fecha_firma'] ?? ''), 'fecha de firma');
    $db->beginTransaction();
    $c  = obtener_credito($db, (int)$s['id_solicitud'], true) ?: throw new ReglaNegocio('El crédito no existe.');
    $ct = obtener_contrato($db, (int)$c['id_credito'], true);
    if (!$ct || $ct['estado'] !== 'Generado') throw new ReglaNegocio('No hay un contrato pendiente de firma.');
    if ($fecha < substr((string)$ct['fecha_generacion'], 0, 10)) throw new ReglaNegocio('La firma no puede ser anterior a la generación del contrato.');

    $db->prepare("UPDATE contrato SET fecha_firma = ?, estado = 'Firmado' WHERE id_contrato = ?")->execute([$fecha, $ct['id_contrato']]);
    auditar($db, 'FIRMAR', 'contrato', (int)$ct['id_contrato'], "Contrato {$ct['numero_contrato']} firmado el $fecha");
    $db->commit();
    flash('success', 'Firma registrada. El crédito ya puede desembolsarse.');
}

// -------------------------------------------------------------- desembolso
function accion_desembolsar(PDO $db, array $s): void
{
    $fecha = validar_fecha((string)($_POST['fecha_desembolso'] ?? ''), 'fecha de desembolso');
    $db->beginTransaction();
    $c  = obtener_credito($db, (int)$s['id_solicitud'], true) ?: throw new ReglaNegocio('El crédito no existe.');
    if ($c['estado_credito'] !== ESTADO_CREDITO_APROBADO) throw new ReglaNegocio('El crédito ya fue desembolsado.');
    $ct = obtener_contrato($db, (int)$c['id_credito'], true);
    if (!$ct || $ct['estado'] !== 'Firmado') throw new ReglaNegocio('Para desembolsar, el contrato debe estar firmado.');
    if ($fecha < (string)$ct['fecha_firma']) throw new ReglaNegocio('El desembolso no puede ser anterior a la firma del contrato.');

    $errores = errores_garantia($db, (int)$s['id_solicitud'], $s['tipo_nombre'], (float)$c['monto_aprobado'], true);
    if ($errores) throw new ReglaNegocio(implode(' ', $errores));

    $db->prepare('UPDATE credito SET fecha_desembolso = ?, saldo_actual = monto_aprobado, estado_credito = ? WHERE id_credito = ?')
       ->execute([$fecha, ESTADO_CREDITO_VIGENTE, $c['id_credito']]);
    $db->prepare("UPDATE contrato SET estado = 'Vigente' WHERE id_contrato = ?")->execute([$ct['id_contrato']]);
    $db->prepare("UPDATE garantia SET estado = 'Formalizada' WHERE id_solicitud = ? AND estado = 'Registrada'")->execute([$s['id_solicitud']]);

    // TODO integrante 4: aquí debe generarse el cronograma de pagos (cronograma + cuotas) del crédito.

    auditar($db, 'DESEMBOLSAR', 'credito', (int)$c['id_credito'], "Crédito {$c['numero_credito']} desembolsado por " . bs($c['monto_aprobado']) . " el $fecha");
    $db->commit();
    flash('success', 'Crédito desembolsado. Ahora está vigente.');
}

// ------------------------------------------------------------------ router
$db = bd();
$idSolicitud = (int)($_POST['id_solicitud'] ?? 0);
$ancla = '';

try {
    csrf_validar();
    if (!puede_editar()) throw new ReglaNegocio('Tu rol no tiene permiso para realizar esta acción.');
    $s = cargar_solicitud($db, $idSolicitud) ?: throw new ReglaNegocio('La solicitud no existe.');

    switch ((string)($_POST['accion'] ?? '')) {
        case 'crear_credito':       accion_crear_credito($db, $s);       $ancla = 'datos-credito';   break;
        case 'guardar_garantia':    accion_guardar_garantia($db, $s);    $ancla = 'garantias';       break;
        case 'eliminar_garantia':   accion_eliminar_garantia($db, $s);   $ancla = 'garantias';       break;
        case 'subir_documento':     accion_subir_documento($db, $s);     $ancla = 'documentos';      break;
        case 'eliminar_documento':  accion_eliminar_documento($db, $s);  $ancla = 'documentos';      break;
        case 'generar_contrato':    accion_generar_contrato($db, $s);    $ancla = 'contrato';        break;
        case 'registrar_firma':     accion_registrar_firma($db, $s);     $ancla = 'contrato';        break;
        case 'desembolsar':         accion_desembolsar($db, $s);         $ancla = 'estado-credito';  break;
        default: throw new ReglaNegocio('Acción no reconocida.');
    }
} catch (ReglaNegocio $ex) {
    if ($db->inTransaction()) $db->rollBack();
    flash('danger', $ex->getMessage());
} catch (Throwable $ex) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[creditos] ' . $ex->getMessage());
    flash('danger', 'Ocurrió un error inesperado. Inténtalo de nuevo.');
}

volver($idSolicitud, $ancla);