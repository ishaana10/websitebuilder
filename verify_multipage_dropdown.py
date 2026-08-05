import re
import sys
import time
import os
from playwright.sync_api import sync_playwright

def run_e2e_verification():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        username = f"user_mp_{int(time.time())}"
        print(f"Registering user: {username}")
        page.goto("http://127.0.0.1:8000/index.php?action=register")
        page.wait_for_timeout(1000)

        page.fill("input[name='username']", username)
        page.fill("input[name='email']", f"{username}@test.com")
        page.fill("input[name='password']", "password123")
        page.fill("input[name='confirm_password']", "password123")
        page.click("button[type='submit']")
        page.wait_for_timeout(1500)

        # Login
        print("Logging in...")
        page.goto("http://127.0.0.1:8000/index.php")
        page.fill("input[name='username_or_email']", username)
        page.fill("input[name='password']", "password123")
        page.click("button[type='submit']")
        page.wait_for_timeout(1500)

        # Use template to initialize project
        print("Choosing SaaS template...")
        page.click("button:has-text('Templates Library')")
        page.wait_for_timeout(1000)

        page.click("button:has-text('Use Template Theme')")
        page.wait_for_timeout(3500) # Wait for clone process & builder workspace initialization

        # Verify navbar is on canvas
        page.wait_for_selector("[data-component='navbar']")
        print("Navbar loaded successfully!")

        # Register a dialog handler for creating pages
        def handle_dialog(dialog):
            print(f"Dialog: {dialog.message}")
            dialog.accept("aboutus")

        page.on("dialog", handle_dialog)

        # Create Page
        print("Clicking Create New Page button...")
        page.click("button[title='Create New Page']")
        page.wait_for_timeout(2000)

        # Check active page selection value is 'aboutus'
        active_val = page.locator("header select").first.input_value()
        print(f"Active Page after creation: {active_val}")
        assert active_val == "aboutus", f"Expected active page to be aboutus, got {active_val}"

        # Since aboutus is blank (or copied index navbar/footer), let's click 'Properties' to verify selection
        # Now let's switch back to 'index'
        print("Switching back to index page...")
        page.select_option("header select", value="index")
        page.wait_for_timeout(1000)

        # Click navbar on canvas to show link management
        print("Opening Navbar properties panel...")
        page.click("[data-component='navbar']")
        page.wait_for_selector("text=Manage Navigation Links")

        # Let's add a new navigation link
        page.click("text=Add New Link")
        page.wait_for_timeout(500)

        # Change link type to Dropdown
        # First let's find the newly added link row (it has type select)
        type_selects = page.locator("select:has-text('Link')")
        print(f"Number of link type select dropdowns: {type_selects.count()}")
        type_selects.last.select_option("dropdown")
        page.wait_for_timeout(500)

        # Click Add Dropdown Sub Link
        print("Adding dropdown sub link...")
        page.click("text=Add Dropdown Sub Link")
        page.wait_for_timeout(500)

        # Set sub link text & target page as 'aboutus'
        sub_text_input = page.locator("input[placeholder='Sub Link text']").last
        sub_text_input.fill("About Us Page")

        child_type_select = page.locator("select:has-text('Link')").last
        child_type_select.select_option("page")
        page.wait_for_timeout(500)

        page_select = page.locator("select:has-text('index')").last
        page_select.select_option("aboutus")
        page.wait_for_timeout(500)

        # Take a visual screenshot of builder UI
        os.makedirs("/home/jules/verification/screenshots", exist_ok=True)
        screenshot_path = "/home/jules/verification/screenshots/multipage_dropdown.png"
        page.screenshot(path=screenshot_path)
        print(f"Screenshot taken and saved to {screenshot_path}")

        print("Saved draft & publishing multipage site...")
        page.click("text=Publish Site")
        page.wait_for_timeout(4000)

        # Let's navigate to dynamic PHP renderer
        import subprocess
        cmd = f"mariadb -u builder_user -pbuilder_pass -D site_builder -N -e \"SELECT slug FROM projects ORDER BY created_at DESC LIMIT 1\""
        slug = subprocess.check_output(cmd, shell=True).decode().strip()

        live_url = f"http://127.0.0.1:8000/render.php?slug={slug}&user={username}"
        print(f"Opening published index page: {live_url}")
        page.goto(live_url)
        page.wait_for_selector("[data-component='navbar']")

        # Let's open the aboutus page via routing parameter
        aboutus_url = f"{live_url}&page=aboutus"
        print(f"Opening published aboutus page: {aboutus_url}")
        page.goto(aboutus_url)
        page.wait_for_selector("[data-component='navbar']")

        print("Multi-page links and dynamic page compilation resolved successfully!")
        browser.close()

if __name__ == "__main__":
    run_e2e_verification()
