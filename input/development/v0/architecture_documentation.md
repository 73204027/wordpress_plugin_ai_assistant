# Software Architecture Specification

**Document Target:** Engineering & Development Team

**System Name:** Custom E-Commerce AI Agent WordPress Plugin (`wc-ai-agent`)

**Architecture Pattern:** Decoupled Stateless Client / Edge-Stream-Through Backend / Private AI Inference Node

---

## 1. Panoramic Architecture View

The system is designed as a tri-tier architecture engineered to operate within the strict execution constraints of a shared hosting environment (Hostinger Business/Premium) while delivering real-time, low-latency AI responses. It decouples heavy compute processes from the e-commerce infrastructure by offloading all Large Language Model (LLM) inference tasks to a dedicated, secured external Virtual Private Server (VPS).

```
[ Client Browser ] 
       │ (Persistent Connection via Fetch / ReadableStream)
       ▼
[ Hostinger Shared Web Server (WordPress + WooCommerce PHP-FPM) ]
       │ (Pass-Through Server-Sent Events Token Streaming)
       ▼
[ External Cloud VPS (Nginx Reverse Proxy + Secured Ollama Instance) ]

```

### Core Architectural Principles

* **Zero Local Inference Compute:** No AI models or complex computational operations execute on the Hostinger server.
* **Non-Buffered Direct Pass-Through:** The WordPress backend acts strictly as a secure proxy, executing a non-buffered read/write loop between the external VPS and the client browser.
* **Cryptographic State Isolation:** Clients are treated as untrusted actors. The conversational state is validated and maintained server-side using stateless identifiers, preventing payload manipulation.

---

## 2. Component Breakdown Matrix

| Component Tier | Technical Stack | Primary Architectural Responsibility | Resource Profile |
| --- | --- | --- | --- |
| **Frontend UI Widget** | Vanilla JS (ES6+) / Web Components | Mount shadow-DOM chat shell; capture inputs; manage client-side state hooks; process incoming HTTP `ReadableStream` chunks. | Low (Client CPU bound) |
| **E-Commerce Core (Hostinger)** | WordPress REST API / PHP-FPM / WooCommerce Core | Authenticate requests via nonces; resolve stateless session tokens; strip client-injected system instructions; enforce worker-protection limits; proxy streaming data. | Medium (Constrained by PHP worker availability pool) |
| **AI Inference Node (External VPS)** | Linux OS / Nginx / Ollama / (Optional FastAPI Wrapper) | Secure local model execution (e.g., Llama-3 / Qwen models); handle high-concurrency token processing; output OpenAI-compatible streaming JSON lines. | High (GPU or High-Core Compute bound) |

---

## 3. Component Interaction Workflow

```
[Client Widget]           [WP REST Endpoint]        [Nginx / VPS Proxy]        [Ollama Engine]
       │                          │                          │                        │
       │─── 1. Send Message ─────>│                          │                        │
       │    (Session ID + Text)   │                          │                        │
       │                          │─── 2. Fetch History & ──>│                        │
       │                          │    Inject Sys Prompt     │                        │
       │                          │                          │─── 3. Stream Request ─>│
       │                          │                          │    (v1/chat/completions)│
       │                          │                          │                        │
       │                          │                          │<── 4. Emit Token ──────│
       │                          │<── 5. Instant Flush ─────│                        │
       │<── 6. Render Token ──────│    (No Buffering)        │                        │

```

### Detailed Sequence Phase Execution

1. **Initiation:** The client UI dispatches an asynchronous HTTP POST request to a custom registered WordPress REST API route (`/wp-json/wc-ai-agent/v1/chat`). The request contains only the raw user text input and a cryptographically safe session token identifier stored in the browser's storage.
2. **Ingress Guarding & Assembly:** The WordPress backend intercepts the request, runs a security clearance check, fetches the verified chat history array from server-side memory, appends the latest user message, and attaches the immutable system ruleset.
3. **Upstream Transport:** The WordPress backend opens a persistent cURL connection to the external VPS endpoint using an encrypted authorization token in the headers.
4. **Downstream Execution:** The external VPS processes the prompt through the Ollama engine. As the very first token is computed, it is pushed back down to the Nginx layer.
5. **Pass-Through Pipe:** The WordPress PHP worker intercepts the chunk immediately through an active write function callback, unbuffers the web server output layer, and mirrors the data slice to the client browser via Server-Sent Events (SSE protocol).
6. **Termination:** The moment the Ollama engine emits the completion stop token, the cURL session is closed by PHP, the updated conversation history string is saved back to the server cache, and the PHP worker process is instantly released back into the Hostinger execution pool.

---

## 4. Session & State Management for Unauthenticated Users

To accommodate users who are browsing anonymously without an active WordPress account, the plugin operates a stateless client identity lifecycle matched with a stateful server-side caching layer.

### Identity Lifecycle Rules

