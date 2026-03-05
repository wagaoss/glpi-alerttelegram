<?php
// Define a constante de versão do nosso plugin
define('PLUGIN_ALERTTELEGRAM_VERSION', '1.0.0');

// Inicialização do plugin no ecossistema do GLPI
function plugin_init_alerttelegram() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['alerttelegram'] = true;
    $PLUGIN_HOOKS['config_page']['alerttelegram'] = 'front/config.php';
    Plugin::registerClass('PluginAlerttelegramChatid', ['addtabon' => ['User', 'Group', 'Profile']]);

    // --- 1. GATILHOS DE CRIAÇÃO ---
    $PLUGIN_HOOKS['item_add']['alerttelegram'] = [
        'Ticket'           => 'plugin_alerttelegram_item_add',
        'ITILFollowup'     => 'plugin_alerttelegram_item_add',
        'TicketTask'       => 'plugin_alerttelegram_item_add',
        'ITILSolution'     => 'plugin_alerttelegram_item_add',
        'TicketValidation' => 'plugin_alerttelegram_item_add',
        'Ticket_User'      => 'plugin_alerttelegram_actor_add' // <-- GATILHO DE ENTRADA SEPARADO
    ];

    // --- 2. GATILHOS DE ATUALIZAÇÃO ---
    $PLUGIN_HOOKS['item_update']['alerttelegram'] = [
        'Ticket' => 'plugin_alerttelegram_item_update' 
    ];

    // --- 3. GATILHOS DE EXCLUSÃO ---
    $PLUGIN_HOOKS['item_purge']['alerttelegram'] = [
        'Ticket_User' => 'plugin_alerttelegram_actor_purge' // <-- GATILHO DE SAÍDA SEPARADO
    ];
}

function plugin_version_alerttelegram() {
    return [
        'name'           => 'Alerta Telegram',
        'version'        => PLUGIN_ALERTTELEGRAM_VERSION,
        'author'         => 'Wagaoss',
        'license'        => 'GPLv2+',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => '10.0.0'
            ]
        ]
    ];
}

function plugin_check_prerequisites_alerttelegram() { return true; }
function plugin_check_config_alerttelegram() { return true; }