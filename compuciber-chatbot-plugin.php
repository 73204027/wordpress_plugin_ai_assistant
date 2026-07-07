<?php
/**
 * Plugin Name: AI Assistant
 * Description: Spanish-only AI sales assistant powered by Ollama.
 * Version: 1.0.0
 * Author: marcelo_dev
 */

if (!defined('ABSPATH')) {
    exit;
}

final class AI_Assistant_Plugin {
    const OPTION_KEY = 'ai_assistant_settings';
    const NONCE_ACTION = 'ai_chat';

    private static $instance = null;

    public static function activate(): void {
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, self::defaults());
        }
    }

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function defaults(): array {
        return [
            'assistant_name'    => 'Asistente',
            'brand_name'        => 'Compuciber',
            'ollama_base_url'   => 'http://localhost:11434/api',
            'ollama_model'      => 'qwen2.5:latest',
            'catalog_api_base'  => '',
            'catalog_api_token' => '',
        ];
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('ai_assistant', [$this, 'shortcode']);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function settings(): array {
        return wp_parse_args(get_option(self::OPTION_KEY, []), self::defaults());
    }

    public function admin_menu(): void {
        add_options_page(
            'AI Assistant',
            'AI Assistant',
            'manage_options',
            'ai-assistant',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void {
        register_setting('ai_assistant_group', self::OPTION_KEY, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($input): array {
        $current = $this->settings();
        $input = is_array($input) ? $input : [];

        return [
            'assistant_name'    => sanitize_text_field($input['assistant_name'] ?? $current['assistant_name']),
            'brand_name'        => sanitize_text_field($input['brand_name'] ?? $current['brand_name']),
            'ollama_base_url'   => esc_url_raw($input['ollama_base_url'] ?? $current['ollama_base_url']),
            'ollama_model'      => sanitize_text_field($input['ollama_model'] ?? $current['ollama_model']),
            'catalog_api_base'  => esc_url_raw($input['catalog_api_base'] ?? $current['catalog_api_base']),
            'catalog_api_token' => sanitize_text_field($input['catalog_api_token'] ?? $current['catalog_api_token']),
        ];
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $o = $this->settings();
        ?>

        <div class="wrap">
            <h1>AI Assistant</h1>
            <form method="post" action="options.php">
                <?php settings_fields('ai_assistant_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="assistant_name">Assistant name</label></th>
                        <td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[assistant_name]" id="assistant_name" type="text" class="regular-text" value="<?php echo esc_attr($o['assistant_name']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="brand_name">Brand name</label></th>
                        <td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[brand_name]" id="brand_name" type="text" class="regular-text" value="<?php echo esc_attr($o['brand_name']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ollama_base_url">Ollama base URL</label></th>
                        <td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[ollama_base_url]" id="ollama_base_url" type="url" class="regular-text" value="<?php echo esc_attr($o['ollama_base_url']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ollama_model">Ollama model</label></th>
                        <td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[ollama_model]" id="ollama_model" type="text" class="regular-text" value="<?php echo esc_attr($o['ollama_model']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="catalog_api_base">Catalog API base URL</label></th>
                        <td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[catalog_api_base]" id="catalog_api_base" type="url" class="regular-text" value="<?php echo esc_attr($o['catalog_api_base']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="catalog_api_token">Catalog API token</label></th>
                        <td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[catalog_api_token]" id="catalog_api_token" type="password" class="regular-text" value="<?php echo esc_attr($o['catalog_api_token']); ?>"></td>
                    </tr>
                </table>
                <?php submit_button('Save settings'); ?>
            </form>
        </div>
        <?php
    }

    public function enqueue_assets(): void {
        wp_enqueue_style(
            'ai-assistant-css',
            plugins_url('assets/chat.css', __FILE__),
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'ai-assistant-js',
            plugins_url('assets/chat.js', __FILE__),
            [],
            '1.0.0',
            true
        );

        $o = $this->settings();
        wp_localize_script('ai-assistant-js', 'AI_CHAT', [
            'endpoint'      => esc_url_raw(rest_url('ai/v1/chat')),
            'nonce'         => wp_create_nonce('wp_rest'),
            'assistantName' => $o['assistant_name'],
            'brandName'     => $o['brand_name'],
            'placeholder'   => 'Escribe tu pregunta...',
        ]);
    }

    public function shortcode(): string {
        ob_start();
        $o = $this->settings();
        ?>
        <div class="ai-shell" data-ai-chat>
            <button type="button" class="ai-fab" aria-label="Open chat">💬</button>
            <div class="ai-panel is-hidden">
                <div class="ai-topbar">
                    <strong><?php echo esc_html($o['assistant_name']); ?></strong>
                    <span><?php echo esc_html($o['brand_name']); ?></span>
                </div>
                <div class="ai-messages"></div>
                <div class="ai-products"></div>
                <form class="ai-form">
                    <input class="ai-input" type="text" autocomplete="off" placeholder="Escribe tu pregunta...">
                    <button class="ai-send" type="submit">Enviar</button>
                </form>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function register_routes(): void {
        register_rest_route('ai/v1', '/chat', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_chat'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_chat(WP_REST_Request $request): WP_REST_Response {

        /*  ENABLE THIS VALIDATION BLOCK TO ASK FOR AUTHENTICATION IN
            ORDER TO USE THE AI CHAT. REMEMBER TO PASS THE NONCE IN THE REQUEST HEADER AS 'x-ai-nonce'.
        */
        // $nonce = trim((string) $request->get_header('x-ai-nonce'));
        // if (!wp_verify_nonce($nonce, 'wp_rest')) {
        //     return new WP_REST_Response([
        //         'ok'    => false,
        //         'reply' => 'Invalid request.',
        //     ], 403);
        // }

        $message = sanitize_textarea_field((string) $request->get_param('message'));
        if ($message === '') {
            return new WP_REST_Response([
                'ok'    => false,
                'reply' => 'Empty message.',
            ], 422);
        }

        $policy = AI_Policy::classify($message);
        if ($policy['mode'] === 'refuse') {
            return new WP_REST_Response([
                'ok'      => true,
                'policy'   => 'refuse',
                'reply'    => $policy['reply'],
                'products' => [],
            ], 200);
        }

        if ($policy['mode'] === 'redirect') {
            return new WP_REST_Response([
                'ok'      => true,
                'policy'   => 'redirect',
                'reply'    => $policy['reply'],
                'products' => [],
            ], 200);
        }

        $o = $this->settings();
        $catalog = new AI_Catalog_Client($o);
        $ollama  = new AI_Ollama_Client($o);

        $history = $request->get_param('history');
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $this->system_prompt()];

        if (is_array($history)) {
            $history = array_slice($history, -6);
            foreach ($history as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $role = ($item['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
                $content = sanitize_textarea_field((string) ($item['content'] ?? ''));
                if ($content !== '') {
                    $messages[] = ['role' => $role, 'content' => $content];
                }
            }
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $tools = $this->tool_schema();

        $result = $ollama->run_tool_loop(
            $messages,
            $tools,
            function (string $tool_name, array $args) use ($catalog) {
                return $this->run_tool($tool_name, $args, $catalog);
            }
        );

        return new WP_REST_Response([
            'ok'      => true,
            'policy'  => 'allow',
            'reply'   => $result['reply'],
            'products'=> $result['products'],
        ], 200);
    }

    private function system_prompt(): string {
        $o = $this->settings();

        return implode("\n", [
            'Eres un asistente de ventas y soporte para una tienda de hardware.',
            'Responde SOLO en español.',
            'Tu misión es ayudar con productos, precios, descuentos, comparaciones, recomendaciones y links directos.',
            'Solo usa información proveniente de herramientas o del contexto del catálogo.',
            'Nunca inventes precios, stock, descuentos, enlaces o especificaciones.',
            'Si el usuario pide algo fuera de tecnología / hardware / accesorios o temas universales permitidos, redirige con amabilidad.',
            'Si el usuario pide temas sensibles, rehúsa con un mensaje breve y vuelve a computadoras.',
            'Si el usuario pide acciones sobre el sistema, solo usa herramientas explícitamente permitidas y solo cuando sean seguras y confirmadas.',
            'Nunca ejecutes shell, SQL directo, archivos, o llamadas arbitrarias.',
            'Marca la tienda como: ' . $o['brand_name'],
        ]);
    }

    private function tool_schema(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Search products by text and filters.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query'     => ['type' => 'string'],
                            'category'  => ['type' => 'string'],
                            'brand'     => ['type' => 'string'],
                            'min_price' => ['type' => 'number'],
                            'max_price' => ['type' => 'number'],
                            'limit'     => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_product',
                    'description' => 'Get a product by ID.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                        ],
                        'required' => ['id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'compare_products',
                    'description' => 'Compare multiple products by IDs.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'ids' => [
                                'type'  => 'array',
                                'items' => ['type' => 'string'],
                            ],
                        ],
                        'required' => ['ids'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_discounts',
                    'description' => 'Get discount listings.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'category' => ['type' => 'string'],
                            'brand'    => ['type' => 'string'],
                            'limit'    => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_current_date',
                    'description' => 'Get the current date and time.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new stdClass(),
                    ],
                ],
            ],
        ];
    }

    private function run_tool(string $tool_name, array $args, AI_Catalog_Client $catalog): array {
        switch ($tool_name) {
            case 'search_products':
                return $catalog->search_products($args);

            case 'get_product':
                return $catalog->get_product($args);

            case 'compare_products':
                return $catalog->compare_products($args);

            case 'get_discounts':
                return $catalog->get_discounts($args);

            case 'get_current_date':
                return [
                    'content' => 'Fecha y hora actuales: ' . wp_date('d/m/Y H:i'),
                    'products' => [],
                ];

            default:
                return [
                    'content' => 'Tool no permitida.',
                    'products' => [],
                ];
        }
    }
}

final class AI_Policy {
    private static function contains(string $haystack, string $needle): bool {
        return strpos($haystack, $needle) !== false;
    }

    public static function classify(string $message): array {
        $m = remove_accents(mb_strtolower($message));

        $sensitive = [
            'politica', 'geopolitica', 'economia', 'medicina', 'salud',
            'genero', 'sexualidad', 'sexo', 'aborto', 'religion', 'guerra',
            'presidente', 'eleccion', 'elecciones', 'militar'
        ];

        foreach ($sensitive as $term) {
            if (self::contains($m, $term)) {
                return [
                    'mode'  => 'refuse',
                    'reply' => 'No creo que sea buena idea hablar de eso 😅. ¿Por qué no volvemos a las computadoras 😎?',
                ];
            }
        }

        $offtopic = [
            'receta', 'cocina', 'cocinar', 'futbol', 'deporte', 'musica',
            'cine', 'pelicula', 'novela', 'poema', 'viaje', 'turismo',
            'salud mental', 'horoscopo', 'broma', 'chiste', 'animales',
        ];

        foreach ($offtopic as $term) {
            if (self::contains($m, $term)) {
                return [
                    'mode'  => 'redirect',
                    'reply' => 'No conozco mucho sobre ese tema... 😅 ¿Por qué mejor no hablamos de computadoras 😎? También puedo mostrarte ofertas y descuentos.',
                ];
            }
        }

        return ['mode' => 'allow'];
    }
}

final class AI_Catalog_Client {
    private $base;
    private $token;

    public function __construct(array $settings) {
        $this->base  = rtrim((string) ($settings['catalog_api_base'] ?? ''), '/');
        $this->token = (string) ($settings['catalog_api_token'] ?? '');
    }

    public function search_products(array $args): array {
        $data = $this->request('GET', '/products', [
            'query'     => sanitize_text_field((string) ($args['query'] ?? '')),
            'category'  => sanitize_text_field((string) ($args['category'] ?? '')),
            'brand'     => sanitize_text_field((string) ($args['brand'] ?? '')),
            'min_price' => isset($args['min_price']) ? (float) $args['min_price'] : '',
            'max_price' => isset($args['max_price']) ? (float) $args['max_price'] : '',
            'limit'     => isset($args['limit']) ? max(1, min(10, (int) $args['limit'])) : 5,
        ]);

        $products = $this->normalize_products($data);
        return [
            'content'  => wp_json_encode(['products' => $products], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'products' => $products,
        ];
    }

    public function get_product(array $args): array {
        $id = sanitize_text_field((string) ($args['id'] ?? ''));
        $data = $this->request('GET', '/products/' . rawurlencode($id), []);
        $product = $this->normalize_product($data);

        return [
            'content'  => wp_json_encode(['product' => $product], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'products' => $product ? [$product] : [],
        ];
    }

    public function compare_products(array $args): array {
        $ids = $args['ids'] ?? [];
        $ids = is_array($ids) ? array_slice(array_filter(array_map('sanitize_text_field', $ids)), 0, 10) : [];

        $data = $this->request('GET', '/products/compare', [
            'ids' => implode(',', $ids),
        ]);

        $products = $this->normalize_products($data);

        return [
            'content'  => wp_json_encode(['comparison' => $products], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'products' => $products,
        ];
    }

    public function get_discounts(array $args): array {
        $data = $this->request('GET', '/discounts', [
            'category' => sanitize_text_field((string) ($args['category'] ?? '')),
            'brand'    => sanitize_text_field((string) ($args['brand'] ?? '')),
            'limit'    => isset($args['limit']) ? max(1, min(10, (int) $args['limit'])) : 5,
        ]);

        $products = $this->normalize_products($data);

        return [
            'content'  => wp_json_encode(['discounts' => $products], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'products' => $products,
        ];
    }

    private function request(string $method, string $path, array $query = []): array {
        if ($this->base === '') {
            return [];
        }

        $url = $this->base . '/' . ltrim($path, '/');
        if (!empty($query)) {
            $url = add_query_arg($query, $url);
        }

        $headers = ['Accept' => 'application/json'];
        if ($this->token !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        $response = wp_remote_request($url, [
            'method'  => $method,
            'timeout' => 20,
            'headers' => $headers,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        return is_array($json) ? $json : [];
    }

    private function normalize_products(array $payload): array {
        $items = $payload['products'] ?? $payload['items'] ?? $payload['data'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $p = $this->normalize_product($item);
                if (!empty($p)) {
                    $out[] = $p;
                }
            }
        }
        return $out;
    }

    private function normalize_product(array $p): array {
        $price = isset($p['price']) ? (float) $p['price'] : 0.0;
        $sale  = isset($p['sale_price']) ? (float) $p['sale_price'] : 0.0;

        return [
            'id'               => (string) ($p['id'] ?? ''),
            'name'             => (string) ($p['name'] ?? $p['title'] ?? ''),
            'brand'            => (string) ($p['brand'] ?? ''),
            'category'         => (string) ($p['category'] ?? ''),
            'price'            => $price,
            'sale_price'       => $sale,
            'discount_percent' => isset($p['discount_percent']) ? (float) $p['discount_percent'] : 0.0,
            'currency'         => (string) ($p['currency'] ?? 'USD'),
            'url'              => esc_url_raw((string) ($p['url'] ?? $p['link'] ?? '')),
            'image'            => esc_url_raw((string) ($p['image'] ?? $p['image_url'] ?? '')),
            'summary'          => wp_strip_all_tags((string) ($p['summary'] ?? $p['description'] ?? '')),
            'stock'            => isset($p['stock']) ? (int) $p['stock'] : null,
        ];
    }
}

final class AI_Ollama_Client {
    private $settings;

    public function __construct(array $settings) {
        $this->settings = $settings;
    }

    public function run_tool_loop(array $messages, array $tools, callable $tool_runner): array {
        $max_rounds = 4;
        $last_reply = '';
        $products   = [];

        for ($i = 0; $i < $max_rounds; $i++) {
            $response = $this->chat($messages, $tools);
            if (empty($response['message']) || !is_array($response['message'])) {
                error_log('OLLAMA RESPONSE: ' . print_r($response, true));
                break;
            }
            

            $assistant_message = $response['message'];
            $messages[] = $assistant_message;

            $content = trim((string) ($assistant_message['content'] ?? ''));
            if ($content !== '') {
                $last_reply = $content;
            }

            $tool_calls = $assistant_message['tool_calls'] ?? [];
            if (!is_array($tool_calls) || empty($tool_calls)) {
                break;
            }

            foreach ($tool_calls as $call) {
                $tool_name = (string) ($call['function']['name'] ?? '');
                $args      = $call['function']['arguments'] ?? [];
                $args      = is_array($args) ? $args : [];

                $result = call_user_func($tool_runner, $tool_name, $args);

                if (isset($result['products']) && is_array($result['products'])) {
                    $products = $result['products'];
                }

                $messages[] = [
                    'role'      => 'tool',
                    'tool_name' => $tool_name,
                    'content'   => wp_json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];
            }
        }

        return [
            'reply'   => $last_reply !== '' ? $last_reply : 'No pude generar una respuesta.',
            'products'=> $products,
        ];
    }

    private function chat(array $messages, array $tools): array {
        $base_url = rtrim((string) ($this->settings['ollama_base_url'] ?? 'http://localhost:11434/api'), '/');
        $model    = (string) ($this->settings['ollama_model'] ?? 'llama3.1:8b-instruct');

        $response = wp_remote_post($base_url . '/chat', [
            'timeout' => 120,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'model'    => $model,
                'stream'   => false,
                'messages'  => $messages,
                'tools'    => $tools,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        return is_array($json) ? $json : [];
    }
}

register_activation_hook(__FILE__, ['AI_Assistant_Plugin', 'activate']);

// bootstrap the plugin
AI_Assistant_Plugin::instance();