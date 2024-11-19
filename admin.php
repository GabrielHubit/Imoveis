<?php
session_start();
if ($_SESSION['usuario'] !== 'admin') {
    header('Location: index.php');
    exit();
}

include 'db.php'; // Arquivo de conexão PDO

// Processar cadastro de novo usuário (usuário comum)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cadastrar_usuario'])) {
        $nome = $_POST['nome'];
        $cpf = $_POST['cpf'];
        $endereco = $_POST['endereco'];
        $usuario = $_POST['usuario'];
        $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $email = $_POST['email'];
        $data_inicio = $_POST['data_inicio'];
        $tempo_contrato = $_POST['tempo_contrato'];

        // Calcular a data de vencimento
        $data_vencimento = date('Y-m-d', strtotime("+$tempo_contrato months", strtotime($data_inicio)));

        // Mensalidade (se necessário, converta e formate o valor)
        $mensalidade = $_POST['mensalidade'];

        // Inserir no banco de dados usando PDO
        $sql = "INSERT INTO usuarios (nome, cpf, endereco, usuario, senha, email, data_inicio, tempo_contrato, data_vencimento, mensalidade) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $cpf, $endereco, $usuario, $senha, $email, $data_inicio, $tempo_contrato, $data_vencimento, $mensalidade]);

        echo "Novo usuário cadastrado com sucesso!";
    } elseif (isset($_POST['cadastrar_funcionario'])) {
        // Cadastrar administrador (funcionário)
        $nome = $_POST['nome'];
        $usuario = $_POST['usuario'];
        $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $email = $_POST['email'];

        // Inserir administrador (funcionário) na tabela 'funcionarios'
        $query = $pdo->prepare("INSERT INTO funcionarios (nome, usuario, senha, email, ativo) VALUES (?, ?, ?, ?, 1)");
        $query->execute([$nome, $usuario, $senha, $email]);
    } elseif (isset($_POST['toggle_ativo_usuario'])) {
        // Alterar o status de um usuário
        $usuario_id = $_POST['usuario_id'];
        $query = $pdo->prepare("UPDATE usuarios SET ativo = NOT ativo WHERE id = ?");
        $query->execute([$usuario_id]);
    } elseif (isset($_POST['toggle_ativo_funcionario'])) {
        // Alterar o status de um administrador (funcionário)
        $funcionario_id = $_POST['funcionario_id'];
        $query = $pdo->prepare("UPDATE funcionarios SET ativo = NOT ativo WHERE id = ?");
        $query->execute([$funcionario_id]);
    } elseif (isset($_POST['interagir_ordem'])) {
        // Interagir com ordem de serviço
        $ordem_id = $_POST['ordem_id'];
        $atividade = $_POST['atividade']; // Descrição da atividade

        // Atualizar ordem de serviço com descrição da atividade
        $query = $pdo->prepare("UPDATE ordens_servicos SET descricao = ?, status = 'andamento' WHERE id = ?");
        $query->execute([$atividade, $ordem_id]);

        // Processar upload de arquivo (comprovante)
        if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
            $arquivo_tmp = $_FILES['comprovante']['tmp_name'];
            $nome_arquivo = $_FILES['comprovante']['name'];

            // Caminho para salvar o arquivo
            $destino = 'uploads/' . $nome_arquivo;
            move_uploaded_file($arquivo_tmp, $destino);

            // Salvar o caminho do arquivo no banco de dados
            $query = $pdo->prepare("UPDATE ordens_servicos SET comprovante = ? WHERE id = ?");
            $query->execute([$destino, $ordem_id]);
        }
    } elseif (isset($_POST['finalizar_ordem'])) {
        // Finalizar ordem de serviço
        $ordem_id = $_POST['ordem_id'];

        // Atualizar o status da ordem de serviço para 'finalizado'
        $query = $pdo->prepare("UPDATE ordens_servicos SET status = 'finalizado' WHERE id = ?");
        $query->execute([$ordem_id]);

        // Opcional: Desabilitar a ordem para evitar novas interações
        $query = $pdo->prepare("UPDATE ordens_servicos SET ativo = 0 WHERE id = ?");
        $query->execute([$ordem_id]);
    } elseif (isset($_POST['aprovar_reembolso'])) {
        // Aprovar ou recusar reembolso
        $ordem_id = $_POST['ordem_id'];
        $aprovar = $_POST['aprovar']; // 1 para aprovar, 0 para recusar

        // Atualizar status de reembolso
        $descricao_reembolso = $_POST['descricao_reembolso']; // Descrição do reembolso
        $query = $pdo->prepare("UPDATE ordens_servicos SET reembolso = ?, descricao_reembolso = ? WHERE id = ?");
        $query->execute([$aprovar, $descricao_reembolso, $ordem_id]);

        // Processar upload de arquivo de comprovante de reembolso
        if (isset($_FILES['comprovante_reembolso']) && $_FILES['comprovante_reembolso']['error'] === UPLOAD_ERR_OK) {
            $arquivo_tmp = $_FILES['comprovante_reembolso']['tmp_name'];
            $nome_arquivo = $_FILES['comprovante_reembolso']['name'];

            // Caminho para salvar o arquivo
            $destino = 'uploads/' . $nome_arquivo;
            move_uploaded_file($arquivo_tmp, $destino);

            // Salvar o caminho do arquivo no banco
            $query = $pdo->prepare("UPDATE ordens_servicos SET comprovante_reembolso = ? WHERE id = ?");
            $query->execute([$destino, $ordem_id]);
        }
    }
}

