<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIdeasLike extends CommonDBTM {
    
    static $rightname = 'ticket';
    
    public static function getTable($classname = null) {
        return 'glpi_plugin_ideas_likes';
    }
    
    public static function getTypeName($nb = 0) {
        return __('Like', 'ideas');
    }
    
    /**
     * Adicionar curtida
     */
    public static function addLike($tickets_id, $users_id) {
        global $DB;

        if (self::userHasLiked($tickets_id, $users_id)) {
            return false;
        }

        return $DB->insert('glpi_plugin_ideas_likes', [
            'tickets_id'    => (int) $tickets_id,
            'users_id'      => (int) $users_id,
            'date_creation' => $_SESSION['glpi_currenttime']
        ]);
    }
    
    /**
     * Remover curtida - MÉTODO PRINCIPAL
     */
    public static function remove($tickets_id, $users_id) {
        global $DB;
        
        if (!self::userHasLiked($tickets_id, $users_id)) {
            return false; // Já não tem curtida
        }
        
        // ✅ Usar método do GLPI (mais seguro)
        $deleted = $DB->delete('glpi_plugin_ideas_likes', [
            'tickets_id' => (int) $tickets_id,
            'users_id'   => (int) $users_id
        ]);
        
        return (bool) $deleted;
    }
    
    /**
     * Contar curtidas de um ticket
     */
    public static function countByTicket($tickets_id) {
        global $DB;
        
        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_ideas_likes',
            'WHERE' => ['tickets_id' => (int) $tickets_id]
        ]);
        
        return count($iterator);
    }

    /**
     * Verificar se usuário já curtiu
     */
    public static function userHasLiked($tickets_id, $users_id) {
        global $DB;
        
        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_ideas_likes',
            'WHERE' => [
                'tickets_id' => (int) $tickets_id,
                'users_id'   => (int) $users_id
            ],
            'LIMIT' => 1
        ]);
        
        return count($iterator) > 0;
    }
    
    /**
     * Buscar todas as curtidas de um ticket
     */
    public static function getByTicket($tickets_id) {
        global $DB;
        
        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_ideas_likes',
            'WHERE' => ['tickets_id' => (int) $tickets_id],
            'ORDER' => 'date_creation DESC'
        ]);
        
        $likes = [];
        foreach ($iterator as $data) {
            $user = new User();
            if ($user->getFromDB($data['users_id'])) {
                $data['user_name'] = $user->getFriendlyName();
                $likes[] = $data;
            }
        }
        
        return $likes;
    }
}