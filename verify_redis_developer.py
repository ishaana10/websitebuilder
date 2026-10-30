import urllib.request
import urllib.parse
import http.cookiejar
import re
import json

def verify_redis_and_developer_tab():
    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

    print("1. Accessing Login Page for Session & CSRF Token...")
    index_html = opener.open('http://127.0.0.1:8000/index.php?action=login').read().decode('utf-8')
    csrf_match = re.search(r'name=\"csrf_token\"\s+value=\"([a-f0-9]+)\"', index_html)
    if not csrf_match:
        csrf_match = re.search(r'value=\"([a-f0-9]+)\"\s+name=\"csrf_token\"', index_html)
    csrf_token = csrf_match.group(1) if csrf_match else ''
    print(f"CSRF Token: {csrf_token}")

    print("\n2. Logging in as admin...")
    login_data = urllib.parse.urlencode({
        'username_or_email': 'admin',
        'password': 'admin123',
        'csrf_token': csrf_token
    }).encode('utf-8')
    req = urllib.request.Request('http://127.0.0.1:8000/auth.php?auth_action=login', data=login_data)
    resp = opener.open(req)

    cookies = {cookie.name: cookie.value for cookie in cj}
    print("Active session cookies:", cookies)
    assert 'nuviswebdesignxssid' in cookies, "Cookie 'nuviswebdesignxssid' not found in session!"
    print("SUCCESS: Session cookie 'nuviswebdesignxssid' verified.")

    print("\n3. Verifying Developer tab and Redis panel in admin.php...")
    admin_html = opener.open('http://127.0.0.1:8000/admin.php').read().decode('utf-8')
    assert 'REDIS HIGH-PERFORMANCE CACHE &amp; SESSION MANAGEMENT STATUS' in admin_html or 'REDIS HIGH-PERFORMANCE CACHE & SESSION MANAGEMENT STATUS' in admin_html, "Redis status header not found in HTML"
    assert 'tab-developer' in admin_html, "tab-developer container not found in HTML"
    assert 'Developer Options' in admin_html, "Developer Options navigation button not found in HTML"
    print("SUCCESS: Admin panel contains Developer Options tab and Redis diagnostics panel HTML.")

    print("\n4. Testing Redis Status API action...")
    status_resp = opener.open('http://127.0.0.1:8000/actions/redis_actions.php?action=get_redis_status').read().decode('utf-8')
    print("Status response:", status_resp)
    status_json = json.loads(status_resp)
    assert status_json.get('success') is True, "get_redis_status API call failed"
    assert status_json.get('socket_path') == '/home/ictfjcom/.redis/redis.sock', "Unexpected socket path"
    print("SUCCESS: get_redis_status returned valid diagnostic payload.")

    print("\n5. Testing Redis Ping API action...")
    ping_resp = opener.open('http://127.0.0.1:8000/actions/redis_actions.php?action=test_redis_ping').read().decode('utf-8')
    print("Ping response:", ping_resp)

    print("\n6. Testing Flush Cache API action...")
    flush_resp = opener.open('http://127.0.0.1:8000/actions/redis_actions.php?action=flush_cache').read().decode('utf-8')
    print("Flush response:", flush_resp)

    print("\nALL VERIFICATION CHECKS PASSED SUCCESSFULLY!")

if __name__ == '__main__':
    verify_redis_and_developer_tab()
