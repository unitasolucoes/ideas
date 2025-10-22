<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIdeasComment extends CommonDBTM {
    
    static $rightname = 'ticket';
    
    public static function getTypeName($nb = 0) {
        return __('Comment', 'ideas');
    }
    
    /**
     * Adiciona um comentário usando ITILFollowup nativo do GLPI
     */
    public static function addComment($tickets_id, $users_id, $content) {
        if (!PluginIdeasTicket::isIdea($tickets_id)
            && !PluginIdeasTicket::isCampaign($tickets_id)) {
            return false;
        }

        if (empty(trim($content))) {
            return false;
        }

        // Usar ITILFollowup nativo
        $followup = new ITILFollowup();
        $result = $followup->add([
            'itemtype'   => 'Ticket',
            'items_id'   => $tickets_id,
            'users_id'   => $users_id,
            'content'    => $content,
            'is_private' => 0
        ]);
        
        if ($result) {
            PluginIdeasUserPoints::addPoints($users_id, 'comment', $tickets_id);
            PluginIdeasLog::logAction('comment_added', $users_id, [
                'tickets_id' => $tickets_id,
                'followup_id' => $result
            ]);
        }
        
        return $result;
    }
    
    public static function countByTicket($tickets_id) {
        global $DB;

        $iterator = $DB->request([
            'FROM'  => 'glpi_itilfollowups',
            'WHERE' => [
                'itemtype'   => 'Ticket',
                'items_id'   => (int) $tickets_id,
                'is_private' => 0
            ]
        ]);

        return count($iterator);
    }
    
    /**
     * Busca comentários (followups) de um ticket
     */
    public static function getByTicket($tickets_id) {
        global $DB;
        
        $iterator = $DB->request([
            'FROM' => 'glpi_itilfollowups',
            'WHERE' => [
                'itemtype'   => 'Ticket',
                'items_id'   => (int) $tickets_id,
                'is_private' => 0
            ],
            'ORDER' => 'date_creation DESC'
        ]);
        
        $comments = [];
        foreach ($iterator as $data) {
            $user = new User();
            if ($user->getFromDB($data['users_id'])) {
                $user_name = $user->getFriendlyName();
                
                // Calcular iniciais
                $name_parts = explode(' ', trim($user_name));
                if (count($name_parts) >= 2) {
                    $user_initials = strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1));
                } else {
                    $user_initials = strtoupper(substr($user_name, 0, 2));
                }
                
                // Decodificar HTML entities do conteúdo
                $content = html_entity_decode($data['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
                $comments[] = [
                    'id' => $data['id'],
                    'user_name' => $user_name,
                    'user_initials' => $user_initials,
                    'user_picture' => $user->fields['picture'] ?? '',
                    'date_creation' => $data['date_creation'],
                    'content' => $content
                ];
            }
        }
        
        return $comments;
    }
}