// Obter lista de usuários
$query = $pdo->query("SELECT * FROM usuarios");
$usuarios = $query->fetchAll();

// Obter lista de funcionários (administradores)
$query = $pdo->query("SELECT * FROM funcionarios");
$funcionarios = $query->fetchAll();

// Obter lista de ordens de serviço
$query = $pdo->query("SELECT * FROM ordens_servicos");
$ordens = $query->fetchAll();

// Obter lista de anos para o filtro de ano
$query = $pdo->query("SELECT DISTINCT ano FROM pagamentos ORDER BY ano DESC");
$anos = $query->fetchAll();
?>



<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel Admin</title>
	<link rel="stylesheet" href="styles.css">

    <style>
        /* Estilo básico para as abas */
        .tab-container {
            display: flex;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            background-color: #f0f0f0;
            cursor: pointer;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-right: 5px;
        }
        .tab:hover {
            background-color: #ddd;
        }
        .tab-content {
            display: none;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .tab-content.active {
            display: block;
        }
    </style>
    <script>
        // Função para trocar de aba
        function showTab(tabIndex) {
            var tabs = document.querySelectorAll('.tab');
            var contents = document.querySelectorAll('.tab-content');

            tabs.forEach(function(tab, index) {
                tab.classList.remove('active');
                contents[index].classList.remove('active');
            });

            tabs[tabIndex].classList.add('active');
            contents[tabIndex].classList.add('active');
        }

        // Mostrar a primeira aba por padrão
        window.onload = function() {
            showTab(0);
        };
    </script>
</head>
<body>
    <h1>Painel do Administrador</h1>

    <!-- Abas de navegação -->
    <div class="tab-container">
        <div class="tab active" onclick="showTab(0)">Cadastrar Usuário</div>
        <div class="tab" onclick="showTab(1)">Cadastrar Funcionário</div>
        <div class="tab" onclick="showTab(2)">Usuários Cadastrados</div>
        <div class="tab" onclick="showTab(3)">Funcionários Cadastrados</div>
        <div class="tab" onclick="showTab(4)">Ordens de Serviço</div>
        <div class="tab" onclick="showTab(5)">Gerar Relatório</div> <!-- Nova Aba -->
    </div>

    <!-- Conteúdo das abas -->

   <!-- Formulário de cadastro de usuário -->
    <div class="tab-content">
        <h2>Cadastrar Novo Usuário</h2>
        <form action="admin.php" method="POST">
            <label for="nome">Nome:</label>
            <input type="text" name="nome" required>
            <br>
            <label for="cpf">CPF:</label>
            <input type="text" name="cpf" required>
            <br>
            <label for="endereco">Endereço:</label>
            <textarea name="endereco" required></textarea>
            <br>
            <label for="usuario">Usuário:</label>
            <input type="text" name="usuario" required>
            <br>
            <label for="senha">Senha:</label>
            <input type="password" name="senha" required>
            <br>
            <label for="email">E-mail:</label>
            <input type="email" name="email" required>
            <br>
			<label for="data_inicio">Data de Início:</label>
			<input type="date" name="data_inicio" id="data_inicio" required>
			<br>
			<label for="tempo_contrato">Tempo de Contrato (meses):</label>
			<input type="number" name="tempo_contrato" id="tempo_contrato" required min="1">
			<br>
			<label for="data_vencimento">Data de Vencimento:</label>
			<input type="date" name="data_vencimento" id="data_vencimento" readonly>
			<br>
            <label for="mensalidade">Mensalidade:</label>
            <input type="number" step="0.01" name="mensalidade" required>
            <br>
            <button type="submit" name="cadastrar_usuario">Cadastrar</button>
        </form>
    </div>



    <!-- Aba de cadastro de funcionário -->
    <div class="tab-content">
        <h2>Cadastrar Novo Funcionário (Administrador)</h2>
        <form action="admin.php" method="POST">
            <label for="nome">Nome:</label>
            <input type="text" name="nome" required>
            <br>
            <label for="usuario">Usuário:</label>
            <input type="text" name="usuario" required>
            <br>
            <label for="senha">Senha:</label>
            <input type="password" name="senha" required>
            <br>
            <label for="email">E-mail:</label>
            <input type="email" name="email" required>
            <br>
            <button type="submit" name="cadastrar_funcionario">Cadastrar</button>
        </form>
    </div>

    <!-- Aba de usuários cadastrados -->
     <!-- Tabela de usuários cadastrados -->
    <div class="tab-content">
        <h2>Usuários Cadastrados</h2>
        <table border="1">
            <tr>
                <th>ID</th>
                <th>Nome</th> <!-- Novo campo -->
                <th>CPF</th>  <!-- Novo campo -->
                <th>Endereço</th> <!-- Novo campo -->
                <th>Usuário</th>
                <th>E-mail</th>
                <th>Mensalidade</th>
                <th>Status</th>
                <th>Pagamento</th>
                <th>Ações</th>
            </tr>

            <!-- Exibição dos usuários cadastrados -->
            <?php foreach ($usuarios as $usuario): ?>
            <tr>
                <td><?php echo $usuario['id']; ?></td>
                <td><?php echo $usuario['nome']; ?></td> <!-- Exibindo o nome -->
                <td><?php echo $usuario['cpf']; ?></td> <!-- Exibindo o CPF -->
                <td><?php echo $usuario['endereco']; ?></td> <!-- Exibindo o endereço -->
                <td><?php echo $usuario['usuario']; ?></td>
                <td><?php echo $usuario['email']; ?></td>
                <td><?php echo number_format($usuario['mensalidade'], 2, ',', '.'); ?></td>
                <td>
                    <form action="admin.php" method="POST" style="display:inline;">
                        <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                        <button type="submit" name="toggle_ativo_usuario">
                            <?php echo $usuario['ativo'] ? 'Inativar' : 'Ativar'; ?>
                        </button>
                    </form>
                </td>
                <td>
                    <form action="verificar_pagamento.php" method="POST" style="display:inline;">
                        <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                        <button type="submit">Verificar Pagamento</button>
                    </form>
                </td>
                <td>
                    <!-- Ações como editar ou excluir podem ser colocadas aqui -->
					
					
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Aba de funcionários cadastrados -->
    <div class="tab-content">
        <h2>Funcionários (Administradores) Cadastrados</h2>
        <table border="1">
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Usuário</th>
                <th>E-mail</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
            <?php foreach ($funcionarios as $funcionario): ?>
            <tr>
                <td><?php echo $funcionario['id']; ?></td>
                <td><?php echo $funcionario['nome']; ?></td>
                <td><?php echo $funcionario['usuario']; ?></td>
                <td><?php echo $funcionario['email']; ?></td>
                <td>
                    <form action="admin.php" method="POST" style="display:inline;">
                        <input type="hidden" name="funcionario_id" value="<?php echo $funcionario['id']; ?>">
                        <button type="submit" name="toggle_ativo_funcionario">
                            <?php echo $funcionario['ativo'] ? 'Inativar' : 'Ativar'; ?>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

<!-- Aba de ordens de serviço -->
<div class="tab-content">
    <h2>Ordens de Serviço</h2>
    <table border="1">
        <tr>
            <th>ID</th>
            <th>Nome do Usuário</th>
            <th>Tipo da OS</th>
            <th>Status</th>
            <th>Data de Abertura</th>
            <th>Ações</th>
        </tr>
        <?php foreach ($ordens as $ordem): ?>
        <tr>
            <td><?php echo $ordem['id']; ?></td>
            <td><?php echo $ordem['nome']; ?></td>
            <td><?php echo ucfirst($ordem['tipo_servico']); ?></td>
            <td><?php echo ucfirst($ordem['status']); ?></td>
            <td><?php echo date('d/m/Y', strtotime($ordem['data_criacao'])); ?></td>
            <td>
                <!-- Botão de interagir -->
                <button onclick="openModal(<?php echo $ordem['id']; ?>)">Interagir</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<!-- Modal para interagir com a ordem de serviço -->
<div id="modal" style="display: none;">
    <div id="modal-content">
        <span id="close-modal" onclick="closeModal()">&times;</span>
        <h3>Interagir com a Ordem de Serviço</h3>
        <form action="admin.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="ordem_id" id="ordem_id">
            
            <!-- Informações da Ordem de Serviço -->
            <p><strong>Nome do Usuário:</strong> <span id="usuario_nome"></span></p>
            <p><strong>Tipo de Serviço:</strong> <span id="tipo_servico"></span></p>
            <p><strong>Status:</strong> <span id="status_os"></span></p>
            <p><strong>Descrição do Chamado:</strong> <span id="descricao_chamado"></span></p>

            <!-- Campo de interação -->
            <textarea name="atividade" placeholder="Descreva a atividade..." required></textarea>
            <br>
            <input type="file" name="comprovante">
            <br>
            <button type="submit" name="interagir_ordem">Interagir</button>
            <br>

            <!-- Novo botão para finalizar a ordem de serviço -->
            <button type="submit" name="finalizar_ordem" id="finalizar-btn" onclick="return confirm('Tem certeza que deseja finalizar esta ordem de serviço?');">Finalizar Chamado</button>
        </form>
    </div>
</div>

<!-- Modal para interagir com a ordem de serviço -->
<div id="modal" style="display: none;">
    <div id="modal-content">
        <span id="close-modal" onclick="closeModal()">&times;</span>
        <h3>Interagir com a Ordem de Serviço</h3>
        <form action="admin.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="ordem_id" id="ordem_id">
            
            <!-- Informações da Ordem de Serviço -->
            <p><strong>Nome do Usuário:</strong> <span id="usuario_nome"></span></p>
            <p><strong>Tipo de Serviço:</strong> <span id="tipo_servico"></span></p>
            <p><strong>Status:</strong> <span id="status_os"></span></p>
            <p><strong>Descrição do Chamado:</strong> <span id="descricao_chamado"></span></p>

            <!-- Campo de interação -->
            <textarea name="atividade" id="atividade" placeholder="Descreva a atividade..." required></textarea>
            <br>
            <input type="file" name="comprovante" id="comprovante">
            <br>

            <!-- Botão de interação, será desabilitado se a ordem for finalizada -->
            <button type="submit" name="interagir_ordem" id="interagir-btn">Interagir</button>
            <br>

            <!-- Novo botão para finalizar a ordem de serviço -->
            <button type="submit" name="finalizar_ordem" id="finalizar-btn" onclick="return confirm('Tem certeza que deseja finalizar esta ordem de serviço?');">Finalizar Chamado</button>
        </form>
    </div>
</div>


<script>
function openModal(ordem_id) {
    document.getElementById('modal').style.display = 'block';

    // Fazer requisição AJAX para buscar as informações da ordem de serviço
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'get_ordem_info.php?id=' + ordem_id, true);
    xhr.onload = function() {
        if (xhr.status == 200) {
            var ordem = JSON.parse(xhr.responseText);
            document.getElementById('ordem_id').value = ordem.id;
            document.getElementById('usuario_nome').textContent = ordem.nome;
            document.getElementById('tipo_servico').textContent = ordem.tipo_servico;
            document.getElementById('status_os').textContent = ordem.status;
            document.getElementById('descricao_chamado').textContent = ordem.descricao || 'Nenhuma descrição fornecida.';

            // Verificar se a ordem de serviço está finalizada
            if (ordem.status === 'finalizado') {
                // Desabilitar os campos de interação e o botão de "Interagir"
                document.getElementById('atividade').disabled = true;
                document.getElementById('comprovante').disabled = true;
                document.getElementById('interagir-btn').disabled = true;
                document.getElementById('finalizar-btn').disabled = true;  // Desabilita o botão de finalizar
                alert('Esta ordem de serviço já foi finalizada e não pode ser modificada.');
            } else {
                // Habilitar os campos de interação caso a ordem esteja em aberto ou em andamento
                document.getElementById('atividade').disabled = false;
                document.getElementById('comprovante').disabled = false;
                document.getElementById('interagir-btn').disabled = false;
                document.getElementById('finalizar-btn').disabled = false;  // Habilita o botão de finalizar
            }
        }
    };
    xhr.send();
}

