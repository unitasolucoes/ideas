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
$campaign_form_url = PluginIdeasConfig::getCampaignFormUrl();
$plugin_web = Plugin::getWebDir('ideas');

$title = sprintf(__('%s – Feed', 'ideas'), $menu_name);
if (Session::getCurrentInterface() == "helpdesk") {
   Html::helpHeader($title, '', 'helpdesk', 'management');
} else {
   Html::header($title, $_SERVER['PHP_SELF'], 'management', 'PluginIdeasMenu');
}

$campaigns = PluginIdeasTicket::getCampaigns(['is_active' => true]);
$ideas = PluginIdeasTicket::getIdeas();
$ideas = array_slice($ideas, 0, 3);
$ranking = PluginIdeasUserPoints::getRanking('total', 4);
$can_admin = PluginIdeasConfig::canAdmin($user_profile);
$can_like = PluginIdeasConfig::canLike($user_profile);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="<?php echo $plugin_web; ?>/css/pulsar.css"/>

<?php
PluginIdeasLayout::shellOpen();

$headerActions = [
    [
        'href'  => $idea_form_url,
        'label' => __('Nova Ideia', 'ideas'),
        'icon'  => 'fa-solid fa-lightbulb',
        'class' => 'primary'
    ]
];

if ($can_admin) {
    $headerActions[] = [
        'href'  => $campaign_form_url,
        'label' => __('Nova Campanha', 'ideas'),
        'icon'  => 'fa-solid fa-flag',
        'class' => 'ghost'
    ];
}

PluginIdeasLayout::renderHeader([
    'badge'    => Html::entities_deep($menu_name),
    'title'    => __('Feed de ideias e campanhas', 'ideas'),
    'subtitle' => __('Acompanhe campanhas ativas, engaje o time e transforme ideias em resultado.', 'ideas'),
    'actions'  => $headerActions
]);

$navItems = PluginIdeasLayout::getNavItems($can_admin);
PluginIdeasLayout::renderNav($navItems, 'feed');
PluginIdeasLayout::contentOpen();
?>

  <div class="pulsar-search">
    <input id="buscar-campanhas" type="text" placeholder="<?php echo __('     Buscar campanhas...', 'ideas'); ?>">
    <button class="search-clear" type="button" title="Limpar busca"><i class="fa-solid fa-times"></i></button>
  </div>

  <main class="pulsar-grid">

    <section class="main-content">

<?php foreach ($campaigns as $campaign): ?>
<?php
  $deadline = $campaign['time_to_resolve'] ?? null;
  $deadline_label = $deadline ? Html::convDateTime($deadline) : __('Não informado', 'ideas');
  $ideas_count = isset($campaign['ideas_count']) ? (int) $campaign['ideas_count'] : PluginIdeasTicket::countIdeasByCampaign((int) $campaign['id']);
  $author_name = $campaign['author_name'] ?? __('Não informado', 'ideas');
?>
<article class="campanha card-u highlight" data-title="<?php echo htmlspecialchars($campaign['name']); ?>">
  <div class="campanha-header">
    <h2><i class="fa-solid fa-flag"></i> <?php echo htmlspecialchars($campaign['name']); ?></h2>
    <!-- ✅ BOTÃO COMPARTILHAR -->
    <button class="share share-campaign-btn" 
            title="Compartilhar" 
            data-campaign-id="<?php echo $campaign['id']; ?>"
            data-campaign-url="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/ideas/front/campaign.php?id=<?php echo $campaign['id']; ?>">
      <i class="fa-solid fa-share-nodes"></i>
    </button>
  </div>
  <div class="kpis">
    <span class="chip"><i class="fa-solid fa-calendar-days"></i> <?php echo htmlspecialchars($deadline_label); ?></span>
    <span class="chip"><i class="fa-solid fa-lightbulb"></i> <?php echo $ideas_count; ?> <?php echo $ideas_count === 1 ? __('ideia', 'ideas') : __('ideias', 'ideas'); ?></span>
    <span class="chip"><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($author_name); ?></span>
  </div>
  <div class="camp-buttons">
    <a href="campaign.php?id=<?php echo $campaign['id']; ?>" class="btn-green"><i class="fa-solid fa-circle-info"></i> Detalhes</a>
    <?php
      $join_char = (strpos($idea_form_url, '?') === false) ? '?' : '&';
      $participar_url = sprintf('%s%scampanha_id=%d', $idea_form_url, $join_char, $campaign['id']);
    ?>
    <a href="<?php echo htmlspecialchars($participar_url); ?>" class="btn-outline"><i class="fa-solid fa-plus"></i> Participar</a>
  </div>
