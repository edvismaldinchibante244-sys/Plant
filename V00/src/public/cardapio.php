<?php

/*
 
   CARDÁPIO DIGITAL PREMIUM - PÁGINA PÚBLICA
   Design profissional estilo restaurantes gourmet
 
 */

$rid      = (int)($_GET['rid'] ?? 0);
$mesa_id  = (int)($_GET['mesa_id'] ?? ($_GET['mesa'] ?? 0));

if (!$rid) {
    http_response_code(404);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Erro</title></head><body style="font-family:sans-serif;text-align:center;padding:60px;color:#666"><h2>QR Code inválido</h2><p>Por favor, escaneie o QR Code correto.</p></body></html>');
}

include_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Buscar restaurante
$stmt = $db->prepare("SELECT * FROM restaurantes WHERE id = :id LIMIT 1");
$stmt->bindParam(':id', $rid);
$stmt->execute();
$restaurante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$restaurante) {
    http_response_code(404);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Erro</title></head><body style="font-family:sans-serif;text-align:center;padding:60px;color:#666"><h2>Restaurante não encontrado</h2></body></html>');
}

// Buscar mesa
$mesa = null;
if ($mesa_id) {
    $stmt = $db->prepare("SELECT * FROM mesas WHERE id = :id AND restaurante_id = :rid LIMIT 1");
    $stmt->bindParam(':id',  $mesa_id);
    $stmt->bindParam(':rid', $rid);
    $stmt->execute();
    $mesa = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Buscar categorias com produtos
$stmt = $db->prepare("SELECT * FROM categorias WHERE restaurante_id = :rid ORDER BY nome ASC");
$stmt->bindParam(':rid', $rid);
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar produtos ativos
$stmt = $db->prepare("
    SELECT p.*, c.nome AS categoria_nome
    FROM produtos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.restaurante_id = :rid AND p.ativo = 1
    ORDER BY c.nome ASC, p.nome ASC
");
$stmt->bindParam(':rid', $rid);
$stmt->execute();
$todos_produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por categoria
$por_categoria = [];
foreach ($todos_produtos as $p) {
    $cat = $p['categoria_nome'] ?? 'Outros';
    $por_categoria[$cat][] = $p;
}

// Base URL para imagens — aponta para src/public onde as imagens são salvas
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$base_url = rtrim($protocol . $host . $script_dir, '/');

$cat_emojis = [
    'Entradas'   => '🥗',
    'Pratos'     => '🍽️',
    'Principais' => '🍖',
    'Bebidas'    => '🥤',
    'Sobremesas' => '🍰',
    'Lanches'    => '🍔',
    'Pizzas'     => '🍕',
    'Massas'     => '🍝',
    'Grelhados'  => '🥩',
    'Frutos do Mar' => '🦐',
    'Vegetariano' => '🥦',
    'Outros'  => '🍴',
];
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1a1a2e">
    <title><?php echo htmlspecialchars($restaurante['nome']); ?> - Cardápio</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #FF6B35;
            --primary-dark: #e55a2b;
            --secondary: #F7931E;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #0f0f23;
            --dark-2: #1a1a2e;
            --dark-light: #16213e;
            --text: #1e293b;
            --text-light: #64748b;
            --text-muted: #94a3b8;
            --bg: #f8fafc;
            --white: #ffffff;
            --border: #e2e8f0;
            --gold: #D4AF37;
            --gold-light: #F4E4BA;
            --gradient: linear-gradient(135deg, #FF6B35 0%, #F7931E 100%);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
            --radius: 16px;
            --radius-sm: 8px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding-bottom: 100px;
            background-image:
                radial-gradient(ellipse at top left, rgba(212, 175, 55, 0.05) 0%, transparent 50%),
                radial-gradient(ellipse at bottom right, rgba(255, 107, 53, 0.03) 0%, transparent 50%);
            margin: 0;
            width: 100%;
        }

        html {
            width: 100%;
        }

        .hero,
        .search-container,
        .categories,
        #productsContainer,
        .category-section {
            width: 100%;
            max-width: none;
            margin-left: 0;
            margin-right: 0;
        }

        /* ===== HERO HEADER ===== */
        .hero {
            background: linear-gradient(135deg, var(--dark) 0%, var(--dark-light) 50%, #1e3a5f 100%);
            color: white;
            padding: 50px 24px 60px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(circle at 20% 80%, rgba(212, 175, 55, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 80% 20%, rgba(255, 107, 53, 0.1) 0%, transparent 40%);
            pointer-events: none;
        }

        .hero::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(to top, var(--bg), transparent);
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .hero-logo {
            font-size: 56px;
            margin-bottom: 16px;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            text-shadow: 0 2px 20px rgba(0, 0, 0, 0.3);
        }

        .hero-subtitle {
            font-size: 15px;
            opacity: 0.85;
            font-weight: 400;
        }

        .hero-divider {
            width: 60px;
            height: 3px;
            background: var(--gold);
            margin: 24px auto 0;
            border-radius: 2px;
        }

        /* ===== SEARCH BAR ===== */
        .search-container {
            padding: 0 20px;
            margin-top: -30px;
            position: relative;
            z-index: 10;
        }

        .search-box {
            background: white;
            border-radius: 50px;
            padding: 6px;
            display: flex;
            align-items: center;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .search-box input {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 50px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            outline: none;
            background: transparent;
        }

        .search-box input::placeholder {
            color: var(--text-muted);
        }

        .search-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--gradient);
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .search-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.4);
        }

        /* ===== CATEGORY TABS ===== */
        .categories {
            padding: 24px 20px 16px;
            display: flex;
            gap: 10px;
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .categories::-webkit-scrollbar {
            display: none;
        }

        .cat-pill {
            padding: 10px 22px;
            border-radius: 30px;
            border: 2px solid var(--border);
            background: white;
            color: var(--text-light);
            font-size: 13px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.3s;
            flex-shrink: 0;
        }

        .cat-pill:hover {
            border-color: var(--gold);
            color: var(--gold);
        }

        .cat-pill.active {
            background: var(--dark);
            color: white;
            border-color: var(--dark);
            box-shadow: 0 4px 15px rgba(26, 26, 46, 0.3);
        }

        /* ===== CATEGORY SECTIONS ===== */
        .category-section {
            padding: 8px 20px 24px;
        }

        .category-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border);
        }

        .category-emoji {
            font-size: 28px;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gold-light);
            border-radius: 12px;
        }

        .category-title {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
        }

        .category-count {
            margin-left: auto;
            background: var(--gold-light);
            color: var(--dark);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* ===== PRODUCT GRID ===== */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: 1px solid transparent;
        }

        .product-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: var(--gold);
        }

        .product-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            position: relative;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }

        .product-card:hover .product-image img {
            transform: scale(1.08);
        }

        /* Highlight: Photo is the main focus */
        .product-image::after {
            content: '📷';
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .product-card:hover .product-image::after {
            opacity: 1;
        }

        .product-image .emoji-fallback {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
            background: linear-gradient(135deg, #fff3e0, #ffe0b2);
        }

        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--primary);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-details {
            padding: 16px;
        }

        .product-name {
            font-weight: 700;
            font-size: 15px;
            color: var(--dark);
            margin-bottom: 6px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-desc {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 10px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 8px;
        }

        .product-price {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
        }

        .product-add {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--gradient);
            border: none;
            color: white;
            font-size: 22px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }

        .product-add:hover {
            transform: scale(1.15);
        }

        .product-add.added {
            background: var(--success);
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-light);
        }

        .empty-state .icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .empty-state p {
            font-size: 15px;
        }

        /* ===== FLOATING CART ===== */
        .cart-float {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            box-shadow: 0 8px 30px rgba(26, 26, 46, 0.4);
            display: none;
            align-items: center;
            gap: 12px;
            z-index: 200;
            transition: all 0.3s;
            border: 2px solid var(--gold);
        }

        .cart-float.visible {
            display: flex;
            animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }

        .cart-float:hover {
            transform: translateX(-50%) translateY(-3px);
            box-shadow: 0 12px 40px rgba(26, 26, 46, 0.5);
        }

        .cart-icon {
            font-size: 20px;
        }

        .cart-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .cart-label {
            font-size: 11px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .cart-total {
            font-size: 16px;
            font-weight: 700;
            color: var(--gold);
        }

        .cart-count {
            background: var(--gold);
            color: var(--dark);
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 800;
        }

        /* ===== CART MODAL ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 300;
            backdrop-filter: blur(4px);
        }

        .modal-overlay.open {
            display: block;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .cart-modal {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-radius: 24px 24px 0 0;
            max-height: 85vh;
            overflow-y: auto;
            z-index: 301;
            transform: translateY(100%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 -10px 60px rgba(0, 0, 0, 0.2);
        }

        .cart-modal.open {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            background: white;
            z-index: 1;
        }

        .modal-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            font-weight: 600;
        }

        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--bg);
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: #fee;
            color: red;
        }

        .cart-items {
            padding: 20px 24px;
        }

        .cart-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-img {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            overflow: hidden;
            background: var(--bg);
            flex-shrink: 0;
        }

        .cart-item-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cart-item-info {
            flex: 1;
        }

        .cart-item-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .cart-item-price {
            color: var(--text-light);
            font-size: 13px;
        }

        .cart-item-qty {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .qty-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid var(--border);
            background: white;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .qty-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .qty-value {
            font-weight: 700;
            font-size: 15px;
            min-width: 24px;
            text-align: center;
        }

        .cart-item-subtotal {
            font-weight: 700;
            font-size: 15px;
            color: var(--primary);
            min-width: 80px;
            text-align: right;
        }

        .cart-summary {
            padding: 20px 24px;
            background: var(--bg);
            border-top: 2px solid var(--border);
        }

        .cart-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .cart-total-label {
            font-size: 18px;
            font-weight: 600;
        }

        .cart-total-value {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
        }

        .order-form {
            margin-top: 8px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 6px;
            color: var(--text);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }

        .form-group textarea {
            resize: none;
            height: 70px;
        }

        .btn-order {
            width: 100%;
            padding: 18px;
            background: var(--gradient);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-order:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25, 107, 53px rgba(255, 0.4);
        }

        .btn-order:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .empty-cart {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
        }

        .empty-cart .icon {
            font-size: 60px;
            margin-bottom: 16px;
        }

        /* ===== SUCCESS SCREEN ===== */
        .success-screen {
            display: none;
            text-align: center;
            padding: 40px 24px 60px;
        }

        .success-screen.show {
            display: block;
        }

        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: bounceIn 0.6s;
        }

        @keyframes bounceIn {
            0% {
                transform: scale(0);
            }

            60% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
            }
        }

        .success-screen h2 {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--success);
        }

        .success-number {
            display: inline-block;
            background: linear-gradient(135deg, var(--gold-light), #f0e6c8);
            color: var(--dark);
            padding: 10px 28px;
            border-radius: 30px;
            font-size: 18px;
            font-weight: 700;
            margin: 16px 0;
        }

        .success-screen p {
            color: var(--text-light);
            font-size: 15px;
            line-height: 1.6;
        }

        .btn-new {
            margin-top: 30px;
            padding: 16px 36px;
            background: var(--dark);
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-new:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(26, 26, 46, 0.3);
        }

        /* ===== FOOTER ===== */
        .footer {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-muted);
            font-size: 12px;
        }

        .footer-brand {
            font-weight: 600;
            color: var(--text-light);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 600px) {
            .hero {
                padding: 36px 16px 44px;
            }

            .hero h1 {
                font-size: 28px;
            }

            .search-container {
                padding: 0 16px;
            }

            .categories {
                padding: 18px 16px 12px;
            }

            .category-section {
                padding: 8px 16px 20px;
            }

            .category-header {
                flex-wrap: wrap;
                gap: 10px;
            }

            .category-count {
                margin-left: 0;
            }

            .products-grid {
                grid-template-columns: 1fr;
            }

            .product-image {
                height: 180px;
            }

            .cart-float {
                width: calc(100% - 32px);
                left: 16px;
                transform: none;
                justify-content: space-between;
            }
        }

        @media (max-width: 480px) {
            .hero h1 {
                font-size: 24px;
            }

            .hero-subtitle {
                font-size: 14px;
            }

            .cat-pill {
                padding: 8px 16px;
                font-size: 12px;
            }

            .product-image {
                height: 160px;
            }
        }

        @media (max-width: 380px) {
            .products-grid {
                grid-template-columns: 1fr;
            }

            .hero h1 {
                font-size: 26px;
            }
        }

        /* ===== IMAGE VIEWER MODAL ===== */
        .image-viewer {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.95);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 20px;
        }

        .image-viewer.open {
            display: flex;
            animation: fadeIn 0.3s;
        }

        .image-viewer-close {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .image-viewer-close:hover {
            background: var(--primary);
            transform: scale(1.1);
        }

        .image-viewer img {
            max-width: 90%;
            max-height: 70vh;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            object-fit: contain;
        }

        .image-viewer-info {
            text-align: center;
            margin-top: 24px;
            color: white;
        }

        .image-viewer-name {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .image-viewer-price {
            font-size: 24px;
            color: var(--gold);
            font-weight: 600;
        }

        .image-viewer-add {
            margin-top: 20px;
            padding: 14px 36px;
            background: var(--gradient);
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .image-viewer-add:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(255, 107, 53, 0.4);
        }
    </style>
</head>

<body>

    <!-- HERO HEADER -->
    <div class="hero">
        <div class="hero-badge">
            <span>🍽️</span>
            <span><?php echo $mesa ? 'Mesa ' . htmlspecialchars($mesa['numero']) : 'Cardápio Digital'; ?></span>
        </div>
        <div class="hero-logo"><?php echo $restaurante['nome']; ?></div>
        <h1><?php echo htmlspecialchars($restaurante['nome']); ?></h1>
        <p class="hero-subtitle">Sabores incomparáveis, experiências únicas</p>
        <div class="hero-divider"></div>
    </div>

    <!-- SEARCH -->
    <div class="search-container">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Buscar pratos..." oninput="filterProducts()">
            <button class="search-btn">🔍</button>
        </div>
    </div>

    <!-- CATEGORIES -->
    <?php if (!empty($por_categoria)): ?>
        <div class="categories" id="categories">
            <button class="cat-pill active" onclick="filterCategory('all', this)">🍽️ Tudo</button>
            <?php foreach (array_keys($por_categoria) as $cat_nome): ?>
                <button class="cat-pill" onclick="filterCategory('<?php echo htmlspecialchars(addslashes($cat_nome)); ?>', this)">
                    <?php echo ($cat_emojis[$cat_nome] ?? '🍴') . ' ' . htmlspecialchars($cat_nome); ?>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- PRODUCTS -->
    <div id="productsContainer">
        <?php if (empty($todos_produtos)): ?>
            <div class="empty-state">
                <div class="icon">🍽️</div>
                <h3>Cardápio em Preparação</h3>
                <p>Em breve teremos novidades deliciosas para você!</p>
            </div>
        <?php else: ?>
            <?php foreach ($por_categoria as $cat_nome => $produtos): ?>
                <div class="category-section" data-category="<?php echo htmlspecialchars($cat_nome); ?>">
                    <div class="category-header">
                        <div class="category-emoji"><?php echo $cat_emojis[$cat_nome] ?? '🍴'; ?></div>
                        <h2 class="category-title"><?php echo htmlspecialchars($cat_nome); ?></h2>
                        <span class="category-count"><?php echo count($produtos); ?> itens</span>
                    </div>
                    <div class="products-grid">
                        <?php foreach ($produtos as $p): ?>
                            <?php
                            $produto_nome_attr = htmlspecialchars($p['nome'] ?? '', ENT_QUOTES, 'UTF-8');
                            $produto_nome_js = htmlspecialchars(addslashes($p['nome'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $produto_imagem_js = htmlspecialchars(addslashes($p['imagem'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $produto_imagem_src = htmlspecialchars($base_url . '/' . ($p['imagem'] ?? ''), ENT_QUOTES, 'UTF-8');
                            ?>
                            <div class="product-card" data-name="<?php echo htmlspecialchars(strtolower($p['nome'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                onclick="addToCart(<?php echo $p['id']; ?>, '<?php echo $produto_nome_js; ?>', <?php echo $p['preco']; ?>, '<?php echo $produto_imagem_js; ?>')">
                                <div class="product-image">
                                    <?php if (!empty($p['imagem'])): ?>
                                        <img src="<?php echo $produto_imagem_src; ?>" alt="<?php echo $produto_nome_attr; ?>">
                                    <?php else: ?>
                                        <?php
                                        // Gerar avatar automático baseado no nome do produto
                                        $cores = ['FF6B35', 'F7931E', '10b981', '3b82f6', '8b5cf6', 'ec4899'];
                                        $cor = $cores[array_rand($cores)];
                                        $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($p['nome']) . "&background=" . $cor . "&color=ffffff&size=256&bold=true";
                                        $avatar_url_safe = htmlspecialchars($avatar_url, ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <img src="<?php echo $avatar_url_safe; ?>" alt="<?php echo $produto_nome_attr; ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="product-details">
                                    <h3 class="product-name"><?php echo $produto_nome_attr; ?></h3>
                                    <?php if (!empty($p['descricao'])): ?>
                                        <p class="product-desc"><?php echo htmlspecialchars($p['descricao']); ?></p>
                                    <?php endif; ?>
                                    <div class="product-footer">
                                        <span class="product-price"><?php echo number_format($p['preco'], 2, ',', '.'); ?> MZN</span>
                                        <button class="product-add" onclick="event.stopPropagation(); addToCart(<?php echo $p['id']; ?>, '<?php echo $produto_nome_js; ?>', <?php echo $p['preco']; ?>, '<?php echo $produto_imagem_js; ?>')">+</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        <span class="footer-brand"><?php echo htmlspecialchars($restaurante['nome']); ?></span> • Cardápio Digital Premium
    </div>

    <!-- FLOATING CART -->
    <button class="cart-float" id="cartFloat" onclick="openCart()">
        <span class="cart-icon">🛒</span>
        <div class="cart-info">
            <span class="cart-label">Carrinho</span>
            <span class="cart-total" id="cartTotalDisplay">0,00 MZN</span>
        </div>
        <span class="cart-count" id="cartCountDisplay">0</span>
    </button>

    <!-- CART MODAL -->
    <div class="modal-overlay" id="modalOverlay" onclick="closeCart()"></div>
    <div class="cart-modal" id="cartModal">
        <!-- CART SCREEN -->
        <div id="cartScreen">
            <div class="modal-header">
                <h2>🛒 Seu Pedido</h2>
                <button class="modal-close" onclick="closeCart()">✕</button>
            </div>
            <div class="cart-items" id="cartItems"></div>
            <div class="cart-summary">
                <div class="cart-total-row">
                    <span class="cart-total-label">Total</span>
                    <span class="cart-total-value" id="cartTotalValue">0,00 MZN</span>
                </div>
                <div class="order-form">
                    <div class="form-group">
                        <label>👤 Seu Nome (opcional)</label>
                        <input type="text" id="clientName" placeholder="Ex: João Silva">
                    </div>
                    <div class="form-group">
                        <label>📝 Observações</label>
                        <textarea id="orderNote" placeholder="Ex: Sem cebola, bem passado..."></textarea>
                    </div>
                    <button class="btn-order" id="btnOrder" onclick="submitOrder()">
                        ✅ Confirmar Pedido
                    </button>
                </div>
            </div>
        </div>

        <!-- SUCCESS SCREEN -->
        <div class="success-screen" id="successScreen">
            <div class="success-icon">🎉</div>
            <h2>Pedido Enviado!</h2>
            <p>Seu pedido foi recebido com sucesso.</p>
            <div class="success-number" id="successNumber">#000</div>
            <p>Aguarde, estamos preparando tudo com carinho! 😊</p>
            <button class="btn-new" onclick="newOrder()">🍽️ Novo Pedido</button>
        </div>
    </div>

    <!-- IMAGE VIEWER MODAL -->
    <div class="image-viewer" id="imageViewer">
        <button class="image-viewer-close" onclick="closeImageViewer()">✕</button>
        <img id="viewerImage" src="" alt="Produto">
        <div class="image-viewer-info">
            <div class="image-viewer-name" id="viewerName"></div>
            <div class="image-viewer-price" id="viewerPrice"></div>
            <button class="image-viewer-add" id="viewerAddBtn" onclick="addFromViewer()">
                <span>+</span> Adicionar ao Carrinho
            </button>
        </div>
    </div>

    <script>
        var cart = [];
        var restaurantId = <?php echo (int)$rid; ?>;
        var tableId = <?php echo (int)$mesa_id; ?>;
        var baseUrl = '<?php echo $base_url; ?>/';

        function addToCart(id, name, price, image) {
            var existing = cart.find(item => item.id === id);
            if (existing) {
                existing.qty++;
            } else {
                cart.push({
                    id: id,
                    name: name,
                    price: parseFloat(price),
                    qty: 1,
                    image: image
                });
            }
            updateCartUI();
            animateAddButton(id);
        }

        function animateAddButton(id) {
            var buttons = document.querySelectorAll('.product-add');
            buttons.forEach(btn => {
                var card = btn.closest('.product-card');
                if (card && card.getAttribute('onclick') && card.getAttribute('onclick').includes(',' + id + ',')) {
                    btn.classList.add('added');
                    setTimeout(() => btn.classList.remove('added'), 500);
                }
            });
        }

        function updateCartUI() {
            var total = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
            var count = cart.reduce((sum, item) => sum + item.qty, 0);

            document.getElementById('cartFloat').classList.toggle('visible', count > 0);
            document.getElementById('cartTotalDisplay').textContent = formatPrice(total);
            document.getElementById('cartCountDisplay').textContent = count;
            document.getElementById('cartTotalValue').textContent = formatPrice(total);
        }

        function renderCartItems() {
            var container = document.getElementById('cartItems');
            if (cart.length === 0) {
                container.innerHTML = '<div class="empty-cart"><div class="icon">🛒</div><p>Seu carrinho está vazio</p></div>';
                return;
            }

            var html = '';
            cart.forEach((item, index) => {
                var subtotal = item.price * item.qty;
                var imgHtml = item.image ?
                    '<img src="' + escHtml(baseUrl + item.image) + '" alt="' + escHtml(item.name) + '">' :
                    '<span style="font-size:24px;">🍴</span>';

                html += '<div class="cart-item">';
                html += '<div class="cart-item-img">' + imgHtml + '</div>';
                html += '<div class="cart-item-info">';
                html += '<div class="cart-item-name">' + escHtml(item.name) + '</div>';
                html += '<div class="cart-item-price">' + formatPrice(item.price) + '</div>';
                html += '</div>';
                html += '<div class="cart-item-qty">';
                html += '<button class="qty-btn" onclick="changeQty(' + index + ', -1)">−</button>';
                html += '<span class="qty-value">' + item.qty + '</span>';
                html += '<button class="qty-btn" onclick="changeQty(' + index + ', 1)">+</button>';
                html += '</div>';
                html += '<div class="cart-item-subtotal">' + formatPrice(subtotal) + '</div>';
                html += '</div>';
            });
            container.innerHTML = html;
        }

        function changeQty(index, delta) {
            cart[index].qty += delta;
            if (cart[index].qty <= 0) {
                cart.splice(index, 1);
            }
            updateCartUI();
            renderCartItems();
        }

        function openCart() {
            renderCartItems();
            document.getElementById('modalOverlay').classList.add('open');
            document.getElementById('cartModal').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeCart() {
            document.getElementById('modalOverlay').classList.remove('open');
            document.getElementById('cartModal').classList.remove('open');
            document.body.style.overflow = '';
        }

        function submitOrder() {
            if (cart.length === 0) {
                alert('Adicione itens ao carrinho!');
                return;
            }

            var btn = document.getElementById('btnOrder');
            btn.disabled = true;
            btn.textContent = '⏳ Enviando...';

            var total = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);

            var data = {
                rid: restaurantId,
                mesa_id: tableId,
                cliente_nome: document.getElementById('clientName').value.trim(),
                observacao: document.getElementById('orderNote').value.trim(),
                total: total,
                itens: cart
            };

            fetch('api/pedido_novo.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        document.getElementById('successNumber').textContent = '#' + result.numero_pedido;
                        document.getElementById('cartScreen').style.display = 'none';
                        document.getElementById('successScreen').classList.add('show');
                        cart = [];
                        updateCartUI();
                    } else {
                        alert(result.message || 'Erro ao enviar pedido');
                        btn.disabled = false;
                        btn.textContent = '✅ Confirmar Pedido';
                    }
                })
                .catch(() => {
                    alert('Erro de conexão');
                    btn.disabled = false;
                    btn.textContent = '✅ Confirmar Pedido';
                });
        }

        function newOrder() {
            document.getElementById('cartScreen').style.display = 'block';
            document.getElementById('successScreen').classList.remove('show');
            document.getElementById('clientName').value = '';
            document.getElementById('orderNote').value = '';
            document.getElementById('btnOrder').disabled = false;
            document.getElementById('btnOrder').textContent = '✅ Confirmar Pedido';
            closeCart();
        }

        function filterCategory(category, btn) {
            // Remove active class from all buttons
            document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
            // Add active class to clicked button
            btn.classList.add('active');

            // Get all category sections
            var sections = document.querySelectorAll('.category-section');

            sections.forEach(function(section) {
                var sectionCat = section.getAttribute('data-category');

                // Show section if it's "all" or matches the selected category
                if (category === 'all' || sectionCat === category) {
                    section.style.display = 'block';
                } else {
                    section.style.display = 'none';
                }
            });

            // Clear search input
            document.getElementById('searchInput').value = '';
        }

        function filterProducts() {
            var query = document.getElementById('searchInput').value.toLowerCase().trim();

            document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
            document.querySelector('.cat-pill').classList.add('active');

            document.querySelectorAll('.category-section').forEach(section => {
                section.style.display = '';
            });

            document.querySelectorAll('.product-card').forEach(card => {
                var name = card.dataset.name || '';
                card.style.display = (!query || name.includes(query)) ? '' : 'none';
            });
        }

        function formatPrice(value) {
            return parseFloat(value).toFixed(2).replace('.', ',') + ' MZN';
        }

        function escHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // ===== IMAGE VIEWER FUNCTIONS =====
        var viewerProduct = null;

        function openImageViewer(id, name, price, image) {
            viewerProduct = {
                id: id,
                name: name,
                price: price,
                image: image
            };

            document.getElementById('viewerImage').src = baseUrl + image;
            document.getElementById('viewerName').textContent = name;
            document.getElementById('viewerPrice').textContent = formatPrice(price);

            document.getElementById('imageViewer').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeImageViewer() {
            document.getElementById('imageViewer').classList.remove('open');
            document.body.style.overflow = '';
            viewerProduct = null;
        }

        function addFromViewer() {
            if (viewerProduct) {
                addToCart(viewerProduct.id, viewerProduct.name, viewerProduct.price, viewerProduct.image);
                closeImageViewer();
            }
        }

        // Close image viewer on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageViewer();
                closeCart();
            }
        });
    </script>
</body>

</html>
