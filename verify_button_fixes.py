import re
import sys

def test_builder_and_components():
    with open('assets/js/components.js', 'r') as f:
        components_code = f.read()

    with open('builder.php', 'r') as f:
        builder_code = f.read()

    # Check 1: btnShape present in components.js schema
    assert 'btnShape' in components_code, "btnShape missing in components.js"
    assert 'Pill / Fully Rounded' in components_code, "Pill option label missing in components.js"

    # Check 2: resolveBtnShapeClass present in builder.php
    assert 'resolveBtnShapeClass' in builder_code, "resolveBtnShapeClass missing in builder.php"
    assert 'rounded-full' in builder_code, "rounded-full class missing in builder.php"

    # Check 3: Edit mode link interception in builder.php
    assert 'allLinksAndBtns.forEach' in builder_code, "allLinksAndBtns link interception missing in builder.php"
    assert "javascript:void(0)" in builder_code, "javascript:void(0) replacement missing in builder.php"

    print("All static code verification checks passed successfully!")

if __name__ == '__main__':
    test_builder_and_components()
