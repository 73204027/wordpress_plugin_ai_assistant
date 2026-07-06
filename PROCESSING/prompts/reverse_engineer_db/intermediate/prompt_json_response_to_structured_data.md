You are an expert in WordPress, WooCommerce internals and database reverse engineering.

Objective:
Transform the following REST API response into a compact, structured JSON optimized for inferring the underlying WordPress/WooCommerce database schema.

The final consumer of this JSON is another LLM whose only task is to reconstruct the database logical model (tables, relationships, post types, taxonomies, metadata, plugin tables, business logic, SQL DDL, etc.).

Therefore:

- Keep ONLY information that provides evidence about the database structure.
- Remove SEO content, HTML, descriptions, localized labels, links, breadcrumbs, social metadata, canonical URLs, images, OpenGraph, Twitter metadata, JSON-LD, etc.
- Remove duplicated information.
- Remove fields that can be deterministically inferred from WordPress defaults unless they reveal customization.
- Keep plugin-specific information.
- Keep every custom post type, taxonomy, REST base, namespace, archive behavior and any evidence of custom plugins.
- Infer plugin names when possible from post types or namespaces.
- Preserve uncertainty when something is inferred rather than explicit.

Return ONLY valid JSON.

Structure data to fill this exact schema (only what current data allows to fill):

{
  "wordpress": {
    "wp_json": {},
    "rest_routes": [],
    "detected_plugins": [],
    "theme": "",
    "page_builder": "",
    "headers": {}
  },

  "woocommerce": {
    "product_examples": [],
    "category_examples": [],
    "attribute_examples": [],
    "variation_examples": [],
    "cart_payloads": [],
    "checkout_payloads": []
  },

  "network": {
    "ajax_calls": [],
    "rest_calls": [],
    "json_responses": []
  },

  "reverse_engineering": {
    "json_ld": [],
    "forms": [],
    "hidden_inputs": [],
    "html_ids": [],
    "html_classes": [],
    "data_attributes": [],
    "embedded_js_objects": [],
    "inline_configuration": []
  },

  "taxonomy_examples": {
    "product_cat": [],
    "product_tag": [],
    "brands": [],
    "attributes": []
  },

  "urls": {
    "sample_product_urls": [],
    "sample_category_urls": [],
    "sample_account_urls": [],
    "sample_checkout_urls": []
  }
}

Additional instructions:

- Never include HTML.
- Never include JSON-LD.
- Never include SEO metadata.
- Never include localized names unless they reveal functionality.
- Merge duplicate structures.
- Compress repeated patterns.
- Prioritize information that helps infer:
    - wp_posts usage
    - wp_postmeta usage
    - wp_terms
    - wp_term_taxonomy
    - wp_term_relationships
    - wp_options
    - WooCommerce entities
    - custom plugin tables
    - custom post types
    - custom taxonomies
    - metadata keys
    - plugin architecture

Input: