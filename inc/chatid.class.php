<?php
// Impede o acesso direto ao arquivo por segurança
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginAlerttelegramChatid extends CommonDBTM {

    // Aponta para a nossa tabela unificada
    static function getTable($classname = null) {
        return 'glpi_plugin_alerttelegram_chatids';
    }

    static function getTypeName($nb = 0) {
        return 'Telegram Chat ID';
    }

    // A mágica acontece aqui: A aba aparece para os 3 tipos!
    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if (in_array($item->getType(), ['User', 'Group', 'Profile']) && $item->getID() > 0) {
            return 'Telegram Alertas';
        }
        return '';
    }

    // Chama o desenho do formulário passando quem está sendo editado
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if (in_array($item->getType(), ['User', 'Group', 'Profile'])) {
            $chatid = new self();
            // Renomeado para evitar conflito com a classe pai CommonDBTM
            $chatid->showCustomForm($item->getType(), $item->getID());
        }
        return true;
    }

    // Função que desenha a tela
    function showCustomForm($itemtype, $items_id) {
        global $DB;

        $id = 0;
        $chat_id = '';
        
        // Busca se esse item específico já tem um Chat ID salvo
        $iterator = $DB->request([
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'itemtype' => $itemtype,
                'items_id' => $items_id
            ]
        ]);

        if (count($iterator) > 0) {
            $result = $iterator->current();
            $id = $result['id'];
            $chat_id = $result['chat_id'];
        }

        // Aponta para o nosso arquivo que processa o salvamento
        $url = Plugin::getWebDir('alerttelegram') . '/front/chatid.form.php';

        echo "<form method='post' action='$url'>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='2'>Configuração de Notificações via Telegram (" . $itemtype . ")</th></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>Chat ID do Telegram:</td>";
        echo "<td>";
        // Campos ocultos que ensinam ao banco DE QUEM é esse Chat ID
        echo "<input type='hidden' name='itemtype' value='$itemtype'>";
        echo "<input type='hidden' name='items_id' value='$items_id'>";
        if ($id > 0) {
            echo "<input type='hidden' name='id' value='$id'>";
        }
        echo "<input type='text' name='chat_id' value='" . Html::cleanInputText($chat_id) . "' style='width: 300px;'>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td colspan='2' class='center'>";
        echo "<input type='submit' name='update' value='Salvar' class='submit'>";
        
        // CORREÇÃO: Geração do Token de segurança da forma correta exigida pelo GLPI 11
        echo "<input type='hidden' name='_glpi_csrf_token' value='" . Session::getNewCSRFToken() . "'>";
        
        echo "</td>";
        echo "</tr>";

        echo "</table>";
        Html::closeForm();
    }
}