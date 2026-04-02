<?php

//PÁGINA INICIAL - RESTAURANTESAAS
//Login e Opções de Acesso

include_once __DIR__ . '/../config/security.php';
security_start_session();
security_set_headers();
security_regenerate_session(15);
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RestauranteSaaS - Sistema de Gestão de Restaurantes</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --primary: #FF6B35;
            --secondary: #F7931E;
            --surface-dark: #111522;
            --surface-soft: rgba(255, 255, 255, 0.08);
            --surface-border: rgba(255, 255, 255, 0.12);
            --text-soft: rgba(255, 255, 255, 0.72);
            --text-muted: rgba(255, 255, 255, 0.58);
            --shadow-soft: 0 24px 60px rgba(0, 0, 0, 0.28);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            overflow-x: hidden;
            color: #fff;
        }

        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 107, 53, 0.1) 0%, transparent 50%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .hero-content {
            position: relative;
            z-index: 10;
        }

        .hero-shell {
            max-width: 1140px;
            margin: 0 auto;
            padding: 38px;
            border-radius: 36px;
            background:
                linear-gradient(145deg, rgba(255, 255, 255, 0.10), rgba(255, 255, 255, 0.04)),
                rgba(7, 11, 24, 0.28);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: var(--shadow-soft);
            backdrop-filter: blur(18px);
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            margin-bottom: 20px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.88);
            font-size: 0.88rem;
            font-weight: 500;
        }

        .hero-badge i {
            color: var(--secondary);
        }

        .brand-logo {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            box-shadow: 0 20px 60px rgba(255, 107, 53, 0.4);
            margin: 0 auto 30px;
        }

        .main-title {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(135deg, #fff, var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .subtitle {
            max-width: 680px;
            margin: 0 auto 40px;
            color: rgba(255, 255, 255, 0.74);
            font-size: 1.08rem;
            line-height: 1.7;
        }

        .action-cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
            align-items: stretch;
            margin-bottom: 26px;
        }

        .action-card {
            position: relative;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.06));
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 26px;
            padding: 32px 26px;
            width: 100%;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            overflow: hidden;
            box-shadow: 0 18px 38px rgba(0, 0, 0, 0.16);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .action-card.login {
            color: #fff;
            opacity: 1;
        }

        .action-card.login .action-title,
        .action-card.login .action-desc,
        .action-card.login .action-icon {
            opacity: 1;
            visibility: visible;
            transform: none;
            display: block;
        }

        .action-card.login * {
            opacity: 1 !important;
            visibility: visible !important;
        }

        .action-card.reserve .action-title,
        .action-card.reserve .action-desc,
        .action-card.reserve .action-icon,
        .action-card.reserve .action-tag {
            opacity: 1;
            visibility: visible;
            transform: none;
        }

        .action-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.07), transparent 55%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-8px);
            border-color: var(--primary);
            box-shadow: 0 26px 52px rgba(0, 0, 0, 0.26);
        }

        .action-card:hover::before {
            opacity: 1;
        }

        .action-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 20px;
        }

        .action-card.login .action-icon {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .action-card.register .action-icon {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .action-card.reserve .action-icon {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .action-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
            margin-bottom: 12px;
        }

        .action-desc {
            color: var(--text-muted);
            font-size: 0.96rem;
            line-height: 1.65;
            margin-bottom: 0;
        }

        .action-card>* {
            position: relative;
            z-index: 1;
        }

        .action-card-highlight {
            border-color: rgba(255, 107, 53, 0.45);
            background: linear-gradient(180deg, rgba(255, 129, 76, 0.18), rgba(255, 255, 255, 0.06));
        }

        .action-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 18px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .features-section {
            padding-top: 8px;
        }

        .features-list {
            display: flex;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .feature-item {
            padding: 12px 16px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.76);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .feature-item i {
            color: var(--primary);
        }

        .footer-text {
            padding-top: 24px;
            text-align: center;
            color: rgba(255, 255, 255, 0.13);
            font-size: 0.7rem;
            letter-spacing: 0.04em;
            user-select: none;
        }

        .login-modal .modal-content {
            background:
                radial-gradient(circle at top right, rgba(247, 147, 30, 0.24), transparent 28%),
                radial-gradient(circle at top left, rgba(255, 107, 53, 0.16), transparent 24%),
                linear-gradient(180deg, rgba(25, 29, 45, 0.98) 0%, rgba(13, 16, 27, 0.98) 100%);
            backdrop-filter: blur(22px);
            border-radius: 28px;
            border: 1px solid var(--surface-border);
            box-shadow: 0 32px 90px rgba(0, 0, 0, 0.42);
            overflow: hidden;
        }

        .login-modal .modal-dialog {
            max-width: 560px;
        }

        .login-modal .modal-header {
            border-bottom: none;
            padding: 26px 28px 10px;
            position: relative;
            z-index: 1;
        }

        .login-modal .modal-body {
            padding: 4px 28px 30px;
            position: relative;
            z-index: 1;
        }

        .login-modal .modal-title {
            color: #fff;
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.03em;
        }

        .modal-title-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            background: linear-gradient(135deg, rgba(255, 107, 53, 0.22), rgba(247, 147, 30, 0.16));
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: var(--primary);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .login-modal .btn-close {
            filter: invert(1) grayscale(1) brightness(200%);
            opacity: 0.78;
        }

        .login-modal .btn-close:hover {
            opacity: 1;
        }

        .modal-copy {
            color: var(--text-soft);
            text-align: center;
            margin-bottom: 22px;
            font-size: 0.96rem;
            line-height: 1.6;
        }

        .login-modal .form-label {
            color: rgba(255, 255, 255, 0.82);
            font-weight: 500;
            font-size: 0.92rem;
        }

        .form-control {
            border-radius: 16px;
            padding: 13px 16px;
            border: 1px solid var(--surface-border);
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.16);
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.36);
        }

        .btn-login {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 16px;
            padding: 13px 30px;
            font-weight: 600;
            width: 100%;
            box-shadow: 0 18px 30px rgba(32, 201, 151, 0.22);
            transition: transform 0.25s ease, box-shadow 0.25s ease, opacity 0.25s ease;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #20c997, #28a745);
            transform: translateY(-1px);
            box-shadow: 0 22px 34px rgba(32, 201, 151, 0.26);
        }

        .btn-recovery {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 16px;
            padding: 13px 30px;
            font-weight: 600;
            width: 100%;
            color: #fff;
            box-shadow: 0 20px 36px rgba(255, 107, 53, 0.24);
            transition: transform 0.25s ease, box-shadow 0.25s ease, opacity 0.25s ease;
        }

        .btn-recovery:hover {
            transform: translateY(-1px);
            box-shadow: 0 24px 40px rgba(255, 107, 53, 0.28);
            color: #fff;
        }

        .forgot-link {
            color: #ffb185;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .login-modal .alert {
            border-radius: 16px;
            border: 1px solid transparent;
            font-size: 0.92rem;
        }

        .login-modal .alert-danger {
            background: rgba(220, 53, 69, 0.14);
            color: #ffd7dd;
            border-color: rgba(220, 53, 69, 0.2);
        }

        .login-modal .alert-warning {
            background: rgba(255, 193, 7, 0.14);
            color: #ffe39b;
            border-color: rgba(255, 193, 7, 0.22);
        }

        .login-modal .alert-success {
            background: rgba(40, 167, 69, 0.14);
            color: #baf4c9;
            border-color: rgba(40, 167, 69, 0.22);
        }

        .recovery-success-state {
            text-align: center;
            padding: 6px 0 4px;
        }

        .recovery-status-icon {
            width: 88px;
            height: 88px;
            margin: 0 auto 18px;
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            font-size: 2rem;
            box-shadow: 0 22px 44px rgba(255, 107, 53, 0.26);
        }

        .recovery-success-state h5 {
            color: #fff;
            font-size: 1.8rem;
            margin-bottom: 12px;
        }

        .recovery-success-state p {
            color: var(--text-soft);
            margin-bottom: 10px;
        }

        .recovery-countdown-card {
            margin: 24px 0 18px;
            padding: 18px 20px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .recovery-countdown-label {
            display: block;
            margin-bottom: 12px;
            color: rgba(255, 255, 255, 0.66);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.76rem;
            font-weight: 600;
        }

        #countdownTimer {
            display: block;
            font-size: 3.3rem;
            line-height: 1;
            font-weight: 700;
            letter-spacing: -0.04em;
            background: linear-gradient(135deg, #fff, #ffb185);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .recovery-countdown-unit {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.92rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .recovery-divider {
            border-color: rgba(255, 255, 255, 0.08);
            margin: 18px 0;
        }

        .recovery-note {
            color: #f5f7fb;
            font-size: 1rem;
            line-height: 1.7;
        }

        @media (max-width: 992px) {
            .hero-section {
                min-height: 100dvh;
                padding: 24px 16px 28px;
            }

            .hero-section::before {
                width: 180%;
                height: 180%;
                top: -35%;
                left: -35%;
            }

            .hero-content {
                width: 100%;
            }

            .hero-shell {
                padding: 28px 22px;
                border-radius: 28px;
            }

            .main-title {
                font-size: clamp(2rem, 7vw, 2.7rem);
                line-height: 1.05;
            }

            .subtitle {
                font-size: 1rem;
                margin-bottom: 30px;
            }

            .action-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 20px;
            }

            .action-card {
                padding: 28px 20px;
            }

            .features-section {
                padding-top: 4px;
            }

            .features-list {
                gap: 12px 16px;
            }

            .footer-text {
                padding-top: 20px;
            }
        }

        @media (max-width: 768px) {
            .hero-section {
                padding: 18px 12px 24px;
            }

            .hero-shell {
                padding: 24px 18px;
                border-radius: 24px;
            }

            .hero-badge {
                font-size: 0.82rem;
                padding: 9px 14px;
                margin-bottom: 16px;
            }

            .brand-logo {
                width: 92px;
                height: 92px;
                font-size: 44px;
                margin-bottom: 24px;
            }

            .main-title {
                font-size: clamp(1.9rem, 6.4vw, 2.5rem);
            }

            .subtitle {
                font-size: 0.98rem;
                margin-bottom: 26px;
            }

            .action-cards {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .action-card {
                padding: 24px 18px;
            }

            .action-icon {
                width: 74px;
                height: 74px;
                font-size: 34px;
                margin-bottom: 18px;
            }

            .action-title {
                font-size: 1.35rem;
            }

            .features-section {
                padding-top: 2px;
            }

            .features-list {
                justify-content: stretch;
                gap: 10px;
            }

            .feature-item {
                width: calc(50% - 5px);
                justify-content: center;
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            .hero-content.container {
                max-width: 100%;
                padding-left: 0;
                padding-right: 0;
            }

            .hero-section {
                padding-top: 14px;
                padding-bottom: 18px;
            }

            .hero-shell {
                padding: 22px 14px 18px;
                border-radius: 22px;
            }

            .hero-badge {
                width: 100%;
                justify-content: center;
                margin-bottom: 14px;
            }

            .brand-logo {
                width: 78px;
                height: 78px;
                font-size: 36px;
                margin-bottom: 16px;
            }

            .main-title {
                font-size: 1.72rem;
                margin-bottom: 10px;
            }

            .subtitle {
                margin-bottom: 22px;
                font-size: 0.98rem;
                line-height: 1.6;
                color: rgba(255, 255, 255, 0.82);
            }

            .action-cards {
                gap: 18px;
            }

            .action-card {
                padding: 20px 16px;
                border-radius: 18px;
                box-shadow: 0 14px 30px rgba(0, 0, 0, 0.18);
                transition: box-shadow 0.2s, border 0.2s, background 0.2s;
                min-height: 160px;
            }

            .action-card.reserve {
                min-height: auto;
                justify-content: flex-start;
                padding: 18px 16px 16px;
                gap: 8px;
            }

            .action-desc {
                font-size: 0.92rem;
                line-height: 1.5;
                margin-bottom: 8px;
            }

            .action-tag {
                margin-top: 12px;
                white-space: normal;
                text-align: center;
            }

            .action-card:active {
                background: rgba(255, 255, 255, 0.18);
                box-shadow: 0 8px 18px rgba(0, 0, 0, 0.16);
                border-color: var(--primary);
            }

            .action-icon {
                width: 62px;
                height: 62px;
                font-size: 28px;
                margin-bottom: 14px;
            }

            .action-title {
                font-size: 1.16rem;
                margin-bottom: 8px;
            }

            .action-desc {
                font-size: 0.94rem;
                line-height: 1.5;
            }

            .action-tag {
                margin-top: 14px;
                font-size: 0.72rem;
            }

            .features-section {
                padding-top: 2px;
            }

            .features-list {
                gap: 10px;
                flex-direction: column;
                align-items: stretch;
            }

            .feature-item {
                width: 100%;
                font-size: 0.88rem;
                justify-content: center;
                text-align: center;
            }

            .footer-text {
                font-size: 0.76rem;
                padding-top: 18px;
                color: rgba(255, 255, 255, 0.48);
            }

            .login-modal .modal-dialog {
                margin: 10px;
            }

            .login-modal .modal-header {
                padding: 22px 16px 10px;
            }

            .login-modal .modal-body {
                padding: 6px 16px 18px;
            }

            .login-modal .modal-title {
                font-size: 1.55rem;
            }

            .modal-title-icon {
                width: 44px;
                height: 44px;
                margin-right: 10px;
            }

            .recovery-success-state h5 {
                font-size: 1.55rem;
            }

            #countdownTimer {
                font-size: 2.9rem;
            }
        }

        @media (max-width: 420px) {
            .hero-shell {
                padding: 18px 12px 16px;
                border-radius: 18px;
            }

            .brand-logo {
                width: 68px;
                height: 68px;
                font-size: 32px;
                margin-bottom: 12px;
            }

            .main-title {
                font-size: 1.55rem;
            }

            .subtitle {
                font-size: 0.92rem;
                margin-bottom: 18px;
            }

            .action-cards {
                gap: 14px;
            }

            .action-card {
                padding: 16px 14px;
                min-height: 140px;
                gap: 8px;
            }

            .action-icon {
                width: 54px;
                height: 54px;
                font-size: 24px;
                margin-bottom: 10px;
            }

            .action-title {
                font-size: 1.05rem;
            }

            .action-desc {
                font-size: 0.88rem;
            }

            .action-tag {
                font-size: 0.68rem;
                padding: 6px 10px;
            }
        }
    </style>
</head>

<body>

    <div class="hero-section">
        <div class="container hero-content">
            <div class="hero-shell text-center">
                <div class="hero-badge">
                    <i class="fas fa-shield-alt"></i>
                    <span>Plataforma moderna para operar o seu restaurante</span>
                </div>
                <div class="brand-logo">
                    <i class="fas fa-utensils"></i>
                </div>
                <h1 class="main-title">EDVISTRO</h1>
                <p class="subtitle">Controle reservas, vendas e operação diária com uma experiência clara, rápida e profissional em desktop e mobile.</p>

                <div class="action-cards">
                    <a href="#" class="action-card login" data-bs-toggle="modal" data-bs-target="#modalLogin">
                        <div class="action-icon">
                            <i class="fas fa-right-to-bracket"></i>
                        </div>
                        <h3 class="action-title">Entrar</h3>
                        <p class="action-desc">Acesse sua conta</p>
                    </a>

                    <a href="solicitar_plano.php" class="action-card register">
                        <div class="action-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h3 class="action-title">Cadastrar</h3>
                        <p class="action-desc">Crie a conta do restaurante e comece com uma estrutura pronta para crescer.</p>
                    </a>

                    <a href="fazer_reserva.php" class="action-card reserve action-card-highlight">
                        <div class="action-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 class="action-title">Reservar Mesa</h3>
                        <p class="action-desc">Ofereça uma jornada simples para o cliente reservar online em poucos toques.</p>
                        <span class="action-tag"><i class="fas fa-star"></i> Experiência rápida</span>
                    </a>
                </div>

                <div class="features-section">
                    <div class="features-list">
                        <div class="feature-item"><i class="fas fa-check-circle"></i><span>Gestão de Produtos</span></div>
                        <div class="feature-item"><i class="fas fa-check-circle"></i><span>Controle de Caixa</span></div>
                        <div class="feature-item"><i class="fas fa-check-circle"></i><span>Pedidos Online</span></div>
                        <div class="feature-item"><i class="fas fa-check-circle"></i><span>Relatórios</span></div>
                        <div class="feature-item"><i class="fas fa-check-circle"></i><span>QR Code</span></div>
                    </div>
                </div>

                <div class="footer-text">
                    &copy; <?php echo date('Y'); ?> RestauranteSaaS. Todos os direitos reservados.
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Login -->
    <div class="modal fade login-modal" id="modalLogin" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-center w-100">
                        <span class="modal-title-icon"><i class="fas fa-utensils"></i></span>
                        <strong>RestauranteSaaS</strong>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="modal-copy">Entre no painel com a mesma identidade visual premium do sistema e gerencie sua operação com segurança.</p>
                    <form id="formLogin">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="seu@email.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Senha</label>
                            <input type="password" name="senha" class="form-control" placeholder="••••••••" required>
                        </div>
                        <div id="loginAlert" class="alert" style="display: none;"></div>
                        <button type="submit" class="btn btn-login text-white">
                            <i class="fas fa-sign-in-alt me-2"></i>Entrar
                        </button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="#" class="forgot-link" data-bs-toggle="modal" data-bs-target="#modalEsqueciSenha">Esqueceu a senha?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Esqueci a Senha -->
    <div class="modal fade login-modal" id="modalEsqueciSenha" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-center w-100">
                        <span class="modal-title-icon"><i class="fas fa-key"></i></span>
                        <strong>Recuperar Senha</strong>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="modal-copy">Informe o email da conta para receber um link seguro de redefinição e recuperar o acesso ao restaurante.</p>
                    <!-- Formulário de email (mostra primeiro) -->
                    <div id="emailForm">
                        <form id="formEsqueciSenha">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" id="emailRecuperacao" class="form-control" placeholder="seu@email.com" required>
                            </div>
                            <div id="esqueciAlert" class="alert" style="display: none;"></div>
                            <button type="submit" class="btn btn-recovery" id="btnEnviarLink">
                                <i class="fas fa-paper-plane me-2"></i>Enviar Link de Recuperação
                            </button>
                        </form>
                    </div>

                    <!-- Timer (mostra depois de enviar) -->
                    <div id="timerContainer" class="recovery-success-state" style="display: none;">
                        <div class="recovery-status-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h5>Email Enviado!</h5>
                        <p>Se o email estiver cadastrado, enviamos um link de recuperação.</p>
                        <p>Verifique também a pasta de spam para evitar atrasos.</p>
                        <div class="recovery-countdown-card">
                            <span class="recovery-countdown-label">Nova solicitação disponível em</span>
                            <span id="countdownTimer">60</span>
                            <span class="recovery-countdown-unit">segundos</span>
                        </div>
                        <hr class="recovery-divider">
                        <p class="recovery-note" id="recoveryHint">Use o link recebido por email para redefinir a senha com segurança.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Exibir mensagem de sessão ao carregar a página (para fluxo de recuperação de senha)
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['mensagem_login'])): ?>
                // Abrir modal de login e exibir mensagem
                var loginModal = new bootstrap.Modal(document.getElementById('modalLogin'));
                var alertDiv = document.getElementById('loginAlert');
                alertDiv.className = 'alert alert-warning';
                alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i><?php echo addslashes($_SESSION['mensagem_login']); ?>';
                alertDiv.style.display = 'block';
                loginModal.show();
                <?php unset($_SESSION['mensagem_login']); ?>
            <?php endif; ?>
        });

        let recoveryTimer = null;

        // Função para iniciar o timer após enviar o email
        function startRecoveryTimer() {
            let seconds = 60;
            const countdownElement = document.getElementById('countdownTimer');
            const timerContainer = document.getElementById('timerContainer');
            const emailForm = document.getElementById('emailForm');
            const recoveryHint = document.getElementById('recoveryHint');

            recoveryHint.textContent = 'Use o link recebido por email para redefinir a senha com segurança.';

            // Ocultar formulário de email e mostrar timer
            emailForm.style.display = 'none';
            timerContainer.style.display = 'block';

            recoveryTimer = setInterval(function() {
                seconds--;
                countdownElement.textContent = seconds;

                if (seconds <= 0) {
                    clearInterval(recoveryTimer);
                    // Voltar ao formulário de email
                    timerContainer.style.display = 'none';
                    emailForm.style.display = 'block';
                    document.getElementById('formEsqueciSenha').reset();
                    recoveryHint.textContent = 'Use o link recebido por email para redefinir a senha com segurança.';
                    // Re-activar botão para nova tentativa
                    var btnReativar = document.getElementById('btnEnviarLink');
                    if (btnReativar) {
                        btnReativar.disabled = false;
                        btnReativar.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Enviar Link de Recuperação';
                    }
                }
            }, 1000);
        }

        // Limpar estado quando o modal é fechado
        document.getElementById('modalEsqueciSenha').addEventListener('hidden.bs.modal', function() {
            if (recoveryTimer) {
                clearInterval(recoveryTimer);
                recoveryTimer = null;
            }
            // Resetar visualização
            document.getElementById('emailForm').style.display = 'block';
            document.getElementById('timerContainer').style.display = 'none';
            document.getElementById('countdownTimer').textContent = '60';
            document.getElementById('formEsqueciSenha').reset();
            document.getElementById('esqueciAlert').style.display = 'none';
            document.getElementById('recoveryHint').textContent = 'Use o link recebido por email para redefinir a senha com segurança.';
            // Garantir que o botão está sempre desbloqueado ao fechar/reabrir o modal
            var btnReset = document.getElementById('btnEnviarLink');
            if (btnReset) {
                btnReset.disabled = false;
                btnReset.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Enviar Link de Recuperação';
            }
        });

        // Enviar email de recuperação
        document.getElementById('formEsqueciSenha').addEventListener('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            var alertDiv = document.getElementById('esqueciAlert');
            var btn = document.getElementById('btnEnviarLink');
            var email = document.getElementById('emailRecuperacao').value;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';

            // Usar a API correta para enviar email de recuperação
            fetch('api/esqueci_senha.php', {
                    method: 'POST',
                    body: formData
                })
                .then(async (response) => {
                    const raw = await response.text();
                    try {
                        return JSON.parse(raw);
                    } catch (e) {
                        throw new Error('Resposta inválida do servidor. Verifique o log do PHP para detalhes.');
                    }
                })
                .then(data => {
                    if (data.success) {
                        // Email enviado com sucesso - iniciar timer
                        startRecoveryTimer();
                    } else {
                        alertDiv.className = 'alert alert-danger';
                        alertDiv.textContent = data.message || 'Erro ao enviar email';
                        alertDiv.style.display = 'block';
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Enviar Link de Recuperação';
                    }
                })
                .catch(err => {
                    alertDiv.className = 'alert alert-danger';
                    alertDiv.textContent = 'Não foi possível enviar o link de recuperação. Tente novamente.';
                    alertDiv.style.display = 'block';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Enviar Link de Recuperação';
                });
        });

        // Login form submission
        document.getElementById('formLogin').addEventListener('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            var alertDiv = document.getElementById('loginAlert');

            fetch('api/login_process.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Erro na conexão: ' + response.status);
                    }
                    return response.text();
                })
                .then(function(text) {
                    if (!text || text.trim() === '') {
                        throw new Error('Resposta vazia do servidor');
                    }
                    var data = JSON.parse(text);
                    if (data.success) {
                        alertDiv.className = 'alert alert-success';
                        alertDiv.textContent = 'Login realizado com sucesso! Redirecionando...';
                        alertDiv.style.display = 'block';
                        setTimeout(function() {
                            var redirectUrl = data.redirect || 'dashboard.php';
                            window.location.href = redirectUrl;
                        }, 1000);
                    } else {
                        alertDiv.className = 'alert alert-danger';
                        alertDiv.textContent = data.message || 'Erro ao fazer login';
                        alertDiv.style.display = 'block';
                    }
                })
                .catch(function(err) {
                    alertDiv.className = 'alert alert-danger';
                    alertDiv.textContent = 'Não foi possível processar o login. Tente novamente.';
                    alertDiv.style.display = 'block';
                });
        });
    </script>
</body>

</html>