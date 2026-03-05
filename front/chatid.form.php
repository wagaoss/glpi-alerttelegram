<?php
// Carrega o core do GLPI
include ("../../../inc/includes.php");

// Verifica se o usuário está logado por questões de segurança
Session::checkLoginUser();

// Instancia a nossa classe
$chatid = new PluginAlerttelegramChatid();

// Verifica se o formulário enviou a requisição de 'update' (botão Salvar)
if (isset($_POST["update"])) {
    // Se o formulário enviou um 'id', significa que o usuário já tinha cadastro, então atualizamos
    if (isset($_POST["id"]) && $_POST["id"] > 0) {
        $chatid->update($_POST);
    } else {
        // Se não enviou 'id', é a primeira vez que o usuário salva, então inserimos um registro novo
        $chatid->add($_POST);
    }
    // Retorna para a tela anterior com uma mensagem de sucesso nativa do GLPI
    Html::back();
}

// Se o arquivo for acessado diretamente sem enviar o formulário, exibe erro e morre
Html::displayErrorAndDie('Acesso não autorizado ou formulário inválido.');