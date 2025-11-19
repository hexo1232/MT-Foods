<?php
include "conexao.php";
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["sucesso" => false, "erro" => "Método inválido"]);
    exit;
}

if (!isset($_POST["id_pedido"], $_POST["novo_status"])) {
    echo json_encode(["sucesso" => false, "erro" => "Parâmetros incompletos"]);
    exit;
}

$id = intval($_POST["id_pedido"]);
$status = trim($_POST["novo_status"]);

// Atualizar pedido
$stmt = $conexao->prepare("UPDATE pedido SET status_pedido = ? WHERE id_pedido = ?");
$stmt->bind_param("si", $status, $id);
$stmt->execute();
$stmt->close();

// Buscar tipo de entrega
$r = $conexao->query("SELECT idtipo_entrega FROM pedido WHERE id_pedido = $id");
$row = $r->fetch_assoc();
$tipo_entrega = $row["idtipo_entrega"];

// Regenerar botões HTML
ob_start();
?>

<?php if ($status === "pendente"): ?>
    <form class="ajax-status">
        <input type="hidden" name="id_pedido" value="<?= $id ?>">
        <input type="hidden" name="novo_status" value="Em preparação">
        <button class="btn-preparar">Iniciar Preparo</button>
    </form>

<?php elseif ($status === "Em preparação"): ?>
    <form class="ajax-status">
        <input type="hidden" name="id_pedido" value="<?= $id ?>">
        <input type="hidden" name="novo_status" value="Pronto para Retirada">
        <button class="btn-retirar">Pronto para Retirada</button>
    </form>

<?php elseif ($status === "Pronto para Retirada"): ?>

    <?php if ($tipo_entrega == 1): ?>
        <form class="ajax-status">
            <input type="hidden" name="id_pedido" value="<?= $id ?>">
            <input type="hidden" name="novo_status" value="entregue">
            <button class="btn-finalizar">Finalizar Pedido</button>
        </form>

    <?php else: ?>
        <form class="ajax-status">
            <input type="hidden" name="id_pedido" value="<?= $id ?>">
            <input type="hidden" name="novo_status" value="Saiu Para Entrega">
            <button class="btn-delivery">Saiu Para Entrega</button>
        </form>
    <?php endif; ?>

<?php elseif ($status === "Saiu Para Entrega"): ?>
    <form class="ajax-status">
        <input type="hidden" name="id_pedido" value="<?= $id ?>">
        <input type="hidden" name="novo_status" value="entregue">
        <button class="btn-finalizar">Finalizar Pedido</button>
    </form>
<?php endif; ?>

<?php
$botoes = ob_get_clean();

echo json_encode([
    "sucesso" => true,
    "id_pedido" => $id,
    "novo_status" => $status,
    "botoes" => $botoes
]);
exit;
