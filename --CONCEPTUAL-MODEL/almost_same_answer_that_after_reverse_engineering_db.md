### Esquema lógico que sí te puedo proponer ya

De forma práctica, para un agente IA de atención al cliente, el núcleo de lectura sería este:

- `wp_posts`: productos, páginas, adjuntos y, según el caso, pedidos antiguos.
- `wp_postmeta`: precio, stock, SKU, imágenes, oferta, atributos y configuración del producto.
- `wp_terms` + `wp_term_taxonomy` + `wp_term_relationships`: categorías, etiquetas, atributos, shipping classes.
- `wp_comments` + `wp_commentmeta`: reseñas de producto.
- `wp_users` + `wp_usermeta`: clientes.
- `wp_options`: ajustes de tienda, páginas de WooCommerce, moneda, impuestos, pasarelas, envío, WhatsApp, plugins y tema.
- Si HPOS está activo: `wp_wc_orders`, `wp_wc_order_addresses`, `wp_wc_order_operational_data`, `wp_wc_orders_meta`.
- `wp_woocommerce_sessions`: carritos/sesiones activas.
- `wp_woocommerce_payment_tokens` y `wp_woocommerce_payment_tokenmeta`: tokens de pago, si la pasarela los usa.
- 