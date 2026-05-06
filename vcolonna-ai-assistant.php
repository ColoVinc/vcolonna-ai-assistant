<?php
/**
 * Plugin Name: VColonna AI Assistant
 * Plugin URI:  https://github.com/ColoVinc/vcolonna-ai-assistant
 * Description: AI Assistant — Agentic chat, content generation, ACF/CPT support with Gemini, OpenAI, Claude and Groq.
 * Version:     0.1.0
 * Author:      Vincenzo Colonna
 * Author URI:  https://github.com/ColoVinc
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vcolonna-ai-assistant
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Sicurezza: blocca accesso diretto al file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Costanti del plugin
define( 'VCAI_VERSION', '0.4.0' );
define( 'VCAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VCAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VCAI_PLUGIN_FILE', __FILE__ );

// Autoload delle classi
spl_autoload_register( function( $class ) {
    $prefix = 'Vcai_';
    if ( strpos( $class, $prefix ) !== 0 ) return;

    $map = [
        'Vcai_Core'          => VCAI_PLUGIN_DIR . 'includes/class-core.php',
        'Vcai_API_Connector' => VCAI_PLUGIN_DIR . 'includes/class-api-connector.php',
        'Vcai_Gemini'        => VCAI_PLUGIN_DIR . 'includes/connectors/class-gemini.php',
        'Vcai_OpenAI'        => VCAI_PLUGIN_DIR . 'includes/connectors/class-openai.php',
        'Vcai_Claude'        => VCAI_PLUGIN_DIR . 'includes/connectors/class-claude.php',
        'Vcai_Groq'          => VCAI_PLUGIN_DIR . 'includes/connectors/class-groq.php',
        'Vcai_Logger'        => VCAI_PLUGIN_DIR . 'includes/class-logger.php',
        'Vcai_History'       => VCAI_PLUGIN_DIR . 'includes/class-history.php',
        'Vcai_Admin'         => VCAI_PLUGIN_DIR . 'admin/class-admin.php',
        'Vcai_Metabox'       => VCAI_PLUGIN_DIR . 'admin/class-metabox.php',
        'Vcai_Chat'          => VCAI_PLUGIN_DIR . 'admin/class-chat.php',
        'Vcai_Tools'         => VCAI_PLUGIN_DIR . 'includes/class-tools.php',
        'Vcai_Knowledge'     => VCAI_PLUGIN_DIR . 'includes/class-knowledge.php',
    ];

    if ( isset( $map[$class] ) && file_exists( $map[$class] ) ) {
        require_once $map[$class];
    }
});

// Avvio del plugin
function vcai_init() {
    Vcai_Core::get_instance();
}
add_action( 'plugins_loaded', 'vcai_init' );

// Hook attivazione / disattivazione
register_activation_hook( __FILE__, 'vcai_activate' );
register_deactivation_hook( __FILE__, 'vcai_deactivate' );

function vcai_activate() {
    set_transient( 'vcai_activated', true, 60 );
    vcai_create_tables();
    add_option( 'vcai_version', VCAI_VERSION );

    if ( ! wp_next_scheduled( 'vcai_daily_cleanup' ) ) {
        wp_schedule_event( time(), 'daily', 'vcai_daily_cleanup' );
    }
}

// Avviato a plugins_loaded per aggiornamenti DB
function vcai_check_version() {
    $installed = get_option( 'vcai_version', '0' );
    if ( version_compare( $installed, VCAI_VERSION, '<' ) ) {
        vcai_create_tables();
        update_option( 'vcai_version', VCAI_VERSION );
    }
}
add_action( 'plugins_loaded', 'vcai_check_version', 5 );

function vcai_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Tabella log
    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}vcai_logs (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        provider VARCHAR(50) NOT NULL,
        prompt_tokens INT NOT NULL DEFAULT 0,
        completion_tokens INT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'success',
        error_message TEXT NULL,
        PRIMARY KEY (id)
    ) $charset;" );

    // Tabella conversazioni
    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}vcai_conversations (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        title VARCHAR(100) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset;" );

    // Tabella messaggi
    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}vcai_messages (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        conversation_id BIGINT(20) UNSIGNED NOT NULL,
        role VARCHAR(10) NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY conversation_id (conversation_id)
    ) $charset;" );

    // Tabella knowledge base
    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}vcai_knowledge (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        doc_name VARCHAR(255) NOT NULL,
        chunk_index INT NOT NULL DEFAULT 0,
        content TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY doc_name (doc_name),
        FULLTEXT KEY content_ft (content)
    ) $charset;" );

}

function vcai_deactivate() {
    wp_clear_scheduled_hook( 'vcai_daily_cleanup' );
}
