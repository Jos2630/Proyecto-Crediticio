<?php
/**
 * Capa de Persistencia Aislada - Conexión Database
 * Módulo: Integrante 1 - Clientes y Datos Iniciales
 * Estudiante: Bryan Henry Alvarado Apodaca
 * 
 * Este archivo implementa el patrón Singleton con Fallback Resiliente.
 * Si config/conexion.php existe y es válido, reutiliza dicha conexión.
 * Si no existe o tiene fallos, crea su propia conexión aislada compatible con XAMPP y Docker.
 */

namespace Modulo1\Persistencia;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instancia = null;

    /**
     * Obtiene la conexión PDO activa con manejo seguro de excepciones.
     */
    public static function getConexion(): PDO {
        if (self::$instancia !== null) {
            return self::$instancia;
        }

        // 1. Intentar cargar la conexión central si está disponible
        $rutaConexionGlobal = dirname(__DIR__, 2) . '/config/conexion.php';
        if (file_exists($rutaConexionGlobal)) {
            try {
                require_once $rutaConexionGlobal;
                if (isset($conexion) && $conexion instanceof PDO) {
                    self::$instancia = $conexion;
                    return self::$instancia;
                }
                if (function_exists('obtenerConexion')) {
                    $conn = obtenerConexion();
                    if ($conn instanceof PDO) {
                        self::$instancia = $conn;
                        return self::$instancia;
                    }
                }
            } catch (\Throwable $t) {
                error_log("Aviso: Fallback activado en Database.php: " . $t->getMessage());
            }
        }

        // 2. Conexión aislada de reserva compatible con XAMPP y Docker
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'sistema_creditos';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $opciones = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        // Intento A: XAMPP estándar (root / "")
        try {
            self::$instancia = new PDO($dsn, 'root', '', $opciones);
            return self::$instancia;
        } catch (PDOException $eXampp) {
            // Intento B: Docker user_creditos
            try {
                self::$instancia = new PDO($dsn, 'user_creditos', 'password_creditos', $opciones);
                return self::$instancia;
            } catch (PDOException $eDocker) {
                // Intento C: Docker root
                try {
                    self::$instancia = new PDO($dsn, 'root', 'root', $opciones);
                    return self::$instancia;
                } catch (PDOException $eFinal) {
                    error_log("Error crítico de base de datos en Módulo 1: " . $eFinal->getMessage());
                    throw new PDOException("No se pudo conectar a la base de datos de créditos. Verifique que MySQL esté iniciado en XAMPP o Docker.");
                }
            }
        }
    }
}
