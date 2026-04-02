<?php

/**
 * Configuração dos Planos do Sistema
 * Define limites e funcionalidades de cada plano
 */

return [
    'BASICO' => [
        'nome' => 'Básico',
        'descricao' => 'Ideal para lanchonetes ou pequenos restaurantes.',
        'cor' => '#6c757d',
        'icone' => 'fa-user',
        'precos' => [
            'mensal' => 1500,
            'trimestral' => 4000,
            'anual' => 15000
        ],
        'e_gratis' => false,
        'limites' => [
            'produtos' => 100,
            'usuarios' => 3,
            'mesas' => 20,
        ],
        'funcionalidades' => [
            'pdv' => true,
            'estoque_simples' => true,
            'mesas' => true,
            'produtos_basico' => true,
            'relatorio_diario' => true,
            'clientes' => true,
            'impressao_recibos' => true,
            'backup_manual' => true,
            'logo_recibo' => true,
            'pedidos_online' => false,
            'fluxo_garcom' => true,
            'fluxo_cozinha' => true,
            'relatorios_avancados' => false,
            'multi_filial' => false,
            'caixa' => false,
            'garcons' => false,
            'estatisticas_produtos' => false,
            'exportacao_relatorios' => false,
            'dashboard_executivo' => false,
            'estoque_avancado' => false,
            'transferencia_estoque' => false,
            'relatorios_inteligentes' => false,
            'api_integracao' => false,
            'backup_automatico' => false,
            'notificacoes' => false,
            'suporte_prioritario' => false,
        ]
    ],
    'PROFISSIONAL' => [
        'nome' => 'Profissional',
        'descricao' => 'Ideal para restaurantes médios.',
        'cor' => '#17a2b8',
        'icone' => 'fa-star',
        'precos' => [
            'mensal' => 3000,
            'trimestral' => 8000,
            'anual' => 30000
        ],
        'limites' => [
            'produtos' => 500,
            'usuarios' => 10,
            'mesas' => 50,
        ],
        'funcionalidades' => [
            'pdv' => true,
            'estoque_simples' => true,
            'mesas' => true,
            'produtos_basico' => true,
            'relatorio_diario' => true,
            'clientes' => true,
            'impressao_recibos' => true,
            'backup_manual' => true,
            'logo_recibo' => true,
            'pedidos_online' => true,
            'fluxo_garcom' => true,
            'fluxo_cozinha' => true,
            'relatorios_avancados' => true,
            'caixa' => true,
            'garcons' => true,
            'estatisticas_produtos' => true,
            'exportacao_relatorios' => true,
            'multi_filial' => false,
            'dashboard_executivo' => false,
            'estoque_avancado' => false,
            'transferencia_estoque' => false,
            'relatorios_inteligentes' => false,
            'api_integracao' => false,
            'backup_automatico' => false,
            'notificacoes' => false,
            'suporte_prioritario' => false,
        ]
    ],
    'EMPRESARIAL' => [
        'nome' => 'Empresarial',
        'descricao' => 'Ideal para restaurantes grandes ou cadeias.',
        'cor' => '#FF6B35',
        'icone' => 'fa-crown',
        'precos' => [
            'mensal' => 6000,
            'trimestral' => 16000,
            'anual' => 60000
        ],
        'limites' => [
            'produtos' => -1,
            'usuarios' => -1,
            'mesas' => -1,
        ],
        'funcionalidades' => [
            'pdv' => true,
            'estoque_simples' => true,
            'mesas' => true,
            'produtos_basico' => true,
            'relatorio_diario' => true,
            'clientes' => true,
            'impressao_recibos' => true,
            'backup_manual' => true,
            'logo_recibo' => true,
            'pedidos_online' => true,
            'fluxo_garcom' => true,
            'fluxo_cozinha' => true,
            'relatorios_avancados' => true,
            'caixa' => true,
            'garcons' => true,
            'estatisticas_produtos' => true,
            'exportacao_relatorios' => true,
            'multi_filial' => true,
            'dashboard_executivo' => true,
            'estoque_avancado' => true,
            'transferencia_estoque' => true,
            'relatorios_inteligentes' => true,
            'api_integracao' => true,
            'backup_automatico' => true,
            'notificacoes' => true,
            'suporte_prioritario' => true,
        ]
    ]
];

/**
 * Função para verificar se uma funcionalidade está disponível no plano
 */
if (!function_exists('plano_tem_funcionalidade')) {
    function plano_tem_funcionalidade($plano, $funcionalidade)
    {
        static $planos_cache = null;

        if ($planos_cache === null) {
            $planos_cache = require __DIR__ . '/planos.php';
        }

        $plano = strtoupper($plano);

        if (!isset($planos_cache[$plano])) {
            return false;
        }

        return $planos_cache[$plano]['funcionalidades'][$funcionalidade] ?? false;
    }
}

/**
 * Função para obter os limites do plano
 */
if (!function_exists('plano_get_limites')) {
    function plano_get_limites($plano)
    {
        static $planos_cache = null;

        if ($planos_cache === null) {
            $planos_cache = require __DIR__ . '/planos.php';
        }

        $plano = strtoupper($plano);

        if (!isset($planos_cache[$plano])) {
            return $planos_cache['BASICO']['limites'];
        }

        return $planos_cache[$plano]['limites'];
    }
}

/**
 * Função para verificar limite de produtos
 */
if (!function_exists('plano_pode_cadastrar_produto')) {
    function plano_pode_cadastrar_produto($plano, $qtd_atual = 0)
    {
        $limites = plano_get_limites($plano);

        if ($limites['produtos'] == -1) {
            return ['permitido' => true, 'remaining' => -1];
        }

        $remaining = $limites['produtos'] - $qtd_atual;

        return [
            'permitido' => $remaining > 0,
            'remaining' => $remaining,
            'limite' => $limites['produtos']
        ];
    }
}

/**
 * Função para verificar limite de usuários
 */
if (!function_exists('plano_pode_cadastrar_usuario')) {
    function plano_pode_cadastrar_usuario($plano, $qtd_atual = 0)
    {
        $limites = plano_get_limites($plano);

        if ($limites['usuarios'] == -1) {
            return ['permitido' => true, 'remaining' => -1];
        }

        $remaining = $limites['usuarios'] - $qtd_atual;

        return [
            'permitido' => $remaining > 0,
            'remaining' => $remaining,
            'limite' => $limites['usuarios']
        ];
    }
}
