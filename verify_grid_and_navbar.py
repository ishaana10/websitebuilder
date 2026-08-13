import os
import time
import random
from playwright.sync_api import sync_playwright

def run_verification():
    with sync_playwright() as p:
        print("Launching browser for Grid and Navbar Verification...")
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

        username = f"grid_user_{random.randint(1000, 9999)}"
        email = f"{username}@example.com"
        print(f"Registering user: {username}")
        page.fill("input[name='username']", username)
        page.fill("input[name='email']", email)
        page.fill("input[name='password']", "password123")
        page.fill("input[name='confirm_password']", "password123")
        page.click("button[type='submit']")

        print("Logging in...")
        page.fill("input[name='username_or_email']", username)
        page.fill("input[name='password']", "password123")
        page.click("button[type='submit']")

        # Onboard project
        print("Onboarding SaaS Template project...")
        page.click("button:has-text('Templates Library')")
        page.wait_for_selector("button:has-text('Use Template Theme')")
        page.click("button:has-text('Use Template Theme')")

        print("Waiting for Visual Builder Workspace...")
        page.wait_for_timeout(4000)
        page.wait_for_selector("[data-component-instance='navbar']")

        # --- Navbar CTA Button and Brand Color Verification ---
        print("Clicking Responsive Navigation Bar on canvas...")
        navbar = page.locator("[data-component-instance='navbar']").first
        navbar.click()

        print("Waiting for properties panel to populate...")
        page.wait_for_selector("text=Edit Properties")

        # Set dedicated Brand Name Color
        print("Setting Brand Name Color to #ff00ff...")
        brand_color_input = page.locator("input[id='prop-brandColor']")
        brand_color_input.fill("#ff00ff")
        brand_color_input.press("Enter")
        page.wait_for_timeout(1000)

        # Set dedicated CTA Button Background
        print("Setting CTA Button Background to #00ffff...")
        btn_bg_input = page.locator("input[id='prop-btnBg']")
        btn_bg_input.fill("#00ffff")
        btn_bg_input.press("Enter")
        page.wait_for_timeout(1000)

        # Verify that CTA button background on the canvas has updated to #00ffff
        # while Brand Name text color has updated to #ff00ff
        brand_name_el = page.locator("[data-component-instance='navbar'] div.text-xl")
        cta_btn_el = page.locator("[data-component-instance='navbar'] a:has-text('Get Started')")

        # Check brand text color style
        brand_style = brand_name_el.get_attribute("style") or ""
        assert "color: rgb(255, 0, 255)" in brand_style or "#ff00ff" in brand_style, f"Expected Brand Name color #ff00ff, got: {brand_style}"

        # Check cta btn style
        cta_style = cta_btn_el.get_attribute("style") or ""
        assert "background-color: rgb(0, 255, 255)" in cta_style or "#00ffff" in cta_style, f"Expected CTA background #00ffff, got: {cta_style}"
        print("✔ Verified: Brand color and Button background colors are independent in builder mode!")

        # Uncheck "Show CTA Button" to verify hiding
        print("Unchecking Show CTA Button...")
        show_cta_checkbox = page.locator("input[id='prop-showCta']")
        show_cta_checkbox.uncheck()
        page.wait_for_timeout(1000)

        # Verify that CTA button is completely omitted/removed
        assert cta_btn_el.count() == 0, "Expected CTA button to be completely removed when 'Show CTA Button' is false!"
        print("✔ Verified: CTA button is completely removed in builder mode!")

        # --- Responsive Grid Cards Verification ---
        print("Adding Responsive Grid Containers from sidebar...")
        search_input = page.locator("input[placeholder*='Search widgets']")
        search_input.fill("Responsive Grid")
        page.wait_for_timeout(1000)

        # Click the widget card to add to canvas
        grid_widget_card = page.locator("text=Responsive Grid Containers").first
        grid_widget_card.click()
        page.wait_for_timeout(2000)

        print("Selecting Responsive Grid Container on canvas...")
        grid_sect = page.locator("[data-component-instance='layout_grid']").first
        grid_sect.click()
        page.wait_for_timeout(1000)

        # Verify the custom grid editor exists
        print("Verifying Manage Grid Cards editor is visible...")
        page.wait_for_selector("text=Manage Grid Cards")

        # Test Add New Card
        print("Clicking Add New Card button...")
        page.click("button:has-text('Add New Card')")
        page.wait_for_timeout(1000)

        # Verify that a new card textarea has been added (should now have 4 textareas)
        card_inputs = page.locator("textarea[placeholder='Card Content']")
        assert card_inputs.count() == 4, f"Expected 4 card inputs, got {card_inputs.count()}"

        # Customize the new card's content
        new_text = "Highly customized card content for automation verification testing!"
        print(f"Setting last card content to: '{new_text}'...")
        card_inputs.last.fill(new_text)
        card_inputs.last.press("Enter")
        page.wait_for_timeout(1000)

        # Test Remove Card (remove the first card)
        print("Testing Remove Card...")
        first_trash_btn = page.locator("button[title='Remove Card']").first
        first_trash_btn.click()
        page.wait_for_timeout(1000)

        # Now there should be 3 cards
        assert card_inputs.count() == 3, f"Expected 3 cards after removal, got {card_inputs.count()}"
        print("✔ Verified: Dynamic adding, removing, and editing cards works in builder mode!")

        # Save and Publish
        print("Saving draft layouts...")
        page.click("button:has-text('Save Draft')")
        time.sleep(1)

        print("Publishing website...")
        page.click("button:has-text('Publish Site')")

        # Wait for "Published Successfully!" toast
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
        page.wait_for_selector("[data-component='navbar']")

        # Verify button is still omitted on live site
        live_cta_btn = page.locator("[data-component='navbar'] a:has-text('Get Started')")
        assert live_cta_btn.count() == 0, "Expected CTA button to be omitted on live published page!"
        print("✔ Verified: CTA button is completely omitted on live published page!")

        # Verify Grid has the custom card with custom text
        live_custom_card = page.locator(f"[data-component='layout_grid'] :has-text('{new_text}')")
        assert live_custom_card.count() > 0, "Expected custom card to be rendered on live published page!"
        print("✔ Verified: Custom grid cards render perfectly on live published page!")

        print("✓ SUCCESS: Fully verified all requirements of the task!")

        # Create screenshots folder and save screen verification
        screenshot_path = "/home/jules/verification/screenshots/grid_and_navbar_verification.png"
        page.screenshot(path=screenshot_path)
        print(f"Screenshot captured at: {screenshot_path}")

        context.close()
        browser.close()

if __name__ == "__main__":
    run_verification()
