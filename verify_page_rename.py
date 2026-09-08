import re
import sys

def verify_rename_feature():
    with open('builder.php', 'r', encoding='utf-8') as f:
        content = f.read()

    # 1. Verify renamePage function definition
    if 'const renamePage = (oldPageName) => {' not in content:
        print("FAIL: renamePage function definition not found in builder.php")
        sys.exit(1)

    # 2. Verify protection for index page
    if "if (oldPageName === 'index')" not in content:
        print("FAIL: index page rename protection not found")
        sys.exit(1)

    # 3. Verify prompt for new page name
    if "prompt(" not in content or "Enter new name for page" not in content:
        print("FAIL: renamePage prompt not found")
        sys.exit(1)

    # 4. Verify cross-page link/prop update logic
    if "propKey.toLowerCase().endsWith('page')" not in content or "link.pageName = safeName" not in content:
        print("FAIL: Cross-page link updating logic missing")
        sys.exit(1)

    # 5. Verify UI button in workspace header
    if 'title="Rename Current Page"' not in content or '<i className="fas fa-edit"></i>' not in content:
        print("FAIL: Rename button UI missing in builder.php header")
        sys.exit(1)

    print("SUCCESS: All rename page checks passed successfully!")

if __name__ == '__main__':
    verify_rename_feature()
