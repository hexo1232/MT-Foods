<?php 
// 1. Configuração e Segurança
session_start();
require_once "conexao.php"; 
require_once "require_login.php"; 

$ID_ORIGEM_MANUAL = 3; // 🎯 Origem manual/presencial

// Verifica se o admin está logado
$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario || $usuario['idperfil'] !== 1) {
    header('Location: login.php'); 
    exit();
}

// -------------------------------------------------------------------
// 2. Coleta e Validação dos Dados do Formulário (POST)
// -------------------------------------------------------------------

// Campos obrigatórios vindos do personalizacao.php
if (empty($_POST['id_produto']) || empty($_POST['quantidade']) || !isset($_POST['preco']) || !isset($_POST['valor_adicional'])) {
    http_response_code(400);
    exit("Dados do produto ou personalização incompletos.");
}

$id_produto = intval($_POST['id_produto']);
$quantidade = max(1, intval($_POST['quantidade']));

// O preço base (original do produto)
// O preço base (original do produto)
$preco_base_unitario = floatval($_POST['preco']); 

// 🎯 CORREÇÃO: O PREÇO FINAL POR UNIDADE é a SOMA do preço base com o valor do ajuste/personalização.
// Se 'valor_adicional' for um acréscimo (15), ele soma (200+15=215).
// Se 'valor_adicional' for um desconto (-15), ele subtrai (200-15=185).
$preco_unitario_final = $preco_base_unitario;
//  + floatval($_POST['valor_adicional']); 

// O custo total do novo item (Preço Unitário Final * Quantidade)
$custo_total_novo_item = $quantidade * $preco_unitario_final;
// Dados de personalização
$ingredientes_reduzidos_json = $_POST['ingredientes_reduzidos'] ?? '[]';
$ingredientes_incrementados_json = $_POST['ingredientes_incrementados'] ?? '[]';

$ingredientes_reduzidos = json_decode($ingredientes_reduzidos_json, true);
$ingredientes_incrementados = json_decode($ingredientes_incrementados_json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    exit("Erro na leitura dos dados de personalização.");
}

// -------------------------------------------------------------------
// 3. Inicia a transação (Atomicidade para Pedido e Estoque)
// -------------------------------------------------------------------
$conexao->begin_transaction();

