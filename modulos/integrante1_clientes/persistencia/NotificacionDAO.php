<?php
/**
 * Capa de Persistencia - Notificaciones (DAO)
 * Módulo: Integrante 1 - Clientes y Datos Iniciales
 * Estudiante: Bryan Henry Alvarado Apodaca
 * 
 * Gestiona avisos, alertas y estados de notificación hacia los clientes.
 */

namespace Modulo1\Persistencia;

use PDO;
use Exception;

require_once __DIR__ . '/Database.php';

class NotificacionDAO {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConexion();
    }

    /**
     * Lista notificaciones con información del cliente asociado.
     */
    public function listar(string $estado = '', int $limite = 50): array {
        $sql = "SELECT n.*, c.ci, c.nombres, c.apellidos, c.telefono, c.correo
                FROM notificacion n
                JOIN cliente c ON n.id_cliente = c.id_cliente";
        $params = [];

        if (!empty($estado)) {
            $sql .= " WHERE n.estado = ?";
            $params[] = $estado;
        }

        $sql .= " ORDER BY n.id_notificacion DESC LIMIT " . (int)$limite;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene todas las notificaciones emitidas para un cliente en específico.
     */
    public function obtenerPorCliente(int $id_cliente): array {
        $sql = "SELECT * FROM notificacion WHERE id_cliente = ? ORDER BY id_notificacion DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_cliente]);
        return $stmt->fetchAll();
    }

    /**
     * Registra una nueva notificación para un cliente.
     */
    public function crear(int $id_cliente, string $tipo, string $mensaje, ?int $id_solicitud = null, ?int $id_credito = null, string $estado = 'Pendiente'): int {
        $sql = "INSERT INTO notificacion (id_cliente, id_solicitud, id_credito, tipo_notificacion, mensaje, fecha_envio, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $fechaEnvio = ($estado === 'Enviado') ? date('Y-m-d H:i:s') : null;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $id_cliente,
            $id_solicitud,
            $id_credito,
            trim($tipo),
            trim($mensaje),
            $fechaEnvio,
            $estado
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Actualiza el estado de una notificación (ej. de 'Pendiente' a 'Enviado').
     */
    public function cambiarEstado(int $id_notificacion, string $nuevoEstado): bool {
        $sql = "UPDATE notificacion SET estado = ?";
        $params = [$nuevoEstado];
        if ($nuevoEstado === 'Enviado') {
            $sql .= ", fecha_envio = CURRENT_TIMESTAMP";
        }
        $sql .= " WHERE id_notificacion = ?";
        $params[] = $id_notificacion;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Cuenta cuántas notificaciones pendientes existen en el sistema.
     */
    public function contarPendientes(): int {
        return (int)$this->db->query("SELECT count(*) FROM notificacion WHERE estado = 'Pendiente'")->fetchColumn();
    }
}
