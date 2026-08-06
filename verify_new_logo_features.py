import os
import time
import random
from playwright.sync_api import sync_playwright

def run_verification():
    with sync_playwright() as p:
        print("Launching browser for Logo Customizer Verification...")
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            viewport={"width": 1280, "height": 800},
            record_video_dir="/home/jules/verification/videos"
        )
        page = context.new_page()

        # Step 1: Register and login
        print("Navigating to index.php...")
        page.goto("http://127.0.0.1:8000/index.php")
        page.wait_for_selector("text=Nuvis Webbuilder")

        print("Switching to registration form...")
        page.click("text=Register standard builder")

        username = f"logo_user_{random.randint(1000, 9999)}"
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

        # Select the navbar component on canvas
        print("Clicking Responsive Navigation Bar on canvas...")
        navbar = page.locator("[data-component-instance='navbar']").first
        navbar.click()

        # Edit properties in the properties tab
        print("Waiting for properties panel to populate...")
        page.wait_for_selector("text=Edit Properties")

        # Fill in logo URL
        test_logo_url = "https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=120&auto=format&fit=crop&q=60"
        print("Setting Logo URL...")
        logo_input = page.locator("input[id='prop-logoUrl']")
        logo_input.fill(test_logo_url)
        # Trigger input change
        logo_input.press("Enter")

        # Set Logo Width
        print("Setting Logo Width to 150px...")
        width_input = page.locator("input[id='prop-logoWidth']")
        width_input.fill("150px")
        width_input.press("Enter")

        # Set Logo Height
        print("Setting Logo Height to 50px...")
        height_input = page.locator("input[id='prop-logoHeight']")
        height_input.fill("50px")
        height_input.press("Enter")

        # Select Logo Shape
        print("Selecting Circular Logo Shape...")
        shape_select = page.locator("select:near(label:has-text('Logo Shape'))").first
        shape_select.select_option("circle")

        # Select Logo Position
        print("Selecting Logo Position: Right of Text...")
        position_select = page.locator("select:near(label:has-text('Logo Position'))").first
        position_select.select_option("right-of-text")

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
        page.wait_for_selector("[data-component='navbar']")

        # Verify HTML has correct styles on the logo image
        print("Verifying published logo elements and styles...")
        logo_img = page.locator("[data-component='navbar'] img")

        src = logo_img.get_attribute("src")
        style = logo_img.get_attribute("style")
        print(f"Found Logo image src: {src}")
        print(f"Found Logo style attribute: {style}")

        # Assertions
        assert src == test_logo_url, f"Expected logo URL '{test_logo_url}', got '{src}'"
        assert "width: 150px" in style, f"Expected logo style to contain 'width: 150px', got '{style}'"
        assert "height: 50px" in style, f"Expected logo style to contain 'height: 50px', got '{style}'"
        assert "border-radius: 9999px" in style, f"Expected circular logo shape, got '{style}'"

        # Verify position container has flex-row-reverse
        parent_div = page.locator("[data-component='navbar'] .flex-row-reverse")
        assert parent_div.count() > 0, "Expected brand Logo/Text container to use 'flex-row-reverse' class for Right of Text alignment!"

        print("✓ SUCCESS: Fully customizable Logo features successfully verified on the live published page!")

        # Create screenshots folder and save screen verification
        os.makedirs("/home/jules/verification/screenshots", exist_ok=True)
        screenshot_path = "/home/jules/verification/screenshots/customizable_logo_preview.png"
        page.screenshot(path=screenshot_path)
        print(f"Screenshot captured at: {screenshot_path}")

        context.close()
        browser.close()

if __name__ == "__main__":
    run_verification()