try {
    // -----------------------------------------------------
    // A. Iniciar ou Obter o ID do Pedido em Curso 
    // -----------------------------------------------------
    $id_admin = $usuario['id_usuario'] ?? 0;
    if ($id_admin <= 0) {
        throw new Exception("ID de Administrador inválido na sessão. Faça login novamente.");
    }

    $total_antigo       = 0.00;
    $id_pedido_atual    = $_SESSION['admin_pedido_id'] ?? null;
    
    if (!$id_pedido_atual) {
        // Cria um novo pedido
        $sql_cria_pedido = "
            INSERT INTO pedido 
            (id_usuario, status_pedido, total, idtipo_origem_pedido, idtipo_pagamento, idtipo_entrega) 
            VALUES (?, 'pendente', 0.00, ?, 1, 1)
        "; 
        $stmt = $conexao->prepare($sql_cria_pedido);
        $stmt->bind_param("ii", $id_admin, $ID_ORIGEM_MANUAL);
        $stmt->execute();

        if ($stmt->error) {
            throw new Exception("Erro ao criar o novo pedido: " . $stmt->error);
        }
        
        $id_pedido_atual = $conexao->insert_id;
        $_SESSION['admin_pedido_id'] = $id_pedido_atual;
        $stmt->close();

    } else {
        // Busca o total atual do pedido
        $sql_total = "SELECT total FROM pedido WHERE id_pedido = ?";
        $stmt_total = $conexao->prepare($sql_total);
        $stmt_total->bind_param("i", $id_pedido_atual);
        $stmt_total->execute();

        $resultado_total = $stmt_total->get_result();
        if ($row = $resultado_total->fetch_assoc()) {
            $total_antigo = floatval($row['total']);
        }
        $stmt_total->close();
    }


    // -----------------------------------------------------
    // B. Débito/Crédito de Estoque (Lógica Mantida)
    // -----------------------------------------------------
    
    // Processa ingredientes INCREMENTADOS (DEBITA estoque)
    foreach ($ingredientes_incrementados as $ingr) {
        $id_ingrediente = intval($ingr['id_ingrediente']);
        // Multiplica a quantidade adicional do ingrediente pela quantidade do produto
        $qtd_debito = intval($ingr['qtd']) * $quantidade; 
        
        if ($qtd_debito > 0) {
            // Verifica o estoque
            $sql_check_stock = "SELECT quantidade_estoque FROM ingrediente WHERE id_ingrediente = ?";
            $stmt_check = $conexao->prepare($sql_check_stock);
            $stmt_check->bind_param("i", $id_ingrediente);
            $stmt_check->execute();
            $estoque_atual = $stmt_check->get_result()->fetch_assoc()['quantidade_estoque'] ?? 0;
            $stmt_check->close();

            if ($estoque_atual < $qtd_debito) {
                throw new Exception("Estoque insuficiente para o ingrediente ID: {$id_ingrediente}. Necessário: {$qtd_debito}, Disponível: {$estoque_atual}.");
            }

            // Debita o estoque
            $sql_debito = "UPDATE ingrediente SET quantidade_estoque = quantidade_estoque - ? WHERE id_ingrediente = ?";
            $stmt_debito = $conexao->prepare($sql_debito);
            $stmt_debito->bind_param("ii", $qtd_debito, $id_ingrediente);
            $stmt_debito->execute();
            if ($stmt_debito->error) {
                throw new Exception("Erro ao debitar estoque: " . $stmt_debito->error);
            }
            $stmt_debito->close();
        }
    }

    // Processa ingredientes REDUZIDOS (CREDITA estoque)
    foreach ($ingredientes_reduzidos as $ingr) {
        $id_ingrediente = intval($ingr['id_ingrediente']);
        // Multiplica a quantidade reduzida do ingrediente pela quantidade do produto
        $qtd_credito = intval($ingr['qtd']) * $quantidade; 
        
        if ($qtd_credito > 0) {
            // Credita o estoque
            $sql_credito = "UPDATE ingrediente SET quantidade_estoque = quantidade_estoque + ? WHERE id_ingrediente = ?";
            $stmt_credito = $conexao->prepare($sql_credito);
            $stmt_credito->bind_param("ii", $qtd_credito, $id_ingrediente);
            $stmt_credito->execute();
            if ($stmt_credito->error) {
                throw new Exception("Erro ao creditar estoque: " . $stmt_credito->error);
            }
            $stmt_credito->close();
        }
    }


    // -----------------------------------------------------
    // C. Inserir o Item na Tabela 'item_pedido'
    // 🎯 ALTERAÇÃO PARA ALINHAR com admin_finalizar_pedidos.php:
    //    - Usa preco_unitario (preço final por unidade) e subtotal (custo total do item).
    //    - Assumimos que a coluna is_personalizado não é estritamente necessária ou não existe
    //      na sua tabela conforme o modelo do historico_compras.
    // -----------------------------------------------------
    
    $sql_insere_item = "
        INSERT INTO item_pedido 
        (id_pedido, id_produto, quantidade, preco_unitario, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ";
    $stmt_item = $conexao->prepare($sql_insere_item);
    
    // preco_unitario: preco_unitario_final (valor_adicional do POST)
    // subtotal: custo_total_novo_item (preco_unitario_final * quantidade)
    $stmt_item->bind_param("iiidd", $id_pedido_atual, $id_produto, $quantidade, $preco_unitario_final, $custo_total_novo_item);
    
    $stmt_item->execute();
    $id_item_pedido = $conexao->insert_id;
    $stmt_item->close();

    
    // -----------------------------------------------------
    // D. Inserir os Detalhes da Personalização (Tabela 'item_pedido_personalizacao')
    // -----------------------------------------------------

    // Prepara o statement para buscar o nome do ingrediente
    $sql_busca_nome = "SELECT nome_ingrediente FROM ingrediente WHERE id_ingrediente = ?";
    $stmt_nome = $conexao->prepare($sql_busca_nome);

    // Prepara o statement para inserir na tabela de personalização
    $sql_detalhe = "
        INSERT INTO item_pedido_personalizacao 
        (id_item_pedido, ingrediente_nome, tipo) 
        VALUES (?, ?, ?)
    ";
    $stmt_detalhe = $conexao->prepare($sql_detalhe);

    // Processa ingredientes INCREMENTADOS (Tipo: 'extra')
    foreach ($ingredientes_incrementados as $ingr) {
        $id_ingrediente = intval($ingr['id_ingrediente']);
        $qtd_extra_por_item = intval($ingr['qtd']); 

        if ($qtd_extra_por_item > 0) {
            // Busca o nome do ingrediente
            $stmt_nome->bind_param("i", $id_ingrediente);
            $stmt_nome->execute();
            $nome_ingr = $stmt_nome->get_result()->fetch_assoc()['nome_ingrediente'] ?? 'Ingrediente Extra (ID: ' . $id_ingrediente . ')';

            // Repete a inserção pela QUANTIDADE TOTAL (Qtd Ingrediente por Item * Qtd de Itens)
            $total_repeticoes = $qtd_extra_por_item;

            $tipo = 'extra';
            for($i = 0; $i < $total_repeticoes; $i++) {
                $stmt_detalhe->bind_param("iss", $id_item_pedido, $nome_ingr, $tipo);
                $stmt_detalhe->execute();
            }
        }
    }

    // Processa ingredientes REDUZIDOS (Tipo: 'removido')
    foreach ($ingredientes_reduzidos as $ingr) {
        $id_ingrediente = intval($ingr['id_ingrediente']);
        $qtd_removida_por_item = intval($ingr['qtd']); 
        
        if ($qtd_removida_por_item > 0) {
            // Busca o nome do ingrediente
            $stmt_nome->bind_param("i", $id_ingrediente);
            $stmt_nome->execute();
            $nome_ingr = $stmt_nome->get_result()->fetch_assoc()['nome_ingrediente'] ?? 'Ingrediente Removido (ID: ' . $id_ingrediente . ')';
            
            // Repete a inserção pela QUANTIDADE TOTAL (Qtd Ingrediente por Item * Qtd de Itens)
            $total_repeticoes = $qtd_removida_por_item;
            
            $tipo = 'removido';
            for($i = 0; $i < $total_repeticoes; $i++) {
                $stmt_detalhe->bind_param("iss", $id_item_pedido, $nome_ingr, $tipo);
                $stmt_detalhe->execute();
            }
        }
    }
    
    // Fecha os statements preparados
    $stmt_nome->close();
    $stmt_detalhe->close();
    
    
    // -----------------------------------------------------
    // E. Atualizar o Total do Pedido
    // -----------------------------------------------------
    $novo_total = $total_antigo + $custo_total_novo_item;
    
    $sql_atualiza_total = "UPDATE pedido SET total = ? WHERE id_pedido = ?";
    $stmt_total_update = $conexao->prepare($sql_atualiza_total);
    $stmt_total_update->bind_param("di", $novo_total, $id_pedido_atual);
    $stmt_total_update->execute();
    $stmt_total_update->close();

    // Commit
    $conexao->commit();

} catch (Exception $e) {
    // Rollback em caso de qualquer erro (falha de estoque, BD, etc.)
    $conexao->rollback(); 
    error_log("Erro de transação (Admin Personalização): " . $e->getMessage());
    $_SESSION['erro_pedido'] = "Erro na transação: " . $e->getMessage();
    
    // Redireciona de volta para a página de personalização com erro
    header('Location: personalizacao.php?id_produto=' . $id_produto . '&modo=admin_pedido'); 
    exit();
}
    
// 4. Sucesso e Redirecionamento
$_SESSION['sucesso_pedido'] = "Item personalizado adicionado ao Pedido #{$id_pedido_atual} com sucesso! Total: " . 
    number_format($novo_total, 2, ',', '.') . " MZN";

header('Location: cardapio.php?modo=admin_pedido'); 
exit();