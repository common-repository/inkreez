<?php

/**
 * Plugin Name:Inkreez
 * Plugin URI: https://www.inkreez.com
 * Description: Boostez vos ventes, administrer votre e-commerce et développer votre Woocommerce
 * Version: 1.6.1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: Inkreez
 * Author: Enimad
 * Author URI: https://www.enimad.com
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once('config.php');
require_once('InkreezPlugin.php');

/**
 * Add to CSV
 *
 * @since 1.0.0
 * @param ElementorPro\Modules\Forms\Registrars\Form_Actions_Registrar $form_actions_registrar
 * @return void
 */
function inkreez_add_new_inkreez_form_action( $form_actions_registrar ) {

    // Check if Elementor Pro is installed and activated
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    if ( is_plugin_active( 'elementor-pro/elementor-pro.php' ) ) {
        include_once( __DIR__ .  '/form-actions/inkreez-add-sequence-action-after-submit.php' );
        $form_actions_registrar->register( new Inkreez_Add_Sequence_Action_After_Submit() );
    } else {
        // Elementor Pro is not active
        error_log( 'Elementor Pro is not activated. The custom form action could not be registered.' );
    }

}
add_action( 'elementor_pro/forms/actions/register', 'inkreez_add_new_inkreez_form_action' );


/*********************************** RABBITMQ ****************************************/
function inkreez_send_orders_to_api($after_date = null)
{
    global $wpdb;
    $urlbase = esc_url(inkreez_getInkreezRestUrl());
    $inkreezKey = esc_html(sanitize_text_field(get_option('inkreez_key')));
    $inkreezKey = str_replace(' ', '', $inkreezKey);

    include_once(ABSPATH . 'wp-load.php');
    $orderData = [];
    $after_date = null;

    $args = array(
        'limit' => -1, // Setting limit to -1 retrieves all orders
        'return' => 'ids', // Specifies that only order IDs should be returned
    );

   if ($after_date !== null) {
        $args['date_created'] = '>' . strtotime($after_date); // Orders created after the specified date
    }

    $order_ids = wc_get_orders($args);

    if(count($order_ids) === 0){
        $orderData['data'] = [0];
    }else{
        $orderData['data'] = $order_ids;
    }

    $orderData['inkreezKey'] = $inkreezKey;

    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = esc_html(sanitize_text_field($_SERVER['HTTP_HOST']));
    $baseUrl = esc_url($scheme . "://" . $host);

    $orderData['websiteURL'] = $baseUrl;

    // Set up the request to send JSON data
    $response = wp_remote_post(esc_url($urlbase . 'api/ext_wp/push_data'), array(
        'method'    => 'POST',
        'body'      => wp_json_encode($orderData, JSON_UNESCAPED_SLASHES),
        'headers'   => array('Content-Type' => 'application/json'), // Specify content type as JSON
        'data_format' => 'body',
        'timeout' => 45
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        return "Something went wrong: $error_message";
    }

    return true;
}
/*********************************** RABBITMQ ****************************************/


/******************************** API WORDPRESS *************************************/
add_action('rest_api_init', function () {
    // on doit appeler : /wp-json/api/inkreez/order/{order_id}
    register_rest_route('api/inkreez', '/order/(?P<order_id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'inkreez_get_product_data',
        'permission_callback' => '__return_true',
        'args' => array(
            'order_id' => array(
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));

    // on doit appeler : /wp-json/api/inkreez/version
    register_rest_route('api/inkreez', '/version', array(
        'methods' => 'GET',
        'callback' => 'inkreez_get_plugin_version',
        'permission_callback' => '__return_true', // Allows public access, consider using permissions for sensitive data
    ));

    // on doit appeler : /wp-json/api/inkreez/command
    register_rest_route('api/inkreez', '/command', array(
        'methods' => 'GET',
        'callback' => 'inkreez_get_all_order_ids',
        'permission_callback' => '__return_true', // Allows public access, consider using permissions for sensitive data
    ));

    // on doit appeler : /wp-json/api/inkreez/refresh-sequence
    register_rest_route('api/inkreez', '/refresh-sequence', array(
        'methods' => 'GET',
        'callback' => 'inkreez_refresh_sequences',
        'permission_callback' => '__return_true', // Allows public access, consider using permissions for sensitive data
    ));
});

function inkreez_get_product_data(WP_REST_Request $request)
{

    $order_id = esc_html(sanitize_text_field($request->get_param('order_id')));
    // Get the order object
    $order = wc_get_order($order_id);
    $inkreezKey = esc_html(sanitize_text_field(get_option('inkreez_key')));
    $inkreezKey = str_replace(' ', '', $inkreezKey);

    if (isset($_GET['TOKEN']) && $inkreezKey === $_GET['TOKEN']) {
        if ($order) {

            if (is_a($order, 'Automattic\WooCommerce\Admin\Overrides\OrderRefund')) {
                $parent_order_id = $order->get_parent_id();
                $order = wc_get_order($parent_order_id);
            }

            $order_info = [
                'order_info' => [
                    'id' => $order->get_id(),
                    'currency' => $order->get_currency(),
                    'payment_method' => $order->get_payment_method_title(),
                    'total_amount' => $order->get_total(),
                    'tax_amount' => $order->get_total_tax(),
                    'date_created_gmt' => $order->get_date_created()->date('c'), // ISO 8601 format
                ],
                'customer' => [
                    'id' => $order->get_customer_id(),
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                    'billing_address' => [
                        'company' => $order->get_billing_company(),
                        'address_1' => $order->get_billing_address_1(),
                        'address_2' => $order->get_billing_address_2(),
                        'city' => $order->get_billing_city(),
                        'state' => $order->get_billing_state(),
                        'postcode' => $order->get_billing_postcode(),
                        'country' => $order->get_billing_country(),
                    ],
                    'shipping_address' => [
                        'company' => $order->get_shipping_company(),
                        'address_1' => $order->get_shipping_address_1(),
                        'address_2' => $order->get_shipping_address_2(),
                        'city' => $order->get_shipping_city(),
                        'state' => $order->get_shipping_state(),
                        'postcode' => $order->get_shipping_postcode(),
                        'country' => $order->get_shipping_country(),
                    ]
                ],
                'products' => [],
            ];

            if($order_info['customer']['id'] == 0){
                $order_info['customer']['id'] = $order->get_user_id();

                if($order->get_user_id() == 0){
                    $order_info['customer']['id'] = get_post_meta($order_id, '_customer_user', true);
                }
            }


            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                if($product) {
                    $product_categories = get_the_terms($product->get_id(), 'product_cat');
                    $category_id = null;
                    $category_name = "Uncategorized";

                    if (!is_wp_error($product_categories) && !empty($product_categories)) {
                        $category_id = $product_categories[0]->term_id;
                        $category_name = $product_categories[0]->name;
                    }

                    $order_info['products'][] = [
                        'product_id' => $product->get_id(),
                        'product_name' => $product->get_name(),
                        'quantity' => $item->get_quantity(),
                        'category_id' => $category_id,
                        'category_name' => $category_name,
                        'total_price' => $item->get_total(),
                    ];
                }
            }

            $orderData['data'][] = $order_info;
        } else {
            // Handle the case where no order is found with the given $order_id
            $orderData['error'] = 'Order not found';
        }
    }else{
        $orderData['error'] = 'Token not set or equal';
    }


    return new WP_REST_Response($orderData, 200);
}

function inkreez_get_plugin_version()
{
    // Include the file that defines is_plugin_active() if it's not already available
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');

    // Check if the Inkreez plugin is active
    if (is_plugin_active('inkreez/Inkreez.php')) {
        $inkreezKey = esc_html(sanitize_text_field(get_option('inkreez_key')));
        $inkreezKey = str_replace(' ', '', $inkreezKey);

        if (isset($_GET['TOKEN']) && $inkreezKey === $_GET['TOKEN']) {
            // Get plugin data
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/inkreez/Inkreez.php');
            $plugin_version = $plugin_data['Version'];

            $version_info = [
                'version' => $plugin_version,
            ];

            return new WP_REST_Response($version_info, 200);
        } else {
            // TOKEN FAUX
            $version_info = [
                'error' => 'TOKEN_INCORRECT_OR_MISSING',
            ];

            return new WP_REST_Response($version_info, 404);
        }
    } else {
        // Plugin is not active
        $version_info = [
            'error' => 'Plugin is not active',
        ];

        return new WP_REST_Response($version_info, 404);
    }
}

function inkreez_get_sequence_inbound()
{
    $token = esc_html(sanitize_text_field(get_option('inkreez_key')));

    if($token){
        $token = str_replace(' ', '', $token);

        $apiURL = esc_url(inkreez_getInkreezRestUrl() . "api/sequence/inbound?TOKEN=$token");

        $response = wp_remote_get($apiURL);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            exit;
        }

        $response = json_decode($response['body'], true);

        if ($response["code"] !== "NO_USER_FOUND") {
            return $response["data"];
        }else{
            return false;
        }
    }else{
        return false;
    }
}

