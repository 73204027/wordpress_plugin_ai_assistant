# HTML TO STRUCTURED DATA
For each html page/component, produce a json schema with the following structure:
// example with product page
{
  "page":"product",

  "forms":[
      {
         "action":"/?wc-ajax=add_to_cart",
         "method":"POST"
      }
  ],

  "hidden_inputs":[
      "product_id",
      "variation_id",
      "quantity",
      "attribute_pa_color",
      "attribute_pa_size"
  ],

  "json_ld":{
      ...
  },

  "html_ids":[
      "product-3158",
      "quantity"
  ],

  "html_classes":[
      "product",
      "single-product",
      "woocommerce-product-gallery",
      "variations_form"
  ],

  "data_attributes":[
      "data-product_id",
      "data-product_variations",
      "data-product_variations_hash"
  ],

  "embedded_js":[
      "wc_add_to_cart_params",
      "wc_cart_fragments_params",
      "wc_checkout_params"
  ]
}

# HAR TO STRUCTURED DATA
Extract every unique request, endpoint, payload schema, response schema, parameter names, cookie names, headers indicating plugins, ajax actions and REST namespaces.

Example of result:
{
   "ajax":[
      {
         "action":"woocommerce_add_to_cart",
         "method":"POST",
         "parameters":[
             "product_id",
             "quantity"
         ]
      }
   ],

   "rest":[
      {
         "route":"/wp/v2/product"
      }
   ],

   "responses":[
      {
          "contains":[
              "product_id",
              "variation_id",
              "price_html",
              "stock_status"
          ]
      }
   ]
}

# ELIMINATE BULLSHIT