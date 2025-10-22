<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

require_once dirname(__DIR__) . '/inc/layout.class.php';

$plugin_web = Plugin::getWebDir('ideas');
$csrf_token = Session::getNewCSRFToken();

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
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="<?php echo $plugin_web; ?>/css/pulsar.css"/>
<meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="_glpi_csrf_token" id="pulsar-csrf-token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

<?php
PluginIdeasLayout::shellOpen();

$navItems = PluginIdeasLayout::getNavItems($can_admin);
PluginIdeasLayout::renderHeader([
    'badge'    => Html::entities_deep($config['menu_name']),
    'title'    => __('Detalhes da ideia', 'ideas'),
    'subtitle' => __('Acompanhe a evolução e a discussão desta proposta.', 'ideas'),
    'actions'  => [
        [
            'href'  => $plugin_web . '/front/feed.php',
            'label' => __('Voltar ao Feed', 'ideas'),
            'icon'  => 'fa-solid fa-arrow-left',
            'class' => 'ghost'
        ],
        [
            'href'  => $plugin_web . '/front/my_ideas.php',
            'label' => __('Minhas Ideias', 'ideas'),
            'icon'  => 'fa-solid fa-lightbulb',
            'class' => 'ghost'
        ]
    ]
]);

PluginIdeasLayout::renderNav($navItems, 'ideas_all');
PluginIdeasLayout::contentOpen('detail-layout');
?>

  <div class="detail-grid">
    
    <!-- Coluna Principal -->
    <div class="detail-main">

      <?php $timelineSteps = $timeline_data['steps'] ?? []; ?>
      <?php if (!empty($timelineSteps)): ?>
      <section class="card-u timeline-card">
        <header class="timeline-card__header">
          <div>
            <h2><?php echo __('Visualização das etapas da ideia', 'ideas'); ?></h2>
            <p class="timeline-card__subtitle"><?php echo __('Acompanhe o ciclo da ideia: do rascunho à implementação.', 'ideas'); ?></p>
          </div>
          <span class="timeline-card__badge">
            <i class="fa-solid fa-location-dot"></i>
            <?php echo __('Etapa atual:', 'ideas'); ?>
            <strong><?php echo htmlspecialchars($timeline_data['current_label'] ?? ''); ?></strong>
          </span>
        </header>
        <ol class="timeline-steps">
          <?php foreach ($timelineSteps as $step): ?>
          <li class="timeline-step timeline-step--<?php echo htmlspecialchars($step['state']); ?>">
            <span class="timeline-step__badge"><?php echo (int) $step['position']; ?></span>
            <div class="timeline-step__content">
              <h3><?php echo htmlspecialchars($step['label']); ?></h3>
              <p><?php echo htmlspecialchars($step['description']); ?></p>
            </div>
          </li>
          <?php endforeach; ?>
        </ol>
      </section>
      <?php endif; ?>

      <!-- Card da Ideia -->
      <article class="card-u idea-detail-card">
        <?php $statusInfo = PluginIdeasTicket::getStatusPresentation($data['status']); ?>
        <div class="idea-status-badge">
          <span class="badge <?php echo $statusInfo['class']; ?>">
            <i class="fa-solid <?php echo $statusInfo['icon']; ?>"></i>
            <?php echo htmlspecialchars($statusInfo['label']); ?>
          </span>
        </div>

        <header class="idea-header">
          <div class="author-section">
            <div class="author-avatar"><?php echo htmlspecialchars($author_initials); ?></div>
            <div class="author-info">
              <h2 class="idea-title"><?php echo htmlspecialchars($data['name']); ?></h2>
              <p class="idea-meta">
                por <strong><?php echo htmlspecialchars($author_name); ?></strong> • 
                Enviada em <?php echo Html::convDate($data['date']); ?>
              </p>
            </div>
          </div>
        </header>

        <div class="idea-content">
          <?php 
          $content = $data['content'];
          $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
          echo $content; 
          ?>
        </div>

        <footer class="idea-footer">
          <div class="idea-stats">
            <button class="stat-btn like-btn <?php echo $data['has_liked'] ? 'liked' : ''; ?>" 
                    data-ticket="<?php echo $tickets_id; ?>"
                    data-liked="<?php echo $data['has_liked'] ? '1' : '0'; ?>"
                    <?php echo !$can_like ? 'disabled' : ''; ?>>
              <i class="fa-solid fa-heart"></i>
              <span class="like-count"><?php echo $data['likes_count']; ?></span>
            </button>
            <span class="stat-item">
              <i class="fa-solid fa-comment"></i>
              <span id="comment-count"><?php echo count($comments); ?></span>
            </span>
            <span class="stat-item">
              <i class="fa-solid fa-eye"></i>
              <span><?php echo $data['views_count']; ?></span>
            </span>
          </div>
          <button class="btn-outline btn-small share-btn" data-id="<?php echo $tickets_id; ?>">
            <i class="fa-solid fa-share-nodes"></i> Compartilhar
          </button>
        </footer>
      </article>

      <!-- Seção de Comentários -->
      <section class="card-u comments-section" id="comments-section">
        <header class="comments-header">
          <h2><i class="fa-solid fa-comments"></i> Comentários (<span id="total-comments"><?php echo count($comments); ?></span>)</h2>
        </header>

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
      
      <div class="card-u sidebar-card">
        <h3><i class="fa-solid fa-flag"></i> Campanha</h3>
        <?php if (isset($data['campaign_id']) && $data['campaign_id'] > 0): ?>
          <p class="sidebar-label">Esta ideia está vinculada a:</p>
          <a href="campaign.php?id=<?php echo $data['campaign_id']; ?>" class="campaign-link">
            <?php echo htmlspecialchars($data['campaign_name'] ?? 'Campanha'); ?>
          </a>
          <?php if (!empty($data['campaign_deadline'])): ?>
          <p class="campaign-deadline"><i class="fa-solid fa-calendar"></i> Prazo: <?php echo $data['campaign_deadline']; ?></p>
          <?php endif; ?>
        <?php else: ?>
          <p class="pulsar-muted">Sem campanha associada</p>
        <?php endif; ?>
      </div>

      <div class="card-u sidebar-card">
        <h3><i class="fa-solid fa-info-circle"></i> Status</h3>
        <div class="status-info">
          <?php $statusInfo = PluginIdeasTicket::getStatusPresentation($data['status']); ?>
          <span class="badge-large <?php echo $statusInfo['class']; ?>">
            <i class="fa-solid <?php echo $statusInfo['icon']; ?>"></i>
            <?php echo htmlspecialchars($statusInfo['label']); ?>
          </span>
          <p class="status-date">
            <i class="fa-solid fa-calendar"></i>
            <?php echo Html::convDate($data['date']); ?>
          </p>
        </div>
      </div>

    </aside>

  </div>
        <?php PluginIdeasLayout::contentClose(); ?>
    <?php PluginIdeasLayout::shellClose(); ?>

