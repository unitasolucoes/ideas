<?php

include('../../../inc/includes.php');
require_once __DIR__ . '/../inc/campanha.view.php';

Session::checkLoginUser();

// Qualquer usuário logado pode acessar o formulário de campanha
// Removida validação de permissão específica

$config = PluginIdeasConfig::getConfig();
$menuName = $config['menu_name'] ?? 'Pulsar';
$campaignCategoryId = (int) ($config['campaign_category_id'] ?? 152);
$parentGroupId = (int) ($config['parent_group_id'] ?? 0);

$campanhas = [];
try {
    global $DB;
    $iterator = $DB->request([
        'SELECT' => ['id', 'name'],
        'FROM'   => 'glpi_tickets',
        'WHERE'  => [
            'itilcategories_id' => $campaignCategoryId,
            'is_deleted'        => 0
        ],
        'ORDER'  => 'name ASC'
    ]);

    foreach ($iterator as $row) {
        $campanhas[] = $row;
    }
} catch (Throwable $exception) {
    error_log('Plugin Ideas - Erro ao buscar campanhas pai: ' . $exception->getMessage());
}

$title = sprintf(__('%s – Nova Campanha', 'ideas'), $menuName);
if (Session::getCurrentInterface() === 'helpdesk') {
    Html::helpHeader($title, '', 'helpdesk', 'management');
} else {
    Html::header($title, $_SERVER['PHP_SELF'], 'management', 'PluginIdeasMenu');
}

$csrf = Session::getNewCSRFToken();
$pluginWeb = Plugin::getWebDir('ideas');

?>
<link rel="stylesheet" href="<?php echo $pluginWeb; ?>/css/pulsar.css">
<link rel="stylesheet" href="<?php echo $pluginWeb; ?>/css/forms.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/pt.js"></script>

<?php
plugin_ideas_render_campanha_form($campanhas, $csrf);
?>

<script src="<?php echo $pluginWeb; ?>/js/form-helpers.js"></script>
<script src="<?php echo $pluginWeb; ?>/js/campanha.form.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof flatpickr !== 'undefined' && flatpickr.l10ns && flatpickr.l10ns.pt) {
      flatpickr.localize(flatpickr.l10ns.pt);
    }
  });
</script>
<?php
if (Session::getCurrentInterface() === 'helpdesk') {
    Html::helpFooter();
} else {
    Html::footer();
}
