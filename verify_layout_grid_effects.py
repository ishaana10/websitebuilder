import os
import time
import random
from playwright.sync_api import sync_playwright

def run_verification():
    with sync_playwright() as p:
        print("Launching browser for Layout Grid Effects Verification...")
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            viewport={"width": 1280, "height": 800},
            record_video_dir="/home/jules/verification/videos"
        )
        page = context.new_page()

        # Step 1: Register and login
        print("Navigating to index.php...")
        page.goto("http://127.0.0.1:8000/index.php")
        page.wait_for_selector("text=Nuvis Webidesigner")

        print("Switching to registration form...")
        page.click("text=Register standard builder")

        username = f"grid_fx_user_{random.randint(1000, 9999)}"
        email = f"{username}@example.com"
        print(f"Registering user: {username}")
        page.fill("input[name='username']", username)
        page.fill("input[name='email']", email)
        page.fill("input[name='password']", "password123")
        page.fill("input[name='confirm_password']", "password123")
        page.click("button[type='submit']")

        print("Waiting for page load after registration...")
        page.wait_for_selector("input[name='username_or_email']", timeout=10000)

        print("Logging in...")
        page.fill("input[name='username_or_email']", username)
        page.fill("input[name='password']", "password123")
        page.click("button[type='submit']")

        # Onboard project
        print("Onboarding SaaS Template project...")
        page.wait_for_selector("button:has-text('Templates Library')", timeout=10000)
        page.click("button:has-text('Templates Library')")
        page.wait_for_selector("button:has-text('Use Template Theme')")
        page.click("button:has-text('Use Template Theme')")

        print("Waiting for Visual Builder Workspace...")
        page.wait_for_timeout(4000)
        page.wait_for_selector("[data-component-instance='navbar']")

        # --- Add & Test Layout Grid Component ---
        print("Adding Responsive Grid Containers from sidebar...")
        search_input = page.locator("input[placeholder*='Search widgets']")
        search_input.fill("Responsive Grid")
        page.wait_for_timeout(1000)

        # Click widget card to insert
        grid_widget_card = page.locator("text=Responsive Grid Containers").first
        grid_widget_card.click()
        page.wait_for_timeout(2000)

        print("Selecting Responsive Grid Container on canvas...")
        grid_sect = page.locator("[data-component-instance='layout_grid']").first
        grid_sect.click()
        page.wait_for_timeout(1000)

        # Verify cardEffect select dropdown is available in properties panel
        print("Verifying Card Visual / Animation Effect dropdown exists...")
        page.wait_for_selector("select[id='prop-cardEffect']")
        effect_select = page.locator("select[id='prop-cardEffect']")

        # Test selecting 'hover-lift' effect
        print("Selecting 'hover-lift' (Hover Lift & Scale) effect...")
        effect_select.select_option("hover-lift")
        page.wait_for_timeout(1000)

        # Verify card in canvas has hover-lift classes
        card_el = page.locator("[data-component-instance='layout_grid'] div.p-6").first
        card_class = card_el.get_attribute("class") or ""
        print(f"Canvas card class: {card_class}")
        assert "hover:-translate-y-2" in card_class and "hover:scale-[1.02]" in card_class, f"Expected hover-lift classes in canvas card, got: {card_class}"
        print("✔ Canvas updated with hover-lift effect classes!")

        # Save and Publish
        print("Saving draft layout...")
        page.click("button:has-text('Save Draft')")
        time.sleep(1)

        print("Publishing site...")
        page.click("button:has-text('Publish Site')")
        page.wait_for_selector("text=Published Successfully!", timeout=10000)
        print("Publish successful!")

        # Navigate to compiled preview
        page.goto("http://127.0.0.1:8000/admin.php")
        page.wait_for_selector("text=View Live", state="attached")

        preview_link = page.locator("a:has-text('View Live')").first
        preview_url = preview_link.get_attribute("href")
        print(f"Loading compiled webpage preview: {preview_url}")

        page.goto("http://127.0.0.1:8000/" + preview_url)
        page.wait_for_timeout(2000)
        page.wait_for_selector("[data-component='layout_grid']")

        # Verify live published page grid card has effect classes
        live_card = page.locator("[data-component='layout_grid'] div.p-6").first
        live_card_class = live_card.get_attribute("class") or ""
        print(f"Live card class: {live_card_class}")
        assert "hover:-translate-y-2" in live_card_class and "hover:scale-[1.02]" in live_card_class, f"Expected hover-lift classes on live page card, got: {live_card_class}"
        print("✔ Verified: Selected layout grid card effect renders properly on live published page!")

        os.makedirs("/home/jules/verification/screenshots", exist_ok=True)
        screenshot_path = "/home/jules/verification/screenshots/grid_effects_verification.png"
        page.screenshot(path=screenshot_path)
        print(f"Screenshot captured at: {screenshot_path}")

        context.close()
        browser.close()

if __name__ == "__main__":
    run_verification()
