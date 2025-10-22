<?php
/**
 * Template de detalhes da campanha - COM COMPARTILHAMENTO
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

require_once dirname(__DIR__) . '/inc/layout.class.php';

$plugin_web = Plugin::getWebDir('ideas');
$ideas = $specific_data['ideas'] ?? [];
$idea_form_url = PluginIdeasConfig::getIdeaFormUrl();
$join_char = (strpos($idea_form_url, '?') === false) ? '?' : '&';
$idea_form_campaign_url = sprintf('%s%scampanha_id=%d', $idea_form_url, $join_char, $tickets_id);

$author_name = $data['author_name'] ?? '';
$author_initials = $data['author_initials'] ?? '';

if ($author_name === '' && !empty($data['author_id'])) {
    $fallback_user = new User();
    if ($fallback_user->getFromDB($data['author_id'])) {
        $author_name = $fallback_user->getFriendlyName();
        $author_initials = PluginIdeasConfig::getUserInitials(
            $fallback_user->fields['firstname'] ?? '',
            $fallback_user->fields['realname'] ?? ''
        );
    }
}

if ($author_name === '' && !empty($data['users_id_recipient'])) {
    $fallback_user = new User();
    if ($fallback_user->getFromDB($data['users_id_recipient'])) {
        $author_name = $fallback_user->getFriendlyName();
        $author_initials = PluginIdeasConfig::getUserInitials(
            $fallback_user->fields['firstname'] ?? '',
            $fallback_user->fields['realname'] ?? ''
        );
    }
}

if ($author_name === '') {
    $author_name = __('Não informado', 'ideas');
}

if ($author_initials === '') {
    $author_initials = '??';
}

$created_at = !empty($data['date']) ? Html::convDate($data['date']) : __('Não informado', 'ideas');
$deadline_label = !empty($data['time_to_resolve']) ? Html::convDateTime($data['time_to_resolve']) : __('Não informado', 'ideas');
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="<?php echo $plugin_web; ?>/css/pulsar.css"/>

<?php
PluginIdeasLayout::shellOpen();

$navItems = PluginIdeasLayout::getNavItems($can_admin);
PluginIdeasLayout::renderHeader([
    'badge'    => Html::entities_deep($config['menu_name']),
    'title'    => htmlspecialchars($data['name']),
    'subtitle' => __('Campanha de coleta de ideias', 'ideas'),
    'actions'  => [
        [
            'href'  => $idea_form_campaign_url,
            'label' => __('Participar com uma ideia', 'ideas'),
            'icon'  => 'fa-solid fa-lightbulb',
            'class' => 'primary'
        ],
        [
            'href'  => $plugin_web . '/front/feed.php',
            'label' => __('Voltar ao Feed', 'ideas'),
            'icon'  => 'fa-solid fa-arrow-left',
            'class' => 'ghost'
        ]
    ]
]);

PluginIdeasLayout::renderNav($navItems, 'campaigns');
PluginIdeasLayout::contentOpen('detail-layout');
?>

  <div class="detail-grid">
    
    <!-- Coluna Principal -->
    <div class="detail-main">
      
      <!-- Card da Campanha -->
      <article class="card-u campaign-detail-card">
        <?php $statusInfo = PluginIdeasTicket::getStatusPresentation($data['status']); ?>
        <div class="campaign-status-badge">
          <span class="badge <?php echo $statusInfo['class']; ?>">
            <i class="fa-solid <?php echo $statusInfo['icon']; ?>"></i>
            <?php echo htmlspecialchars($statusInfo['label']); ?>
          </span>
        </div>

        <header class="campaign-header">
          <div class="campaign-author">
            <div class="author-avatar"><?php echo htmlspecialchars($author_initials); ?></div>
            <div class="campaign-info">
              <h2 class="campaign-title"><?php echo htmlspecialchars($data['name']); ?></h2>
              <p class="campaign-meta">
                <?php echo __('por', 'ideas'); ?> <strong><?php echo htmlspecialchars($author_name); ?></strong> • <?php echo sprintf(__('Criada em %s', 'ideas'), $created_at); ?>
              </p>
            </div>
          </div>
        </header>

        <div class="campaign-content">
          <?php 
          $content = $data['content'];
          $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
          echo $content; 
          ?>
        </div>

        <!-- ✅ FOOTER COM LIKE, COMENTÁRIOS E COMPARTILHAR -->
        <footer class="campaign-footer">
          <div class="idea-stats">
            <!-- Botão de Like -->
            <button class="stat-btn like-btn <?php echo $data['has_liked'] ? 'liked' : ''; ?>" 
                    data-ticket="<?php echo $tickets_id; ?>"
                    data-liked="<?php echo $data['has_liked'] ? '1' : '0'; ?>"
                    <?php echo !$can_like ? 'disabled' : ''; ?>>
              <i class="fa-solid fa-heart"></i>
              <span class="like-count"><?php echo $data['likes_count']; ?></span>
            </button>
            
            <!-- Botão de Comentários (rola até seção) -->
            <button class="stat-btn comment-scroll-btn" id="scroll-to-comments">
              <i class="fa-solid fa-comment"></i>
              <span id="comment-count"><?php echo count($comments); ?></span>
            </button>
            
            <!-- Visualizações -->
            <span class="stat-item">
              <i class="fa-solid fa-eye"></i>
              <span><?php echo $data['views_count']; ?></span>
            </span>
          </div>
          
          <!-- ✅ BOTÃO COMPARTILHAR -->
          <button class="btn-outline btn-small share-btn" data-id="<?php echo $tickets_id; ?>">
            <i class="fa-solid fa-share-nodes"></i> Compartilhar
          </button>
        </footer>
      </article>

      <!-- Ideias Vinculadas -->
      <section class="card-u ideas-section">
        <header class="section-header">
          <h2><i class="fa-solid fa-lightbulb"></i> Ideias Vinculadas (<?php echo count($ideas); ?>)</h2>
        </header>

        <?php if (count($ideas) > 0): ?>
          <div class="ideas-grid">
            <?php foreach ($ideas as $idea): 
              $idea_user = new User();
              $idea_user->getFromDB($idea['users_id_recipient']);
            ?>
            <article class="idea-card-mini">
              <div class="idea-card-header">
                <h3><?php echo htmlspecialchars($idea['name']); ?></h3>
                <?php $statusInfo = PluginIdeasTicket::getStatusPresentation($idea['status']); ?>
                <span class="badge <?php echo $statusInfo['class']; ?>">
                  <i class="fa-solid <?php echo $statusInfo['icon']; ?>"></i>
                  <?php echo htmlspecialchars($statusInfo['label']); ?>
                </span>
              </div>
              <p class="idea-author">por <?php echo $idea_user->getFriendlyName(); ?></p>
              <div class="idea-card-footer">
                <div class="idea-stats-mini">
                  <span><i class="fa-solid fa-heart"></i> <?php echo $idea['likes_count']; ?></span>
                  <span><i class="fa-solid fa-comment"></i> <?php echo $idea['comments_count']; ?></span>
                </div>
                <a href="idea.php?id=<?php echo $idea['id']; ?>" class="btn-outline btn-xs">
                  <i class="fa-solid fa-arrow-right"></i>
                </a>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="fa-solid fa-lightbulb"></i>
            <p>Nenhuma ideia vinculada ainda. Seja o primeiro a participar!</p>
            <a href="<?php echo htmlspecialchars($idea_form_campaign_url); ?>" class="btn-u primary">
              <i class="fa-solid fa-plus"></i> Enviar Primeira Ideia
            </a>
          </div>
        <?php endif; ?>
      </section>

      <!-- Comentários da Campanha -->
      <section class="card-u comments-section" id="comments-section">
        <header class="comments-header">
          <h2><i class="fa-solid fa-comments"></i> Comentários (<span id="total-comments"><?php echo count($comments); ?></span>)</h2>
        </header>

        <!-- Formulário de Novo Comentário -->
        <div class="comment-form-wrapper">
          <form id="comment-form" class="comment-form">
            <div class="comment-input-group">
              <div class="current-user-avatar"><?php 
                $current_user = new User();
                $current_user->getFromDB(Session::getLoginUserID());
                $current_initials = PluginIdeasConfig::getUserInitials(
                    $current_user->fields['firstname'] ?? '', 
                    $current_user->fields['realname'] ?? ''
                );

                
                echo htmlspecialchars($current_initials);
              ?></div>
              <textarea 
                id="comment-content" 
                name="content" 
                placeholder="Adicione seu comentário..." 
                rows="3"
                required></textarea>
            </div>
            <div class="comment-form-actions">
              <button type="submit" class="btn-u primary">
                <i class="fa-solid fa-paper-plane"></i> Enviar Comentário
              </button>
            </div>
          </form>
        </div>

        <!-- Lista de Comentários -->
        <div class="comments-list" id="comments-list">
          <?php if (count($comments) > 0): ?>
            <?php foreach ($comments as $index => $comment): 
              $comment_user_name = $comment['user_name'] ?? 'Usuário';
              
              $name_parts = explode(' ', $comment_user_name);
              if (count($name_parts) >= 2) {
                  $comment_user_initials = strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1));
              } else {
                  $comment_user_initials = strtoupper(substr($comment_user_name, 0, 2));
              }
              
              $is_hidden = $index >= 5 ? 'comment-hidden' : '';
            ?>
            <article class="comment-item <?php echo $is_hidden; ?>" data-comment-index="<?php echo $index; ?>" data-comment-id="<?php echo $comment['id']; ?>">
              <div class="comment-avatar"><?php echo htmlspecialchars($comment_user_initials); ?></div>
              <div class="comment-body">
                <div class="comment-header">
                  <strong class="comment-author"><?php echo htmlspecialchars($comment_user_name); ?></strong>
                  <span class="comment-date"><?php echo Html::convDateTime($comment['date_creation']); ?></span>
                </div>
                <div class="comment-content">
                  <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                </div>
              </div>
            </article>
            <?php endforeach; ?>

            <?php if (count($comments) > 5): ?>
            <div class="comments-toggle">
              <button id="toggle-comments-btn" class="btn-outline btn-toggle">
                <i class="fa-solid fa-chevron-down"></i>
                <span class="toggle-text">Ver mais <?php echo count($comments) - 5; ?> comentários</span>
              </button>
            </div>
            <?php endif; ?>

          <?php else: ?>
            <div class="empty-comments">
              <i class="fa-solid fa-comment-slash"></i>
              <p>Nenhum comentário ainda. Seja o primeiro a comentar!</p>
            </div>
          <?php endif; ?>
        </div>
      </section>

    </div>

    <!-- Sidebar -->
    <aside class="detail-sidebar">
      
      <!-- Status -->
      <div class="card-u sidebar-card">
        <h3><i class="fa-solid fa-info-circle"></i> Status</h3>
        <div class="status-info">
          <?php $statusInfo = PluginIdeasTicket::getStatusPresentation($data['status']); ?>
          <span class="badge-large <?php echo $statusInfo['class']; ?>">
            <i class="fa-solid <?php echo $statusInfo['icon']; ?>"></i>
            <?php echo htmlspecialchars($statusInfo['label']); ?>
          </span>
        </div>
      </div>

      <!-- Prazo -->
      <div class="card-u sidebar-card">
        <h3><i class="fa-solid fa-calendar"></i> Prazo</h3>
        <div class="deadline-info">
          <p class="deadline-date">
            <?php echo htmlspecialchars($deadline_label); ?>
          </p>
          <?php if (!empty($data['time_to_resolve'])): ?>
            <?php
            $now = time();
            $deadline = strtotime($data['time_to_resolve']);
            $days_left = $deadline ? floor(($deadline - $now) / 86400) : null;
            ?>
            <?php if ($days_left !== null): ?>
              <?php if ($days_left > 0): ?>
                <p class="deadline-countdown">
                  <i class="fa-solid fa-clock"></i>
                  Faltam <?php echo $days_left; ?> dias
                </p>
              <?php elseif ($days_left == 0): ?>
                <p class="deadline-countdown today">
                  <i class="fa-solid fa-exclamation-triangle"></i>
                  Último dia!
                </p>
              <?php else: ?>
                <p class="deadline-countdown expired">
                  <i class="fa-solid fa-times-circle"></i>
                  Prazo encerrado
                </p>
              <?php endif; ?>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Estatísticas -->
      <div class="card-u sidebar-card">
        <h3><i class="fa-solid fa-chart-bar"></i> Estatísticas</h3>
        <div class="stats-list">
          <div class="stat-row">
            <span class="stat-label"><i class="fa-solid fa-lightbulb"></i> Total de Ideias</span>
            <span class="stat-value"><?php echo count($ideas); ?></span>
          </div>
          <div class="stat-row">
            <span class="stat-label"><i class="fa-solid fa-heart"></i> Curtidas</span>
            <span class="stat-value"><?php echo $data['likes_count']; ?></span>
          </div>
          <div class="stat-row">
            <span class="stat-label"><i class="fa-solid fa-comment"></i> Comentários</span>
            <span class="stat-value"><?php echo count($comments); ?></span>
          </div>
          <div class="stat-row">
            <span class="stat-label"><i class="fa-solid fa-eye"></i> Visualizações</span>
            <span class="stat-value"><?php echo $data['views_count']; ?></span>
          </div>
        </div>
      </div>

      <!-- Ações Administrativas -->
    </aside>

  </div>
        <?php PluginIdeasLayout::contentClose(); ?>
    <?php PluginIdeasLayout::shellClose(); ?>

<script src="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/ideas/js/pulsar.js"></script>

<script>
(function() {
  'use strict';

  // ✅ Sistema de LIKE
  const likeBtn = document.querySelector('.like-btn');
  
  if (likeBtn) {
    likeBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();

      if (this.disabled) return;

      const ticketId = this.getAttribute('data-ticket');
      const icon = this.querySelector('i');
      const originalIcon = icon.className;
      
      icon.className = 'fa-solid fa-spinner fa-spin';
      this.disabled = true;

      PulsarLike.toggle(ticketId, function(response) {
        if (response.success) {
          window.location.reload();
        } else {
          icon.className = originalIcon;
          likeBtn.disabled = false;
          alert(response.message || 'Erro ao processar curtida');
        }
      });
    });
  }

  // ✅ Scroll suave até comentários
  const scrollToCommentsBtn = document.getElementById('scroll-to-comments');
  if (scrollToCommentsBtn) {
    scrollToCommentsBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const commentsSection = document.getElementById('comments-section');
      if (commentsSection) {
        commentsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        setTimeout(function() {
          const textarea = document.getElementById('comment-content');
          if (textarea) {
            textarea.focus();
          }
        }, 500);
      }
    });
  }

  // ✅ BOTÃO COMPARTILHAR
  const shareBtn = document.querySelector('.share-btn');
  if (shareBtn) {
    shareBtn.addEventListener('click', function(e) {
      e.preventDefault();
      
      const icon = this.querySelector('i');
      const originalIcon = icon.className;
      const originalText = this.innerHTML;
      
      // Copiar URL
      const url = window.location.href;
      
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
          // ✅ Mudar para ícone de check
          icon.className = 'fa-solid fa-check';
          shareBtn.innerHTML = '<i class="fa-solid fa-check"></i> Link copiado!';
          
          // ✅ Voltar ao normal após 2 segundos
          setTimeout(function() {
            shareBtn.innerHTML = originalText;
          }, 2000);
        }).catch(function(err) {
          console.error('Erro ao copiar:', err);
          alert('Não foi possível copiar o link');
        });
      } else {
        // Fallback para navegadores antigos
        const textarea = document.createElement('textarea');
        textarea.value = url;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
          document.execCommand('copy');
          icon.className = 'fa-solid fa-check';
          shareBtn.innerHTML = '<i class="fa-solid fa-check"></i> Link copiado!';
          
          setTimeout(function() {
            shareBtn.innerHTML = originalText;
          }, 2000);
        } catch (err) {
          console.error('Erro ao copiar:', err);
          alert('Não foi possível copiar o link');
        }
        
        document.body.removeChild(textarea);
      }
    });
  }

  // Toggle de comentários
  const toggleBtn = document.getElementById('toggle-comments-btn');
  if (toggleBtn) {
    let expanded = false;

    toggleBtn.addEventListener('click', function(e) {
      e.preventDefault();
      expanded = !expanded;
      
      const allComments = document.querySelectorAll('.comment-item');
      const toggleText = this.querySelector('.toggle-text');
      
      allComments.forEach((comment, index) => {
        if (index >= 5) {
          if (expanded) {
            comment.classList.remove('comment-hidden');
          } else {
            comment.classList.add('comment-hidden');
          }
        }
      });

      const hiddenCount = Array.from(allComments).filter((c, i) => i >= 5).length;
      
      if (expanded) {
        toggleText.textContent = 'Ver menos comentários';
        this.classList.add('expanded');
      } else {
        toggleText.textContent = 'Ver mais ' + hiddenCount + ' comentários';
        this.classList.remove('expanded');
        
        const commentsSection = document.getElementById('comments-section');
        if (commentsSection) {
          commentsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
    });
  }

  // Sistema de comentários
  const commentForm = document.getElementById('comment-form');
  if (commentForm) {
    commentForm.addEventListener('submit', function(e) {
      e.preventDefault();

      const contentInput = document.getElementById('comment-content');
      const content = contentInput.value.trim();

      if (!content) {
        alert('Por favor, escreva um comentário.');
        return;
      }

      const ticketId = <?php echo $tickets_id; ?>;
      const submitBtn = this.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';

      PulsarComment.add(ticketId, content, (response) => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Enviar Comentário';

        if (response.success) {
          window.location.reload();
        } else {
          alert(response.message || 'Erro ao adicionar comentário');
        }
      });
    });
  }
})();
</script>

<?php
Html::footer();
?>