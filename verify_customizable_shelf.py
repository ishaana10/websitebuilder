import os
import time
from playwright.sync_api import sync_playwright

def run_verification():
    os.makedirs("/home/jules/verification/screenshots", exist_ok=True)
    os.makedirs("/home/jules/verification/videos", exist_ok=True)

    screenshot_path = "/home/jules/verification/screenshots/customizable_shelf_verified.png"

    with sync_playwright() as p:
        print("Launching browser for Customizable Shelf verification...")
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

        # Step 2: Search and add customizable_shelf component
        print("Searching and adding Customizable Dynamic Shelf component...")
        search_input = page.locator("input[placeholder*='Search widgets']")
        search_input.fill("Customizable")
        page.wait_for_timeout(1000)

        shelf_card = page.locator("text=Customizable Drag & Flex Shelf").first
        shelf_card.click()
        page.wait_for_timeout(1500)

        # Select the customizable_shelf section on canvas
        print("Selecting Customizable Shelf section on canvas...")
        section_el = page.locator("section[data-component='customizable_shelf']").last
        section_el.wait_for(timeout=10000)
        section_el.click()
        page.wait_for_timeout(1500)

        # Step 3: Verify Objects Manager Sidebar UI
        print("Verifying Shelf Layout & Objects Manager...")
        page.wait_for_selector("text=Shelf Layout & Objects Manager", timeout=5000)

        # Test adding a new button
        print("Testing adding a new button...")
        add_btn = page.locator("button:has-text('+ Button')")
        add_btn.click()
        page.wait_for_timeout(1000)

        # Test adding a new picture
        print("Testing adding a new picture...")
        add_pic = page.locator("button:has-text('+ Picture')")
        add_pic.click()
        page.wait_for_timeout(1000)

        # Check element inputs
        btn_inputs = page.locator("input[placeholder='Button Label']")
        print(f"Button input count: {btn_inputs.count()}")
        assert btn_inputs.count() >= 2, "Failed to add new button"

        # Edit newly added button label
        last_btn_input = btn_inputs.last
        last_btn_input.fill("Custom Interactivity Button")
        page.wait_for_timeout(1000)

        # Verify button rendered on canvas
        page.wait_for_selector("text=Custom Interactivity Button", timeout=5000)
        print("Custom button label verified on canvas!")

        # Step 4: Test reordering objects (Move Up / Move Down)
        print("Testing reordering objects (Move Up)...")
        move_up_btns = page.locator("button[title='Move Up']")
        if move_up_btns.count() > 1:
            # Click move up on last object
            move_up_btns.last.click()
            page.wait_for_timeout(1000)
            print("Move Up executed successfully!")

        # Step 5: Test changing layout direction and alignment
        print("Testing changing layout flow direction...")
        layout_select = page.locator("select[id='prop-layoutDirection']")
        if layout_select.count() > 0:
            layout_select.select_option("flex-col")
            page.wait_for_timeout(1000)

        section_html = section_el.inner_html()
        assert "flex-col" in section_html, "Layout flow direction flex-col missing in section HTML"
        print("Flex Column layout direction verified in section HTML!")

        # Step 6: Test deleting an object
        print("Testing deleting an object...")
        delete_btns = page.locator("button[title='Delete Object']")
        initial_count = delete_btns.count()
        if delete_btns.count() > 0:
            delete_btns.last.click()
            page.wait_for_timeout(1000)
            assert page.locator("button[title='Delete Object']").count() == initial_count - 1, "Failed to delete object"
            print("Delete object verified successfully!")

        # Take screenshot
        page.screenshot(path=screenshot_path, full_page=True)
        print(f"Screenshot saved to {screenshot_path}")

        browser.close()
        print("Verification script finished successfully!")

if __name__ == "__main__":
    run_verification()
