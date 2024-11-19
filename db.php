<?php
$host = 'localhost';
$db = 'sistema_imoveis'; // Altere para o nome do seu banco
$user = 'root';      // Altere para seu usuário do MySQL
$pass = '';        // Altere para sua senha do MySQL

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Erro de conexão: " . $e->getMessage();
}
?>
