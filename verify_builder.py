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
    username = f"dev_{ts}"
    email = f"dev_{ts}@webcraft.io"
    password = "securepass123"

    print("Navigating to WebCraft Landing Portal...")
    page.goto("http://127.0.0.1:8000/index.php")
    page.wait_for_timeout(1000)
    page.screenshot(path="/home/jules/verification/screenshots/1_landing_portal.png")

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

    print("Logging into WebCraft...")
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

    print("Interacting with the visual hero component...")
    page.click("div[data-component-instance='hero']")
    page.wait_for_timeout(1000)

    print("Updating heading via live properties customizer...")
    page.fill("input[id='prop-heading-text']", "Supercharged Commercial Solutions")
    page.wait_for_timeout(1000)

    print("Saving draft layouts...")
    page.click("button:has-text('Save Draft')")
    page.wait_for_timeout(1500)

    print("Publishing website compilers and caching production static HTML...")
    page.click("button:has-text('Publish Site')")
    page.wait_for_timeout(3000)

    # Fetch exact slug
    slug = get_latest_slug(username)
    print(f"Resolved latest compiled project slug: {slug}")

    print("Navigating to compiled webpage preview...")
    page.goto(f"http://127.0.0.1:8000/render.php?slug={slug}&user={username}")
    page.wait_for_timeout(1500)
    page.screenshot(path="/home/jules/verification/screenshots/13_rendered_page.png")

    # Interactive chatbot testing
    print("Testing Floating AI Chatbot widget interactions...")
    # Chatbot button click
    page.click("div[data-component='chatbot'] button")
    page.wait_for_timeout(1000)
    page.screenshot(path="/home/jules/verification/screenshots/14_chatbot_open.png")

    print("Sending message to AI bot...")
    page.fill("input[name='chat_msg']", "What is the cost of enterprise plans?")
    page.wait_for_timeout(500)
    page.click("form[onsubmit*='sendWebCraftChatMessage'] button[type='submit']")
    page.wait_for_timeout(2000) # Wait for AI simulated reply
    page.screenshot(path="/home/jules/verification/screenshots/15_chatbot_replied.png")

    # Interactive Contact form submission testing
    print("Submitting the public contact form...")
    page.fill("input[name='name']", "Enterprise Customer")
    page.fill("input[name='email']", "customer@enterprise.com")
    page.fill("textarea[name='message']", "I am highly interested in purchasing the Enterprise Pro licenses. Please notify me!")
    page.wait_for_timeout(1000)
    page.screenshot(path="/home/jules/verification/screenshots/16_filled_contact_form.png")

    page.click("form[onsubmit*='submitWebCraftForm'] button[type='submit']")
    page.wait_for_timeout(3000) # Wait for secure AJAX process & success animation alert
    page.screenshot(path="/home/jules/verification/screenshots/17_contact_form_success.png")

    # Verify that contact submissions are displayed inside the Admin Panel
    print("Navigating back to the Admin Dashboard...")
    page.goto("http://127.0.0.1:8000/admin.php")
    page.wait_for_timeout(1500)

    print("Switching Tabs: Form Submissions Tab...")
    page.click("button:has-text('Form Submissions')")
    page.wait_for_timeout(1500)
    page.screenshot(path="/home/jules/verification/screenshots/18_admin_submissions_logs.png")

    print("Verification completed successfully!")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            record_video_dir="/home/jules/verification/videos"
        )
        page = context.new_page()
        try:
            run_verification(page)
        except Exception as e:
            page.screenshot(path="/home/jules/verification/screenshots/error_state.png")
            raise e
        finally:
            context.close()
            browser.close()
