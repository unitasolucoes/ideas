<?php
/**
 * Minhas Ideias - Pulsar
 */

include ('../../../inc/includes.php');
require_once __DIR__ . '/../inc/layout.class.php';
require_once __DIR__ . '/../inc/ticket.class.php';

Session::checkLoginUser();

$user_profile = $_SESSION['glpiactiveprofile']['id'] ?? 0;

if (!PluginIdeasConfig::canView($user_profile)) {
    Html::displayRightError();
    exit;
}

$config              = PluginIdeasConfig::getConfig();
$menu_name           = $config['menu_name'];
$plugin_web          = Plugin::getWebDir('ideas');
$csrf_token          = Session::getNewCSRFToken();
$campaign_category_id = (int) ($config['campaign_category_id'] ?? 0);
$idea_category_id     = (int) ($config['idea_category_id'] ?? 0);

$title = sprintf(__('%s – Minhas Ideias', 'ideas'), $menu_name);
if (Session::getCurrentInterface() == "helpdesk") {
    Html::helpHeader($title, '', 'helpdesk', 'management');
} else {
    Html::header($title, $_SERVER['PHP_SELF'], 'management', 'PluginIdeasMenu');
}

global $DB, $CFG_GLPI;

$user_id = (int) Session::getLoginUserID();

// ========================================
// FILTROS
// ========================================

$status_filter = $_GET['status'] ?? 'all';
$campaign_filter = (int) ($_GET['campaign'] ?? 0);
$sort_filter = $_GET['sort'] ?? 'date_desc';
$search = $_GET['search'] ?? '';

// ========================================
// BUSCAR IDEIAS DO USUÁRIO
// ========================================

// ========================================
// BUSCAR IDEIAS DO USUÁRIO LOGADO (categoria 153)
// ========================================

$query = "
    SELECT 
        glpi_tickets.id,
        glpi_tickets.name,
        glpi_tickets.content,
        glpi_tickets.date,
        glpi_tickets.status,
        glpi_tickets.itilcategories_id,
        glpi_tickets_users.users_id as users_id_requester,
        (SELECT COUNT(*) FROM glpi_plugin_ideas_likes WHERE tickets_id = glpi_tickets.id) as likes_count,
        (
            SELECT COUNT(*)
            FROM glpi_itilfollowups
            WHERE itemtype = 'Ticket'
              AND items_id = glpi_tickets.id
              AND is_private = 0
        ) as comments_count,
        (SELECT COUNT(*) FROM glpi_plugin_ideas_likes WHERE tickets_id = glpi_tickets.id AND users_id = {$user_id}) as has_liked,
        u.realname as user_realname,
        u.firstname as user_firstname
    FROM glpi_tickets
    INNER JOIN glpi_tickets_users ON glpi_tickets_users.tickets_id = glpi_tickets.id AND glpi_tickets_users.type = 1
    LEFT JOIN glpi_users u ON u.id = glpi_tickets_users.users_id
    WHERE glpi_tickets_users.users_id = {$user_id}
    AND glpi_tickets.is_deleted = 0
";

if ($idea_category_id > 0) {
    $query .= " AND glpi_tickets.itilcategories_id = " . (int) $idea_category_id;
}

if ($status_filter !== 'all') {
    $query .= " AND glpi_tickets.status = " . (int) $status_filter;
}

if (!empty($search)) {
    $search_escaped = $DB->escape($search);
    $query .= " AND (glpi_tickets.name LIKE '%{$search_escaped}%' OR glpi_tickets.content LIKE '%{$search_escaped}%')";
}

// Ordenação
$orderby = 'glpi_tickets.date DESC';
switch ($sort_filter) {
    case 'date_asc':
        $orderby = 'glpi_tickets.date ASC';
        break;
    case 'likes_desc':
        $orderby = 'likes_count DESC, glpi_tickets.date DESC';
        break;
    case 'comments_desc':
        $orderby = 'comments_count DESC, glpi_tickets.date DESC';
        break;
}

$query .= " ORDER BY {$orderby}";

$result = $DB->query($query);
$ideas = [];

if ($result) {
    while ($row = $DB->fetchAssoc($result)) {
        $row['campaign_link'] = PluginIdeasTicket::getCampaignForIdea((int) ($row['id'] ?? 0));
        $ideas[] = $row;
    }
}

$ideas = array_map(static function ($idea) {
    return is_array($idea) ? $idea : [];
}, $ideas);

if ($campaign_filter > 0) {
    $ideas = array_values(array_filter($ideas, static function ($idea) use ($campaign_filter) {
        $link = $idea['campaign_link'] ?? [];
        return !empty($link) && (int) ($link['campaign_id'] ?? 0) === $campaign_filter;
    }));
}

$total_ideas = count($ideas);

