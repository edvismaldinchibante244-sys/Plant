<?php

/*
   Configuração dos Planos do Sistema
   Define limites e funcionalidades de cada plano
   Versão 2.0 - Com mais granularidade
 */

$planos = [
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
        'limites' => [
            'produtos' => 100,
            'usuarios' => 4,
            'mesas' => 20,
            'categorias' => 10,
            'subcategorias' => 0,
        ],
        'funcionalidades' => [
            // PDV e Vendas
            'pdv' => true,
            'caixa' => false,
            'garcons' => false,

            // Produtos e Estoque
            'estoque_simples' => true,
            'produtos_basico' => true,
            'estoque_avancado' => false,
            'transferencia_estoque' => false,

            // Mesas e Pedidos
            'mesas' => true,
            'pedidos_online' => false,
            'fluxo_garcom' => true,
            'fluxo_cozinha' => true,

            // Categorias (NOVO)
            'subcategorias' => false,

            // Relatórios
            'relatorio_diario' => true,
            'relatorios_avancados' => false,
            'estatisticas_produtos' => false,
            'relatorios_inteligentes' => false,
            'relatorio_personalizado' => false,
            'relatorio_financeiro' => false,
            'export_pdf' => false,
            'export_excel' => false,
            'export_csv' => false,
            'exportacao_relatorios' => false,

            // Dashboard
            'dashboard_executivo' => false,

            // Clientes
            'clientes' => true,

            // Impressão
            'impressao_recibos' => true,
            'logo_recibo' => true,

            // Backup
            'backup_manual' => true,
            'backup_automatico' => false,
            'backup_diario' => false,
            'backup_hora' => false,
            'download_banco' => false,
            'recuperacao_automatica' => false,

            // Multi-filial
            'multi_filial' => false,

            // Integrações
            'api_integracao' => false,
            'notificacoes' => false,

            // Suporte
            'suporte_email' => true,
            'suporte_whatsapp' => false,
            'suporte_24h' => false,
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
            'categorias' => 25,
            'subcategorias' => 50,
        ],
        'funcionalidades' => [
            // PDV e Vendas
            'pdv' => true,
            'caixa' => true,
            'garcons' => true,

            // Produtos e Estoque
            'estoque_simples' => true,
            'produtos_basico' => true,
            'estoque_avancado' => false,
            'transferencia_estoque' => false,

            // Mesas e Pedidos
            'mesas' => true,
            'pedidos_online' => true,
            'fluxo_garcom' => true,
            'fluxo_cozinha' => true,

            // Categorias (NOVO)
            'subcategorias' => true,

            // Relatórios
            'relatorio_diario' => true,
            'relatorios_avancados' => true,
            'estatisticas_produtos' => true,
            'relatorios_inteligentes' => false,
            'relatorio_personalizado' => true,
            'relatorio_financeiro' => true,
            'export_pdf' => true,
            'export_excel' => true,
            'export_csv' => true,
            'exportacao_relatorios' => true,

            // Dashboard
            'dashboard_executivo' => false,

            // Clientes
            'clientes' => true,

            // Impressão
            'impressao_recibos' => true,
            'logo_recibo' => true,

            // Backup
            'backup_manual' => true,
            'backup_automatico' => false,
            'backup_diario' => true,
            'backup_hora' => false,
            'download_banco' => false,
            'recuperacao_automatica' => false,

            // Multi-filial
            'multi_filial' => false,

            // Integrações
            'api_integracao' => false,
            'notificacoes' => false,

            // Suporte
            'suporte_email' => true,
            'suporte_whatsapp' => true,
            'suporte_24h' => false,
            'suporte_prioritario' => false,
        ]
    ],
    'EMPRESARIAL' => [
        'nome' => 'Empresarial',
        'descricao' => 'Para grandes restaurantes e redes.',
        'cor' => '#6f42c1',
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
            'categorias' => -1,
            'subcategorias' => -1,
        ],
        'funcionalidades' => [
            // PDV e Vendas
            'pdv' => true,
            'caixa' => true,
            'garcons' => true,

            // Produtos e Estoque
            'estoque_simples' => true,
            'produtos_basico' => true,
            'estoque_avancado' => true,
            'transferencia_estoque' => true,

            // Mesas e Pedidos
            'mesas' => true,
            'pedidos_online' => true,
            'fluxo_garcom' => true,
            'fluxo_cozinha' => true,

            // Categorias (NOVO)
            'subcategorias' => true,

            // Relatórios
            'relatorio_diario' => true,
            'relatorios_avancados' => true,
            'estatisticas_produtos' => true,
            'relatorios_inteligentes' => true,
            'relatorio_personalizado' => true,
            'relatorio_financeiro' => true,
            'export_pdf' => true,
            'export_excel' => true,
            'export_csv' => true,
            'exportacao_relatorios' => true,

            // Dashboard
            'dashboard_executivo' => true,

            // Clientes
            'clientes' => true,

            // Impressão
            'impressao_recibos' => true,
            'logo_recibo' => true,

            // Backup
            'backup_manual' => true,
            'backup_automatico' => true,
            'backup_diario' => true,
            'backup_hora' => true,
            'download_banco' => true,
            'recuperacao_automatica' => true,

            // Multi-filial
            'multi_filial' => true,

            // Integrações
            'api_integracao' => false, // Removido conforme solicitado
            'notificacoes' => true,

            // Suporte
            'suporte_email' => true,
            'suporte_whatsapp' => true,
            'suporte_24h' => true,
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
        $planos = require __DIR__ . '/planos.php';

        $plano = strtoupper($plano);

        if (!isset($planos[$plano])) {
            return false;
        }

        return $planos[$plano]['funcionalidades'][$funcionalidade] ?? false;
    }
}

