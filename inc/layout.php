<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Funções utilitárias para padronizar o layout das páginas do Pulsar.
 */
class PluginIdeasLayout
{
    /**
     * Abre o contêiner principal do shell do aplicativo.
     *
     * @param string $extraClass Classes adicionais para o contêiner raiz.
     */
    public static function shellOpen(string $extraClass = ''): void
    {
        static $bodyClassInjected = false;

        if (!$bodyClassInjected) {
            echo '<script>document.body.classList.add("pulsar-full-width");</script>';
            $bodyClassInjected = true;
        }

        $classes = trim('pulsar-app-shell pulsar-wrap ' . $extraClass);
        echo '<div class="' . $classes . '">';
    }

    /**
     * Fecha o contêiner principal do shell do aplicativo.
     */
    public static function shellClose(): void
    {
        echo '</div>';
    }

    /**
     * Abre o contêiner de conteúdo principal.
     *
     * @param string $extraClass Classes adicionais para a área de conteúdo.
     */
    public static function contentOpen(string $extraClass = ''): void
    {
        $classes = trim('pulsar-app-content ' . $extraClass);
        echo '<div class="' . $classes . '">';
    }

    /**
     * Fecha o contêiner de conteúdo principal.
     */
    public static function contentClose(): void
    {
        echo '</div>';
    }

    /**
     * Renderiza o cabeçalho padrão das páginas.
     *
     * @param array $options
     *  - badge       (string) Texto do badge exibido acima do título.
     *  - title       (string) Título principal do cabeçalho.
     *  - subtitle    (string) Subtítulo opcional.
     *  - actions     (array)  Botões de ação (href, label, icon, class).
     *  - extra_class (string) Classe CSS adicional para o cabeçalho.
     */
    public static function renderHeader(array $options = []): void
    {
        $badge      = $options['badge'] ?? '';
        $title      = $options['title'] ?? '';
        $subtitle   = $options['subtitle'] ?? '';
        $actions    = $options['actions'] ?? [];
        $extraClass = $options['extra_class'] ?? '';

        $classes = trim('pulsar-app-header ' . $extraClass);

        echo '<header class="' . $classes . '">';
        echo '<div class="pulsar-app-header__content">';
        if ($badge !== '') {
            echo '<span class="pulsar-app-badge">' . $badge . '</span>';
        }
        if ($title !== '') {
            echo '<h1>' . $title . '</h1>';
        }
        if ($subtitle !== '') {
            echo '<p>' . $subtitle . '</p>';
        }
        echo '</div>';

        if (!empty($actions)) {
            echo '<div class="pulsar-app-actions">';
            foreach ($actions as $action) {
                $href   = $action['href'] ?? '#';
                $label  = $action['label'] ?? '';
                $icon   = $action['icon'] ?? '';
                $class  = trim('btn-u ' . ($action['class'] ?? 'ghost'));
                $target = isset($action['target']) ? ' target="' . htmlspecialchars($action['target']) . '"' : '';

                echo '<a class="' . htmlspecialchars($class) . '" href="' . htmlspecialchars($href) . '"' . $target . '>';
                if ($icon !== '') {
                    echo '<i class="' . htmlspecialchars($icon) . '"></i> ';
                }
                echo $label;
                echo '</a>';
            }
            echo '</div>';
        }

        echo '</header>';
    }

    /**
     * Retorna os itens padrão do menu de navegação.
     *
     * @param bool  $canAdmin  Define se os links de administração devem aparecer.
     * @param array $options   Permite ocultar itens específicos usando ['hide' => ['chave']].
     *
     * @return array
     */
    public static function getNavItems(bool $canAdmin, array $options = []): array
    {
        $pluginWeb = Plugin::getWebDir('ideas');

        $items = [
            'feed'      => [
                'label' => __('Feed', 'ideas'),
                'icon'  => 'fa-solid fa-bolt',
                'href'  => $pluginWeb . '/front/feed.php'
            ],
            'my_ideas'  => [
                'label' => __('Minhas Ideias', 'ideas'),
                'icon'  => 'fa-solid fa-lightbulb',
                'href'  => $pluginWeb . '/front/my_ideas.php'
            ],
            'ideas_all' => [
                'label' => __('Todas as Ideias', 'ideas'),
                'icon'  => 'fa-solid fa-list',
                'href'  => $pluginWeb . '/front/ideas_all.php'
            ],
            'campaigns' => [
                'label' => __('Campanhas', 'ideas'),
                'icon'  => 'fa-solid fa-flag',
                'href'  => $pluginWeb . '/front/campaigns.php'
            ],
            'dashboard' => [
                'label' => __('Dashboard', 'ideas'),
                'icon'  => 'fa-solid fa-chart-bar',
                'href'  => $pluginWeb . '/front/dashboard.php'
            ],
        ];

        if ($canAdmin) {
            $items['settings'] = [
                'label' => __('Configurações', 'ideas'),
                'icon'  => 'fa-solid fa-gear',
                'href'  => $pluginWeb . '/front/settings.php'
            ];
        }

        $hide = $options['hide'] ?? [];
        if (!empty($hide)) {
            foreach ($hide as $key) {
                unset($items[$key]);
            }
        }

        return $items;
    }

    /**
     * Renderiza a barra de navegação principal.
     *
     * @param array  $navItems  Lista de itens no formato ['key' => ['label' => '', 'icon' => '', 'href' => '']].
     * @param string $activeKey Chave do item ativo.
     * @param string $extraClass Classe CSS adicional para a barra de navegação.
     */
    public static function renderNav(array $navItems, string $activeKey, string $extraClass = ''): void
    {
        $classes = trim('pulsar-app-nav ' . $extraClass);
        echo '<nav class="' . $classes . '" aria-label="' . __('Navegação do Pulsar', 'ideas') . '">';
        foreach ($navItems as $key => $item) {
            $href  = $item['href'] ?? '#';
            $label = $item['label'] ?? '';
            $icon  = $item['icon'] ?? '';
            $class = 'pulsar-app-nav__item';
            if ($key === $activeKey) {
                $class .= ' pulsar-app-nav__item--active';
            }

            if (!empty($item['class'])) {
                $class .= ' ' . $item['class'];
            }

            $ariaCurrent = $key === $activeKey ? ' aria-current="page"' : '';

            echo '<a class="' . htmlspecialchars($class) . '" href="' . htmlspecialchars($href) . '"' . $ariaCurrent . '>';
            if ($icon !== '') {
                echo '<i class="' . htmlspecialchars($icon) . '"></i> ';
            }
            echo '<span>' . $label . '</span>';
            echo '</a>';
        }
        echo '</nav>';
    }
}

