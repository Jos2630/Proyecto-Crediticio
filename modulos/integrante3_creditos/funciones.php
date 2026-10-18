<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$verificar = dirname(__DIR__, 2) . '/includes/verificar_sesion.php';
if (is_file($verificar)) require_once $verificar;

// ---- Valores que debes coordinar con tus compañeros ----
const ESTADO_SOLICITUD_APROBADA = 'Aprobado';
const ESTADO_CREDITO_APROBADO   = 'Aprobado';   // crédito creado, aún sin desembolsar
const ESTADO_CREDITO_VIGENTE    = 'Vigente';    // crédito desembolsado
const LTV_VIVIENDA   = 0.80;                    // préstamo máx. = 80 % del valor del inmueble
const MAX_ARCHIVO    = 5 * 1024 * 1024;         // 5 MB
const ROLES_EDITORES = [1, 2, 4];               // Administrador, Oficial, Jefe
const TIPOS_GARANTIA     = ['Inmueble', 'Vehículo', 'Personal', 'Otro'];
const TIPOS_DOC_GARANTIA = ['Título de propiedad / Folio real', 'Avalúo', 'Factura / Título del vehículo', 'SOAT', 'Contrato de garantía', 'Otro'];

class ReglaNegocio extends Exception {}

function bd(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=sistema_creditos;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function escalar(PDO $db, string $sql, array $p = [])
{
    $st = $db->prepare($sql);
    $st->execute($p);
    return $st->fetchColumn();
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function bs($v): string { return 'Bs ' . number_format((float)$v, 2, ',', '.'); }
function fecha($v): string { $t = $v ? strtotime((string)$v) : false; return $t ? date('d/m/Y', $t) : '—'; }
function usuario_id(): int { return (int)($_SESSION['id_usuario'] ?? 0); }

function puede_editar(): bool
{
    if (!isset($_SESSION['id_rol'])) return true; // ajusta si tu login guarda el rol con otro nombre
    return in_array((int)$_SESSION['id_rol'], ROLES_EDITORES, true);
}

// ---- CSRF y mensajes ----
function csrf_token(): string
{
    if (empty($_SESSION['csrf_creditos'])) $_SESSION['csrf_creditos'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_creditos'];
}
function csrf_campo(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_validar(): void
{
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        throw new ReglaNegocio('El formulario expiró. Recarga la página e inténtalo de nuevo.');
    }
}
function flash(string $tipo, string $msg): void { $_SESSION['flash_creditos'][] = ['tipo' => $tipo, 'msg' => $msg]; }
function flash_html(): string
{
    $h = '';
    foreach ($_SESSION['flash_creditos'] ?? [] as $f) {
        $h .= '<div class="alert alert-' . e($f['tipo']) . ' alert-dismissible fade show">' . e($f['msg'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    unset($_SESSION['flash_creditos']);
    return $h;
}

function auditar(PDO $db, string $accion, string $entidad, ?int $id, string $detalle, string $resultado = 'Exitoso'): void
{
    $uid = usuario_id();
    $db->prepare('INSERT INTO bitacora_auditoria (id_usuario, accion, entidad, id_registro, detalle, resultado) VALUES (?,?,?,?,?,?)')
       ->execute([$uid > 0 ? $uid : null, $accion, $entidad, $id, $detalle, $resultado]);
}

function clase_estado(string $estado): string
{
    return match ($estado) {
        'Aprobada', 'Vigente', 'Firmado', 'Formalizada', 'Validado', 'Activo' => 'text-bg-success',
        'Aprobado'                                                            => 'text-bg-primary',
        'Pendiente', 'Generado', 'Registrada'                                 => 'text-bg-warning',
        'En mora', 'Rechazada', 'Anulado'                                     => 'text-bg-danger',
        default                                                               => 'text-bg-secondary',
    };
}

/** Número correlativo por año, p. ej. CR-2026-001. Usar dentro de una transacción. */
function siguiente_numero(PDO $db, string $tabla, string $col, string $prefijo): string
{
    $patron = $prefijo . '-' . date('Y') . '-';
    $n = (int)escalar($db, "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX($col,'-',-1) AS UNSIGNED)),0)+1 FROM $tabla WHERE $col LIKE ? FOR UPDATE", [$patron . '%']);
    return $patron . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
}

function validar_fecha(string $f, string $etiqueta): string
{
    $d = DateTime::createFromFormat('!Y-m-d', $f);
    if (!$d || $d->format('Y-m-d') !== $f) throw new ReglaNegocio("La $etiqueta no es válida.");
    if ($d > new DateTime('today')) throw new ReglaNegocio("La $etiqueta no puede ser futura.");
    return $f;
}

// ---- Consultas comunes ----
function cargar_solicitud(PDO $db, int $id): ?array
{
    $st = $db->prepare('SELECT s.*, cl.ci, cl.nombres, cl.apellidos, tc.nombre AS tipo_nombre, tc.tasa_interes AS tasa_tipo,
                               tc.plazo_minimo, tc.plazo_maximo, tc.monto_minimo, tc.monto_maximo
                        FROM solicitud_credito s
                        JOIN cliente cl ON cl.id_cliente = s.id_cliente
                        JOIN tipo_credito tc ON tc.id_tipo_credito = s.id_tipo_credito
                        WHERE s.id_solicitud = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
function obtener_credito(PDO $db, int $idSolicitud, bool $bloquear = false): ?array
{
    $st = $db->prepare('SELECT * FROM credito WHERE id_solicitud = ?' . ($bloquear ? ' FOR UPDATE' : ''));
    $st->execute([$idSolicitud]);
    return $st->fetch() ?: null;
}
function obtener_contrato(PDO $db, int $idCredito, bool $bloquear = false): ?array
{
    $st = $db->prepare('SELECT * FROM contrato WHERE id_credito = ?' . ($bloquear ? ' FOR UPDATE' : ''));
    $st->execute([$idCredito]);
    return $st->fetch() ?: null;
}

// ---- Archivos de garantía ----
function carpeta_garantias(): string { return dirname(__DIR__, 2) . '/documentos/garantias'; }
function borrar_archivo_garantia(string $ruta): void
{
    $base = realpath(carpeta_garantias());
    $arch = realpath(dirname(__DIR__, 2) . '/' . $ruta);
    if ($base && $arch && strpos($arch, $base . DIRECTORY_SEPARATOR) === 0 && is_file($arch)) @unlink($arch);
}

/** Reglas de garantía: vivienda exige inmueble y cobertura; al desembolsar, documentos de respaldo. */
function errores_garantia(PDO $db, int $idSolicitud, string $tipoCredito, float $monto, bool $exigirDocs): array
{
    $errores = [];
    $st = $db->prepare('SELECT g.id_garantia, g.tipo_garantia, g.valor_estimado,
                               (SELECT COUNT(*) FROM documento_garantia d WHERE d.id_garantia = g.id_garantia) AS docs
                        FROM garantia g WHERE g.id_solicitud = ?');
    $st->execute([$idSolicitud]);
    $gs = $st->fetchAll();

    if (stripos($tipoCredito, 'vivienda') !== false) {
        $hay = false; $valor = 0.0;
        foreach ($gs as $g) {
            if ($g['tipo_garantia'] === 'Inmueble') { $hay = true; $valor += (float)$g['valor_estimado']; }
        }
        if (!$hay) {
            $errores[] = 'El crédito de vivienda exige una garantía hipotecaria (tipo Inmueble).';
        } elseif ($monto > $valor * LTV_VIVIENDA) {
            $errores[] = sprintf('El monto aprobado (%s) supera el %d%% del valor del inmueble (%s).', bs($monto), (int)round(LTV_VIVIENDA * 100), bs($valor));
        }
    }
    if ($exigirDocs) {
        foreach ($gs as $g) {
            if (in_array($g['tipo_garantia'], ['Inmueble', 'Vehículo'], true) && (int)$g['docs'] === 0) {
                $errores[] = 'La garantía #' . $g['id_garantia'] . ' (' . $g['tipo_garantia'] . ') no tiene documentos de respaldo.';
            }
        }
    }
    return $errores;
}

function texto_contrato(array $d, array $garantias): string
{
    $lista = $garantias
        ? implode("\n", array_map(fn($x) => '- ' . $x['tipo_garantia'] . ': ' . $x['descripcion']
            . ($x['valor_estimado'] !== null ? ' (valor estimado ' . bs($x['valor_estimado']) . ')' : ''), $garantias))
        : 'Este crédito no cuenta con garantías registradas.';

    return implode("\n\n", [
        "PRIMERA (Objeto).- El BANCO otorga al CLIENTE un crédito de tipo {$d['tipo']}, identificado con el N° {$d['numero_credito']}, destinado a: " . ($d['destino'] ?: 'el fin declarado en la solicitud') . '.',
        'SEGUNDA (Monto y plazo).- El monto del crédito es ' . bs($d['monto_aprobado']) . " y el plazo es de {$d['plazo_meses']} meses.",
        'TERCERA (Interés).- El crédito devenga una tasa de interés del ' . number_format((float)$d['tasa_interes'], 2, ',', '.') . '% anual.',
        'CUARTA (Pago).- El CLIENTE pagará el crédito en cuotas periódicas según el cronograma de pagos generado al desembolso, que forma parte de este contrato.',
        'QUINTA (Mora).- El incumplimiento en el pago de una cuota genera interés moratorio por cada día de atraso, según las condiciones vigentes del BANCO.',
        "SEXTA (Garantías).- El crédito se respalda con:\n" . $lista,
        'SÉPTIMA (Pago anticipado).- El CLIENTE puede realizar pagos anticipados parciales o cancelar totalmente el crédito en cualquier momento.',
        'OCTAVA (Conformidad).- Las partes declaran su conformidad con las condiciones de este contrato y lo firman en señal de aceptación.',
    ]);
}