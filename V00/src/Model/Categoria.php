<?php


 
  /*CLASSE DE CATEGORIAS - MODEL*/
  
 

class Categoria
{
    private $conn;
    private $table_name = "categorias";

    public $id;
    public $restaurante_id;
    public $nome;
    public $descricao;
    public $ativo;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Listar todas as categorias
     */
    public function listar($restaurante_id)
    {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE restaurante_id = :restaurante_id 
                  ORDER BY nome ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":restaurante_id", $restaurante_id);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Cadastrar nova categoria
     */
    public function cadastrar()
    {
        $query = "INSERT INTO " . $this->table_name . "
                  SET restaurante_id = :restaurante_id,
                      nome = :nome,
                      descricao = :descricao,
                      ativo = :ativo";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":restaurante_id", $this->restaurante_id);
        $stmt->bindParam(":nome", $this->nome);
        $stmt->bindParam(":descricao", $this->descricao);
        $stmt->bindParam(":ativo", $this->ativo);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    /**
     * Inativar categoria
     */
    public function deletar($id, $restaurante_id)
    {
        try {
            $query = "UPDATE " . $this->table_name . "
                      SET ativo = 0
                      WHERE id = :id AND restaurante_id = :restaurante_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":restaurante_id", $restaurante_id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
}
