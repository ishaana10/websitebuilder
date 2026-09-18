from playwright.sync_api import sync_playwright
import time
import os
import sqlite3

def get_latest_slug(username):
    conn = sqlite3.connect('site_builder.sqlite')
    conn.row_factory = sqlite3.Row
    cursor = conn.cursor()
    row = cursor.execute("""
        SELECT projects.slug FROM projects
        JOIN users ON projects.user_id = users.id
        WHERE users.username = ?
        ORDER BY projects.id DESC LIMIT 1
    """, (username,)).fetchone()
    conn.close()
    return row['slug'] if row else None

def run_verification(page):
    ts = str(int(time.time()))
    username = f"pay_user_{ts}"
    email = f"pay_user_{ts}@nuvis-webdesign-x.io"
    password = "securepass123"

    os.makedirs("/home/jules/verification/screenshots", exist_ok=True)

    print("Navigating to Landing Page...")
    page.goto("http://127.0.0.1:8000/index.php")
    page.wait_for_timeout(1000)

    print("Navigating to Registration...")
    page.goto("http://127.0.0.1:8000/index.php?action=register")
    page.wait_for_timeout(1000)

    print(f"Registering user: {username}...")
    page.fill("input[name='username']", username)
    page.fill("input[name='email']", email)
    page.fill("input[name='password']", password)
    page.fill("input[name='confirm_password']", password)
    page.wait_for_timeout(500)
    page.click("button[type='submit']")
    page.wait_for_timeout(1500)

    print("Logging into Nuvis WebDesign X...")
    page.fill("input[name='username_or_email']", username)
    page.fill("input[name='password']", password)
    page.wait_for_timeout(500)
    page.click("button[type='submit']")
    page.wait_for_timeout(1500)

    print("Selecting Templates Library...")
    page.click("button:has-text('Templates Library')")
    page.wait_for_timeout(1000)

    print("Loading Theme Template...")
    page.click("button:has-text('Use Template Theme')")
    page.wait_for_timeout(3000)

    # Search for PayPal / Payment component in sidebar
    print("Searching for 'PayPal' in component shelf...")
    search_input = page.locator("input[placeholder*='Search widgets']")
    search_input.fill("PayPal")
    page.wait_for_timeout(1000)
    page.screenshot(path="/home/jules/verification/screenshots/01_payment_search.png")

    print("Adding 'PayPal & Payment Gateway Checkout' component to canvas...")
    page.click("text=PayPal & Payment Gateway Checkout")
    page.wait_for_timeout(1500)
    page.screenshot(path="/home/jules/verification/screenshots/02_payment_added_canvas.png")

    # Verify component is present in canvas
    payment_sec = page.locator("[data-component='payment_checkout']")
    if not payment_sec.is_visible():
        raise Exception("payment_checkout component is not visible on canvas!")

    print("✔ Verified: payment_checkout component added to canvas in Builder Mode.")

    print("Saving project draft...")
    page.click("button:has-text('Save Draft')")
    page.wait_for_timeout(1500)

    print("Publishing site...")
    page.click("button:has-text('Publish Site')")
    page.wait_for_timeout(3000)

    slug = get_latest_slug(username)
    print(f"Resolved published site slug: {slug}")

    print("Navigating to rendered live website...")
    page.goto(f"http://127.0.0.1:8000/render.php?slug={slug}&user={username}")
    page.wait_for_timeout(1500)
    page.screenshot(path="/home/jules/verification/screenshots/03_rendered_payment_page.png")

    live_payment_sec = page.locator("[data-component='payment_checkout']")
    if not live_payment_sec.is_visible():
        raise Exception("payment_checkout component is not visible on rendered live page!")

    print("Testing tab switching on live page...")
    page.click("button:has-text('Credit Card')")
    page.wait_for_timeout(500)
    page.screenshot(path="/home/jules/verification/screenshots/04_live_credit_card_tab.png")

    page.click("button:has-text('Bank Wire')")
    page.wait_for_timeout(500)
    page.screenshot(path="/home/jules/verification/screenshots/05_live_bank_wire_tab.png")

    page.click("button:has-text('Credit Card')")
    page.wait_for_timeout(500)

    name_field = live_payment_sec.locator("input[name='customer_name']")
    email_field = live_payment_sec.locator("input[name='customer_email']")

    name_field.fill("Alice Test")
    email_field.fill("alice@example.com")

    print("Submitting test Credit Card payment on live rendered page...")
    submit_btn = live_payment_sec.locator("button[type='submit']")
    submit_btn.click()
    page.wait_for_timeout(2000)
    page.screenshot(path="/home/jules/verification/screenshots/06_live_payment_submitted.png")

    status_msg = live_payment_sec.locator(".payment-status-message")
    msg_text = status_msg.inner_text()
    print(f"Status Message: {msg_text}")

    if "processed successfully" not in msg_text.lower():
        raise Exception(f"Payment submission failed with message: {msg_text}")

    print("✔ Verified: Live payment checkout processed successfully!")
    print("All payment checkout verification steps completed successfully!")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context()
        page = context.new_page()
        page.on("console", lambda msg: print(f"CONSOLE: {msg.text}"))
        page.on("pageerror", lambda err: print(f"ERROR: {err}"))
        try:
            run_verification(page)
        finally:
            context.close()
            browser.close()
