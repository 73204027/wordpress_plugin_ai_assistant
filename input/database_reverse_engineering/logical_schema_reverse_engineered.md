Compuciber’s database is mostly standard WordPress/WooCommerce, but the “PC builder / complete computer” business logic is probably concentrated in wp_postmeta.meta_key = wooco_components on WooCommerce products of type composite.

So your plugin should model three layers:

Layer 1: Normal WooCommerce product data
Layer 2: Taxonomy/attribute/brand/category model
Layer 3: WPC Composite Products component graph

That third layer is the real business-specific schema.