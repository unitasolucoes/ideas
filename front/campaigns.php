<?php

include('../../../inc/includes.php');
require_once __DIR__ . '/../inc/layout.class.php';
require_once __DIR__ . '/../inc/ticket.class.php';

Session::checkLoginUser();

$profile_id = $_SESSION['glpiactiveprofile']['id'] ?? 0;
if (!PluginIdeasConfig::canView($profile_id)) {
    Html::displayRightError();
    exit;
}

$config = PluginIdeasConfig::getConfig();
$menu_name = $config['menu_name'] ?? 'Pulsar';
$title = sprintf(__('%s – Campanhas', 'ideas'), $menu_name);

if (Session::getCurrentInterface() === 'helpdesk') {
    Html::helpHeader($title, '', 'helpdesk', 'management');
} else {
    Html::header($title, $_SERVER['PHP_SELF'], 'management', 'PluginIdeasMenu');
}

$plugin_web = Plugin::getWebDir('ideas');
$campaigns = PluginIdeasTicket::getCampaigns();
$can_admin = PluginIdeasConfig::canAdmin($profile_id);
$campaign_form_url = PluginIdeasConfig::getCampaignFormUrl();

?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
<link rel="stylesheet" href="<?php echo $plugin_web; ?>/css/pulsar.css" />

<?php
PluginIdeasLayout::shellOpen();

$headerActions = [];

if ($can_admin) {
    $campaign_form_target = $campaign_form_url ?: ($plugin_web . '/front/nova_campanha.php');
    $headerActions[] = [
        'href'  => $campaign_form_target,
        'label' => __('Nova Campanha', 'ideas'),
        'icon'  => 'fa-solid fa-flag-checkered',
        'class' => 'primary'
    ];
}

$headerActions[] = [
    'href'  => $plugin_web . '/front/feed.php',
    'label' => __('Voltar ao Feed', 'ideas'),
    'icon'  => 'fa-solid fa-arrow-left',
    'class' => 'ghost'
];

PluginIdeasLayout::renderHeader([
    'badge'    => Html::entities_deep($menu_name),
    'title'    => __('Campanhas', 'ideas'),
    'subtitle' => __('Visualize todas as campanhas cadastradas e seus principais detalhes.', 'ideas'),
    'actions'  => $headerActions
]);

