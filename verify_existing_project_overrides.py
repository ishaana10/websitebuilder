import os
import time
import json
import subprocess
from playwright.sync_api import sync_playwright

def get_project_id_and_slug(username):
    cmd = f"mariadb -u builder_user -pbuilder_pass -D site_builder -N -e \"SELECT projects.id, projects.slug FROM projects JOIN users ON projects.user_id = users.id WHERE users.username='{username}' ORDER BY projects.id DESC LIMIT 1\""
    res = subprocess.check_output(cmd, shell=True).decode().strip().split('\t')
    return res[0], res[1]

def test_existing_project_overrides():
    os.makedirs('/home/jules/verification/screenshots', exist_ok=True)
    ts = str(int(time.time()))
    username = f"exuser_{ts}"
    email = f"exuser_{ts}@example.com"
    password = "password123"

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(viewport={'width': 1280, 'height': 800})
        page = context.new_page()

        print("Navigating to index.php?action=register...")
        page.goto("http://127.0.0.1:8000/index.php?action=register")
        page.wait_for_timeout(1000)

        print(f"Registering user: {username}...")
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

        # Create an existing project via SQL with stale element_overrides that set display:none / hidden:true on link indices 3 and 4!
        print("Injecting existing project with stale element_overrides into database...")
        existing_content = {
            "pages": {
                "index": [
                    {
                        "id": "sec-navbar-100",
                        "type": "navbar",
                        "props": {
                            "brandText": "Nuvis Existing Project",
                            "bgColor": "#0f172a",
                            "textColor": "#ffffff",
                            "accentColor": "#14b8a6",
                            "links": [
                                {"text": "Home", "type": "link", "url": "#home"},
                                {"text": "Features", "type": "link", "url": "#features"},
                                {"text": "About Us", "type": "page", "pageName": "aboutus"},
                                {"text": "Contact", "type": "page", "pageName": "contact"},
                                {"text": "New Tab 5", "type": "link", "url": "#newtab"}
                            ]
                        },
                        "style": {"classes": []},
                        # Stale element overrides mimicking old saved state
                        "element_overrides": {
                            "el-navlink-3": {"hidden": True},
                            "el-navlink-mobile-3": {"styles": {"display": "none"}},
                            "el-navlink-4": {"hidden": True},
                            "el-3": {"hidden": True},
                            "el-4": {"hidden": True}
                        }
                    }
                ]
            },
            "activePage": "index"
        }

        content_json_str = json.dumps(existing_content)

        # Save to file and import via mariadb
        sql_script = f"/tmp/insert_project_{ts}.sql"
        with open(sql_script, "w") as f:
            f.write("USE site_builder;\n")
            f.write(f"INSERT INTO projects (user_id, name, slug, description, content_json, published_html, status) VALUES ((SELECT id FROM users WHERE username='{username}'), 'Existing Stale Project', 'existing-stale-project', 'Testing existing project sanitization', '{content_json_str.replace("'", "''")}', '', 'draft');\n")

        subprocess.check_call(f"mariadb -u builder_user -pbuilder_pass < {sql_script}", shell=True)
        proj_id, slug = get_project_id_and_slug(username)
        print(f"Injected project ID: {proj_id}, Slug: {slug}")

        # Open existing project in builder.php
        print("Opening existing project in builder workspace...")
        page.goto(f"http://127.0.0.1:8000/builder.php?project_id={proj_id}")

        print("Waiting for builder UI...")
        page.wait_for_selector("text='Save Draft'", timeout=30000)
        page.wait_for_timeout(2000)

        # Save draft first to trigger sanitization and save
        print("Saving draft...")
        page.click("button:has-text('Save Draft')")
        page.wait_for_timeout(2000)

        print("Publishing site...")
        page.click("button:has-text('Publish Site')")
        page.wait_for_timeout(3000)

        proj_id, slug = get_project_id_and_slug(username)

        # Load published page in mobile viewport
        mobile_context = browser.new_context(viewport={'width': 375, 'height': 812})
        mob_page = mobile_context.new_page()

        user_page_url = f"http://127.0.0.1:8000/render.php?slug={slug}&user={username}"
        print(f"Navigating to existing project published page: {user_page_url}")
        mob_page.goto(user_page_url)
        mob_page.wait_for_timeout(2000)

        # Click burger menu
        pub_burger = mob_page.locator("[data-component='navbar'] button:has(.fa-bars)").first
        if pub_burger.is_visible():
            pub_burger.click()
            mob_page.wait_for_timeout(1000)

        mobile_menu_html = mob_page.locator(".mobile-menu").inner_html()
        print("MOBILE MENU HTML:")
        print(mobile_menu_html)

        # Verify Contact link (index 3) and New Tab 5 (index 4) are VISIBLE
        contact_link = mob_page.locator(".mobile-menu a", has_text="Contact").first
        assert contact_link.is_visible(), "Contact link MUST be visible and NOT display:none!"
        print("✔ Verified: Contact link renders VISIBLE in existing project mobile view!")

        new_tab_link = mob_page.locator(".mobile-menu a", has_text="New Tab 5").first
        assert new_tab_link.is_visible(), "New Tab 5 MUST be visible and NOT display:none!"
        print("✔ Verified: Newly added Tab 5 renders VISIBLE in existing project mobile view!")

        screenshot_path = "/home/jules/verification/screenshots/existing_project_sanitized.png"
        mob_page.screenshot(path=screenshot_path)
        print(f"Screenshot captured at: {screenshot_path}")

        mobile_context.close()
        context.close()
        browser.close()
        print("ALL EXISTING PROJECT SANITIZATION VERIFICATION CHECKS PASSED!")

if __name__ == "__main__":
    test_existing_project_overrides()
