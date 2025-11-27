<?php
// admin_ver_detalhes_pedido.php - COM EDIÇÃO DE ITENS
require_once "conexao.php";
require_once "require_login.php";

$usuario = $_SESSION['usuario'] ?? null;

if (!$usuario || (int)$usuario['idperfil'] !== 1) {
    header('Location: dashboard.php');
    exit();
}

$id_pedido = filter_input(INPUT_GET, 'id_pedido', FILTER_VALIDATE_INT);

if (!$id_pedido) {
    $_SESSION['erro'] = "Pedido não especificado.";
    header('Location: admin_lista_pedidos_finalizar.php');
    exit();
}

// Buscar informações do pedido
$sql_pedido = "
    SELECT 
        p.id_pedido,
        p.reference,
        p.data_pedido,
        p.total,
        p.status_pedido,
        u.nome,
        u.apelido,
        u.telefone AS tel_usuario,
        u.email
    FROM pedido p
    JOIN usuario u ON p.id_usuario = u.id_usuario
    WHERE p.id_pedido = ?
";

$stmt = $conexao->prepare($sql_pedido);
$stmt->bind_param("i", $id_pedido);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    $_SESSION['erro'] = "Pedido não encontrado.";
    header('Location: admin_lista_pedidos_finalizar.php');
    exit();
}

$pedido = $resultado->fetch_assoc();
$stmt->close();

// Buscar itens do pedido
$sql_itens = "
    SELECT 
        ip.id_item_pedido,
        ip.quantidade,
        ip.preco_unitario,
        ip.subtotal,
        p.nome_produto,
        (SELECT caminho_imagem FROM produto_imagem 
         WHERE id_produto = p.id_produto AND imagem_principal = 1 LIMIT 1) AS imagem_principal
    FROM item_pedido ip
    JOIN produto p ON ip.id_produto = p.id_produto
    WHERE ip.id_pedido = ?
";

$stmt_itens = $conexao->prepare($sql_itens);
$stmt_itens->bind_param("i", $id_pedido);
$stmt_itens->execute();
$resultado_itens = $stmt_itens->get_result();
$itens = [];

while ($item = $resultado_itens->fetch_assoc()) {
    $item_id = $item['id_item_pedido'];
    
    // Buscar personalizações
    $sql_pers = "
        SELECT 
            ipp.ingrediente_nome,
            ipp.tipo,
            COUNT(*) AS qtd
        FROM item_pedido_personalizacao ipp
        WHERE ipp.id_item_pedido = ?
        GROUP BY ipp.ingrediente_nome, ipp.tipo
    ";
    $stmt_pers = $conexao->prepare($sql_pers);
    $stmt_pers->bind_param("i", $item_id);
    $stmt_pers->execute();
    $resultado_pers = $stmt_pers->get_result();
    
    $personalizacoes = [];
    while ($pers = $resultado_pers->fetch_assoc()) {
        $personalizacoes[] = $pers;
    }
    $stmt_pers->close();
    
    $item['personalizacoes'] = $personalizacoes;
    $itens[] = $item;
}

$stmt_itens->close();

