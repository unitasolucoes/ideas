<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIdeasRedirect {

    public static function maybeRedirect($params = []): void {
        if (!self::isFormcreatorContext()) {
            return;
        }

        $formId = self::detectRequestedFormId($params);
        if ($formId <= 0) {
            return;
        }

        $ids = self::getConfiguredFormIds();

        if ($ids['idea'] > 0 && $formId === $ids['idea']) {
            Html::redirect(Plugin::getWebDir('ideas') . '/front/nova_ideia.php');
            exit;
        }

        if ($ids['campaign'] > 0 && $formId === $ids['campaign']) {
            Html::redirect(Plugin::getWebDir('ideas') . '/front/nova_campanha.php');
            exit;
        }
    }

    public static function redirectFromFormList($params = []): void {
        self::maybeRedirect($params);
    }

    public static function redirectFromFormDisplay($params = []): void {
        self::maybeRedirect($params);
    }

    private static function detectRequestedFormId($params = []): int {
        if (isset($_GET['id'])) {
            return (int) $_GET['id'];
        }

        if (isset($_REQUEST['forms_id'])) {
            return (int) $_REQUEST['forms_id'];
        }

        if (isset($_REQUEST['form_id'])) {
            return (int) $_REQUEST['form_id'];
        }

        if (is_array($params) && isset($params['id'])) {
            return (int) $params['id'];
        }

        if (is_array($params) && isset($params['forms_id'])) {
            return (int) $params['forms_id'];
        }

        return 0;
    }

    private static function getConfiguredFormIds(): array {
        return [
            'idea'     => PluginIdeasConfig::getFormCreatorIdeaFormId(),
            'campaign' => PluginIdeasConfig::getFormCreatorCampaignFormId()
        ];
    }

    private static function isFormcreatorContext(): bool {
        if (!isset($_SERVER['SCRIPT_NAME'])) {
            return false;
        }

        $script = $_SERVER['SCRIPT_NAME'];

        if (strpos($script, '/formcreator/front/') !== false) {
            return true;
        }

        if (strpos($script, '/marketplace/formcreator/front/') !== false) {
            return true;
        }

        return false;
    }
}
