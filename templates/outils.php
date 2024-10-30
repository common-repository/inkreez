<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

session_start();
?>

<div class="wrap">

    <div class="bandeau d-flex-center">
        <img class="logo" src="<?php echo esc_url(plugins_url('/assets/logo@2x.png', __FILE__)) ?>">
        <h2 class="white-text">Dashboard</h2>
    </div>

    <section class="container-inkreez">

        <?php if(isset($_SESSION['inkreez']) && !empty($_SESSION['inkreez'])) : ?>
            <div class="notice-inkreez notice-<?php echo esc_html(sanitize_text_field($_SESSION['inkreez']['type'])) ?>">
                <?php echo esc_html(sanitize_text_field($_SESSION['inkreez']['message'])) ?>
            </div>
            <?php unset($_SESSION['inkreez']); ?>
        <?php endif; ?>

        <div class="wrap wrap-inkreez">
            <h2>Inkreez : Google Tag Manager</h2>
            <form method="post">
                <?php settings_fields('gtm_settings_group'); ?>
                <?php wp_nonce_field('gtm_settings_nonce', '_wpnonce'); ?>
                <label for="gtm-code-head">Google Tag Manager ID</label><br>

                <input type="text" name="gtm-code-id" id="gtm-code-id" placeholder="GTM-XXXXXXXX" value="<?php echo esc_html(sanitize_text_field($gtm_code_id)) ?>" style="width: 150px">
                <br><br>

                <input type="submit" name="submit" value="ENREGISTRER" class="btn-submit">
            </form>
        </div>
    </section>

</div>
