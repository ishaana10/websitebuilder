import time
import subprocess
import os
from playwright.sync_api import sync_playwright

def run_gallery_verification():
    ts = str(int(time.time()))[-6:]
    username = f"devgal_{ts}"
    email = f"devgal_{ts}@nuvis.io"
    password = "securepassword123"

    # Start PHP server
    php_proc = subprocess.Popen(["php", "-S", "127.0.0.1:8000"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    time.sleep(1)

    # Create dummy image file for upload testing
    test_img_path = "/tmp/test_gallery_upload.png"
    with open(test_img_path, "wb") as f:
        f.write(b"\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15c4\x00\x00\x00\rIDATx\x9cc`\x00\x00\x00\x02\x00\x01H\xafA\x04\x00\x00\x00\x00IEND\xaeB`\x82")

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

            print("Adding Image Gallery component...")
            page.click("text=Visual Portfolio Image Gallery")
            page.wait_for_timeout(1500)

            print("Checking sidebar layoutMode select...")
            page.select_option("select[id='prop-layoutMode']", "sidescroll")
            page.wait_for_timeout(500)

            overlay_inputs = page.locator("input[value='View Image']")
            if overlay_inputs.count() > 0:
                overlay_inputs.first.fill("Zoom Photo")
                page.wait_for_timeout(500)

            print("Testing image file upload for first gallery item...")
            file_input = page.locator("input[type='file'][accept='image/*']").first
            file_input.set_input_files(test_img_path)
            page.wait_for_timeout(2000)

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

            print("Verifying uploaded image path in published HTML...")
            assert "uploads/" in page.content()
            assert "Zoom Photo" in page.content()

            print("Clicking gallery image overlay to verify lightbox modal...")
            page.click("text=Zoom Photo")
            page.wait_for_timeout(500)

            print("Verifying lightbox modal exists and is visible...")
            lightbox = page.query_selector("#nuvis-image-lightbox-modal")
            assert lightbox is not None
            assert "opacity-100" in lightbox.get_attribute("class")

            page.screenshot(path="image_gallery_verification.png")
            print("Verification successful! Uploaded image & lightbox modal verified.")
            browser.close()
    finally:
        php_proc.terminate()
        if os.path.exists(test_img_path):
            os.remove(test_img_path)

if __name__ == "__main__":
    run_gallery_verification()
