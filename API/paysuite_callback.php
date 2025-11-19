<?php
// Caminho: Restaurante/API/paysuite_callback.php

require_once '../conexao.php';
require_once 'config_paysuite.php';
require_once 'helpers.php';
require_once '../notificacao.php';

// Função auxiliar de log
function paysuite_debug_log($mensagem)
{
    $arquivo = __DIR__ . '/paysuite_debug.log';
    file_put_contents($arquivo, "[" . date('Y-m-d H:i:s') . "] " . $mensagem . PHP_EOL, FILE_APPEND);
}

// ================================
// INÍCIO DO CALLBACK
// ================================
$payload = file_get_contents('php://input');
paysuite_debug_log("==== CALLBACK RECEBIDO ====");
paysuite_debug_log("Raw payload: " . $payload);

if (empty($payload)) {
    paysuite_debug_log("ERRO: Nenhum payload recebido.");
    http_response_code(400);
    exit('Nenhum payload recebido');
}

$data = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    paysuite_debug_log("ERRO: JSON inválido no payload.");
    http_response_code(400);
    exit('JSON inválido');
}

// ================================
// VALIDAÇÃO BÁSICA
// ================================
if (!isset($data['event']) || !isset($data['data'])) {
    paysuite_debug_log("ERRO: Estrutura de callback inválida. Dados recebidos: " . print_r($data, true));
    http_response_code(400);
    exit('Evento inválido');
}

$evento = $data['event'];
$detalhes = $data['data'];
$referencia = $detalhes['reference'] ?? null;

// Mantenha o status como 'sucesso' se for 'payment.success' ou 'payment.completed'
$status = (in_array($evento, ['payment.success', 'payment.completed'])) ? 'sucesso' : 'falhou';

paysuite_debug_log("Evento: $evento | Status interpretado: $status | Referência: $referencia");

if (!$referencia) {
    paysuite_debug_log("ERRO: Referência ausente no callback.");
    http_response_code(400);
    exit('Sem referência válida');
}

