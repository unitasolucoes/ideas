<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

require_once __DIR__ . '/layout.class.php';

/**
 * Renderiza o formulário de nova ideia utilizando um layout inspirado no GLPI.
 *
 * @param array  $campanhas          Lista de campanhas disponíveis.
 * @param array  $areas              Lista de áreas impactadas.
 * @param array  $objetivos          Objetivos estratégicos disponíveis.
 * @param array  $ideiasExistentes   Ideias já cadastradas.
 * @param string $csrf               Token CSRF.
* @param int    $selectedCampaignId ID de campanha pré-selecionada.
* @param int    $currentUserId      ID do usuário autenticado.
*/
function plugin_ideas_render_ideia_form(
    array $campanhas,
    array $areas,
    string $csrf,
    int $selectedCampaignId = 0,
    int $currentUserId = 0
): void {
    $pluginWeb = Plugin::getWebDir('ideas');

    $config    = PluginIdeasConfig::getConfig();
    $menuName  = $config['menu_name'] ?? 'Pulsar';
    $profileId = $_SESSION['glpiactiveprofile']['id'] ?? 0;
    $canAdmin  = PluginIdeasConfig::canAdmin($profileId);

    $navItems = PluginIdeasLayout::getNavItems($canAdmin);

    $entityId = $_SESSION['glpiactive_entity'] ?? 0;
    $currentUserId = (int) $currentUserId;
    $authorDropdowns = [];
    $authorCount = 3;

    for ($index = 0; $index < $authorCount; $index++) {
        $fieldName = sprintf('autores[%d]', $index);
        $defaultValue = ($index === 0 && $currentUserId > 0) ? $currentUserId : 0;

        $authorDropdowns[$index] = Dropdown::show('User', [
            'name'                 => $fieldName,
            'entity'               => $entityId,
            'right'                => 'all',
            'display'              => false,
            'value'                => $defaultValue,
            'display_emptychoice'  => $index !== 0,
            'emptylabel'           => __('Selecione um colaborador', 'ideas'),
            'comments'             => false,
            'addicon'              => false,
            'width'                => '100%',
            'condition'            => ['is_deleted' => 0, 'is_active' => 1],
            'displaywith'          => ['firstname', 'realname'],
            'autocomplete'         => 1
        ]);
    }

    ob_start();
    ?>
    <meta name="csrf-token" content="<?php echo Html::entities_deep($csrf); ?>">
    <?php PluginIdeasLayout::shellOpen('pulsar-idea-shell'); ?>
        <?php
        PluginIdeasLayout::renderHeader([
            'badge'       => Html::entities_deep($menuName),
            'title'       => __('Compartilhe uma nova ideia', 'ideas'),
            'subtitle'    => __('Descreva sua proposta, vincule à campanha ativa e acompanhe todas as etapas até a implementação.', 'ideas'),
            'extra_class' => 'pulsar-idea-header',
            'actions'     => [
                [
                    'href'  => $pluginWeb . '/front/feed.php',
                    'label' => __('Voltar ao Feed', 'ideas'),
                    'icon'  => 'fa-solid fa-arrow-left',
                    'class' => 'ghost'
                ]
            ]
        ]);

        PluginIdeasLayout::renderNav($navItems, '', 'pulsar-idea-nav');
        PluginIdeasLayout::contentOpen();
        ?>

        <section class="pulsar-card pulsar-card--timeline">
            <div class="pulsar-card__header">
                <h2><?php echo __('Visualização das etapas da ideia', 'ideas'); ?></h2>
                <p class="pulsar-card__subtitle"><?php echo __('Acompanhe o ciclo da ideia: do rascunho à implementação.', 'ideas'); ?></p>
            </div>
            <div class="pulsar-card__body">
                <ol class="pulsar-timeline" data-timeline>
                    <li class="pulsar-timeline__step is-current" data-step="rascunho">
                        <span class="pulsar-timeline__badge">1</span>
                        <div class="pulsar-timeline__content">
                            <h3><?php echo __('Rascunho', 'ideas'); ?></h3>
                            <p><?php echo __('Preencha as principais informações da sua proposta.', 'ideas'); ?></p>
                        </div>
                    </li>
                    <li class="pulsar-timeline__step" data-step="avaliacao_inicial">
                        <span class="pulsar-timeline__badge">2</span>
                        <div class="pulsar-timeline__content">
                            <h3><?php echo __('Avaliação Inicial', 'ideas'); ?></h3>
                            <p><?php echo __('A equipe responsável analisa o alinhamento com a campanha.', 'ideas'); ?></p>
                        </div>
                    </li>
                    <li class="pulsar-timeline__step" data-step="avaliacao_tecnica">
                        <span class="pulsar-timeline__badge">3</span>
                        <div class="pulsar-timeline__content">
                            <h3><?php echo __('Avaliação Técnica', 'ideas'); ?></h3>
                            <p><?php echo __('Especialistas verificam viabilidade, riscos e recursos necessários.', 'ideas'); ?></p>
                        </div>
                    </li>
                    <li class="pulsar-timeline__step" data-step="comite">
                        <span class="pulsar-timeline__badge">4</span>
                        <div class="pulsar-timeline__content">
                            <h3><?php echo __('Comitê de Inovação', 'ideas'); ?></h3>
                            <p><?php echo __('O comitê prioriza, aprova e define os próximos passos.', 'ideas'); ?></p>
                        </div>
                    </li>
                    <li class="pulsar-timeline__step" data-step="implementacao">
                        <span class="pulsar-timeline__badge">5</span>
                        <div class="pulsar-timeline__content">
                            <h3><?php echo __('Implementação', 'ideas'); ?></h3>
                            <p><?php echo __('A ideia ganha vida e passa a ser monitorada em produção.', 'ideas'); ?></p>
                        </div>
                    </li>
                </ol>
            </div>
        </section>

        <section class="pulsar-info-grid">
            <article class="pulsar-mini-card">
                <h3><?php echo __('Informações', 'ideas'); ?></h3>
                <p><?php echo __('Defina campanha, título e a área mais impactada pela proposta.', 'ideas'); ?></p>
            </article>
            <article class="pulsar-mini-card">
                <h3><?php echo __('Revisão', 'ideas'); ?></h3>
                <p><?php echo __('Detalhe o problema identificado, descreva a solução e indique os resultados esperados.', 'ideas'); ?></p>
            </article>
            <article class="pulsar-mini-card">
                <h3><?php echo __('Envio', 'ideas'); ?></h3>
                <p><?php echo __('Escolha os autores responsáveis, inclua anexos e finalize o envio da ideia.', 'ideas'); ?></p>
            </article>
        </section>

        <div class="pulsar-idea-layout">
            <main class="pulsar-idea-main">
                <div class="pulsar-form">
                    <form id="form-nova-ideia" method="post" action="<?php echo $pluginWeb; ?>/front/processar_ideia.php" enctype="multipart/form-data">
                        <input type="hidden" name="_glpi_csrf_token" value="<?php echo Html::entities_deep($csrf); ?>">
                        <input type="hidden" name="acao" value="enviar">

                        <section class="pulsar-card">
                            <div class="pulsar-card__header">
                                <h2><?php echo __('Identificação da ideia', 'ideas'); ?></h2>
                                <p class="pulsar-card__subtitle"><?php echo __('Preencha os dados principais e detalhe a proposta da ideia.', 'ideas'); ?></p>
                            </div>
                            <div class="pulsar-card__body">
                                <div class="pulsar-grid">
                                    <div class="pulsar-field">
                                        <label class="pulsar-label required" for="campanha_id"><?php echo __('Selecione a campanha', 'ideas'); ?></label>
                                        <select id="campanha_id" name="campanha_id" class="form-select" required>
                                            <option value="" <?php echo $selectedCampaignId === 0 ? 'selected' : ''; ?>><?php echo __('Selecione uma campanha', 'ideas'); ?></option>
                                            <?php foreach ($campanhas as $campanha):
                                                $id = (int) $campanha['id'];
                                                $deadlineRaw = $campanha['time_to_resolve'] ?? null;
                                                if (empty($deadlineRaw) || $deadlineRaw === '0000-00-00 00:00:00') {
                                                    $deadlineRaw = null;
                                                }
                                                $deadline = Html::entities_deep($deadlineRaw ?? '');
                                                $start = Html::entities_deep($campanha['date'] ?? '');
                                                $name = Html::entities_deep($campanha['name']);
                                                ?>
                                                <option value="<?php echo $id; ?>"
                                                        data-deadline="<?php echo $deadline; ?>"
                                                        data-start="<?php echo $start; ?>"
                                                    <?php echo $id === $selectedCampaignId ? 'selected' : ''; ?>>
                                                    <?php echo $name; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div id="campaign-preview" class="campaign-preview" data-campaign-preview style="display:none;"></div>
                                    </div>

                                    <div class="pulsar-field">
                                        <label class="pulsar-label required" for="titulo"><?php echo __('Título', 'ideas'); ?></label>
                                        <input type="text" id="titulo" name="titulo" class="form-control" maxlength="255" required>
                                        <span class="pulsar-note"><?php echo __('Use um título curto, objetivo e fácil de identificar.', 'ideas'); ?></span>
                                    </div>

                                    <div class="pulsar-field">
                                        <label class="pulsar-label" for="area_impactada"><?php echo __('Área mais impactada', 'ideas'); ?></label>
                                        <select id="area_impactada" name="area_impactada" class="form-select">
                                            <option value=""><?php echo __('Selecione a área impactada', 'ideas'); ?></option>
                                            <?php foreach ($areas as $area):
                                                $areaId = (int) ($area['id'] ?? 0);
                                                $areaName = Html::entities_deep($area['name'] ?? '');
                                            ?>
                                                <option value="<?php echo $areaId; ?>"><?php echo $areaName; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="pulsar-grid">
                                    <div class="pulsar-field pulsar-field--full">
                                        <label class="pulsar-label required" for="problema_identificado"><?php echo __('Problema identificado', 'ideas'); ?></label>
                                        <textarea id="problema_identificado" name="problema_identificado" class="tinymce-editor" rows="6" required></textarea>
                                        <span class="pulsar-note"><?php echo __('O que você entende que pode ser melhorado? Descreva o desafio ou problema que quer corrigir. Você pode incluir dados e informações para melhor explicar o problema.', 'ideas'); ?></span>
                                    </div>

                                    <div class="pulsar-field pulsar-field--full">
                                        <label class="pulsar-label required" for="solucao_proposta"><?php echo __('Solução proposta', 'ideas'); ?></label>
                                        <textarea id="solucao_proposta" name="solucao_proposta" class="tinymce-editor" rows="6" required></textarea>
                                        <span class="pulsar-note"><?php echo __('Qual a sua ideia e como entende possível colocá-la em prática?', 'ideas'); ?></span>
                                    </div>

                                    <div class="pulsar-field pulsar-field--full">
                                        <label class="pulsar-label required" for="beneficios_resultados"><?php echo __('Benefícios e resultados esperados', 'ideas'); ?></label>
                                        <textarea id="beneficios_resultados" name="beneficios_resultados" class="tinymce-editor" rows="6" required></textarea>
                                        <span class="pulsar-note"><?php echo __('Quais os ganhos que a sua ideia trará para a empresa? Como podemos mensurar ou calcular os resultados?', 'ideas'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="pulsar-card">
                            <div class="pulsar-card__header">
                                <h2><?php echo __('Autores e anexos', 'ideas'); ?></h2>
                                <p class="pulsar-card__subtitle"><?php echo __('Selecione os responsáveis pela ideia e envie materiais complementares, se necessário.', 'ideas'); ?></p>
                            </div>
                            <div class="pulsar-card__body">
                                <div class="pulsar-grid">
                                    <div class="pulsar-field pulsar-field--full">
                                        <label class="pulsar-label required"><?php echo __('Autor da ideia', 'ideas'); ?></label>
                                        <span class="pulsar-note"><?php echo __('Escolha até 3 autores para acompanhar a proposta (o primeiro campo é obrigatório).', 'ideas'); ?></span>
                                        <div class="pulsar-author-grid">
                                            <?php foreach ($authorDropdowns as $index => $dropdown): ?>
                                                <div class="pulsar-author-grid__item">
                                                    <span class="pulsar-author-grid__label">
                                                        <?php echo $index === 0
                                                            ? __('Autor 1 (obrigatório)', 'ideas')
                                                            : sprintf(__('Autor %d (opcional)', 'ideas'), $index + 1); ?>
                                                    </span>
                                                    <div class="glpi-select2-container"><?php echo $dropdown; ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="pulsar-field pulsar-field--full">
                                        <div class="pulsar-attachment">
                                            <strong><?php echo __('Anexos (opcional)', 'ideas'); ?></strong>
                                            <span><?php echo __('Arraste e solte ou selecione arquivos (até 100 MB por arquivo).', 'ideas'); ?></span>
                                            <input type="file" id="anexos" name="anexos[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-u" data-action="enviar"><?php echo __('Enviar ideia', 'ideas'); ?></button>
                            <a class="btn btn-tertiary btn-u" href="<?php echo $pluginWeb; ?>/front/feed.php"><?php echo __('Cancelar', 'ideas'); ?></a>
                        </div>
                    </form>
                </div>
            </main>

            <aside class="pulsar-idea-sidebar">
                <div class="pulsar-sidebar-card">
                    <div class="pulsar-sidebar-card__header">
                        <h3><?php echo __('Prazo da campanha', 'ideas'); ?></h3>
                        <span class="pulsar-sidebar-card__muted"><?php echo __('Atualize ao selecionar uma campanha.', 'ideas'); ?></span>
                    </div>
                    <div class="pulsar-sidebar-card__body">
                        <div class="pulsar-countdown">
                            <span class="pulsar-countdown__value" data-campaign-days>00</span>
                            <span class="pulsar-countdown__label"><?php echo __('dias restantes', 'ideas'); ?></span>
                        </div>
                        <p class="pulsar-sidebar-card__info"><?php echo __('Prazo final:', 'ideas'); ?> <strong data-campaign-deadline>--</strong></p>
                        <div class="pulsar-progress">
                            <div class="pulsar-progress__bar">
                                <span class="pulsar-progress__value" data-campaign-progress style="width:0%;"></span>
                            </div>
                            <span class="pulsar-progress__label" data-campaign-progress-label>0%</span>
                        </div>
                    </div>
                </div>

                <div class="pulsar-sidebar-card">
                    <div class="pulsar-sidebar-card__header">
                        <h3><?php echo __('Dicas para uma boa ideia', 'ideas'); ?></h3>
                    </div>
                    <div class="pulsar-sidebar-card__body">
                        <ul class="pulsar-tips">
                            <li><?php echo __('Seja claro e específico no título.', 'ideas'); ?></li>
                            <li><?php echo __('Explique o problema que resolve.', 'ideas'); ?></li>
                            <li><?php echo __('Demonstre os benefícios esperados.', 'ideas'); ?></li>
                            <li><?php echo __('Considere a viabilidade técnica.', 'ideas'); ?></li>
                        </ul>
                    </div>
                </div>

            </aside>
        </div>
        <?php PluginIdeasLayout::contentClose(); ?>
    <?php PluginIdeasLayout::shellClose(); ?>
    <?php
    echo ob_get_clean();
}
