<?php
// Inicia a sessão para acessar dados do usuário.
session_start();
// Inclui o arquivo de conexão com o banco de dados.
include "conexao.php";

//adicionar_carrinho_personalizado.php, para itens personalizados

// --- 1. RECEBENDO E VALIDANDO OS DADOS DO POST ---

// Recebe e valida os dados básicos do POST.
$id_produto = (int)($_POST['id_produto'] ?? 0);
$quantidade = max(1, (int)($_POST['quantidade'] ?? 1)); // Garante que a quantidade seja no mínimo 1
$valor_adicional_liquido = floatval($_POST['valor_adicional'] ?? 0); // Valor líquido de extras - removidos

// Decodifica os dados JSON enviados pelo JavaScript.
$ingredientes_incrementados = json_decode($_POST['ingredientes_incrementados'] ?? '[]', true);
$ingredientes_reduzidos = json_decode($_POST['ingredientes_reduzidos'] ?? '[]', true);

// Verifica dados essenciais
if ($id_produto <= 0 || $quantidade <= 0) {
    http_response_code(400);
    echo json_encode(['erro' => 'Produto ou quantidade inválidos.']);
    exit;
}

// Verifica se o usuário está logado.
$id_usuario = $_SESSION['usuario']['id_usuario'] ?? null;
if (!$id_usuario) {
    http_response_code(401);
    echo json_encode(['erro' => 'Usuário não logado.']);
    exit;
}

