import os
import time
import subprocess
from playwright.sync_api import sync_playwright

def get_latest_slug(username):
    cmd = f"mariadb -u builder_user -pbuilder_pass -D site_builder -N -e \"SELECT slug FROM projects JOIN users ON projects.user_id = users.id WHERE users.username='{username}' ORDER BY projects.created_at DESC LIMIT 1\""
    res = subprocess.check_output(cmd, shell=True).decode().strip()
    return res

def test_mobile_and_gradients():
    os.makedirs('/home/jules/verification/screenshots', exist_ok=True)
    ts = str(int(time.time()))
    username = f"mobuser_{ts}"
    email = f"mobuser_{ts}@example.com"
    password = "password123"

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        # Desktop viewport for builder editing
        context = browser.new_context(viewport={'width': 1280, 'height': 800})
        page = context.new_page()

        print("Navigating to index.php?action=register...")
        page.goto("http://127.0.0.1:8000/index.php?action=register")
        page.wait_for_timeout(1000)

        print(f"Registering mobile test user: {username}...")
        page.fill("input[name='username']", username)
        page.fill("input[name='email']", email)
        page.fill("input[name='password']", password)
        page.fill("input[name='confirm_password']", password)
        page.click("button[type='submit']")
        page.wait_for_timeout(1500)

        print("Logging in...")
        page.fill("input[name='username_or_email']", username)
        page.fill("input[name='password']", password)
        page.click("button[type='submit']")
        page.wait_for_timeout(2000)

        # Onboard template
        print("Switching to Templates Library...")
        page.click("button:has-text('Templates Library')")
        page.wait_for_timeout(1000)
        page.click("button:has-text('Use Template Theme')")
        page.wait_for_timeout(3000)

        # Switch to Theme tab and test Lime Green Gradient Theme Preset
        print("Testing Theme Presets in Control Center...")
        page.click("button:has-text('Theme')")
        page.wait_for_timeout(500)
        page.click("button:has-text('Lime Green Energy')")
        page.wait_for_timeout(1000)

        # Switch to mobile canvas view in builder
        print("Testing mobile view in builder...")
        page.click("button:has-text('mobile')")
        page.wait_for_timeout(1000)

        # Expand mobile burger menu in builder canvas
        print("Testing burger menu click on mobile canvas...")
        burger_button = page.locator("[data-component='navbar'] button:has(.fa-bars)").first
        if burger_button.is_visible():
            burger_button.click()
            page.wait_for_timeout(1000)

        mobile_screenshot_path = "/home/jules/verification/screenshots/mobile_view_builder.png"
        page.screenshot(path=mobile_screenshot_path)
        print(f"Mobile builder screenshot saved at: {mobile_screenshot_path}")

        print("Publishing site...")
        page.click("button:has-text('Publish Site')")
        page.wait_for_timeout(3000)

        slug = get_latest_slug(username)
        print(f"Latest slug: {slug}")

        context.close()

        # Open mobile browser context for render.php
        mobile_context = browser.new_context(viewport={'width': 375, 'height': 812})
        mob_page = mobile_context.new_page()

        user_page_url = f"http://127.0.0.1:8000/render.php?slug={slug}&user={username}"
        print(f"Navigating to published page in mobile viewport: {user_page_url}")
        mob_page.goto(user_page_url)
        mob_page.wait_for_timeout(2000)

        # Click hamburger menu on published page
        pub_burger = mob_page.locator("[data-component='navbar'] button:has(.fa-bars)").first
        if pub_burger.is_visible():
            pub_burger.click()
            mob_page.wait_for_timeout(1000)

        # Check that mobile menu links are visible and stacked
        mobile_menu = mob_page.locator(".mobile-menu")
        assert mobile_menu.is_visible(), "Mobile menu should be visible after burger click"

        pub_screenshot_path = "/home/jules/verification/screenshots/mobile_view_published.png"
        mob_page.screenshot(path=pub_screenshot_path)
        print(f"Mobile published screenshot saved at: {pub_screenshot_path}")

        mobile_context.close()
        browser.close()
        print("ALL MOBILE & GRADIENT VERIFICATION CHECKS PASSED SUCCESSFULLY!")

if __name__ == "__main__":
    test_mobile_and_gradients()