</article>
<?php endforeach; ?>

      <section class="ideas">
        <div class="section-header">
          <h3><i class="fa-solid fa-lightbulb"></i> Ideias Recentes</h3>
          <a href="ideas_all.php" class="btn-link">Ver todas</a>
        </div>

        <?php foreach ($ideas as $idea): ?>
        <div class="idea-card card-u">
          <div class="idea-title"><?php echo htmlspecialchars($idea['name']); ?></div>
          <div class="idea-meta pulsar-muted">
            <?php echo __('por', 'ideas'); ?> <?php echo htmlspecialchars($idea['author_name'] ?? __('Não informado', 'ideas')); ?> • <?php echo Html::convDate($idea['date']); ?>
          </div>
          <div class="idea-foot">
            <?php $statusInfo = PluginIdeasTicket::getStatusPresentation($idea['status']); ?>
            <span class="badge <?php echo $statusInfo['class']; ?>">
              <i class="fa-solid <?php echo $statusInfo['icon']; ?>"></i>
              <?php echo htmlspecialchars($statusInfo['label']); ?>
            </span>
            
            <!-- Botão de Like -->
            <button class="stat-btn like-btn <?php echo $idea['has_liked'] ? 'liked' : ''; ?>" 
                    data-ticket="<?php echo $idea['id']; ?>"
                    data-liked="<?php echo $idea['has_liked'] ? '1' : '0'; ?>"
                    <?php echo !$can_like ? 'disabled' : ''; ?>>
              <i class="fa-solid fa-heart"></i>
              <span class="like-count"><?php echo $idea['likes_count']; ?></span>
            </button>
            
            <span><i class="fa-solid fa-comment"></i> <?php echo $idea['comments_count']; ?></span>
            <span class="pulsar-muted"><?php echo Html::convDate($idea['date']); ?></span>
            
            <a href="idea.php?id=<?php echo $idea['id']; ?>" class="btn-outline btn-xs">
              Ver detalhes
            </a>
          </div>
        </div>
        <?php endforeach; ?>

      </section>
    </section>

    <aside class="sidebar-pulsar">
      <div class="card-u">
        <div class="section-header">
          <h3><i class="fa-solid fa-ranking-star"></i> Ranking Geral</h3>
        </div>
        <ul class="ranking">
          <?php foreach ($ranking as $rank): ?>
          <li class="ranking-item">
            <div class="rank-left">
              <div class="avatar <?php 
                if ($rank['position'] == 1) echo 'gold';
                elseif ($rank['position'] == 2) echo 'silver';
                elseif ($rank['position'] == 3) echo 'bronze';
              ?>"><?php echo $rank['position']; ?></div>
              <div class="who">
                <div class="name"><?php echo htmlspecialchars($rank['user_name']); ?></div>
              </div>
            </div>
            <div class="points"><?php echo $rank['points']; ?> pts</div>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </aside>
  </main>
        <?php PluginIdeasLayout::contentClose(); ?>
    <?php PluginIdeasLayout::shellClose(); ?>

<script src="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/ideas/js/pulsar.js"></script>

<script>
(function(){
  // Sistema de busca de campanhas
  const input = document.getElementById('buscar-campanhas');
  const cards = Array.from(document.querySelectorAll('.campanha'));
  const clearBtn = document.querySelector('.search-clear');
  
  input.addEventListener('input', function(){
    const q = this.value.toLowerCase().trim();
    cards.forEach(c => {
      const t = (c.dataset.title || '').toLowerCase();
      c.style.display = t.includes(q) ? '' : 'none';
    });
  });
  
  clearBtn.addEventListener('click', function(){
    input.value = '';
    input.dispatchEvent(new Event('input'));
    input.focus();
  });

  // ✅ Sistema de COMPARTILHAMENTO de CAMPANHAS
  document.querySelectorAll('.share-campaign-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      const campaignUrl = this.dataset.campaignUrl;
      const icon = this.querySelector('i');
      const originalIcon = icon.className;
      
      // Copiar URL
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(window.location.origin + campaignUrl).then(function() {
          // ✅ Mudar para check
          icon.className = 'fa-solid fa-check';
          
          // ✅ Voltar após 2 segundos
          setTimeout(function() {
            icon.className = originalIcon;
          }, 2000);
        }).catch(function(err) {
          console.error('Erro ao copiar:', err);
          alert('Não foi possível copiar o link');
        });
      } else {
        // Fallback
        const url = window.location.origin + campaignUrl;
        const textarea = document.createElement('textarea');
        textarea.value = url;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
          document.execCommand('copy');
          icon.className = 'fa-solid fa-check';
          
          setTimeout(function() {
            icon.className = originalIcon;
          }, 2000);
        } catch (err) {
          console.error('Erro ao copiar:', err);
          alert('Não foi possível copiar o link');
        }
        
        document.body.removeChild(textarea);
      }
    });
  });

  // ✅ Likes com spinner
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('.like-btn');
    if (!btn || btn.disabled) return;
    
    e.preventDefault();
    e.stopPropagation();
    
    const ticketId = btn.getAttribute('data-ticket');
    const icon = btn.querySelector('i');
    const originalIcon = icon.className;
    
    // Mostrar spinner
    icon.className = 'fa-solid fa-spinner fa-spin';
    btn.disabled = true;
    
    PulsarLike.toggle(ticketId, function(response) {
      if (response.success) {
        // Recarregar a página
        window.location.reload();
      } else {
        // Voltar ao estado original se der erro
        icon.className = originalIcon;
        btn.disabled = false;
        alert(response.message || 'Erro ao processar curtida');
      }
    });
  });
})();
</script>

<?php
Html::footer();
?>
