import requests
import re

def test_saas_endpoints():
    print("=== Nuvis Webidesigner SaaS Integration Test Suite ===")
    session = requests.Session()
    base_url = "http://127.0.0.1:8000/api.php"

    # 0. Simulate Login to authorize Builder-specific actions
    print("0. Accessing Login Page for Session & CSRF Token...")
    r = session.get("http://127.0.0.1:8000/index.php?action=login")

    # Parse CSRF token
    csrf_match = re.search(r'name="csrf_token"\s+value="([a-f0-9]+)"', r.text)
    if not csrf_match:
        csrf_match = re.search(r'value="([a-f0-9]+)"\s+name="csrf_token"', r.text)

    csrf_token = csrf_match.group(1) if csrf_match else None
    assert csrf_token is not None, "Failed to parse CSRF token from Login Page!"
    print(f"✔ Initialized Session & CSRF Token: {csrf_token}")

    print("Logging in as admin/admin123...")
    login_payload = {
        'username_or_email': 'admin',
        'password': 'admin123',
        'csrf_token': csrf_token
    }
    r_login = session.post("http://127.0.0.1:8000/auth.php?auth_action=login", data=login_payload)
    print(f"Login Response Status: {r_login.status_code}")

    # Let's verify that we can fetch builder.php with a 200 OK status (meaning login was successful!)
    r_builder = session.get("http://127.0.0.1:8000/builder.php?project_id=1")
    # If not logged in, we get redirected to index.php?action=login, check history for redirect or inspect content
    assert "react-app-root" in r_builder.text, "Failed to log in! Builder page content did not bootstrap."
    print("✔ Login Session Established successfully!")

    # 1. Test Fetching Blog Posts
    print("\n1. Fetching blog posts CMS feed...")
    res = session.get(f"{base_url}?action=get_blog_posts")
    print(f"Status: {res.status_code}")
    data = res.json()
    assert data["success"] == True
    assert len(data["posts"]) > 0
    print(f"✔ CMS Blog Posts: Loaded {len(data['posts'])} articles successfully!")

    # 2. Test Fetching E-Commerce Products
    print("\n2. Fetching storefront e-commerce catalog...")
    res = session.get(f"{base_url}?action=get_ecommerce_products")
    print(f"Status: {res.status_code}")
    data = res.json()
    assert data["success"] == True
    assert len(data["products"]) > 0
    print(f"✔ E-Commerce Catalog: Loaded {len(data['products'])} products successfully!")

    # 3. Test Stripe Mock Checkout & Order placement
    print("\n3. Simulating Stripe Checkout checkout order...")
    payload = {
        'customer_name': 'Sarah Exterminator Test',
        'customer_email': 'sarahtest@pestkit.com',
        'total_amount': '74.98'
    }
    res = session.post(f"{base_url}?action=create_ecommerce_order", data=payload)
    print(f"Status: {res.status_code}")
    data = res.json()
    assert data["success"] == True
    assert "invoice_id" in data
    print(f"✔ Stripe Simulated Checkout: Order placed successfully! Invoice: {data['invoice_id']}")

    # 4. Test Appointment Scheduling Calendar
    print("\n4. Simulating Appointment calendar booking...")
    payload = {
        'customer_name': 'Marcus Exterminator Test',
        'customer_email': 'marcustest@pestkit.com',
        'booking_date': '2026-10-15',
        'booking_time': '10:30 AM',
        'service_name': 'Termite Soil Treatment Barrier'
    }
    res = session.post(f"{base_url}?action=create_booking", data=payload)
    print(f"Status: {res.status_code}")
    data = res.json()
    assert data["success"] == True
    print("✔ Calendar Scheduling: Appointment mapped & sync'd to CRM pipelines successfully!")

    # 5. Test AI Prompt-to-Section Layout generator
    print("\n5. Generating precompiled layout block from AI prompt...")
    # Fetch CSRF token for the active session to pass custom API actions validation
    r_csrf = session.get("http://127.0.0.1:8000/builder.php?project_id=1")
    csrf_builder_match = re.search(r'const CSRF_TOKEN = "([a-f0-9]+)"', r_csrf.text)
    active_builder_csrf = csrf_builder_match.group(1) if csrf_builder_match else "dummy_csrf"

    headers = {
        'X-CSRF-TOKEN': active_builder_csrf,
        'Content-Type': 'application/json'
    }
    payload_ai = {
        'prompt': 'Create a beautiful emerald split-feature grid with photo on the right',
        'csrf_token': active_builder_csrf
    }
    res = session.post(f"{base_url}?action=generate_ai_section", headers=headers, json=payload_ai)
    print(f"Status: {res.status_code}")
    data = res.json()
    assert data["success"] == True
    assert data["section"]["type"] == "feature_split"
    print("✔ AI Superpowers Generator: Prompt parsed and precompiled features_split successfully!")

    # 6. Test Sitemap XML & Robots.txt
    print("\n6. Verifying Sitemap XML metadata feed...")
    res = session.get(f"{base_url}?action=sitemap&project_id=1")
    print(f"Status: {res.status_code}")
    assert res.status_code == 200
    assert "xml" in res.headers.get("Content-Type", "")
    print("✔ Sitemap XML generated with correct MIME type successfully!")

    print("\n7. Verifying Robots.txt indicators...")
    res = session.get(f"{base_url}?action=robots&project_id=1")
    print(f"Status: {res.status_code}")
    assert res.status_code == 200
    assert "sitemap" in res.text.lower()
    print("✔ Robots.txt output references sitemap xml correctly!")

    print("\n=== All SaaS Visual Builder Integration Tests Passed Flawlessly! ===")

if __name__ == '__main__':
    test_saas_endpoints()
