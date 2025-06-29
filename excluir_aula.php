<?php
session_start();
require 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

$stmt = $pdo->prepare("SELECT role FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$usuario || $usuario['role'] !== 'admin') {
    die("Acesso negado.");
}

$id = intval($_GET['id'] ?? 0);

// Excluir as respostas vinculadas à aula
$stmt = $pdo->prepare("DELETE FROM respostas_aulas WHERE aula_id = ?");
$stmt->execute([$id]);

// Agora sim pode excluir a aula
$stmt = $pdo->prepare("DELETE FROM aulas WHERE id = ?");
$stmt->execute([$id]);

header("Location: listar_aulas");
exit;
