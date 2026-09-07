import os
import time
import random
from playwright.sync_api import sync_playwright

def run_verification():
    os.makedirs("/home/jules/verification/screenshots", exist_ok=True)
    os.makedirs("/home/jules/verification/videos", exist_ok=True)

    screenshot_path = "/home/jules/verification/screenshots/work_description_shelf_verified.png"

    with sync_playwright() as p:
        print("Launching browser for Work Description Shelf verification...")
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

        # Step 2: Search and add work_description_shelf component
        print("Searching and adding Work Description Picture Shelf component...")
        search_input = page.locator("input[placeholder*='Search widgets']")
        search_input.fill("Work Description")
        page.wait_for_timeout(1000)

        shelf_component_card = page.locator("text=Work Description Picture Shelf").first
        shelf_component_card.click()
        page.wait_for_timeout(1500)

        # Click on the work_description_shelf section on canvas to open properties in right sidebar
        print("Selecting Work Description Shelf section on canvas...")
        section_el = page.locator("section[data-component='work_description_shelf']").first
        section_el.wait_for(timeout=10000)
        section_el.click()
        page.wait_for_timeout(1500)

        # Step 3: Verify Manage Work / Service Picture Items
        print("Verifying Picture Items Manager...")
        add_item_btn = page.locator("button:has-text('Add Service / Work Item')")
        add_item_btn.wait_for(timeout=5000)
        add_item_btn.click()
        page.wait_for_timeout(1000)

        item_titles = page.locator("input[placeholder='Service / Work Title']")
        print(f"Count of picture items: {item_titles.count()}")
        assert item_titles.count() > 3, "Failed to add picture item"

        # Step 4: Verify Manage Service Tags / Badges List
        print("Verifying Service Tags / Badges List Manager...")
        add_tag_btn = page.locator("button:has-text('Add Service Tag Badge')")
        add_tag_btn.wait_for(timeout=5000)
        add_tag_btn.click()
        page.wait_for_timeout(1000)

        tag_inputs = page.locator("input[placeholder='Tag / Badge Text']")
        print(f"Count of tag badge items: {tag_inputs.count()}")

        # Fill newly added tag
        last_tag_input = tag_inputs.last
        last_tag_input.fill("Custom Pest Protection Tag")
        page.wait_for_timeout(1000)

        # Verify custom tag pill rendered on canvas
        page.wait_for_selector("text=Custom Pest Protection Tag", timeout=5000)
        print("Custom tag pill verified on canvas!")

        # Step 5: Verify List Visual Effect
        print("Testing List Visual Effect dropdown...")
        effect_select = page.locator("select[id='prop-listEffect']")
        print(f"Effect select count: {effect_select.count()}")
        if effect_select.count() > 0:
            effect_select.select_option("hover-glow")
            page.wait_for_timeout(1500)

        section_html = section_el.inner_html()
        print("Section HTML snippet:", section_html[:300])

        assert "hover:shadow-[0_0_15px_rgba(20,184,166,0.6)]" in section_html, "List effect class missing in section HTML"
        print("List visual effect verified!")

        # Step 6: Delete a tag item
        print("Testing deleting tag item...")
        delete_tag_btns = page.locator("button[title='Delete Tag']")
        initial_tag_count = tag_inputs.count()
        if delete_tag_btns.count() > 0:
            delete_tag_btns.first.click()
            page.wait_for_timeout(1000)
            print(f"Tag count after deletion: {page.locator('input[placeholder=\"Tag / Badge Text\"]').count()}")
            assert page.locator('input[placeholder="Tag / Badge Text"]').count() == initial_tag_count - 1, "Failed to delete tag item"

        # Take screenshot
        page.screenshot(path=screenshot_path, full_page=True)
        print(f"Screenshot saved to {screenshot_path}")

        browser.close()
        print("Verification script finished successfully!")

if __name__ == "__main__":
    run_verification()