$campaign_options = [];

try {
    $where = ['is_deleted' => 0];
    if ($campaign_category_id > 0) {
        $where['itilcategories_id'] = $campaign_category_id;
    }

    $campaign_iterator = $DB->request([
        'SELECT' => ['id', 'name', 'time_to_resolve', 'date'],
        'FROM'   => 'glpi_tickets',
        'WHERE'  => $where,
        'ORDER'  => 'name ASC'
    ]);

    foreach ($campaign_iterator as $row) {
        $campaign_options[] = [
            'id'   => (int) ($row['id'] ?? 0),
            'name' => $row['name'] ?? sprintf(__('Campanha #%d', 'ideas'), $row['id'] ?? 0)
        ];
    }
} catch (Throwable $exception) {
    error_log('Plugin Ideas - Erro ao listar campanhas em Minhas Ideias: ' . $exception->getMessage());
}

$statuses = [
    Ticket::INCOMING => 'Novo',
    Ticket::ASSIGNED => 'Em análise',
    Ticket::PLANNED => 'Planejado',
    Ticket::WAITING => 'Aguardando',
    Ticket::SOLVED => 'Solucionado',
    Ticket::CLOSED => 'Fechado'
];

$can_admin = PluginIdeasConfig::canAdmin($user_profile);
$can_like  = PluginIdeasConfig::canLike($user_profile);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="<?php echo $plugin_web; ?>/css/pulsar.css"/>
<meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="_glpi_csrf_token" id="pulsar-csrf-token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

<?php
PluginIdeasLayout::shellOpen();

