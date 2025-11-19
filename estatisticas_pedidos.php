<?php
error_reporting(0);
header('Content-Type: application/json');
include "conexao.php";

$periodo = $_GET['periodo'] ?? 'mensal';

switch ($periodo) {
  case 'diario':
    $sql = "
      SELECT DATE(data_pedido) AS label, COUNT(*) AS total 
      FROM pedido 
      WHERE data_pedido IS NOT NULL 
      GROUP BY DATE(data_pedido)
      ORDER BY DATE(data_pedido)
    ";
    break;

  case 'semanal':
    $sql = "
      SELECT CONCAT('Semana ', WEEK(data_pedido)) AS label, COUNT(*) AS total 
      FROM pedido 
      WHERE data_pedido IS NOT NULL 
      GROUP BY WEEK(data_pedido)
      ORDER BY WEEK(data_pedido)
    ";
    break;

  case 'mensal':
    $sql = "
      SELECT MONTHNAME(data_pedido) AS label, COUNT(*) AS total 
      FROM pedido 
      WHERE data_pedido IS NOT NULL 
      GROUP BY MONTH(data_pedido)
      ORDER BY MONTH(data_pedido)
    ";
    break;

  case 'anual':
    $sql = "
      SELECT YEAR(data_pedido) AS label, COUNT(*) AS total 
      FROM pedido 
      WHERE data_pedido IS NOT NULL 
      GROUP BY YEAR(data_pedido)
      ORDER BY YEAR(data_pedido)
    ";
    break;

  default:
    $sql = "
      SELECT MONTHNAME(data_pedido) AS label, COUNT(*) AS total 
      FROM pedido 
      WHERE data_pedido IS NOT NULL 
      GROUP BY MONTH(data_pedido)
      ORDER BY MONTH(data_pedido)
    ";
}

$result = $conexao->query($sql);

$dados = [];
if ($result) {
  while ($row = $result->fetch_assoc()) {
    $dados[] = [
      'label' => $row['label'],
      'total' => (int)$row['total']
    ];
  }
}

echo json_encode($dados);