function inkreez_refresh_sequences()
{
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');

    $inkreezKey = esc_html(sanitize_text_field(get_option('inkreez_key')));
    $inkreezKey = str_replace(' ', '', $inkreezKey);

    if (isset($_GET['TOKEN']) && $inkreezKey === $_GET['TOKEN']) {

        delete_option('inkreez_sequences');
        add_option('inkreez_sequences', inkreez_get_sequence_inbound());

        return new WP_REST_Response(["code" => "SEQ_REFRESH_OK"], 200);
    } else {

        return new WP_REST_Response(["code" => "TOKEN_INCORRECT_OR_MISSING"], 200);
    }

}

function inkreez_get_all_order_ids(){
    global $wpdb;
    $inkreezKey = esc_html(sanitize_text_field(get_option('inkreez_key')));
    $inkreezKey = str_replace(' ', '', $inkreezKey);

    if (isset($_GET['TOKEN']) && $inkreezKey === $_GET['TOKEN']) {

        if(isset($_GET['pushallcommand']) && $_GET['pushallcommand'] == 1){
            $args = array(
                'limit' => -1,
                'return' => 'ids',
            );
            $order_ids = wc_get_orders($args);

            if(count($order_ids) === 0){
                $orderData['data'] = [0];
            }else{
                $orderData['data'] = $order_ids;
            }
        }else{
            return new WP_REST_Response(["error" => 'Missing parameter'], 200);
        }

        return new WP_REST_Response(["order_ids" => $orderData], 200);
    }else{
        return new WP_REST_Response(["error" => 'Token not set or equal'], 200);
    }
}
/******************************** API WORDPRESS *************************************/


