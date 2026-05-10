<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Classe base per tutti i connettori API
 * Ogni provider (Gemini, OpenAI, Claude) estende questa classe
 */
abstract class Vcai_API_Connector {

    protected $api_key;
    protected $timeout = 30;
    protected $model   = '';

    public function __construct( $api_key ) {
        $this->api_key = $api_key;
        $this->timeout = (int) get_option( 'vcai_api_timeout', 30 ) ?: 30;
    }

    /**
     * Imposta il modello da usare
     */
    public function set_model( string $model ): void {
        $this->model = $model;
    }

    /**
     * Metodo principale da implementare in ogni connettore
     */
    abstract public function generate( string $prompt, array $options = [] ): array;

    /**
     * Genera con function calling — da implementare in ogni connettore
     */
    abstract public function generate_with_tools( array $history, string $message, array $options = [] ): array;

    /**
     * Streaming della risposta testuale — scrive chunk SSE direttamente nell'output.
     * Da sovrascrivere nei connettori. Fallback: manda tutto in un colpo.
     */
    public function stream_response( string $prompt, array $options = [] ): void {
        $response = $this->generate( $prompt, $options );
        if ( $response['success'] ) {
            echo "data: " . wp_json_encode( [ 'chunk' => $response['text'] ] ) . "\n\n";
        } else {
            echo "data: " . wp_json_encode( [ 'error' => $response['error'] ] ) . "\n\n";
        }
        echo "data: [DONE]\n\n";
    }

    /**
     * Esegue una chiamata HTTP con streaming — legge la risposta riga per riga
     * e chiama $callback per ogni chunk di testo.
     * Usa cURL con CURLOPT_WRITEFUNCTION per compatibilità con tutti gli hosting.
     */
    protected function http_stream( string $url, array $body, array $headers, callable $callback ): array {
        $default_headers = [ 'Content-Type' => 'application/json' ];
        $all_headers = array_merge( $default_headers, $headers );

        $curl_headers = [];
        foreach ( $all_headers as $k => $v ) {
            $curl_headers[] = "$k: $v";
        }

        $ch = curl_init( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init -- streaming requires cURL WRITEFUNCTION callback, not available via WP HTTP API
        curl_setopt( $ch, CURLOPT_POST, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
        curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $body ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $curl_headers ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
        curl_setopt( $ch, CURLOPT_TIMEOUT, $this->timeout ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt

        $buffer = '';
        curl_setopt( $ch, CURLOPT_WRITEFUNCTION, function( $ch, $data ) use ( $callback, &$buffer ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
            $buffer .= $data;
            while ( ( $pos = strpos( $buffer, "\n" ) ) !== false ) {
                $line = substr( $buffer, 0, $pos + 1 );
                $buffer = substr( $buffer, $pos + 1 );
                $callback( $line );
                if ( ob_get_level() ) ob_flush();
                flush();
            }
            return strlen( $data );
        } );

        curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
        $error = curl_error( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error
        curl_close( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close

        // Processa eventuale contenuto rimasto nel buffer
        if ( $buffer !== '' ) {
            $callback( $buffer );
            if ( ob_get_level() ) ob_flush();
            flush();
        }

        if ( $error ) {
            return [ 'success' => false, 'error' => 'Errore connessione: ' . $error ];
        }

        return [ 'success' => true ];
    }

    /**
     * Esegue una chiamata HTTP POST verso l'API
     */
    protected function http_post( string $url, array $body, array $headers = [] ): array {
        $default_headers = [
            'Content-Type' => 'application/json',
        ];

        $response = wp_remote_post( $url, [
            'timeout' => $this->timeout,
            'headers' => array_merge( $default_headers, $headers ),
            'body'    => wp_json_encode( $body ),
        ]);

        // Errore di connessione (timeout, DNS, ecc.)
        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
                'code'    => 0,
            ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $error_msg = $body['error']['message'] ?? "Errore HTTP $code";
            return [
                'success' => false,
                'error'   => $error_msg,
                'code'    => $code,
            ];
        }

        return [
            'success' => true,
            'data'    => $body,
            'code'    => $code,
        ];
    }

    /**
     * Formatta la risposta in un formato standard uguale per tutti i provider
     */
    protected function format_response( string $text, int $prompt_tokens = 0, int $completion_tokens = 0 ): array {
        return [
            'success'           => true,
            'text'              => $text,
            'prompt_tokens'     => $prompt_tokens,
            'completion_tokens' => $completion_tokens,
            'total_tokens'      => $prompt_tokens + $completion_tokens,
        ];
    }

    /**
     * Formatta un errore in formato standard
     */
    protected function format_error( string $message, int $code = 0 ): array {
        // Mappa codici HTTP a messaggi utente
        $user_message = $message;
        switch ( $code ) {
            case 401: $user_message = __( 'API key non valida. Controlla la chiave nelle impostazioni di VColonna AI.', 'vcolonna-ai-assistant' ); break;
            case 403: $user_message = __( 'Accesso negato dall\'API. Verifica i permessi della tua API key.', 'vcolonna-ai-assistant' ); break;
            case 429: $user_message = __( 'Quota API esaurita o troppe richieste. Riprova tra qualche minuto.', 'vcolonna-ai-assistant' ); break;
            case 500:
            case 502:
            case 503: $user_message = __( 'Il servizio AI è temporaneamente non disponibile. Riprova tra poco.', 'vcolonna-ai-assistant' ); break;
        }

        return [
            'success' => false,
            'error'   => $user_message,
            'code'    => $code,
        ];
    }
}
