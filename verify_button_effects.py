import time
import subprocess
import os
from playwright.sync_api import sync_playwright

def run_button_effects_verification():
    ts = str(int(time.time()))[-6:]
    username = f"btneff_{ts}"
    email = f"btneff_{ts}@nuvis.io"
    password = "securepassword123"

    # Start PHP server
    php_proc = subprocess.Popen(["php", "-S", "127.0.0.1:8000"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    time.sleep(1)

    try:
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            page = browser.new_page()

            print(f"Registering user: {username}...")
            page.goto("http://127.0.0.1:8000/index.php?action=register")
            page.fill("input[name='username']", username)
            page.fill("input[name='email']", email)
            page.fill("input[name='password']", password)
            page.fill("input[name='confirm_password']", password)
            page.click("button[type='submit']")
            page.wait_for_timeout(1000)

            print("Logging in...")
            page.fill("input[name='username_or_email']", username)
            page.fill("input[name='password']", password)
            page.click("button[type='submit']")
            page.wait_for_timeout(2000)

            print("Onboarding project from Templates Library...")
            page.click("button:has-text('Templates Library')")
            page.wait_for_timeout(1000)
            page.click("button:has-text('Use Template Theme')")
            page.wait_for_timeout(3000)

            print("Adding Customizable Hero component from shelf...")
            page.click("text=Customizable Hero")
            page.wait_for_timeout(1000)

            print("Selecting Button Effect: Outer Neon Glow Effect...")
            page.select_option("select[id='prop-btnEffect']", "glow")
            page.wait_for_timeout(500)

            print("Saving and Publishing project...")
            page.click("button:has-text('Save Draft')")
            page.wait_for_timeout(1000)
            page.click("button:has-text('Publish Site')")
            page.wait_for_timeout(2000)

            # Get project slug
            cmd = f"mariadb -u builder_user -pbuilder_pass -D site_builder -N -e \"SELECT slug FROM projects JOIN users ON projects.user_id = users.id WHERE users.username='{username}' ORDER BY projects.created_at DESC LIMIT 1\""
            slug = subprocess.check_output(cmd, shell=True).decode().strip()
            print(f"Published slug: {slug}")

            print("Navigating to published site in render.php...")
            page.goto(f"http://127.0.0.1:8000/render.php?slug={slug}&user={username}")
            page.wait_for_timeout(1500)

            print("Verifying glow effect class in published HTML...")
            content = page.content()
            assert "shadow-[0_0_25px_rgba(20,184,166,0.7)]" in content

            page.screenshot(path="button_effects_verification.png")
            print("Verification successful! Glow button effect verified in published HTML.")
            browser.close()
    finally:
        php_proc.terminate()

if __name__ == "__main__":
    run_button_effects_verification()
