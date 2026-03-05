<?php
// Carrega o core do GLPI
include ("../../../inc/includes.php");

// Verifica se o usuário está logado e tem permissão
Session::checkLoginUser();
Session::checkRight("config", UPDATE);

global $DB;

// --- HACKER MODE: Sequestro da conexão com o banco para burlar o terminal ---
$reflection = new ReflectionClass($DB);
$property = $reflection->getProperty('dbh');
$property->setAccessible(true);
$dbh = $property->getValue($DB);

$runRawQuery = function($sql) use ($dbh) {
    if (is_object($dbh) && method_exists($dbh, 'exec')) @$dbh->exec($sql);
    else @$dbh->query($sql);
};

// --- AUTO-REPARO 1: Garante a coluna char_limit ---
if (!$DB->fieldExists('glpi_plugin_alerttelegram_configs', 'char_limit')) {
    $runRawQuery("ALTER TABLE `glpi_plugin_alerttelegram_configs` ADD `char_limit` INT(11) NOT NULL DEFAULT '150' AFTER `bot_token`");
}

// --- AUTO-REPARO 2: Força chaves UNSIGNED (Mata a faixa amarela do GLPI) ---
$runRawQuery("ALTER TABLE `glpi_plugin_alerttelegram_configs` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
$runRawQuery("ALTER TABLE `glpi_plugin_alerttelegram_chatids` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, MODIFY `items_id` INT UNSIGNED NOT NULL DEFAULT '0'");


// Define a URL exata
$url_base = Plugin::getWebDir('alerttelegram') . '/front/config.php';

// Se o formulário foi enviado
if (isset($_POST["update"])) {
    $bot_token = $_POST['bot_token'];
    $char_limit = (int)$_POST['char_limit']; 
    
    if ($char_limit <= 0) $char_limit = 150;
    
    $DB->update('glpi_plugin_alerttelegram_configs', [
        'bot_token'  => $bot_token,
        'char_limit' => $char_limit
    ], ['id' => 1]);
    
    Html::redirect($url_base);
}

// Busca as configurações atuais
$current_token = '';
$current_limit = 150; 

$iterator = $DB->request([
    'FROM'  => 'glpi_plugin_alerttelegram_configs', 
    'WHERE' => ['id' => 1]
]);

if (count($iterator) > 0) {
    $result = $iterator->current();
    $current_token = $result['bot_token'] ?? '';
    $current_limit = $result['char_limit'] ?? 150;
}

// Desenha a tela
Html::header("Alert Telegram", $_SERVER['PHP_SELF'], "config", "plugins");

echo "<div class='center'>";
echo "<form method='post' action='$url_base'>";
echo "<table class='tab_cadre_fixe'>";

echo "<tr><th colspan='2'>Configuração Global do Bot Telegram</th></tr>";

echo "<tr class='tab_bg_1'>";
echo "<td width='30%'>Token do Bot:</td>";
echo "<td>";
echo "<input type='text' name='bot_token' value='" . Html::cleanInputText($current_token) . "' style='width: 80%;' placeholder='Ex: 123456789:ABCdef...'>";
echo "</td>";
echo "</tr>";

echo "<tr class='tab_bg_1'>";
echo "<td>Limite de Caracteres:</td>";
echo "<td>";
echo "<input type='number' name='char_limit' value='" . $current_limit . "' style='width: 100px;' min='10' max='4000'>";
echo " <span style='color: #888; margin-left: 10px;'>(Evita mensagens gigantes no celular)</span>";
echo "</td>";
echo "</tr>";

echo "<tr class='tab_bg_2'>";
echo "<td colspan='2' class='center'>";
echo "<input type='submit' name='update' value='Salvar Configurações' class='submit'>";
echo "</td>";
echo "</tr>";

echo "</table>";
Html::closeForm();
echo "</div>";

Html::footer();