<?php
include "conexao.php";

$id_pedido = intval($_GET['id_pedido']);

$sql = "SELECT status, DATE_FORMAT(data_hora, '%d/%m %H:%i') as data
        FROM rastreamento_pedido
        WHERE id_pedido = $id_pedido
        ORDER BY data_hora ASC";
$result = mysqli_query($conexao, $sql);

$dados = [];
while($row = mysqli_fetch_assoc($result)) {
    $dados[] = $row;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($dados);
?>
