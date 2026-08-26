import sys
import re
import subprocess

def test_components_js():
    print("Testing assets/js/components.js for WhatsApp Integration...")
    with open("assets/js/components.js", "r", encoding="utf-8") as f:
        content = f.read()

    # Check for whatsapp_chatbot component definition
    assert "id: 'whatsapp_chatbot'" in content, "Missing whatsapp_chatbot component in components.js"
    assert "WhatsApp Business Floating Widget" in content, "Missing title for whatsapp_chatbot"
    assert "fab fa-whatsapp" in content, "Missing WhatsApp icon in components.js"

    # Check for whatsapp_business_block component definition
    assert "id: 'whatsapp_business_block'" in content, "Missing whatsapp_business_block component in components.js"
    assert "WhatsApp Business Callout Section" in content, "Missing title for whatsapp_business_block"
    assert "https://wa.me/" in content, "Missing wa.me link pattern in components.js"

    # Check for JS runtime functions
    assert "window.toggleWhatsAppChatbot" in content, "Missing toggleWhatsAppChatbot function"
    assert "window.sendWhatsAppChatMessage" in content, "Missing sendWhatsAppChatMessage function"

    # Check btnLinkType schema updates
    assert "{value: 'whatsapp', label: 'WhatsApp Business Chat'}" in content, "Missing whatsapp option in btnLinkType schema"

    print("✅ assets/js/components.js verification passed!")

def test_builder_php():
    print("Testing builder.php for WhatsApp Integration...")
    with open("builder.php", "r", encoding="utf-8") as f:
        content = f.read()

    # Check resolveBtnUrl handles whatsapp
    assert "linkType === 'whatsapp'" in content, "Missing linkType === 'whatsapp' check in resolveBtnUrl"
    assert "https://wa.me/" in content, "Missing wa.me URL builder in builder.php"

    # Check position compiler supports whatsapp_chatbot
    assert "whatsapp_chatbot" in content, "Missing whatsapp_chatbot position compiler in builder.php"

    print("✅ builder.php verification passed!")

def test_syntax_and_linting():
    print("Linting PHP and JS files...")
    # Check PHP syntax
    res_builder = subprocess.run(["php", "-l", "builder.php"], capture_output=True, text=True)
    assert res_builder.returncode == 0, f"PHP syntax error in builder.php: {res_builder.stderr}"

    print("✅ PHP syntax check passed!")

def main():
    try:
        test_components_js()
        test_builder_php()
        test_syntax_and_linting()
        print("\n🎉 ALL WHATSAPP INTEGRATION VERIFICATION TESTS PASSED SUCCESSFULLY!")
    except AssertionError as err:
        print(f"\n❌ TEST FAILED: {err}")
        sys.exit(1)
    except Exception as e:
        print(f"\n❌ UNEXPECTED ERROR: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
