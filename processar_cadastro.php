<?php
include 'db.php';

$usuario = $_POST['usuario'];
$email = $_POST['email'];
$senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

// Verifica se o usuário ou o e-mail já existe
$query = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? OR email = ?");
$query->execute([$usuario, $email]);

if ($query->rowCount() > 0) {
    echo "Usuário ou e-mail já existe.";
} else {
    $query = $pdo->prepare("INSERT INTO usuarios (usuario, email, senha) VALUES (?, ?, ?)");
    $query->execute([$usuario, $email, $senha]);
    header('Location: index.php');
}
?>