PluginIdeasLayout::renderHeader([
    'badge'    => Html::entities_deep($menu_name),
    'title'    => __('Minhas Ideias', 'ideas'),
    'subtitle' => __('Acompanhe e gerencie suas ideias enviadas.', 'ideas'),
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
PluginIdeasLayout::renderNav($navItems, 'my_ideas');
PluginIdeasLayout::contentOpen();
?>

  <div class="pulsar-filters-container card-u">
    <div class="pulsar-search-inline">
      <input type="text" id="buscar-ideias" placeholder="<?php echo __('     Buscar ideias por título ou conteúdo...', 'ideas'); ?>" value="<?php echo htmlspecialchars($search); ?>">
      <button class="search-clear" type="button" title="Limpar busca"><i class="fa-solid fa-times"></i></button>
    </div>

    <div class="pulsar-filters">
      <div class="filter-group">
        <label>Status:</label>
        <select class="filter-select" id="filter-status" onchange="window.location.href='?status='+this.value+'&campaign=<?php echo $campaign_filter; ?>&sort=<?php echo $sort_filter; ?>&search=<?php echo urlencode($search); ?>'">
          <option value="all"<?php echo $status_filter === 'all' ? ' selected' : ''; ?>>Todos</option>
          <?php foreach ($statuses as $status_id => $status_name): ?>
            <option value="<?php echo $status_id; ?>"<?php echo $status_filter == $status_id ? ' selected' : ''; ?>>
              <?php echo $status_name; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="filter-group">
        <label>Campanha:</label>
        <select class="filter-select" id="filter-campaign" onchange="window.location.href='?campaign='+this.value+'&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_filter; ?>&search=<?php echo urlencode($search); ?>'">
          <option value="0"<?php echo $campaign_filter === 0 ? ' selected' : ''; ?>>Todas</option>
          <?php foreach ($campaign_options as $campaign): ?>
            <option value="<?php echo $campaign['id']; ?>"<?php echo $campaign_filter == $campaign['id'] ? ' selected' : ''; ?>>
              <?php echo htmlspecialchars($campaign['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="filter-group">
        <label>Ordenar por:</label>
        <select class="filter-select" id="filter-order" onchange="window.location.href='?sort='+this.value+'&status=<?php echo $status_filter; ?>&campaign=<?php echo $campaign_filter; ?>&search=<?php echo urlencode($search); ?>'">
          <option value="date_desc"<?php echo $sort_filter === 'date_desc' ? ' selected' : ''; ?>>Mais recentes</option>
          <option value="date_asc"<?php echo $sort_filter === 'date_asc' ? ' selected' : ''; ?>>Mais antigas</option>
          <option value="likes_desc"<?php echo $sort_filter === 'likes_desc' ? ' selected' : ''; ?>>Mais curtidas</option>
          <option value="comments_desc"<?php echo $sort_filter === 'comments_desc' ? ' selected' : ''; ?>>Mais comentadas</option>
        </select>
      </div>

      <?php if ($status_filter !== 'all' || $campaign_filter > 0 || $sort_filter !== 'date_desc' || !empty($search)): ?>
        <button class="btn-u ghost" onclick="window.location.href='my_ideas.php'"><i class="fa-solid fa-filter-circle-xmark"></i> Limpar filtros</button>
      <?php endif; ?>
    </div>

    <div class="ideas-counter">
      <span><?php echo $total_ideas; ?></span> ideias encontradas
    </div>
  </div>

  <main class="pulsar-ideas-grid">
  
    <?php if ($total_ideas > 0): ?>
      <?php foreach ($ideas as $idea): 
        $ticket_id = $idea['id'];
        $title = htmlspecialchars($idea['name']);
        
        $content = $idea['content'];
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        $content_preview = strlen($content) > 180 ? substr($content, 0, 180) . '...' : $content;
        
        $user_name = trim(($idea['user_firstname'] ?? '') . ' ' . ($idea['user_realname'] ?? ''));
        if (empty($user_name)) {
            $user_name = 'Usuário';
        }

        $user_initials = PluginIdeasConfig::getUserInitials($idea['user_firstname'] ?? '', $idea['user_realname'] ?? '');
        
        $date = Html::convDate($idea['date']);
        
        $status_id = $idea['status'];
        $statusInfo = PluginIdeasTicket::getStatusPresentation($status_id);
        $status_name = $statuses[$status_id] ?? $statusInfo['label'];
        
        $likes_count = (int) $idea['likes_count'];
        $comments_count = (int) $idea['comments_count'];
        $has_liked = (int) $idea['has_liked'] > 0;
        
        $campaign_link = $idea['campaign_link'] ?? [];
        $campaign_id   = (int) ($campaign_link['campaign_id'] ?? 0);
        $campaign_name = $campaign_id > 0 ? htmlspecialchars($campaign_link['campaign_name'] ?? '') : '';
      ?>
      
      <article class="idea-card card-u">
        <div class="idea-card-header">
          <div class="idea-author">
            <span class="author-avatar"><?php echo $user_initials; ?></span>
            <div class="author-info">
              <div class="author-name"><?php echo htmlspecialchars($user_name); ?></div>
              <div class="idea-date pulsar-muted"><?php echo $date; ?></div>
            </div>
          </div>
          <span class="badge <?php echo $statusInfo['class']; ?>">
            <i class="fa-solid <?php echo $statusInfo['icon']; ?>"></i>
            <?php echo htmlspecialchars($status_name); ?>
          </span>
        </div>
        
        <div class="idea-card-body">
          <h3 class="idea-title"><?php echo $title; ?></h3>
          <p class="idea-excerpt"><?php echo htmlspecialchars($content_preview); ?></p>
          <?php if (!empty($campaign_name)): ?>
          <div class="idea-campaign-chip" title="Campanha vinculada">
            <i class="fa-solid fa-flag"></i>
            <span><?php echo $campaign_name; ?></span>
          </div>
          <?php endif; ?>
        </div>

        <div class="idea-card-footer">
          <div class="idea-stats">
            <button class="stat-btn like-btn <?php echo $has_liked ? 'liked' : ''; ?>"
                    data-ticket="<?php echo $ticket_id; ?>"
                    data-liked="<?php echo $has_liked ? '1' : '0'; ?>"
                    <?php echo !$can_like ? 'disabled' : ''; ?>>
              <i class="fa-solid fa-heart"></i>
              <span class="like-count"><?php echo $likes_count; ?></span>
            </button>
            <span class="stat-item">
              <i class="fa-solid fa-comment"></i>
              <span><?php echo $comments_count; ?></span>
            </span>
          </div>
          <div class="idea-actions">
            <a href="idea.php?id=<?php echo $ticket_id; ?>" class="btn-outline btn-small">
              <i class="fa-solid fa-arrow-right"></i> Ver detalhes
            </a>
          </div>
        </div>
      </article>
      
      <?php endforeach; ?>
    <?php else: ?>
      <!-- ✅ MENSAGEM DE VAZIO -->
      <div class="card-u text-center empty-state">
        <div class="empty">
          <div class="empty-icon">
            <i class="fa-solid fa-search" style="font-size: 3rem; color: #ccc;"></i>
          </div>
          <p class="empty-title">Nenhuma ideia corresponde aos filtros</p>
          <p class="empty-subtitle pulsar-muted">Tente ajustar seus critérios de busca.</p>
        </div>
      </div>
    <?php endif; ?>
    
  </main>
  
        <?php PluginIdeasLayout::contentClose(); ?>
    <?php PluginIdeasLayout::shellClose(); ?>

<script src="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/ideas/js/pulsar.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Sistema de curtidas
  document.querySelectorAll('.like-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();

      if (this.hasAttribute('disabled')) {
        return;
      }

      const ticketId = this.getAttribute('data-ticket');
      PulsarLike.toggle(ticketId);
    });
  });

});
</script>

<?php
Html::footer();
