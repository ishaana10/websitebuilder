import os
import time
from playwright.sync_api import sync_playwright

def run_verification():
    os.makedirs("/home/jules/verification/screenshots", exist_ok=True)
    os.makedirs("/home/jules/verification/videos", exist_ok=True)

    screenshot_path = "/home/jules/verification/screenshots/asymmetric_photo_grid_verified.png"

    with sync_playwright() as p:
        print("Launching browser for Asymmetric Photo Grid verification...")
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            viewport={"width": 1280, "height": 800},
            record_video_dir="/home/jules/verification/videos"
        )
        page = context.new_page()

        # Step 1: Login as admin
        print("Navigating to login page...")
        page.goto("http://127.0.0.1:8000/index.php?action=login")
        page.wait_for_selector("input[name='username_or_email']", timeout=10000)

        print("Logging in as admin...")
        page.fill("input[name='username_or_email']", "admin")
        page.fill("input[name='password']", "admin123")
        page.click("button[type='submit']")

        # Navigate directly to builder for project 1
        page.goto("http://127.0.0.1:8000/builder.php?project_id=1")
        page.wait_for_selector(".canvas-inner-html", timeout=15000)
        print("Builder loaded successfully!")

        # Step 2: Search and add asymmetric_photo_grid component
        print("Searching and adding Asymmetric Photo Grid component...")
        search_input = page.locator("input[placeholder*='Search widgets']")
        search_input.fill("Asymmetric Photo")
        page.wait_for_timeout(1000)

        grid_component_card = page.locator("text=Asymmetric Photo Grid Showcase").first
        grid_component_card.click()
        page.wait_for_timeout(1500)

        # Click on the asymmetric_photo_grid section on canvas to open properties in right sidebar
        print("Selecting Asymmetric Photo Grid section on canvas...")
        section_el = page.locator("section[data-component='asymmetric_photo_grid']").first
        section_el.wait_for(timeout=10000)
        section_el.click()
        page.wait_for_timeout(1500)

        # Step 3: Verify Upload Asset buttons and input fields
        print("Verifying Upload Asset buttons...")
        upload_labels = page.locator("label:has-text('Upload Asset')")
        print(f"Upload Asset labels count: {upload_labels.count()}")
        assert upload_labels.count() == 3, f"Expected 3 Upload Asset buttons, found {upload_labels.count()}"

        # Update image URL manually to test prop binding
        test_asset_url = "uploads/test_asset_photo.jpg"
        main_img_input = page.locator("input[placeholder='https://... or uploads/...']").first
        main_img_input.fill(test_asset_url)
        page.wait_for_timeout(1000)

        # Verify canvas image updated
        section_html = section_el.inner_html()
        assert test_asset_url in section_html, "Site asset URL not rendered in section canvas HTML"
        print("Site asset URL verification passed!")

        # Take screenshot
        page.screenshot(path=screenshot_path, full_page=True)
        print(f"Screenshot saved to {screenshot_path}")

        browser.close()
        print("Verification script finished successfully!")

if __name__ == "__main__":
    run_verification()
