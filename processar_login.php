
<?php
session_start();
include 'db.php';

// Verifica se os dados foram enviados via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valida se o usuário e a senha foram preenchidos
    if (empty($_POST['usuario']) || empty($_POST['senha'])) {
        echo "Usuário e senha são obrigatórios.";
        exit();
    }

    $usuario = $_POST['usuario'];
    $senha = $_POST['senha'];

    // Prepara a consulta para buscar o usuário
    $query = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ?");
    $query->execute([$usuario]);
    $user = $query->fetch();

    // Verifica se o usuário existe e se a senha está correta
    if ($user) {
        if (password_verify($senha, $user['senha'])) {
            $_SESSION['usuario'] = $user['usuario'];
            $_SESSION['usuario_id'] = $user['id']; // Armazenar ID do usuário na sessão

            // Redireciona conforme o tipo de usuário
            if ($user['usuario'] === 'admin') {
                header('Location: admin.php');
            } else {
                header('Location: usuario.php');
            }
            exit(); // Adiciona exit após redirecionar
        } else {
            echo "Senha inválida.";
        }
    } else {
        echo "Usuário não encontrado.";
    }
}
?>
