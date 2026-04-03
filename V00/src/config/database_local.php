<?php

/*
   Local Database Configuration (XAMPP/WAMP)
   Versão melhorada e mais segura
*/

class Database
{
    private $host = 'dpg-d77angeslomc73dcoar0-a'; // melhor que localhost
    private $db_name = 'dpg-d77angeslomc73dcoar0-a/saas_p75y';
    private $username = 'saas_p75y_user';
    private $password = 'G7J4zekw5FNIMI4GGPVfpcXeoEryAYYt';

    private $conn;

    public function getConnection()
    {
        if ($this->conn) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // mostra erros
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // retorna array associativo
                PDO::ATTR_EMULATE_PREPARES => false, // segurança
                PDO::ATTR_PERSISTENT => true // conexão persistente (melhor performance)
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);

            // Teste simples de conexão
            $this->conn->query("SELECT 1");

            return $this->conn;

        } catch (PDOException $e) {

            // Log do erro (melhor prática)
            error_log("Erro DB: " . $e->getMessage());

            // Resposta amigável
            header('Content-Type: application/json');

            echo json_encode([
                'success' => false,
                'message' => 'Erro ao conectar ao banco de dados',
                'error' => $e->getMessage() // remove isso em produção
            ]);

            exit;
        }
    }
}
