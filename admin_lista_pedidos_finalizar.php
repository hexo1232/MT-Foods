<?php
// admin_lista_pedidos_finalizar.php - VERSÃO COM MÚLTIPLOS PEDIDOS E EDIÇÃO

require_once "conexao.php";
require_once "require_login.php";

$usuario = $_SESSION['usuario'] ?? null;

if (!$usuario || (int)$usuario['idperfil'] !== 1) {
    header('Location: dashboard.php');
    exit();
}

// Processar ações (Cancelar, Novo Pedido, Editar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CANCELAR PEDIDO
    if (isset($_POST['cancelar_pedido'])) {
        $id_cancelar = filter_input(INPUT_POST, 'id_pedido', FILTER_VALIDATE_INT);
        
        if ($id_cancelar) {
            $conexao->begin_transaction();
            try {
                // Recrédito de estoque
                $sql_itens = "SELECT ip.id_produto, ip.quantidade FROM item_pedido ip WHERE ip.id_pedido = ?";
                $stmt = $conexao->prepare($sql_itens);
                $stmt->bind_param("i", $id_cancelar);
                $stmt->execute();
                $resultado = $stmt->get_result();
                
                while ($item = $resultado->fetch_assoc()) {
                    $id_produto = $item['id_produto'];
                    $quantidade = $item['quantidade'];
                    
                    // Buscar ingredientes base
                    $sql_ing = "SELECT id_ingrediente, quantidade_ingrediente FROM produto_ingrediente WHERE id_produto = ?";
                    $stmt_ing = $conexao->prepare($sql_ing);
                    $stmt_ing->bind_param("i", $id_produto);
                    $stmt_ing->execute();
                    $res_ing = $stmt_ing->get_result();
                    
                    while ($ing = $res_ing->fetch_assoc()) {
                        $qtd_credito = $ing['quantidade_ingrediente'] * $quantidade;
                        $stmt_credito = $conexao->prepare("UPDATE ingrediente SET quantidade_estoque = quantidade_estoque + ? WHERE id_ingrediente = ?");
                        $stmt_credito->bind_param("ii", $qtd_credito, $ing['id_ingrediente']);
                        $stmt_credito->execute();
                        $stmt_credito->close();
                    }
                    $stmt_ing->close();
                }
                $stmt->close();
                
                // Atualizar status
                $stmt_cancel = $conexao->prepare("UPDATE pedido SET status_pedido = 'cancelado', data_finalizacao = NOW() WHERE id_pedido = ?");
                $stmt_cancel->bind_param("i", $id_cancelar);
                $stmt_cancel->execute();
                $stmt_cancel->close();
                
                $conexao->commit();
                
                // Limpar sessão se era o pedido ativo
                if (isset($_SESSION['admin_pedido_id']) && $_SESSION['admin_pedido_id'] == $id_cancelar) {
                    unset($_SESSION['admin_pedido_id']);
                }
                
                $_SESSION['sucesso'] = "Pedido #{$id_cancelar} cancelado e estoque reabastecido.";
                
            } catch (Exception $e) {
                $conexao->rollback();
                $_SESSION['erro'] = "Erro ao cancelar: " . $e->getMessage();
            }
        }
        header('Location: admin_lista_pedidos_finalizar.php');
        exit();
    }
    
    // CRIAR NOVO PEDIDO
    if (isset($_POST['novo_pedido'])) {
        unset($_SESSION['admin_pedido_id']); // Limpa qualquer pedido ativo
        $_SESSION['sucesso'] = "Pronto para criar novo pedido! Adicione itens no cardápio.";
        header('Location: cardapio.php?modo=admin_pedido');
        exit();
    }
    
    // EDITAR PEDIDO EXISTENTE
    if (isset($_POST['editar_pedido'])) {
        $id_editar = filter_input(INPUT_POST, 'id_pedido', FILTER_VALIDATE_INT);
        
        if ($id_editar) {
            // Verificar se o pedido está pendente
            $stmt_check = $conexao->prepare("SELECT id_pedido FROM pedido WHERE id_pedido = ? AND status_pedido = 'pendente'");
            $stmt_check->bind_param("i", $id_editar);
            $stmt_check->execute();
            $resultado = $stmt_check->get_result();
            
            if ($resultado->num_rows > 0) {
                $_SESSION['admin_pedido_id'] = $id_editar;
                $_SESSION['sucesso'] = "Editando Pedido #{$id_editar}. Adicione ou remova itens.";
                header('Location: cardapio.php?modo=admin_pedido');
                exit();
            } else {
                $_SESSION['erro'] = "Pedido não encontrado ou já finalizado.";
            }
            $stmt_check->close();
        }
        header('Location: admin_lista_pedidos_finalizar.php');
        exit();
    }
}

// Buscar todos os pedidos pendentes (origem manual)
$sql_pedidos = "
    SELECT 
        p.id_pedido,
        p.data_pedido,
        p.total,
        p.status_pedido,
        u.nome,
        u.apelido,
        COUNT(ip.id_item_pedido) AS total_itens
    FROM pedido p
    JOIN usuario u ON p.id_usuario = u.id_usuario
    LEFT JOIN item_pedido ip ON p.id_pedido = ip.id_pedido
  WHERE p.status_pedido IN ('pendente', 'Em preparação', 'Saiu Para Entrega', 'Pronto para Retirada', 'servido') 
    AND p.idtipo_origem_pedido = 3
    GROUP BY p.id_pedido
    ORDER BY p.data_pedido DESC