* **Token Generation:** Upon initialization of the JavaScript widget on the e-commerce shopfront, the frontend queries local browser storage for a key named `wc_ai_anonymous_sec_token`. If absent, the browser generates a cryptographically secure UUID version 4 string.
* **Verification:** This UUID token accompanies every API request payload within a custom HTTP request header string (`X-AI-Agent-Session-Token`). The PHP backend validates the string format against a strict UUID regex layout (`^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$`). Any incoming session identifier failing validation drops the connection instantly with an HTTP 400 Bad Request error.

### Server-Side Persistence Model

* **The Cache Mechanism:** Because MySQL database queries incur heavy input/output disk read write costs on shared servers, conversation histories must not be written to standard WordPress database rows during chat turns.
* **Transient Storage Engine:** The plugin saves the compiled JSON transaction object into the **WordPress Transients API**, which interfaces automatically with memory caching layers if present, or utilizes clean database key-value lookups as a fallback.
* **Naming Structure:** Keys are indexed exactly as `_transient_ai_sess_[Validated_UUID]`.
* **TTL (Time To Live Garbage Collection):** Every session object is written with an explicit expiration horizon of **7200 seconds (2 hours)**. Every sequential turn made by the specific user shifts the expiration window forward by another 2 hours. Abandoned chat sessions are purged automatically by core WordPress garbage collection architecture, maintaining zero long-term database bloat.

---

## 5. Security Architecture & Zero-Trust Verification Guardrails

The architecture follows a zero-trust structural implementation model. The client browser is assumed to be fully compromised and under active control of potentially malicious entities.

### Prompt Injection and Payload Tampering Mitigation

* **Role-Based Filtering Control:** The backend completely ignores any system roles or formatting commands passed inside the raw network request from the frontend browser. The incoming JSON layout parsing routine forces a structural map that transforms data entries strictly into standard string contents bounded to the `user` label context.
* **Immutable System Instruction Injections:** The instructions guiding the business logic, product data policies, and conversational guardrails of the AI agent reside exclusively within safe PHP file properties inside the Hostinger server directory. The code appends this data array block at index zero of the payload array stack *only after* validation processes pass, right before hitting the upstream cURL execution block.
* **Memory-Limit Bounding (DoS Defense):** To counter Denial of Service style payload attacks meant to overflow the system memory allocation footprint or drive up upstream execution bills, the backend enforces a max historical array length boundary. If the count of previous dialogue turns fetched from the server-side transient object exceeds **10 total iterations**, the oldest index items are permanently removed from the array pointer before transport processing.

### External AI VPS Access Layer Security

Ollama’s native service engine distributes unauthenticated, open HTTP access on port `11434` by default. Exposing this port to the raw internet is a catastrophic vulnerability. The external VPS architecture locks this down via a hardened proxy pipeline:

```
[ Hostinger Server ] ──> HTTP POST (Port 443) ──> [ Nginx Reverse Proxy ] ──> Unix Socket/Localhost ──> [ Ollama API ]

```

* **Port-Level Isolation:** The VPS firewall rules block all incoming traffic requests to port `11434` from external IP boundaries. The service is bound strictly to `123.0.0.1` (localhost).
* **Nginx Token Layer Gateway:** Access control is managed through an Nginx reverse proxy endpoint routing securely over port `443` (SSL/TLS). Nginx evaluates an explicit authorization check block against a custom, complex server token variable sequence (`X-Inference-Secret-Key`).
* **Format Uniformity:** To guarantee optimal maintainability, the API layer on the VPS utilizes Ollama's built-in OpenAI compatibility routes (`/v1/chat/completions`). This ensures that if the private VPS goes offline or runs out of capacity during peak holiday seasons, switching the plugin backend to a public alternative provider (OpenAI, DeepSeek, Anthropic) requires updating only a single environment URL string without rewriting any internal processing code blocks.

---

## 6. Hostinger Resource Preservation Protocols (The Core Safeguards)

To preserve performance parameters across the online storefront and guarantee that checkout queues do not freeze under heavy chatbot interaction cycles, the plugin backend applies strict resource preservation caps.

### The Math of PHP Worker Depletion

Hostinger Business tier environments support a concurrent ceiling limit of **60 active PHP workers**. If a chat thread holds an active process link open for an extended duration, that specific worker thread is removed from standard page-load servicing duties. To prevent complete worker depletion under concurrent usage spikes, the plugin enforces these structural configurations:

```
                   [ Incoming HTTP Connection ]
                                │
                  Is Worker Free? (Max 60 Pool)
                     ├── YES ──> Check Limits
                     └── NO  ──> Queue / Drop 503
                                │
               ┌────────────────┴────────────────┐
               ▼                                 ▼
   CURLOPT_CONNECTTIMEOUT             CURLOPT_TIMEOUT
       (Max 3 Seconds)                (Max 8 Seconds)

```

### Operational Parameter Configurations

