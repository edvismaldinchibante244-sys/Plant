<?php

/*
 
   SERVIÇO DE PRODUTOS - SERVICE LAYER
 
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/plano_check.php';
require_once __DIR__ . '/../Model/Produto.php';

class ProdutoService
{
    private $db;
    private $produto;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->produto = new Produto($this->db);
    }

    /**
     * Listar todos os produtos
     */
    public function listar($restaurante_id)
    {
        $stmt = $this->produto->listar($restaurante_id);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar produto por ID
     */
    public function buscarPorId($id, $restaurante_id)
    {
        return $this->produto->buscarPorId($id, $restaurante_id);
    }

    /**
     * Cadastrar produto
     */
    public function cadastrar($dados)
    {
        $restauranteId = (int)($dados['restaurante_id'] ?? 0);
        $planoRestauranteId = (int)($dados['plano_restaurante_id'] ?? $restauranteId);
        $temMultiFilial = $planoRestauranteId > 0 && plano_tem_funcionalidade_db($planoRestauranteId, 'multi_filial');

        if ($temMultiFilial) {
            $stmtCount = $this->db->prepare("
                SELECT COUNT(*)
                FROM produtos p
                INNER JOIN restaurantes r ON r.id = p.restaurante_id
                WHERE p.ativo = 1
                  AND (p.restaurante_id = :base_restaurante_id OR r.filial_id = :filial_base_id)
            ");
            $stmtCount->bindValue(':base_restaurante_id', $planoRestauranteId, PDO::PARAM_INT);
            $stmtCount->bindValue(':filial_base_id', $planoRestauranteId, PDO::PARAM_INT);
            $stmtCount->execute();
            $totalProdutosAtivos = (int)$stmtCount->fetchColumn();
        } else {
            $totalProdutosAtivos = (int)$this->produto->contarAtivos($restauranteId);
        }

        $verificacaoPlano = plano_verificar_limite_db($planoRestauranteId > 0 ? $planoRestauranteId : $restauranteId, 'produtos', $totalProdutosAtivos);

        if (!$verificacaoPlano['permitido']) {
            return array(
                "success" => false,
                "message" => "Limite do plano atingido. O plano {$verificacaoPlano['plano']} permite até {$verificacaoPlano['limite']} produtos."
            );
        }

        $categoriaIdValidada = $this->normalizarCategoriaId(
            $dados['categoria_id'] ?? null,
            $restauranteId
        );

        $this->produto->restaurante_id = $restauranteId;
        $this->produto->categoria_id = $categoriaIdValidada;
        $this->produto->nome = $dados['nome'];
        $this->produto->descricao = $dados['descricao'] ?? '';
        $this->produto->preco = $dados['preco'];
        $this->produto->custo = $dados['custo'] ?? 0;
        $this->produto->estoque = $dados['estoque'] ?? 0;
        $this->produto->estoque_minimo = $dados['estoque_minimo'] ?? 5;
        $this->produto->ativo = $dados['ativo'] ?? 1;
        // sanitize imagem path (remove redundant src/public prefix)
        $img = $dados['imagem'] ?? null;
        if ($img) {
            $img = str_replace('src/public/', '', $img);
        }
        $this->produto->imagem = $img;

        $id = $this->produto->cadastrar();

        if ($id) {
            return array("success" => true, "id" => $id, "message" => "Produto cadastrado com sucesso!");
        }

        return array("success" => false, "message" => "Erro ao cadastrar produto.");
    }

    /**
     * Editar produto
     */
    public function editar($dados)
    {
        $categoriaIdValidada = $this->normalizarCategoriaId(
            $dados['categoria_id'] ?? null,
            $dados['restaurante_id'] ?? 0
        );

        $this->produto->id = $dados['id'];
        $this->produto->restaurante_id = $dados['restaurante_id'];
        $this->produto->categoria_id = $categoriaIdValidada;
        $this->produto->nome = $dados['nome'];
        $this->produto->descricao = $dados['descricao'] ?? '';
        $this->produto->preco = $dados['preco'];
        $this->produto->custo = $dados['custo'] ?? 0;
        $this->produto->estoque = $dados['estoque'] ?? 0;
        $this->produto->estoque_minimo = $dados['estoque_minimo'] ?? 5;
        $this->produto->ativo = $dados['ativo'] ?? 1;
        // sanitize imagem path
        $img = $dados['imagem'] ?? null;
        if ($img) {
            $img = str_replace('src/public/', '', $img);
        }
        $this->produto->imagem = $img;

        if ($this->produto->editar()) {
            return array("success" => true, "message" => "Produto atualizado com sucesso!");
        }

        return array("success" => false, "message" => "Erro ao atualizar produto.");
    }

    /**
     * Inativar produto
     */
    public function deletar($id, $restaurante_id)
    {
        if ($this->produto->deletar($id, $restaurante_id)) {
            return array("success" => true, "message" => "Produto inativado com sucesso!");
        }

        return array("success" => false, "message" => "Erro ao inativar produto.");
    }

    /**
     * Atualizar estoque
     */
    public function atualizarEstoque($id, $restaurante_id, $quantidade, $tipo = 'ENTRADA')
    {
        if ($this->produto->atualizarEstoque($id, $restaurante_id, $quantidade, $tipo)) {
            return array("success" => true, "message" => "Estoque atualizado com sucesso!");
        }

        return array("success" => false, "message" => "Erro ao atualizar estoque.");
    }

    /**
     * Listar produtos com estoque baixo
     */
    public function estoqueBaixo($restaurante_id)
    {
        $stmt = $this->produto->estoqueBaixo($restaurante_id);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contar produtos ativos
     */
    public function contarAtivos($restaurante_id)
    {
        return $this->produto->contarAtivos($restaurante_id);
    }

    /**
     * Processar upload de imagem
     */
    public function processarImagem($file)
    {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        // diretório onde as imagens devem ser salvas (pasta pública)
        $upload_dir = __DIR__ . '/../../src/public/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $tmp_name = $file['tmp_name'];
        $orig_name = basename($file['name']);
        $ext = pathinfo($orig_name, PATHINFO_EXTENSION);
        $filename = uniqid('p_', true) . '.' . $ext;
        $dest = $upload_dir . $filename;

        if (move_uploaded_file($tmp_name, $dest)) {
            // Retornar caminho relativo ao diretório public (sem "src/public/")
            // ou seja, o que será concatenado ao base_url em JS
            return 'images/' . $filename;
        }

        return null;
    }

    /**
     * Garante que categoria_id exista e pertença ao mesmo restaurante.
     * Se inválida, retorna null para evitar falha no save/update.
     */
    private function normalizarCategoriaId($categoriaId, $restauranteId)
    {
        if (empty($categoriaId)) {
            return null;
        }

        $categoriaId = (int)$categoriaId;
        $restauranteId = (int)$restauranteId;

        if ($categoriaId <= 0 || $restauranteId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id FROM categorias WHERE id = :id AND restaurante_id = :rid LIMIT 1');
        $stmt->execute([
            ':id' => $categoriaId,
            ':rid' => $restauranteId
        ]);

        $categoriaValida = $stmt->fetch(PDO::FETCH_ASSOC);
        return $categoriaValida ? $categoriaId : null;
    }
}
