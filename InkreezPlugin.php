<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class InkreezPlugin
{
    private static $_instance = null;
    private $key = null;
    private $role = null;
    private $reservedRoles = array('administrator', 'editor', 'author', 'contributor');


    private function __construct()
    {
        $this->key = esc_html(sanitize_text_field(get_option('inkreez_key')));
        add_action('admin_init', array($this, 'init_settings')); // 22/04
    }

    public static function getInstance()
    {

        if (is_null(self::$_instance)) {
            self::$_instance = new InkreezPlugin();
        }

        return self::$_instance;
    }

    public function activate()
    {
        $this->generateKey('');
        $this->setRole('subscriber');
    }

    public function deactivate()
    {
        delete_option('inkreez_key');
    }

    public function register()
    {
        $__self = $this;

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        //Necessary include for wp_delete_user
        add_action('init', function () {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
        });

        //Register user last login
        //??? add_action('wp_login', array($this, 'userLastLogin'), 10, 2);

        //Settings page
        add_action('admin_menu', array($this, 'inkreez_add_admin_pages'));

        //Handle form submission
        add_action('admin_post_submit-form-inkreez-token', array($this, '_inkreez_token_handle_form_action'));

        if (isset($_GET['actionel'])) {
            $actionel = esc_html(sanitize_text_field($_GET['actionel']));
            if ($actionel == 'TAG') {
                $mail = esc_html(sanitize_email($_GET['mail']));
                $idtf = esc_html(sanitize_text_field($_GET['IDTF']));
                $tag = esc_html(sanitize_text_field($_GET['tag']));
                $sum = esc_html(sanitize_text_field($_GET['SUM']));
                $this->AjoutTag($mail, $idtf, $tag, $sum);
                exit;
            }
        }
    }

    public function inkreez_add_admin_pages()
    {
        $url = esc_url(plugins_url('/assets/inkreez-menu.png', __FILE__));

        add_menu_page('Inkreez Plugin', 'Inkreez', 'manage_options', 'inkreez_plugin', array($this, 'inkreez_admin_index'), $url, 110);
        add_submenu_page(
            'inkreez_plugin', // Parent menu slug
            'GTM Inkreez', // Page title
            'Outils', // Menu title
            'manage_options', // Capability
            'gtm-settings', // Menu slug
            array($this, 'settings_page') // Callback function
        );
    }

    public function inkreez_admin_index()
    {
        $token = esc_html(sanitize_text_field(get_option('inkreez_key')));
        $isAssociated = "";

        wp_register_style('inkreez-index-plugin-style', esc_url(plugins_url('templates/assets/index.css', __FILE__)), array(), '1.0.0', 'all');
        wp_enqueue_style('inkreez-index-plugin-style');

        if ($token) {

            $token = str_replace(' ', '', $token);

            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = esc_html(sanitize_text_field($_SERVER['HTTP_HOST']));
            $url = esc_url($scheme . "://" . $host);
            $base_url = inkreez_getInkreezRestUrl() . "api/is_associated";
            $query_args = array(
                'TOKEN' => $token,
                'URL' => $url
            );
            $apiURL = esc_url_raw(add_query_arg($query_args, $base_url));

            $response = wp_remote_get($apiURL);

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                exit;
            }
            $response = json_decode($response['body'], true);

            if ($response["code"] === "EVERYTHING_OK") {
                $isAssociated = "Votre site est bien associé à Inkreez.";
            }
        }

        require_once plugin_dir_path(__FILE__) . 'templates/index.php';
    }

    public function _inkreez_token_handle_form_action()
    {
        session_start();

        if (isset($_POST['inkreezkey-submit'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'token_settings_nonce')) {
                wp_die('Security check');
            }

            $this->generateKey(esc_html(sanitize_text_field($_POST['inkreezkey-input'])));
            $token = esc_html(sanitize_text_field(get_option('inkreez_key')));
            $token = str_replace(' ', '', $token);

            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = esc_html(sanitize_text_field($_SERVER['HTTP_HOST']));
            $url = esc_url($scheme . "://" . $host);

            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/inkreez/Inkreez.php');
            $version = $plugin_data['Version'];

            $apiURL = inkreez_getInkreezRestUrl() . "api/user_info?TOKEN=$token&URL=$url&TYPE=WooCommerce&VERSION=$version";

            $response = wp_remote_get($apiURL);

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                exit;
            }
            $response = json_decode($response['body'], true);


            if ($response['code'] === "EVERYTHING_OK" || $response['code'] === "URL_ADDED") {
                $_SESSION['inkreez'] = ["message" => $response['message'], "type" => "success"];

                if ($response['code'] === "URL_ADDED") {
                    // Envoyer les commandes dans rabbitMQ
                    inkreez_send_orders_to_api();
                }

            } else {
                if ($response['message'] == '') {
                    $response['message'] = 'Impossible de joindre le serveur. Merci de contacter le support.';
                }
                $_SESSION['inkreez'] = ["message" => $response['message'], "type" => "error"];
            }
        }

        header('Location: ' . esc_url_raw($_SERVER['HTTP_REFERER']));
        die();
    }



    // 22/04
    public function init_settings()
    {
        register_setting('gtm_settings_group', 'inkreez_gtm_code_head');
        register_setting('gtm_settings_group', 'inkreez_gtm_code_body');
    }

    public function settings_page()
    {
        wp_register_style('inkreez-outils-plugin-style', esc_url(plugins_url('templates/assets/outils.css', __FILE__)), array(), '1.0.0', 'all');
        wp_enqueue_style('inkreez-outils-plugin-style');

        if (isset($_POST['submit'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'gtm_settings_nonce')) {
                wp_die('Security check');
            }

            $gtm_code_id = isset($_POST['gtm-code-id']) ? esc_html(sanitize_text_field($_POST['gtm-code-id'])) : '';
            $gtm_code_id = str_replace(' ', '', $gtm_code_id);
            update_option('inkreez_gtm_code_id', $gtm_code_id);

            $_SESSION['inkreez'] = ["message" => "Settings saved successfully.", "type" => "success"];
        }

        $gtm_code_id = get_option('inkreez_gtm_code_id', '');

        require_once plugin_dir_path(__FILE__) . 'templates/outils.php';
    }
    // 22/04

    public function generateKey($key)
    {
        $this->key = $key;
        delete_option('inkreez_key');
        add_option('inkreez_key', $this->key);
    }

    private function setRole($role)
    {
        $this->role = $role;
        if (!get_option('inkreez_role')) {
            add_option('inkreez_role', $this->role);
        } else {
            update_option('inkreez_role', $this->role, true);
        }
    }

    public function getKey()
    {
        return $this->key;
    }
}
