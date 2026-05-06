<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Classe Admin — gestisce il pannello di impostazioni nel WP Admin
 */
class Vcai_Admin {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_notices', [ $this, 'activation_notice' ] );
        add_action( 'admin_notices', [ $this, 'missing_key_notice' ] );
        add_action( 'wp_ajax_vcai_test_api', [ $this, 'ajax_test_api' ] );
        add_action( 'wp_ajax_vcai_clear_logs', [ $this, 'ajax_clear_logs' ] );
        add_action( 'wp_ajax_vcai_upload_knowledge', [ $this, 'ajax_upload_knowledge' ] );
        add_action( 'wp_ajax_vcai_delete_knowledge', [ $this, 'ajax_delete_knowledge' ] );
        add_action( 'wp_ajax_vcai_index_posts', [ $this, 'ajax_index_posts' ] );
        add_action( 'wp_ajax_vcai_generate_alt', [ $this, 'ajax_generate_alt' ] );
        add_filter( 'attachment_fields_to_edit', [ $this, 'add_alt_button_to_media' ], 10, 2 );
    }

    /**
     * Notice dopo attivazione plugin
     */
    public function activation_notice() {
        if ( ! get_transient( 'vcai_activated' ) ) return;
        delete_transient( 'vcai_activated' );
        $url = admin_url( 'admin.php?page=vcai' );
        echo '<div class="notice notice-success is-dismissible"><p><strong>🤖 ' . esc_html__( 'VColonna AI attivato!', 'vcolonna-ai-assistant' ) . '</strong> <a href="' . esc_url( $url ) . '">' . esc_html__( 'Configura la tua API key', 'vcolonna-ai-assistant' ) . '</a> ' . esc_html__( 'per iniziare.', 'vcolonna-ai-assistant' ) . '</p></div>';
    }

    /**
     * Notice se nessuna API key è configurata
     */
    public function missing_key_notice() {
        if ( get_transient( 'vcai_activated' ) ) return;
        $screen = get_current_screen();
        if ( $screen && strpos( $screen->id, 'vcai' ) !== false ) return;

        $provider = get_option( 'vcai_default_provider', 'gemini' );
        $key      = get_option( 'vcai_' . $provider . '_api_key', '' );
        if ( ! empty( $key ) ) return;

        $url = admin_url( 'admin.php?page=vcai' );
        echo '<div class="notice notice-warning is-dismissible"><p><strong>🤖 VColonna AI:</strong> ' . esc_html__( 'API key non configurata.', 'vcolonna-ai-assistant' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Vai alle impostazioni', 'vcolonna-ai-assistant' ) . '</a>.</p></div>';
    }

    /**
     * Registra le voci di menu nel WP Admin
     */
    public function register_menu() {
        add_menu_page(
            'VColonna AI',
            'VColonna AI',
            'manage_options',
            'vcai',
            [ $this, 'render_settings_page' ],
            'dashicons-superhero',
            30
        );

        add_submenu_page(
            'vcai',
            'Impostazioni',
            'Impostazioni',
            'manage_options',
            'vcai',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'vcai',
            'Log Chiamate',
            'Log Chiamate',
            'manage_options',
            'vcai-logs',
            [ $this, 'render_logs_page' ]
        );

        add_submenu_page(
            'vcai',
            'Knowledge Base',
            'Knowledge Base',
            'manage_options',
            'vcai-knowledge',
            [ $this, 'render_knowledge_page' ]
        );
    }

    /**
     * Registra le impostazioni WordPress
     */
    public function register_settings() {
        // Gruppo impostazioni API
        register_setting( 'vcai_settings', 'vcai_gemini_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting( 'vcai_settings', 'vcai_openai_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting( 'vcai_settings', 'vcai_default_provider', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'gemini',
        ]);
        register_setting( 'vcai_settings', 'vcai_gemini_model', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'gemini-2.0-flash',
        ]);
        register_setting( 'vcai_settings', 'vcai_openai_model', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'gpt-5.4-mini',
        ]);
        register_setting( 'vcai_settings', 'vcai_claude_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting( 'vcai_settings', 'vcai_claude_model', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'claude-sonnet-4-6',
        ]);
        register_setting( 'vcai_settings', 'vcai_groq_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting( 'vcai_settings', 'vcai_groq_model', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'llama-3.3-70b-versatile',
        ]);
        register_setting( 'vcai_settings', 'vcai_rate_limit', [
            'sanitize_callback' => 'absint',
            'default'           => 30,
        ]);
        register_setting( 'vcai_settings', 'vcai_auto_delete_days', [
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]);
        register_setting( 'vcai_settings', 'vcai_api_timeout', [
            'sanitize_callback' => 'absint',
            'default'           => 30,
        ]);

        // Gruppo knowledge base (settings group separato)
        register_setting( 'vcai_knowledge_settings', 'vcai_knowledge_enabled', [
            'sanitize_callback' => 'absint',
            'default'           => 1,
        ]);
        register_setting( 'vcai_knowledge_settings', 'vcai_knowledge_max_chars', [
            'sanitize_callback' => 'absint',
            'default'           => 1500,
        ]);

        // Gruppo contesto sito
        register_setting( 'vcai_settings', 'vcai_site_name', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting( 'vcai_settings', 'vcai_site_sector', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting( 'vcai_settings', 'vcai_site_tone', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting( 'vcai_settings', 'vcai_site_target', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting( 'vcai_settings', 'vcai_site_description', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
    }

    /**
     * Carica CSS e JS solo nelle pagine del plugin
     */
    public function enqueue_assets( $hook ) {
        // Script per il bottone alt text nella modale media (tutte le pagine admin)
        wp_enqueue_script( 'vcai-media-alt', VCAI_PLUGIN_URL . 'assets/js/media-alt.js', [ 'jquery' ], VCAI_VERSION, true );
        wp_localize_script( 'vcai-media-alt', 'vcai_alt', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'vcai_nonce' ),
        ]);

        if ( strpos( $hook, 'vcai' ) === false ) return;

        // Chart.js per la pagina log
        if ( strpos( $hook, 'vcai-logs' ) !== false ) {
            wp_enqueue_script( 'chartjs', VCAI_PLUGIN_URL . 'assets/vendor/chart.min.js', [], '4.5.1', true );
        }

        // Bootstrap solo nelle pagine VColonna AI (settings, logs, knowledge)
        wp_enqueue_style( 'bootstrap', VCAI_PLUGIN_URL . 'assets/vendor/bootstrap.min.css', [], '5.3.3' );
        wp_enqueue_script( 'bootstrap', VCAI_PLUGIN_URL . 'assets/vendor/bootstrap.bundle.min.js', [], '5.3.3', true );

        wp_enqueue_style(
            'vcai-admin',
            VCAI_PLUGIN_URL . 'assets/css/admin.css',
            [],
            VCAI_VERSION
        );

        wp_enqueue_script(
            'vcai-admin',
            VCAI_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            VCAI_VERSION,
            true
        );

        // Passa dati PHP → JS
        wp_localize_script( 'vcai-admin', 'vcai', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'vcai_nonce' ),
        ]);
    }

    /**
     * Renderizza la pagina impostazioni
     */
    public function render_settings_page() {
        require_once VCAI_PLUGIN_DIR . 'templates/settings-page.php';
    }

    /**
     * Renderizza la pagina log
     */
    public function render_logs_page() {
        $per_page    = 30;
        $current     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pagination, read-only
        $total_items = Vcai_Logger::count_logs();
        $total_pages = max( 1, ceil( $total_items / $per_page ) );
        $logs        = Vcai_Logger::get_logs( $per_page, $current );
        $stats       = Vcai_Logger::get_stats();
        $daily_stats    = Vcai_Logger::get_daily_stats( 30 );
        $provider_stats = Vcai_Logger::get_provider_stats();

        wp_enqueue_script( 'vcai-logs-charts', VCAI_PLUGIN_URL . 'assets/js/logs-charts.js', [ 'chartjs' ], VCAI_VERSION, true );
        wp_localize_script( 'vcai-logs-charts', 'vcai_logs_data', [
            'daily'    => $daily_stats,
            'provider' => $provider_stats,
            'i18n'     => [
                'calls'  => __( 'Chiamate', 'vcolonna-ai-assistant' ),
                'tokens' => __( 'Token', 'vcolonna-ai-assistant' ),
            ],
        ] );

        require_once VCAI_PLUGIN_DIR . 'templates/logs-page.php';
    }

    /**
     * AJAX: testa la connessione API
     */
    public function ajax_test_api() {
        check_ajax_referer( 'vcai_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permessi insufficienti.', 'vcolonna-ai-assistant' ) );
        }

        $connector = self::get_connector();
        if ( ! $connector ) {
            wp_send_json_error( __( 'API key non configurata.', 'vcolonna-ai-assistant' ) );
        }

        $response = $connector->generate( 'Rispondi solo con: "VColonna AI connesso correttamente!"' );

        if ( $response['success'] ) {
            wp_send_json_success( $response['text'] );
        } else {
            wp_send_json_error( $response['error'] );
        }
    }

    /**
     * AJAX: svuota tutti i log
     */
    public function ajax_clear_logs() {
        check_ajax_referer( 'vcai_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permessi insufficienti.' );
        }

        Vcai_Logger::clear_logs();
        wp_send_json_success( 'Log svuotati.' );
    }

    /**
     * Recupera il contesto del sito da usare nei prompt
     */
    public static function get_site_context(): string {
        $name        = get_option( 'vcai_site_name', get_bloginfo('name') );
        $sector      = get_option( 'vcai_site_sector', '' );
        $tone        = get_option( 'vcai_site_tone', '' );
        $target      = get_option( 'vcai_site_target', '' );
        $description = get_option( 'vcai_site_description', '' );

        $context = "Stai lavorando per il sito web chiamato \"$name\".";
        if ( $sector )      $context .= " Settore: $sector.";
        if ( $description ) $context .= " Descrizione: $description.";
        if ( $tone )        $context .= " Tono di comunicazione: $tone.";
        if ( $target )      $context .= " Pubblico target: $target.";
        $context .= " Scrivi sempre in italiano, a meno che non venga specificato diversamente.";

        return $context;
    }

    /**
     * Crea e restituisce il connettore AI attivo
     */
    public static function get_connector(): ?Vcai_API_Connector {
        $provider = get_option( 'vcai_default_provider', 'gemini' );

        if ( $provider === 'openai' ) {
            $api_key = get_option( 'vcai_openai_api_key', '' );
            if ( empty( $api_key ) ) return null;
            $connector = new Vcai_OpenAI( $api_key );
            $connector->set_model( get_option( 'vcai_openai_model', 'gpt-5.4-mini' ) );
            return $connector;
        }

        if ( $provider === 'claude' ) {
            $api_key = get_option( 'vcai_claude_api_key', '' );
            if ( empty( $api_key ) ) return null;
            $connector = new Vcai_Claude( $api_key );
            $connector->set_model( get_option( 'vcai_claude_model', 'claude-sonnet-4-6' ) );
            return $connector;
        }

        if ( $provider === 'groq' ) {
            $api_key = get_option( 'vcai_groq_api_key', '' );
            if ( empty( $api_key ) ) return null;
            $connector = new Vcai_Groq( $api_key );
            $connector->set_model( get_option( 'vcai_groq_model', 'llama-3.3-70b-versatile' ) );
            return $connector;
        }

        $api_key = get_option( 'vcai_gemini_api_key', '' );
        if ( empty( $api_key ) ) return null;
        $connector = new Vcai_Gemini( $api_key );
        $connector->set_model( get_option( 'vcai_gemini_model', 'gemini-2.5-flash-lite' ) );
        return $connector;
    }

    /**
     * Controlla e incrementa il rate limit per l'utente corrente.
     * Restituisce true se il limite è superato.
     */
    public static function is_rate_limited(): bool {
        $limit = (int) get_option( 'vcai_rate_limit', 30 );
        if ( $limit <= 0 ) return false; // 0 = disabilitato

        $user_id = get_current_user_id();
        $key     = 'vcai_rl_' . $user_id;
        $count   = (int) get_transient( $key );

        if ( $count >= $limit ) return true;

        set_transient( $key, $count + 1, HOUR_IN_SECONDS );
        return false;
    }

    /**
     * Renderizza la pagina Knowledge Base
     */
    public function render_knowledge_page() {
        $documents = Vcai_Knowledge::get_documents();
        require_once VCAI_PLUGIN_DIR . 'templates/knowledge-page.php';
    }

    /**
     * AJAX: carica un documento nella knowledge base
     */
    public function ajax_upload_knowledge() {
        check_ajax_referer( 'vcai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permessi insufficienti.' );

        $name    = sanitize_text_field( wp_unslash( $_POST['doc_name'] ?? '' ) );
        $content = sanitize_textarea_field( wp_unslash( $_POST['doc_content'] ?? '' ) );

        if ( empty( $name ) || empty( $content ) ) {
            wp_send_json_error( 'Nome e contenuto sono obbligatori.' );
        }

        $chunks = Vcai_Knowledge::add_document( $name, $content );
        wp_send_json_success( [ 'chunks' => $chunks, 'message' => "Documento \"$name\" salvato ($chunks frammenti)." ] );
    }

    /**
     * AJAX: elimina un documento dalla knowledge base
     */
    public function ajax_delete_knowledge() {
        check_ajax_referer( 'vcai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permessi insufficienti.' );

        $name = sanitize_text_field( wp_unslash( $_POST['doc_name'] ?? '' ) );
        if ( empty( $name ) ) wp_send_json_error( 'Nome documento mancante.' );

        Vcai_Knowledge::delete_document( $name );
        wp_send_json_success( 'Documento eliminato.' );
    }

    /**
     * AJAX: indicizza tutti i post pubblicati
     */
    public function ajax_index_posts() {
        check_ajax_referer( 'vcai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permessi insufficienti.' );

        $count = Vcai_Knowledge::index_all_posts();
        wp_send_json_success( [ 'count' => $count, 'message' => "$count post indicizzati nella knowledge base." ] );
    }

    /**
     * Aggiunge il bottone "Genera Alt Text" nella modale media
     */
    public function add_alt_button_to_media( $form_fields, $post ) {
        if ( ! wp_attachment_is_image( $post->ID ) ) return $form_fields;

        $form_fields['vcai_alt'] = [
            'label' => '',
            'input' => 'html',
            'html'  => '<button type="button" class="button vcai-generate-alt" data-id="' . esc_attr( $post->ID ) . '">🤖 ' . esc_html__( 'Genera Alt Text con AI', 'vcolonna-ai-assistant' ) . '</button>',
        ];

        return $form_fields;
    }

    /**
     * AJAX: genera alt text per un'immagine
     */
    public function ajax_generate_alt() {
        check_ajax_referer( 'vcai_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Permessi insufficienti.' );

        $attachment_id = intval( $_POST['attachment_id'] ?? 0 );
        if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
            wp_send_json_error( 'Immagine non valida.' );
        }

        $connector = self::get_connector();
        if ( ! $connector ) wp_send_json_error( 'API key non configurata.' );

        $image_url = wp_get_attachment_url( $attachment_id );
        if ( ! $image_url ) wp_send_json_error( 'URL immagine non trovato.' );

        // Usa il thumbnail per risparmiare token
        $thumb = wp_get_attachment_image_src( $attachment_id, 'medium' );
        $url   = $thumb ? $thumb[0] : $image_url;

        $context  = self::get_site_context();
        $prompt   = "$context\n\nGenera un alt text breve e descrittivo (massimo 125 caratteri) per questa immagine. ";
        $prompt  .= "Rispondi SOLO con il testo dell'alt, senza virgolette né spiegazioni.\n\nURL immagine: $url";

        $response = $connector->generate( $prompt, [ 'max_tokens' => 100, 'temperature' => 0.3 ] );

        if ( ! $response['success'] ) {
            wp_send_json_error( $response['error'] );
        }

        $alt_text = sanitize_text_field( trim( $response['text'], ' "\'') );
        update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

        wp_send_json_success( [ 'alt_text' => $alt_text ] );
    }

}