<!-- ✅ LOG 1: Antes de carregar pulsar.js -->
<script>
console.log('=================================');
console.log('🔵 INÍCIO DO CARREGAMENTO');
console.log('=================================');
console.log('📍 Caminho do pulsar.js:', '<?php echo $CFG_GLPI['root_doc']; ?>/plugins/ideas/js/pulsar.js');
console.log('🎫 Ticket ID:', <?php echo $tickets_id; ?>);
</script>

<!-- ✅ Carregar pulsar.js -->
<script src="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/ideas/js/pulsar.js"></script>

<!-- ✅ LOG 2: Depois de carregar pulsar.js -->
<script>
console.log('=================================');
console.log('🟢 APÓS CARREGAR pulsar.js');
console.log('=================================');
console.log('✅ PulsarLike existe?', typeof PulsarLike !== 'undefined' ? 'SIM' : 'NÃO');
console.log('✅ PulsarComment existe?', typeof PulsarComment !== 'undefined' ? 'SIM' : 'NÃO');
console.log('✅ getGLPICSRFToken existe?', typeof getGLPICSRFToken !== 'undefined' ? 'SIM' : 'NÃO');

if (typeof PulsarLike === 'undefined') {
  console.error('❌ ERRO CRÍTICO: PulsarLike NÃO FOI CARREGADO!');
}
if (typeof PulsarComment === 'undefined') {
  console.error('❌ ERRO CRÍTICO: PulsarComment NÃO FOI CARREGADO!');
}
</script>

