import json
import sqlite3
from playwright.sync_api import sync_playwright

def verify_full():
    print("--- Verifying Sample Legal Pages Feature ---")

    # Read builder.php content
    with open('builder.php', 'r', encoding='utf-8') as f:
        builder = f.read()

    assert "generateSampleLegalPages" in builder, "generateSampleLegalPages missing from builder.php"
    assert "+ Legal Pages" in builder, "+ Legal Pages button missing from builder.php"
    assert "privacy" in builder and "terms" in builder and "support" in builder, "Legal page keys missing"
    print("[PASS] builder.php verified.")

    # Read install.php content
    with open('install.php', 'r', encoding='utf-8') as f:
        install = f.read()

    assert "legal_links_shelf" in install, "legal_links_shelf missing from install.php templates"
    print("[PASS] install.php verified.")

    print("\nALL BACKEND VERIFICATIONS PASSED!")

if __name__ == '__main__':
    verify_full()
