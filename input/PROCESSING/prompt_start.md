Give me the specific and detailed software architecture of an E-commerce WordPress + WooCommerce web system, featuring a custom business-data-driven customer service AI Agent (LLM) ChatBot, with Ollama, including concepts like skills, tools, cli, mcp… which gets materialized in the form of a WordPress plugin.
"CONCEPTUALIZING
(human development)
PROTOTYPES FOR AI AGENT:

3RD PARTY SERVICE
Custom madeOpenAI API
Local ModelMCP + SKILLS + Ollama
SKILLS + CLI + Ollama
hosted n8n + Ollama
RESULT 1
Below is the version I would actually ship first: a single WordPress plugin directory that keeps the chat UI, REST endpoint, Ollama bridge, topic guardrails, and catalog lookup logic together. Ollama supports tool calling through /api/chat, and Modelfile is the place to define FROM, PARAMETER, and SYSTEM; WordPress custom REST routes should be registered on rest_api_init, include permission_callback, and front-end scripts should be loaded with wp_enqueue_script. For browser-to-WordPress REST calls inside WordPress, the standard nonce pattern is wp_create_nonce('wp_rest') plus the X-WP-Nonce header.
I am assuming your product data will be exposed by a REST catalog API. If you later move that adapter into Laravel, Laravel’s HTTP client is the official outgoing-request wrapper on that side.

What this design does
It gives you:
a Spanish-only assistant,

strict topic boundaries,

a safe redirect when the user is off-topic,

a hard refusal for sensitive topics,

product search / comparison / discounts through tools,

direct product links,

no arbitrary system execution,

one plugin directory only.
I would not start with fine-tuning. Start with a strong system prompt, strict tool calling, and clean catalog APIs. Fine-tuning becomes useful later, after you have real chat logs and know what the model still gets wrong.

Step-by-step

Create this folder:

wp-content/plugins/aia-assistant/

Add the files below.

Activate the plugin in WordPress.

Create the Ollama model with the Modelfile below. Ollama documents the ollama create ... -f Modelfile flow and the Modelfile syntax.

Point the plugin settings to:

your local Ollama server,

your catalog API base URL,

your API token if your catalog is protected.

Implement the catalog endpoints in Laravel or your current backend so they return products, prices, discounts, and URLs in the shape expected by the plugin.

Test these cases:
product search,

comparison,

discount lookup,

off-topic redirect,

sensitive-topic refusal.

Only after this works, consider extra tools like add-to-cart, quote generation, or support ticket creation. Keep those allowlisted and explicit; do not expose shell, SQL, or filesystem execution.


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
                            'query'     => ['type' => 'string'],
                            'category'  => ['type' => 'string'],
                            'brand'     => ['type' => 'string'],
                            'min_price' => ['type' => 'number'],
                            'max_price' => ['type' => 'number'],
                            'limit'     => ['type' => 'integer'],
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
                                'type'  => 'array',
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
                            'brand'    => ['type' => 'string'],
                            'limit'    => ['type' => 'integer'],
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

    private function run_tool(string $tool_name, array $args, AIA_Catalog_Client $catalog): array {
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

final class AIA_Policy {
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
                    'mode'  => 'refuse',
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
                    'mode'  => 'redirect',
                    'reply' => 'No conozco mucho sobre ese tema... 😅 ¿Por qué mejor no hablamos de computadoras 😎? También puedo mostrarte ofertas y descuentos.',
                ];
            }
        }

        return ['mode' => 'allow'];
    }
}

final class AIA_Catalog_Client {
    private $base;
    private $token;

    public function __construct(array $settings) {
        $this->base  = rtrim((string) ($settings['catalog_api_base'] ?? ''), '/');
        $this->token = (string) ($settings['catalog_api_token'] ?? '');
    }

