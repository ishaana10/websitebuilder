import os
import time
import random
from playwright.sync_api import sync_playwright

def run_verification():
    with sync_playwright() as p:
        print("Launching browser for Mobile Navbar CTA Layout Verification...")
        browser = p.chromium.launch(headless=True)
        # Mobile viewport context (e.g. iPhone / Android portrait screen size)
        context = browser.new_context(
            viewport={"width": 375, "height": 667},
            record_video_dir="/home/jules/verification/videos"
        )
        page = context.new_page()

        print("Navigating to index.php...")
        page.goto("http://127.0.0.1:8000/index.php")
        page.wait_for_selector("text=Nuvis Webidesigner")

        page.click("text=Register standard builder")
        username = f"mobile_cta_{random.randint(1000, 9999)}"
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

        print("Onboarding Pestkit / SaaS Template project...")
        page.click("button:has-text('Templates Library')")
        page.wait_for_selector("button:has-text('Use Template Theme')")
        page.click("button:has-text('Use Template Theme')")

        print("Waiting for Visual Builder Workspace...")
        page.wait_for_timeout(3000)

        # Set long button text on Navbar CTA to stress test vertical stretching
        navbar = page.locator("[data-component-instance='navbar']").first
        navbar.click()
        page.wait_for_selector("text=Edit Properties")

        print("Setting long CTA button text ('Get Quote Today')...")
        cta_input = page.locator("input[id='prop-btnText']")
        cta_input.fill("Get Quote Today")

        print("Publishing site...")
        page.click("button:has-text('Publish Site')")
        page.wait_for_selector("text=Published Successfully!", timeout=10000)

        # Navigate to compiled preview on mobile
        page.goto("http://127.0.0.1:8000/admin.php")
        page.wait_for_selector("text=View Live", state="attached")

        preview_link = page.locator("a:has-text('View Live')").first
        preview_url = preview_link.get_attribute("href")
        print(f"Loading compiled webpage preview on mobile: {preview_url}")

        mobile_preview = context.new_page()
        mobile_preview.goto(f"http://127.0.0.1:8000/{preview_url}")
        mobile_preview.wait_for_selector("[data-component='navbar']")

        cta_button = mobile_preview.locator("[data-cta-button='true']")

        # Measure dimensions
        box = cta_button.bounding_box()
        print(f"Mobile Navbar CTA Box: width={box['width']}px, height={box['height']}px")

        # CTA button should be wide and compact vertically (e.g. ~35-50px height), NOT stretched vertically (>100px)
        assert box['height'] < 60, f"CTA button height is {box['height']}px - vertical stretching detected!"
        assert box['width'] > 60, f"CTA button width is {box['width']}px - squished horizontally!"

        print("✓ SUCCESS: Mobile Navbar CTA button layout verified successfully without vertical stretching!")

        os.makedirs("/home/jules/verification/screenshots", exist_ok=True)
        screenshot_path = "/home/jules/verification/screenshots/mobile_navbar_cta_fix.png"
        mobile_preview.screenshot(path=screenshot_path)
        print(f"Screenshot captured at: {screenshot_path}")

        browser.close()

if __name__ == "__main__":
    run_verification()
