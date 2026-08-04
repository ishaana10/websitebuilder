from playwright.sync_api import sync_playwright
import time
import os
import subprocess

def get_latest_slug(username):
    cmd = f"mariadb -u builder_user -pbuilder_pass -D site_builder -N -e \"SELECT slug FROM projects JOIN users ON projects.user_id = users.id WHERE users.username='{username}' ORDER BY projects.created_at DESC LIMIT 1\""
    res = subprocess.check_output(cmd, shell=True).decode().strip()
    return res

def run_verification(page):
    ts = str(int(time.time()))
    username = f"dev_feat_{ts}"
    email = f"dev_feat_{ts}@nuvis-webbuilder.io"
    password = "securepass123"

    print("Navigating to Nuvis Webbuilder Landing Portal...")
    page.goto("http://127.0.0.1:8000/index.php")
    page.wait_for_timeout(1000)

    print("Switching to Registration View...")
    page.goto("http://127.0.0.1:8000/index.php?action=register")
    page.wait_for_timeout(1000)

    print(f"Registering new developer user: {username}...")
    page.fill("input[name='username']", username)
    page.fill("input[name='email']", email)
    page.fill("input[name='password']", password)
    page.fill("input[name='confirm_password']", password)
    page.wait_for_timeout(500)
    page.click("button[type='submit']")
    page.wait_for_timeout(1500)

    print("Logging into Nuvis Webbuilder...")
    page.fill("input[name='username_or_email']", username)
    page.fill("input[name='password']", password)
    page.wait_for_timeout(500)
    page.click("button[type='submit']")
    page.wait_for_timeout(1500)

    print("Switching Tabs: Templates Library Tab...")
    page.click("button:has-text('Templates Library')")
    page.wait_for_timeout(1000)

    print("Onboarding project from 'SaaS Product Landing Page' Theme Template...")
    page.click("button:has-text('Use Template Theme')")
    page.wait_for_timeout(3000) # Wait for clone process & builder workspace initialization
    page.screenshot(path="/home/jules/verification/screenshots/new_01_builder_init.png")

    # Test Component Search/Filter input
    print("Testing Component Search/Filter functionality...")
    search_input = page.locator("input[placeholder*='Search widgets']")
    search_input.fill("Alert")
    page.wait_for_timeout(1000)
    page.screenshot(path="/home/jules/verification/screenshots/new_02_search_alert.png")

    # Clear search
    print("Clearing search query...")
    search_input.fill("")
    page.wait_for_timeout(500)

    # Verify search displays expected elements
    print("Verifying various widgets in standard pre-built list...")
    for query in ["Alert", "Progress", "Countdown", "Social", "Box"]:
        search_input.fill(query)
        page.wait_for_timeout(500)
        # Ensure at least one item is displayed matching the search query
        count = page.locator(".truncate").count()
        print(f" - Search for '{query}': found {count} matching items.")
        if count == 0:
            raise Exception(f"Expected to find some pre-built items for '{query}' but found 0!")

    # Clear search query again
    search_input.fill("")
    page.wait_for_timeout(500)

    # Now let's test the "Clear Canvas" feature
    print("Testing 'Clear Canvas' interaction...")
    # Register dialog handler for confirm popup
    page.on("dialog", lambda dialog: dialog.accept())

    page.click("button:has-text('Clear Canvas')")
    page.wait_for_timeout(1500)
    page.screenshot(path="/home/jules/verification/screenshots/new_03_canvas_cleared.png")

    # Check if empty text is present or sections are cleared
    empty_title = page.locator("h3:has-text('Your Canvas is Empty')")
    if empty_title.is_visible():
        print("✔ Verified: Canvas is successfully empty after Clear Canvas action!")
    else:
        raise Exception("Canvas was not cleared or empty message was not shown.")

    # Test Undo (Ctrl+Z) to restore cleared canvas
    print("Testing Undo (Ctrl+Z) to restore sections...")
    page.keyboard.press("Control+z")
    page.wait_for_timeout(1500)
    page.screenshot(path="/home/jules/verification/screenshots/new_04_canvas_restored.png")

    # Verify if sections are restored
    if not empty_title.is_visible():
        print("✔ Verified: Canvas sections successfully restored via Ctrl+Z undo stack!")
    else:
        raise Exception("Undo did not restore canvas sections.")

    print("Saving modified draft layout...")
    page.click("button:has-text('Save Draft')")
    page.wait_for_timeout(1500)

    print("New features verification finished successfully!")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            record_video_dir="/home/jules/verification/videos"
        )
        page = context.new_page()
        page.on("console", lambda msg: print(f"BROWSER CONSOLE: {msg.text}"))
        page.on("pageerror", lambda err: print(f"BROWSER ERROR: {err}"))
        try:
            run_verification(page)
        except Exception as e:
            page.screenshot(path="/home/jules/verification/screenshots/new_error_state.png")
            raise e
        finally:
            context.close()
            browser.close()
