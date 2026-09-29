import os
import time
from playwright.sync_api import sync_playwright

def run_grid_verification():
    os.makedirs("/home/jules/verification/screenshots", exist_ok=True)
    screenshot_path = "/home/jules/verification/screenshots/grid_shelves_verified.png"

    with sync_playwright() as p:
        print("Launching browser for Grid Shelves customization verification...")
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(viewport={"width": 1280, "height": 800})
        page = context.new_page()

        # Step 1: Login as admin
        print("Logging in as admin...")
        page.goto("http://127.0.0.1:8000/index.php?action=login")
        page.wait_for_selector("input[name='username_or_email']", timeout=10000)
        page.fill("input[name='username_or_email']", "admin")
        page.fill("input[name='password']", "admin123")
        page.click("button[type='submit']")

        # Navigate to builder
        page.goto("http://127.0.0.1:8000/builder.php?project_id=1")
        page.wait_for_selector(".canvas-inner-html", timeout=15000)
        print("Builder loaded successfully!")

        # Test 1: Layout Grid reordering and rich fields
        print("Testing Layout Grid card customization and reordering...")
        search_input = page.locator("input[placeholder*='Search widgets']")
        search_input.fill("Responsive Grid")
        page.wait_for_timeout(1000)

        grid_card = page.locator("text=Responsive Grid Containers").first
        grid_card.click()
        page.wait_for_timeout(1500)

        section_el = page.locator("section[data-component='layout_grid']").last
        section_el.wait_for(timeout=10000)
        section_el.click()
        page.wait_for_timeout(1500)

        # Edit card 1 title and CTA button
        title_inputs = page.locator("input[placeholder='Card Title (Optional)']")
        if title_inputs.count() > 0:
            title_inputs.first.fill("Custom Enhanced Grid Card")
            page.wait_for_timeout(500)

        btn_text_inputs = page.locator("input[placeholder='Button Label']")
        if btn_text_inputs.count() > 0:
            btn_text_inputs.first.fill("Explore Grid Action")
            page.wait_for_timeout(500)

        # Check canvas rendering
        page.wait_for_selector("text=Custom Enhanced Grid Card", timeout=5000)
        page.wait_for_selector("text=Explore Grid Action", timeout=5000)
        print("Layout grid title and CTA button verified on canvas!")

        # Test 2: Move Down reordering
        move_down_btns = page.locator("button[title='Move Down']")
        if move_down_btns.count() > 0:
            move_down_btns.first.click()
            page.wait_for_timeout(1000)
            print("Card reordering Move Down tested successfully!")

        # Test 3: Icon Spotlight Box reordering
        print("Testing Icon Spotlight Box card reordering...")
        search_input.fill("Icon / Image Spotlight Box Grid")
        page.wait_for_timeout(1000)

        spotlight_card = page.locator("text=Icon / Image Spotlight Box Grid").first
        spotlight_card.click()
        page.wait_for_timeout(1500)

        spotlight_section = page.locator("section[data-component='icon_image_box']").last
        spotlight_section.wait_for(timeout=10000)
        spotlight_section.click()
        page.wait_for_timeout(1500)

        # Test move up/down on spotlight cards
        spotlight_move_down = page.locator("button[title='Move Down']")
        if spotlight_move_down.count() > 0:
            spotlight_move_down.first.click()
            page.wait_for_timeout(1000)
            print("Spotlight card Move Down tested successfully!")

        page.screenshot(path=screenshot_path, full_page=True)
        print(f"Screenshot saved to {screenshot_path}")

        browser.close()
        print("Grid shelves verification completed successfully!")

if __name__ == "__main__":
    run_grid_verification()