// ================================
// PROCESSAMENTO PRINCIPAL
// ================================
try {
    if ($conexao->connect_error) {
        throw new Exception("Falha na conexão com o banco de dados: " . $conexao->connect_error);
    }

    $conexao->begin_transaction();

    paysuite_debug_log("Atualizando status da transação para '$status'...");

    // 1. Atualiza o status da transação na tabela 'transacoes'
    $stmt = $conexao->prepare("UPDATE transacoes SET status = ?, data_atualizacao = NOW() WHERE referencia = ? AND status = 'pendente'");
    $stmt->bind_param("ss", $status, $referencia);
    if (!$stmt->execute()) {
        throw new Exception("Falha ao atualizar status da transação: " . $stmt->error);
    }
    paysuite_debug_log("Transações afetadas: " . $stmt->affected_rows);
    $stmt->close();

    // ================================
    // CASO DE SUCESSO
    // ================================
    if ($status === 'sucesso') {

        paysuite_debug_log("Pagamento bem-sucedido. Buscando dados do pedido temporário...");

        // 2. Busca o pedido_temp e o ID da transação (t.id)
        $stmt_temp = $conexao->prepare("
            SELECT pt.*, t.id AS id_transacao 
            FROM pedido_temp pt
            JOIN transacoes t ON t.referencia = pt.reference
            WHERE pt.reference = ? 
            LIMIT 1
        ");
        $stmt_temp->bind_param("s", $referencia);
        $stmt_temp->execute();
        $res_temp = $stmt_temp->get_result();
        $pedido_temp = $res_temp->fetch_assoc();
        $stmt_temp->close();

        if (!$pedido_temp) {
            paysuite_debug_log("AVISO: Pedido temporário já processado ou não encontrado para referência $referencia.");
            $conexao->commit();
            http_response_code(200);
            exit;
        }

        // Extração dos dados
        $id_usuario = $pedido_temp['id_usuario'];
        $id_transacao = $pedido_temp['id_transacao'];
        $total_final = (float)$pedido_temp['total'];
        $idtipo_pagamento = (int)$pedido_temp['idtipo_pagamento'];
        $idtipo_entrega = (int)$pedido_temp['idtipo_entrega'];
        $pontos_gastos = (int)$pedido_temp['pontos_gastos'];
        $endereco_json = $pedido_temp['endereco_info'];
        $itens_json = $pedido_temp['itens'];
        $bairro= $pedido_temp['bairro'];
        $ponto_referencia= $pedido_temp['ponto_referencia'];
              $telefone= $pedido_temp['telefone'];

        $endereco_array = json_decode($endereco_json, true);
        // $telefone = $endereco_array['telefone'] ?? '000000000';

        $stmt_user = $conexao->prepare("SELECT email FROM usuario WHERE id_usuario = ?");
        $stmt_user->bind_param("i", $id_usuario);
        $stmt_user->execute();
        $res_user = $stmt_user->get_result();
        $user_data = $res_user->fetch_assoc();
        $email = $user_data['email'] ?? 'desconhecido@exemplo.com';
        $stmt_user->close();

        $itens_pedido_array = json_decode($itens_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON de itens inválido no pedido_temp: " . json_last_error_msg());
        }

        paysuite_debug_log("Pedido Temp encontrado → Usuário: $id_usuario | Total: $total_final | Pontos Gastos: $pontos_gastos");

        // 3. INSERIR PEDIDO FINAL
        $status_inicial = 'pendente';

        $sql_pedido = "
    INSERT INTO pedido (reference, id_usuario, telefone, email, idtipo_pagamento, 
                        total, idtipo_entrega, endereco_json, status_pedido, bairro, ponto_referencia, data_finalizacao)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt_pedido = $conexao->prepare($sql_pedido);

$stmt_pedido->bind_param(
    "siisidissss",
    $referencia,         // s
    $id_usuario,         // i
    $telefone,           // i
    $email,              // s
    $idtipo_pagamento,   // i
    $total_final,        // d
    $idtipo_entrega,     // i
    $endereco_json,      // s
    $status_inicial,     // s
    $bairro,             // s
    $ponto_referencia    // s
);


        if (!$stmt_pedido->execute()) {
            throw new Exception("Falha ao inserir Pedido Final: " . $stmt_pedido->error);
        }
        $id_pedido_final = $conexao->insert_id;
        $stmt_pedido->close();

        paysuite_debug_log("Pedido final ID $id_pedido_final inserido com sucesso.");

        // 4. ATUALIZA A TRANSAÇÃO COM O ID DO PEDIDO FINAL
        $stmt_upd_trans = $conexao->prepare("UPDATE transacoes SET id_pedido = ? WHERE id = ?");
        $stmt_upd_trans->bind_param("ii", $id_pedido_final, $id_transacao);
        $stmt_upd_trans->execute();
        $stmt_upd_trans->close();

        // 5. INSERIR ITENS DO PEDIDO E ATUALIZAR ESTOQUE DE INGREDIENTES
        foreach ($itens_pedido_array as $item) {
            $item_preco_unitario = (float)($item['preco'] ?? 0.00); // Usando 'preco' como preço unitário
            $item_subtotal = (float)($item['subtotal'] ?? 0.00);
            $item_quantidade = (int)($item['quantidade'] ?? 1);
            $id_produto = (int)($item['id_produto'] ?? 0);

            if ($id_produto === 0) {
                 paysuite_debug_log("AVISO: Item no pedido sem ID de produto. Ignorando.");
                 continue;
            }

            // CORREÇÃO: Fallback para preço unitário
            if ($item_preco_unitario <= 0 && $item_quantidade > 0 && $item_subtotal > 0) {
                $item_preco_unitario = $item_subtotal / $item_quantidade;
            } elseif ($item_preco_unitario <= 0) {
                // Tenta buscar o preço do produto na BD se não estiver no JSON (medida de segurança)
                $stmt_preco = $conexao->prepare("SELECT preco FROM produto WHERE id_produto = ?");
                $stmt_preco->bind_param("i", $id_produto);
                $stmt_preco->execute();
                $item_preco_unitario = (float)($stmt_preco->get_result()->fetch_assoc()['preco'] ?? 0.00);
                $stmt_preco->close();
            }


            $sql_item = "
                INSERT INTO item_pedido (id_pedido, id_produto, quantidade, preco_unitario, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ";
            $stmt_item = $conexao->prepare($sql_item);
            $stmt_item->bind_param(
                "iiidd",
                $id_pedido_final,
                $id_produto,
                $item_quantidade,
                $item_preco_unitario,
                $item_subtotal
            );

            if (!$stmt_item->execute()) {
                throw new Exception("Falha ao inserir Item Pedido: " . $stmt_item->error);
            }
            $id_item_pedido_final = $conexao->insert_id;
            $stmt_item->close();

            // --- INÍCIO DA DEDUÇÃO DE ESTOQUE (COM CORREÇÃO DE COLUNA/JOIN) ---
            
            // 5.1. Dedução dos Ingredientes BASE (Receita)
            // CORREÇÃO CRÍTICA: Trocado 'quantidade_necessaria' por 'quantidade_ingrediente' e adicionado JOIN
            $stmt_receita = $conexao->prepare("
                SELECT 
                    pi.id_ingrediente, 
                    pi.quantidade_ingrediente,
                    i.nome_ingrediente
                FROM produto_ingrediente pi
                JOIN ingrediente i ON i.id_ingrediente = pi.id_ingrediente
                WHERE pi.id_produto = ?
            ");
            $stmt_receita->bind_param("i", $id_produto);
            $stmt_receita->execute();
            $res_receita = $stmt_receita->get_result();
            
            $stmt_estoque = $conexao->prepare("
                UPDATE ingrediente 
                SET quantidade_estoque = quantidade_estoque - ?
                WHERE id_ingrediente = ? AND quantidade_estoque >= ?
            ");

            while ($receita = $res_receita->fetch_assoc()) {
                $id_ingrediente_base = (int)$receita['id_ingrediente'];
                // O nome agora é carregado graças ao JOIN
                $nome_ingrediente_base = $receita['nome_ingrediente'] ?? ''; 
                
                // CORRIGIDO AQUI: Usando 'quantidade_ingrediente' da BD
                $qtd_base_por_unidade = (float)$receita['quantidade_ingrediente']; 
                
                // Verifica se este ingrediente base foi REMOVIDO pelo cliente
                $foi_removido = false;
                
                // Atenção: Isto requer que o JSON inclua o nome ou o ID para o ingrediente removido
                foreach (($item['ingredientes_reduzidos'] ?? []) as $ing_rem) {
                    // Se o item tem o id_ingrediente (ideal) ou nome 
                    if (($ing_rem['id_ingrediente'] ?? 0) == $id_ingrediente_base || strtolower($ing_rem['nome_ingrediente'] ?? '') == strtolower($nome_ingrediente_base)) {
                        $foi_removido = true;
                        break;
                    }
                }
                
                if (!$foi_removido) {
                    // Se não foi removido, deduz a quantidade base * quantidade de itens
                    $quantidade_a_reduzir = $qtd_base_por_unidade * $item_quantidade;

                    if ($quantidade_a_reduzir > 0) {
                        // Deduz do estoque (e garante que o estoque não fique negativo - embora a transação proteja)
                        $stmt_estoque->bind_param("idi", $quantidade_a_reduzir, $id_ingrediente_base, $quantidade_a_reduzir);
                        if (!$stmt_estoque->execute()) {
                            // Se a execução falhar (por exemplo, falta de estoque na condição WHERE), lançar exceção
                            throw new Exception("Falha ao deduzir estoque base do ingrediente $id_ingrediente_base.");
                        }
                    }
                }
            }
            $stmt_receita->close();
            $stmt_estoque->close();

            // 5.2. Dedução dos Ingredientes EXTRA/INCREMENTADOS (Lógica Original Mantida e Aprimorada)
            $ingredientes_incrementados = $item['ingredientes_incrementados'] ?? [];

            $stmt_estoque_extra = $conexao->prepare("
                UPDATE ingrediente 
                SET quantidade_estoque = quantidade_estoque - ?
                WHERE id_ingrediente = ? AND quantidade_estoque >= ?
            ");
            
            foreach ($ingredientes_incrementados as $ingrediente) {
                $id_ingrediente = (int)($ingrediente['id_ingrediente'] ?? 0);
                if ($id_ingrediente <= 0) {
                    paysuite_debug_log("AVISO: Ingrediente extra sem ID. Ignorando dedução de estoque.");
                    continue;
                }

                // Assumindo que 'quantidade' é o ajuste/extra do ingrediente
                $quantidade_ajuste = (int)($ingrediente['quantidade'] ?? 1); 
                $quantidade_a_reduzir_extra = $quantidade_ajuste * $item_quantidade; 
                
                if ($quantidade_a_reduzir_extra > 0) {
                    $stmt_estoque_extra->bind_param("idi", $quantidade_a_reduzir_extra, $id_ingrediente, $quantidade_a_reduzir_extra);
                    if (!$stmt_estoque_extra->execute()) {
                        throw new Exception("Falha ao deduzir estoque extra do ingrediente $id_ingrediente.");
                    }
                }
            }
            $stmt_estoque_extra->close();


            // FIM DA DEDUÇÃO DE ESTOQUE
            
            // 5.3. Inserir Personalizações no item_pedido_personalizacao (Lógica original, apenas refatorada para usar as novas variáveis)

            // Ingredientes Removidos
            foreach (($item['ingredientes_reduzidos'] ?? []) as $ingrediente_removido) {
                $nome_ingrediente = $ingrediente_removido['nome_ingrediente'] ?? null;
                if (!$nome_ingrediente) continue;

                $sql_pers = "
                    INSERT INTO item_pedido_personalizacao (id_item_pedido, ingrediente_nome, tipo)
                    VALUES (?, ?, 'Removido')
                ";
                $stmt_pers = $conexao->prepare($sql_pers);
                $stmt_pers->bind_param("is", $id_item_pedido_final, $nome_ingrediente);
                $stmt_pers->execute();
                $stmt_pers->close();
            }

            // Ingredientes Incrementados/Extra
            foreach ($ingredientes_incrementados as $ingrediente_extra) {
                $nome_ingrediente = $ingrediente_extra['nome_ingrediente'] ?? null;
                if (!$nome_ingrediente) continue;

                $quantidade_ajuste = (int)($ingrediente_extra['quantidade'] ?? 1);
                if ($quantidade_ajuste > 1) {
                    $nome_ingrediente_pers = "{$nome_ingrediente} (x{$quantidade_ajuste})";
                } else {
                    $nome_ingrediente_pers = $nome_ingrediente;
                }

                $sql_pers = "
                    INSERT INTO item_pedido_personalizacao (id_item_pedido, ingrediente_nome, tipo)
                    VALUES (?, ?, 'Extra')
                ";
                $stmt_pers = $conexao->prepare($sql_pers);
                $stmt_pers->bind_param("is", $id_item_pedido_final, $nome_ingrediente_pers);
                $stmt_pers->execute();
                $stmt_pers->close();
            }
        }

        paysuite_debug_log("Itens do pedido e personalizações inseridos. Estoque atualizado (Base + Extras).");

        // 6. GESTÃO DE FIDELIDADE
        paysuite_debug_log("Iniciando gestão de fidelidade...");

        if ($pontos_gastos > 0) {
            $stmt_deduz_pontos = $conexao->prepare("
                UPDATE fidelidade SET pontos = pontos - ? WHERE id_usuario = ? AND pontos >= ?
            ");
            $stmt_deduz_pontos->bind_param("iii", $pontos_gastos, $id_usuario, $pontos_gastos);
            $stmt_deduz_pontos->execute();
            $stmt_deduz_pontos->close();
        }

        $pontos_ganhos = floor((float)$total_final * 0.1);
        if ($pontos_ganhos > 0) {
            $stmt_fid = $conexao->prepare("
                INSERT INTO fidelidade (id_usuario, pontos, data_ultima_compra, data_expiracao)
                VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 12 MONTH))
                ON DUPLICATE KEY UPDATE 
                    pontos = pontos + VALUES(pontos),
                    data_ultima_compra = NOW(),
                    data_expiracao = DATE_ADD(NOW(), INTERVAL 12 MONTH)
            ");
            $stmt_fid->bind_param("ii", $id_usuario, $pontos_ganhos);
            $stmt_fid->execute();
            $stmt_fid->close();
        }

        paysuite_debug_log("Gestão de fidelidade concluída.");

        // 7. LIMPAR CARRINHO
        paysuite_debug_log("Iniciando limpeza do carrinho ativo...");

        $stmt_get_carrinho = $conexao->prepare("
            SELECT id_carrinho 
            FROM carrinho 
            WHERE id_usuario = ? AND status IN ('activo', 'pendente_pagamento') 
            ORDER BY id_carrinho DESC LIMIT 1
        ");
        $stmt_get_carrinho->bind_param("i", $id_usuario);
        $stmt_get_carrinho->execute();
        $res_carrinho = $stmt_get_carrinho->get_result();
        $carrinho_ativo = $res_carrinho->fetch_assoc();
        $stmt_get_carrinho->close();

        if ($carrinho_ativo) {
            $id_carrinho = $carrinho_ativo['id_carrinho'];
            paysuite_debug_log("Carrinho ativo #$id_carrinho encontrado.");

            // Excluir ingredientes do carrinho
            $stmt_get_item_ids = $conexao->prepare("SELECT id_item_carrinho FROM item_carrinho WHERE id_carrinho = ?");
            $stmt_get_item_ids->bind_param("i", $id_carrinho);
            $stmt_get_item_ids->execute();
            $res_item_ids = $stmt_get_item_ids->get_result();
            $item_ids = [];
            while ($row = $res_item_ids->fetch_assoc()) {
                $item_ids[] = $row['id_item_carrinho'];
            }
            $stmt_get_item_ids->close();

            if (!empty($item_ids)) {
                $placeholders = str_repeat('?,', count($item_ids) - 1) . '?';
                $tipos = str_repeat('i', count($item_ids));

                $sql_del_ingredientes = "DELETE FROM carrinho_ingrediente WHERE id_item_carrinho IN ($placeholders)";
                $stmt_del_ing = $conexao->prepare($sql_del_ingredientes);

                $params = array_merge([$tipos], $item_ids);
                // O uso de call_user_func_array com bind_param é a forma correta para arrays de tamanho dinâmico
                call_user_func_array([$stmt_del_ing, 'bind_param'], $params);

                $stmt_del_ing->execute();
                $stmt_del_ing->close();
            }

            // Deletar itens e atualizar status
            $stmt_del_item = $conexao->prepare("DELETE FROM item_carrinho WHERE id_carrinho = ?");
            $stmt_del_item->bind_param("i", $id_carrinho);
            $stmt_del_item->execute();
            $stmt_del_item->close();

            $stmt_upd_carrinho = $conexao->prepare("
                UPDATE carrinho SET status = 'finalizado' 
                WHERE id_carrinho = ? AND status IN ('activo', 'pendente_pagamento')
            ");
            $stmt_upd_carrinho->bind_param("i", $id_carrinho);
            $stmt_upd_carrinho->execute();
            $stmt_upd_carrinho->close();

            paysuite_debug_log("Carrinho ativo #$id_carrinho finalizado e limpo.");
        } else {
            paysuite_debug_log("AVISO: Nenhum carrinho ativo encontrado para limpar.");
        }

        // 8. DELETAR PEDIDO TEMPORÁRIO
        $stmt_del_temp = $conexao->prepare("DELETE FROM pedido_temp WHERE reference = ?");
        $stmt_del_temp->bind_param("s", $referencia);
        $stmt_del_temp->execute();
        $stmt_del_temp->close();

        // 9. ENVIAR NOTIFICAÇÃO
        if (function_exists('criarNotificacao')) {
            criarNotificacao($conexao, 'novo_pedido', "Novo Pedido #$id_pedido_final recebido.", $id_pedido_final);
        }

    } elseif ($status === 'falhou') {
        paysuite_debug_log("Transação $referencia falhou. Nenhuma ação de pedido final tomada.");
    }

    $conexao->commit();
    paysuite_debug_log("Processo de callback finalizado e transação confirmada.");
} catch (Exception $e) {
    if ($conexao->autocommit) {
        $conexao->rollback();
    }
    paysuite_debug_log("❌ ERRO CRÍTICO CALLBACK: " . $e->getMessage() . " | Linha: " . $e->getLine());
    http_response_code(500);
    exit('Erro interno do servidor');
}

// Responde ao PaySuite com código 200 OK
http_response_code(200);
echo 'OK';
?>