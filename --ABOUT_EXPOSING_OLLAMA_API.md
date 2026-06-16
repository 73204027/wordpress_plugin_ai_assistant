No extra public domain is required for the AI unless you want to expose it separately.

For your case, the simplest path is usually:

keep the storefront on your main domain,
run Ollama on the same VPS or another private server,
connect them by internal IP, localhost, or private network.

If you are on a plan that does not allow custom services or background processes, then you will need a VPS for the AI part.