/**
 * Nuvis Webbuilder Pre-Built High-Quality UI Widgets & Components
 * Tailored for modern, beautiful, and fully customizable responsive websites
 */

const UI_COMPONENTS = [
    // === HEADERS & FOOTERS ===
    {
        id: 'navbar',
        name: 'Responsive Navigation Bar',
        category: 'Headers',
        icon: 'fas fa-bars',
        schema: [
            { key: 'brandText', label: 'Brand Name', type: 'text', default: 'NUVIS WEBBUILDER' },
            { key: 'logoUrl', label: 'Logo Image URL', type: 'text', default: '' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#0f172a' },
            { key: 'textColor', label: 'Text Color', type: 'color', default: '#ffffff' },
            { key: 'accentColor', label: 'Accent Color', type: 'color', default: '#14b8a6' }
        ],
        html: `
<nav class="py-4 px-6 shadow-md rounded-lg relative" style="background-color: {{bgColor}}; color: {{textColor}};" data-component="navbar">
    <div class="flex justify-between items-center">
        <div class="text-xl font-extrabold tracking-wider" style="color: {{accentColor}};">{{brandText}}</div>

        <!-- Desktop Links -->
        <div class="hidden md:flex space-x-6">
            {{links}}
        </div>

        <div class="flex items-center gap-4">
            <!-- CTA Button -->
            <a href="#get-started" class="font-bold px-4 py-2 rounded transition duration-300 text-sm" style="background-color: {{accentColor}}; color: {{bgColor}};" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Get Started</a>

            <!-- Mobile Burger Toggle -->
            <button onclick="const m = this.closest('[data-component]').querySelector('.mobile-menu'); m.classList.toggle('hidden');" class="md:hidden text-xl focus:outline-none" style="color: {{textColor}};">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>

    <!-- Mobile Menu dropdown -->
    <div class="mobile-menu hidden md:hidden flex flex-col space-y-3 mt-4 pt-4 border-t border-slate-700/50">
        {{links}}
    </div>
</nav>`
    },
    {
        id: 'footer',
        name: 'Corporate Footer Block',
        category: 'Footers',
        icon: 'fas fa-shoe-prints',
        schema: [
            { key: 'brandText', label: 'Brand Name', type: 'text', default: 'NUVIS WEBBUILDER' },
            { key: 'logoUrl', label: 'Logo Image URL', type: 'text', default: '' },
            { key: 'copyright', label: 'Copyright Note', type: 'text', default: 'Nuvis Webbuilder. All rights reserved.' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#020617' },
            { key: 'textColor', label: 'Text Color', type: 'color', default: '#94a3b8' },
            { key: 'accentColor', label: 'Link Accent Color', type: 'color', default: '#14b8a6' }
        ],
        html: `
<footer class="py-12 px-8 rounded-lg text-center" style="background-color: {{bgColor}}; color: {{textColor}};" data-component="footer">
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6">
        <div class="text-lg font-black text-white" style="color: {{accentColor}};">{{brandText}}</div>
        <div class="flex space-x-6 text-sm">
            {{links}}
        </div>
        <div class="text-xs">&copy; ${new Date().getFullYear()} {{copyright}}</div>
    </div>
</footer>`
    },

    // === CORE / LAYOUT BUILDING BLOCKS ===
    {
        id: 'hero',
        name: 'Customizable Hero',
        category: 'Hero',
        icon: 'fas fa-rocket',
        schema: [
            { key: 'badgeText', label: 'Badge Text', type: 'text', default: 'NEW REVOLUTION' },
            { key: 'heading', label: 'Hero Heading', type: 'text', default: 'Build Stunning Websites In Minutes' },
            { key: 'text', label: 'Subheading Text', type: 'textarea', default: 'The ultimate low-code drag and drop page builder designed to transform complex ideas.' },
            { key: 'btnText', label: 'Primary CTA Text', type: 'text', default: 'Start For Free' },
            { key: 'btnBg', label: 'Primary CTA Background', type: 'color', default: '#14b8a6' },
            { key: 'btnColor', label: 'Primary CTA Text Color', type: 'color', default: '#0f172a' },
            { key: 'secondaryBtnText', label: 'Secondary CTA Text', type: 'text', default: 'Learn More' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#0f172a' },
            { key: 'headingColor', label: 'Heading Text Color', type: 'color', default: '#ffffff' },
            { key: 'textColor', label: 'Body Text Color', type: 'color', default: '#cbd5e1' }
        ],
        html: `
<section class="py-24 px-8 rounded-lg text-center" style="background-color: {{bgColor}};" data-component="hero">
    <div class="max-w-3xl mx-auto">
        <span class="font-semibold px-4 py-1.5 rounded-full text-xs uppercase tracking-widest border" style="background-color: rgba(20, 184, 166, 0.1); color: {{btnBg}}; border-color: rgba(20, 184, 166, 0.2);">{{badgeText}}</span>
        <h1 class="text-4xl md:text-6xl font-black mt-6 tracking-tight leading-none" style="color: {{headingColor}};">{{heading}}</h1>
        <p class="mt-6 text-lg md:text-xl leading-relaxed" style="color: {{textColor}};">{{text}}</p>
        <div class="mt-10 flex flex-wrap justify-center gap-4">
            <button class="font-extrabold px-8 py-4 rounded-lg shadow-lg transition-all duration-300" style="background-color: {{btnBg}}; color: {{btnColor}};">{{btnText}}</button>
            <button class="font-bold px-8 py-4 rounded-lg border transition-all duration-300" style="border-color: rgba(255,255,255,0.2); color: {{headingColor}};">{{secondaryBtnText}}</button>
        </div>
    </div>
</section>`
    },
    {
        id: 'layout_grid',
        name: 'Responsive Flex Row/Grid',
        category: 'Headers',
        icon: 'fas fa-th',
        schema: [
            { key: 'colCount', label: 'Column Count', type: 'select', default: 'grid-cols-3', options: [
                { value: 'grid-cols-1', label: '1 Column' },
                { value: 'grid-cols-2', label: '2 Columns' },
                { value: 'grid-cols-3', label: '3 Columns' },
                { value: 'grid-cols-4', label: '4 Columns' }
            ]},
            { key: 'heading', label: 'Section Header', type: 'text', default: 'Structured Grid Layout' },
            { key: 'colText1', label: 'Column 1 Content', type: 'textarea', default: 'High density column structure.' },
            { key: 'colText2', label: 'Column 2 Content', type: 'textarea', default: 'Responsive breakpoint scaling.' },
            { key: 'colText3', label: 'Column 3 Content', type: 'textarea', default: 'Flex space distribution.' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#1e293b' },
            { key: 'headingColor', label: 'Header Text Color', type: 'color', default: '#ffffff' },
            { key: 'cardBgColor', label: 'Card Background', type: 'color', default: '#0f172a' },
            { key: 'textColor', label: 'Text Color', type: 'color', default: '#94a3b8' }
        ],
        html: `
<section class="py-16 px-8 rounded-lg" style="background-color: {{bgColor}};" data-component="layout_grid">
    <div class="max-w-6xl mx-auto">
        <h2 class="text-3xl font-bold text-center mb-10" style="color: {{headingColor}};">{{heading}}</h2>
        <div class="grid gap-6 {{colCount}}">
            <div class="p-6 rounded-lg shadow" style="background-color: {{cardBgColor}}; color: {{textColor}};">{{colText1}}</div>
            <div class="p-6 rounded-lg shadow" style="background-color: {{cardBgColor}}; color: {{textColor}};">{{colText2}}</div>
            <div class="p-6 rounded-lg shadow" style="background-color: {{cardBgColor}}; color: {{textColor}};">{{colText3}}</div>
        </div>
    </div>
</section>`
    },
    {
        id: 'spacer_divider',
        name: 'Spacer / Divider',
        category: 'Headers',
        icon: 'fas fa-arrows-alt-v',
        schema: [
            { key: 'height', label: 'Height (px)', type: 'select', default: 'h-12', options: [
                { value: 'h-4', label: '16px (h-4)' },
                { value: 'h-8', label: '32px (h-8)' },
                { value: 'h-12', label: '48px (h-12)' },
                { value: 'h-24', label: '96px (h-24)' }
            ]},
            { key: 'borderColor', label: 'Line Color', type: 'color', default: '#334155' },
            { key: 'bgColor', label: 'Container Background', type: 'color', default: '#0f172a' },
            { key: 'showLine', label: 'Show Divider Line', type: 'checkbox', default: true }
        ],
        html: `
<div class="w-full flex items-center justify-center {{height}} rounded-lg" style="background-color: {{bgColor}};" data-component="spacer_divider">
    <div class="w-11/12 border-t" style="border-color: {{borderColor}}; display: {{showLine ? 'block' : 'none'}};"></div>
</div>`
    },

    // === CONTENT & MARKETING ===
    {
        id: 'heading_rich_text',
        name: 'Heading & Rich Text',
        category: 'Features',
        icon: 'fas fa-align-left',
        schema: [
            { key: 'heading', label: 'Section Heading', type: 'text', default: 'Elegance meets pure performance.' },
            { key: 'text', label: 'Rich Content Block', type: 'textarea', default: 'Craft a beautifully structured layout where your imagery directly interfaces with your product description.' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#0f172a' },
            { key: 'headingColor', label: 'Heading Color', type: 'color', default: '#14b8a6' },
            { key: 'textColor', label: 'Paragraph Color', type: 'color', default: '#cbd5e1' }
        ],
        html: `
<section class="py-16 px-8 rounded-lg" style="background-color: {{bgColor}};" data-component="heading_rich_text">
    <div class="max-w-4xl mx-auto space-y-6">
        <h2 class="text-4xl font-extrabold tracking-tight" style="color: {{headingColor}};">{{heading}}</h2>
        <p class="text-base leading-relaxed" style="color: {{textColor}};">{{text}}</p>
    </div>
</section>`
    },
    {
        id: 'feature_split',
        name: 'Side-by-Side Split Feature',
        category: 'Features',
        icon: 'fas fa-columns',
        schema: [
            { key: 'heading', label: 'Section Title', type: 'text', default: 'Elegance meets pure performance.' },
            { key: 'text', label: 'Feature Description', type: 'textarea', default: 'Craft a beautifully structured layout where your imagery directly interfaces with your product description. Adjust photo alignments and style typography to match.' },
            { key: 'imageUrl', label: 'Image URL', type: 'text', default: 'https://images.unsplash.com/photo-1551434678-e076c223a692?w=800&auto=format&fit=crop&q=60' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#0f172a' },
            { key: 'headingColor', label: 'Title Color', type: 'color', default: '#ffffff' },
            { key: 'textColor', label: 'Body Text Color', type: 'color', default: '#cbd5e1' },
            { key: 'imageRounding', label: 'Border Radius', type: 'select', default: 'rounded-xl', options: [
                { value: 'rounded-none', label: 'Sharp' },
                { value: 'rounded-lg', label: 'Medium' },
                { value: 'rounded-xl', label: 'Large' },
                { value: 'rounded-full', label: 'Circular' }
            ]}
        ],
        html: `
<section class="py-16 px-8 rounded-lg" style="background-color: {{bgColor}};" data-component="feature_split">
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center gap-12">
        <div class="flex-1 space-y-6">
            <span class="font-semibold px-3 py-1 rounded-full text-xs uppercase tracking-wider" style="background-color: rgba(20, 184, 166, 0.1); color: #14b8a6;">Next-Gen Interface</span>
            <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight" style="color: {{headingColor}};">{{heading}}</h2>
            <p class="text-base leading-relaxed" style="color: {{textColor}};">{{text}}</p>
        </div>
        <div class="flex-1 w-full">
            <img src="{{imageUrl}}" alt="Feature Image" class="w-full object-cover shadow-lg border border-slate-800 {{imageRounding}}" />
        </div>
    </div>
</section>`
    },
    {
        id: 'testimonials_carousel',
        name: 'Testimonials Slider',
        category: 'Features',
        icon: 'fas fa-star',
        schema: [
            { key: 'heading', label: 'Main Heading', type: 'text', default: 'What our clients say' },
            { key: 'authorName', label: 'Author Name', type: 'text', default: 'Sarah Jenkins' },
            { key: 'authorRole', label: 'Author Role', type: 'text', default: 'CTO at CloudCorp' },
            { key: 'text', label: 'Quote content', type: 'textarea', default: 'Rebuilding our workspace with Nuvis Webbuilder decreased static page load times instantly.' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#020617' },
            { key: 'cardBg', label: 'Card Background', type: 'color', default: '#0f172a' },
            { key: 'headingColor', label: 'Heading Color', type: 'color', default: '#ffffff' },
            { key: 'textColor', label: 'Quote Color', type: 'color', default: '#cbd5e1' },
            { key: 'accentColor', label: 'Stars / Highlight', type: 'color', default: '#fbbf24' }
        ],
        html: `
<section class="py-16 px-8 rounded-lg" style="background-color: {{bgColor}};" data-component="testimonials_carousel">
    <div class="max-w-4xl mx-auto text-center">
        <h2 class="text-3xl font-extrabold mb-10" style="color: {{headingColor}};">{{heading}}</h2>
        <div class="carousel-container p-8 rounded-2xl shadow border border-slate-800" style="background-color: {{cardBg}};">
            <div class="carousel-slide flex flex-col items-center">
                <div class="flex gap-1 mb-4" style="color: {{accentColor}};">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
                <p class="text-lg italic leading-relaxed" style="color: {{textColor}};">"{{text}}"</p>
                <div class="mt-6">
                    <h4 class="font-bold" style="color: {{headingColor}};">{{authorName}}</h4>
                    <p class="text-xs" style="color: {{textColor}}; opacity: 0.8;">{{authorRole}}</p>
                </div>
            </div>
        </div>
    </div>
</section>`
    },
    {
        id: 'pricing_comparison',
        name: 'Interactive Pricing Matrix',
        category: 'Pricing',
        icon: 'fas fa-tags',
        schema: [
            { key: 'tier1Name', label: 'Tier 1 Name', type: 'text', default: 'Starter' },
            { key: 'tier1Price', label: 'Tier 1 Price', type: 'text', default: '$19' },
            { key: 'tier2Name', label: 'Tier 2 Name', type: 'text', default: 'Professional' },
            { key: 'tier2Price', label: 'Tier 2 Price', type: 'text', default: '$49' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#0f172a' },
            { key: 'cardBg', label: 'Card Background', type: 'color', default: '#1e293b' },
            { key: 'accentColor', label: 'Accent Border', type: 'color', default: '#14b8a6' },
            { key: 'textColor', label: 'Body Text Color', type: 'color', default: '#e2e8f0' }
        ],
        html: `
<section class="py-16 px-8 rounded-lg" style="background-color: {{bgColor}}; color: {{textColor}};" data-component="pricing_comparison">
    <div class="max-w-5xl mx-auto">
        <h2 class="text-3xl font-extrabold text-center mb-12">Flexible Pricing Packages</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
            <div class="p-8 rounded-2xl border border-slate-700 text-center flex flex-col justify-between" style="background-color: {{cardBg}};">
                <div>
                    <h3 class="text-xl font-bold mb-4">{{tier1Name}}</h3>
                    <div class="text-4xl font-black mb-4">{{tier1Price}} <span class="text-sm font-normal opacity-70">/mo</span></div>
                    <ul class="text-sm space-y-3 my-6 text-left">
                        <li><i class="fas fa-check mr-2" style="color: {{accentColor}};"></i> 3 Sandbox Projects</li>
                        <li><i class="fas fa-check mr-2" style="color: {{accentColor}};"></i> Absolute raw HTML export</li>
                    </ul>
                </div>
                <button class="w-full py-3 rounded-lg font-bold" style="background-color: {{accentColor}}; color: {{bgColor}};">Get Started</button>
            </div>
            <div class="p-8 rounded-2xl border-2 text-center flex flex-col justify-between" style="background-color: {{cardBg}}; border-color: {{accentColor}};">
                <div>
                    <h3 class="text-xl font-bold mb-4" style="color: {{accentColor}};">{{tier2Name}}</h3>
                    <div class="text-4xl font-black mb-4">{{tier2Price}} <span class="text-sm font-normal opacity-70">/mo</span></div>
                    <ul class="text-sm space-y-3 my-6 text-left">
                        <li><i class="fas fa-check mr-2" style="color: {{accentColor}};"></i> Unlimited Websites</li>
                        <li><i class="fas fa-check mr-2" style="color: {{accentColor}};"></i> AI Assistant Modules</li>
                    </ul>
                </div>
                <button class="w-full py-3 rounded-lg font-bold text-slate-950" style="background-color: {{accentColor}};">Go Pro</button>
            </div>
        </div>
    </div>
</section>`
    },

    // === INTERACTIVE & MEDIA ===
    {
        id: 'media_carousel',
        name: 'Carousel Slider',
        category: 'Advanced',
        icon: 'fas fa-images',
        schema: [
            { key: 'heading', label: 'Carousel Header', type: 'text', default: 'Interactive Showcase' },
            { key: 'imgUrl1', label: 'Slide 1 Image', type: 'text', default: 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=800&auto=format&fit=crop&q=60' },
            { key: 'imgUrl2', label: 'Slide 2 Image', type: 'text', default: 'https://images.unsplash.com/photo-1504868584819-f8e8b4b6d7e3?w=800&auto=format&fit=crop&q=60' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#0f172a' },
            { key: 'accentColor', label: 'Navigation Arrows Color', type: 'color', default: '#14b8a6' }
        ],
        html: `
<section class="py-16 px-8 rounded-lg text-center" style="background-color: {{bgColor}};" data-component="media_carousel">
    <h2 class="text-2xl font-bold mb-6 text-white">{{heading}}</h2>
    <div class="carousel-container relative max-w-xl mx-auto h-64 overflow-hidden rounded-xl border border-slate-800">
        <div class="carousel-slide w-full h-full">
            <img src="{{imgUrl1}}" class="w-full h-full object-cover" />
        </div>
        <div class="carousel-slide w-full h-full hidden">
            <img src="{{imgUrl2}}" class="w-full h-full object-cover" />
        </div>
        <button onclick="window.nextCarouselSlide(this)" class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full flex items-center justify-center text-white" style="background-color: rgba(0,0,0,0.5);" onmouseover="this.style.color='{{accentColor}}'" onmouseout="this.style.color='#fff'">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>
</section>`
    },
    {
        id: 'interactive_tabs',
        name: 'Responsive Tabs Component',
        category: 'Advanced',
        icon: 'fas fa-folder',
        schema: [
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#020617' },
            { key: 'accentColor', label: 'Active Tab Border', type: 'color', default: '#14b8a6' },
            { key: 'textColor', label: 'Content Color', type: 'color', default: '#94a3b8' }
        ],
        html: `
<section class="py-16 px-8 rounded-lg tabs-container" style="background-color: {{bgColor}};" data-component="interactive_tabs">
    <div class="max-w-2xl mx-auto">
        <div class="flex border-b border-slate-800 mb-6 gap-4 overflow-x-auto">
            {{tabButtons}}
        </div>
        <div class="mt-4">
            {{tabContents}}
        </div>
    </div>
</section>`
    },

    // === FORMS & CONVERSION ===
    {
        id: 'contact',
        name: 'Secure Contact Form',
        category: 'Forms',
        icon: 'fas fa-envelope',
        schema: [
            { key: 'heading', label: 'Form Title', type: 'text', default: 'Get In Touch' },
            { key: 'text', label: 'Sub-text prompt', type: 'textarea', default: 'Have questions? Drop us a line.' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#0f172a' },
            { key: 'btnBg', label: 'Button Background', type: 'color', default: '#14b8a6' },
            { key: 'btnColor', label: 'Button Text Color', type: 'color', default: '#0f172a' }
        ],
        html: `
<section class="py-16 px-8 rounded-lg" style="background-color: {{bgColor}};" data-component="contact">
    <div class="max-w-md mx-auto text-center">
        <h2 class="text-3xl font-extrabold" style="color: {{btnBg}};">{{heading}}</h2>
        <p class="text-slate-400 mt-2 text-sm">{{text}}</p>

        <form class="mt-8 space-y-4" onsubmit="event.preventDefault(); window.submitNuvisWebbuilderForm(this);">
            <div class="nuvis-webbuilder-form-status hidden p-3 rounded text-xs font-bold text-center"></div>
            <input type="text" name="name" placeholder="Full Name" required class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-teal-500 text-sm" />
            <input type="email" name="email" placeholder="Email Address" required class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-teal-500 text-sm" />
            <textarea name="message" placeholder="Write message..." rows="4" required class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-teal-500 text-sm"></textarea>
            <button type="submit" class="font-bold w-full py-3 rounded-lg transition-all text-sm tracking-wide flex items-center justify-center gap-2" style="background-color: {{btnBg}}; color: {{btnColor}};">
                <span>Send Message</span>
            </button>
        </form>
    </div>
</section>`
    },
    {
        id: 'newsletter_signup',
        name: 'Newsletter Signup Banner',
        category: 'Forms',
        icon: 'fas fa-paper-plane',
        schema: [
            { key: 'heading', label: 'Main Header', type: 'text', default: 'Subscribe to our newsletter' },
            { key: 'text', label: 'Subtext promise', type: 'text', default: 'Receive developer updates twice a month. No spam.' },
            { key: 'btnText', label: 'Button Text', type: 'text', default: 'Subscribe' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#1e293b' },
            { key: 'accentColor', label: 'Button Accent Color', type: 'color', default: '#14b8a6' },
            { key: 'textColor', label: 'Text Color', type: 'color', default: '#ffffff' }
        ],
        html: `
<section class="py-12 px-8 rounded-lg text-center" style="background-color: {{bgColor}}; color: {{textColor}};" data-component="newsletter_signup">
    <div class="max-w-2xl mx-auto space-y-4">
        <h3 class="text-2xl font-bold">{{heading}}</h3>
        <p class="text-sm opacity-80">{{text}}</p>
        <form class="flex flex-col sm:flex-row gap-3 max-w-md mx-auto mt-6" onsubmit="event.preventDefault(); alert('Subscribed successfully!');">
            <input type="email" required placeholder="Enter your email" class="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
            <button type="submit" class="font-bold px-6 py-2.5 rounded-lg text-xs hover:opacity-90" style="background-color: {{accentColor}}; color: {{bgColor}};">{{btnText}}</button>
        </form>
    </div>
</section>`
    },

    // === E-COMMERCE / PRODUCT ===
    {
        id: 'product_shelf',
        name: 'Product Shelf Card',
        category: 'Pricing',
        icon: 'fas fa-shopping-bag',
        schema: [
            { key: 'title', label: 'Product Title', type: 'text', default: 'Nuvis Developer Pro License' },
            { key: 'price', label: 'Price Tag', type: 'text', default: '$129.00' },
            { key: 'desc', label: 'Product Highlights', type: 'textarea', default: 'Absolute priority compiling with secure persistent storage logic.' },
            { key: 'imgUrl', label: 'Product Image', type: 'text', default: 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=400&auto=format&fit=crop&q=60' },
            { key: 'bgColor', label: 'Card Background', type: 'color', default: '#111827' },
            { key: 'textColor', label: 'Text Color', type: 'color', default: '#e2e8f0' },
            { key: 'btnBg', label: 'Button Accent', type: 'color', default: '#14b8a6' }
        ],
        html: `
<div class="max-w-sm mx-auto rounded-xl overflow-hidden shadow-2xl border border-slate-800 flex flex-col justify-between" style="background-color: {{bgColor}}; color: {{textColor}};" data-component="product_shelf">
    <img src="{{imgUrl}}" class="w-full h-48 object-cover" />
    <div class="p-6 space-y-4">
        <div class="flex justify-between items-center">
            <h4 class="font-extrabold text-base leading-tight">{{title}}</h4>
            <span class="text-sm font-black px-2 py-1 rounded" style="background-color: rgba(20,184,166,0.1); color: {{btnBg}};">{{price}}</span>
        </div>
        <p class="text-xs opacity-85 leading-relaxed">{{desc}}</p>
        <button onclick="window.addToMiniCart()" class="w-full font-bold py-2.5 rounded-lg text-xs tracking-wider uppercase transition hover:opacity-90" style="background-color: {{btnBg}}; color: {{bgColor}};">
            <i class="fas fa-shopping-cart mr-2"></i> Add To Cart
        </button>
    </div>
</div>`
    },
    {
        id: 'cart_mini',
        name: 'Live Mini-Cart Widget',
        category: 'Pricing',
        icon: 'fas fa-shopping-cart',
        schema: [
            { key: 'heading', label: 'Header Text', type: 'text', default: 'Your Checkout Cart' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#0f172a' },
            { key: 'accentColor', label: 'Theme Highlight', type: 'color', default: '#14b8a6' },
            { key: 'textColor', label: 'Label Color', type: 'color', default: '#ffffff' }
        ],
        html: `
<section class="p-6 rounded-lg border border-slate-800 flex justify-between items-center max-w-md mx-auto" style="background-color: {{bgColor}}; color: {{textColor}};" data-component="cart_mini">
    <div class="flex items-center gap-3">
        <i class="fas fa-shopping-cart text-xl" style="color: {{accentColor}};"></i>
        <div>
            <h5 class="font-bold text-sm">{{heading}}</h5>
            <span class="text-xs opacity-70"><span id="mini-cart-count">0</span> item(s) selected</span>
        </div>
    </div>
    <div class="flex gap-2">
        <button onclick="window.clearMiniCart()" class="px-3 py-1.5 rounded text-[10px] uppercase font-bold border border-slate-700 hover:bg-slate-800">Clear</button>
        <button onclick="alert('Proceeding to visual mock checkout!');" class="px-3 py-1.5 rounded text-[10px] uppercase font-bold text-slate-950 hover:opacity-95" style="background-color: {{accentColor}};">Checkout</button>
    </div>
</section>`
    },

    // === UTILITY & ADVANCED ===
    {
        id: 'faq',
        name: 'Interactive FAQ Accordion',
        category: 'Advanced',
        icon: 'fas fa-question-circle',
        schema: [
            { key: 'heading', label: 'FAQ Title', type: 'text', default: 'Frequently Asked Questions' },
            { key: 'q1', label: 'Question 1', type: 'text', default: 'How does the local compiling mechanism operate?' },
            { key: 'a1', label: 'Answer 1', type: 'textarea', default: 'Our platform compiles visual assets into highly optimized, fully responsive static HTML output instantly.' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#0f172a' },
            { key: 'cardBg', label: 'Card Background', type: 'color', default: '#020617' },
            { key: 'headingColor', label: 'Header Text Color', type: 'color', default: '#ffffff' },
            { key: 'accentColor', label: 'Accordions Active Accent', type: 'color', default: '#14b8a6' }
        ],
        html: `
<section class="py-16 px-8 rounded-lg" style="background-color: {{bgColor}};" data-component="faq">
    <div class="max-w-4xl mx-auto">
        <h2 class="text-3xl font-extrabold text-center tracking-tight mb-10" style="color: {{headingColor}};">{{heading}}</h2>

        <div class="space-y-4">
            <div class="border rounded-lg overflow-hidden border-slate-800" style="background-color: {{cardBg}};">
                <button onclick="window.toggleNuvisFaqAccordion(this)" class="w-full text-left px-6 py-4 font-bold text-sm flex justify-between items-center transition" style="color: {{headingColor}};">
                    <span>{{q1}}</span>
                    <i class="fas fa-chevron-down opacity-60"></i>
                </button>
                <div class="faq-accordion-content hidden px-6 pb-5 text-xs border-t border-slate-900 pt-3 leading-relaxed" style="color: {{accentColor}};">
                    {{a1}}
                </div>
            </div>
        </div>
    </div>
</section>`
    },
    {
        id: 'html_raw',
        name: 'Low-Code Custom Raw HTML',
        category: 'Advanced',
        icon: 'fas fa-code',
        schema: [
            { key: 'rawHtml', label: 'Custom HTML Code', type: 'textarea', default: '<div class="p-6 bg-slate-950 border border-slate-800 rounded-lg text-center text-xs text-teal-400 font-mono">Custom Code Block Injected</div>' }
        ],
        html: `
<div class="p-2 border border-slate-800 rounded bg-slate-950 text-slate-100" data-component="html_raw">
    <div class="custom-html-container text-left">{{rawHtml}}</div>
</div>`
    },
    {
        id: 'fullwidth_raw_html',
        name: 'Full-Width Raw HTML Block',
        category: 'Advanced',
        icon: 'fas fa-code',
        schema: [
            { key: 'rawHtml', label: 'Custom HTML Code', type: 'textarea', default: '<div class="p-6 bg-slate-950 text-center text-xs text-teal-400 font-mono">Clean Raw HTML</div>' }
        ],
        html: `{{rawHtml}}`
    },
    {
        id: 'chatbot',
        name: 'Interactive AI Chatbot',
        category: 'Advanced',
        icon: 'fas fa-comments',
        schema: [
            { key: 'agentName', label: 'Bot Title', type: 'text', default: 'AI Support Bot' },
            { key: 'accentColor', label: 'Bubble Highlight Color', type: 'color', default: '#14b8a6' },
            { key: 'bgColor', label: 'Chat Dialog Background', type: 'color', default: '#0f172a' }
        ],
        html: `
<div class="fixed bottom-6 right-6 z-50 font-sans" data-component="chatbot">
    <button onclick="window.toggleNuvisWebbuilderChat()" class="w-14 h-14 rounded-full flex items-center justify-center shadow-2xl transition duration-300 focus:outline-none" style="background-color: {{accentColor}};">
        <i class="fas fa-comments text-xl text-slate-950"></i>
    </button>
    <div id="nuvis-webbuilder-chat-window" class="hidden absolute bottom-16 right-0 w-80 border border-slate-800 rounded-xl shadow-2xl overflow-hidden flex flex-col" style="background-color: {{bgColor}};">
        <div class="p-4 border-b border-slate-800 flex justify-between items-center bg-slate-950">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="font-bold text-xs text-white uppercase tracking-wider">{{agentName}}</span>
            </div>
            <button onclick="window.toggleNuvisWebbuilderChat()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div id="nuvis-webbuilder-chat-logs" class="p-4 h-48 overflow-y-auto space-y-3 flex flex-col text-xs text-slate-300">
            <div class="bg-slate-800/80 p-2 rounded-lg self-start max-w-[85%] leading-relaxed">
                Hello there! Welcome. How can I assist your operations today?
            </div>
        </div>
        <form onsubmit="event.preventDefault(); window.sendNuvisWebbuilderChatMessage(this);" class="p-3 bg-slate-950 border-t border-slate-800 flex gap-2">
            <input type="text" name="chat_msg" placeholder="Ask something..." required class="flex-1 bg-slate-850 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-teal-500">
            <button type="submit" class="font-bold px-3 py-1.5 rounded-lg text-xs hover:opacity-90" style="background-color: {{accentColor}}; color: {{bgColor}};"><i class="fas fa-paper-plane text-slate-950"></i></button>
        </form>
    </div>
</div>`
    },

    // === NICE-TO-HAVE / DIFFERENTIATORS ===
    {
        id: 'before_after_slider',
        name: 'Before/After Image Slider',
        category: 'Advanced',
        icon: 'fas fa-columns',
        schema: [
            { key: 'beforeImg', label: 'Before Image (Left)', type: 'text', default: 'https://images.unsplash.com/photo-1551434678-e076c223a692?w=600&auto=format&fit=crop&q=60' },
            { key: 'afterImg', label: 'After Image (Right)', type: 'text', default: 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=600&auto=format&fit=crop&q=60' },
            { key: 'bgColor', label: 'Wrapper Background', type: 'color', default: '#020617' },
            { key: 'sliderColor', label: 'Slider Handle Color', type: 'color', default: '#14b8a6' }
        ],
        html: `
<section class="py-16 px-8 rounded-lg text-center" style="background-color: {{bgColor}};" data-component="before_after_slider">
    <div class="before-after-container relative max-w-xl mx-auto h-72 rounded-xl overflow-hidden border border-slate-800 select-none">
        <div class="absolute inset-0 w-full h-full">
            <img src="{{afterImg}}" class="w-full h-full object-cover" />
        </div>
        <div class="before-image absolute inset-0 h-full overflow-hidden" style="width: 50%;">
            <img src="{{beforeImg}}" class="absolute left-0 top-0 w-full h-full object-cover max-w-none" style="width: 576px; height: 288px;" />
        </div>
        <input type="range" min="0" max="100" value="50" oninput="window.updateBeforeAfterSlider(this)" class="absolute inset-0 w-full h-full opacity-0 cursor-ew-resize z-20" />
        <div class="absolute top-0 bottom-0 pointer-events-none z-10 w-0.5" style="left: 50%; background-color: {{sliderColor}}; transform: translateX(-50%);"></div>
    </div>
</section>`
    },
    {
        id: 'logo_grid',
        name: 'Client Logo Grid',
        category: 'Features',
        icon: 'fas fa-images',
        schema: [
            { key: 'heading', label: 'Heading Title', type: 'text', default: 'Trusted by the world\'s best teams' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#0f172a' },
            { key: 'textColor', label: 'Text Color', type: 'color', default: '#94a3b8' }
        ],
        html: `
<section class="py-12 px-8 rounded-lg text-center" style="background-color: {{bgColor}}; color: {{textColor}};" data-component="logo_grid">
    <div class="max-w-6xl mx-auto">
        <h3 class="text-sm font-semibold uppercase tracking-wider mb-8">{{heading}}</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 items-center justify-items-center opacity-70">
            <img src="https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=120&auto=format&fit=crop&q=60" class="h-8 object-contain filter grayscale invert" alt="Logo 1" />
            <img src="https://images.unsplash.com/photo-1618005198143-d36680004cdf?w=120&auto=format&fit=crop&q=60" class="h-8 object-contain filter grayscale invert" alt="Logo 2" />
            <img src="https://images.unsplash.com/photo-1618005131359-2547e18930c4?w=120&auto=format&fit=crop&q=60" class="h-8 object-contain filter grayscale invert" alt="Logo 3" />
            <img src="https://images.unsplash.com/photo-1618005154255-61188587f2e0?w=120&auto=format&fit=crop&q=60" class="h-8 object-contain filter grayscale invert" alt="Logo 4" />
        </div>
    </div>
</section>`
    },
    {
        id: 'cta_banner',
        name: 'CTA Banner',
        category: 'Hero',
        icon: 'fas fa-bullhorn',
        schema: [
            { key: 'heading', label: 'CTA Heading', type: 'text', default: 'Ready to accelerate your workflow?' },
            { key: 'text', label: 'CTA Text Content', type: 'textarea', default: 'Join thousands of builders already compiling blazing fast commercial websites with Nuvis.' },
            { key: 'btnText', label: 'Button Text', type: 'text', default: 'Get Started Now' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#14b8a6' },
            { key: 'textColor', label: 'Heading Text Color', type: 'color', default: '#0f172a' },
            { key: 'btnBg', label: 'Button Background', type: 'color', default: '#0f172a' },
            { key: 'btnColor', label: 'Button Text Color', type: 'color', default: '#ffffff' }
        ],
        html: `
<section class="py-16 px-8 rounded-lg text-center" style="background-color: {{bgColor}}; color: {{textColor}};" data-component="cta_banner">
    <div class="max-w-4xl mx-auto space-y-6">
        <h2 class="text-3xl md:text-5xl font-black tracking-tight" style="color: {{textColor}};">{{heading}}</h2>
        <p class="text-base md:text-lg max-w-2xl mx-auto opacity-90" style="color: {{textColor}};">{{text}}</p>
        <div class="pt-4">
            <button class="font-extrabold px-8 py-4 rounded-lg shadow-lg transition duration-300 hover:scale-105" style="background-color: {{btnBg}}; color: {{btnColor}};">{{btnText}}</button>
        </div>
    </div>
</section>`
    },
    {
        id: 'team_showcase',
        name: 'Team Showcase Cards',
        category: 'Features',
        icon: 'fas fa-users',
        schema: [
            { key: 'heading', label: 'Section Heading', type: 'text', default: 'Meet our visionary leaders' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#0f172a' },
            { key: 'cardBg', label: 'Card Background', type: 'color', default: '#1e293b' },
            { key: 'headingColor', label: 'Heading Color', type: 'color', default: '#ffffff' },
            { key: 'textColor', label: 'Body Text Color', type: 'color', default: '#cbd5e1' }
        ],
        html: `
<section class="py-16 px-8 rounded-lg" style="background-color: {{bgColor}};" data-component="team_showcase">
    <div class="max-w-6xl mx-auto">
        <h2 class="text-3xl font-extrabold text-center mb-12" style="color: {{headingColor}};">{{heading}}</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="p-6 rounded-xl text-center shadow border border-slate-800" style="background-color: {{cardBg}};">
                <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300&auto=format&fit=crop&q=60" class="w-24 h-24 rounded-full mx-auto object-cover mb-4 border-2 border-teal-500" alt="Team Member 1" />
                <h3 class="text-lg font-bold" style="color: {{headingColor}};">Alexandra Vance</h3>
                <p class="text-xs text-teal-400 mb-3 uppercase tracking-wider font-semibold">CEO & Founder</p>
                <p class="text-xs leading-relaxed" style="color: {{textColor}};">Alexandra guides the overall strategic direction and architecture of Nuvis.</p>
            </div>
            <div class="p-6 rounded-xl text-center shadow border border-slate-800" style="background-color: {{cardBg}};">
                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=300&auto=format&fit=crop&q=60" class="w-24 h-24 rounded-full mx-auto object-cover mb-4 border-2 border-teal-500" alt="Team Member 2" />
                <h3 class="text-lg font-bold" style="color: {{headingColor}};">Marcus Sterling</h3>
                <p class="text-xs text-teal-400 mb-3 uppercase tracking-wider font-semibold">Chief Architect</p>
                <p class="text-xs leading-relaxed" style="color: {{textColor}};">Marcus leads the precompiled compiler engineering and database structures.</p>
            </div>
            <div class="p-6 rounded-xl text-center shadow border border-slate-800" style="background-color: {{cardBg}};">
                <img src="https://images.unsplash.com/photo-1517841905240-472988babdf9?w=300&auto=format&fit=crop&q=60" class="w-24 h-24 rounded-full mx-auto object-cover mb-4 border-2 border-teal-500" alt="Team Member 3" />
                <h3 class="text-lg font-bold" style="color: {{headingColor}};">Sonia Kova</h3>
                <p class="text-xs text-teal-400 mb-3 uppercase tracking-wider font-semibold">Head of UX Design</p>
                <p class="text-xs leading-relaxed" style="color: {{textColor}};">Sonia crafts clean aesthetic patterns to streamline drag-and-drop actions.</p>
            </div>
        </div>
    </div>
</section>`
    },
    {
        id: 'stats_grid',
        name: 'Statistics / Numbers Grid',
        category: 'Features',
        icon: 'fas fa-chart-bar',
        schema: [
            { key: 'heading', label: 'Section Heading', type: 'text', default: 'Our Performance In Numbers' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#020617' },
            { key: 'textColor', label: 'Text Color', type: 'color', default: '#cbd5e1' },
            { key: 'accentColor', label: 'Accent Highlight Color', type: 'color', default: '#14b8a6' }
        ],
        html: `
<section class="py-16 px-8 rounded-lg text-center" style="background-color: {{bgColor}}; color: {{textColor}};" data-component="stats_grid">
    <div class="max-w-6xl mx-auto">
        <h2 class="text-2xl md:text-3xl font-extrabold mb-12 text-white">{{heading}}</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
            <div>
                <div class="text-4xl md:text-5xl font-black mb-2" style="color: {{accentColor}};">99.9%</div>
                <div class="text-xs uppercase tracking-wider font-medium opacity-80">Server Uptime</div>
            </div>
            <div>
                <div class="text-4xl md:text-5xl font-black mb-2" style="color: {{accentColor}};">15M+</div>
                <div class="text-xs uppercase tracking-wider font-medium opacity-80">APIs Processed</div>
            </div>
            <div>
                <div class="text-4xl md:text-5xl font-black mb-2" style="color: {{accentColor}};">100ms</div>
                <div class="text-xs uppercase tracking-wider font-medium opacity-80">Average Load Time</div>
            </div>
            <div>
                <div class="text-4xl md:text-5xl font-black mb-2" style="color: {{accentColor}};">25k+</div>
                <div class="text-xs uppercase tracking-wider font-medium opacity-80">Global Installs</div>
            </div>
        </div>
    </div>
</section>`
    },
    {
        id: 'video_player',
        name: 'Interactive Video Player',
        category: 'Advanced',
        icon: 'fas fa-video',
        schema: [
            { key: 'heading', label: 'Video Title', type: 'text', default: 'See Nuvis Webbuilder In Action' },
            { key: 'videoUrl', label: 'Video URL', type: 'text', default: 'https://www.w3schools.com/html/mov_bbb.mp4' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#0f172a' },
            { key: 'headingColor', label: 'Heading Color', type: 'color', default: '#ffffff' }
        ],
        html: `
<section class="py-16 px-8 rounded-lg text-center" style="background-color: {{bgColor}};" data-component="video_player">
    <div class="max-w-4xl mx-auto space-y-6">
        <h2 class="text-2xl md:text-3xl font-bold" style="color: {{headingColor}};">{{heading}}</h2>
        <div class="relative aspect-video rounded-xl overflow-hidden border border-slate-800 shadow-2xl">
            <video src="{{videoUrl}}" controls="true" class="w-full h-full object-cover"></video>
        </div>
    </div>
</section>`
    },
    {
        id: 'image_gallery',
        name: 'Image Gallery',
        category: 'Advanced',
        icon: 'fas fa-images',
        schema: [
            { key: 'heading', label: 'Gallery Heading', type: 'text', default: 'Explore our latest visual designs' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#020617' },
            { key: 'headingColor', label: 'Heading Color', type: 'color', default: '#ffffff' }
        ],
        html: `
<section class="py-16 px-8 rounded-lg" style="background-color: {{bgColor}};" data-component="image_gallery">
    <div class="max-w-6xl mx-auto">
        <h2 class="text-2xl md:text-3xl font-extrabold text-center mb-10" style="color: {{headingColor}};">{{heading}}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            <div class="overflow-hidden rounded-xl border border-slate-800 shadow-lg group">
                <img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600&auto=format&fit=crop&q=60" class="w-full h-48 object-cover transition duration-300 group-hover:scale-105" alt="Gallery Image 1" />
            </div>
            <div class="overflow-hidden rounded-xl border border-slate-800 shadow-lg group">
                <img src="https://images.unsplash.com/photo-1504868584819-f8e8b4b6d7e3?w=600&auto=format&fit=crop&q=60" class="w-full h-48 object-cover transition duration-300 group-hover:scale-105" alt="Gallery Image 2" />
            </div>
            <div class="overflow-hidden rounded-xl border border-slate-800 shadow-lg group">
                <img src="https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=600&auto=format&fit=crop&q=60" class="w-full h-48 object-cover transition duration-300 group-hover:scale-105" alt="Gallery Image 3" />
            </div>
        </div>
    </div>
</section>`
    },

    // === ELEMENTOR EXTRA EXTRACTED WIDGETS ===
    {
        id: 'alert_block',
        name: 'Alert Notification Block',
        category: 'Features',
        icon: 'fas fa-exclamation-circle',
        schema: [
            { key: 'heading', label: 'Alert Heading', type: 'text', default: 'Attention Required' },
            { key: 'text', label: 'Alert Message', type: 'textarea', default: 'This is a beautifully styled dynamic alert banner designed to capture user focus.' },
            { key: 'alertType', label: 'Alert Status Type', type: 'select', default: 'info', options: [
                { value: 'info', label: 'Info (Teal)' },
                { value: 'warning', label: 'Warning (Amber)' },
                { value: 'success', label: 'Success (Green)' },
                { value: 'error', label: 'Error (Red)' }
            ]},
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#0f172a' },
            { key: 'textColor', label: 'Text Color', type: 'color', default: '#cbd5e1' }
        ],
        html: `
<div class="p-5 rounded-lg border flex items-start gap-4 shadow-md relative alert-box-el" style="background-color: {{bgColor}}; color: {{textColor}}; border-color: {{alertType === 'info' ? '#14b8a6' : alertType === 'warning' ? '#f59e0b' : alertType === 'success' ? '#10b981' : '#ef4444'}}" data-component="alert_block">
    <div class="text-lg shrink-0 mt-0.5" style="color: {{alertType === 'info' ? '#14b8a6' : alertType === 'warning' ? '#f59e0b' : alertType === 'success' ? '#10b981' : '#ef4444'}}">
        <i class="fas {{alertType === 'info' ? 'fa-info-circle' : alertType === 'warning' ? 'fa-exclamation-triangle' : alertType === 'success' ? 'fa-check-circle' : 'fa-times-circle'}}"></i>
    </div>
    <div class="flex-1 space-y-1">
        <h4 class="font-bold text-sm text-white">{{heading}}</h4>
        <p class="text-xs leading-relaxed opacity-90">{{text}}</p>
    </div>
    <button onclick="window.dismissAlertBlock(this)" class="text-slate-500 hover:text-white transition text-xs focus:outline-none" title="Dismiss Alert">
        <i class="fas fa-times"></i>
    </button>
</div>`
    },
    {
        id: 'icon_image_box',
        name: 'Icon / Image Box Box',
        category: 'Features',
        icon: 'fas fa-box-open',
        schema: [
            { key: 'heading', label: 'Box Title', type: 'text', default: 'High Density Architecture' },
            { key: 'text', label: 'Box Description', type: 'textarea', default: 'Combine beautiful icons or direct image uploads into clean card containers that match your theme perfectly.' },
            { key: 'iconClass', label: 'FontAwesome Icon', type: 'text', default: 'fas fa-cubes' },
            { key: 'imageUrl', label: 'Image URL (Optional)', type: 'text', default: '' },
            { key: 'bgColor', label: 'Card Background', type: 'color', default: '#1e293b' },
            { key: 'accentColor', label: 'Icon Accent Color', type: 'color', default: '#14b8a6' },
            { key: 'textColor', label: 'Text Color', type: 'color', default: '#cbd5e1' }
        ],
        html: `
<div class="p-6 rounded-xl border border-slate-800 text-center flex flex-col items-center gap-4 hover:border-slate-700 transition duration-300 shadow-xl max-w-sm mx-auto" style="background-color: {{bgColor}}; color: {{textColor}};" data-component="icon_image_box">
    {{imageUrl ? '<img src="' + imageUrl + '" class="w-16 h-16 object-cover rounded-lg shadow-md" />' : '<div class="w-12 h-12 rounded-full flex items-center justify-center text-xl shadow-lg" style="background-color: rgba(20, 184, 166, 0.1); color: ' + accentColor + '"><i class="' + iconClass + '"></i></div>'}}
    <div class="space-y-2">
        <h4 class="text-base font-bold text-white">{{heading}}</h4>
        <p class="text-xs leading-relaxed opacity-85">{{text}}</p>
    </div>
</div>`
    },
    {
        id: 'countdown_timer',
        name: 'Countdown Timer Clock',
        category: 'Advanced',
        icon: 'fas fa-clock',
        schema: [
            { key: 'heading', label: 'Timer Heading', type: 'text', default: 'Our Launching Event Begins In' },
            { key: 'targetDate', label: 'Target Date (YYYY-MM-DD HH:MM)', type: 'text', default: '2026-12-31 23:59' },
            { key: 'bgColor', label: 'Background Color', type: 'color', default: '#020617' },
            { key: 'cardBg', label: 'Metrics Card Background', type: 'color', default: '#0f172a' },
            { key: 'accentColor', label: 'Highlight Accent Color', type: 'color', default: '#14b8a6' }
        ],
        html: `
<section class="py-12 px-6 rounded-lg text-center" style="background-color: {{bgColor}};" data-component="countdown_timer" data-target="{{targetDate}}">
    <div class="max-w-2xl mx-auto space-y-6">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-400">{{heading}}</h3>
        <div class="flex justify-center items-center gap-4">
            <div class="p-4 rounded-xl shadow-lg border border-slate-800/80 w-20 flex flex-col items-center" style="background-color: {{cardBg}};">
                <span class="days-val text-2xl md:text-3xl font-black" style="color: {{accentColor}};">00</span>
                <span class="text-[9px] text-slate-400 uppercase font-bold tracking-widest mt-1">Days</span>
            </div>
            <span class="text-xl text-slate-600 font-black">:</span>
            <div class="p-4 rounded-xl shadow-lg border border-slate-800/80 w-20 flex flex-col items-center" style="background-color: {{cardBg}};">
                <span class="hours-val text-2xl md:text-3xl font-black" style="color: {{accentColor}};">00</span>
                <span class="text-[9px] text-slate-400 uppercase font-bold tracking-widest mt-1">Hours</span>
            </div>
            <span class="text-xl text-slate-600 font-black">:</span>
            <div class="p-4 rounded-xl shadow-lg border border-slate-800/80 w-20 flex flex-col items-center" style="background-color: {{cardBg}};">
                <span class="minutes-val text-2xl md:text-3xl font-black" style="color: {{accentColor}};">00</span>
                <span class="text-[9px] text-slate-400 uppercase font-bold tracking-widest mt-1">Min</span>
            </div>
            <span class="text-xl text-slate-600 font-black">:</span>
            <div class="p-4 rounded-xl shadow-lg border border-slate-800/80 w-20 flex flex-col items-center" style="background-color: {{cardBg}};">
                <span class="seconds-val text-2xl md:text-3xl font-black" style="color: {{accentColor}};">00</span>
                <span class="text-[9px] text-slate-400 uppercase font-bold tracking-widest mt-1">Sec</span>
            </div>
        </div>
    </div>
</section>`
    },
    {
        id: 'social_icons',
        name: 'Social Share Buttons',
        category: 'Advanced',
        icon: 'fas fa-share-alt',
        schema: [
            { key: 'heading', label: 'Heading Label', type: 'text', default: 'Follow our digital accounts' },
            { key: 'twitterUrl', label: 'Twitter/X URL', type: 'text', default: 'https://twitter.com' },
            { key: 'githubUrl', label: 'GitHub URL', type: 'text', default: 'https://github.com' },
            { key: 'linkedinUrl', label: 'LinkedIn URL', type: 'text', default: 'https://linkedin.com' },
            { key: 'bgColor', label: 'Wrapper Background', type: 'color', default: '#0f172a' },
            { key: 'accentColor', label: 'Button Hover Color', type: 'color', default: '#14b8a6' }
        ],
        html: `
<section class="py-8 px-6 rounded-lg text-center" style="background-color: {{bgColor}};" data-component="social_icons">
    <div class="max-w-md mx-auto space-y-4">
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-widest">{{heading}}</h4>
        <div class="flex justify-center items-center gap-4">
            <a href="{{twitterUrl}}" target="_blank" class="w-10 h-10 rounded-full border border-slate-800 flex items-center justify-center text-slate-300 transition duration-300 hover:text-slate-950 hover:scale-110 shadow" style="--hover-bg: {{accentColor}}" onmouseover="this.style.backgroundColor=this.style.getPropertyValue('--hover-bg'); this.style.borderColor=this.style.getPropertyValue('--hover-bg');" onmouseout="this.style.backgroundColor='transparent'; this.style.borderColor='#1e293b';">
                <i class="fab fa-twitter"></i>
            </a>
            <a href="{{githubUrl}}" target="_blank" class="w-10 h-10 rounded-full border border-slate-800 flex items-center justify-center text-slate-300 transition duration-300 hover:text-slate-950 hover:scale-110 shadow" style="--hover-bg: {{accentColor}}" onmouseover="this.style.backgroundColor=this.style.getPropertyValue('--hover-bg'); this.style.borderColor=this.style.getPropertyValue('--hover-bg');" onmouseout="this.style.backgroundColor='transparent'; this.style.borderColor='#1e293b';">
                <i class="fab fa-github"></i>
            </a>
            <a href="{{linkedinUrl}}" target="_blank" class="w-10 h-10 rounded-full border border-slate-800 flex items-center justify-center text-slate-300 transition duration-300 hover:text-slate-950 hover:scale-110 shadow" style="--hover-bg: {{accentColor}}" onmouseover="this.style.backgroundColor=this.style.getPropertyValue('--hover-bg'); this.style.borderColor=this.style.getPropertyValue('--hover-bg');" onmouseout="this.style.backgroundColor='transparent'; this.style.borderColor='#1e293b';">
                <i class="fab fa-linkedin-in"></i>
            </a>
        </div>
    </div>
</section>`
    },
    {
        id: 'progress_bar',
        name: 'Progress Meter Bar',
        category: 'Features',
        icon: 'fas fa-spinner',
        schema: [
            { key: 'heading', label: 'Progress Goal Description', type: 'text', default: 'Server Performance Rate' },
            { key: 'percentage', label: 'Progress Percentage', type: 'select', default: '85', options: [
                { value: '10', label: '10%' },
                { value: '25', label: '25%' },
                { value: '40', label: '40%' },
                { value: '50', label: '50%' },
                { value: '65', label: '65%' },
                { value: '75', label: '75%' },
                { value: '85', label: '85%' },
                { value: '95', label: '95%' },
                { value: '100', label: '100%' }
            ]},
            { key: 'bgColor', label: 'Container Background', type: 'color', default: '#0f172a' },
            { key: 'trackColor', label: 'Track Bar Background', type: 'color', default: '#1e293b' },
            { key: 'fillColor', label: 'Fill Bar Color', type: 'color', default: '#14b8a6' }
        ],
        html: `
<section class="py-10 px-8 rounded-lg" style="background-color: {{bgColor}};" data-component="progress_bar">
    <div class="max-w-xl mx-auto space-y-3">
        <div class="flex justify-between items-center text-xs font-bold">
            <span class="text-white">{{heading}}</span>
            <span style="color: {{fillColor}};">{{percentage}}%</span>
        </div>
        <div class="w-full h-3 rounded-full overflow-hidden" style="background-color: {{trackColor}};">
            <div class="h-full rounded-full transition-all duration-1000 ease-out" style="background-color: {{fillColor}}; width: {{percentage}}%;"></div>
        </div>
    </div>
</section>`
    }
];

// Global runtime scripts injection for live compiled renderings (Contact, Chatbot, and FAQ mechanics)
if (typeof window !== 'undefined') {
    // Mini-cart initializer
    window.updateMiniCartCount = function() {
        const countEl = document.getElementById('mini-cart-count');
        if (countEl) {
            let count = parseInt(localStorage.getItem('cart_count') || '0');
            countEl.innerText = count;
        }
    };

    window.addToMiniCart = function() {
        let count = parseInt(localStorage.getItem('cart_count') || '0') + 1;
        localStorage.setItem('cart_count', count);
        window.updateMiniCartCount();
        alert('Item added to cart!');
    };

    window.clearMiniCart = function() {
        localStorage.setItem('cart_count', '0');
        window.updateMiniCartCount();
    };

    // Before/After slider updater
    window.updateBeforeAfterSlider = function(input) {
        const parent = input.closest('.before-after-container');
        if (parent) {
            const beforeImg = parent.querySelector('.before-image');
            const handleLine = parent.querySelector('.absolute.top-0.bottom-0');
            if (beforeImg) beforeImg.style.width = input.value + '%';
            if (handleLine) handleLine.style.left = input.value + '%';
        }
    };

    // Carousel transitions
    window.nextCarouselSlide = function(btn) {
        const parent = btn.closest('.carousel-container');
        if (parent) {
            const slides = parent.querySelectorAll('.carousel-slide');
            let activeIdx = Array.from(slides).findIndex(s => !s.classList.contains('hidden'));
            if (activeIdx !== -1) {
                slides[activeIdx].classList.add('hidden');
                activeIdx = (activeIdx + 1) % slides.length;
                slides[activeIdx].classList.remove('hidden');
            }
        }
    };

    // Switch Tabs
    window.switchTab = function(btn, index) {
        const parent = btn.closest('.tabs-container');
        if (parent) {
            const tabs = parent.querySelectorAll('.tab-content');
            const buttons = parent.querySelectorAll('.tab-btn');
            tabs.forEach((t, idx) => t.classList.toggle('hidden', idx !== index));
            buttons.forEach((b, idx) => {
                const activeColor = b.getAttribute('data-active-color') || '#14b8a6';
                b.style.borderColor = idx === index ? activeColor : 'transparent';
                b.style.color = idx === index ? activeColor : '#94a3b8';
            });
        }
    };

    window.submitNuvisWebbuilderForm = function(formElement) {
        const btn = formElement.querySelector("button[type='submit']");
        const statusDiv = formElement.querySelector(".nuvis-webbuilder-form-status");

        if (btn) btn.disabled = true;
        if (statusDiv) {
            statusDiv.className = "nuvis-webbuilder-form-status p-3 rounded text-xs font-bold text-center bg-slate-800 text-slate-400";
            statusDiv.innerText = "Submitting secure entry...";
            statusDiv.classList.remove("hidden");
        }

        const formData = new FormData(formElement);
        formData.append('project_id', typeof PROJECT_ID !== 'undefined' ? PROJECT_ID : '1');

        fetch('submit_form.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                statusDiv.className = "nuvis-webbuilder-form-status p-3 rounded text-xs font-bold text-center bg-emerald-950 text-emerald-400 border border-emerald-500/20";
                statusDiv.innerText = data.message;
                formElement.reset();
            } else {
                statusDiv.className = "nuvis-webbuilder-form-status p-3 rounded text-xs font-bold text-center bg-red-950 text-red-400 border border-red-500/20";
                statusDiv.innerText = data.error || "Submission rejected.";
            }
        })
        .catch(err => {
            statusDiv.className = "nuvis-webbuilder-form-status p-3 rounded text-xs font-bold text-center bg-red-950 text-red-400 border border-red-500/20";
            statusDiv.innerText = "Connection Failed. Please try again.";
        })
        .finally(() => {
            if (btn) btn.disabled = false;
        });
    };

    window.toggleNuvisWebbuilderChat = function() {
        const win = document.getElementById('nuvis-webbuilder-chat-window');
        if (win) {
            win.classList.toggle('hidden');
        }
    };

    window.sendNuvisWebbuilderChatMessage = function(formElement) {
        const input = formElement.querySelector("input[name='chat_msg']");
        const logs = document.getElementById('nuvis-webbuilder-chat-logs');

        if (!input || !logs) return;

        const userMsg = input.value.trim();
        input.value = '';

        // Append User Message bubble
        const userDiv = document.createElement('div');
        userDiv.className = "bg-teal-500 text-slate-950 p-2 rounded-lg self-end max-w-[85%] leading-relaxed font-bold text-xs";
        userDiv.style.backgroundColor = 'rgb(20, 184, 166)';
        userDiv.innerText = userMsg;
        logs.appendChild(userDiv);
        logs.scrollTop = logs.scrollHeight;

        // Simulate AI Bot typing
        setTimeout(() => {
            const aiDiv = document.createElement('div');
            aiDiv.className = "bg-slate-800/80 p-2 rounded-lg self-start max-w-[85%] leading-relaxed text-xs";

            let responseText = "That's an interesting query! Our technical team can certainly assist you. Let us know your contact information via the contact form above.";
            if (userMsg.toLowerCase().includes('price') || userMsg.toLowerCase().includes('pricing') || userMsg.toLowerCase().includes('cost')) {
                responseText = "Our software licensing models start at just $0/mo for side developer projects, and $29/mo for complete Enterprise scopes including custom raw HTML features.";
            } else if (userMsg.toLowerCase().includes('feature') || userMsg.toLowerCase().includes('capabilities')) {
                responseText = "Nuvis Webbuilder specializes in real-time compilations, 100ms static optimization, robust parameterized data architectures, and dynamic visual layouts.";
            }

            aiDiv.innerText = responseText;
            logs.appendChild(aiDiv);
            logs.scrollTop = logs.scrollHeight;
        }, 800);
    };

    window.toggleNuvisFaqAccordion = function(buttonElement) {
        const accordionContent = buttonElement.nextElementSibling;
        const icon = buttonElement.querySelector("i");
        if (accordionContent) {
            const isHidden = accordionContent.classList.contains('hidden');
            accordionContent.classList.toggle('hidden', !isHidden);
            if (icon) {
                icon.className = isHidden ? "fas fa-chevron-up text-teal-400 transition-transform" : "fas fa-chevron-down text-slate-500 transition-transform";
            }
        }
    };

    // Dismiss Alert Block
    window.dismissAlertBlock = function(btn) {
        const box = btn.closest('.alert-box-el');
        if (box) {
            box.style.opacity = '0';
            setTimeout(() => box.remove(), 300);
        }
    };

    // Countdown Timer dynamic clock logic initializer
    window.initNuvisCountdownClocks = function() {
        const timers = document.querySelectorAll('[data-component="countdown_timer"]');
        timers.forEach(timer => {
            if (timer.dataset.hasTimerInitialized) return;
            timer.dataset.hasTimerInitialized = "true";

            const targetStr = timer.getAttribute('data-target') || '2026-12-31 23:59';
            const daysEl = timer.querySelector('.days-val');
            const hoursEl = timer.querySelector('.hours-val');
            const minsEl = timer.querySelector('.minutes-val');
            const secsEl = timer.querySelector('.seconds-val');

            const updateClock = () => {
                const target = new Date(targetStr.replace(' ', 'T')).getTime();
                const now = new Date().getTime();
                const diff = target - now;

                if (isNaN(target) || diff <= 0) {
                    if (daysEl) daysEl.innerText = "00";
                    if (hoursEl) hoursEl.innerText = "00";
                    if (minsEl) minsEl.innerText = "00";
                    if (secsEl) secsEl.innerText = "00";
                    return;
                }

                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const secs = Math.floor((diff % (1000 * 60)) / 1000);

                if (daysEl) daysEl.innerText = String(days).padStart(2, '0');
                if (hoursEl) hoursEl.innerText = String(hours).padStart(2, '0');
                if (minsEl) minsEl.innerText = String(mins).padStart(2, '0');
                if (secsEl) secsEl.innerText = String(secs).padStart(2, '0');
            };

            updateClock();
            setInterval(updateClock, 1000);
        });
    };

    // Auto-sync cart and initialize dynamic clocks on DOM load
    document.addEventListener('DOMContentLoaded', () => {
        window.updateMiniCartCount();
        window.initNuvisCountdownClocks();
    });

    // Handle React re-render visual hook to trigger clock setup
    const observer = new MutationObserver((mutations) => {
        window.initNuvisCountdownClocks();
    });
    observer.observe(document.body, { childList: true, subtree: true });
}
