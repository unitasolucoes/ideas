<?php

include('../../../inc/includes.php');
require_once __DIR__ . '/../inc/layout.class.php';
Session::checkLoginUser();

$user_profile = $_SESSION['glpiactiveprofile']['id'] ?? 0;

if (!PluginIdeasConfig::canView($user_profile)) {
    Html::displayRightError();
    exit;
}

if (!PluginIdeasConfig::canAdmin($user_profile)) {
    Html::displayRightError();
    exit;
}

$config = PluginIdeasConfig::getConfig();
$menu_name = $config['menu_name'];
$plugin_web = Plugin::getWebDir('ideas');
$ranking_configs = PluginIdeasRankingConfig::getAllConfig();

$ranking_actions = [
    'submitted_idea'   => __('Ideia enviada', 'ideas'),
    'approved_idea'    => __('Ideia aprovada', 'ideas'),
    'implemented_idea' => __('Ideia implementada', 'ideas'),
    'like'             => __('Curtida registrada', 'ideas'),
    'comment'          => __('Comentário publicado', 'ideas')
];

$view_profiles  = json_decode($config['view_profile_ids'] ?? '[]', true) ?: [];
$like_profiles  = json_decode($config['like_profile_ids'] ?? '[]', true) ?: [];
$admin_profiles = json_decode($config['admin_profile_ids'] ?? '[]', true) ?: [];

