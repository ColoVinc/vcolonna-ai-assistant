<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Connettore per OpenAI API (GPT) — con Function Calling
 */
class Vcai_OpenAI extends Vcai_API_Connector {

    private $api_base = 'https://api.openai.com/v1/chat/completions';

    protected function get_api_base(): string {
        return $this->api_base;
    }

    protected function get_provider_name(): string {
        return 'openai';
    }

    public function generate( string $prompt, array $options = [] ): array {
        if ( empty( $this->api_key ) ) {
            return $this->format_error( 'API key OpenAI non configurata.' );
        }

        $body = [
            'model'      => $options['model'] ?? $this->model,
            'messages'    => [ [ 'role' => 'user', 'content' => $prompt ] ],
            'max_tokens'  => $options['max_tokens']  ?? 2048,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        $response = $this->http_post( $this->get_api_base(), $body, $this->auth_headers() );

        if ( ! $response['success'] ) {
            Vcai_Logger::log( $this->get_provider_name(), 0, 0, 'error', $response['error'] );
            return $this->format_error( $response['error'], $response['code'] );
        }

        $data = $response['data'];
        $text = $data['choices'][0]['message']['content'] ?? '';

        if ( empty( $text ) ) {
            Vcai_Logger::log( $this->get_provider_name(), 0, 0, 'error', 'Risposta vuota.' );
            return $this->format_error( 'Risposta vuota da OpenAI.' );
        }

        $pt = $data['usage']['prompt_tokens']     ?? 0;
        $ct = $data['usage']['completion_tokens'] ?? 0;
        Vcai_Logger::log( $this->get_provider_name(), $pt, $ct, 'success' );

        return $this->format_response( $text, $pt, $ct );
    }

    public function generate_with_tools( array $history, string $message, array $options = [] ): array {
        if ( empty( $this->api_key ) ) {
            return $this->format_error( 'API key OpenAI non configurata.' );
        }

        // Converti history dal formato Gemini al formato OpenAI
        $messages = [];
        $messages[] = [ 'role' => 'system', 'content' => $this->build_system_prompt() ];
        foreach ( $history as $turn ) {
            $role    = $turn['role'] === 'model' ? 'assistant' : 'user';
            $content = $turn['parts'][0]['text'] ?? '';
            if ( $content ) $messages[] = [ 'role' => $role, 'content' => $content ];
        }
        $messages[] = [ 'role' => 'user', 'content' => $message ];

        $body = [
            'model'       => $options['model'] ?? $this->model,
            'messages'    => $messages,
            'tools'       => $this->convert_tools(),
            'max_tokens'  => $options['max_tokens']  ?? 1024,
            'temperature' => $options['temperature'] ?? 0.4,
        ];

        $total_pt    = 0;
        $total_ct    = 0;
        $last_action = null;
        $max_turns   = 5;

        for ( $turn = 0; $turn < $max_turns; $turn++ ) {
            $body['messages'] = $messages;
            $response = $this->http_post( $this->get_api_base(), $body, $this->auth_headers() );

            if ( ! $response['success'] ) {
                // Se un'azione è già stata eseguita, restituisci il suo risultato anziché l'errore
                if ( $last_action ) {
                    $fallback = $last_action['result']['message'] ?? 'Operazione completata.';
                    Vcai_Logger::log( $this->get_provider_name(), $total_pt, $total_ct, 'success' );
                    $result = $this->format_response( $fallback, $total_pt, $total_ct );
                    $result['action_taken'] = $last_action;
                    return $result;
                }
                Vcai_Logger::log( $this->get_provider_name(), $total_pt, $total_ct, 'error', $response['error'] );
                return $this->format_error( $response['error'], $response['code'] );
            }

            $data    = $response['data'];
            $choice  = $data['choices'][0] ?? [];
            $msg     = $choice['message'] ?? [];
            $total_pt += $data['usage']['prompt_tokens']     ?? 0;
            $total_ct += $data['usage']['completion_tokens'] ?? 0;

            // Nessun tool call
            if ( empty( $msg['tool_calls'] ) ) {
                $text = $msg['content'] ?? 'Operazione completata.';
                Vcai_Logger::log( $this->get_provider_name(), $total_pt, $total_ct, 'success' );
                $result = $this->format_response( $text, $total_pt, $total_ct );
                if ( $last_action ) $result['action_taken'] = $last_action;
                return $result;
            }

            // Esegui tool calls
            $messages[] = $msg; // assistant message con tool_calls
            foreach ( $msg['tool_calls'] as $tc ) {
                $tool_name = $tc['function']['name'];
                $tool_args = json_decode( $tc['function']['arguments'], true ) ?? [];
                $tool_result = Vcai_Tools::execute( $tool_name, $tool_args );

                if ( in_array( $tool_name, [ 'create_post', 'update_post', 'delete_post', 'create_custom_post', 'update_custom_post', 'moderate_comment', 'reply_comment', 'update_site_settings', 'create_product', 'add_menu_item' ] ) ) {
                    $last_action = [ 'tool' => $tool_name, 'result' => $tool_result ];
                }

                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $tc['id'],
                    'content'      => wp_json_encode( $tool_result ),
                ];
            }
        }

        $fallback = $last_action['result']['message'] ?? 'Operazione completata.';
        Vcai_Logger::log( $this->get_provider_name(), $total_pt, $total_ct, 'success' );
        $result = $this->format_response( $fallback, $total_pt, $total_ct );
        if ( $last_action ) $result['action_taken'] = $last_action;
        return $result;
    }

