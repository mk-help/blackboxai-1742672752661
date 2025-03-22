<?php
/**
 * Plugin Name: InfoTrack
 * Plugin URI: https://infotrack.com
 * Description: Plugin SaaS de Trackeamento de Tráfego Pago para Infoprodutos
 * Version: 1.0.0
 * Author: InfoTrack Team
 * Author URI: https://infotrack.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: infotrack
 * Domain Path: /languages
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('INFOTRACK_VERSION', '1.0.0');
define('INFOTRACK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('INFOTRACK_PLUGIN_URL', plugin_dir_url(__FILE__));

// Carregar arquivos necessários
require_once INFOTRACK_PLUGIN_DIR . 'includes/db-install.php';
require_once INFOTRACK_PLUGIN_DIR . 'includes/api.php';
require_once INFOTRACK_PLUGIN_DIR . 'includes/logger.php';
require_once INFOTRACK_PLUGIN_DIR . 'includes/helpers.php';

/**
 * Classe principal do plugin
 */
class InfoTrack {
    /**
     * Construtor
     */
    public function __construct() {
        // Hooks de ativação e desativação
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Inicializar hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_infotrack_update_dashboard', array($this, 'ajax_update_dashboard'));
    }

    /**
     * Ativação do plugin
     */
    public function activate() {
        // Criar tabelas no banco de dados
        infotrack_install();

        // Adicionar capabilities para administradores
        $role = get_role('administrator');
        $role->add_cap('manage_infotrack');
    }

    /**
     * Desativação do plugin
     */
    public function deactivate() {
        // Remover capabilities
        $role = get_role('administrator');
        $role->remove_cap('manage_infotrack');
    }

    /**
     * Adicionar menus administrativos
     */
    public function add_admin_menu() {
        add_menu_page(
            __('InfoTrack Dashboard', 'infotrack'),
            __('InfoTrack', 'infotrack'),
            'manage_infotrack',
            'infotrack-dashboard',
            array($this, 'render_dashboard_page'),
            'dashicons-chart-area',
            30
        );

        add_submenu_page(
            'infotrack-dashboard',
            __('Configurações', 'infotrack'),
            __('Configurações', 'infotrack'),
            'manage_infotrack',
            'infotrack-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Carregar assets (CSS e JavaScript)
     */
    public function enqueue_admin_assets($hook) {
        // Verificar se estamos em uma página do plugin
        if (strpos($hook, 'infotrack') === false) {
            return;
        }

        // Bootstrap CSS
        wp_enqueue_style(
            'bootstrap',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
            array(),
            '5.1.3'
        );

        // CSS customizado
        wp_enqueue_style(
            'infotrack-admin',
            INFOTRACK_PLUGIN_URL . 'assets/css/admin.css',
            array('bootstrap'),
            INFOTRACK_VERSION
        );

        // Bootstrap JS
        wp_enqueue_script(
            'bootstrap-bundle',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js',
            array('jquery'),
            '5.1.3',
            true
        );

        // Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '3.7.0',
            true
        );

        // JavaScript customizado
        wp_enqueue_script(
            'infotrack-admin',
            INFOTRACK_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'bootstrap-bundle', 'chartjs'),
            INFOTRACK_VERSION,
            true
        );

        // Localizar script
        wp_localize_script('infotrack-admin', 'infotrack_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('infotrack_nonce')
        ));
    }

    /**
     * Renderizar página do dashboard
     */
    public function render_dashboard_page() {
        include INFOTRACK_PLUGIN_DIR . 'pages/dashboard.php';
    }

    /**
     * Renderizar página de configurações
     */
    public function render_settings_page() {
        include INFOTRACK_PLUGIN_DIR . 'pages/settings.php';
    }

    /**
     * Handler para atualização AJAX do dashboard
     */
    public function ajax_update_dashboard() {
        check_ajax_referer('infotrack_nonce', 'nonce');

        if (!current_user_can('manage_infotrack')) {
            wp_send_json_error('Permissão negada');
        }

        // Sanitizar dados recebidos
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');
        $campaign = sanitize_text_field($_POST['campaign'] ?? '');

        try {
            // Buscar dados atualizados
            $data = array(
                'google_ads' => get_google_ads_data(array(
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'campaign' => $campaign
                )),
                'facebook_ads' => get_facebook_ads_data(array(
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'campaign' => $campaign
                ))
            );

            wp_send_json_success($data);
        } catch (Exception $e) {
            infotrack_log_error($e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
}

// Inicializar o plugin
new InfoTrack();