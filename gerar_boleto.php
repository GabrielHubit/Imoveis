<?php
// Arquivo usuario.php

// Inclua o OpenBoleto (certifique-se de que o OpenBoleto está instalado e disponível)
require_once 'vendor/autoload.php';
use OpenBoleto\Banco\BancoDoBrasil;
use OpenBoleto\Agente;

function registrar_pagamento($conexao, $usuario_id) {
    // Recupera os dados do usuário no banco de dados
    $query = "SELECT * FROM usuarios WHERE id = ?";
    $stmt = $conexao->prepare($query);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $usuario = $resultado->fetch_assoc();

    if (!$usuario) {
        echo "Usuário não encontrado.";
        return;
    }

    // Dados do sacado (quem paga o boleto) usando as informações do usuário
    $sacado = new Agente(
        $usuario['nome'], 
        $usuario['cpf'], 
        $usuario['endereco'], 
        '', 
        '', 
        $usuario['email']
    );

    // Dados do cedente (quem emite o boleto)
    $cedente = new Agente('Minha Empresa', '123.456.789-09', 'Rua Exemplo, 123', '12345-678', 'Cidade', 'UF');

    // Cria o boleto com os dados
    $boleto = new BancoDoBrasil(array(
        'dataVencimento' => new DateTime($usuario['data_vencimento']),
        'valor' => $usuario['mensalidade'],
        'sacado' => $sacado,
        'cedente' => $cedente,
        'agencia' => '1234', // Dados fictícios de agência e conta
        'carteira' => '18',
        'conta' => '12345',
        'convenio' => '1234567',
        'numeroDocumento' => $usuario['id'],
    ));

    // Exibe o boleto
    echo $boleto->getOutput();
}
