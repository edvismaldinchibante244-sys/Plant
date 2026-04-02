<?php

/*
   Local Database Configuration (XAMPP/WAMP)
   Use this file for local development
*/

class Database
{
    private $host = 'localhost';
    private $db_name = 'restaurante_saas';
    private $username = 'root';
    private $password = '';

    private $conn;

    public function getConnection()
    {
        if ($this->conn) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            return $this->conn;
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Erro de conexão: ' . $e->getMessage()]);
            exit;
        }
    }
}
