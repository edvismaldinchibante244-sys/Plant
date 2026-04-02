<?php

/*
   Service de Mesas
   Arquitetura N-Tier
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/plano_check.php';
require_once __DIR__ . '/../Model/Mesa.php';

class MesaService
{
    private $db;
    private $mesa;

    public function __construct($mesa = null)
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->mesa = $mesa ?? new Mesa($this->db);
    }

    /**
     * Listar mesas
     */
    public function listar($restaurante_id)
    {
        $stmt = $this->mesa->listar($restaurante_id);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar mesas livres
     */
    public function mesasLivres($restaurante_id)
    {
        $stmt = $this->mesa->mesasLivres($restaurante_id);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atualizar status da mesa
     */
    public function atualizarStatus($id, $restaurante_id, $status)
    {
        if ($this->mesa->atualizarStatus($id, $restaurante_id, $status)) {
            return array("success" => true, "message" => "Status atualizado com sucesso!");
        }

        return array("success" => false, "message" => "Erro ao atualizar status.");
    }

    /**
     * Cadastrar nova mesa
     */
    public function cadastrar($dados, $restaurante_id, $plano_restaurante_id = 0)
    {
        $planoRestauranteId = $plano_restaurante_id > 0 ? $plano_restaurante_id : $restaurante_id;
        $temMultiFilial = $planoRestauranteId > 0 && plano_tem_funcionalidade_db($planoRestauranteId, 'multi_filial');

        if ($temMultiFilial) {
            $stmtCount = $this->db->prepare("
                SELECT COUNT(*)
                FROM mesas m
                INNER JOIN restaurantes r ON r.id = m.restaurante_id
                WHERE m.restaurante_id = :base_restaurante_id OR r.filial_id = :filial_base_id
            ");
            $stmtCount->bindValue(':base_restaurante_id', $planoRestauranteId, PDO::PARAM_INT);
            $stmtCount->bindValue(':filial_base_id', $planoRestauranteId, PDO::PARAM_INT);
            $stmtCount->execute();
            $totalMesas = (int)$stmtCount->fetchColumn();
        } else {
            $queryCount = "SELECT COUNT(*) FROM mesas WHERE restaurante_id = :rid";
            $stmtCount = $this->db->prepare($queryCount);
            $stmtCount->bindParam(':rid', $restaurante_id, PDO::PARAM_INT);
            $stmtCount->execute();
            $totalMesas = (int)$stmtCount->fetchColumn();
        }

        $verificacaoPlano = plano_verificar_limite_db($planoRestauranteId, 'mesas', $totalMesas);
        if (!$verificacaoPlano['permitido']) {
            return array(
                "success" => false,
                "message" => "Limite do plano atingido. O plano {$verificacaoPlano['plano']} permite até {$verificacaoPlano['limite']} mesas."
            );
        }

        // Verificar se número já existe
        $query = "SELECT id FROM mesas WHERE restaurante_id = :rid AND numero = :numero LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':rid', $restaurante_id);
        $numero = trim((string)($dados['numero'] ?? ''));
        $stmt->bindParam(':numero', $numero, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $mensagemDuplicado = mb_strtolower($numero, 'UTF-8') === 'balcão' || strtolower($numero) === 'balcao'
                ? "Já existe um balcão cadastrado"
                : "Já existe uma mesa com este número";
            return array("success" => false, "message" => $mensagemDuplicado);
        }

        $query2 = "INSERT INTO mesas (restaurante_id, numero, capacidade, status)
                   VALUES (:restaurante_id, :numero, :capacidade, 'LIVRE')";
        $stmt2 = $this->db->prepare($query2);
        $stmt2->bindParam(':restaurante_id', $restaurante_id);
        $capacidade = (int)($dados['capacidade'] ?? 0);
        $stmt2->bindParam(':numero', $numero, PDO::PARAM_STR);
        $stmt2->bindParam(':capacidade', $capacidade, PDO::PARAM_INT);

        if ($stmt2->execute()) {
            $mensagemSucesso = mb_strtolower($numero, 'UTF-8') === 'balcão' || strtolower($numero) === 'balcao'
                ? "Balcão cadastrado com sucesso!"
                : "Mesa cadastrada com sucesso!";
            return array("success" => true, "message" => $mensagemSucesso);
        }

        return array("success" => false, "message" => "Erro ao cadastrar mesa");
    }
}