// Função para fechar o modal
function closeModal() {
    document.getElementById('modal').style.display = 'none';
}

// Função para calcular a data de vencimento
document.getElementById('tempo_contrato').addEventListener('input', function() {
    var data_inicio = document.getElementById('data_inicio').value;
    var tempo_contrato = parseInt(this.value);

    // Verifica se os dois campos (data_inicio e tempo_contrato) têm valor
    if (data_inicio && tempo_contrato) {
        var dateInicio = new Date(data_inicio);
        dateInicio.setMonth(dateInicio.getMonth() + tempo_contrato); // Soma os meses ao tempo de contrato
        var data_vencimento = dateInicio.toISOString().split('T')[0]; // Formata para o formato YYYY-MM-DD
        document.getElementById('data_vencimento').value = data_vencimento;
    }
});


</script>

    <!-- Aba de geração de relatório -->
    <div class="tab-content">
        <h2>Gerar Relatório</h2>
        <form action="relatorios.php" method="GET">
            <label for="filtro_ano">Ano:</label>
            <select name="ano" id="filtro_ano">
                <option value="">Selecione o Ano</option>
                <?php foreach ($anos as $ano): ?>
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
                <?php foreach ($usuarios as $usuario): ?>
                    <option value="<?php echo $usuario['id']; ?>"><?php echo $usuario['usuario']; ?></option>
                <?php endforeach; ?>
            </select>
            <br>
            <button type="submit">Gerar Relatório</button>
        </form>
    </div>

    <p><a href="index.php">Sair</a></p>

<script>
// Função para gerar boleto automático com vencimento em 30 dias
function gerarBoletoAutomatico($cliente_id, $valor_boleto) {
    $data_cadastro = date("Y-m-d"); // Data atual
    $data_vencimento = date("Y-m-d", strtotime($data_cadastro . ' + 30 days'));

    // Incluindo o arquivo gerar_boleto.php para criar o boleto
    include 'gerar_boleto.php';
    criarBoleto($cliente_id, $valor_boleto, $data_vencimento);
}

// Exemplo de chamada da função logo após o cadastro do cliente
// Supondo que o cadastro do cliente já gere um ID e valor
$cliente_id = 123;  // Substitua com o ID real do cliente cadastrado
$valor_boleto = 150.00;  // Substitua com o valor desejado para o boleto
gerarBoletoAutomatico($cliente_id, $valor_boleto);
</script>
</body>
</html>