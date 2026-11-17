<?php
$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "educaletra";

$conn = new mysqli($host, $usuario, $senha, $banco);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(["erro" => "Falha na conexão com o banco: " . $conn->connect_error]));
}
?>