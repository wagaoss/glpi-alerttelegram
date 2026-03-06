<?php

// Função executada no momento da instalação do plugin
function plugin_alerttelegram_install() {
    global $DB;

    $reflection = new ReflectionClass($DB);
    $property = $reflection->getProperty('dbh');
    $property->setAccessible(true);
    $dbh = $property->getValue($DB);

    $runRawQuery = function($sql) use ($dbh) {
        if (is_object($dbh) && method_exists($dbh, 'exec')) @$dbh->exec($sql);
        else @$dbh->query($sql);
    };

    $table_chatids = 'glpi_plugin_alerttelegram_chatids';
    if (!$DB->tableExists($table_chatids)) {
        $query = "CREATE TABLE `$table_chatids` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `itemtype` VARCHAR(100) NOT NULL,
            `items_id` INT(11) NOT NULL DEFAULT '0',
            `chat_id` VARCHAR(100) NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `item` (`itemtype`, `items_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $runRawQuery($query);
    }

    $table_configs = 'glpi_plugin_alerttelegram_configs';
    if (!$DB->tableExists($table_configs)) {
        $query = "CREATE TABLE `$table_configs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `bot_token` VARCHAR(255) NULL DEFAULT NULL,
            `char_limit` INT(11) NOT NULL DEFAULT '150',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $runRawQuery($query);
        $DB->insert($table_configs, ['bot_token' => '', 'char_limit' => 150]);
    } else {
        if (!$DB->fieldExists($table_configs, 'char_limit')) {
            $runRawQuery("ALTER TABLE `$table_configs` ADD `char_limit` INT(11) NOT NULL DEFAULT '150' AFTER `bot_token`;");
        }
    }

    return true;
}

// Função executada no momento da desinstalação do plugin
function plugin_alerttelegram_uninstall() {
    global $DB;

    $reflection = new ReflectionClass($DB);
    $property = $reflection->getProperty('dbh');
    $property->setAccessible(true);
    $dbh = $property->getValue($DB);

    $runRawQuery = function($sql) use ($dbh) {
        if (is_object($dbh) && method_exists($dbh, 'exec')) @$dbh->exec($sql);
        else @$dbh->query($sql);
    };

    if ($DB->tableExists('glpi_plugin_alerttelegram_chatids')) $runRawQuery("DROP TABLE `glpi_plugin_alerttelegram_chatids`");
    if ($DB->tableExists('glpi_plugin_alerttelegram_configs')) $runRawQuery("DROP TABLE `glpi_plugin_alerttelegram_configs`");

    return true;
}

// --- FUNÇÃO CENTRAL DE DISPARO ---
function plugin_alerttelegram_enviar($tickets_id, $acao, $conteudo_extra = "", $uid_forcado = 0, $texto_completo = "") {
    global $DB, $CFG_GLPI;

    if ($tickets_id == 0) return false;

    $iterator_token = $DB->request(['FROM' => 'glpi_plugin_alerttelegram_configs', 'WHERE' => ['id' => 1]]);
    if (count($iterator_token) == 0) return false;
    
    $config_data = $iterator_token->current();
    $bot_token = $config_data['bot_token'];
    $char_limit = isset($config_data['char_limit']) && $config_data['char_limit'] > 0 ? (int)$config_data['char_limit'] : 150;
    
    if (empty($bot_token)) return false; 

    // --- CAÇA ÀS MENÇÕES ANTES DE CORTAR O TEXTO ---
    $mencoes_ids = [];
    if (!empty($texto_completo)) {
        preg_match_all('/@([a-zA-Z0-9_\.-]+)/', $texto_completo, $matches);
        
        if (!empty($matches[1])) {
            $nomes_mencionados = $matches[1]; 
            
            $iterator_mencoes = $DB->request([
                'SELECT' => 'id',
                'FROM'   => 'glpi_users',
                'WHERE'  => ['name' => $nomes_mencionados]
            ]);

            foreach ($iterator_mencoes as $row_m) {
                $mencoes_ids[] = $row_m['id'];
            }
        }
    }

    // Aplica o limite de caracteres
    if (!empty($conteudo_extra) && mb_strlen($conteudo_extra) > $char_limit) {
        $conteudo_extra = mb_substr($conteudo_extra, 0, $char_limit) . " [...]";
    }

    $ticket = new Ticket();
    if (!$ticket->getFromDB($tickets_id)) return false;

    $titulo = $ticket->getField('name');
    $status_id = $ticket->getField('status');
    $status_nome = Ticket::getStatus($status_id); 
    
    $autor_id = Session::getLoginUserID();
    $autor_nome = getUserName($autor_id);
    $url_chamado = $CFG_GLPI["url_base"] . "/front/ticket.form.php?id=" . $tickets_id;

    // --- AUTO-REPARO 1.1.0: FAREJADOR DE MOTIVO DE PENDÊNCIA ---
    $motivo_pendencia = "";
    if ($status_id == 4) { // 4 é o status nativo "Pendente" no GLPI
        $iterator_pendencia = $DB->request([
            'SELECT'     => ['glpi_pendingreasons.name'],
            'FROM'       => 'glpi_pendingreasons_items',
            'INNER JOIN' => [
                'glpi_pendingreasons' => [
                    'ON' => [
                        'glpi_pendingreasons_items' => 'pendingreasons_id',
                        'glpi_pendingreasons'       => 'id'
                    ]
                ]
            ],
            'WHERE'      => [
                'glpi_pendingreasons_items.itemtype' => 'Ticket',
                'glpi_pendingreasons_items.items_id' => $tickets_id
            ],
            'ORDER'      => 'glpi_pendingreasons_items.id DESC',
            'LIMIT'      => 1
        ]);

        if (count($iterator_pendencia) > 0) {
            $row_pendencia = $iterator_pendencia->current();
            $motivo_pendencia = "\n⏸️ <b>Motivo:</b> " . $row_pendencia['name'];
        }
    }

    // --- MONTAGEM DA MENSAGEM ---
    $mensagem = "🔔 <b>ATUALIZAÇÃO NO CHAMADO #{$tickets_id}</b>\n\n";
    $mensagem .= "⚡ <b>Ação:</b> {$acao}\n";
    $mensagem .= "🏷️ <b>Título:</b> {$titulo}\n";
    $mensagem .= "👤 <b>Autor:</b> {$autor_nome}\n";
    $mensagem .= "🚦 <b>Status:</b> {$status_nome}{$motivo_pendencia}\n"; // Motivo injetado aqui!
    
    if (!empty($conteudo_extra)) {
        $mensagem .= "\n📝 <b>Detalhes:</b>\n<i>{$conteudo_extra}</i>\n";
    }
    
    $mensagem .= "\n🔗 <a href='{$url_chamado}'>Acessar Chamado no GLPI</a>";

    $target_user_ids = [];
    
    // 1. Autor
    if ($autor_id) $target_user_ids[] = $autor_id;
    
    // 2. ID Forçado
    if ($uid_forcado > 0) $target_user_ids[] = $uid_forcado;

    // 3. IDs dos Mencionados
    foreach ($mencoes_ids as $mid) {
        $target_user_ids[] = $mid;
    }

    // 4. Usuários Vinculados Diretamente
    $iterator_ticket_users = $DB->request([
        'SELECT' => 'users_id',
        'FROM'   => 'glpi_tickets_users',
        'WHERE'  => ['tickets_id' => $tickets_id]
    ]);
    foreach ($iterator_ticket_users as $row) {
        $target_user_ids[] = $row['users_id'];
    }

    // 5. Usuários em Grupos
    $iterator_groups_tickets = $DB->request([
        'SELECT' => 'groups_id',
        'FROM'   => 'glpi_groups_tickets',
        'WHERE'  => ['tickets_id' => $tickets_id]
    ]);
    foreach ($iterator_groups_tickets as $row_group) {
        $iterator_groups_users = $DB->request([
            'SELECT' => 'users_id',
            'FROM'   => 'glpi_groups_users',
            'WHERE'  => ['groups_id' => $row_group['groups_id']]
        ]);
        foreach ($iterator_groups_users as $row_user) {
            $target_user_ids[] = $row_user['users_id'];
        }
    }

    $target_user_ids = array_unique($target_user_ids); 

    foreach ($target_user_ids as $uid) {
        $iterator_chat = $DB->request([
            'FROM'  => 'glpi_plugin_alerttelegram_chatids',
            'WHERE' => ['itemtype' => 'User', 'items_id' => $uid]
        ]);

        if (count($iterator_chat) > 0) {
            $chat_id = $iterator_chat->current()['chat_id'];
            
            if (!empty($chat_id)) {
                $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
                $data = [
                    'chat_id'    => $chat_id,
                    'text'       => $mensagem,
                    'parse_mode' => 'HTML' 
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 2); 
                curl_exec($ch);
                curl_close($ch);
            }
        }
    }

    return true;
}

// =========================================================================
// GATILHOS (HOOKS)
// =========================================================================

function plugin_alerttelegram_item_add(CommonDBTM $item) {
    $itemtype = $item->getType();
    $tickets_id = 0;
    $acao = "";
    
    $raw_content = html_entity_decode($item->getField('content') ?? '');
    $has_media = (stripos($raw_content, '<img') !== false || stripos($raw_content, '<video') !== false || stripos($raw_content, '<iframe') !== false);
    
    $texto_completo = trim(str_replace('&nbsp;', ' ', strip_tags($raw_content)));
    $conteudo_extra = $texto_completo;

    if ($itemtype == 'Ticket') {
        $tickets_id = $item->getField('id');
        $acao = "🎫 Novo Chamado Aberto";
    } elseif ($itemtype == 'ITILFollowup') {
        $tickets_id = $item->getField('items_id'); 
        $acao = "💬 Novo Acompanhamento";
    } elseif ($itemtype == 'TicketTask') {
        $tickets_id = $item->getField('tickets_id');
        $acao = "✅ Nova Tarefa Adicionada";
    } elseif ($itemtype == 'ITILSolution') {
        $tickets_id = $item->getField('items_id');
        $acao = "💡 Solução Proposta";
    } elseif ($itemtype == 'TicketValidation') {
        $tickets_id = $item->getField('tickets_id');
        $acao = "⚠️ Aprovação Solicitada";
    } else {
        return false;
    }

    if (empty($conteudo_extra) && $has_media) {
        $conteudo_extra = "[🖼️ Imagem/Vídeo anexado. Acesse o chamado para visualizar]";
        $texto_completo = ""; 
    }

    return plugin_alerttelegram_enviar($tickets_id, $acao, $conteudo_extra, 0, $texto_completo);
}

function plugin_alerttelegram_item_update(CommonDBTM $item) {
    if ($item->getType() != 'Ticket') return false;

    if (isset($item->updates) && in_array('status', $item->updates)) {
        $old_status = $item->oldvalues['status'];
        $new_status = $item->fields['status'];
        
        if ($old_status != $new_status) {
            $tickets_id = $item->getField('id');
            $acao = "🔄 Status Alterado";
            $conteudo_extra = "O chamado mudou para o status: " . Ticket::getStatus($new_status);
            
            return plugin_alerttelegram_enviar($tickets_id, $acao, $conteudo_extra);
        }
    }
    return false;
}

function plugin_alerttelegram_actor_add(CommonDBTM $item) {
    if ($item->getType() != 'Ticket_User') return false;
    
    $tickets_id = $item->getField('tickets_id');
    $users_id = $item->getField('users_id');
    $type = $item->getField('type'); 
    
    $nome_alvo = getUserName($users_id);
    $papel = ($type == 1) ? "Requerente" : (($type == 2) ? "Técnico" : "Observador");

    $acao = "➕ Ator Adicionado";
    $conteudo_extra = "O usuário {$nome_alvo} entrou como {$papel} no chamado.";

    return plugin_alerttelegram_enviar($tickets_id, $acao, $conteudo_extra);
}

function plugin_alerttelegram_actor_purge(CommonDBTM $item) {
    if ($item->getType() != 'Ticket_User') return false;
    
    $tickets_id = $item->getField('tickets_id');
    $users_id = $item->getField('users_id');
    $type = $item->getField('type'); 
    
    $nome_alvo = getUserName($users_id);
    $papel = ($type == 1) ? "Requerente" : (($type == 2) ? "Técnico" : "Observador");

    $acao = "➖ Ator Removido";
    $conteudo_extra = "O usuário {$nome_alvo} foi removido da função de {$papel} do chamado.";

    return plugin_alerttelegram_enviar($tickets_id, $acao, $conteudo_extra, $users_id);
}