* **Forced Streaming Configuration (`CURLOPT_WRITEFUNCTION`):** The PHP runtime environment must never store the compiled text output stream in internal runtime variable structures before echoing it to the client. The system script configures a native stream callback buffer routine that processes chunks bit by bit as they hit the server interface, instantly releasing network blocks using `ob_flush()` and `flush()` logic operations.
* **Connection Timeout Bound (`CURLOPT_CONNECTTIMEOUT`):** Set to exactly **3 seconds**. If the private VPS AI node does not acknowledge connection request frames within this period, the execution drops immediately, protecting the worker from stalling.
* **Absolute Execution Lifespan Cap (`CURLOPT_TIMEOUT`):** Set to a maximum ceiling limit of **8 seconds**. If an LLM inference cycle stalls on the external machine due to hardware queuing or complex token calculations, the Hostinger process terminates the connection forcefully at the 8-second mark, instantly freeing the active PHP worker process back into the operational pool.
* **Max Token Constraints:** The outbox data structure payload payload restricts the generation length metric limit parameter (`max_tokens`) to **150 tokens max**. This design constraint ensures that responses are punchy, direct, and fast, keeping typical thread lifetimes down below 2.5 seconds total processing runtimes.

---

## 7. Developer Implementation Blueprint (Technical Directives for Coding)

Developers writing the core functional layout must stick directly to the following execution code designs.

### Frontend Client Strategy (JavaScript)

* Do not include external frameworks or heavyweight UI scripts. Write the entire layout inside a native JavaScript Class architecture or standard Web Component module.
* Utilize the modern browser `fetch()` mechanism combined with a text decoder routine to parse the text data stream line by line.

```javascript
// Implementation Logic for Stream Reading Flow
const response = await fetch('/wp-json/wc-ai-agent/v1/chat', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': WCAiAgentSettings.nonce,
        'X-AI-Agent-Session-Token': sessionTokenUUID
    },
    body: JSON.stringify({ message: userInputText })
});

const reader = response.body.getReader();
const decoder = new TextDecoder("utf-8");

while (true) {
    const { value, done } = await reader.read();
    if (done) break;
    const chunkString = decoder.decode(value, { stream: true });
    // Core Engine Rule: Immediately inject chunkString tokens into the Shadow DOM Chat Window node
}

```

### Backend WordPress Architecture Logic (PHP)

#### Route Registration Phase

Hook into the standard execution pipeline using the `rest_api_init` action hook filter. Ensure that the endpoint remains fully open for access by unauthenticated web browsers.

```php
add_action('rest_api_init', function () {
    register_rest_route('wc-ai-agent/v1', '/chat', array(
        'methods'             => 'POST',
        'callback'            => 'wc_ai_agent_handle_streaming_chat',
        'permission_callback' => '__return_true', // Intentionally open for anonymous store visitors
    ));
});

```

#### Stream Controller Processing Block

Inside the endpoint controller implementation callback logic block, headers must be initialized immediately to drop buffering behavior across servers or reverse-proxy caching utilities like Cloudflare or LiteSpeed engines.

```php
function wc_ai_agent_handle_streaming_chat(WP_REST_Request $request) {
    // 1. Sanitize incoming inputs and validate session tokens matching UUID specifications
    // 2. Fetch or initialize the verified system instruction prompt maps and transient histories
    
    // 3. Force establish explicit HTTP Streaming Delivery Headers
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // Crucial configuration directive to bypass Nginx / LiteSpeed buffer holds
    
    // 4. Initialize cURL stream transport array blocks
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://vps-node-endpoint.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Prevent cURL from capturing the response string inside variables
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    
    // Inject the inline callback function to pipe individual pieces of data down to the user's browser real-time
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $dataChunk) {
        echo $dataChunk;
        ob_flush();
        flush(); // Emits chunk data right down the live pipe network connection line
        return strlen($dataChunk);
    });
    
    // Compile data elements into payload arrays, pass headers containing the secret API access keys
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($compiledPayloadData));
    
    curl_exec($ch);
    curl_close($ch);
    
    // 5. Update structural histories in the server transients storage cache before termination
    exit; // Force shutdown standard WP rendering pipeline routines to preserve data integrity
}

```

### WooCommerce Product Data Injection Strategy (Read-Only Context)

* **The Constraint:** Do not supply live product data objects directly into the foundational system prompt structure, as doing so will instantly cause context window exhaustion.
* **The Execution Blueprint:** Implement a strict **Read-Only Semantic Search or Keyword Hook Router**. When a user types a query, the backend intercepts it, runs a localized quick text match using a clean WooCommerce database index query (`WC_Product_Query`), transforms the results into a compact, text-only inventory data block (e.g., `ID: 102 - Name: Leather Jacket - Price: $89 - Stock: 4 units`), and attaches this specific block to the payload context before sending it to the external VPS.
* This approach ensures that the model has access to accurate, up-to-date business records while remaining completely isolated from any transactional execution systems. It maintains structural safety, high speed, and optimal cost control.