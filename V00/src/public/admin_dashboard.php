<?php

/*
 
   PÁGINA DE DASHBOARD - SUPER ADMIN
   Gráficos e análises visuais
 
 */

include_once '../config/super_admin_check.php';
include_once '../config/database.php';

requireSuperAdminPermission('view_dashboard');

$database = new Database();
$db = $database->getConnection();

$super_admin_nome = $_SESSION['nome'] ?? 'Super Admin';
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Super Admin</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/adminpro-theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
        }

        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--dark) 0%, #1a1a2e 100%);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
        }

        .sidebar .brand {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .brand h3 {
            color: white;
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }

        .sidebar .brand span {
            color: var(--secondary);
            font-size: 12px;
        }

        .sidebar .nav-item a {
            color: rgba(255, 255, 255, 0.7);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
        }

        .sidebar .nav-item a:hover,
        .sidebar .nav-item a.active {
            background: rgba(108, 92, 231, 0.3);
            color: white;
            border-left: 3px solid var(--primary);
        }

        .main-content {
            margin-left: 260px;
            padding: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .chart-card {
            background: white;
            padding: 20px;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        @media (max-width: 991px) {
            .sidebar {
                width: 100%;
                position: relative;
            }

            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 576px) {
            .sidebar {
                width: 100% !important;
                position: relative !important;
                min-height: auto !important;
                display: flex !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 14px !important;
            }

            .stat-card {
                padding: 16px !important;
            }

            .stat-icon {
                width: 44px;
                height: 44px;
                font-size: 20px;
            }

            .chart-card {
                padding: 16px !important;
            }

            .chart-container {
                height: 240px;
            }
        }
    </style>
</head>

<body>

    <div class="container-fluid">
        <div class="row">
            <nav class="sidebar col-md-3 col-lg-2 d-md-block">
                <div class="brand">
                    <h3><i class="fas fa-crown me-2"></i>Super Admin</h3>
                    <span>Dashboard Analítico</span>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item"><a href="admin.php" class="nav-link"><i class="fas fa-building"></i> Restaurantes</a></li>
                    <li class="nav-item"><a href="admin_dashboard.php" class="nav-link active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li class="nav-item"><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                </ul>
            </nav>

            <main class="main-content col-md-9 ms-sm-auto col-lg-10">
                <!-- Alertas -->
                <div id="alertContainer"></div>

                <!-- Estatísticas em Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted" style="font-size: 13px;">Total de Restaurantes</div>
                                    <div class="fs-3 fw-bold" id="statTotalRestaurantes">0</div>
                                    <div class="kpi-trend neutral" id="kpiTotalRestaurantesTrend">-</div>
                                </div>
                                <div class="stat-icon" style="background: rgba(108, 92, 231, 0.1); color: var(--primary);">
                                    <i class="fas fa-building"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted" style="font-size: 13px;">Restaurantes Ativos</div>
                                    <div class="fs-3 fw-bold text-success" id="statRestaurantesAtivos">0</div>
                                    <div class="kpi-trend neutral" id="kpiRestaurantesAtivosTrend">-</div>
                                </div>
                                <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted" style="font-size: 13px;">Receita Mensal</div>
                                    <div class="fs-3 fw-bold text-info" id="statReceitaMensal">0 MZN</div>
                                    <div class="kpi-trend neutral" id="kpiReceitaMensalTrend">-</div>
                                </div>
                                <div class="stat-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted" style="font-size: 13px;">Assinaturas Expirando</div>
                                    <div class="fs-3 fw-bold text-warning" id="statAssinaturasExpirando">0</div>
                                    <div class="kpi-trend neutral" id="kpiAssinaturasExpirandoTrend">-</div>
                                </div>
                                <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos Linha 1 -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="chart-card">
                            <h5 class="mb-3"><i class="fas fa-chart-pie me-2 text-primary"></i>Restaurantes por Plano</h5>
                            <div class="chart-container">
                                <canvas id="chartPlanos"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-card">
                            <h5 class="mb-3"><i class="fas fa-chart-pie me-2 text-success"></i>Restaurantes por Status</h5>
                            <div class="chart-container">
                                <canvas id="chartStatus"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos Linha 2 -->
                <div class="row g-4 mb-4">
                    <div class="col-md-8">
                        <div class="chart-card">
                            <h5 class="mb-3"><i class="fas fa-chart-line me-2 text-info"></i>Crescimento de Restaurantes</h5>
                            <div class="chart-container">
                                <canvas id="chartCrescimento"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="chart-card">
                            <h5 class="mb-3"><i class="fas fa-chart-bar me-2 text-warning"></i>Receita por Plano</h5>
                            <div class="chart-container">
                                <canvas id="chartReceita"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos Linha 3 -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="chart-card">
                            <h5 class="mb-3"><i class="fas fa-trophy me-2 text-warning"></i>Top 5 Restaurantes (Usuários)</h5>
                            <div class="chart-container">
                                <canvas id="chartTopUsuarios"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-card">
                            <h5 class="mb-3"><i class="fas fa-shopping-cart me-2 text-info"></i>Compras de Planos por Mês</h5>
                            <div class="chart-container">
                                <canvas id="chartCompras"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cores para os gráficos
        const cores = {
            primary: '#6c5ce7',
            secondary: '#a29bfe',
            success: '#28a745',
            warning: '#ffc107',
            danger: '#dc3545',
            info: '#17a2b8',
            basico: '#6c757d',
            profissional: '#17a2b8',
            enterprise: '#ffc107'
        };

        // Carregar dados e inicializar gráficos
        document.addEventListener('DOMContentLoaded', function() {
            carregarEstatisticas();
            carregarGraficos();
        });

        // Carregar estatísticas
        function carregarEstatisticas() {
            fetch('api/super_admin_estatisticas.php', {
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('statTotalRestaurantes').textContent = data.data.total_restaurantes;
                        document.getElementById('statRestaurantesAtivos').textContent = data.data.restaurantes_ativos;
                        document.getElementById('statReceitaMensal').textContent = data.data.receita_mensal.toFixed(0) + ' MZN';
                        document.getElementById('statAssinaturasExpirando').textContent = data.data.assinaturas_expirando;

                        atualizarKpiComparativo('kpiTotalRestaurantesTrend', data.data.comparativos?.total_restaurantes);
                        atualizarKpiComparativo('kpiRestaurantesAtivosTrend', data.data.comparativos?.restaurantes_ativos);
                        atualizarKpiComparativo('kpiReceitaMensalTrend', data.data.comparativos?.receita_mensal, true);
                        atualizarKpiComparativo('kpiAssinaturasExpirandoTrend', data.data.comparativos?.assinaturas_expirando);
                    }
                })
                .catch(err => console.error('Erro ao carregar estatísticas:', err));
        }

        function atualizarKpiComparativo(elementId, comparativo, monetario = false) {
            const el = document.getElementById(elementId);
            if (!el || !comparativo) {
                return;
            }

            const variacao = Number(comparativo.variacao_percentual || 0);
            const atual = Number(comparativo.atual || 0);
            const anterior = Number(comparativo.anterior || 0);

            const signal = variacao > 0 ? '+' : '';
            const arrow = variacao > 0 ? 'fa-arrow-up' : (variacao < 0 ? 'fa-arrow-down' : 'fa-minus');
            const css = variacao > 0 ? 'positive' : (variacao < 0 ? 'negative' : 'neutral');
            const anteriorTexto = monetario ? `${anterior.toFixed(0)} MZN` : `${anterior}`;

            el.className = `kpi-trend ${css}`;
            el.innerHTML = `<i class="fas ${arrow}"></i> ${signal}${variacao.toFixed(2)}% <small>vs mês anterior (${anteriorTexto})</small>`;

            if (atual === 0 && anterior === 0) {
                el.className = 'kpi-trend neutral';
                el.innerHTML = '<i class="fas fa-minus"></i> Sem variação <small>vs mês anterior</small>';
            }
        }

        // Carregar gráficos
        function carregarGraficos() {
            fetch('api/super_admin_dashboard_graficos.php', {
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        criarGraficoPlanos(data.data.restaurantes_por_plano);
                        criarGraficoStatus(data.data.restaurantes_por_status);
                        criarGraficoCrescimento(data.data.crescimento_restaurantes);
                        criarGraficoReceita(data.data.receita_por_plano);
                        criarGraficoTopUsuarios(data.data.top_restaurantes_usuarios);
                        criarGraficoCompras(data.data.compras_por_mes);
                    }
                })
                .catch(err => console.error('Erro ao carregar gráficos:', err));
        }

        // Gráfico de Pizza - Planos
        function criarGraficoPlanos(dados) {
            const ctx = document.getElementById('chartPlanos').getContext('2d');

            const labels = dados.map(d => {
                if (d.plano === 'EMPRESARIAL') return 'Empresarial';
                if (d.plano === 'PROFISSIONAL') return 'Profissional';
                return 'Básico';
            });
            const values = dados.map(d => d.total);
            const backgroundColors = dados.map(d => {
                if (d.plano === 'EMPRESARIAL') return cores.enterprise;
                if (d.plano === 'PROFISSIONAL') return cores.profissional;
                return cores.basico;
            });

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: backgroundColors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Gráfico de Pizza - Status
        function criarGraficoStatus(dados) {
            const ctx = document.getElementById('chartStatus').getContext('2d');

            const labels = dados.map(d => {
                if (d.status === 'ATIVO') return 'Ativo';
                if (d.status === 'BLOQUEADO') return 'Bloqueado';
                return 'Cancelado';
            });
            const values = dados.map(d => d.total);
            const backgroundColors = dados.map(d => {
                if (d.status === 'ATIVO') return cores.success;
                if (d.status === 'BLOQUEADO') return cores.warning;
                return cores.danger;
            });

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: backgroundColors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Gráfico de Linha - Crescimento
        function criarGraficoCrescimento(dados) {
            const ctx = document.getElementById('chartCrescimento').getContext('2d');

            const labels = dados.map(d => {
                const [ano, mes] = d.mes.split('-');
                const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
                return meses[parseInt(mes) - 1] + '/' + ano.slice(2);
            });
            const values = dados.map(d => parseInt(d.total));

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Novos Restaurantes',
                        data: values,
                        borderColor: cores.info,
                        backgroundColor: 'rgba(23, 162, 184, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Gráfico de Barra - Receita
        function criarGraficoReceita(dados) {
            const ctx = document.getElementById('chartReceita').getContext('2d');

            const labels = dados.map(d => {
                if (d.plano === 'EMPRESARIAL') return 'Empresarial';
                if (d.plano === 'PROFISSIONAL') return 'Profissional';
                return 'Básico';
            });
            const values = dados.map(d => parseFloat(d.receita) || 0);
            const backgroundColors = dados.map(d => {
                if (d.plano === 'EMPRESARIAL') return cores.enterprise;
                if (d.plano === 'PROFISSIONAL') return cores.profissional;
                return cores.basico;
            });

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Receita (MZN)',
                        data: values,
                        backgroundColor: backgroundColors,
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Gráfico de Barra Horizontal - Top Usuários
        function criarGraficoTopUsuarios(dados) {
            const ctx = document.getElementById('chartTopUsuarios').getContext('2d');

            const labels = dados.map(d => d.nome.substring(0, 15) + (d.nome.length > 15 ? '...' : ''));
            const values = dados.map(d => parseInt(d.total_usuarios));

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Usuários',
                        data: values,
                        backgroundColor: [
                            cores.primary,
                            cores.secondary,
                            cores.info,
                            cores.warning,
                            cores.success
                        ],
                        borderRadius: 5
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Gráfico de Linha - Compras por Mês
        function criarGraficoCompras(dados) {
            const ctx = document.getElementById('chartCompras').getContext('2d');

            const labels = dados.map(d => {
                const [ano, mes] = d.mes.split('-');
                const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
                return meses[parseInt(mes) - 1] + '/' + ano.slice(2);
            });
            const total = dados.map(d => parseInt(d.total));
            const aprovados = dados.map(d => parseInt(d.aprovados));

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Total',
                        data: total,
                        borderColor: cores.primary,
                        backgroundColor: 'rgba(108, 92, 231, 0.1)',
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Aprovados',
                        data: aprovados,
                        borderColor: cores.success,
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        (() => {
            const logoutLinks = document.querySelectorAll('a[href="logout.php"]');
            if (!logoutLinks.length) {
                return;
            }

            const markOffline = () => {
                const url = 'api/online_offline.php';

                try {
                    if (navigator.sendBeacon) {
                        const data = new FormData();
                        data.append('source', 'logout');
                        navigator.sendBeacon(url, data);
                        return;
                    }
                } catch (e) {}

                try {
                    fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        keepalive: true,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                } catch (e) {}
            };

            logoutLinks.forEach((link) => {
                link.addEventListener('click', markOffline, {
                    passive: true
                });
            });
        })();
    </script>
</body>

</html>

