<?php
// conexao.php
$host = 'localhost';
$db   = 'u663037055_codego';
$user = 'u663037055_victtooorrrr';
$pass = '@Robvic09';
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, $options);
} catch (\PDOException $e) {
    die("Erro ao conectar no banco de dados: " . $e->getMessage());
}
