(() => {
  "use strict";

  const SETTINGS = window.CCAI_SETTINGS || null;

  if (!SETTINGS) return;

  const STORAGE_KEY = "ccai_session_token_v1";

  const safeText = (value) => String(value ?? "");

  const debug = (...args) => {
    if (SETTINGS?.debug && typeof console !== "undefined") {
      console.warn("[CCAI]", ...args);
    }
  };

  const getCrypto = () =>
    typeof globalThis !== "undefined" && globalThis.crypto ? globalThis.crypto : null;

  const uuidV4Fallback = () => {
    const cryptoObj = getCrypto();
    const bytes = new Uint8Array(16);

    if (cryptoObj && typeof cryptoObj.getRandomValues === "function") {
      cryptoObj.getRandomValues(bytes);
    } else {
      for (let i = 0; i < bytes.length; i += 1) {
        bytes[i] = Math.floor(Math.random() * 256);
      }
    }

    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    const hex = [...bytes].map((b) => b.toString(16).padStart(2, "0"));

    return [
      hex.slice(0, 4).join(""),
      hex.slice(4, 6).join(""),
      hex.slice(6, 8).join(""),
      hex.slice(8, 10).join(""),
      hex.slice(10, 16).join(""),
    ].join("-");
  };

  const isUuidV4 = (value) =>
    /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/.test(
      String(value || "").toLowerCase()
    );

  const getSessionToken = () => {
    try {
      const existing = localStorage.getItem(STORAGE_KEY);

      if (isUuidV4(existing)) {
        return existing.toLowerCase();
      }

      const cryptoObj = getCrypto();

      const token =
        cryptoObj && typeof cryptoObj.randomUUID === "function"
          ? cryptoObj.randomUUID()
          : uuidV4Fallback();

      localStorage.setItem(STORAGE_KEY, token);

      return token.toLowerCase();
    } catch (_) {
      const cryptoObj = getCrypto();

      return cryptoObj && typeof cryptoObj.randomUUID === "function"
        ? cryptoObj.randomUUID().toLowerCase()
        : uuidV4Fallback();
    }
  };

  class CompuciberChat {
    constructor(root) {
      this.root = root;
      this.fab = root.querySelector(".ccai-fab");
      this.panel = root.querySelector(".ccai-panel");
      this.closeButton = root.querySelector(".ccai-close");
      this.messagesEl = root.querySelector(".ccai-messages");
      this.productsEl = root.querySelector(".ccai-products");
      this.form = root.querySelector(".ccai-form");
      this.input = root.querySelector(".ccai-input");
      this.sendButton = root.querySelector(".ccai-send");

      this.sessionToken = getSessionToken();
      this.isBusy = false;
      this.abortController = null;
      this.hasGreeted = false;

      this.bind();
    }

    bind() {
      if (
        !this.root ||
        !this.fab ||
        !this.panel ||
        !this.form ||
        !this.input ||
        !this.sendButton ||
        !this.messagesEl ||
        !this.productsEl
      ) {
        debug("Chat DOM incomplete; widget not mounted.");
        return;
      }

      this.fab.addEventListener("click", () => this.togglePanel());
      this.closeButton?.addEventListener("click", () => this.closePanel());

      this.form.addEventListener("submit", (event) => {
        event.preventDefault();
        this.submit();
      });

      this.input.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
          this.closePanel();
        }
      });
    }

    togglePanel() {
      const isHidden = this.panel.classList.toggle("is-hidden");
      this.fab.setAttribute("aria-expanded", String(!isHidden));

      if (!isHidden) {
        this.greetOnce();
        window.setTimeout(() => this.input?.focus(), 20);
        this.scrollBottom();
      }
    }

    closePanel() {
      this.panel.classList.add("is-hidden");
      this.fab.setAttribute("aria-expanded", "false");
    }

    greetOnce() {
      if (this.hasGreeted) return;

      this.addMessage("assistant", SETTINGS.i18n?.hello || "Hola, puedo ayudarte con productos y soporte.");
      this.hasGreeted = true;
    }

    setBusy(value) {
      this.isBusy = Boolean(value);

      if (this.input) {
        this.input.disabled = this.isBusy;
      }

      if (this.sendButton) {
        this.sendButton.disabled = this.isBusy;
      }

      this.root?.classList.toggle("is-busy", this.isBusy);
    }

    scrollBottom() {
      if (!this.messagesEl) return;
      this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
    }

    addMessage(role, text) {
      const bubble = document.createElement("div");
      bubble.className = `ccai-bubble ccai-${role}`;

      const body = document.createElement("div");
      body.className = "ccai-text";
      body.textContent = safeText(text);

      bubble.appendChild(body);
      this.messagesEl.appendChild(bubble);
      this.scrollBottom();

      return body;
    }

    appendToMessage(messageBodyEl, token) {
      if (!messageBodyEl) return;
      messageBodyEl.textContent += safeText(token);
      this.scrollBottom();
    }

    renderProducts(items) {
      this.productsEl.innerHTML = "";

      if (!Array.isArray(items) || items.length === 0) {
        this.productsEl.classList.remove("has-products");
        return;
      }

      this.productsEl.classList.add("has-products");

      const title = document.createElement("div");
      title.className = "ccai-products-title";
      title.textContent = "Productos relacionados";
      this.productsEl.appendChild(title);

      for (const product of items.slice(0, 8)) {
        const card = document.createElement("article");
        card.className = "ccai-product";

        if (product.image) {
          const img = document.createElement("img");
          img.className = "ccai-product-image";
          img.alt = product.name || "Producto";
          img.loading = "lazy";
          img.src = product.image;
          card.appendChild(img);
        }

        const body = document.createElement("div");
        body.className = "ccai-product-body";

        const name = document.createElement("div");
        name.className = "ccai-product-name";
        name.textContent = product.name || "Producto";
        body.appendChild(name);

        const meta = document.createElement("div");
        meta.className = "ccai-product-meta";

        const price = product.sale_price || product.price || "";
        const parts = [
          product.sku ? `SKU: ${product.sku}` : "",
          product.type ? `Tipo: ${product.type}` : "",
          price ? `Precio: ${price} ${product.currency || ""}` : "",
          product.stock_status ? `Stock: ${product.stock_status}` : "",
        ].filter(Boolean);

        meta.textContent = parts.join(" · ");
        body.appendChild(meta);

        if (Array.isArray(product.attributes) && product.attributes.length) {
          const attrs = document.createElement("div");
          attrs.className = "ccai-product-attrs";
          attrs.textContent = product.attributes.slice(0, 3).join(" · ");
          body.appendChild(attrs);
        }

        if (product.summary) {
          const summary = document.createElement("div");
          summary.className = "ccai-product-summary";
          summary.textContent = product.summary;
          body.appendChild(summary);
        }

        const actions = document.createElement("div");
        actions.className = "ccai-product-actions";

        if (product.url) {
          const viewLink = document.createElement("a");
          viewLink.className = "ccai-product-link";
          viewLink.href = product.url;
          viewLink.target = "_blank";
          viewLink.rel = "noopener noreferrer";
          viewLink.textContent = "Ver producto";
          actions.appendChild(viewLink);
        }

        if (product.cart_url) {
          const cartLink = document.createElement("a");
          cartLink.className = "ccai-product-cart";
          cartLink.href = product.cart_url;
          cartLink.rel = "nofollow";
          cartLink.textContent = "Añadir al carrito";
          actions.appendChild(cartLink);
        }

        body.appendChild(actions);
        card.appendChild(body);
        this.productsEl.appendChild(card);
      }
    }

    parseSseBlock(block) {
      const lines = block.split(/\r?\n/);
      let event = "message";
      const dataLines = [];

      for (const rawLine of lines) {
        const line = rawLine.trimEnd();

        if (line.startsWith("event:")) {
          event = line.slice(6).trim();
        } else if (line.startsWith("data:")) {
          dataLines.push(line.slice(5).trimStart());
        }
      }

      if (!dataLines.length) {
        return null;
      }

      const dataRaw = dataLines.join("\n");

      try {
        return {
          event,
          data: JSON.parse(dataRaw),
        };
      } catch (_) {
        return {
          event,
          data: { raw: dataRaw },
        };
      }
    }

    async readSseStream(response, assistantBody) {
      if (!response.body || typeof response.body.getReader !== "function") {
        throw new Error("ReadableStream is not supported by this browser.");
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder("utf-8");
      let buffer = "";

      while (true) {
        const { value, done } = await reader.read();

        if (done) {
          break;
        }

        buffer += decoder.decode(value, { stream: true });

        const blocks = buffer.split(/\n\n/);
        buffer = blocks.pop() || "";

        for (const block of blocks) {
          const parsed = this.parseSseBlock(block);

          if (!parsed) continue;

          if (parsed.event === "token") {
            this.appendToMessage(assistantBody, parsed.data?.text || "");
          } else if (parsed.event === "products") {
            this.renderProducts(parsed.data?.items || []);
          } else if (parsed.event === "error") {
            debug("SSE error event", parsed.data);
            assistantBody.textContent = SETTINGS.i18n?.error || "Ahora mismo no puedo conectar con el asistente. Intenta nuevamente en unos segundos.";
            return;
          } else if (parsed.event === "done") {
            return;
          }
        }
      }
    }

    async submit() {
      if (this.isBusy) return;

      const text = String(this.input.value || "").trim();

      if (!text) return;

      if (text.length > 700) {
        this.addMessage("assistant", "Tu mensaje es muy largo. Resume tu consulta.");
        return;
      }

      this.input.value = "";
      this.addMessage("user", text);
      this.renderProducts([]);

      const assistantBody = this.addMessage("assistant", "");
      this.setBusy(true);

      this.abortController = new AbortController();

      const timeout = window.setTimeout(() => {
        try {
          this.abortController.abort();
        } catch (_) {}
      }, 18000);

      try {
        const response = await fetch(SETTINGS.endpoint, {
          method: "POST",
          credentials: "same-origin",
          signal: this.abortController.signal,
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": SETTINGS.nonce,
            [SETTINGS.sessionHeader || "x-ai-agent-session-token"]: this.sessionToken,
          },
          body: JSON.stringify({
            message: text,
          }),
        });

        if (!response.ok) {
          let payload = null;

          try {
            payload = await response.json();
          } catch (_) {}

          const fallback =
            response.status === 429
              ? SETTINGS.i18n?.rateLimited
              : SETTINGS.i18n?.error;

          assistantBody.textContent = payload?.reply || fallback || "No pude responder en este momento.";
          return;
        }

        await this.readSseStream(response, assistantBody);

        if (!assistantBody.textContent.trim()) {
          assistantBody.textContent =
            "No pude generar una respuesta en este momento. Intenta con una consulta más específica.";
        }
      } catch (error) {
        debug("Chat request failed", error);

        if (!assistantBody.textContent.trim()) {
          assistantBody.textContent =
            error?.name === "AbortError"
              ? "El asistente tardó demasiado. Intenta nuevamente en unos segundos."
              : SETTINGS.i18n?.error || "Ahora mismo no puedo conectar con el asistente. Intenta nuevamente en unos segundos.";
        }
      } finally {
        window.clearTimeout(timeout);
        this.abortController = null;
        this.setBusy(false);
        this.input.focus();
      }
    }
  }

  const init = () => {
    document.querySelectorAll("[data-ccai-chat]").forEach((root) => {
      if (root.dataset.ccaiMounted === "1") return;

      root.dataset.ccaiMounted = "1";
      new CompuciberChat(root);
    });
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();