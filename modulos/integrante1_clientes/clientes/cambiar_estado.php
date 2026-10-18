<?php
/**
 * Alternar Estado de Cliente (Activo / Inactivo)
 * Módulo: Integrante 1 (Bryan Alvarado)
 */

require_once __DIR__ . '/../persistencia/ClienteDAO.php';
use Modulo1\Persistencia\ClienteDAO;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_cliente = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = $_GET['token'] ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    die("Solicitud inválida: Token CSRF no coincide.");
}

$clienteDAO = new ClienteDAO();
$cliente = $clienteDAO->obtenerClienteConEconomia($id_cliente);

if ($cliente) {
    $nuevoEstado = ($cliente['estado'] === 'Activo') ? 'Inactivo' : 'Activo';
    $clienteDAO->cambiarEstado($id_cliente, $nuevoEstado);
    header("Location: index.php?msg=Estado+del+cliente+actualizado+a+{$nuevoEstado}");
} else {
    header("Location: index.php?msg=Cliente+no+encontrado");
}
exit;
