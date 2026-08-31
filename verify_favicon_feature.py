import os
import time
import random
from playwright.sync_api import sync_playwright

def run_verification():
    with sync_playwright() as p:
        print("Launching browser for Favicon Feature Verification...")
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

        username = f"favicon_user_{random.randint(1000, 9999)}"
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
        page.wait_for_timeout(3000)
        page.wait_for_selector("text=Control Center")

        # Open SEO Tab
        print("Opening SEO & Metadata Settings panel...")
        page.click("button:has-text('SEO')")

        test_favicon_url = "https://cdn.example.com/assets/my-custom-favicon.ico"
        print(f"Entering custom Favicon URL: {test_favicon_url}")
        page.fill("#seo-favicon-input", test_favicon_url)

        print("Saving draft layouts & SEO settings...")
        page.click("button:has-text('Save SEO Injections')")
        time.sleep(1)

        print("Publishing website...")
        page.click("button:has-text('Publish Site')")

        page.wait_for_selector("text=Published Successfully!", timeout=10000)
        print("Publish successful!")

        # Navigate to admin.php to click 'View Live'
        page.goto("http://127.0.0.1:8000/admin.php")
        page.wait_for_selector("text=View Live", state="attached")

        preview_link = page.locator("a:has-text('View Live')").first
        preview_url = preview_link.get_attribute("href")
        print(f"Loading compiled webpage preview: {preview_url}")

        preview_page = context.new_page()
        preview_page.goto(f"http://127.0.0.1:8000/{preview_url}")

        # Check <link rel="icon"> in head
        icon_href = preview_page.get_attribute("link[rel='icon']", "href")
        shortcut_href = preview_page.get_attribute("link[rel='shortcut icon']", "href")

        print(f"Found link[rel='icon'] href: {icon_href}")
        print(f"Found link[rel='shortcut icon'] href: {shortcut_href}")

        assert icon_href == test_favicon_url, f"Expected {test_favicon_url}, got {icon_href}"
        assert shortcut_href == test_favicon_url, f"Expected {test_favicon_url}, got {shortcut_href}"

        print("✓ SUCCESS: Website Favicon option successfully verified on the live published page!")

        os.makedirs("/home/jules/verification/screenshots", exist_ok=True)
        screenshot_path = "/home/jules/verification/screenshots/favicon_feature_preview.png"
        preview_page.screenshot(path=screenshot_path)
        print(f"Screenshot captured at: {screenshot_path}")

        browser.close()

if __name__ == "__main__":
    run_verification()