// --- 2. BUSCANDO PREÇOS E CATEGORIAS DO PRODUTO (Segurança) ---
try {
    $stmt_produto = $conexao->prepare("
        SELECT 
            p.preco, p.preco_promocional, 
            GROUP_CONCAT(c.nome_categoria SEPARATOR ',') AS categorias_nomes
        FROM produto p
        LEFT JOIN produto_categoria pc ON p.id_produto = pc.id_produto
        LEFT JOIN categoria c ON pc.id_categoria = c.id_categoria
        WHERE p.id_produto = ?
        GROUP BY p.id_produto
    ");
    $stmt_produto->bind_param("i", $id_produto);
    $stmt_produto->execute();
    $res_produto = $stmt_produto->get_result();
    $produto = $res_produto->fetch_assoc();

    if (!$produto) {
        http_response_code(404);
        exit("Produto não encontrado.");
    }

    $preco_normal = (float)$produto['preco'];
    $preco_promocional = (float)$produto['preco_promocional'];
    $categorias_nomes = $produto['categorias_nomes'] ?? '';

} catch (Exception $e) {
    http_response_code(500);
    exit("Erro ao buscar dados do produto.");
}

// --- 3. CÁLCULO FINAL DO PREÇO TOTAL E SUBTOTAL ---

$preco_base_unitario = $preco_normal; // Preço base é o normal por default

// Verifica se o produto está em promoção e usa o preço promocional
if (stripos($categorias_nomes, 'Promoções da semana') !== false && $preco_promocional > 0) {
    $preco_base_unitario = $preco_promocional;
}

// O preco_total unitário é o preço base (promocional ou normal) MAIS o valor líquido dos adicionais/removidos
// O valor_adicional_liquido deve vir calculado do frontend.
$preco_total_unitario = $preco_base_unitario + $valor_adicional_liquido;

// O subtotal é o valor total para este item do carrinho (unidades * preço unitário já ajustado)
$subtotal = $quantidade * $preco_total_unitario;


// --- 4. INICIANDO A TRANSAÇÃO E BUSCANDO O CARRINHO ---
$conexao->begin_transaction();

try {
    // Procura por um carrinho ativo para o usuário.
    $stmt = $conexao->prepare("SELECT id_carrinho FROM carrinho WHERE id_usuario = ? AND status = 'activo' LIMIT 1");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $res = $stmt->get_result();

    // Cria ou usa o carrinho existente
    if ($res->num_rows > 0) {
        $id_carrinho = (int)$res->fetch_assoc()['id_carrinho'];
    } else {
        $stmt = $conexao->prepare("INSERT INTO carrinho (id_usuario, data_criacao, status) VALUES (?, NOW(), 'activo')");
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $id_carrinho = $stmt->insert_id;
    }

    // --- 5. FUNÇÃO PARA GERAR A STRING DE PERSONALIZAÇÃO ---
    function criarStringPersonalizacao($conexao, $reduzidos, $incrementados) {
        // ... (o código desta função permanece o mesmo, pois é apenas para gerar a string de detalhes) ...
        $mensagens = [];
        $ids_modificados = [];

        foreach ($reduzidos as $ing) {
            if (isset($ing['id_ingrediente'])) $ids_modificados[] = $ing['id_ingrediente'];
        }
        foreach ($incrementados as $ing) {
            if (isset($ing['id_ingrediente'])) $ids_modificados[] = $ing['id_ingrediente'];
        }

        $nomes = [];
        if (!empty($ids_modificados)) {
            $ids_str = implode(',', array_map('intval', array_unique($ids_modificados)));
            // Note: Usamos query sem prepare pois os IDs foram sanitizados para intval()
            $res = $conexao->query("SELECT id_ingrediente, nome_ingrediente FROM ingrediente WHERE id_ingrediente IN ($ids_str)");
            while ($row = $res->fetch_assoc()) {
                $nomes[$row['id_ingrediente']] = $row['nome_ingrediente'];
            }
        }

        foreach ($reduzidos as $ing) {
            if (isset($ing['id_ingrediente'], $ing['qtd'])) {
                $nome = $nomes[$ing['id_ingrediente']] ?? 'Ingrediente Desconhecido';
                $mensagens[] = "$nome reduzido " . $ing['qtd'] . " vez" . ($ing['qtd'] > 1 ? "es" : "");
            }
        }
        foreach ($incrementados as $ing) {
            if (isset($ing['id_ingrediente'], $ing['qtd'])) {
                $nome = $nomes[$ing['id_ingrediente']] ?? 'Ingrediente Desconhecido';
                $mensagens[] = "$nome incrementado " . $ing['qtd'] . " vez" . ($ing['qtd'] > 1 ? "es" : "");
            }
        }

        return empty($mensagens) ? "Sem personalizações adicionais." : implode(' | ', $mensagens);
    }

    // Gera a string de personalização.
    $detalhes_personalizacao = criarStringPersonalizacao($conexao, $ingredientes_reduzidos, $ingredientes_incrementados);

    // --- 6. INSERINDO O ITEM PRINCIPAL NO CARRINHO ---
    // Gera um UUID único
    $uuid = bin2hex(random_bytes(16));

    $stmt = $conexao->prepare("
        INSERT INTO item_carrinho 
        (id_carrinho, id_produto, quantidade, subtotal, id_tipo_item_carrinho, detalhes_personalizacao, uuid) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $id_tipo_item_carrinho = 2; // Tipo 'personalizado'.
    // ATENÇÃO: $subtotal já inclui o preço do produto E os ajustes de ingredientes.
    $stmt->bind_param("iiidsss", $id_carrinho, $id_produto, $quantidade, $subtotal, $id_tipo_item_carrinho, $detalhes_personalizacao, $uuid);
    $stmt->execute();
    $id_item_carrinho = $stmt->insert_id;

    // --- 7. INSERINDO OS INGREDIENTES MODIFICADOS (APENAS PARA DETALHE) ---
    
    // ATENÇÃO: A COLUNA 'preco' ESTÁ SENDO DEFINIDA COMO 0.00 PARA EVITAR A DUPLICAÇÃO DO CÁLCULO
    // Se o seu sistema de checkout ler o subtotal do item_carrinho, essa é a abordagem correta.
    $preco_zero = 0.00; 

    $stmt_ing = $conexao->prepare("INSERT INTO carrinho_ingrediente (id_item_carrinho, id_ingrediente, tipo, preco) VALUES (?, ?, ?, ?)");

    // Itera sobre os ingredientes incrementados.
    foreach ($ingredientes_incrementados as $ing) {
        $id_ing = (int)($ing['id_ingrediente'] ?? 0);
        $qtd = (int)($ing['qtd'] ?? 1);
        
        if ($id_ing > 0) {
            $tipo = 'extra';
            for ($i = 0; $i < $qtd; $i++) {
                // Insere com PREÇO ZERO. O valor foi somado no $subtotal do item_carrinho.
                $stmt_ing->bind_param("iisd", $id_item_carrinho, $id_ing, $tipo, $preco_zero); 
                $stmt_ing->execute();
            }
        }
    }

    // Itera sobre os ingredientes reduzidos.
    foreach ($ingredientes_reduzidos as $ing) {
        $id_ing = (int)($ing['id_ingrediente'] ?? 0);
        $qtd = (int)($ing['qtd'] ?? 1);
        
        if ($id_ing > 0) {
            $tipo = 'removido';
            for ($i = 0; $i < $qtd; $i++) {
                // Insere com PREÇO ZERO. O valor foi descontado no $subtotal do item_carrinho.
                $stmt_ing->bind_param("iisd", $id_item_carrinho, $id_ing, $tipo, $preco_zero);
                $stmt_ing->execute();
            }
        }
    }

    // --- 8. FINALIZANDO A TRANSAÇÃO E RESPONDENDO ---
    $conexao->commit();

    // Retorna uma resposta de sucesso em formato JSON.
    echo json_encode([
        'sucesso' => true,
        'id_item_carrinho' => $id_item_carrinho,
        'subtotal' => $subtotal
    ]);

} catch (Exception $e) {
    // Se houver algum erro, desfaz todas as operações de banco de dados.
    $conexao->rollback();
    // Retorna uma resposta de erro em formato JSON.
    error_log("Erro no carrinho: " . $e->getMessage());
    echo json_encode(['erro' => 'Ocorreu um erro ao adicionar o item ao carrinho. Detalhes: ' . $e->getMessage()]);
}

// Fechar as conexões (boa prática)
if (isset($stmt_produto)) $stmt_produto->close();
if (isset($stmt)) $stmt->close();
if (isset($stmt_ing)) $stmt_ing->close();
// Fechar a conexão principal
if (isset($conexao)) $conexao->close();

?>
