<?php
/**
 * Capa de Persistencia - Tipos de Crédito (DAO)
 * Módulo: Integrante 1 - Clientes y Datos Iniciales
 * Estudiante: Bryan Henry Alvarado Apodaca
 * 
 * Gestiona el catálogo de tipos de crédito que consumirá el Integrante 2 (Solicitudes).
 */

namespace Modulo1\Persistencia;

use PDO;
use Exception;

require_once __DIR__ . '/Database.php';

class TipoCreditoDAO {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConexion();
    }

    /**
     * Lista todos los tipos de crédito registrados.
     */
    public function listar(bool $soloActivos = false): array {
        $sql = "SELECT * FROM tipo_credito";
        if ($soloActivos) {
            $sql .= " WHERE estado = 'Activo'";
        }
        $sql .= " ORDER BY id_tipo_credito ASC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Obtiene un tipo de crédito por su ID.
     */
    public function obtenerPorId(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM tipo_credito WHERE id_tipo_credito = ?");
        $stmt->execute([$id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Registra un nuevo tipo de crédito.
     */
    public function registrar(array $datos): int {
        $sql = "INSERT INTO tipo_credito 
                (nombre, descripcion, tasa_interes, plazo_minimo, plazo_maximo, monto_minimo, monto_maximo, parametros, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        // Validar que parametros sea un JSON válido
        $parametros = $datos['parametros'] ?? '{}';
        if (is_array($parametros)) {
            $parametros = json_encode($parametros, JSON_UNESCAPED_UNICODE);
        } else {
            // Validar sintaxis json
            json_decode($parametros);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $parametros = '{}';
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            trim($datos['nombre']),
            !empty($datos['descripcion']) ? trim($datos['descripcion']) : null,
            (float)$datos['tasa_interes'],
            (int)$datos['plazo_minimo'],
            (int)$datos['plazo_maximo'],
            (float)$datos['monto_minimo'],
            (float)$datos['monto_maximo'],
            $parametros,
            $datos['estado'] ?? 'Activo'
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Actualiza un tipo de crédito existente.
     */
    public function actualizar(int $id, array $datos): bool {
        $sql = "UPDATE tipo_credito 
                SET nombre = ?, descripcion = ?, tasa_interes = ?, plazo_minimo = ?, 
                    plazo_maximo = ?, monto_minimo = ?, monto_maximo = ?, parametros = ?, estado = ?
                WHERE id_tipo_credito = ?";

        $parametros = $datos['parametros'] ?? '{}';
        if (is_array($parametros)) {
            $parametros = json_encode($parametros, JSON_UNESCAPED_UNICODE);
        } else {
            json_decode($parametros);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $parametros = '{}';
            }
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            trim($datos['nombre']),
            !empty($datos['descripcion']) ? trim($datos['descripcion']) : null,
            (float)$datos['tasa_interes'],
            (int)$datos['plazo_minimo'],
            (int)$datos['plazo_maximo'],
            (float)$datos['monto_minimo'],
            (float)$datos['monto_maximo'],
            $parametros,
            $datos['estado'] ?? 'Activo',
            $id
        ]);
    }

    /**
     * Alterna el estado entre Activo e Inactivo.
     */
    public function cambiarEstado(int $id, string $nuevoEstado): bool {
        $stmt = $this->db->prepare("UPDATE tipo_credito SET estado = ? WHERE id_tipo_credito = ?");
        return $stmt->execute([$nuevoEstado, $id]);
    }

    /**
     * Cuenta el total de tipos de crédito activos.
     */
    public function contar(): int {
        return (int)$this->db->query("SELECT count(*) FROM tipo_credito WHERE estado = 'Activo'")->fetchColumn();
    }
}
