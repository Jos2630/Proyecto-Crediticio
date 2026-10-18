<?php
/**
 * Capa de Persistencia - Cliente y Datos Económicos (DAO)
 * Módulo: Integrante 1 - Clientes y Datos Iniciales
 * Estudiante: Bryan Henry Alvarado Apodaca
 * 
 * Implementa consultas preparadas con PDO para evitar SQL Injection.
 */

namespace Modulo1\Persistencia;

use PDO;
use Exception;

require_once __DIR__ . '/Database.php';

class ClienteDAO {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConexion();
    }

    /**
     * Lista clientes con filtros opcionales de búsqueda y estado.
     */
    public function listarClientes(string $buscar = '', string $estado = ''): array {
        $sql = "SELECT c.*, ie.ocupacion, ie.ingreso_mensual, ie.gastos_mensuales
                FROM cliente c
                LEFT JOIN informacion_economica ie ON c.id_cliente = ie.id_cliente
                WHERE 1=1";
        $params = [];

        if (!empty($buscar)) {
            $sql .= " AND (c.ci LIKE ? OR c.nombres LIKE ? OR c.apellidos LIKE ? OR c.telefono LIKE ?)";
            $termino = "%{$buscar}%";
            $params[] = $termino;
            $params[] = $termino;
            $params[] = $termino;
            $params[] = $termino;
        }

        if (!empty($estado)) {
            $sql .= " AND c.estado = ?";
            $params[] = $estado;
        }

        $sql .= " ORDER BY c.id_cliente DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene los datos completos de un cliente y su información económica asociada.
     */
    public function obtenerClienteConEconomia(int $id_cliente): ?array {
        $sql = "SELECT c.*, 
                       ie.id_info_economica, ie.ocupacion, ie.empleador, 
                       ie.ingreso_mensual, ie.otros_ingresos, ie.gastos_mensuales, 
                       ie.fecha_actualizacion as fecha_act_economica
                FROM cliente c
                LEFT JOIN informacion_economica ie ON c.id_cliente = ie.id_cliente
                WHERE c.id_cliente = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_cliente]);
        $resultado = $stmt->fetch();
        return $resultado ?: null;
    }

    /**
     * Verifica si una cédula de identidad ya se encuentra registrada.
     */
    public function existeCi(string $ci, ?int $excluirId = null): bool {
        $sql = "SELECT count(*) FROM cliente WHERE ci = ?";
        $params = [trim($ci)];
        if ($excluirId !== null) {
            $sql .= " AND id_cliente != ?";
            $params[] = $excluirId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Registra un nuevo cliente y su información económica dentro de una transacción atómica.
     */
    public function registrarCliente(array $cli, array $eco): int {
        $this->db->beginTransaction();
        try {
            $sqlCli = "INSERT INTO cliente (ci, nombres, apellidos, fecha_nacimiento, telefono, correo, direccion, estado)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtCli = $this->db->prepare($sqlCli);
            $stmtCli->execute([
                trim($cli['ci']),
                trim($cli['nombres']),
                trim($cli['apellidos']),
                !empty($cli['fecha_nacimiento']) ? $cli['fecha_nacimiento'] : null,
                !empty($cli['telefono']) ? trim($cli['telefono']) : null,
                !empty($cli['correo']) ? trim($cli['correo']) : null,
                !empty($cli['direccion']) ? trim($cli['direccion']) : null,
                $cli['estado'] ?? 'Activo'
            ]);

            $id_cliente = (int)$this->db->lastInsertId();

            $sqlEco = "INSERT INTO informacion_economica (id_cliente, ocupacion, empleador, ingreso_mensual, otros_ingresos, gastos_mensuales)
                       VALUES (?, ?, ?, ?, ?, ?)";
            $stmtEco = $this->db->prepare($sqlEco);
            $stmtEco->execute([
                $id_cliente,
                trim($eco['ocupacion']),
                !empty($eco['empleador']) ? trim($eco['empleador']) : null,
                (float)($eco['ingreso_mensual'] ?? 0),
                (float)($eco['otros_ingresos'] ?? 0),
                (float)($eco['gastos_mensuales'] ?? 0)
            ]);

            $this->db->commit();
            return $id_cliente;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            if ($e->getCode() === '23000') {
                throw new Exception('El CI ya se encuentra registrado para otro cliente.');
            }
            error_log("Error al registrar cliente: " . $e->getMessage());
            throw $e;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error al registrar cliente: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualiza datos de un cliente y su información económica.
     */
    public function actualizarCliente(int $id_cliente, array $cli, array $eco): bool {
        $this->db->beginTransaction();
        try {
            $sqlCli = "UPDATE cliente 
                       SET ci = ?, nombres = ?, apellidos = ?, fecha_nacimiento = ?, 
                           telefono = ?, correo = ?, direccion = ?, estado = ?
                       WHERE id_cliente = ?";
            $stmtCli = $this->db->prepare($sqlCli);
            $stmtCli->execute([
                trim($cli['ci']),
                trim($cli['nombres']),
                trim($cli['apellidos']),
                !empty($cli['fecha_nacimiento']) ? $cli['fecha_nacimiento'] : null,
                !empty($cli['telefono']) ? trim($cli['telefono']) : null,
                !empty($cli['correo']) ? trim($cli['correo']) : null,
                !empty($cli['direccion']) ? trim($cli['direccion']) : null,
                $cli['estado'] ?? 'Activo',
                $id_cliente
            ]);

            // Verificar si ya tiene registro económico o requiere insert
            $stmtCheck = $this->db->prepare("SELECT id_info_economica FROM informacion_economica WHERE id_cliente = ?");
            $stmtCheck->execute([$id_cliente]);
            $existeEco = $stmtCheck->fetchColumn();

            if ($existeEco) {
                $sqlEco = "UPDATE informacion_economica
                           SET ocupacion = ?, empleador = ?, ingreso_mensual = ?, otros_ingresos = ?, gastos_mensuales = ?, fecha_actualizacion = CURRENT_TIMESTAMP
                           WHERE id_cliente = ?";
                $stmtEco = $this->db->prepare($sqlEco);
                $stmtEco->execute([
                    trim($eco['ocupacion']),
                    !empty($eco['empleador']) ? trim($eco['empleador']) : null,
                    (float)($eco['ingreso_mensual'] ?? 0),
                    (float)($eco['otros_ingresos'] ?? 0),
                    (float)($eco['gastos_mensuales'] ?? 0),
                    $id_cliente
                ]);
            } else {
                $sqlEco = "INSERT INTO informacion_economica (id_cliente, ocupacion, empleador, ingreso_mensual, otros_ingresos, gastos_mensuales)
                           VALUES (?, ?, ?, ?, ?, ?)";
                $stmtEco = $this->db->prepare($sqlEco);
                $stmtEco->execute([
                    $id_cliente,
                    trim($eco['ocupacion']),
                    !empty($eco['empleador']) ? trim($eco['empleador']) : null,
                    (float)($eco['ingreso_mensual'] ?? 0),
                    (float)($eco['otros_ingresos'] ?? 0),
                    (float)($eco['gastos_mensuales'] ?? 0)
                ]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error al actualizar cliente: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Alterna o cambia el estado del cliente (Activo / Inactivo).
     */
    public function cambiarEstado(int $id_cliente, string $nuevoEstado): bool {
        $stmt = $this->db->prepare("UPDATE cliente SET estado = ? WHERE id_cliente = ?");
        return $stmt->execute([$nuevoEstado, $id_cliente]);
    }

    /**
     * Obtiene métricas estadísticas de clientes para el panel.
     */
    public function contarMetricas(): array {
        $total = $this->db->query("SELECT count(*) FROM cliente")->fetchColumn() ?: 0;
        $activos = $this->db->query("SELECT count(*) FROM cliente WHERE estado = 'Activo'")->fetchColumn() ?: 0;
        $inactivos = $this->db->query("SELECT count(*) FROM cliente WHERE estado = 'Inactivo'")->fetchColumn() ?: 0;
        return [
            'total' => (int)$total,
            'activos' => (int)$activos,
            'inactivos' => (int)$inactivos
        ];
    }
}
