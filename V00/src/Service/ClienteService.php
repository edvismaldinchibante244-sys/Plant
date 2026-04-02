<?php

/*
   Service: ClienteService
   Lógica de negócio para clientes e CRM
*/

namespace App\Service;

class ClienteService
{
    private $db;
    private $modelCliente;

    public function __construct($database, $modelCliente)
    {
        $this->db = $database;
        $this->modelCliente = $modelCliente;
    }

    /**
     * Registrar cliente automaticamente na primeira compra
     */
    public function registrarClienteVenda($restaurante_id, $cliente_info)
    {
        $cliente_existente = $this->modelCliente->obter(
            null,
            $cliente_info['telefone'] ?? null,
            $cliente_info['email'] ?? null
        );

        if (!$cliente_existente) {
            return $this->modelCliente->criar([
                'restaurante_id' => $restaurante_id,
                'nome' => $cliente_info['nome'],
                'email' => $cliente_info['email'] ?? null,
                'telefone' => $cliente_info['telefone'] ?? null
            ]);
        }

        return $cliente_existente['id'];
    }

    /**
     * Atualizar estatísticas de cliente após venda
     */
    public function atualizarEstatisticasVenda($cliente_id, $restaurante_id, $valor_venda)
    {
        // Calcular novo ticket médio
        $stats = $this->modelCliente->calcularEstatisticas($cliente_id);

        $query = "UPDATE clientes 
                  SET data_ultima_visita = NOW(),
                  ticket_medio = :ticket_medio,
                  tipo_cliente = CASE 
                    WHEN :valor_total > 15000 THEN 'vip'
                    WHEN :valor_total > 5000 THEN 'corporativo'
                    ELSE tipo_cliente
                  END
                  WHERE id = :id";

        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':id' => $cliente_id,
            ':ticket_medio' => $stats['ticket_medio'] ?? 0,
            ':valor_total' => $stats['valor_total_gasto'] ?? 0
        ]);
    }

    /**
     * Registrar compra no histórico
     */
    public function registrarHistoricoCompra($cliente_id, $restaurante_id, $venda_id, $valor, $pontos)
    {
        $query = "INSERT INTO cliente_historico_compras 
                  (cliente_id, venda_id, restaurante_id, data_compra, valor_total, pontos_ganhos)
                  VALUES 
                  (:cliente_id, :venda_id, :restaurante_id, NOW(), :valor, :pontos)";

        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':cliente_id' => $cliente_id,
            ':venda_id' => $venda_id,
            ':restaurante_id' => $restaurante_id,
            ':valor' => $valor,
            ':pontos' => $pontos
        ]);
    }

    /**
     * Identificar clientes VIP para campanhas direcionadas
     */
    public function identificarVips($restaurante_id)
    {
        return $this->modelCliente->obterVips($restaurante_id, 20);
    }

    /**
     * Calcular score de cliente (para priorização)
     */
    public function calcularScoreCliente($cliente_id)
    {
        $query = "SELECT 
                  (SELECT COUNT(*) FROM cliente_historico_compras WHERE cliente_id = :cliente_id) as compras,
                  (SELECT SUM(valor_total) FROM cliente_historico_compras WHERE cliente_id = :cliente_id) as gasto,
                  (SELECT pontos_totais_ganhos FROM pontos_fidelidade WHERE cliente_id = :cliente_id) as pontos,
                  c.tipo_cliente
                  FROM clientes c
                  WHERE c.id = :cliente_id";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':cliente_id' => $cliente_id]);
        $dados = $stmt->fetch(\PDO::FETCH_ASSOC);

        $score = 0;
        $score += ($dados['compras'] ?? 0) * 10;
        $score += ($dados['gasto'] ?? 0) / 100;
        $score += ($dados['pontos'] ?? 0) / 50;

        $multiplicadores = ['comum' => 1, 'vip' => 2, 'corporativo' => 1.5];
        $score *= $multiplicadores[$dados['tipo_cliente']] ?? 1;

        return round($score, 2);
    }

    /**
     * Bloquear cliente após N no-shows
     */
    public function validarBloqueio($cliente_id)
    {
        $query = "SELECT bloqueado, motivo_bloqueio FROM clientes WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $cliente_id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Enviar campaign personalizada para cliente
     */
    public function enviarCampaignPersonalizada($cliente_id, $tipo_campaign)
    {
        $cliente = $this->modelCliente->obter($cliente_id);

        $caminhaMensagens = [
            'reativacao' => "Que saudade! Volta nos visitar 🍽️",
            'aniversario' => "Feliz aniversário! Ganhe 20% de desconto",
            'vip' => "Exclusivo para você! Acesso a pratos especiais"
        ];

        // TODO: Enviar SMS/WhatsApp via Twilio
        // $mensagem = $caminhasMensagens[$tipo_campaign];

        return true;
    }
}
