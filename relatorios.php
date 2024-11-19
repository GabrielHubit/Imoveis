<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

session_start();
if ($_SESSION['usuario'] !== 'admin') {
    header('Location: index.php');
    exit();
}

include 'db.php';

// Gerar o relatório
if (isset($_GET['ano']) || isset($_GET['mes']) || isset($_GET['usuario_id'])) {
    // Receber os filtros via GET
    $ano = $_GET['ano'] ?? null;
    $mes = $_GET['mes'] ?? null;
    $usuario_id = $_GET['usuario_id'] ?? null;

    // Construir a consulta com base nos filtros
    $queryStr = "SELECT u.id, u.usuario, u.email, u.ativo, p.mes, p.ano, p.pago, u.mensalidade
                 FROM usuarios u
                 LEFT JOIN pagamentos p ON u.id = p.usuario_id
                 WHERE 1";

    // Adicionar filtros à consulta
    if ($ano) {
        $queryStr .= " AND p.ano = :ano";
    }
    if ($mes) {
        $queryStr .= " AND p.mes = :mes";
    }
    if ($usuario_id) {
        $queryStr .= " AND u.id = :usuario_id";
    }

    // Preparar e executar a consulta
    $query = $pdo->prepare($queryStr);

    if ($ano) {
        $query->bindParam(':ano', $ano, PDO::PARAM_INT);
    }
    if ($mes) {
        $query->bindParam(':mes', $mes, PDO::PARAM_INT);
    }
    if ($usuario_id) {
        $query->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    }

    $query->execute();
    $usuarios = $query->fetchAll();

    // Gerar o relatório em Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Cabeçalhos
    $sheet->setCellValue('A1', 'ID');
    $sheet->setCellValue('B1', 'Usuário');
    $sheet->setCellValue('C1', 'E-mail');
    $sheet->setCellValue('D1', 'Status');
    $sheet->setCellValue('E1', 'Mês');
    $sheet->setCellValue('F1', 'Ano');
    $sheet->setCellValue('G1', 'Pagamento');
    $sheet->setCellValue('H1', 'Mensalidade');

    $row = 2; // Começar na segunda linha
    foreach ($usuarios as $usuario) {
        $sheet->setCellValue('A' . $row, $usuario['id']);
        $sheet->setCellValue('B' . $row, $usuario['usuario']);
        $sheet->setCellValue('C' . $row, $usuario['email']);
        $sheet->setCellValue('D' . $row, $usuario['ativo'] ? 'Ativo' : 'Inativo');
        $sheet->setCellValue('E' . $row, $usuario['mes']);
        $sheet->setCellValue('F' . $row, $usuario['ano']);
        $sheet->setCellValue('G' . $row, $usuario['pago'] ? 'Pago' : 'Não Pago');
        $sheet->setCellValue('H' . $row, $usuario['mensalidade']);
        $row++;
    }

    // Gerar o arquivo .xlsx
    $writer = new Xlsx($spreadsheet);
    $filename = 'relatorio_usuarios.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $writer->save('php://output');
    exit();
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerar Relatórios</title>
</head>
<body>
    <h1>Gerar Relatório</h1>
    <form action="relatorios.php" method="GET">
        <label for="filtro_ano">Ano:</label>
        <select name="ano" id="filtro_ano">
            <option value="">Selecione o Ano</option>
            <?php
            // Obter lista de anos disponíveis para filtro
            $query = $pdo->query("SELECT DISTINCT ano FROM pagamentos ORDER BY ano DESC");
            $anos = $query->fetchAll();
            foreach ($anos as $ano): ?>
                <option value="<?php echo $ano['ano']; ?>"><?php echo $ano['ano']; ?></option>
            <?php endforeach; ?>
        </select>
        <br>
        <label for="filtro_mes">Mês:</label>
        <select name="mes" id="filtro_mes">
            <option value="">Selecione o Mês</option>
            <option value="1">Janeiro</option>
            <option value="2">Fevereiro</option>
            <option value="3">Março</option>
            <option value="4">Abril</option>
            <option value="5">Maio</option>
            <option value="6">Junho</option>
            <option value="7">Julho</option>
            <option value="8">Agosto</option>
            <option value="9">Setembro</option>
            <option value="10">Outubro</option>
            <option value="11">Novembro</option>
            <option value="12">Dezembro</option>
        </select>
        <br>
        <label for="filtro_usuario">Usuário:</label>
        <select name="usuario_id" id="filtro_usuario">
            <option value="">Selecione o Usuário</option>
            <?php
            // Obter lista de usuários disponíveis para filtro
            $query = $pdo->query("SELECT id, usuario FROM usuarios ORDER BY usuario");
            $usuarios = $query->fetchAll();
            foreach ($usuarios as $usuario): ?>
                <option value="<?php echo $usuario['id']; ?>"><?php echo $usuario['usuario']; ?></option>
            <?php endforeach; ?>
        </select>
        <br>
        <button type="submit">Gerar Relatório</button>
    </form>

    <p><a href="admin.php">Voltar ao Painel</a></p>
</body>
</html>