/**
 * Função para obter os limites do plano
 */
if (!function_exists('plano_get_limites')) {
    function plano_get_limites($plano)
    {
        $planos = require __DIR__ . '/planos.php';

        $plano = strtoupper($plano);

        if (!isset($planos[$plano])) {
            return $planos['BASICO']['limites'];
        }

        return $planos[$plano]['limites'];
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

/**
 * Função para obter a lista resumida de recursos exibida no catálogo e dashboard
 */
if (!function_exists('plano_get_recursos_catalogo')) {
    function plano_get_recursos_catalogo($plano)
    {
        $planos = require __DIR__ . '/planos.php';
        $plano = strtoupper((string)$plano);
        $config = $planos[$plano] ?? $planos['BASICO'];
        $limites = $config['limites'] ?? [];
        $funcionalidades = $config['funcionalidades'] ?? [];

        $recursos = [];
        $recursos[] = (($limites['produtos'] ?? 0) == -1) ? 'Produtos ilimitados' : 'Até ' . ($limites['produtos'] ?? 0) . ' produtos';
        $recursos[] = (($limites['usuarios'] ?? 0) == -1) ? 'Usuários ilimitados' : 'Até ' . ($limites['usuarios'] ?? 0) . ' usuários';
        $recursos[] = (($limites['mesas'] ?? 0) == -1) ? 'Mesas ilimitadas' : 'Até ' . ($limites['mesas'] ?? 0) . ' mesas';

        if (!empty($funcionalidades['pedidos_online'])) {
            $recursos[] = 'Pedidos online (QR Code)';
        }

        if (!empty($funcionalidades['relatorios_avancados'])) {
            $recursos[] = 'Relatórios avançados';
        } elseif (!empty($funcionalidades['relatorio_diario'])) {
            $recursos[] = 'Relatório diário';
        }

        if (!empty($funcionalidades['multi_filial'])) {
            $recursos[] = 'Multi-filial';
        }

        if (!empty($funcionalidades['suporte_24h'])) {
            $recursos[] = 'Suporte 24/7';
        } elseif (!empty($funcionalidades['suporte_whatsapp'])) {
            $recursos[] = 'Suporte via WhatsApp';
        } elseif (!empty($funcionalidades['suporte_email'])) {
            $recursos[] = 'Suporte por email';
        }

        return array_values(array_unique($recursos));
    }
}

return $planos;
