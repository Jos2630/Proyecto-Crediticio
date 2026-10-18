<?php
/**
 * Capa de Persistencia - Historial Crediticio (DAO)
 * Módulo: Integrante 1 - Clientes y Datos Iniciales
 * Estudiante: Bryan Henry Alvarado Apodaca
 * 
 * Gestiona consultas de Buró de Crédito (Infocred, ASFI) asociadas a clientes y solicitudes.
 */

namespace Modulo1\Persistencia;

use PDO;
use Exception;

require_once __DIR__ . '/Database.php';

class HistorialDAO {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConexion();
    }

    /**
     * Lista todas las consultas de historial crediticio con datos del cliente y solicitud.
     */
    public function listarTodo(): array {
        $sql = "SELECT h.*, 
                       s.monto_solicitado, s.destino, s.fecha_solicitud,
                       c.id_cliente, c.ci, c.nombres, c.apellidos
                FROM historial_crediticio h
                JOIN solicitud_credito s ON h.id_solicitud = s.id_solicitud
                JOIN cliente c ON s.id_cliente = c.id_cliente
                ORDER BY h.fecha_consulta DESC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Obtiene el historial de consultas de un cliente en específico.
     */
    public function obtenerPorCliente(int $id_cliente): array {
        $sql = "SELECT h.*, s.monto_solicitado, s.fecha_solicitud
                FROM historial_crediticio h
                JOIN solicitud_credito s ON h.id_solicitud = s.id_solicitud
                WHERE s.id_cliente = ?
                ORDER BY h.fecha_consulta DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_cliente]);
        return $stmt->fetchAll();
    }

    /**
     * Registra una nueva consulta de buró crediticio.
     */
    public function registrar(int $id_solicitud, string $entidad, int $puntaje, ?string $obligaciones, ?string $observaciones): int {
        $sql = "INSERT INTO historial_crediticio (id_solicitud, entidad_consultada, puntaje, obligaciones_vigentes, observaciones)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $id_solicitud,
            trim($entidad),
            $puntaje,
            $obligaciones ? trim($obligaciones) : null,
            $observaciones ? trim($observaciones) : null
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Cuenta el total de consultas de buró registradas.
     */
    public function contar(): int {
        return (int)$this->db->query("SELECT count(*) FROM historial_crediticio")->fetchColumn();
    }
}
