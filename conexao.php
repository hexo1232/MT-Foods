<?php
$host = "localhost";
$usuario = "root";
$password = "";
$bd="restaurante";

$conexao= new mysqli($host, $usuario, $password,$bd);

if ($conexao->connect_error){

    echo "Erro ao conectar á base de dados";
}



?>