    public function search_products(array $args): array {
        $data = $this->request('GET', '/products', [
            'query'     => sanitize_text_field((string) ($args['query'] ?? '')),
            'category'  => sanitize_text_field((string) ($args['category'] ?? '')),
            'brand'     => sanitize_text_field((string) ($args['brand'] ?? '')),
            'min_price' => isset($args['min_price']) ? (float) $args['min_price'] : '',
            'max_price' => isset($args['max_price']) ? (float) $args['max_price'] : '',
            'limit'     => isset($args['limit']) ? max(1, min(10, (int) $args['limit'])) : 5,
        ]);

        $products = $this->normalize_products($data);
        return [
            'content'  => wp_json_encode(['products' => $products], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'products' => $products,
        ];
    }

    public function get_product(array $args): array {
        $id = sanitize_text_field((string) ($args['id'] ?? ''));
        $data = $this->request('GET', '/products/' . rawurlencode($id), []);
        $product = $this->normalize_product($data);

        return [
            'content'  => wp_json_encode(['product' => $product], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
            'content'  => wp_json_encode(['comparison' => $products], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'products' => $products,
        ];
    }

    public function get_discounts(array $args): array {
        $data = $this->request('GET', '/discounts', [
            'category' => sanitize_text_field((string) ($args['category'] ?? '')),
            'brand'    => sanitize_text_field((string) ($args['brand'] ?? '')),
            'limit'    => isset($args['limit']) ? max(1, min(10, (int) $args['limit'])) : 5,
        ]);

        $products = $this->normalize_products($data);

        return [
            'content'  => wp_json_encode(['discounts' => $products], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
            'method'  => $method,
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
        $sale  = isset($p['sale_price']) ? (float) $p['sale_price'] : 0.0;

        return [
            'id'               => (string) ($p['id'] ?? ''),
            'name'             => (string) ($p['name'] ?? $p['title'] ?? ''),
            'brand'            => (string) ($p['brand'] ?? ''),
            'category'         => (string) ($p['category'] ?? ''),
            'price'            => $price,
            'sale_price'       => $sale,
            'discount_percent' => isset($p['discount_percent']) ? (float) $p['discount_percent'] : 0.0,
            'currency'         => (string) ($p['currency'] ?? 'USD'),
            'url'              => esc_url_raw((string) ($p['url'] ?? $p['link'] ?? '')),
            'image'            => esc_url_raw((string) ($p['image'] ?? $p['image_url'] ?? '')),
            'summary'          => wp_strip_all_tags((string) ($p['summary'] ?? $p['description'] ?? '')),
            'stock'            => isset($p['stock']) ? (int) $p['stock'] : null,
        ];
    }
}

final class AIA_Ollama_Client {
    private $settings;

    public function __construct(array $settings) {
        $this->settings = $settings;
    }

    public function run_tool_loop(array $messages, array $tools, callable $tool_runner): array {
        $max_rounds = 4;
        $last_reply = '';
        $products   = [];

        for ($i = 0; $i < $max_rounds; $i++) {
            $response = $this->chat($messages, $tools);
            if (empty($response['message']) || !is_array($response['message'])) {
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
                $args      = $call['function']['arguments'] ?? [];
                $args      = is_array($args) ? $args : [];

                $result = call_user_func($tool_runner, $tool_name, $args);

                if (isset($result['products']) && is_array($result['products'])) {
                    $products = $result['products'];
                }

                $messages[] = [
                    'role'      => 'tool',
                    'tool_name' => $tool_name,
                    'content'   => wp_json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];
            }
        }

        return [
            'reply'   => $last_reply !== '' ? $last_reply : 'No pude generar una respuesta.',
            'products'=> $products,
        ];
    }

    private function chat(array $messages, array $tools): array {
        $base_url = rtrim((string) ($this->settings['ollama_base_url'] ?? 'http://localhost:11434/api'), '/');
        $model    = (string) ($this->settings['ollama_model'] ?? 'llama3.1:8b-instruct');

        $response = wp_remote_post($base_url . '/chat', [
            'timeout' => 120,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'model'    => $model,
                'stream'   => false,
                'messages'  => $messages,
                'tools'    => $tools,
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

register_activation_hook(__FILE__, ['AIA_Assistant_Plugin', 'activate']);
AIA_Assistant_Plugin::instance();
assets/chat.js
document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector("[data-aia-chat]");
  if (!root || typeof AIA_CHAT === "undefined") return;

  const fab = root.querySelector(".aia-fab");
  const panel = root.querySelector(".aia-panel");
  const messagesEl = root.querySelector(".aia-messages");
  const productsEl = root.querySelector(".aia-products");
  const form = root.querySelector(".aia-form");
  const input = root.querySelector(".aia-input");

  const history = [];

  const scrollBottom = () => {
    messagesEl.scrollTop = messagesEl.scrollHeight;
  };

  const addMessage = (role, text) => {
    history.push({ role, content: text });

    const bubble = document.createElement("div");
    bubble.className = `aia-bubble aia-${role}`;

    const body = document.createElement("div");
    body.className = "aia-text";
    body.textContent = text;

    bubble.appendChild(body);
    messagesEl.appendChild(bubble);
    scrollBottom();
  };

  const renderProducts = (items) => {
    productsEl.innerHTML = "";
    if (!items || !items.length) return;

    const title = document.createElement("div");
    title.className = "aia-products-title";
    title.textContent = "Resultados";
    productsEl.appendChild(title);

    items.forEach((p) => {
      const card = document.createElement("div");
      card.className = "aia-product";

      const name = document.createElement("div");
      name.className = "aia-product-name";
      name.textContent = p.name || "Producto";

      const meta = document.createElement("div");
      meta.className = "aia-product-meta";
      const price = p.sale_price && Number(p.sale_price) > 0 ? p.sale_price : p.price;
      meta.textContent = [
        p.brand ? `Marca: ${p.brand}` : null,
        p.category ? `Categoría: ${p.category}` : null,
        price ? `Precio: ${price} ${p.currency || ""}` : null,
        p.discount_percent ? `Descuento: ${p.discount_percent}%` : null,
      ].filter(Boolean).join(" · ");

      card.appendChild(name);
      card.appendChild(meta);

      if (p.summary) {
        const summary = document.createElement("div");
        summary.className = "aia-product-summary";
        summary.textContent = p.summary;
        card.appendChild(summary);
      }

      if (p.url) {
        const link = document.createElement("a");
        link.className = "aia-product-link";
        link.href = p.url;
        link.target = "_blank";
        link.rel = "noopener noreferrer";
        link.textContent = "Ver producto";
        card.appendChild(link);
      }

      productsEl.appendChild(card);
    });
  };

  fab.addEventListener("click", () => {
    panel.classList.toggle("is-hidden");
    if (!messagesEl.childElementCount) {
      addMessage("assistant", `Hola, soy ${AIA_CHAT.assistantName}. Puedo ayudarte con productos, precios y descuentos.`);
    }
    scrollBottom();
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const text = (input.value || "").trim();
    if (!text) return;

    input.value = "";
    addMessage("user", text);

    const typing = document.createElement("div");
    typing.className = "aia-bubble aia-assistant";
    typing.textContent = "Escribiendo...";
    messagesEl.appendChild(typing);
    scrollBottom();

    try {
      const response = await fetch(AIA_CHAT.endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-AIA-Nonce": AIA_CHAT.nonce,
        },
        body: JSON.stringify({
          message: text,
          history: history.slice(-6),
        }),
      });

      const data = await response.json();
      typing.remove();

      const reply = (data && data.reply) ? data.reply : "No pude responder en este momento.";
      addMessage("assistant", reply);
      renderProducts(data.products || []);
    } catch (err) {
      typing.remove();
      addMessage("assistant", "Ahora mismo no puedo conectar con el asistente.");
    }
  });
});
assets/chat.css
.aia-shell {
  position: fixed;
  right: 20px;
  bottom: 20px;
  z-index: 99999;
  font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.aia-fab {
  width: 58px;
  height: 58px;
  border: 0;
  border-radius: 999px;
  cursor: pointer;
  font-size: 24px;
  box-shadow: 0 14px 40px rgba(0,0,0,.18);
}

.aia-panel {
  width: 360px;
  max-width: calc(100vw - 40px);
  height: 540px;
  max-height: calc(100vh - 100px);
  margin-top: 12px;
  display: flex;
  flex-direction: column;
  background: #fff;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 18px 60px rgba(0,0,0,.18);
  border: 1px solid rgba(0,0,0,.06);
}

.aia-panel.is-hidden {
  display: none;
}

.aia-topbar {
  padding: 14px 16px;
  background: #111827;
  color: #fff;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.aia-topbar span {
  font-size: 12px;
  opacity: .8;
}

.aia-messages {
  flex: 1;
  overflow: auto;
  padding: 14px;
  background: #f8fafc;
}

.aia-bubble {
  margin-bottom: 10px;
  padding: 10px 12px;
  border-radius: 16px;
  line-height: 1.45;
  font-size: 14px;
  max-width: 92%;
}

.aia-user {
  margin-left: auto;
  background: #dbeafe;
}

.aia-assistant {
  margin-right: auto;
  background: #fff;
  border: 1px solid #e5e7eb;
}

.aia-products {
  border-top: 1px solid #e5e7eb;
  padding: 12px;
  background: #fff;
  max-height: 180px;
  overflow: auto;
}

.aia-products-title {
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 10px;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: #6b7280;
}

.aia-product {
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 10px;
  margin-bottom: 10px;
}

.aia-product-name {
  font-weight: 700;
  margin-bottom: 4px;
}

.aia-product-meta,
.aia-product-summary {
  font-size: 13px;
  color: #4b5563;
  margin-bottom: 6px;
}

.aia-product-link {
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
}

.aia-form {
  display: flex;
  gap: 8px;
  padding: 12px;
  border-top: 1px solid #e5e7eb;
  background: #fff;
}

.aia-input {
  flex: 1;
  height: 42px;
  border-radius: 12px;
  border: 1px solid #d1d5db;
  padding: 0 12px;
}

.aia-send {
  border: 0;
  border-radius: 12px;
  padding: 0 16px;
  background: #111827;
  color: #fff;
  cursor: pointer;
}
Ollama Modelfile
Ollama’s documentation says to save it as Modelfile, use FROM for the base model, and use PARAMETER / SYSTEM to set behavior.
FROM llama3.1:8b-instruct
PARAMETER temperature 0.2

PARAMETER num_ctx 8192

PARAMETER top_p 0.9
SYSTEM """

Eres un asistente de ventas y soporte de una tienda de computadoras y accesorios.
Reglas:

Responde únicamente en español.
Solo hablas de tecnología, hardware, accesorios, precios, descuentos, comparaciones, recomendaciones, enlaces de productos y temas universales simples como fecha.
Si el usuario pregunta algo fuera de tema, redirige con amabilidad a computadoras.
Si el usuario pregunta temas sensibles, rehúsa con educación y vuelve a computadoras.
Nunca inventes precios, stock, descuentos o links.
Usa herramientas solo cuando haga falta y solo las permitidas.
Si no tienes datos del catálogo, dilo claramente.
Tono: amable, vendedor, claro, breve, natural.

"""
Then create the model:
ollama create wp-hardware-assistant -f Modelfile

ollama run wp-hardware-assistant
Ollama’s API is available locally at http://localhost:11434/api, and tool calling happens through /api/chat.

The Laravel-side contract
Your catalog API should return normalized JSON. A good contract is:
GET /products

GET /products/{id}

GET /products/compare?ids=1,2,3

GET /discounts

optionally GET /recommendations
Each response should include the canonical url, price, sale_price, discount_percent, brand, category, summary, and image.

Why this is safer than “let the model do everything”
The model is never allowed to touch the system directly. It only gets:
a limited set of tools,

catalog data,

and the right to answer in Spanish.
That is the part that keeps the assistant production-safe.

About fine-tuning
No, I would not do fine-tuning first.
For this use case, the right order is:
strict Spanish system prompt,

tool calling,

catalog/API grounding,

logged failure cases,

only then fine-tuning if you still need style or domain consistency improvements.
HEADING 3"