<!-- ✅ Código principal -->
<script>
(function() {
  'use strict';

  console.log('=================================');
  console.log('🟡 INICIALIZANDO EVENTOS');
  console.log('=================================');

  // Sistema de Toggle de Comentários
  console.log('📌 Procurando botão #toggle-comments-btn...');
  const toggleBtn = document.getElementById('toggle-comments-btn');
  console.log('   Encontrado:', toggleBtn ? 'SIM' : 'NÃO');
  
  if (toggleBtn) {
    let expanded = false;
    toggleBtn.addEventListener('click', function(e) {
      e.preventDefault();
      console.log('🔵 Toggle de comentários clicado! Expanded:', !expanded);
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

  // Sistema de Curtida
  console.log('📌 Procurando botão .like-btn...');
  const likeBtn = document.querySelector('.like-btn');
  console.log('   Encontrado:', likeBtn ? 'SIM' : 'NÃO');
  
  if (likeBtn) {
    console.log('   Ticket ID:', likeBtn.dataset.ticket);
    console.log('   Liked?:', likeBtn.dataset.liked);
    console.log('   Disabled?:', likeBtn.disabled);
    
    if (typeof PulsarLike !== 'undefined') {
      likeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('=================================');
        console.log('❤️ BOTÃO DE CURTIR CLICADO!');
        console.log('=================================');
        
        if (this.disabled) {
          console.log('⚠️ Botão desabilitado - saindo');
          return;
        }

        const ticketId = this.dataset.ticket;
        const likeCountSpan = this.querySelector('.like-count');
        
        console.log('📊 Dados do like:');
        console.log('   Ticket ID:', ticketId);
        console.log('   Contagem atual:', likeCountSpan.textContent);

        console.log('🚀 Chamando PulsarLike.toggle...');
        PulsarLike.toggle(ticketId, (response) => {
          console.log('📥 Resposta recebida:', response);
          
          if (response.success) {
            console.log('✅ Sucesso!');
            console.log('   Nova contagem:', response.count);
            console.log('   Liked?:', response.liked);
            
            likeCountSpan.textContent = response.count;
            this.dataset.liked = response.liked ? '1' : '0';

            if (response.liked) {
              this.classList.add('liked');
            } else {
              this.classList.remove('liked');
            }
          } else {
            console.error('❌ Erro:', response.message);
            alert(response.message || 'Erro ao processar curtida');
          }
        });
      });
    } else {
      console.error('❌ PulsarLike não definido - botão de like não funcionará');
    }
  }

  // Sistema de Comentários
  console.log('📌 Procurando formulário #comment-form...');
  const commentForm = document.getElementById('comment-form');
  console.log('   Encontrado:', commentForm ? 'SIM' : 'NÃO');
  
  if (commentForm) {
    if (typeof PulsarComment !== 'undefined') {
      commentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        console.log('=================================');
        console.log('💬 FORMULÁRIO DE COMENTÁRIO ENVIADO!');
        console.log('=================================');

        const contentInput = document.getElementById('comment-content');
        const content = contentInput.value.trim();
        
        console.log('📝 Conteúdo:', content);
        console.log('📏 Tamanho:', content.length);

        if (!content) {
          console.log('⚠️ Conteúdo vazio - mostrando alerta');
          alert('Por favor, escreva um comentário.');
          return;
        }

        const ticketId = <?php echo $tickets_id; ?>;
        const submitBtn = this.querySelector('button[type="submit"]');
        
        console.log('🎫 Ticket ID:', ticketId);
        console.log('🚀 Chamando PulsarComment.add...');
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';

        PulsarComment.add(ticketId, content, (response) => {
          console.log('📥 Resposta recebida:', response);
          
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Enviar Comentário';

          if (response.success) {
            console.log('✅ Sucesso!');
            console.log('   Comentário ID:', response.comment.id);
            console.log('   User:', response.comment.user_name);
            console.log('   Initials:', response.comment.user_initials);
            
            const commentsList = document.getElementById('comments-list');
            const emptyComments = commentsList.querySelector('.empty-comments');
            
            if (emptyComments) {
              console.log('🗑️ Removendo mensagem de "sem comentários"');
              emptyComments.remove();
            }

            const newComment = document.createElement('article');
            newComment.className = 'comment-item comment-new';
            newComment.innerHTML = `
              <div class="comment-avatar">${response.comment.user_initials}</div>
              <div class="comment-body">
                <div class="comment-header">
                  <strong class="comment-author">${response.comment.user_name}</strong>
                  <span class="comment-date">${response.comment.date}</span>
                </div>
                <div class="comment-content">${response.comment.content}</div>
              </div>
            `;

            const firstComment = commentsList.querySelector('.comment-item');
            if (firstComment) {
              commentsList.insertBefore(newComment, firstComment);
            } else {
              commentsList.appendChild(newComment);
            }

            const commentCount = document.getElementById('total-comments');
            if (commentCount) {
              const oldCount = parseInt(commentCount.textContent);
              const newCount = oldCount + 1;
              console.log('📊 Atualizando contador:', oldCount, '→', newCount);
              commentCount.textContent = newCount;
            }

            contentInput.value = '';
            contentInput.focus();

            console.log('📜 Rolando para o novo comentário...');
            newComment.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

          } else {
            console.error('❌ Erro:', response.message);
            alert(response.message || 'Erro ao adicionar comentário');
          }
        });
      });
    } else {
      console.error('❌ PulsarComment não definido - formulário não funcionará');
    }
  }

  // Sistema de Compartilhamento
  console.log('📌 Procurando botão .share-btn...');
  const shareBtn = document.querySelector('.share-btn');
  console.log('   Encontrado:', shareBtn ? 'SIM' : 'NÃO');
  
  if (shareBtn) {
    shareBtn.addEventListener('click', function(e) {
      e.preventDefault();
      console.log('🔵 Botão de compartilhar clicado!');
      
      const url = window.location.href;
      console.log('   URL:', url);
      
      if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => {
          console.log('✅ Link copiado!');
          const originalHTML = this.innerHTML;
          this.innerHTML = '<i class="fa-solid fa-check"></i> Link copiado!';
          this.style.borderColor = '#10b981';
          this.style.color = '#10b981';
          
          setTimeout(() => {
            this.innerHTML = originalHTML;
            this.style.borderColor = '';
            this.style.color = '';
          }, 2000);
        });
      } else {
        prompt('Copie o link:', url);
      }
    });
  }

  console.log('=================================');
  console.log('✅ INICIALIZAÇÃO COMPLETA!');
  console.log('=================================');

})();
</script>

<?php
Html::footer();
?>