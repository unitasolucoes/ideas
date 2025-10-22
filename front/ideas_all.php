<?php

include('../../../inc/includes.php');
require_once __DIR__ . '/../inc/layout.class.php';
Session::checkLoginUser();

$user_profile = $_SESSION['glpiactiveprofile']['id'] ?? 0;

if (!PluginIdeasConfig::canView($user_profile)) {
    Html::displayRightError();
    exit;
}

$config = PluginIdeasConfig::getConfig();
$menu_name = $config['menu_name'];
$idea_form_url = PluginIdeasConfig::getIdeaFormUrl();
$plugin_web = Plugin::getWebDir('ideas');
$csrf_token = Session::getNewCSRFToken();

$title = sprintf(__('%s – Todas as Ideias', 'ideas'), $menu_name);
if (Session::getCurrentInterface() == "helpdesk") {
    Html::helpHeader($title, '', 'helpdesk', 'management');
} else {
    Html::header($title, $_SERVER['PHP_SELF'], 'management', 'PluginIdeasMenu');
}

// Buscar todas as ideias
$ideas = PluginIdeasTicket::getIdeas();

// Buscar campanhas para o filtro
$campaigns = PluginIdeasTicket::getCampaigns();
$can_admin = PluginIdeasConfig::canAdmin($user_profile);
$can_like  = PluginIdeasConfig::canLike($user_profile);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="<?php echo $plugin_web; ?>/css/pulsar.css"/>
<meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="_glpi_csrf_token" id="pulsar-csrf-token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

<?php
PluginIdeasLayout::shellOpen();

$actions = [
    [
        'href'  => $idea_form_url,
        'label' => __('Nova Ideia', 'ideas'),
        'icon'  => 'fa-solid fa-plus',
        'class' => 'primary'
    ],
    [
        'href'  => $plugin_web . '/front/feed.php',
        'label' => __('Voltar ao Feed', 'ideas'),
        'icon'  => 'fa-solid fa-arrow-left',
        'class' => 'ghost'
    ]
];

PluginIdeasLayout::renderHeader([
    'badge'    => Html::entities_deep($menu_name),
    'title'    => __('Todas as Ideias', 'ideas'),
    'subtitle' => __('Explore todas as ideias enviadas pela comunidade.', 'ideas'),
    'actions'  => $actions
]);