$campaign_category_id = (int)$config['campaign_category_id'];
$idea_category_id     = (int)$config['idea_category_id'];
$parent_group_id = (int)($config['parent_group_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (method_exists('Session', 'checkCSRF')) {
        Session::checkCSRF($_POST);
    }

    $menu_name_post = trim($_POST['menu_name'] ?? '');
    $campaign_post  = (int)($_POST['campaign_category_id'] ?? 0);
    $idea_post      = (int)($_POST['idea_category_id'] ?? 0);
    $parent_group_post = (int)($_POST['parent_group_id'] ?? 0);
    $view_post      = isset($_POST['view_profile_ids']) ? array_map('intval', (array)$_POST['view_profile_ids']) : [];
    $like_post      = isset($_POST['like_profile_ids']) ? array_map('intval', (array)$_POST['like_profile_ids']) : [];
    $admin_post     = isset($_POST['admin_profile_ids']) ? array_map('intval', (array)$_POST['admin_profile_ids']) : [];

    $data = [
        'menu_name'            => $menu_name_post !== '' ? $menu_name_post : 'Pulsar',
        'campaign_category_id' => $campaign_post,
        'idea_category_id'     => $idea_post,
        'parent_group_id'      => $parent_group_post,
        'view_profile_ids'     => json_encode(array_values(array_unique($view_post))),
        'like_profile_ids'     => json_encode(array_values(array_unique($like_post))),
        'admin_profile_ids'    => json_encode(array_values(array_unique($admin_post)))
    ];

    $update_success = PluginIdeasConfig::updateConfig($data);

    $ranking_post = $_POST['ranking'] ?? [];
    foreach ($ranking_actions as $action_key => $action_label) {
        if (isset($ranking_post[$action_key]['points_value'])) {
            $raw_points = $ranking_post[$action_key]['points_value'];
            if (is_array($raw_points)) {
                $raw_points = reset($raw_points);
            }
            $points_value = max(0, (int)$raw_points);
        } else {
            $points_value = isset($ranking_configs[$action_key]['points_value']) 
                ? (int)$ranking_configs[$action_key]['points_value'] 
                : 0;
        }

        $update_success = PluginIdeasRankingConfig::updatePointsValue($action_key, $points_value) && $update_success;
    }

    if ($update_success) {
        Session::addMessageAfterRedirect(__('Configurações atualizadas com sucesso.', 'ideas'), true, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Não foi possível atualizar as configurações.', 'ideas'), true, ERROR);
    }

    Html::redirect($_SERVER['REQUEST_URI']);
    exit;
}

$profiles = [];
$profile_iterator = $DB->request([
    'SELECT' => ['id', 'name'],
    'FROM'   => 'glpi_profiles',
    'ORDER'  => 'name'
]);
foreach ($profile_iterator as $row) {
    $profiles[] = $row;
}

$title = sprintf(__('%s – Configurações', 'ideas'), $menu_name);
if (Session::getCurrentInterface() == "helpdesk") {
   Html::helpHeader($title, '', 'helpdesk', 'management');
} else {
   Html::header($title, $_SERVER['PHP_SELF'], 'management', 'PluginIdeasMenu');
}

$csrf_token = Session::getNewCSRFToken();
$can_admin = PluginIdeasConfig::canAdmin($user_profile);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="<?php echo $plugin_web; ?>/css/pulsar.css"/>

<?php
PluginIdeasLayout::shellOpen();

PluginIdeasLayout::renderHeader([
    'badge'    => Html::entities_deep($menu_name),
    'title'    => __('Configurações', 'ideas'),
    'subtitle' => __('Configure as categorias, permissões e pontuações do Pulsar.', 'ideas'),
    'actions'  => [
        [
            'href'  => $plugin_web . '/front/feed.php',
            'label' => __('Voltar ao Feed', 'ideas'),
            'icon'  => 'fa-solid fa-arrow-left',
            'class' => 'ghost'
        ]
    ]
]);

$navItems = PluginIdeasLayout::getNavItems($can_admin);
PluginIdeasLayout::renderNav($navItems, 'settings');
PluginIdeasLayout::contentOpen();
?>

  <form method="post" class="settings-form">
    <input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf_token; ?>">

    <section class="card-u">
      <h2><i class="fa-solid fa-sliders"></i> Configurações Gerais</h2>
      <div class="form-grid">
        <div class="form-group">
          <label for="menu_name">Nome do Menu</label>
          <input type="text" id="menu_name" name="menu_name" value="<?php echo htmlspecialchars($config['menu_name']); ?>" required>
        </div>
        
        <div class="form-group">
          <label for="dropdown_campaign_category_id">Categoria de Campanhas</label>
          <?php
          ITILCategory::dropdown([
              'name'      => 'campaign_category_id',
              'value'     => $campaign_category_id,
              'entity'    => $_SESSION['glpiactive_entity'],
              'entity_sons' => true,
              'display_emptychoice' => true,
              'emptylabel' => __('Selecione uma categoria', 'ideas')
          ]);
          ?>
        </div>
        
        <div class="form-group">
          <label for="dropdown_idea_category_id">Categoria de Ideias</label>
          <?php
          ITILCategory::dropdown([
              'name'      => 'idea_category_id',
              'value'     => $idea_category_id,
              'entity'    => $_SESSION['glpiactive_entity'],
              'entity_sons' => true,
              'display_emptychoice' => true,
              'emptylabel' => __('Selecione uma categoria', 'ideas')
          ]);
          ?>
        </div>
        
        <div class="form-group">
          <label for="dropdown_parent_group_id">Grupo pai das áreas impactadas</label>
          <?php
          Group::dropdown([
              'name'      => 'parent_group_id',
              'value'     => $parent_group_id,
              'entity'    => $_SESSION['glpiactive_entity'],
              'entity_sons' => true,
              'display_emptychoice' => true,
              'emptylabel' => __('Selecione um grupo', 'ideas')
          ]);
          ?>
          <small class="pulsar-muted"><?php echo __('As áreas impactadas dos formulários buscarão os subgrupos desse grupo.', 'ideas'); ?></small>
        </div>
      </div>
    </section>

    <section class="card-u">
      <h2><i class="fa-solid fa-user-lock"></i> Permissões</h2>
      <p class="pulsar-muted">Selecione quais perfis terão acesso a cada recurso do Pulsar.</p>
      <div class="form-grid form-grid--permissions">
        <?php
        $permission_map = [
            'view_profile_ids' => [
                'icon'        => 'fa-solid fa-eye',
                'label'       => __('Perfis que podem visualizar', 'ideas'),
                'description' => __('Vazio significa acesso liberado para todos.', 'ideas'),
                'selected'    => $view_profiles
            ],
            'like_profile_ids' => [
                'icon'        => 'fa-solid fa-heart',
                'label'       => __('Perfis que podem curtir', 'ideas'),
                'description' => __('Vazio significa acesso liberado para todos.', 'ideas'),
                'selected'    => $like_profiles
            ],
            'admin_profile_ids' => [
                'icon'        => 'fa-solid fa-crown',
                'label'       => __('Perfis administradores', 'ideas'),
                'description' => __('Administradores têm acesso às configurações.', 'ideas'),
                'selected'    => $admin_profiles
            ],
        ];

        $multiselect_size = max(4, min(10, count($profiles)));
        ?>
        <?php foreach ($permission_map as $field => $meta): ?>
          <div class="form-group">
            <label for="<?php echo $field; ?>">
              <i class="<?php echo $meta['icon']; ?>"></i> <?php echo $meta['label']; ?>
            </label>
            <select id="<?php echo $field; ?>"
                    name="<?php echo $field; ?>[]"
                    class="pulsar-multiselect"
                    size="<?php echo $multiselect_size; ?>"
                    multiple>
              <?php foreach ($profiles as $profile):
                $profile_id = (int) $profile['id'];
                $selected = in_array($profile_id, $meta['selected'], true) ? ' selected' : '';
              ?>
                <option value="<?php echo $profile_id; ?>"<?php echo $selected; ?>>
                  <?php echo htmlspecialchars($profile['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small><?php echo $meta['description']; ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card-u">
      <h2><i class="fa-solid fa-trophy"></i> <?php echo __('Pontuação', 'ideas'); ?></h2>
      <p class="pulsar-muted"><?php echo __('Configure a pontuação atribuída para cada ação do Pulsar. Use 0 para desativar.', 'ideas'); ?></p>
      <div class="table-responsive">
        <table class="table-u">
          <thead>
            <tr>
              <th><?php echo __('Ação', 'ideas'); ?></th>
              <th><?php echo __('Pontuação', 'ideas'); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ranking_actions as $action_key => $action_label):
                $current_points = $ranking_configs[$action_key]['points_value'] ?? 0;
            ?>
              <tr>
                <td><?php echo htmlspecialchars($action_label); ?></td>
                <td>
                  <input type="number"
                        id="ranking_<?php echo htmlspecialchars($action_key); ?>_points"
                        name="ranking[<?php echo htmlspecialchars($action_key); ?>][points_value]"
                        min="0"
                        step="1"
                        value="<?php echo (int)$current_points; ?>"
                        class="input-small">
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <div class="form-actions">
      <button type="submit" class="btn-u primary"><i class="fa-solid fa-floppy-disk"></i> <?php echo __('Salvar configurações', 'ideas'); ?></button>
      <a href="feed.php" class="btn-u ghost"><i class="fa-solid fa-xmark"></i> <?php echo __('Cancelar', 'ideas'); ?></a>
    </div>
  </form>

<?php PluginIdeasLayout::contentClose(); ?>
<?php PluginIdeasLayout::shellClose(); ?>

<?php
Html::footer();
