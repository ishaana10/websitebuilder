import sys
import re

def check_components():
    print("Checking components.js for contact_shelf and top_bar_shelf...")
    with open('assets/js/components.js', 'r') as f:
        content = f.read()

    assert 'contact_shelf' in content, "contact_shelf missing in components.js"
    assert 'top_bar_shelf' in content, "top_bar_shelf missing in components.js"
    assert 'isSticky' in content, "isSticky property missing in components.js"
    assert 'topMargin' in content, "topMargin property missing in components.js"
    assert 'cornerRadius' in content, "cornerRadius property missing in components.js"
    print("components.js checks passed!")

def check_builder():
    print("Checking builder.php for topMargin, top_bar_shelf, isSticky, favicon CSRF...")
    with open('builder.php', 'r') as f:
        content = f.read()

    assert 'topMarginStyle' in content, "topMarginStyle missing in builder.php"
    assert 'top_bar_shelf' in content, "top_bar_shelf compiler missing in builder.php"
    assert 'isSticky' in content, "isSticky handling missing in builder.php"
    assert 'margin-top:' in content, "margin-top CSS inline calculation missing"
    assert '<div className="w-full">' in content, "w-full wrapper missing in builder.php"
    print("builder.php checks passed!")

def check_render():
    print("Checking render.php for space-y-4 removal and btnBlinkKeyframes...")
    with open('render.php', 'r') as f:
        content = f.read()

    assert '<main class="w-full">' in content, "<main class='w-full'> missing in render.php"
    assert 'btnBlinkKeyframes' in content, "btnBlinkKeyframes missing in render.php"
    print("render.php checks passed!")

def check_api():
    print("Checking api.php for mime types and try-catch...")
    with open('api.php', 'r') as f:
        content = f.read()

    assert 'image/x-icon' in content, "image/x-icon missing in api.php"
    assert 'try {' in content, "try block missing in api.php"
    assert 'json_response' in content, "json_response missing in api.php"
    print("api.php checks passed!")

if __name__ == '__main__':
    try:
        check_components()
        check_builder()
        check_render()
        check_api()
        print("ALL STATIC CHECKS PASSED SUCCESSFULLY!")
    except AssertionError as e:
        print(f"VERIFICATION FAILED: {e}")
        sys.exit(1)
