<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}


/**
 * Elementor form Inkreez action.
 *
 * Custom Elementor form action which adds new subscriber to Inkreez Sequence after form submission.
 *
 * @since 1.0.0
 */
class Inkreez_Add_Sequence_Action_After_Submit extends \ElementorPro\Modules\Forms\Classes\Action_Base {

    /**
     * Get action name.
     *
     * Retrieve Inkreez action name.
     *
     * @since 1.0.0
     * @access public
     * @return string
     */
    public function get_name() {
        return 'Inkreez Ajouter à la Séquence';
    }

    /**
     * Get action label.
     *
     * Retrieve Inkreez action label.
     *
     * @since 1.0.0
     * @access public
     * @return string
     */
    public function get_label() {
        return esc_html__( 'Inkreez : Ajouter à la séquence', 'inkreez' );
    }

    /**
     * Register action controls.
     *
     * Add input fields to allow the user to customize the action settings.
     *
     * @since 1.0.0
     * @access public
     * @param \Elementor\Widget_Base $widget
     */
    public function register_settings_section( $widget ) {
        $widget->start_controls_section(
            'inkreez_name',
            [
                'label' => esc_html__('Inkreez - Séquence', 'inkreez'),
                'condition' => [
                    'submit_actions' => $this->get_name(),
                ],
            ]
        );

        $widget->add_control(
            'inkreez_description',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => esc_html__('Plug-in pour envoyer les données du formulaire directement dans une séquence Inkreez. Vous devez nommer votre champ "mail" ou "email".', 'inkreez'),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            ]
        );

        $sequences = get_option('inkreez_sequences');
        $Tab = [];
        foreach ($sequences as $S) {
            $Tab[$S['id']]=$S['NomCible'];
        }

        $widget->add_control(
            'inkreez_listesequence',
            [
                'label' => esc_html__( 'Ajouter à la séquence', 'inkreez' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options'=>$Tab
            ]
        );


        $widget->end_controls_section();
    }

    /**
     * Run action.
     *
     * Runs the Inkreez action after form submission.
     *
     * @since 1.0.0
     * @access public
     * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record
     * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
     */
    public function run($record, $ajax_handler) {
        $settings = $record->get('form_settings');
        $raw_fields = $record->get('fields');

        $sequenceValue = $settings['inkreez_listesequence'];

        $TabValue=array();
        $fields = [];
        $mail='';
        foreach ($raw_fields as $id => $field) {
            if(substr($id, 0, 6) !== "field_") {
                if (in_array($id,array('mail','email'))) {
                    $mail=$field['value'];
                }
                else {
                    $TabValue[]=$id.'='.$field['value'];
                }
            }
        }

        inkreez_AddMailInkreez($mail,$sequenceValue,$TabValue);
    }

    /**
     * On export.
     *
     * Clears Inkreez form settings/fields when exporting.
     *
     * @since 1.0.0
     * @access public
     * @param array $element
     */
    public function on_export( $element ) {

        return $element;

    }
}
