<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function inkreez_getInkreezRestUrl() {
    $urlbase = 'https://www.inkreez.com/';
    return $urlbase;
}

function inkreez_get_urlInkreez($url, $fields)
{
    $args = array(
        'headers' => array(
            'Content-Type' => 'application/json; charset=UTF-8'
        ),
        'body' => wp_json_encode($fields)
    );

    $response = wp_remote_post($url, $args);
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        exit;
    }
    return wp_remote_retrieve_body($response);
}

function inkreez_AddMailInkreez($mail,$sequence,$TabValue) {
    $urlbase = esc_url(inkreez_getInkreezRestUrl());
    $token = esc_html(sanitize_text_field(get_option('inkreez_key')));

    $PR = isset($_COOKIE['PR']) ? sanitize_text_field(wp_unslash($_COOKIE['PR'])) : '';
    $mail = sanitize_email($mail);
    $sequence = sanitize_text_field($sequence);

    $url = $urlbase.'api/add-mail-sequence?token='.$token.'&sequence='.$sequence.'&mail='.$mail.'&PR='.urlencode($PR).'&Val='.urlencode(serialize($TabValue));

    $R = inkreez_get_urlInkreez($url,[]);
    $Decode = json_decode($R);

    $resultat = $Decode->code;

    if (json_last_error() !== JSON_ERROR_NONE) {
        exit('Failed to decode JSON.');
    }

    if ($resultat != '200') {
        echo "Erreur inexplicable lors de l'ajout du mail.";
        exit;
    }
}
