*nota: el texto tachado significa que no es válido y que fue descartado*
USE CASES:
- sq - query
	- consult for products based on a certain estimate
	- consult for delivery status (order)
	- ~~reseñas~~
- ~~sql - make actions~~
	- ~~create a new order~~
	- ~~update an order~~
	- ~~delete an order, cancel an order~~



**WORFLOW**
	1. I ask for specs of each product, I ask assistant to compare each product
	2. I ask for discounts or cupouns active right now
	3. I say, 'Ok, so I will go for this bundle/option'
	4. AI agent chat offers a clickable button/link to add to cart the products of interest => foeach product in bundle: AddToCart(product)
	5. User is in view cart. 
	6. User presses Purchase. *Important: user is the one who agrees and ends building the Cart/Order form. AI simply help loading the products of interest, but didn't create an order, nor submit any request of type POST, PATCH, UPDATE or DELETE to database or server.*
	7. I ask assistant for my order status


So, based on that worflow, I must pinpoint which skills the ai agent needs.
## AGENT CAPABILITIES (SKILLS)
- SQL (SELECT)
	- *Entities:* Product (specs, price, category, description, additional info), Category, Order (WHERE id_user = this.id_user AND order_state = active) (arrival_date, order_status, delivery_address)
- Add to cart (this is not about: CREATE order, or UPDATE cart, this is about loading the products in the HTML form, and, if user agrees, user presses *order*)


- SKILLS
	- 

















- sql - query (SELECT)
	- product
	- order
- sql - make actions
	- CREATE
	- UPDATE orders SET delivery_address = 'New Address' WHERE id_order = 27


## CHATGPT ANSWERED
Tu análisis va en la dirección correcta, pero **está incompleto** y hay una corrección importante: para ese flujo, el agente no solo necesita “pedidos y estados”, “checkout”, “cupones” e “impuestos”; también necesita **catálogo, stock, variaciones, imágenes, carrito/sesión y datos de cliente/usuario**.

### Validación de tu análisis
Lo que faltó:

- **productos**: nombre, SKU, descripción, precio, oferta, stock, estado, marca/atributos.
- **categorías**: navegación y recomendación.
- **variaciones / bundles / componentes**: en tu caso esto es muy importante porque el sitio muestra productos compuestos.
- **carrito / sesión**: para “AddToCart” y recuperación del carrito.
- **clientes/usuarios**: para ver pedidos del usuario correcto.
- **envío / métodos de entrega**: si el agente debe explicar costo y ETA.
- **medios/imágenes**: para mostrar el producto correcto al comparar o recomendar.
- **configuración de pagos**: no para cobrar, sino para explicar opciones de pago y restricciones.
- **páginas informativas**: políticas, garantía, cambios, contacto; útiles para atención al cliente, aunque no para el motor de compra.

### Lo que sí y lo que no debe hacer el agente

Tu workflow sugiere algo clave: **el agente ayuda a decidir y preparar**, pero **el usuario confirma la compra**. Eso cambia mucho el diseño.

Por tanto:

- **Sí necesita lectura SQL / lectura de datos**.
- **Sí necesita una acción de carrito**.
- **No necesita crear/editar/eliminar pedidos** si el usuario finaliza la compra manualmente.
- **No necesita escribir SQL directo** para órdenes si el flujo es “solo ayudar a cargar el carrito”.
- La acción “AddToCart(product)” **no debería modelarse como SQL CREATE/UPDATE**; normalmente es una acción de carrito/sesión o una llamada a la API/endpoint del ecommerce.

### Requerimiento final corregido
**Capacidades necesarias del agente IA**
1. **Lectura de catálogo**
    - producto: título, SKU, descripción, precio normal, precio oferta, stock, disponibilidad, imágenes, atributos, categorías, compatibilidad y componentes.
    - categoría: árbol jerárquico y navegación.
    - bundle/producto compuesto: componentes y opciones seleccionables.
2. **Lectura comercial**
    - cupones activos.
    - descuentos, precios rebajados y reglas de promoción.
    - impuestos y cálculo de totales.
    - métodos de envío y tiempos estimados.
3. **Lectura de cliente/pedido**
    - pedidos del usuario autenticado. (ej: estado del pedido, dirección de entrega, historial, mínimo de compra)
4. **Acción de carrito**
    - añadir productos al carrito.
    - modificar cantidades.
    - ~~quitar productos del carrito~~.
    - consultar resumen del carrito.
5. **No requerido**
    - crear pedido directo desde IA.
    - actualizar pedido.
    - cancelar o borrar pedido desde IA.
    - escritura directa en base de datos para compras.

### Sobre “checkout”

Tu duda aquí es válida: **checkout sí importa**, pero más como **flujo funcional** que como tabla aislada. El agente necesita conocer qué campos pide el checkout y cómo se resuelve el total final, pero no necesariamente manipular una “tabla checkout” como entidad separada.

### Mi recomendación final

Si el objetivo es un asistente de atención al cliente que ayude a elegir y preparar compra, el alcance mínimo correcto es:

**SELECT/READ sobre productos, categorías, bundles, stock, precios, cupones, impuestos, envío, pedidos y usuario + acción de carrito.**