";

$resultado_pedidos = $conexao->query($sql_pedidos);
$pedidos = [];

while ($pedido = $resultado_pedidos->fetch_assoc()) {
    $pedidos[] = $pedido;
}

$pedido_ativo_id = $_SESSION['admin_pedido_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos para Finalizar</title>
    <link rel="stylesheet" href="css/cliente.css">
    <script src="js/darkmode1.js"></script>
    <style>
        .container-pedidos {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }
        
        .header-acoes {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #111111ff 10%, #4b4b4bff  50%);
            border-radius: 10px;
            color: white;
        }
        
        .btn-novo-pedido {
            background: #28a745;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-novo-pedido:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        
        .pedidos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .pedido-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 3px solid transparent;
        }
        
        .pedido-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .pedido-card.ativo {
            border-color: #667eea;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .pedido-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .pedido-id {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
        }
        
        .badge-ativo {
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
        }
        
        .pedido-info {
            margin: 15px 0;
        }
        
        .pedido-info p {
            margin: 8px 0;
            color: #666;
        }
        
        .pedido-info strong {
            color: #333;
        }
        
        .pedido-total {
            font-size: 1.5em;
            font-weight: bold;
            color: #28a745;
            text-align: center;
            margin: 15px 0;
        }
        
        .pedido-acoes {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-acao {
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-editar {
            background: #007bff;
            color: white;
        }
        
        .btn-editar:hover {
            background: #0056b3;
        }
        
        .btn-finalizar {
            background: #28a745;
            color: white;
        }
        
        .btn-finalizar:hover {
            background: #218838;
        }
        
        .btn-ver-detalhes {
            background: #6c757d;
            color: white;
            grid-column: 1 / -1;
        }
        
        .btn-ver-detalhes:hover {
            background: #5a6268;
        }
        
        .btn-cancelar {
            background: #dc3545;
            color: white;
            grid-column: 1 / -1;
        }
        
        .btn-cancelar:hover {
            background: #c82333;
        }
        
        .sem-pedidos {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .sem-pedidos h2 {
            color: #666;
            margin-bottom: 20px;
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
            <a href="dashboard.php">Dashboard</a>
            <a href="cardapio.php?modo=admin_pedido">Cardápio</a>
        </div>
    </div>
</header>

<div class="container-pedidos">
    
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
    
    <div class="header-acoes">
        <div>
            <h1>Pedidos Pendentes (<?= count($pedidos) ?>)</h1>
            <?php if ($pedido_ativo_id): ?>
                <p style="margin-top: 10px; font-size: 0.95em;">
                    Editando: <strong>Pedido #<?= $pedido_ativo_id ?></strong>
                </p>
            <?php endif; ?>
        </div>
        <form method="POST" style="margin: 0;">
            <button type="submit" name="novo_pedido" class="btn-novo-pedido">
                 Novo Pedido
            </button>
        </form>
    </div>
    
    <?php if (empty($pedidos)): ?>
        <div class="sem-pedidos">
            <h2> Nenhum pedido pendente</h2>
            <p>Clique em "Novo Pedido" para começar</p>
        </div>
    <?php else: ?>
        <div class="pedidos-grid">
            <?php foreach ($pedidos as $pedido): ?>
                <div class="pedido-card <?= ($pedido['id_pedido'] == $pedido_ativo_id) ? 'ativo' : '' ?>">
                    <div class="pedido-header">
                        <div class="pedido-id">Pedido #<?= $pedido['id_pedido'] ?></div>
                        <?php if ($pedido['id_pedido'] == $pedido_ativo_id): ?>
                            <span class="badge-ativo">ATIVO</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="pedido-info">
                        <p><strong>Atendente:</strong> <?= htmlspecialchars($pedido['nome'] . ' ' . $pedido['apelido']) ?></p>
                        <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?></p>
                        <p><strong>Itens:</strong> <?= $pedido['total_itens'] ?></p>
                    </div>
                    
                    <div class="pedido-total">
                        <?= number_format($pedido['total'], 2, ',', '.') ?> MZN
                    </div>
                    
                    <div class="pedido-acoes">
                        <form method="POST" style="display: contents;">
                            <input type="hidden" name="id_pedido" value="<?= $pedido['id_pedido'] ?>">
                            <button type="submit" name="editar_pedido" class="btn-acao btn-editar">
                                Editar
                            </button>
                        </form>
                        
                        <a href="admin_finalizar_pedido.php?id_pedido=<?= $pedido['id_pedido'] ?>" class="btn-acao btn-finalizar">
                            Finalizar
                        </a>
                        
                        <a href="admin_ver_detalhes_pedido.php?id_pedido=<?= $pedido['id_pedido'] ?>" class="btn-acao btn-ver-detalhes">
                            Ver Detalhes
                        </a>
                        
                        <form method="POST" style="display: contents;" onsubmit="return confirm('Tem certeza que deseja cancelar este pedido? O estoque será reabastecido.');">
                            <input type="hidden" name="id_pedido" value="<?= $pedido['id_pedido'] ?>">
                            <button type="submit" name="cancelar_pedido" class="btn-acao btn-cancelar">
                                Cancelar
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
</div>

</body>
</html>