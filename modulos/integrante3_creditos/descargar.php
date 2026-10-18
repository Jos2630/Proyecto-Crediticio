<?php
require_once __DIR__ . '/funciones.php';

$st = bd()->prepare('SELECT nombre_archivo, ruta_archivo FROM documento_garantia WHERE id_documento_garantia = ?');
$st->execute([(int)($_GET['id'] ?? 0)]);
$doc = $st->fetch();

$base    = realpath(carpeta_garantias());
$archivo = $doc ? realpath(RAIZ_PROYECTO . '/' . $doc['ruta_archivo']) : false;
if (!$base || !$archivo || strpos($archivo, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($archivo)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($archivo));
header('Content-Length: ' . filesize($archivo));
header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($doc['nombre_archivo']));
header('X-Content-Type-Options: nosniff');
readfile($archivo);