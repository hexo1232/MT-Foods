<?php
$host = "localhost";
$usuario = "root";
$password = "";
$bd="restaurante";

$conexao= new mysqli($host, $usuario, $password,$bd);

if ($conexao->connect_error){


    die("Erro ao conectar à base de dados: " . $conexao->connect_error);

}



?>