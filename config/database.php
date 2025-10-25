<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'pl';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username, 
                $this->password
            );
            
            // Configuración de caracteres UTF-8
            $this->conn->exec("set names utf8");
            
            // Mostrar errores detallados
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // ✅ TIMEOUT: Evita esperas indefinidas en bloqueos de transacciones
            // Si una consulta tarda más de 5 segundos, lanza una excepción
            $this->conn->setAttribute(PDO::ATTR_TIMEOUT, 5);
            
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Configuraciones generales del sistema
date_default_timezone_set('America/Panama');
?>
