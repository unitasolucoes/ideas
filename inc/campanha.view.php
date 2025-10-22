<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

require_once __DIR__ . '/layout.class.php';

/**
 * Renderiza o formulário de nova campanha utilizando um layout inspirado no GLPI.
 *
 * @param array  $campanhas Campanhas existentes para seleção como pai.
 * @param string $csrf      Token CSRF.
 */
function plugin_ideas_render_campanha_form(array $campanhas, string $csrf): void {
    $pluginWeb = Plugin::getWebDir('ideas');
    $config    = PluginIdeasConfig::getConfig();
    $menuName  = $config['menu_name'] ?? 'Pulsar';
    $profileId = $_SESSION['glpiactiveprofile']['id'] ?? 0;
    $canAdminNav = PluginIdeasConfig::canAdmin($profileId);

    $navItems = PluginIdeasLayout::getNavItems($canAdminNav);

    ob_start();
    ?>
    <meta name="csrf-token" content="<?php echo Html::entities_deep($csrf); ?>">
    <?php PluginIdeasLayout::shellOpen(); ?>
        <?php
        PluginIdeasLayout::renderHeader([
            'badge'       => Html::entities_deep($menuName),
            'title'       => __('Nova campanha', 'ideas'),
            'subtitle'    => __('Cadastre uma nova campanha, detalhe a proposta e informe o prazo estimado.', 'ideas'),
            'actions'     => [
                [
                    'href'  => $pluginWeb . '/front/campaigns.php',
                    'label' => __('Voltar às campanhas', 'ideas'),
                    'icon'  => 'fa-solid fa-arrow-left',
                    'class' => 'ghost'
                ]
            ]
        ]);

        PluginIdeasLayout::renderNav($navItems, 'campaigns');
        PluginIdeasLayout::contentOpen();
        ?>
        <div class="pulsar-form">
        <form id="form-nova-campanha" method="post" action="<?php echo $pluginWeb; ?>/front/processar_campanha.php" enctype="multipart/form-data">
            <input type="hidden" name="_glpi_csrf_token" value="<?php echo Html::entities_deep($csrf); ?>">

            <section class="pulsar-card">
                <div class="pulsar-card__header">
                    <h2><?php echo __('Informações da campanha', 'ideas'); ?></h2>
                    <p class="pulsar-card__subtitle"><?php echo __('Informe os dados principais, os benefícios esperados e o prazo estimado da iniciativa.', 'ideas'); ?></p>
                </div>
                <div class="pulsar-card__body">
                    <div class="pulsar-grid">
                        <div class="pulsar-field">
                            <label class="pulsar-label required" for="titulo"><?php echo __('Título da campanha', 'ideas'); ?></label>
                            <input type="text" id="titulo" name="titulo" class="form-control" maxlength="255" required>
                        </div>

                        <div class="pulsar-field">
                            <label class="pulsar-label" for="campanha_pai_id"><?php echo __('Campanha pai (opcional)', 'ideas'); ?></label>
                            <select id="campanha_pai_id" name="campanha_pai_id" class="form-select">
                                <option value="0"><?php echo __('Nenhuma', 'ideas'); ?></option>
                                <?php foreach ($campanhas as $campanha):
                                    $id = (int) $campanha['id'];
                                    $name = Html::entities_deep($campanha['name']);
                                    ?>
                                    <option value="<?php echo $id; ?>"><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="pulsar-field">
                            <label class="pulsar-label" for="prazo_estimado"><?php echo __('Prazo estimado', 'ideas'); ?></label>
                            <input type="text" id="prazo_estimado" name="prazo_estimado" class="form-control flatpickr-input" placeholder="dd/mm/aaaa">
                            <span class="pulsar-note"><?php echo __('Opcional – defina a data prevista para encerramento da campanha.', 'ideas'); ?></span>
                        </div>

                        <div class="pulsar-field pulsar-field--full">
                            <label class="pulsar-label required" for="descricao"><?php echo __('Descrição da campanha', 'ideas'); ?></label>
                            <textarea id="descricao" name="descricao" class="tinymce-editor" rows="10" required></textarea>
                            <span class="pulsar-note"><?php echo __('Descreva objetivos, escopo e contexto geral.', 'ideas'); ?></span>
                        </div>

                        <div class="pulsar-field pulsar-field--full">
                            <label class="pulsar-label required" for="beneficios"><?php echo __('Benefícios esperados', 'ideas'); ?></label>
                            <textarea id="beneficios" name="beneficios" class="tinymce-editor" rows="8" required></textarea>
                            <span class="pulsar-note"><?php echo __('Qual impacto positivo a campanha pretende alcançar?', 'ideas'); ?></span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="pulsar-card">
                <div class="pulsar-card__header">
                    <h2><?php echo __('Materiais de apoio', 'ideas'); ?></h2>
                    <p class="pulsar-card__subtitle"><?php echo __('Anexe peças gráficas, planilhas ou apresentações relacionadas.', 'ideas'); ?></p>
                </div>
                <div class="pulsar-card__body">
                    <div class="pulsar-field pulsar-field--full">
                        <div class="pulsar-attachment">
                            <strong><?php echo __('Anexos opcionais', 'ideas'); ?></strong>
                            <span><?php echo __('Arraste e solte ou selecione arquivos (até 100 MB cada).', 'ideas'); ?></span>
                            <input type="file" id="anexos" name="anexos[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx">
                        </div>
                        <span class="pulsar-note"><?php echo __('Aceitamos imagens, PDFs, documentos do Office e apresentações.', 'ideas'); ?></span>
                    </div>
                </div>
            </section>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-u"><?php echo __('Criar campanha', 'ideas'); ?></button>
                <a class="btn btn-secondary btn-u" href="<?php echo $pluginWeb; ?>/front/campaigns.php"><?php echo __('Cancelar', 'ideas'); ?></a>
            </div>
        </form>
        </div>
        <?php PluginIdeasLayout::contentClose(); ?>
    <?php PluginIdeasLayout::shellClose(); ?>
    <?php
    echo ob_get_clean();
}
