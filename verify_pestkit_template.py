from playwright.sync_api import sync_playwright
import time
import os
import subprocess

def get_latest_slug(username):
    cmd = f"mariadb -u builder_user -pbuilder_pass -D site_builder -N -e \"SELECT slug FROM projects JOIN users ON projects.user_id = users.id WHERE users.username='{username}' ORDER BY projects.created_at DESC LIMIT 1\""
    res = subprocess.check_output(cmd, shell=True).decode().strip()
    return res

def run_pestkit_verification(page):
    ts = str(int(time.time()))
    username = f"dev_pest_{ts}"
    email = f"dev_pest_{ts}@nuvis-webidesigner.io"
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

    print("Onboarding project from 'PestKit Pest Control Demo' Theme Template...")
    # Find card containing PestKit Pest Control Demo and click its button
    pest_card_btn = page.locator("button[onclick*='PestKit Pest Control Demo']")
    pest_card_btn.click()
    page.wait_for_timeout(5000) # Wait for clone process & builder workspace initialization

    print("Verifying builder canvas has loaded the multi-block PestKit sections...")
    page.screenshot(path="/home/jules/verification/screenshots/pest_01_builder_init.png")

    print("Saving draft layouts...")
    page.click("button:has-text('Save Draft')")
    page.wait_for_timeout(1500)

    print("Publishing website compilers and caching production static HTML...")
    page.click("button:has-text('Publish Site')")
    page.wait_for_timeout(3000)

    # Fetch exact slug
    slug = get_latest_slug(username)
    print(f"Resolved latest compiled PestKit project slug: {slug}")

    print("Navigating to compiled webpage preview...")
    page.goto(f"http://127.0.0.1:8000/render.php?slug={slug}&user={username}")
    page.wait_for_timeout(2000)
    page.screenshot(path="/home/jules/verification/screenshots/pest_02_rendered_page.png")

    # Verify key brand and page content is rendered
    print("Verifying PestKit brand text is rendered on page...")
    brand_present = page.locator("span:has-text('PestKit')").count() > 0
    title_present = page.locator("h1:has-text('Enjoy Your Home')").count() > 0
    print(f" - Brand 'PestKit' found: {brand_present}")
    print(f" - Main header found: {title_present}")
    if not brand_present or not title_present:
        raise Exception("PestKit brand markings or hero headers are missing from the production build!")

    # Test Contact form submission on PestKit page
    print("Submitting the PestKit Contact form...")
    page.fill("input[placeholder='John Doe']", "Pest Extermination Lead")
    page.fill("input[placeholder='email@address.com']", "vance@pestkit.com")
    page.fill("textarea[placeholder*='Briefly describe']", "We have a severe wasp problem in the warehouse eaves. Wasp control requested.")
    page.wait_for_timeout(1000)
    page.screenshot(path="/home/jules/verification/screenshots/pest_03_filled_form.png")

    page.click("form[onsubmit*='submitNuvisWebidesignerForm'] button[type='submit']")
    page.wait_for_timeout(3000) # Wait for secure AJAX process & success animation alert
    page.screenshot(path="/home/jules/verification/screenshots/pest_04_form_success.png")

    # Verify that contact submissions are displayed inside the Admin Panel
    print("Navigating back to the Admin Dashboard...")
    page.goto("http://127.0.0.1:8000/admin.php")
    page.wait_for_timeout(1500)

    print("Switching Tabs: Form Submissions Tab...")
    page.click("button:has-text('Form Submissions')")
    page.wait_for_timeout(1500)
    page.screenshot(path="/home/jules/verification/screenshots/pest_05_admin_submissions_logs.png")

    print("Verification completed successfully!")

if __name__ == "__main__":
    os.makedirs("/home/jules/verification/screenshots", exist_ok=True)
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            record_video_dir="/home/jules/verification/videos"
        )
        page = context.new_page()
        page.on("console", lambda msg: print(f"BROWSER CONSOLE: {msg.text}"))
        page.on("pageerror", lambda err: print(f"BROWSER ERROR: {err}"))
        try:
            run_pestkit_verification(page)
        except Exception as e:
            page.screenshot(path="/home/jules/verification/screenshots/pest_error_state.png")
            raise e
        finally:
            context.close()
            browser.close()
