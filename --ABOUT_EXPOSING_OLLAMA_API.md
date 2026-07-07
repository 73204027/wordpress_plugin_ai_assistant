No extra server/IP is required for the AI unless you want to expose it separately.

For your case, the simplest path is usually:

keep the storefront on your main domain,
run Ollama on the same VPS or another private server,
connect them by internal IP, localhost, or private network.

If you are on a plan that does not allow custom services or background processes, then you will need a VPS for the AI part.

OPTIONS
Since they're using Wordpress - Hostinger:
1. Ollama
	1. if Hostinger Premium
		1. 
	2. if Hostinger Business
		1. Hostinger slot 1 => Website
		2. Hostinger slot 2 => WooCommerce
		3. Hostinger slot 3 (node.js app) => MCP
		4. Third Party VPS => Ollama
	3. if Hostinger VPS

2. Third Party AI Model Provider
	1. Hostinger slot 1 => Website
	2. Hostinger slot 2 => WooCommerce
	3. Hostinger slot 3 (node.js app) => MCP

3. Third Party AI Agent Provider
	1. Hostinger slot 1 => Website
	2. Hostinger slot 2 => WooCommerce



	3. If Hostinger Premium or Business Plan AND avaliable_domains > 0

Ollama