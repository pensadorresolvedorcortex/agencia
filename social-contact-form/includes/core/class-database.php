<?php
/**
 * Database Class for FormyChat.
 *
 * @package FormyChat
 * @since 3.0.0
 */


// Namespace.
namespace FormyChat;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Database Class.
 *
 * @package FormyChat
 * @since 1.0.0
 */
class Database extends \FormyChat\Base {

    /**
     * Actions.
     */
    public function actions() {
        add_action( 'init', array( $this, 'create_tables' ), 0 );
    }

    /**
     * Create tables.
     */
    public function create_tables() {

        // Create SCF Table.
        $this->create_scf_table();

        // Create Widget Table.
        $this->create_widget_table();

        // Fix for old version.
        $this->migrate_to_multiwidgets();
    }

    /**
     * SCF Table.
     */
    public function create_scf_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}scf_leads` (
            `id` mediumint(30) NOT NULL AUTO_INCREMENT,
            `field` text NOT NULL,
            `meta` text NOT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
            `deleted_at` timestamp DEFAULT NULL,
            `note` text NOT NULL,
            PRIMARY KEY (`id`)
        ) $charset_collate;" ); // phpcs:ignore 

        // Alter SCF Table, add form (string) and form_id (int) columns if not exists.
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}scf_leads LIKE 'form'"); // db call ok; no-cache ok.
        if ( empty($column_exists) ) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}scf_leads ADD COLUMN form text NULL DEFAULT NULL"); // db call ok; no-cache ok.
        }

        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}scf_leads LIKE 'form_id'"); // db call ok; no-cache ok.
        if ( empty($column_exists) ) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}scf_leads ADD COLUMN form_id mediumint NULL DEFAULT NULL"); // db call ok; no-cache ok.
        }

        // Alter SCF Table, add widget_id (int) column if not exists.
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}scf_leads LIKE 'widget_id'"); // db call ok; no-cache ok.
        if ( empty($column_exists) ) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}scf_leads ADD COLUMN widget_id mediumint NULL DEFAULT 1"); // db call ok; no-cache ok.
        }
    }


    /**
     * Widget Table.
     */
    public function create_widget_table() {

        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        if ( get_option('scf_widget_table_created') ) {
            // Drop old table.
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}scf_widgets"); // db call ok; no-cache ok.

            delete_option('scf_widget_table_created');
        }

        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}scf_widgets(
            `id` mediumint(30) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) DEFAULT NULL,
            `is_active` tinyint(1) DEFAULT 1,
            `config` long NULL DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL DEFAULT NULL,
            `deleted_at` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;" ); // phpcs:ignore
    }

    /**
     * Fix for old version.
     */
    public function migrate_to_multiwidgets() {

        // Bail if new widget is already created.
        if ( get_option('formychat_has_first_widget') ) {
            return;
        }

        // WhatsApp.
        $whatsapp = get_option('scf_whatsapp', '');

        // Bail if not set.
        if ( empty($whatsapp) ) {

            // Update old version.
            update_option('formychat_has_first_widget', true);
            return;
        }

        // Default config.
        $config = \FormyChat\App::widget_config();

        // Others
        $icon = get_option('scf_icon', '');
        $cta = get_option('scf_call_to_action', '');
        $form = get_option('scf_form', '');
        $cf7 = get_option('scf_contact_form_7', '');
        $greetings = get_option('scf_greetings', '');
        $target = get_option('scf_target', '');

        // Old to new keys mapping.
        $old_keys = [
            'whatsapp' => [
                'direct_web' => 'web_version',
                'phone_code' => 'country_code',
                'phone_number' => 'number',
            ],
            'icon' => [
                'delay' => 'delay',
                'image' => 'image_url',
                'size' => 'size',
                'size_custom' => 'size_custom',
                'position' => 'position',
            ],
            'cta' => [
                'text' => 'text',
                'size' => 'size',
                'size_custom' => 'size_custom',
                'color' => 'color',
                'background' => 'background_color',
            ],
            'form' => [
                'title' => 'title',
                'header' => 'subtitle',
                'footer' => 'footer',
                'submit' => 'submit',
                'allow_country_selection' => 'show_country_code_field',
                'phone_code' => 'country_code',
                'size' => 'size',
                'size_custom' => 'size_custom',
                'font' => 'font_family',
                'color' => 'text_color',
                'background' => 'background_color',
                'open_by_default' => 'open_by_default',
                'close_on_submit' => 'close_on_submit',
            ],
            'cf7' => [
                'selectedid' => 'form_id',
                'confirmationstateofCustommail' => 'configured',
            ],
            'target' => [
                'excludes' => 'exclude_pages',
                'exclude_all' => 'exclude_all_pages',
                'excludes_all_except' => 'exclude_all_pages_except',
            ],
            'greetings' => [
                'enabled' => 'enabled',
                'template' => 'template',
            ],
        ];

        try {
            foreach ( $old_keys as $key => $values ) {
                foreach ( $values as $old_key => $new_key ) {
                    if ( isset( ${$key}[ $old_key ] ) ) {
                        $config[ $key ][ $new_key ] = ${$key}[ $old_key ];
                    }
                }
            }
        } catch ( \Exception $e ) { // phpcs:ignore
            // Do nothing.
        }

        // Adjustments.
        $duplicate = \FormyChat\App::widget_config();

        // Format WhatsApp message template.
        $config['whatsapp']['message_template'] = isset( $whatsapp['defined_preset'] ) && ! empty( $whatsapp['defined_preset'] ) ? str_replace('{break}', "\n", $whatsapp['defined_preset']) : $duplicate['whatsapp']['message_template'];

        // Add new_tab key to whatsapp.
        if ( isset( $form['open_in_new_tab'] ) ) {
            $config['whatsapp']['new_tab'] = $form['open_in_new_tab'];
        }

        // Add hadDelay key to icon.
        if ( isset($config['icon']['delay']) && $config['icon']['delay'] > 0 ) {
            $config['icon']['has_delay'] = true;
            $config['icon']['delay'] = intval( $config['icon']['delay'] );
        }

        // Icon position custom.
        $config['icon']['position_custom'] = [
            'top' => isset( $icon['position']['top'] ) ? $icon['position']['top'] : null,
            'right' => isset( $icon['position']['right'] ) ? $icon['position']['right'] : null,
            'bottom' => isset( $icon['position']['bottom'] ) ? $icon['position']['bottom'] : null,
            'left' => isset( $icon['position']['left'] ) ? $icon['position']['left'] : null,
        ];

        // Add cta enabled.
        $config['cta']['enabled'] = ! empty($cta['text']);

        // CF7 Form ID boolean.
        $config['cf7']['form_id'] = intval( $config['cf7']['form_id'] );

        // form.open_by_default.
        $config['form']['open_by_default'] = isset( $config['form']['open_by_default'] ) ? wp_validate_boolean( $config['form']['open_by_default'] ) : false;

        // form.show_country_code_field
        $config['form']['show_country_code_field'] = isset( $config['form']['show_country_code_field'] ) ? wp_validate_boolean( $config['form']['show_country_code_field'] ) : false;

        // Form mode.
        $config['form']['mode'] = isset( $cf7['selectedmode'] ) && 'cf7' === $cf7['selectedmode'] ? 'cf7' : 'formychat';

        // Email.
        $config['email'] = [
            'enabled' => isset( $cf7['activemail'] ) ? wp_validate_boolean( $cf7['activemail'] ) : false,
            'address' => isset( $cf7['cf7mail'] ) ? $cf7['cf7mail'] : '',
            'admin_email' => wp_validate_boolean( $cf7['confirmationstateofCustommail'] ),
        ];

        // Add default images.
        $config['icon']['image_url'] = isset( $config['icon']['image_url'] ) && ! empty( $config['icon']['image_url'] ) ? $config['icon']['image_url'] : FORMYCHAT_PUBLIC . '/images/whatsapp.svg';

        // If greetings is enabled.
        $config['greetings']['enabled'] = isset( $greetings['enabled'] ) ? wp_validate_boolean( $greetings['enabled'] ) : false;

        // Greetings on click.
        $config['greetings']['on_click'] = isset( $greetings['on_click'] ) && 'load_form' === $greetings['on_click'] ? 'show_form' : 'redirect';

        // Template style will be +1.
        $config['greetings']['style'] = isset( $greetings['template_style'] ) ? intval( $greetings['template_style'] ) + 1 : 1;

        // Adjust simple.
        $config['greetings']['templates']['simple']['background_color'] = isset( $config['greetings']['templates']['simple']['background'] ) && ! empty( $config['greetings']['templates']['simple']['cta_text_color'] ) ? $config['greetings']['templates']['wave']['background'] : '#FFFFFF';

        // Adjust wave.
        $wave_keys = [
            'content_icon' => 'icon_url',
            'content_position' => 'icon_position',
            'heading' => 'heading',
            'heading_size' => 'heading_size',
            'heading_size_custom' => 'heading_size_custom',
            'message' => 'message',
            'message_size' => 'message_size',
            'message_size_custom' => 'message_size_custom',
            'heading_color' => 'heading_color',
            'message_color' => 'message_color',
            'background' => 'background_color',
            'font_family' => 'font_family',
            'cta_text' => 'cta_text',
            'cta_heading' => 'cta_heading',
            'cta_message' => 'cta_message',
            'cta_icon' => 'cta_icon_url',
            'cta_color' => 'cta_text_color',
            'cta_background' => 'cta_background_color',
            'cta_heading_color' => 'cta_heading_color',
            'cta_message_color' => 'cta_message_color',
            'cta_heading_size' => 'cta_heading_size',
            'cta_message_size' => 'cta_message_size',
        ];

        foreach ( $wave_keys as $old_key => $new_key ) {
            $config['greetings']['templates']['wave'][ $new_key ] = isset( $config['greetings']['templates']['wave'][ $old_key ] ) && ! empty( $config['greetings']['templates']['wave'][ $old_key ] ) ? $config['greetings']['templates']['wave'][ $old_key ] : $duplicate['greetings']['templates']['wave'][ $new_key ];
        }

        // Boolean to show_icon and show_cta.
        $config['greetings']['templates']['wave']['show_icon'] = wp_validate_boolean( $greetings['templates']['wave']['show_content'] );
        $config['greetings']['templates']['wave']['show_cta'] = wp_validate_boolean( $greetings['templates']['wave']['show_cta'] );

        $simple_keys = [
            'heading' => 'heading',
            'heading_size' => 'heading_size',
            'heading_size_custom' => 'heading_size_custom',
            'message' => 'message',
            'message_size' => 'message_size',
            'message_size_custom' => 'message_size_custom',
            'heading_color' => 'heading_color',
            'message_color' => 'message_color',
            'background' => 'background_color',
            'font_family' => 'font_family',
        ];

        foreach ( $simple_keys as $old_key => $new_key ) {
            $config['greetings']['templates']['simple'][ $new_key ] = isset( $config['greetings']['templates']['simple'][ $old_key ] ) && ! empty( $config['greetings']['templates']['simple'][ $old_key ] ) ? $config['greetings']['templates']['simple'][ $old_key ] : $duplicate['greetings']['templates']['simple'][ $new_key ];
        }

        // Exclude all filter to boolean.
        $config['target']['exclude_all_pages'] = isset( $config['target']['exclude_all_pages'] ) ? wp_validate_boolean( $config['target']['exclude_all_pages'] ) : false;

        // Default fonts are set to sans-serif.
        $config['form']['font_family'] = 'default' === $config['form']['font_family'] ? 'sans-serif' : $config['form']['font_family'];
        $config['greetings']['templates']['wave']['font_family'] = 'default' === $config['greetings']['templates']['wave']['font_family'] ? 'sans-serif' : $config['greetings']['templates']['wave']['font_family'];
        $config['greetings']['templates']['simple']['font_family'] = 'default' === $config['greetings']['templates']['simple']['font_family'] ? 'sans-serif' : $config['greetings']['templates']['simple']['font_family'];

        $payload = [
            'name' => __( 'My First Widget', 'formychat' ),
            'is_active' => wp_validate_boolean(  get_option('scf_enabled', 0) ),
            'config' => $config,
        ];

        try {
            \FormyChat\Models\Widget::create($payload);

            // Update old version.
            update_option('formychat_has_first_widget', true);

            // Update old version.
        } catch ( \Exception $e ) { // phpcs:ignore
            // Do nothing.
        }
    }
}

Database::init();
