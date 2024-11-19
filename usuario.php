<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// Verifica se o ID do usuário está na sessão
$usuario_id = $_SESSION['usuario_id'] ?? null;
$usuario_nome = $_SESSION['usuario']; // Nome do usuário logado

include 'db.php';

// Processar pagamento (se houver uma requisição POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Registrar Pagamento
    if (isset($_POST['pagar'])) {
        $mes = filter_var($_POST['mes'], FILTER_VALIDATE_INT);
        $ano = filter_var($_POST['ano'], FILTER_VALIDATE_INT);
        $data_inicio = $_POST['data_inicio'];  // Data de início (presumivelmente enviada via formulário)

        // Validação do mês e do ano
        if ($mes < 1 || $mes > 12 || $ano < 2000 || $ano > date("Y")) {
            echo "Mês ou ano inválido.";
        } else {
            // Calcular a data de vencimento (30 dias após data_inicio)
            $vencimento = date('Y-m-d', strtotime("$data_inicio +30 days"));

            // Verificar se faltam 7 dias para o vencimento
            $data_atual = date('Y-m-d');
            $intervalo = (strtotime($vencimento) - strtotime($data_atual)) / 86400; // Diferença em dias

            if ($intervalo <= 7) {
                echo "Atenção: O boleto vence em $intervalo dias.";
            }

            // Processar o arquivo de pagamento
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['comprovante']['tmp_name'];
                $fileName = $_FILES['comprovante']['name'];
                $fileNameParts = pathinfo($fileName);
                $fileExtension = strtolower($fileNameParts['extension']);

                // Verifique se a extensão é permitida (por tipo MIME)
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
                $mimeType = mime_content_type($fileTmpPath);
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];

                if (in_array($fileExtension, $allowedExtensions) && in_array($mimeType, $allowedMimeTypes)) {
                    // Mover o arquivo para a pasta de destino
                    $uploadFileDir = './uploads/';
                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }
                    $destPath = $uploadFileDir . uniqid() . '.' . $fileExtension;

                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        // Inserir o pagamento no banco de dados
                        $query = $pdo->prepare("INSERT INTO pagamentos (usuario_id, mes, ano, pago, comprovante, data_inicio, vencimento) 
                                                VALUES (?, ?, ?, 1, ?, ?, ?)");
                        $query->execute([$usuario_id, $mes, $ano, $destPath, $data_inicio, $vencimento]);

                        echo "Pagamento registrado com sucesso. O boleto vence em $vencimento.";
                    } else {
                        echo "Erro ao mover o arquivo para a pasta de destino.";
                    }
                } else {
                    echo "Formato de arquivo não permitido ou MIME type inválido.";
                }
            } else {
                echo "Erro no upload do arquivo.";
            }
        }
    }

    // Processar Ordem de Serviço ou Reembolso
    if (isset($_POST['abrir_os']) || isset($_POST['solicitar_reembolso'])) {
        $nome = $usuario_nome; // Nome do usuário logado
        
        // Verificando 'tipo_servico' e 'descricao' antes de usar
        $tipo_servico = $_POST['tipo_servico'] ?? ''; // Se não enviado, valor vazio
        $descricao = filter_var($_POST['descricao'] ?? '', FILTER_SANITIZE_STRING); // Garantir que 'descricao' esteja definida e seja segura
        $data_os = $_POST['data_os'] ?? date('Y-m-d'); // Data da OS (se não estiver enviada, usa a data atual)

        // Processamento do arquivo de comprovante
        if (isset($_FILES['comprovante_os']) && $_FILES['comprovante_os']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['comprovante_os']['tmp_name'];
            $fileName = $_FILES['comprovante_os']['name'];
            $fileNameParts = pathinfo($fileName);
            $fileExtension = strtolower($fileNameParts['extension']);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];

            // Verifique se a extensão e o MIME são válidos
            $mimeType = mime_content_type($fileTmpPath);
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];

            if (in_array($fileExtension, $allowedExtensions) && in_array($mimeType, $allowedMimeTypes)) {
                $uploadFileDir = './uploads/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }
                $destPath = $uploadFileDir . uniqid() . '.' . $fileExtension;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    // Salvar no banco de dados
                    $status_os = "aberto"; // Status inicial da OS
                    $query = $pdo->prepare("INSERT INTO ordens_servicos (usuario_id, nome, tipo_servico, descricao, data_os, status, comprovante) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $query->execute([$usuario_id, $nome, $tipo_servico, $descricao, $data_os, $status_os, $destPath]);

                    echo "Ordem de Serviço registrada com sucesso.";
                } else {
                    echo "Erro ao mover o arquivo para a pasta de destino.";
                }
            } else {
                echo "Formato de arquivo não permitido ou MIME type inválido.";
            }
        }

        // Processar Reembolso
        if (isset($_POST['solicitar_reembolso'])) {
            $os_id = filter_var($_POST['os_id'], FILTER_VALIDATE_INT);
            $descricao_reembolso = filter_var($_POST['descricao_reembolso'], FILTER_SANITIZE_STRING);

            // Processar o arquivo de comprovante de reembolso
            if (isset($_FILES['comprovante_reembolso']) && $_FILES['comprovante_reembolso']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['comprovante_reembolso']['tmp_name'];
                $fileName = $_FILES['comprovante_reembolso']['name'];
                $fileNameParts = pathinfo($fileName);
                $fileExtension = strtolower($fileNameParts['extension']);
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];

                // Verifique se a extensão e o MIME são válidos
                $mimeType = mime_content_type($fileTmpPath);
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];

                if (in_array($fileExtension, $allowedExtensions) && in_array($mimeType, $allowedMimeTypes)) {
                    // Mover o arquivo para a pasta de destino
                    $uploadFileDir = './uploads/';
                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }
                    $destPath = $uploadFileDir . uniqid() . '.' . $fileExtension;

                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        // Atualizar a OS com informações de reembolso
                        $query = $pdo->prepare("UPDATE ordens_servicos SET reembolso = 1, descricao_reembolso = ?, comprovante_reembolso = ? WHERE id = ?");
                        $query->execute([$descricao_reembolso, $destPath, $os_id]);

                        echo "Reembolso solicitado com sucesso.";
                    } else {
                        echo "Erro ao mover o arquivo para a pasta de destino.";
                    }
                } else {
                    echo "Formato de arquivo não permitido ou MIME type inválido.";
                }
            } else {
                echo "Erro no upload do arquivo de reembolso.";
            }
        }
    }
}

