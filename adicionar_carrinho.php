<?php
// Inicia a sessão e inclui a conexão com o banco de dados

//adicionar_carrinho.php, para itens normais
session_start();
include "conexao.php";
include "verifica_login_opcional.php"; 

// Verifica quais dados chegaram
$tem_preco_promo = isset($_POST['id_produto'], $_POST['quantidade'], $_POST['preco_promocional']);
$tem_preco_normal = isset($_POST['id_produto'], $_POST['quantidade'], $_POST['preco']);

if (!$tem_preco_promo && !$tem_preco_normal) {
    http_response_code(400);
    exit("Dados inválidos. Por favor, tente novamente.");
}

// Sanitiza e valida os dados de entrada
$id_produto = intval($_POST['id_produto']);
$quantidade = max(1, intval($_POST['quantidade']));
$id_tipo_item_carrinho = 1; // 📌 Item Padrão

// Define o preço baseado no que foi enviado
if ($tem_preco_promo) {
    $preco = floatval($_POST['preco_promocional']);
} else {
    $preco = floatval($_POST['preco']);
}

$subtotal = $quantidade * $preco;

// Lógica para Usuários Logados (salva no banco de dados)
if (isset($_SESSION['usuario']) && isset($_SESSION['usuario']['id_usuario'])) {
    $id_usuario = $_SESSION['usuario']['id_usuario'];

    // Localiza o carrinho ativo do usuário
    $stmt = $conexao->prepare("SELECT id_carrinho FROM carrinho WHERE id_usuario = ? AND status = 'activo'");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $id_carrinho = $res->fetch_assoc()['id_carrinho'];
    } else {
        $stmt = $conexao->prepare("INSERT INTO carrinho (id_usuario, data_criacao, status) VALUES (?, NOW(), 'activo')");
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $id_carrinho = $stmt->insert_id;
    }

    // Gera um UUID se não foi enviado (para consistência com a remoção depois)
    $uuid = bin2hex(random_bytes(16));

    // Insere o novo item
    // IMPORTANTE: Adicionei a coluna uuid aqui, pois é necessária para remover o item depois
    $stmt = $conexao->prepare("
        INSERT INTO item_carrinho (id_carrinho, id_produto, quantidade, id_tipo_item_carrinho, subtotal, detalhes_personalizacao, uuid) 
        VALUES (?, ?, ?, ?, ?, 'Sem personalizações adicionais.', ?)
    ");
    $stmt->bind_param("iiiids", $id_carrinho, $id_produto, $quantidade, $id_tipo_item_carrinho, $subtotal, $uuid);
    
    if ($stmt->execute()) {
        http_response_code(200);
    } else {
        http_response_code(500);
        echo "Erro ao inserir no banco.";
    }
    exit;

} else {
    // Usuário não logado (cookie tratado no JS)
    http_response_code(200);
    exit;
}
?>