    private function auth_headers(): array {
        return [ 'Authorization' => 'Bearer ' . $this->api_key ];
    }

    private function build_system_prompt(): string {
        $system = Vcai_Admin::get_site_context();
        $system .= "\n\nSei un assistente AI integrato nel pannello di amministrazione WordPress. ";
        $system .= "Puoi eseguire azioni reali sul sito usando i tool disponibili. ";
        $system .= "Quando l'utente chiede di creare, modificare, eliminare o recuperare contenuti, usa SEMPRE i tool appropriati. NON chiedere mai all'utente di eseguire comandi o tool. ";
        $system .= "REGOLA FONDAMENTALE: quando l'utente menziona un Custom Post Type (qualsiasi tipo diverso da 'post' e 'page'), devi IMMEDIATAMENTE chiamare il tool get_custom_post_types per scoprire i CPT e campi ACF, poi chiamare create_custom_post o update_custom_post. ";
        $system .= "Dopo aver eseguito un'azione, conferma cosa hai fatto in modo chiaro e conciso. ";
        $system .= "Rispondi sempre in italiano.";
        return $system;
    }

    /**
     * Converte le dichiarazioni tool dal formato Gemini al formato OpenAI
     */
    private function convert_tools(): array {
        $tools = [];
        foreach ( Vcai_Tools::get_declarations() as $decl ) {
            $params = $decl['parameters'] ?? [];
            if ( $params instanceof \stdClass ) {
                $params = [ 'type' => 'object', 'properties' => new \stdClass() ];
            }
            $tools[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => $decl['name'],
                    'description' => $decl['description'],
                    'parameters'  => $params,
                ],
            ];
        }
        return $tools;
    }

    /**
     * Streaming: manda la risposta testuale chunk per chunk via SSE.
     */
    public function stream_response( string $prompt, array $options = [] ): void {
        $body = [
            'model'       => $options['model'] ?? $this->model,
            'messages'    => $options['messages'] ?? [ [ 'role' => 'user', 'content' => $prompt ] ],
            'max_tokens'  => $options['max_tokens']  ?? 1024,
            'temperature' => $options['temperature'] ?? 0.4,
            'stream'      => true,
        ];

        $total_pt = 0;
        $total_ct = 0;

        $this->http_stream( $this->get_api_base(), $body, $this->auth_headers(), function( $line ) use ( &$total_pt, &$total_ct ) {
            $line = trim( $line );
            if ( strpos( $line, 'data: ' ) !== 0 ) return;
            $payload = substr( $line, 6 );
            if ( $payload === '[DONE]' ) return;
            $json = json_decode( $payload, true );
            if ( ! $json ) return;

            $text = $json['choices'][0]['delta']['content'] ?? '';
            if ( $text !== '' ) {
                echo "data: " . wp_json_encode( [ 'chunk' => $text ] ) . "\n\n";
            }

            if ( isset( $json['usage'] ) ) {
                $total_pt = $json['usage']['prompt_tokens']     ?? $total_pt;
                $total_ct = $json['usage']['completion_tokens'] ?? $total_ct;
            }
        });

        Vcai_Logger::log( $this->get_provider_name(), $total_pt, $total_ct, 'success' );
        echo "data: [DONE]\n\n";
    }

    public static function get_models(): array {
        return [
            'gpt-5.4-nano'  => 'GPT-5.4 Nano (più economico, $0.20/M token)',
            'gpt-5.4-mini'  => 'GPT-5.4 Mini (bilanciato, $0.75/M token)',
            'gpt-5.4'       => 'GPT-5.4 (flagship, reasoning avanzato)',
            'gpt-4.1'       => 'GPT-4.1 (ottimo per coding, contesto 1M)',
            'gpt-4.1-mini'  => 'GPT-4.1 Mini (veloce, buon rapporto qualità/prezzo)',
            'gpt-4.1-nano'  => 'GPT-4.1 Nano (ultra-economico)',
        ];
    }
}
