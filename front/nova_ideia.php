<?php

include('../../../inc/includes.php');
require_once __DIR__ . '/../inc/ideia.view.php';
require_once __DIR__ . '/../inc/ticket.class.php';

Session::checkLoginUser();

$profileId = $_SESSION['glpiactiveprofile']['id'] ?? 0;
if (!PluginIdeasConfig::canView($profileId)) {
    Html::displayRightError();
    exit;
}

$config = PluginIdeasConfig::getConfig();
$menuName = $config['menu_name'] ?? 'Pulsar';
$campaignCategoryId = (int) ($config['campaign_category_id'] ?? 152);
$parentGroupId = (int) ($config['parent_group_id'] ?? 0);

$campanhas = [];
try {
    global $DB;
    $where = ['is_deleted' => 0];
    if ($campaignCategoryId > 0) {
        $where['itilcategories_id'] = $campaignCategoryId;
    }

    $iterator = $DB->request([
        'SELECT' => ['id', 'name', 'time_to_resolve', 'date'],
        'FROM'   => 'glpi_tickets',
        'WHERE'  => $where,
        'ORDER'  => 'name ASC'
    ]);

    foreach ($iterator as $row) {
        $campanhas[] = $row;
    }
} catch (Throwable $exception) {
    error_log('Plugin Ideas - Erro ao buscar campanhas: ' . $exception->getMessage());
}

$areasImpactadas = [];

if ($parentGroupId > 0) {
    try {
        $areasIterator = $DB->request([
            'SELECT' => ['id', 'name', 'completename'],
            'FROM'   => 'glpi_groups',
            'WHERE'  => [
                'is_deleted' => 0,
                'groups_id'  => $parentGroupId
            ],
            'ORDER'  => 'name ASC'
        ]);

        foreach ($areasIterator as $areaRow) {
            $areasImpactadas[] = [
                'id'   => (int) $areaRow['id'],
                'name' => $areaRow['name'] ?? $areaRow['completename']
            ];
        }
    } catch (Throwable $exception) {
        error_log('Plugin Ideas - Erro ao buscar áreas impactadas: ' . $exception->getMessage());
    }
}

if (empty($areasImpactadas)) {
    $areasImpactadas = [];
}

$selectedCampaignId = 0;
$selectedCampaignFound = false;
if (isset($_GET['campanha_id'])) {
    $candidateId = (int) $_GET['campanha_id'];
    if ($candidateId > 0) {
        foreach ($campanhas as $campanha) {
            if ((int) $campanha['id'] === $candidateId) {
                $selectedCampaignId = $candidateId;
                $selectedCampaignFound = true;
                break;
            }
        }

        if (!$selectedCampaignFound) {
            $ticket = new Ticket();
            if ($ticket->getFromDB($candidateId)
                && (int) ($ticket->fields['itilcategories_id'] ?? 0) === $campaignCategoryId) {
                $campanhas[] = [
                    'id'              => $ticket->fields['id'],
                    'name'            => $ticket->fields['name'] ?? '',
                    'time_to_resolve' => $ticket->fields['time_to_resolve'] ?? null,
                    'date'            => $ticket->fields['date'] ?? null,
                ];
                $selectedCampaignId = $candidateId;
                $selectedCampaignFound = true;
            }
        }

        if (!$selectedCampaignFound) {
            $selectedCampaignId = 0;
        }
    }
}

$title = sprintf(__('%s – Nova Ideia', 'ideas'), $menuName);
if (Session::getCurrentInterface() === 'helpdesk') {
    Html::helpHeader($title, '', 'helpdesk', 'management');
} else {
    Html::header($title, $_SERVER['PHP_SELF'], 'management', 'PluginIdeasMenu');
}

$csrf = Session::getNewCSRFToken();
$pluginWeb = Plugin::getWebDir('ideas');
$currentUserId = Session::getLoginUserID();

?>
<link rel="stylesheet" href="<?php echo $pluginWeb; ?>/css/pulsar.css">
<link rel="stylesheet" href="<?php echo $pluginWeb; ?>/css/forms.css">
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js" referrerpolicy="origin"></script>

<?php
plugin_ideas_render_ideia_form(
    $campanhas,
    $areasImpactadas,
    $csrf,
    $selectedCampaignId,
    $currentUserId
);
?>

<script src="<?php echo $pluginWeb; ?>/js/form-helpers.js"></script>
<script src="<?php echo $pluginWeb; ?>/js/ideia.form.js"></script>
<?php
if (Session::getCurrentInterface() === 'helpdesk') {
    Html::helpFooter();
} else {
    Html::footer();
}