$navItems = PluginIdeasLayout::getNavItems($can_admin);
PluginIdeasLayout::renderNav($navItems, 'ideas_all');
PluginIdeasLayout::contentOpen();
?>

  <div class="pulsar-filters-container card-u">
    <div class="pulsar-search-inline">
      <input id="buscar-ideias" type="text" placeholder="<?php echo __('     Buscar ideias por título ou conteúdo...', 'ideas'); ?>">
      <button class="search-clear" type="button" title="Limpar busca"><i class="fa-solid fa-times"></i></button>
    </div>

    <div class="pulsar-filters">
      <div class="filter-group">
        <label>Status:</label>
        <select class="filter-select" id="filter-status">
          <option value="">Todos</option>
          <?php
          $statuses = Ticket::getAllStatusArray();
          foreach ($statuses as $key => $value) {
              echo "<option value='$key'>" . htmlspecialchars($value) . "</option>";
          }
          ?>
        </select>
      </div>
      
      <div class="filter-group">
        <label>Campanha:</label>
        <select class="filter-select" id="filter-campaign">
          <option value="">Todas</option>
          <?php foreach ($campaigns as $campaign): ?>
            <option value="<?php echo $campaign['id']; ?>"><?php echo htmlspecialchars($campaign['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="filter-group">
        <label>Ordenar por:</label>
        <select class="filter-select" id="filter-order">
          <option value="recent">Mais recentes</option>
          <option value="oldest">Mais antigas</option>
          <option value="popular">Mais curtidas</option>
          <option value="commented">Mais comentadas</option>
        </select>
      </div>

      <button class="btn-u ghost" id="btn-clear-filters"><i class="fa-solid fa-filter-circle-xmark"></i> Limpar filtros</button>
    </div>

    <div class="ideas-counter">
      <span id="ideas-count"><?php echo count($ideas); ?></span> ideias encontradas
    </div>
  </div>

  <main class="pulsar-ideas-grid">
  
    <?php if (count($ideas) > 0): ?>
      <?php foreach ($ideas as $idea):
        $statusInfo = PluginIdeasTicket::getStatusPresentation($idea['status']);
        $author_name = $idea['author_name'] ?? __('Não informado', 'ideas');
        $author_initials = $idea['author_initials'] ?? '??';
      ?>
      <?php
        $idea_title_attr = htmlspecialchars(strtolower(strip_tags((string) $idea['name'])));
        $idea_content_attr = htmlspecialchars(strtolower(strip_tags((string) $idea['content'])));
      ?>
      <?php
        $content_preview = $idea['content'];
        $content_preview = html_entity_decode($content_preview, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content_preview = strip_tags($content_preview);
        $content_preview = preg_replace('/\s+/', ' ', $content_preview);
        $content_preview = trim($content_preview);
        if (strlen($content_preview) > 180) {
            $content_preview = substr($content_preview, 0, 180) . '...';
        }
      ?>
      <article class="idea-card card-u"
               data-status="<?php echo (int) $idea['status']; ?>"
               data-title="<?php echo $idea_title_attr; ?>"
               data-content="<?php echo $idea_content_attr; ?>"
               data-likes="<?php echo (int) $idea['likes_count']; ?>"
               data-comments="<?php echo (int) $idea['comments_count']; ?>"
               data-date="<?php echo strtotime($idea['date']); ?>"
               data-campaign="<?php echo $idea['campaign_id'] ? (int) $idea['campaign_id'] : ''; ?>">
        
        <div class="idea-card-header">
          <div class="idea-author">
            <span class="author-avatar"><?php echo htmlspecialchars($author_initials); ?></span>
            <div class="author-info">
              <div class="author-name"><?php echo htmlspecialchars($author_name); ?></div>
              <div class="idea-date pulsar-muted"><?php echo Html::convDate($idea['date']); ?></div>
            </div>
          </div>
          <span class="badge <?php echo $statusInfo['class']; ?>">
            <i class="fa-solid <?php echo $statusInfo['icon']; ?>"></i>
            <?php echo htmlspecialchars($statusInfo['label']); ?>
          </span>
        </div>
        
        <div class="idea-card-body">
          <h3 class="idea-title"><?php echo htmlspecialchars($idea['name']); ?></h3>
          <p class="idea-excerpt"><?php echo htmlspecialchars($content_preview); ?></p>
          <?php if (!empty($idea['campaign_id'])): ?>
          <div class="idea-campaign-chip" title="Campanha vinculada">
            <i class="fa-solid fa-flag"></i>
            <span><?php echo htmlspecialchars($idea['campaign_name']); ?></span>
          </div>
          <?php endif; ?>
        </div>

        <div class="idea-card-footer">
          <div class="idea-stats">
            <button class="stat-btn like-btn <?php echo $idea['has_liked'] ? 'liked' : ''; ?>"
                    data-ticket="<?php echo $idea['id']; ?>"
                    data-liked="<?php echo $idea['has_liked'] ? '1' : '0'; ?>"
                    <?php echo !$can_like ? 'disabled aria-disabled="true"' : ''; ?>>
              <i class="fa-solid fa-heart"></i>
              <span class="like-count"><?php echo $idea['likes_count']; ?></span>
            </button>
            <span class="stat-item">
              <i class="fa-solid fa-comment"></i>
              <span><?php echo $idea['comments_count']; ?></span>
            </span>
          </div>
          <div class="idea-actions">
            <a href="idea.php?id=<?php echo $idea['id']; ?>" class="btn-outline btn-small">
              <i class="fa-solid fa-arrow-right"></i> Ver detalhes
            </a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="card-u text-center empty-state">
        <div class="empty">
          <div class="empty-icon">
            <i class="fa-solid fa-lightbulb" style="font-size: 4rem; color: #ccc;"></i>
          </div>
          <p class="empty-title">Nenhuma ideia encontrada</p>
          <p class="empty-subtitle pulsar-muted">Seja o primeiro a compartilhar uma ideia!</p>
          <div class="empty-action">
            <a href="<?php echo htmlspecialchars($idea_form_url); ?>" class="btn-u primary">
              <i class="fa-solid fa-plus"></i> Enviar primeira ideia
            </a>
          </div>
        </div>
      </div>
    <?php endif; ?>
    
    <div class="no-results-message" style="display: none;">
      <div class="card-u text-center">
        <div class="empty">
          <div class="empty-icon">
            <i class="fa-solid fa-search" style="font-size: 3rem; color: #ccc;"></i>
          </div>
          <p class="empty-title">Nenhuma ideia corresponde aos filtros</p>
          <p class="empty-subtitle pulsar-muted">Tente ajustar seus critérios de busca.</p>
        </div>
      </div>
    </div>
    
  </main>

        <?php PluginIdeasLayout::contentClose(); ?>
    <?php PluginIdeasLayout::shellClose(); ?>


<script src="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/ideas/js/pulsar.js"></script>

<script>
(function() {
  const searchInput = document.getElementById('buscar-ideias');
  const statusFilter = document.getElementById('filter-status');
  const campaignFilter = document.getElementById('filter-campaign');
  const orderFilter = document.getElementById('filter-order');
  const clearFiltersBtn = document.getElementById('btn-clear-filters');
  const searchClearBtn = document.querySelector('.search-clear');
  const ideasCount = document.getElementById('ideas-count');
  const noResultsMsg = document.querySelector('.no-results-message');
  
  let allCards = Array.from(document.querySelectorAll('.idea-card'));
  
  // Função principal de filtro
  function applyFilters() {
    const searchTerm = searchInput.value.toLowerCase().trim();
    const statusValue = statusFilter.value;
    const campaignValue = campaignFilter.value;
    const orderValue = orderFilter.value;
    
    let visibleCards = allCards.filter(card => {
      // Filtro de busca
      if (searchTerm) {
        const title = card.dataset.title || '';
        const content = card.dataset.content || '';
        if (!title.includes(searchTerm) && !content.includes(searchTerm)) {
          return false;
        }
      }
      
      // Filtro de status
      if (statusValue && card.dataset.status !== statusValue) {
        return false;
      }
      
      // Filtro de campanha (implementar quando necessário)
      if (campaignValue && card.dataset.campaign !== campaignValue) {
        return false;
      }
      
      return true;
    });
    
    // Ordenação
    if (orderValue === 'recent') {
      visibleCards.sort((a, b) => parseInt(b.dataset.date) - parseInt(a.dataset.date));
    } else if (orderValue === 'oldest') {
      visibleCards.sort((a, b) => parseInt(a.dataset.date) - parseInt(b.dataset.date));
    } else if (orderValue === 'popular') {
      visibleCards.sort((a, b) => parseInt(b.dataset.likes) - parseInt(a.dataset.likes));
    } else if (orderValue === 'commented') {
      visibleCards.sort((a, b) => parseInt(b.dataset.comments) - parseInt(a.dataset.comments));
    }
    
    // Mostrar/ocultar cards
    allCards.forEach(card => card.style.display = 'none');
    visibleCards.forEach(card => card.style.display = 'flex');
    
    // Atualizar contador
    ideasCount.textContent = visibleCards.length;
    
    // Mostrar mensagem de "sem resultados"
    if (visibleCards.length === 0 && allCards.length > 0) {
      noResultsMsg.style.display = 'block';
    } else {
      noResultsMsg.style.display = 'none';
    }
    
    // Re-ordenar no DOM
    const grid = document.querySelector('.pulsar-ideas-grid');
    visibleCards.forEach(card => grid.appendChild(card));
  }
  
  // Event listeners
  searchInput.addEventListener('input', applyFilters);
  statusFilter.addEventListener('change', applyFilters);
  campaignFilter.addEventListener('change', applyFilters);
  orderFilter.addEventListener('change', applyFilters);
  
  searchClearBtn.addEventListener('click', function() {
    searchInput.value = '';
    applyFilters();
    searchInput.focus();
  });
  
  clearFiltersBtn.addEventListener('click', function() {
    searchInput.value = '';
    statusFilter.value = '';
    campaignFilter.value = '';
    orderFilter.value = 'recent';
    applyFilters();
  });
  
  // Sistema de curtidas
  document.querySelectorAll('.like-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();

      if (this.hasAttribute('disabled')) {
        return;
      }

      const ticketId = this.dataset.ticket;
      const likeCountSpan = this.querySelector('.like-count');

      PulsarLike.toggle(ticketId, (response) => {
        if (response.success) {
          likeCountSpan.textContent = response.count;
          this.dataset.liked = response.liked ? '1' : '0';

          if (response.liked) {
            this.classList.add('liked');
          } else {
            this.classList.remove('liked');
          }

          // Atualizar dataset para ordenação
          this.closest('.idea-card').dataset.likes = response.count;
        } else {
          const message = response && response.message ? response.message : 'Erro ao processar curtida';
          alert(message);
        }
      });
    });
  });

})();
</script>

<?php
Html::footer();
?>
