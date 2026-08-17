import asyncio
from playwright.async_api import async_playwright

async def run():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context()
        page = await context.new_page()

        print("1. Logging into Nuvis Webidesigner...")
        await page.goto("http://127.0.0.1:8000/index.php")
        await page.fill('input[name="username_or_email"]', "admin")
        await page.fill('input[name="password"]', "admin123")
        await page.click('button[type="submit"]')
        await page.wait_for_selector("text=Dashboard")
        print("Logged in successfully!")

        print("2. Opening builder for newly created project...")
        page.on("console", lambda msg: print(f"BROWSER CONSOLE: {msg.type} {msg.text}"))
        # Click "New Website" button in header
        await page.click("text=New Website")
        await page.fill('input[name="project_name"]', "Google AI Test Site")
        await page.click('button:has-text("Start Coding")')
        await page.wait_for_selector("#react-app-root")
        await page.wait_for_selector("text=Components Shelf", timeout=15000)

        print("3. Searching for Google AI Agent Chatbot in components shelf...")
        await page.fill('input[placeholder*="Search widgets"]', "Google AI")
        await page.wait_for_selector("text=Google AI Agent Chatbot")

        print("4. Adding Google AI Agent Chatbot to canvas...")
        await page.click("text=Google AI Agent Chatbot")
        await page.wait_for_selector('[data-component-instance="google_chatbot"]')
        print("Google AI Agent Chatbot successfully added to canvas!")

        print("5. Clicking chatbot to open Properties Panel...")
        await page.click('[data-component-instance="google_chatbot"]')
        await page.wait_for_selector("text=Edit Properties")

        print("6. Changing Bot Name / Title property...")
        await page.fill('input#prop-agentName', "My Automated Google AI Bot")

        print("7. Saving draft and publishing site...")
        await page.click("text=Save Draft")
        await page.wait_for_timeout(1000)
        await page.click("text=Publish Site")
        await page.wait_for_selector("text=Published Successfully!")
        print("Site published successfully!")

        print("8. Navigating to published site render page...")
        await page.goto("http://127.0.0.1:8000/admin.php")
        await page.click("text=My Websites")
        # Get href of View Live link
        href = await page.get_attribute('text=View Live >> nth=0', 'href')
        print(f"Direct render URL: {href}")
        await page.goto(f"http://127.0.0.1:8000/{href}")
        await page.wait_for_selector('[data-component="google_chatbot"]')

        print("9. Interacting with Google Chatbot on published site...")
        # Click toggle button
        await page.click('.google-chat-toggle-btn')
        await page.wait_for_selector('.google-chat-window:not(.hidden)')

        # Verify custom agent name in chat window header
        header_text = await page.inner_text('.google-chat-window')
        print(f"Header text is: {header_text}")
        assert "MY AUTOMATED GOOGLE AI BOT" in header_text.upper()
        print("Verified custom Bot Name in Chat window header!")

        # Send a chat message by pressing Enter
        await page.fill('input[name="chat_msg"]', "hello Google AI agent")
        await page.press('input[name="chat_msg"]', 'Enter')

        # Wait for reply bubble
        await page.wait_for_selector('.google-chat-logs div:has-text("Hello there!")', timeout=5000)
        print("Received AI agent response on published site successfully!")

        # Take screenshot for verification
        await page.screenshot(path="google_chatbot_verification.png")
        print("Screenshot saved to google_chatbot_verification.png")

        await browser.close()

if __name__ == "__main__":
    asyncio.run(run())