/******************************* WOOCOMMERCE HOOK ***********************************/
add_action('woocommerce_thankyou', 'inkreez_send_order_id_to_api', 10, 1);
function inkreez_send_order_id_to_api($order_id)
{
    if (!$order_id) {
        return;
    }

    $urlbase = esc_url(inkreez_getInkreezRestUrl());
    $inkreezKey = esc_html(sanitize_text_field(get_option('inkreez_key')));
    $inkreezKey = str_replace(' ', '', $inkreezKey);

    $orderData['data'] = array($order_id);
    $orderData['inkreezKey'] = $inkreezKey;

    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = esc_html(sanitize_text_field($_SERVER['HTTP_HOST']));
    $baseUrl = esc_url($scheme . "://" . $host);
    $orderData['websiteURL'] = $baseUrl;

    $data_json = wp_json_encode($orderData, JSON_UNESCAPED_SLASHES);

    $response = wp_remote_post(esc_url($urlbase . 'api/ext_wp/push_data'), array(
        'method'    => 'POST',
        'body'      => $data_json,
        'headers'   => array(
            'Content-Type' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        return "Something went wrong: $error_message";
    }
}
/******************************* WOOCOMMERCE HOOK ***********************************/


/******************************* GTM ***********************************/
// Custom function for allowed tags for GTM esc
function inkreez_allowed_gtm_tags() {
    return array(
        'script' => array(
            'type' => array(),
            'src' => array(),
            'async' => array(),
            'defer' => array()
        ),
        'iframe' => array(
            'src' => array(),
            'height' => array(),
            'width' => array(),
            'style' => array(),
            'frameborder' => array(),
            'allow' => array(),
            'allowfullscreen' => array(),
        ),
        'noscript' => array(),
    );
}

// Function to generate programmaticaly the noscript for GTM
function inkreez_generate_gtm_head($gtm_code_id){
    $gtm_code_head = "<!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','".esc_html(sanitize_text_field($gtm_code_id))."');</script>
    <!-- End Google Tag Manager -->";

    return $gtm_code_head;
}

// Function to generate programmaticaly the JS for GTM
function inkreez_generate_gtm_body($gtm_code_id){
    $gtm_code_body = "<!-- Google Tag Manager (noscript) -->
    <noscript><iframe src='https://www.googletagmanager.com/ns.html?id=".esc_html(sanitize_text_field($gtm_code_id))."' 
    height='0' width='0' style='display:none;visibility:hidden'></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->";

    return $gtm_code_body;
}

// Function to output Google Tag Manager code in the <head> section
function inkreez_insert_gtm_code_head()
{
    $gtm_code_id = esc_html(sanitize_text_field(get_option('inkreez_gtm_code_id', '')));
    $gtm_code_head = inkreez_generate_gtm_head($gtm_code_id);
    echo wp_kses(wp_unslash($gtm_code_head), inkreez_allowed_gtm_tags());
}
add_action('wp_head', 'inkreez_insert_gtm_code_head');

// Function to output Google Tag Manager code immediately after the opening <body> tag
function inkreez_insert_gtm_code_body()
{
    $gtm_code_id = esc_html(sanitize_text_field(get_option('inkreez_gtm_code_id', '')));
    $gtm_code_body = inkreez_generate_gtm_body($gtm_code_id);
    echo wp_kses(wp_unslash($gtm_code_body), inkreez_allowed_gtm_tags());
}
add_action('wp_body_open', 'inkreez_insert_gtm_code_body');
/******************************* GTM ***********************************/


/******************************* DEACTIVATION HOOK ***********************************/
register_deactivation_hook(__FILE__, 'inkreez_plugin_deactivate');
function inkreez_plugin_deactivate() {

    $base_url = inkreez_getInkreezRestUrl() . "api/deactivation";
    $inkreezKey = esc_html(sanitize_text_field(get_option('inkreez_key')));
    $inkreezKey = str_replace(' ', '', $inkreezKey);
    $utc_datetime = gmdate('Y-m-d H:i:s', current_time('timestamp', 1));
    $query_args = array(
        'TOKEN' => $inkreezKey,
        'DATE' => $utc_datetime
    );
    $apiURL = esc_url_raw(add_query_arg($query_args, $base_url));
    $response = wp_remote_get($apiURL);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
    } else {
        // Optionally process the response further
        $body = wp_remote_retrieve_body($response);
    }
}
/******************************* DEACTIVATION HOOK ***********************************/


/******************************* CONTACT FORM 7 ***********************************/
add_filter('wpcf7_editor_panels', 'inkreez_cf7_editor_tab');
function inkreez_cf7_editor_tab($panels) {
    $panels['custom-tab'] = array(
        'title' => __('Inkreez', 'inkreez'),
        'callback' => 'inkreez_cf7_editor_tab_content'
    );
    return $panels;
}
function inkreez_cf7_editor_tab_content($post) {
    $form_id = $post->id();  // Assuming $post is the CF7 form object.

    // Retrieve saved settings using get_post_meta
    $saved_settings = get_option('inkreez_cf7_' . $form_id);
    $saved_checkbox = isset($saved_settings['inkreez_checkbox']) ? $saved_settings['inkreez_checkbox'] : 0;
    $saved_select = isset($saved_settings['inkreez_select']) ? $saved_settings['inkreez_select'] : '';
    $saved_cf7email = isset($saved_settings['inkreez_cf7_email']) ? $saved_settings['inkreez_cf7_email'] : '';

    $sequences = get_option('inkreez_sequences');
    $Tab = [];
    foreach ($sequences as $S) {
        $Tab[$S['id']] = $S['NomCible'];
    }

    // Security nonce field
    wp_nonce_field('custom_cf7_settings', 'custom_cf7_settings_nonce');

    ?>
    <div id="custom-settings-panel">
        <h2>Inkreez Séquences</h2>
        <p>Vous pouvez ici associer une de vos séquences Inkreez à un formulaire.</p>
        <p>
            <label>
                <input type="checkbox" name="inkreez_checkbox" value="1" <?php echo ($saved_checkbox ? 'checked' : ''); ?>>
                Cocher cette case pour associer le formulaire à une séquence Inkreez
            </label>
        </p>
        <p>
            <label for="inkreez_cf7_email">Nom du champ email</label>
            <input type="text" name="inkreez_cf7_email" value="<?php echo ($saved_cf7email ? esc_html(sanitize_text_field($saved_cf7email)) : ''); ?>">
        </p>
        <p>
            <label for="inkreez_select">Séquence Inkreez</label>
            <select name="inkreez_select" id="inkreez_select">
                <?php
                foreach ($Tab as $id => $seqName) {
                    $selected = ($id == $saved_select) ? 'selected' : '';
                    echo "<option value='".esc_html(sanitize_text_field($id))."' ".esc_html(sanitize_text_field($selected)).">".esc_html(sanitize_text_field($seqName))."</option>";
                }
                ?>
            </select>
        </p>
    </div>
    <?php
}

add_action('wpcf7_save_contact_form', 'inkreez_save_custom_cf7_settings');
function inkreez_save_custom_cf7_settings($contact_form) {
    $form_id = $contact_form->id();

    // Check if our nonce is set and verify it.
    if ( !isset($_POST['custom_cf7_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['custom_cf7_settings_nonce'])), 'custom_cf7_settings') ) {
        return;
    }

    $inkreez_checkbox = isset($_POST['inkreez_checkbox']) ? 1 : 0;
    $inkreez_select = isset($_POST['inkreez_select']) ? esc_html(sanitize_text_field($_POST['inkreez_select'])) : '';
    $inkreez_cf7_email = isset($_POST['inkreez_cf7_email']) ? esc_html(sanitize_text_field($_POST['inkreez_cf7_email'])) : '';

    $settings_array = array(
        'inkreez_checkbox' => $inkreez_checkbox,
        'inkreez_select' => $inkreez_select,
        'inkreez_cf7_email' => $inkreez_cf7_email
    );

    update_option('inkreez_cf7_' . $form_id, $settings_array);
}

add_action('wpcf7_before_send_mail', 'inkreez_handle_form_submission_cf7');
function inkreez_handle_form_submission_cf7($contact_form) {
    $submission = WPCF7_Submission::get_instance();

    if ($submission) {
        $posted_data = $submission->get_posted_data();

        $form_id = $contact_form->id();

        $settings = get_option('inkreez_cf7_' . $form_id);

        if (!$settings) {
            return;
        }

        if (!empty($settings['inkreez_checkbox']) && $settings['inkreez_checkbox'] == 1) {

            $inkreezSequenceId = $settings['inkreez_select'];
            $emailName = $settings['inkreez_cf7_email'];

            $emailUser = isset($posted_data[$emailName]) ? $posted_data[$emailName] : '';

            $TabValue = [];

            foreach ($posted_data as $key => $value){

                if($key !== $emailName){
                    $TabValue[]= $key.'='.$value;
                }
            }

            inkreez_AddMailInkreez($emailUser,$inkreezSequenceId,$TabValue);
        }

    }
}
/******************************* CONTACT FORM 7 ***********************************/

function inkreez_admin_menu_icon_css()
{
    wp_register_style('inkreez-admin-plugin-style', esc_url(plugins_url('templates/assets/admin.css', __FILE__)), array(), '1.0.0', 'all');
    wp_enqueue_style('inkreez-admin-plugin-style');
}
add_action('admin_head', 'inkreez_admin_menu_icon_css');

// Register the activation hook.
register_activation_hook(__FILE__, 'inkreez_custom_plugin_activate');
function inkreez_custom_plugin_activate()
{
    $token = esc_html(sanitize_text_field(get_option('inkreez_key')));
    $token = str_replace(' ', '', $token);

    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = esc_html(sanitize_text_field($_SERVER['HTTP_HOST']));
    $url = esc_url($scheme . "://" . $host);

    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/inkreez/Inkreez.php');
    $version = esc_html(sanitize_text_field($plugin_data['Version']));

    $base_url = inkreez_getInkreezRestUrl() . "api/user_info";
    $query_args = array(
        'TOKEN' => $token,
        'URL' => $url,
        'TYPE' => 'WooCommerce',
        'VERSION' => $version
    );
    $apiURL = esc_url_raw(add_query_arg($query_args, $base_url));

    // Si réactivation envoyé les commandes à RabbitMQ
    $response = wp_remote_get($apiURL);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        exit;
    }
    $response = json_decode($response['body'], true);

    if ($response['code'] === "WAS_DEACTIVATED" ) {
        $apiURL = esc_url_raw(inkreez_getInkreezRestUrl() . "api/deactivation/date?TOKEN=$token");

        $response = wp_remote_get($apiURL);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log($error_message);
            exit;
        }


        $response = json_decode($response['body'], true);
        inkreez_send_orders_to_api($response["data"]);
    }

    // Set a transient to mark that the plugin has just been activated.
    set_transient('inkreez_custom_plugin_redirect', 1, 30);
}

// Hook into admin_init.
add_action('admin_init', 'inkreez_custom_plugin_redirect');
function inkreez_custom_plugin_redirect()
{
    // Check if the transient is set, and if so, delete it and redirect.
    if (get_transient('inkreez_custom_plugin_redirect')) {
        delete_transient('inkreez_custom_plugin_redirect');

        // Ensure this is only done on plugin activation and the user has the capability to manage options.
        if (!isset($_GET['activate-multi']) && current_user_can('manage_options')) {
            wp_redirect(admin_url('admin.php?page=inkreez_plugin'));
            exit;
        }
    }
}


/**************************** INSTANCIER LE PLUGIN *********************************/
InkreezPlugin::getInstance()->register();
