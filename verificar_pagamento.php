<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'admin') {
    header('Location: index.php');
    exit();
}

include 'db.php';

// Verifica se um usuário específico foi solicitado
$usuario_id = isset($_POST['usuario_id']) ? $_POST['usuario_id'] : null;
$query = $pdo->prepare("SELECT * FROM pagamentos WHERE usuario_id = ?");
$query->execute([$usuario_id]);
$pagamentos = $query->fetchAll();

// Aprovar ou recusar pagamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['aprovar']) || isset($_POST['recusar'])) {
        $pagamento_id = $_POST['pagamento_id'];
        $status = isset($_POST['aprovar']) ? 'aprovado' : 'recusado';

        // Define o valor de pago com base no status
        $pago = ($status === 'aprovado') ? 1 : 0;

        // Tente atualizar o pagamento
        try {
            $updateQuery = $pdo->prepare("UPDATE pagamentos SET status = ?, pago = ? WHERE id = ?");
            $updateQuery->execute([$status, $pago, $pagamento_id]);
            // Redireciona para admin.php após a atualização
            header("Location: admin.php");
            exit();
        } catch (PDOException $e) {
            echo "Erro ao atualizar o pagamento: " . htmlspecialchars($e->getMessage());
            exit();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Pagamentos do Usuário</title>
    <script>
        function confirmAction(action) {
            const actionText = action === 'aprovar' ? 'aprovar' : 'recusar';
            return confirm(Tem certeza que deseja ${actionText} este pagamento?);
        }
    </script>
</head>
<body>
    <h1>Pagamentos do Usuário ID: <?php echo htmlspecialchars($usuario_id); ?></h1>
    <table border="1">
        <tr>
            <th>Mês</th>
            <th>Ano</th>
            <th>Status</th>
            <th>Comprovante</th>
            <th>Ações</th>
        </tr>
        <?php foreach ($pagamentos as $pagamento): ?>
        <tr>
            <td><?php echo htmlspecialchars($pagamento['mes']); ?></td>
            <td><?php echo htmlspecialchars($pagamento['ano']); ?></td>
            <td><?php echo $pagamento['pago'] === 1 ? 'Pago' : ($pagamento['pago'] === 0 ? 'Não Pago' : 'Pendente'); ?></td>
            <td>
                <?php if ($pagamento['comprovante']): ?>
                    <a href="<?php echo htmlspecialchars($pagamento['comprovante']); ?>" target="_blank">Ver Comprovante</a>
                <?php endif; ?>
            </td>
            <td>
                <form method="POST" onsubmit="return confirmAction(this.querySelector('button[name=\'aprovar\']') ? 'aprovar' : 'recusar');">
                    <input type="hidden" name="pagamento_id" value="<?php echo $pagamento['id']; ?>">
                    <input type="hidden" name="usuario_id" value="<?php echo $usuario_id; ?>">
                    <button type="submit" name="aprovar">Aprovar</button>
                    <button type="submit" name="recusar">Recusar</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p><a href="admin.php">Voltar</a></p>
</body>
</html>