$navItems = PluginIdeasLayout::getNavItems($can_admin);
PluginIdeasLayout::renderNav($navItems, 'campaigns');
PluginIdeasLayout::contentOpen();
?>

  <div class="pulsar-filters-container card-u">
    <div class="pulsar-search-inline">
      <input id="buscar-campanhas" type="text" placeholder="<?php echo __('     Buscar campanhas por título ou descrição...', 'ideas'); ?>">
      <button class="search-clear" type="button" title="<?php echo __('Limpar busca', 'ideas'); ?>"><i class="fa-solid fa-times"></i></button>
    </div>

    <div class="pulsar-filters">
      <div class="filter-group">
        <label><?php echo __('Status:', 'ideas'); ?></label>
        <select class="filter-select" id="filter-campaign-status">
          <option value=""><?php echo __('Todos', 'ideas'); ?></option>
          <?php
          $statuses = Ticket::getAllStatusArray();
          foreach ($statuses as $key => $value) {
              echo "<option value='" . (int) $key . "'>" . htmlspecialchars($value) . "</option>";
          }
          ?>
        </select>
      </div>

      <div class="filter-group">
        <label><?php echo __('Ordenar por:', 'ideas'); ?></label>
        <select class="filter-select" id="filter-campaign-order">
          <option value="recent"><?php echo __('Mais recentes', 'ideas'); ?></option>
          <option value="oldest"><?php echo __('Mais antigas', 'ideas'); ?></option>
          <option value="deadline"><?php echo __('Prazo mais próximo', 'ideas'); ?></option>
          <option value="ideas"><?php echo __('Mais ideias associadas', 'ideas'); ?></option>
        </select>
      </div>

      <button class="btn-u ghost" id="btn-campaigns-clear"><i class="fa-solid fa-filter-circle-xmark"></i> <?php echo __('Limpar filtros', 'ideas'); ?></button>
    </div>

    <div class="ideas-counter">
      <span id="campaigns-count"><?php echo count($campaigns); ?></span> <?php echo __('campanhas encontradas', 'ideas'); ?>
    </div>
  </div>

  <main class="pulsar-ideas-grid">

    <?php if (!empty($campaigns)): ?>
      <?php foreach ($campaigns as $campaign):
        $statusInfo = PluginIdeasTicket::getStatusPresentation($campaign['status'] ?? Ticket::INCOMING);
        $author_name = $campaign['author_name'] ?? __('Não informado', 'ideas');
        $author_initials = $campaign['author_initials'] ?? '??';

        $campaign_title_attr = htmlspecialchars(strtolower(strip_tags((string) $campaign['name'])));
        $campaign_content_attr = '';
        $content_preview = '';

        if (!empty($campaign['content'])) {
            $content_preview = html_entity_decode($campaign['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $content_preview = strip_tags($content_preview);
            $content_preview = preg_replace('/\s+/', ' ', $content_preview);
            $content_preview = trim($content_preview);
            $campaign_content_attr = htmlspecialchars(strtolower($content_preview));

            if (strlen($content_preview) > 180) {
                $content_preview = substr($content_preview, 0, 180) . '...';
            }
        }

        $deadline_raw = $campaign['time_to_resolve'] ?? null;
        $deadline_ts = $deadline_raw ? strtotime($deadline_raw) : 0;
        $deadline_label = $deadline_raw ? Html::convDateTime($deadline_raw) : __('Não informado', 'ideas');

        $ideas_count = isset($campaign['ideas_count']) ? (int) $campaign['ideas_count'] : PluginIdeasTicket::countIdeasByCampaign((int) $campaign['id']);
        $created_ts = !empty($campaign['date']) ? strtotime($campaign['date']) : 0;
        $created_label = !empty($campaign['date']) ? Html::convDate($campaign['date']) : __('Sem data', 'ideas');

      ?>
      <article class="idea-card card-u idea-card--campaign"
               data-status="<?php echo (int) ($campaign['status'] ?? 0); ?>"
               data-title="<?php echo $campaign_title_attr; ?>"
               data-content="<?php echo $campaign_content_attr; ?>"
               data-date="<?php echo $created_ts; ?>"
               data-deadline="<?php echo $deadline_ts; ?>"
               data-ideas="<?php echo $ideas_count; ?>">

        <div class="idea-card-header">
          <div class="idea-author">
            <span class="author-avatar"><?php echo htmlspecialchars($author_initials); ?></span>
            <div class="author-info">
              <div class="author-name"><?php echo htmlspecialchars($author_name); ?></div>
              <div class="idea-date pulsar-muted"><?php echo htmlspecialchars($created_label); ?></div>
            </div>
          </div>
          <span class="badge <?php echo $statusInfo['class']; ?>">
            <i class="fa-solid <?php echo $statusInfo['icon']; ?>"></i>
            <?php echo htmlspecialchars($statusInfo['label']); ?>
          </span>
        </div>

        <div class="idea-card-body">
          <h3 class="idea-title"><?php echo htmlspecialchars($campaign['name']); ?></h3>
          <?php if (!empty($content_preview)): ?>
            <p class="idea-excerpt"><?php echo htmlspecialchars($content_preview); ?></p>
          <?php endif; ?>
        </div>

        <div class="idea-card-footer">
          <div class="idea-stats">
            <span class="stat-item">
              <i class="fa-solid fa-calendar-days"></i>
              <span><?php echo htmlspecialchars($deadline_label); ?></span>
            </span>
            <span class="stat-item">
              <i class="fa-solid fa-lightbulb"></i>
              <span><?php echo $ideas_count; ?> <?php echo $ideas_count === 1 ? __('ideia associada', 'ideas') : __('ideias associadas', 'ideas'); ?></span>
            </span>
            <span class="stat-item">
              <i class="fa-solid fa-user"></i>
              <span><?php echo htmlspecialchars($author_name); ?></span>
            </span>
          </div>

          <div class="idea-actions">
            <a href="campaign.php?id=<?php echo (int) $campaign['id']; ?>" class="btn-outline btn-small">
              <i class="fa-solid fa-arrow-right"></i> <?php echo __('Ver detalhes', 'ideas'); ?>
            </a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="card-u text-center empty-state">
        <div class="empty">
          <div class="empty-icon">
            <i class="fa-solid fa-flag" style="font-size: 4rem; color: #ccc;"></i>
          </div>
          <p class="empty-title"><?php echo __('Nenhuma campanha cadastrada', 'ideas'); ?></p>
          <p class="empty-subtitle pulsar-muted"><?php echo __('Assim que uma campanha for criada ela aparecerá aqui.', 'ideas'); ?></p>
          <?php if ($can_admin): ?>
          <div class="empty-action">
            <a href="<?php echo htmlspecialchars($campaign_form_target); ?>" class="btn-u primary">
              <i class="fa-solid fa-flag-checkered"></i> <?php echo __('Criar primeira campanha', 'ideas'); ?>
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="no-results-message" style="display: none;">
      <div class="card-u text-center">
        <div class="empty">
          <div class="empty-icon">
            <i class="fa-solid fa-search" style="font-size: 3rem; color: #ccc;"></i>
          </div>
          <p class="empty-title"><?php echo __('Nenhuma campanha corresponde aos filtros', 'ideas'); ?></p>
          <p class="empty-subtitle pulsar-muted"><?php echo __('Tente ajustar seus critérios de busca.', 'ideas'); ?></p>
        </div>
      </div>
    </div>

  </main>
        <?php PluginIdeasLayout::contentClose(); ?>
    <?php PluginIdeasLayout::shellClose(); ?>

<script>
(function() {
  const searchInput = document.getElementById('buscar-campanhas');
  if (!searchInput) {
    return;
  }

  const statusFilter = document.getElementById('filter-campaign-status');
  const orderFilter = document.getElementById('filter-campaign-order');
  const clearFiltersBtn = document.getElementById('btn-campaigns-clear');
  const searchClearBtn = document.querySelector('.pulsar-filters-container .search-clear');
  const counter = document.getElementById('campaigns-count');
  const noResultsMsg = document.querySelector('.no-results-message');
  const grid = document.querySelector('.pulsar-ideas-grid');

  let cards = Array.from(document.querySelectorAll('.idea-card--campaign'));

  function applyFilters() {
    const term = searchInput.value.toLowerCase().trim();
    const statusValue = statusFilter ? statusFilter.value : '';
    const orderValue = orderFilter ? orderFilter.value : 'recent';

    let visibleCards = cards.filter(card => {
      if (term) {
        const title = card.dataset.title || '';
        const content = card.dataset.content || '';
        if (!title.includes(term) && !content.includes(term)) {
          return false;
        }
      }

      if (statusValue && card.dataset.status !== statusValue) {
        return false;
      }

      return true;
    });

    if (orderValue === 'recent') {
      visibleCards.sort((a, b) => parseInt(b.dataset.date || '0', 10) - parseInt(a.dataset.date || '0', 10));
    } else if (orderValue === 'oldest') {
      visibleCards.sort((a, b) => parseInt(a.dataset.date || '0', 10) - parseInt(b.dataset.date || '0', 10));
    } else if (orderValue === 'deadline') {
      visibleCards.sort((a, b) => {
        const aDeadline = parseInt(a.dataset.deadline || '0', 10) || Number.MAX_SAFE_INTEGER;
        const bDeadline = parseInt(b.dataset.deadline || '0', 10) || Number.MAX_SAFE_INTEGER;
        return aDeadline - bDeadline;
      });
    } else if (orderValue === 'ideas') {
      visibleCards.sort((a, b) => parseInt(b.dataset.ideas || '0', 10) - parseInt(a.dataset.ideas || '0', 10));
    }

    cards.forEach(card => {
      card.style.display = 'none';
    });

    visibleCards.forEach(card => {
      card.style.display = '';
    });

    if (counter) {
      counter.textContent = visibleCards.length;
    }

    if (noResultsMsg) {
      noResultsMsg.style.display = visibleCards.length === 0 && cards.length > 0 ? 'block' : 'none';
    }

    if (grid) {
      visibleCards.forEach(card => grid.appendChild(card));
    }
  }

  searchInput.addEventListener('input', applyFilters);

  if (statusFilter) {
    statusFilter.addEventListener('change', applyFilters);
  }

  if (orderFilter) {
    orderFilter.addEventListener('change', applyFilters);
  }

  if (searchClearBtn) {
    searchClearBtn.addEventListener('click', function() {
      searchInput.value = '';
      searchInput.focus();
      applyFilters();
    });
  }

  if (clearFiltersBtn) {
    clearFiltersBtn.addEventListener('click', function() {
      searchInput.value = '';
      if (statusFilter) {
        statusFilter.value = '';
      }
      if (orderFilter) {
        orderFilter.value = 'recent';
      }
      applyFilters();
    });
  }

  applyFilters();
})();
</script>

<?php
if (Session::getCurrentInterface() === 'helpdesk') {
    Html::helpFooter();
} else {
    Html::footer();
}
