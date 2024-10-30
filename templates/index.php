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



        <article class="d-flex-center">
            <div class="smaller-container" <?php if($isAssociated !== "") : ?>style="display:none;"<?php endif; ?>>
                <strong class="form-title">Vous n'avez pas de compte ?</strong><br>

                <div class="form-body" style="margin-top: 25px;">
                    <a class="btn-submit" href="<?php echo esc_url(inkreez_getInkreezRestUrl() . 'register') ?>" target="_blank">Créer votre compte</a>
                </div>
            </div>


            <div class="smaller-container <?php if($isAssociated !== "") : ?>d-super-flex<?php endif; ?>">
                <form action="<?php echo esc_url(get_admin_url()) . 'admin-post.php' ?>" method="post">
                    <?php settings_fields('token_settings_group'); ?>
                    <?php wp_nonce_field('token_settings_nonce', '_wpnonce'); ?>
                    <input type='hidden' name='action' value='submit-form-inkreez-token'/>
                    <div>
                        <div class="form-body">
                            <?php if($isAssociated !== "") : ?>
                                <strong class="form-title"><?php echo esc_html(sanitize_text_field($isAssociated)) ?></strong><br>
                            <?php else :  ?>
                                <strong class="form-title">Vous avez un compte ?</strong><br>
                                <div style="display: inline-block; margin-top: 10px;">
                                    <input id="inkreezkey-input" type="text" name="inkreezkey-input" size="40"
                                           value="<?php echo esc_html(sanitize_text_field(get_option('inkreez_key'))) ?>" placeholder="Token Inkreez" class="token-inkreez">
                                    <input id="inkreezkey-submit" type="submit" name="inkreezkey-submit" value="Enregistrer" class="btn-submit">
                                </div>
                            <?php endif; ?>



                        </div>
                    </div>
                </form>
            </div>
        </article>

        <?php if(get_option('inkreez_sequences') !== false && is_array(get_option('inkreez_sequences'))): ?>
            <?php if(count(get_option('inkreez_sequences')) !== 0): ?>
                <div class="wrap-inkreez sequences">
                    <h3>Vos séquences</h3>
                    <?php foreach(get_option('inkreez_sequences') as $sequence) :?>
                        <div class="sequence">
                            <p><strong><?php echo esc_html(sanitize_text_field($sequence['NomCible'])) ?></strong></p>
                            <small><?php echo esc_html(sanitize_text_field($sequence['DescriptionCreation'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </section>


</div>