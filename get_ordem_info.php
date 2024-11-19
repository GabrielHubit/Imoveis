<?php
include 'db.php'; // Inclua o arquivo de conexão ao banco

if (isset($_GET['id'])) {
    $ordem_id = $_GET['id'];

    // Obter informações da ordem de serviço
    $query = $pdo->prepare("SELECT * FROM ordens_servicos WHERE id = ?");
    $query->execute([$ordem_id]);
    $ordem = $query->fetch(PDO::FETCH_ASSOC);

    if ($ordem) {
        // Retornar as informações da ordem de serviço em formato JSON
        echo json_encode([
            'id' => $ordem['id'],
            'nome' => $ordem['nome'],
            'tipo_servico' => $ordem['tipo_servico'],
            'status' => $ordem['status'],
            'descricao' => $ordem['descricao']
        ]);
    } else {
        echo json_encode(['error' => 'Ordem de serviço não encontrada.']);
    }
} else {
    echo json_encode(['error' => 'ID da ordem de serviço não fornecido.']);
}
?>