$pode_editar = ($pedido['status_pedido'] === 'pendente');
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Pedido #<?= $id_pedido ?></title>
    <link rel="stylesheet" href="css/cliente.css">
    <script src="js/darkmode1.js"></script>
    <style>
        .detalhes-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .pedido-header {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .pedido-header h1 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .info-item strong {
            display: block;
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .item-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .personalizacao-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.85em;
            margin: 3px;
        }
        
        .badge-extra {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-removido {
            background: #f8d7da;
            color: #721c24;
        }
        
        .total-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            text-align: right;
        }
        
        .total-section h2 {
            margin: 0;
            color: #28a745;
            font-size: 2em;
        }
        
        .btn-voltar {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        
        .btn-voltar:hover {
            background: #0056b3;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
        }
        
        .status-pendente {
            background: #ffc107;
            color: #000;
        }
        
        .status-entregue {
            background: #28a745;
            color: #fff;
        }
        
        .status-cancelado {
            background: #dc3545;
            color: #fff;
        }
        
        .btn-remover-item {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85em;
            transition: all 0.3s ease;
        }
        
        .btn-remover-item:hover {
            background: #c82333;
            transform: scale(1.05);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-erro {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>

<header class="topbar">
  <div class="container">
    <div class="logo">
      <a href="index.php">
        <img src="icones/logo.png" alt="Logo" class="logo-img">
      </a>
    </div>
    <div class="links-menu">
      <a href="admin_lista_pedidos_finalizar.php">Voltar à Lista</a>
    </div>
  </div>
</header>

<div class="detalhes-container">
    
    <?php if (isset($_SESSION['sucesso'])): ?>
        <div class="alert alert-success">
            ✅ <?= htmlspecialchars($_SESSION['sucesso']) ?>
        </div>
        <?php unset($_SESSION['sucesso']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['erro'])): ?>
        <div class="alert alert-erro">
            ❌ <?= htmlspecialchars($_SESSION['erro']) ?>
        </div>
        <?php unset($_SESSION['erro']); ?>
    <?php endif; ?>
    
    <div class="pedido-header">
        <h1>Pedido #<?= $pedido['id_pedido'] ?></h1>
        <span class="status-badge status-<?= $pedido['status_pedido'] ?>">
            <?= ucfirst($pedido['status_pedido']) ?>
        </span>
        
        <div class="info-grid">
            <div class="info-item">
                <strong>Atendente</strong>
                <?= htmlspecialchars($pedido['nome'] . ' ' . $pedido['apelido']) ?>
            </div>
            <div class="info-item">
                <strong>Data/Hora</strong>
                <?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?>
            </div>
            <div class="info-item">
                <strong>Email</strong>
                <?= htmlspecialchars($pedido['email']) ?>
            </div>
            <?php if ($pedido['reference']): ?>
            <div class="info-item">
                <strong>Referência</strong>
                <?= htmlspecialchars($pedido['reference']) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <h2>Itens do Pedido: (<?= count($itens) ?>)</h2>
    
    <?php if (empty($itens)): ?>
        <p style="text-align: center; padding: 40px; color: #999;">
            Nenhum item neste pedido ainda.
        </p>
    <?php else: ?>
        <?php foreach ($itens as $item): ?>
            <div class="item-card">
                <?php if ($pode_editar): ?>
                    <button 
                        class="btn-remover-item" 
                        onclick="if(confirm('Remover este item? O estoque será reabastecido.')) { window.location.href='admin_remover_item_pedido.php?id_item=<?= $item['id_item_pedido'] ?>&id_pedido=<?= $id_pedido ?>'; }">
                        🗑️ Remover
                    </button>
                <?php endif; ?>
                
                <div class="item-header">
                    <div>
                        <strong><?= $item['quantidade'] ?>x <?= htmlspecialchars($item['nome_produto']) ?></strong>: 
                    </div>
                    <div>
                        <?= number_format($item['subtotal'], 2, ',', '.') ?> MZN
                    </div>
                </div>
                
                <!-- <p style="color: #666; font-size: 0.9em;">
                    Preço unitário: <?= number_format($item['preco_unitario'], 2, ',', '.') ?> MZN
                </p> -->
                
                <?php if (!empty($item['personalizacoes'])): ?>
                    <div style="margin-top: 10px;">
                        <strong>Personalizações:</strong><br>
                        <?php foreach ($item['personalizacoes'] as $pers): ?>
                            <span class="personalizacao-badge badge-<?= $pers['tipo'] ?>">
                                <?= $pers['tipo'] === 'extra' ? '+ ' : '- ' ?>
                                <?= htmlspecialchars($pers['ingrediente_nome']) ?>
                                <?php if ($pers['qtd'] > 1): ?>
                                    (x<?= $pers['qtd'] ?>)
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="total-section">
        <h2>Total: <?= number_format($pedido['total'], 2, ',', '.') ?> MZN</h2>
    </div>
    
    <a href="admin_lista_pedidos_finalizar.php" class="btn-voltar">← Voltar à Lista</a>
</div>

</body>
</html>