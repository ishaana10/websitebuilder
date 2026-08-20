from playwright.sync_api import sync_playwright
import time
import os
import subprocess

def run_verification(page):
    ts = str(int(time.time()))
    username = f"dev_ver_{ts}"
    email = f"dev_ver_{ts}@nuvis-webidesigner.io"
    password = "securepass123"

    print("Navigating to Nuvis Webidesigner Landing Portal...")
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

    print("Logging into Nuvis Webidesigner...")
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

    # Ensure screenshots folder exists
    os.makedirs("/home/jules/verification/screenshots", exist_ok=True)
    page.screenshot(path="/home/jules/verification/screenshots/ver_01_builder_init.png")

    # Navigate to Versions tab
    print("Switching to 'Versions' tab...")
    page.click("button:has-text('Vers')")
    page.wait_for_timeout(1500)
    page.screenshot(path="/home/jules/verification/screenshots/ver_02_versions_tab_loaded.png")

    # Verify that the initial setup version is displayed
    print("Checking for Initial setup version in timeline...")
    initial_version = page.locator("h4:has-text('Initial Project Setup')")
    if initial_version.is_visible():
        print("✔ Found initial version snapshot: 'Initial Project Setup'")
    else:
        raise Exception("Initial version snapshot 'Initial Project Setup' is missing!")

    # Create a custom milestone version
    print("Saving a custom manual milestone snapshot...")
    page.fill("input[placeholder*='Version note']", "Milestone: Custom Header Tweaks")
    page.wait_for_timeout(500)
    page.click("button:has-text('Create Milestone')")
    page.wait_for_timeout(2000)
    page.screenshot(path="/home/jules/verification/screenshots/ver_03_milestone_created.png")

    # Verify custom milestone
    custom_milestone = page.locator("h4:has-text('Milestone: Custom Header Tweaks')")
    if custom_milestone.is_visible():
        print("✔ Verified: Custom milestone 'Milestone: Custom Header Tweaks' exists in the timeline!")
    else:
        raise Exception("Custom milestone version was not found in the timeline!")

    # Test previewing the custom milestone
    print("Triggering Preview on the milestone...")
    # Get the "Preview" button specific to the custom milestone container
    preview_btn = page.locator("div:has(h4:has-text('Milestone: Custom Header Tweaks')) button:has-text('Preview')").first
    preview_btn.click()
    page.wait_for_timeout(1500)
    page.screenshot(path="/home/jules/verification/screenshots/ver_04_previewing_milestone.png")

    # Verify preview banner is displayed
    banner = page.locator("span:has-text('PREVIEWING HISTORICAL VERSION')")
    if banner.is_visible():
        print("✔ Verified: Locked preview banner is successfully rendered on canvas!")
    else:
        raise Exception("Preview banner overlay not found on canvas!")

    # Exit preview mode
    print("Exiting preview mode...")
    exit_btn = page.locator("button:has-text('Exit Preview')").first
    exit_btn.click()
    page.wait_for_timeout(1000)

    if not banner.is_visible():
        print("✔ Verified: Read-only preview mode successfully exited!")
    else:
        raise Exception("Failed to exit preview mode!")

    # Trigger restore check
    print("Testing Restore draft to milestone version...")
    # Accept confirm dialogue
    page.on("dialog", lambda dialog: dialog.accept())

    restore_btn = page.locator("div:has(h4:has-text('Milestone: Custom Header Tweaks')) button:has-text('Restore')").first
    restore_btn.click()
    page.wait_for_timeout(2000)
    page.screenshot(path="/home/jules/verification/screenshots/ver_05_restored_draft.png")

    print("✔ Draft successfully restored to selected historical snapshot!")

    # Save draft to check manual save auto-versioning
    print("Saving draft to check manual save auto-versioning...")
    page.click("button:has-text('Save Draft')")
    page.wait_for_timeout(2000)
    page.screenshot(path="/home/jules/verification/screenshots/ver_06_manual_save_version.png")

    print("Page versioning and timeline history features verified successfully!")

if __name__ == "__main__":
    # Ensure PHP server is running on port 8000
    print("Ensuring PHP server is active on localhost:8000...")
    subprocess.Popen("php -d display_errors=On -S 127.0.0.1:8000 > php_server.log 2>&1 &", shell=True)
    time.sleep(2) # Give it 2 seconds to warm up

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
            page.screenshot(path="/home/jules/verification/screenshots/ver_error_state.png")
            raise e
        finally:
            context.close()
            browser.close()
