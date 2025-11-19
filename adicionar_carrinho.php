
<?php
// Inicia a sessão e inclui a conexão com o banco de dados
session_start();
include "conexao.php"; // Confirme se sua conexão usa PDO ou MySQLi com prepared statements
include "verifica_login_opcional.php"; // Verifica o login do usuário

if (stripos($produto['categorias_nomes'], 'Promoções da semana') !== false){
    // Verifica se os dados essenciais foram enviados via POST
if (!isset($_POST['id_produto'], $_POST['quantidade'], $_POST['preco_promocional'])) {
    // Se não houver dados essenciais, retorna um erro 400
    http_response_code(400);
    exit("Dados inválidos. Por favor, tente novamente.");
}
// Sanitiza e valida os dados de entrada
$id_produto = intval($_POST['id_produto']);
$quantidade = max(1, intval($_POST['quantidade'])); // Garante que a quantidade seja no mínimo 1
$preco = floatval($_POST['preco_promocional']);
$subtotal = $quantidade * $preco;
$id_tipo_item_carrinho = 1; // 📌 Item Padrão


}

else{
// Verifica se os dados essenciais foram enviados via POST
if (!isset($_POST['id_produto'], $_POST['quantidade'], $_POST['preco'])) {
    // Se não houver dados essenciais, retorna um erro 400
    http_response_code(400);
    exit("Dados inválidos. Por favor, tente novamente.");
}

// Sanitiza e valida os dados de entrada
$id_produto = intval($_POST['id_produto']);
$quantidade = max(1, intval($_POST['quantidade'])); // Garante que a quantidade seja no mínimo 1
$preco = floatval($_POST['preco']);
$subtotal = $quantidade * $preco;
$id_tipo_item_carrinho = 1; // 📌 Item Padrão

}

// Lógica para Usuários Logados (salva no banco de dados)
if (isset($_SESSION['usuario']) && isset($_SESSION['usuario']['id_usuario'])) {
    $id_usuario = $_SESSION['usuario']['id_usuario'];

    // Localiza o carrinho ativo do usuário. Se não existir, cria um novo.
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

    // Sempre insere um novo item no carrinho para que cada adição seja um item único.
    $stmt = $conexao->prepare("
        INSERT INTO item_carrinho (id_carrinho, id_produto, quantidade, id_tipo_item_carrinho, subtotal, detalhes_personalizacao) 
        VALUES (?, ?, ?, ?, ?, 'Sem personalizações adicionais.')
    ");
    $stmt->bind_param("iiiid", $id_carrinho, $id_produto, $quantidade, $id_tipo_item_carrinho, $subtotal);
    $stmt->execute();

    // Encerra o script com sucesso para o fetch no frontend
    http_response_code(200);
    exit;

} else {
    // Se o usuário não está logado, a lógica é tratada no JavaScript.
    // O backend simplesmente responde com sucesso.
    http_response_code(200);
    exit;
}
?>
