from playwright.sync_api import sync_playwright
import json
import logging
from typing import Dict, Any

logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')

def extract_wide_net_data(product_url: str) -> Dict[str, Any]:
    extracted_data = {
        "all_prices_found": [],
        "product_description_area": None,
        "elementor_text_blocks": [],
        "error": None
    }

    with sync_playwright() as p:
        # Utilizing the native browser bypass we established
        try:
            browser = p.chromium.launch(headless=True, channel="chrome")
        except Exception as e:
            logging.error(f"Failed to launch Chrome channel. Ensure Chrome is installed. {e}")
            extracted_data["error"] = str(e)
            return extracted_data

        page = browser.new_page()

        try:
            logging.info(f"Loading URL and initiating 5-second hard wait...")
            page.goto(product_url, wait_until="domcontentloaded", timeout=30000)
            
            # Blunt-force wait to allow delayed pricing scripts to execute
            page.wait_for_timeout(5000)
            
            # 1. Sweep for ALL price elements on the page, not just the first one
            price_locators = page.locator('.price').all()
            for p_loc in price_locators:
                if p_loc.is_visible():
                    extracted_data["all_prices_found"].append(p_loc.inner_text().strip())

            # 2. Sweep the WooCommerce Custom Tabs (Where Woodmart usually hides specs)
            tabs_locator = page.locator('.woocommerce-tabs')
            if tabs_locator.is_visible():
                extracted_data["product_description_area"] = tabs_locator.inner_text().strip()

            # 3. Sweep all Elementor text widgets (If specs are manually typed out)
            text_widgets = page.locator('.elementor-widget-text-editor').all()
            for widget in text_widgets:
                if widget.is_visible():
                    text_content = widget.inner_text().strip()
                    if text_content: # Ignore empty blocks
                        extracted_data["elementor_text_blocks"].append(text_content)

        except Exception as e:
            logging.error(f"Headless rendering failed: {e}")
            extracted_data["error"] = str(e)
        finally:
            browser.close()

    return extracted_data

# Execution
if __name__ == "__main__":
    url = "https://compuciber.com/producto/computadora-gamer-core-i7-12700kf/"
    result = extract_wide_net_data(url)
    
    # Dump the raw, unformatted text blocks to see where the data is actually hiding
    print("\n--- EXTRACTION PAYLOAD ---")
    print(json.dumps(result, indent=2, ensure_ascii=False))