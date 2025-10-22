<?php
/**
 * Quick integration-like smoke test for PluginIdeasCampanhaCreator
 * using lightweight stubs so we can ensure minimal data tickets
 * are accepted without requiring a full GLPI environment.
 */

if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', __DIR__ . '/..');
}

if (!defined('GLPI_LOG_DIR')) {
    define('GLPI_LOG_DIR', sys_get_temp_dir());
}

if (!function_exists('__')) {
    function __($text, $domain = null) {
        return $text;
    }
}

if (!class_exists('Session')) {
    class Session {
        public static function checkLoginUser(): void {
            // no-op for test
        }

        public static function getLoginUserID(): int {
            return 99;
        }
    }
}

if (!class_exists('PluginIdeasConfig')) {
    class PluginIdeasConfig {
        public static function getConfig(): array {
            return [];
        }

        public static function canAdmin($profileId): bool {
            return true;
        }
    }
}

if (!class_exists('Toolbox')) {
    class Toolbox {
        public static function addslashes_deep($value) {
            return addslashes($value);
        }

        public static function unclean_html_cross_side_scripting_deep($value) {
            return $value;
        }
    }
}

if (!class_exists('Plugin')) {
    class Plugin {
        public static function getWebDir(string $name): string {
            return '/plugins/' . $name;
        }
    }
}

if (!class_exists('CommonITILActor')) {
    class CommonITILActor {
        public const REQUESTER = 1;
    }
}

if (!class_exists('Ticket')) {
    class Ticket {
        public static array $lastAdded = [];
        public const INCOMING = 1;
        public const DEMAND_TYPE = 2;

        public function add(array $data) {
            self::$lastAdded = $data;
            return 321;
        }
    }
}

if (!class_exists('Ticket_User')) {
    class Ticket_User {
        public static array $added = [];

        public function add(array $data) {
            self::$added[] = $data;
            return true;
        }
    }
}

if (!class_exists('PluginIdeasLog')) {
    class PluginIdeasLog {
        public static function add($event, $userId, array $context = []): void {}
    }
}

require_once __DIR__ . '/../inc/logger.php';
require_once __DIR__ . '/../inc/campanha.creator.php';

$_SESSION['glpi_currenttime'] = '2025-01-01 10:00:00';
$_SESSION['glpiactive_entity'] = 1;

$result = PluginIdeasCampanhaCreator::createCampanhaTicket([], []);

assert($result['success'] === true, 'Ticket should be created successfully with minimal data');
assert($result['ticket_id'] === 321, 'Ticket::add stub should return its ID');
assert(isset(Ticket::$lastAdded['name']) && Ticket::$lastAdded['name'] !== '', 'Ticket name must be generated automatically');
assert(Ticket::$lastAdded['content'] === 'Campanha criada automaticamente pelo portal de ideias.', 'Ticket content should use the default fallback');
assert(Ticket_User::$added[0]['type'] === CommonITILActor::REQUESTER, 'Requester link must be created');

echo "All PluginIdeasCampanhaCreator tests passed\n";
