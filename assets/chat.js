document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector("[data-ai-chat]");
  if (!root || typeof AI_CHAT === "undefined") return;

  const fab = root.querySelector(".ai-fab");
  const panel = root.querySelector(".ai-panel");
  const messagesEl = root.querySelector(".ai-messages");
  const productsEl = root.querySelector(".ai-products");
  const form = root.querySelector(".ai-form");
  const input = root.querySelector(".ai-input");

  const history = [];

  const scrollBottom = () => {
    messagesEl.scrollTop = messagesEl.scrollHeight;
  };

  const addMessage = (role, text) => {
    history.push({ role, content: text });

    const bubble = document.createElement("div");
    bubble.className = `ai-bubble ai-${role}`;

    const body = document.createElement("div");
    body.className = "ai-text";
    body.textContent = text;

    bubble.appendChild(body);
    messagesEl.appendChild(bubble);
    scrollBottom();
  };

  const renderProducts = (items) => {
    productsEl.innerHTML = "";
    if (!items || !items.length) return;

    const title = document.createElement("div");
    title.className = "ai-products-title";
    title.textContent = "Resultados";
    productsEl.appendChild(title);

    items.forEach((p) => {
      const card = document.createElement("div");
      card.className = "ai-product";

      const name = document.createElement("div");
      name.className = "ai-product-name";
      name.textContent = p.name || "Producto";

      const meta = document.createElement("div");
      meta.className = "ai-product-meta";
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
        summary.className = "ai-product-summary";
        summary.textContent = p.summary;
        card.appendChild(summary);
      }

      if (p.url) {
        const link = document.createElement("a");
        link.className = "ai-product-link";
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
      addMessage("assistant", `Hola, soy ${AI_CHAT.assistantName}. Puedo ayudarte con productos, precios y descuentos.`);
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
    typing.className = "ai-bubble ai-assistant";
    typing.textContent = "Escribiendo...";
    messagesEl.appendChild(typing);
    scrollBottom();

    try {
      const response = await fetch(AI_CHAT.endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-AI-Nonce": AI_CHAT.nonce,
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