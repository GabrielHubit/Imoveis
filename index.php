<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
	<link rel="stylesheet" href="styles.css">

</head>
<body>
    <h1>Login</h1>
    <form action="processar_login.php" method="POST">
        <label for="usuario">Usuário:</label>
        <input type="text" name="usuario" required>
        <br>
        <label for="senha">Senha:</label>
        <input type="password" name="senha" required>
        <br>
        <button type="submit">Entrar</button>
    </form>
    <p><a href="cadastro.php">Cadastrar Usuário</a></p>
</body>
</html>
