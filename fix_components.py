import re

with open('assets/js/components.js', 'r') as f:
    code = f.read()

# 1. Update pricing_comparison schema
pricing_old_schema = """        schema: [
            { key: 'tier1Name', label: 'Tier 1 Name', type: 'text', default: 'Starter' },
            { key: 'tier1Price', label: 'Tier 1 Price', type: 'text', default: '$29/mo' },
            { key: 'tier2Name', label: 'Tier 2 Name', type: 'text', default: 'Professional' },
            { key: 'tier2Price', label: 'Tier 2 Price', type: 'text', default: '$89/mo' },"""

pricing_new_schema = """        schema: [
            { key: 'tier1Name', label: 'Tier 1 Name', type: 'text', default: 'Starter' },
            { key: 'tier1Price', label: 'Tier 1 Price', type: 'text', default: '$29/mo' },
            { key: 'tier1BtnText', label: 'Tier 1 Button Text', type: 'text', default: 'Choose Starter' },
            { key: 'tier1LinkType', label: 'Tier 1 Link Type', type: 'select', default: 'url', options: [{value: 'url', label: 'Custom URL'}, {value: 'page', label: 'Internal Page'}, {value: 'section', label: 'Section Anchor'}] },
            { key: 'tier1Url', label: 'Tier 1 URL', type: 'text', default: '#' },
            { key: 'tier1Page', label: 'Tier 1 Select Page', type: 'text', default: 'index' },
            { key: 'tier1Section', label: 'Tier 1 Select Section', type: 'text', default: '' },
            { key: 'tier1NewTab', label: 'Tier 1 Open in New Tab', type: 'checkbox', default: false },

            { key: 'tier2Name', label: 'Tier 2 Name', type: 'text', default: 'Professional' },
            { key: 'tier2Price', label: 'Tier 2 Price', type: 'text', default: '$89/mo' },
            { key: 'tier2BtnText', label: 'Tier 2 Button Text', type: 'text', default: 'Get Pro Access' },
            { key: 'tier2LinkType', label: 'Tier 2 Link Type', type: 'select', default: 'url', options: [{value: 'url', label: 'Custom URL'}, {value: 'page', label: 'Internal Page'}, {value: 'section', label: 'Section Anchor'}] },
            { key: 'tier2Url', label: 'Tier 2 URL', type: 'text', default: '#' },
            { key: 'tier2Page', label: 'Tier 2 Select Page', type: 'text', default: 'index' },
            { key: 'tier2Section', label: 'Tier 2 Select Section', type: 'text', default: '' },
            { key: 'tier2NewTab', label: 'Tier 2 Open in New Tab', type: 'checkbox', default: false },"""

if pricing_old_schema in code:
    code = code.replace(pricing_old_schema, pricing_new_schema)
    print("Updated pricing_comparison schema.")

# 2. Update icon_image_box schema
icon_old_schema = """        schema: [
            { key: 'heading', label: 'Box Heading', type: 'text', default: 'Fast Integration' },
            { key: 'text', label: 'Box Description', type: 'textarea', default: 'Seamlessly connect with your existing tech stack using automated webhooks and REST endpoints.' },
            { key: 'iconClass', label: 'FontAwesome Icon Class', type: 'text', default: 'fas fa-bolt' },
            { key: 'imageUrl', label: 'Image URL (Overrides Icon if provided)', type: 'text', default: '' },"""

icon_new_schema = """        schema: [
            { key: 'heading', label: 'Box Heading', type: 'text', default: 'Fast Integration' },
            { key: 'text', label: 'Box Description', type: 'textarea', default: 'Seamlessly connect with your existing tech stack using automated webhooks and REST endpoints.' },
            { key: 'iconClass', label: 'FontAwesome Icon Class', type: 'text', default: 'fas fa-bolt' },
            { key: 'imageUrl', label: 'Image URL (Overrides Icon if provided)', type: 'text', default: '' },
            { key: 'btnText', label: 'Button Text (Optional)', type: 'text', default: 'Learn More' },
            { key: 'btnLinkType', label: 'Button Link Type', type: 'select', default: 'url', options: [{value: 'url', label: 'Custom URL'}, {value: 'page', label: 'Internal Page'}, {value: 'section', label: 'Section Anchor'}] },
            { key: 'btnUrl', label: 'Button URL', type: 'text', default: '#' },
            { key: 'btnPage', label: 'Select Page', type: 'text', default: 'index' },
            { key: 'btnSection', label: 'Select Section', type: 'text', default: '' },
            { key: 'btnNewTab', label: 'Open in New Tab', type: 'checkbox', default: false },"""

if icon_old_schema in code:
    code = code.replace(icon_old_schema, icon_new_schema)
    print("Updated icon_image_box schema.")

with open('assets/js/components.js', 'w') as f:
    f.write(code)
