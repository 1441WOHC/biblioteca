<?php
// config/database.php

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $configPath = dirname(dirname(dirname(__DIR__))) . '/config_segura/config_db.php';

        if (!file_exists($configPath)) {
            // ✅ CAMBIO: No usar die() con mensaje
            error_log("CRÍTICO: No se encuentra config_db.php en: " . $configPath);
            throw new Exception("Error de configuración");
        }

        $config = require($configPath);
        
        $this->host = $config['host'];
        $this->db_name = $config['db_name'];
        $this->username = $config['username'];
        $this->password = $config['password'];
    }

    public function getConnection() {
        $this->conn = null;
        $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name;
        
        try {
            $this->conn = new PDO($dsn, $this->username, $this->password);
            
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_TIMEOUT, 5);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        } catch(PDOException $exception) {
            // ✅ CAMBIO: Solo registrar, NO imprimir nada
            error_log("Error de conexión a BD: " . $exception->getMessage());
            
            // ✅ CAMBIO: Lanzar excepción en lugar de echo + exit
            throw new Exception("Error de conexión a la base de datos");
        }
        
        return $this->conn;
    }
}

date_default_timezone_set('America/Panama');