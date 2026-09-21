import re
import sys

def verify():
    print("--- Verifying legal_links_shelf Component ---")

    # Read assets/js/components.js
    with open('assets/js/components.js', 'r', encoding='utf-8') as f:
        js_content = f.read()

    assert "id: 'legal_links_shelf'" in js_content, "legal_links_shelf id missing from components.js"
    assert "Legal & Policy Links Shelf" in js_content, "Component name missing from components.js"
    assert "topic1Text" in js_content and "topic2Text" in js_content and "topic3Text" in js_content, "Topic schema keys missing"
    assert "Privacy Policy" in js_content and "Terms of Use" in js_content and "Support" in js_content, "Default topic texts missing"
    print("[PASS] assets/js/components.js verified successfully.")

    # Read builder.php
    with open('builder.php', 'r', encoding='utf-8') as f:
        builder_content = f.read()

    assert "sec.type.toLowerCase() === 'legal_links_shelf'" in builder_content, "legal_links_shelf compilation block missing in builder.php"
    assert "field.key.startsWith('topic1')" in builder_content, "topic1LinkType filtering logic missing in builder.php"
    assert "field.key.startsWith('topic2')" in builder_content, "topic2LinkType filtering logic missing in builder.php"
    assert "field.key.startsWith('topic3')" in builder_content, "topic3LinkType filtering logic missing in builder.php"
    print("[PASS] builder.php verified successfully.")

    print("\nALL VERIFICATIONS PASSED SUCCESSFULLY!")

if __name__ == '__main__':
    verify()
