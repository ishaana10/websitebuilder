import os
import time
import random
from playwright.sync_api import sync_playwright

def run_verification():
    with sync_playwright() as p:
        print("Launching browser for Live Preview Button Verification...")
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            viewport={"width": 1280, "height": 800},
            record_video_dir="/home/jules/verification/videos"
        )
        page = context.new_page()

        print("Navigating to index.php...")
        page.goto("http://127.0.0.1:8000/index.php")
        page.wait_for_selector("text=Nuvis Webidesigner")

        page.click("text=Register standard builder")
        username = f"preview_user_{random.randint(1000, 9999)}"
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

        print("Onboarding SaaS Template project...")
        page.click("button:has-text('Templates Library')")
        page.wait_for_selector("button:has-text('Use Template Theme')")
        page.click("button:has-text('Use Template Theme')")

        print("Waiting for Visual Builder Workspace...")
        page.wait_for_timeout(3000)
        page.wait_for_selector("text=Control Center")

        print("Checking Live Preview button presence in header...")
        live_preview_btn = page.locator("a:has-text('Live Preview')")
        assert live_preview_btn.is_visible(), "Live Preview button is not visible in builder header!"

        preview_href = live_preview_btn.get_attribute("href")
        print(f"Live Preview button link target: {preview_href}")
        assert "render.php?slug=" in preview_href, f"Unexpected preview href: {preview_href}"

        print("Publishing site first...")
        page.click("button:has-text('Publish Site')")
        page.wait_for_selector("text=Published Successfully!", timeout=10000)

        print("Clicking Live Preview button...")
        with context.expect_page() as new_page_info:
            live_preview_btn.click()

        preview_page = new_page_info.value
        preview_page.wait_for_selector("main", timeout=10000)

        print(f"Preview page loaded with URL: {preview_page.url}")
        print("✓ SUCCESS: Live Preview button in edit page verified successfully!")

        os.makedirs("/home/jules/verification/screenshots", exist_ok=True)
        screenshot_path = "/home/jules/verification/screenshots/live_preview_button_preview.png"
        preview_page.screenshot(path=screenshot_path)
        print(f"Screenshot captured at: {screenshot_path}")

        browser.close()

if __name__ == "__main__":
    run_verification()
