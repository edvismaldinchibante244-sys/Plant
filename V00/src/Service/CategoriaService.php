<?php

/*

   SERVIÇO DE CATEGORIAS - SERVICE LAYER
 
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../Model/Categoria.php';

class CategoriaService
{
    private $db;
    private $categoria;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->categoria = new Categoria($this->db);
    }

    /**
     * Listar categorias
     */
    public function listar($restaurante_id)
    {
        $stmt = $this->categoria->listar($restaurante_id);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cadastrar categoria
     */
    public function cadastrar($dados)
    {
        try {
            $this->categoria->restaurante_id = $dados['restaurante_id'];
            $this->categoria->nome = $dados['nome'];
            $this->categoria->descricao = $dados['descricao'] ?? '';
            $this->categoria->ativo = $dados['ativo'] ?? 1;

            $id = $this->categoria->cadastrar();

            if ($id) {
                return array(
                    "success" => true,
                    "message" => "Categoria cadastrada com sucesso!",
                    "id" => $id,
                    "categoria" => array(
                        "id" => $id,
                        "nome" => $dados['nome']
                    )
                );
            }

            return array("success" => false, "message" => "Erro ao cadastrar categoria.");
        } catch (Exception $e) {
            return array("success" => false, "message" => "Erro: " . $e->getMessage());
        }
    }

    /**
     * Inativar categoria
     */
    public function deletar($id, $restaurante_id)
    {
        try {
            if ($this->categoria->deletar($id, $restaurante_id)) {
                return array("success" => true, "message" => "Categoria inativada com sucesso!");
            }
            return array("success" => false, "message" => "Erro ao inativar categoria.");
        } catch (Exception $e) {
            return array("success" => false, "message" => "Erro: " . $e->getMessage());
        }
    }
}
