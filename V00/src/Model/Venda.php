<?php

/**
 * ============================================
 * CLASSE DE VENDAS - MODEL
 * ============================================
 */

class Venda
{
    private $conn;
    private $table_name = "vendas";

    public $id;
    public $restaurante_id;
    public $usuario_id;
    public $caixa_id;
    public $mesa_id;
    public $total;
    public $desconto;
    public $total_final;
    public $forma_pagamento;
    public $status;
    public $numero_fatura;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Listar vendas do restaurante
     */
    public function listar($restaurante_id, $data_inicio = null, $data_fim = null)
    {
        $query = "SELECT v.*, u.nome as usuario_nome, m.numero as mesa_numero 
                  FROM " . $this->table_name . " v
                  LEFT JOIN usuarios u ON v.usuario_id = u.id
                  LEFT JOIN mesas m ON v.mesa_id = m.id
                  WHERE v.restaurante_id = :restaurante_id";

        if ($data_inicio && $data_fim) {
            $query .= " AND DATE(v.criado_em) BETWEEN :data_inicio AND :data_fim";
        }

        $query .= " ORDER BY v.criado_em DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":restaurante_id", $restaurante_id);

        if ($data_inicio && $data_fim) {
            $stmt->bindParam(":data_inicio", $data_inicio);
            $stmt->bindParam(":data_fim", $data_fim);
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * Buscar venda por ID
     */
    public function buscarPorId($id, $restaurante_id)
    {
        $query = "SELECT v.*, u.nome as usuario_nome, m.numero as mesa_numero
                  FROM " . $this->table_name . " v
                  LEFT JOIN usuarios u ON v.usuario_id = u.id
                  LEFT JOIN mesas m ON v.mesa_id = m.id
                  WHERE v.id = :id AND v.restaurante_id = :restaurante_id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":restaurante_id", $restaurante_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar itens de uma venda
     */
    public function buscarItens($venda_id)
    {
        $query = "SELECT iv.*, COALESCE(p.nome, CONCAT('Produto #', iv.produto_id)) AS produto_nome
                  FROM itens_venda iv
                  LEFT JOIN produtos p ON iv.produto_id = p.id
                  WHERE iv.venda_id = :venda_id
                  ORDER BY COALESCE(p.nome, CONCAT('Produto #', iv.produto_id)) ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":venda_id", $venda_id);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Criar nova venda
     */
    public function criar()
    {
        $query = "INSERT INTO " . $this->table_name . "
                  SET restaurante_id  = :restaurante_id,
                      usuario_id      = :usuario_id,
                      caixa_id        = :caixa_id,
                      mesa_id         = :mesa_id,
                      total           = :total,
                      desconto        = :desconto,
                      total_final     = :total_final,
                      forma_pagamento = :forma_pagamento,
                      status          = :status,
                      numero_fatura   = :numero_fatura";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":restaurante_id",  $this->restaurante_id);
        $stmt->bindParam(":usuario_id",      $this->usuario_id);
        $stmt->bindParam(":caixa_id",        $this->caixa_id);
        $stmt->bindParam(":mesa_id",         $this->mesa_id);
        $stmt->bindParam(":total",           $this->total);
        $stmt->bindParam(":desconto",        $this->desconto);
        $stmt->bindParam(":total_final",     $this->total_final);
        $stmt->bindParam(":forma_pagamento", $this->forma_pagamento);
        $stmt->bindParam(":status",          $this->status);
        $stmt->bindParam(":numero_fatura",   $this->numero_fatura);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    /**
     * Adicionar item à venda
     */
    public function adicionarItem($venda_id, $produto_id, $quantidade, $preco_unitario)
    {
        $subtotal = $quantidade * $preco_unitario;

        $query = "INSERT INTO itens_venda
                  SET venda_id       = :venda_id,
                      produto_id     = :produto_id,
                      quantidade     = :quantidade,
                      preco_unitario = :preco_unitario,
                      subtotal       = :subtotal";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":venda_id",       $venda_id);
        $stmt->bindParam(":produto_id",     $produto_id);
        $stmt->bindParam(":quantidade",     $quantidade);
        $stmt->bindParam(":preco_unitario", $preco_unitario);
        $stmt->bindParam(":subtotal",       $subtotal);

        return $stmt->execute();
    }

    /**
     * Gerar número de fatura
     */
    public function gerarNumeroFatura($restaurante_id)
    {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " 
                  WHERE restaurante_id = :restaurante_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":restaurante_id", $restaurante_id);
        $stmt->execute();

        $row    = $stmt->fetch(PDO::FETCH_ASSOC);
        $numero = $row['total'] + 1;

        return "FT" . date('Y') . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Vendas de hoje
     */
    public function vendasHoje($restaurante_id)
    {
        $query = "SELECT SUM(total_final) as total 
                  FROM " . $this->table_name . " 
                  WHERE restaurante_id = :restaurante_id 
                  AND DATE(criado_em) = CURDATE() 
                  AND status = 'PAGO'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":restaurante_id", $restaurante_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ?? 0;
    }

    /**
     * Contar vendas de hoje
     */
    public function contarVendasHoje($restaurante_id)
    {
        $query = "SELECT COUNT(*) as total 
                  FROM " . $this->table_name . " 
                  WHERE restaurante_id = :restaurante_id 
                  AND DATE(criado_em) = CURDATE() 
                  AND status = 'PAGO'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":restaurante_id", $restaurante_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ?? 0;
    }

    /**
     * Total de vendas por período
     */
    public function totalVendas($restaurante_id, $data_inicio, $data_fim)
    {
        $query = "SELECT COUNT(*) as total, SUM(total_final) as valor_total 
                  FROM " . $this->table_name . " 
                  WHERE restaurante_id = :restaurante_id 
                  AND DATE(criado_em) BETWEEN :data_inicio AND :data_fim
                  AND status = 'PAGO'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":restaurante_id", $restaurante_id);
        $stmt->bindParam(":data_inicio",    $data_inicio);
        $stmt->bindParam(":data_fim",       $data_fim);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Cancelar venda
     */
    public function cancelar($id, $restaurante_id)
    {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = 'CANCELADO' 
                  WHERE id = :id AND restaurante_id = :restaurante_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id",             $id);
        $stmt->bindParam(":restaurante_id", $restaurante_id);

        return $stmt->execute();
    }

    /**
     * Últimas vendas (para dashboard)
     */
    public function ultimasVendas($restaurante_id, $limite = 10)
    {
        $query = "SELECT v.*, u.nome as usuario_nome, m.numero as mesa_numero
                  FROM " . $this->table_name . " v
                  LEFT JOIN usuarios u ON v.usuario_id = u.id
                  LEFT JOIN mesas m ON v.mesa_id = m.id
                  WHERE v.restaurante_id = :restaurante_id
                  ORDER BY v.criado_em DESC
                  LIMIT :limite";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":restaurante_id", $restaurante_id);
        $stmt->bindParam(":limite",         $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Vendas por dia (para gráfico)
     */
    public function vendasPorDia($restaurante_id, $dias = 7)
    {
        $query = "SELECT DATE(criado_em) as data, SUM(total_final) as total
                  FROM " . $this->table_name . " 
                  WHERE restaurante_id = :restaurante_id 
                  AND DATE(criado_em) >= DATE_SUB(CURDATE(), INTERVAL :dias DAY)
                  AND status = 'PAGO'
                  GROUP BY DATE(criado_em)
                  ORDER BY data ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":restaurante_id", $restaurante_id);
        $stmt->bindParam(":dias",           $dias, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }
}
