<?php

/*
 
   CONTROLADOR DE PRODUTOS - CONTROLLER LAYER
 
*/

require_once __DIR__ . '/../Service/ProdutoService.php';

class ProdutoController
{
    private $produtoService;

    public function __construct()
    {
        $this->produtoService = new ProdutoService();
    }

    /**
     * Listar produtos
     */
    public function listar($restaurante_id)
    {
        return $this->produtoService->listar($restaurante_id);
    }

    /**
     * Buscar produto por ID
     */
    public function buscarPorId($id, $restaurante_id)
    {
        return $this->produtoService->buscarPorId($id, $restaurante_id);
    }

    /**
     * Cadastrar produto
     */
    public function cadastrar($dados)
    {
        // Processar imagem se existir
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            $dados['imagem'] = $this->produtoService->processarImagem($_FILES['imagem']);
        }

        return $this->produtoService->cadastrar($dados);
    }

    /**
     * Editar produto
     */
    public function editar($dados)
    {
        // Processar imagem se existir
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            $dados['imagem'] = $this->produtoService->processarImagem($_FILES['imagem']);
        }

        return $this->produtoService->editar($dados);
    }

    /**
     * Inativar produto
     */
    public function deletar($id, $restaurante_id)
    {
        return $this->produtoService->deletar($id, $restaurante_id);
    }

    /**
     * Atualizar estoque
     */
    public function atualizarEstoque($id, $restaurante_id, $quantidade, $tipo = 'ENTRADA')
    {
        return $this->produtoService->atualizarEstoque($id, $restaurante_id, $quantidade, $tipo);
    }

    /**
     * Estoque baixo
     */
    public function estoqueBaixo($restaurante_id)
    {
        return $this->produtoService->estoqueBaixo($restaurante_id);
    }
}