// Obter pagamentos do usuário
$query = $pdo->prepare("SELECT * FROM pagamentos WHERE usuario_id = ?");
$query->execute([$usuario_id]);
$pagamentos = $query->fetchAll(PDO::FETCH_ASSOC);

// Obter ordens de serviço
$query_os = $pdo->prepare("SELECT * FROM ordens_servicos WHERE usuario_id = ?");
$query_os->execute([$usuario_id]);
$ordens_servicos = $query_os->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- HTML -->
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel do Usuário</title>
	<link rel="stylesheet" href="styles.css">

    <style>
        .tab-container {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            background-color: #f1f1f1;
            border: 1px solid #ddd;
            cursor: pointer;
        }
        .tab.active {
            background-color: #ddd;
            font-weight: bold;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .form-container {
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }

        /* Estilos para a caixa de diálogo/modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            padding-top: 60px;
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
        }
        .modal-header {
            font-size: 1.5em;
        }
        .modal-footer {
            margin-top: 20px;
            text-align: right;
        }
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <h1>Painel do Usuário</h1>
    <p>Bem-vindo, <?php echo $_SESSION['usuario']; ?>!</p>

    <!-- Abas para navegação -->
    <div class="tab-container">
        <div class="tab active" onclick="showTab('registrar')">Registrar Pagamento</div>
        <div class="tab" onclick="showTab('pagamentos')">Meus Pagamentos</div>
        <div class="tab" onclick="showTab('ordem_servico')">Ordem de Serviços</div>
    </div>

    <!-- Conteúdo das abas -->
    <div id="registrar" class="tab-content active">
        <h2>Registrar Pagamento</h2>
        <button onclick="openPaymentModal()">Registrar Pagamento</button>

        <!-- Modal para registrar pagamento -->
        <div id="paymentModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="close" onclick="closePaymentModal()">&times;</span>
                    <h2>Aviso: Pague sua Mensalidade</h2>
                </div>
                <div class="modal-body">
                    <p>Por favor, efetue o pagamento da mensalidade de aluguel e faça o upload do comprovante.</p>
                    <form action="usuario.php" method="POST" enctype="multipart/form-data">
                        <label for="mes">Mês:</label>
                        <input type="number" name="mes" min="1" max="12" required>
                        <br>
                        <label for="ano">Ano:</label>
                        <input type="number" name="ano" min="2000" max="<?php echo date('Y'); ?>" required>
                        <br>
                        <label for="comprovante">Comprovante de pagamento:</label>
                        <input type="file" name="comprovante" accept=".jpg, .jpeg, .png, .pdf" required>
                        <br><br>
                        <button type="submit" name="pagar">Subir Comprovante</button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button onclick="generateBoleto()">Gerar Boleto</button>
                    <button onclick="closePaymentModal()">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="pagamentos" class="tab-content">
        <h2>Meus Pagamentos</h2>
        <table>
            <tr>
                <th>Mês</th>
                <th>Ano</th>
                <th>Status</th>
                <th>Comprovante</th>
            </tr>
            <?php foreach ($pagamentos as $pagamento): ?>
            <tr>
                <td><?php echo $pagamento['mes']; ?></td>
                <td><?php echo $pagamento['ano']; ?></td>
                <td><?php echo $pagamento['pago'] ? 'Pago' : 'Não Pago'; ?></td>
                <td>
                    <?php if ($pagamento['comprovante']): ?>
                        <a href="<?php echo $pagamento['comprovante']; ?>" target="_blank">Ver Comprovante</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div id="ordem_servico" class="tab-content">
        <h2>Ordens de Serviço</h2>
        <button onclick="showTabForm('abrir_os_form')">Abrir OS</button>
        <button onclick="showTabForm('reembolso_form')">Solicitar Reembolso</button>

        <!-- Formulário de abrir OS -->
        <div id="abrir_os_form" class="form-container tab-content">
            <h3>Abrir Ordem de Serviço</h3>
            <form action="usuario.php" method="POST" enctype="multipart/form-data">
                <label for="nome">Nome Completo:</label>
                <input type="text" name="nome" value="<?php echo $usuario_nome; ?>" readonly>
                <br>
                <label for="tipo_servico">Tipo de Serviço:</label>
                <select name="tipo_servico">
                    <option value="melhoria">Melhoria</option>
                    <option value="conserto">Conserto</option>
                </select>
                <br>
                <label for="descricao">Descrição (até 500 palavras):</label>
                <textarea name="descricao" maxlength="500" required></textarea>
                <br>
                <label for="comprovante_os">Comprovante:</label>
                <input type="file" name="comprovante_os" accept=".jpg, .jpeg, .png, .pdf" required>
                <br>
                <label for="data_os">Data:</label>
                <input type="date" name="data_os" value="<?php echo date('Y-m-d'); ?>" required>
                <br>
                <button type="submit" name="abrir_os">Abrir OS</button>
            </form>
        </div>

        <!-- Formulário de Solicitação de Reembolso -->
        <div id="reembolso_form" class="form-container tab-content">
            <h3>Solicitar Reembolso</h3>
            <form action="usuario.php" method="POST" enctype="multipart/form-data">
                <label for="os_id">ID da OS:</label>
                <input type="number" name="os_id" required>
                <br>
                <label for="descricao_reembolso">Descrição do Reembolso:</label>
                <textarea name="descricao_reembolso" required></textarea>
                <br>
                <label for="comprovante_reembolso">Comprovante:</label>
                <input type="file" name="comprovante_reembolso" accept=".jpg, .jpeg, .png, .pdf" required>
                <br>
                <button type="submit" name="solicitar_reembolso">Solicitar Reembolso</button>
            </form>
        </div>

        <!-- Tabela de Ordens de Serviço -->
        <h3>Ordens de Serviço Abertas</h3>
        <table>
            <tr>
                <th>ID da OS</th>
                <th>Nome</th>
                <th>Data de Abertura</th>
                <th>Status</th>
                <th>Reembolso</th>
            </tr>
            <?php foreach ($ordens_servicos as $os): ?>
            <tr>
                <td><?php echo $os['id']; ?></td>
                <td><?php echo $os['nome']; ?></td>
                <td><?php echo $os['data_os']; ?></td>
                <td><?php echo ucfirst($os['status']); ?></td>
                <td>
                    <?php if ($os['reembolso'] == 1): ?>
                        <span style="color: green;">Reembolso Solicitado</span>
                    <?php else: ?>
                        <span style="color: red;">Sem Reembolso</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <p><a href="index.php">Sair</a></p>

    <script>
        function showTab(tabName) {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            // Remover a classe 'active' de todas as abas e conteúdo
            tabs.forEach(tab => tab.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'))
            // Adicionar a classe 'active' para a aba e conteúdo ativos
            document.querySelector(`[onclick="showTab('${tabName}')"]`).classList.add('active');
            document.getElementById(tabName).classList.add('active');
        }

        function showTabForm(formId) {
            // Esconde todos os formulários e mostra o formulário selecionado
            const forms = document.querySelectorAll('.form-container');
            forms.forEach(form => form.classList.remove('active'));

            // Exibir o formulário específico
            document.getElementById(formId).classList.add('active');
        }

        // Função para abrir a modal
        function openPaymentModal() {
            document.getElementById("paymentModal").style.display = "block";
        }

        // Função para fechar a modal
        function closePaymentModal() {
            document.getElementById("paymentModal").style.display = "none";
        }

        // Função para gerar boleto (apenas placeholder)
        function generateBoleto() {
            alert('Gerando boleto...');
        }
    </script>

 <script>

// Função para verificar e alertar sobre boletos próximos do vencimento para o usuário
function verificarAlertaVencimentoUsuario($usuario_id) {
    $conexao = new mysqli("localhost", "usuario", "senha", "banco_de_dados");
    $hoje = date("Y-m-d");
    $data_alerta = date("Y-m-d", strtotime($hoje . ' + 7 days'));

    $sql = "SELECT valor, data_vencimento FROM boletos WHERE cliente_id = ? AND data_vencimento = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("is", $usuario_id, $data_alerta);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        echo "Alerta: O boleto com valor de R$ " . $row['valor'] . " vence em 7 dias (vencimento em " . $row['data_vencimento'] . ").<br>";
    }

    $stmt->close();
    $conexao->close();
}

// Chamada da função para exibir o alerta ao usuário
$usuario_id = 123; // Substitua com o ID real do usuário
verificarAlertaVencimentoUsuario($usuario_id);

</script>

</body>
</html>
