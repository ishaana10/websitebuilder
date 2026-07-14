/* ============================================================
   WEBCRAFT COMPONENT REGISTRY  v2.0
   Single source of truth for all block types.
   Used by: builder shelf, properties panel, JS canvas renderer.
   The PHP render.php mirrors these definitions server-side.
   ============================================================ */

const WCComponents = (() => {

  const registry = {};

  function register(def) { registry[def.type] = def; }
  function get(type)     { return registry[type] ?? null; }

  function categories() {
    const cats = {};
    Object.values(registry).forEach(def => {
      if (!cats[def.category]) cats[def.category] = { label: def.category, components: [] };
      cats[def.category].components.push(def);
    });
    return Object.values(cats);
  }

  // ── HELPER ────────────────────────────────────────────────
  const e = str => String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');

  // ═══════════════════════════════════════════════════════════
  // NAVIGATION
  // ═══════════════════════════════════════════════════════════

  register({
    type: 'navbar',
    label: 'Navbar',
    category: 'Navigation',
    icon: 'fas fa-bars',
    defaultProps: {
      brand: 'My Brand',
      logo_url: '',
      bg: 'bg-slate-900',
      links: [{ label: 'Home', href: '#' }, { label: 'About', href: '#about' }, { label: 'Contact', href: '#contact' }]
    },
    props: [
      { key: 'brand',    label: 'Brand Name',    type: 'text' },
      { key: 'logo_url', label: 'Logo Image URL', type: 'text' },
      { key: 'bg', label: 'Background', type: 'select', options: [
        { value: 'bg-slate-900',  label: 'Dark Slate' },
        { value: 'bg-slate-950',  label: 'Near Black' },
        { value: 'bg-white',      label: 'White' },
        { value: 'bg-teal-900',   label: 'Teal Dark' },
        { value: 'bg-indigo-950', label: 'Deep Indigo' },
      ]},
      {
        key: 'links', label: 'Nav Links', type: 'array',
        itemLabel: 'Link',
        itemDefault: { label: 'New Link', href: '#' },
        fields: [
          { key: 'label', label: 'Label', type: 'text' },
          { key: 'href',  label: 'URL',   type: 'text' },
        ]
      },
    ],
    render: (p) => {
      const links = (p.links ?? []).map(l =>
        `<a href="${e(l.href)}" class="text-slate-300 hover:text-white text-sm font-medium transition">${e(l.label)}</a>`
      ).join('');
      const logo = p.logo_url ? `<img src="${e(p.logo_url)}" class="h-8 w-auto">` : '';
      return `<nav class="${e(p.bg)} px-6 py-4 flex items-center justify-between">
        <a href="#" class="font-black text-white text-lg flex items-center gap-2">${logo}${e(p.brand)}</a>
        <div class="hidden md:flex gap-6">${links}</div>
        <button class="md:hidden text-slate-400"><i class="fas fa-bars"></i></button>
      </nav>`;
    }
  });

  register({
    type: 'footer',
    label: 'Footer',
    category: 'Navigation',
    icon: 'fas fa-shoe-prints',
    defaultProps: {
      brand: 'WebCraft',
      logo_url: '',
      copyright: `© ${new Date().getFullYear()} All rights reserved.`,
      links: [{ label: 'Privacy', href: '#' }, { label: 'Terms', href: '#' }]
    },
    props: [
      { key: 'brand',     label: 'Brand Name', type: 'text' },
      { key: 'copyright', label: 'Copyright',  type: 'text' },
      { key: 'logo_url',  label: 'Logo URL',   type: 'text' },
      {
        key: 'links', label: 'Footer Links', type: 'array',
        itemLabel: 'Link',
        itemDefault: { label: 'New Link', href: '#' },
        fields: [
          { key: 'label', label: 'Label', type: 'text' },
          { key: 'href',  label: 'URL',   type: 'text' },
        ]
      },
    ],
    render: (p) => {
      const links = (p.links ?? []).map(l =>
        `<a href="${e(l.href)}" class="text-slate-400 hover:text-white text-sm transition">${e(l.label)}</a>`
      ).join('');
      const logo = p.logo_url ? `<img src="${e(p.logo_url)}" class="h-7">` : '';
      return `<footer class="bg-slate-950 border-t border-slate-800 py-10 px-6">
        <div class="max-w-5xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
          <div class="flex items-center gap-2">${logo}<span class="font-black text-white">${e(p.brand)}</span></div>
          <div class="flex gap-5">${links}</div>
          <p class="text-slate-500 text-xs">${e(p.copyright)}</p>
        </div>
      </footer>`;
    }
  });

  // ═══════════════════════════════════════════════════════════
  // SECTIONS
  // ═══════════════════════════════════════════════════════════

  register({
    type: 'hero',
    label: 'Hero Section',
    category: 'Sections',
    icon: 'fas fa-star',
    defaultProps: {
      heading: 'Welcome to My Site',
      subheading: 'Built fast with WebCraft.',
      button_text: 'Get Started',
      button_href: '#',
      bg: 'bg-slate-900',
      align: 'text-center',
      padding: 'py-20'
    },
    props: [
      { key: 'heading',     label: 'Heading',      type: 'text' },
      { key: 'subheading',  label: 'Subheading',   type: 'textarea' },
      { key: 'button_text', label: 'Button Label',  type: 'text' },
      { key: 'button_href', label: 'Button Link',   type: 'text' },
      { key: 'bg', label: 'Background', type: 'select', options: [
        { value: 'bg-slate-900',  label: 'Dark Slate' },
        { value: 'bg-indigo-950', label: 'Deep Indigo' },
        { value: 'bg-teal-900',   label: 'Teal Dark' },
        { value: 'bg-white',      label: 'White' },
      ]},
      { key: 'align', label: 'Text Align', type: 'select', options: [
        { value: 'text-center', label: 'Center' },
        { value: 'text-left',   label: 'Left' },
      ]},
      { key: 'padding', label: 'Padding', type: 'select', options: [
        { value: 'py-12', label: 'Small' },
        { value: 'py-16', label: 'Medium' },
        { value: 'py-20', label: 'Large' },
        { value: 'py-28', label: 'XL' },
      ]},
    ],
    render: (p) => {
      const btn = p.button_text
        ? `<a href="${e(p.button_href)}" class="inline-block mt-8 bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-8 py-3 rounded-lg text-sm transition">${e(p.button_text)}</a>`
        : '';
      return `<section class="${e(p.bg)} ${e(p.padding)} ${e(p.align)} px-6">
        <div class="max-w-3xl mx-auto">
          <h1 class="text-4xl font-black text-white leading-tight">${e(p.heading)}</h1>
          <p class="text-slate-300 mt-4 text-lg leading-relaxed">${e(p.subheading)}</p>
          ${btn}
        </div>
      </section>`;
    }
  });

  register({
    type: 'features_grid',
    label: 'Features Grid',
    category: 'Sections',
    icon: 'fas fa-th-large',
    defaultProps: {
      heading: 'Our Features',
      bg: 'bg-slate-900',
      padding: 'py-16',
      features: [
        { icon: 'fas fa-bolt',   title: 'Fast',     desc: 'Lightning-fast performance out of the box.' },
        { icon: 'fas fa-lock',   title: 'Secure',   desc: 'Enterprise-grade security built in.' },
        { icon: 'fas fa-expand', title: 'Scalable', desc: 'Grows seamlessly with your business.' },
      ]
    },
    props: [
      { key: 'heading', label: 'Section Heading', type: 'text' },
      { key: 'bg', label: 'Background', type: 'select', options: [
        { value: 'bg-slate-900',  label: 'Dark' },
        { value: 'bg-slate-950',  label: 'Darker' },
        { value: 'bg-white',      label: 'White' },
      ]},
      {
        key: 'features', label: 'Feature Cards', type: 'array',
        itemLabel: 'Feature',
        itemDefault: { icon: 'fas fa-star', title: 'New Feature', desc: 'Describe this feature.' },
        fields: [
          { key: 'icon',  label: 'Icon Class (FontAwesome)', type: 'text' },
          { key: 'title', label: 'Title',                    type: 'text' },
          { key: 'desc',  label: 'Description',              type: 'textarea' },
        ]
      },
    ],
    render: (p) => {
      const items = (p.features ?? []).map(f => `
        <div class="bg-slate-800 rounded-xl p-6 border border-slate-700 hover:border-teal-500/50 transition">
          <i class="${e(f.icon)} text-teal-400 text-2xl mb-4"></i>
          <h3 class="font-black text-white text-lg">${e(f.title)}</h3>
          <p class="text-slate-400 text-sm mt-2 leading-relaxed">${e(f.desc)}</p>
        </div>`).join('');
      return `<section class="${e(p.bg)} ${e(p.padding)} px-6">
        <div class="max-w-5xl mx-auto">
          <h2 class="text-3xl font-black text-white text-center mb-10">${e(p.heading)}</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">${items}</div>
        </div>
      </section>`;
    }
  });

  register({
    type: 'cta_banner',
    label: 'CTA Banner',
    category: 'Sections',
    icon: 'fas fa-bullhorn',
    defaultProps: {
      heading: 'Ready to get started?',
      subtext: 'Join thousands of builders today.',
      button_text: 'Start Free',
      button_href: '#',
      bg: 'bg-teal-900'
    },
    props: [
      { key: 'heading',     label: 'Heading',     type: 'text' },
      { key: 'subtext',     label: 'Subtext',     type: 'text' },
      { key: 'button_text', label: 'Button Label', type: 'text' },
      { key: 'button_href', label: 'Button Link',  type: 'text' },
      { key: 'bg', label: 'Background', type: 'select', options: [
        { value: 'bg-teal-900',   label: 'Teal Dark' },
        { value: 'bg-indigo-900', label: 'Indigo Dark' },
        { value: 'bg-slate-800',  label: 'Slate' },
      ]},
    ],
    render: (p) =>
      `<section class="${e(p.bg)} py-16 px-6 text-center">
        <h2 class="text-3xl font-black text-white">${e(p.heading)}</h2>
        <p class="text-teal-100 mt-3">${e(p.subtext)}</p>
        <a href="${e(p.button_href)}" class="inline-block mt-6 bg-white text-teal-900 font-black px-8 py-3 rounded-lg text-sm hover:bg-teal-50 transition">${e(p.button_text)}</a>
      </section>`
  });

  register({
    type: 'pricing_cards',
    label: 'Pricing Cards',
    category: 'Sections',
    icon: 'fas fa-tags',
    defaultProps: {
      heading: 'Simple Pricing',
      bg: 'bg-slate-950',
      padding: 'py-16',
      plans: [
        { name: 'Starter', price: 'Free',   desc: 'Perfect for individuals.', features: ['1 project','5 pages','Community support'], cta: 'Get Started', href: '#', highlight: false },
        { name: 'Pro',     price: '$19/mo', desc: 'For growing teams.',       features: ['10 projects','Unlimited pages','Priority support'], cta: 'Start Trial', href: '#', highlight: true },
        { name: 'Business',price: '$49/mo', desc: 'Enterprise power.',        features: ['Unlimited','Custom domain','Dedicated support'], cta: 'Contact Us', href: '#', highlight: false },
      ]
    },
    props: [
      { key: 'heading', label: 'Section Heading', type: 'text' },
      { key: 'bg', label: 'Background', type: 'select', options: [
        { value: 'bg-slate-950', label: 'Near Black' },
        { value: 'bg-slate-900', label: 'Dark' },
        { value: 'bg-white',     label: 'White' },
      ]},
      {
        key: 'plans', label: 'Pricing Plans', type: 'array',
        itemLabel: 'Plan',
        itemDefault: { name: 'New Plan', price: '$0', desc: '', features: ['Feature 1'], cta: 'Get Started', href: '#', highlight: false },
        fields: [
          { key: 'name',      label: 'Plan Name',               type: 'text' },
          { key: 'price',     label: 'Price',                   type: 'text' },
          { key: 'desc',      label: 'Description',             type: 'text' },
          { key: 'cta',       label: 'Button Label',            type: 'text' },
          { key: 'href',      label: 'Button URL',              type: 'text' },
          { key: 'highlight', label: 'Highlight (Most Popular)', type: 'toggle' },
        ]
      },
    ],
    render: (p) => {
      const cards = (p.plans ?? []).map(plan => {
        const feats = (plan.features ?? []).map(f => `<li class="flex items-center gap-2 text-slate-300 text-sm"><i class="fas fa-check text-teal-400 text-xs"></i>${e(f)}</li>`).join('');
        const ring   = plan.highlight ? 'border-teal-500 shadow-teal-500/10 shadow-xl' : 'border-slate-700';
        const btnCls = plan.highlight ? 'bg-teal-500 hover:bg-teal-400 text-slate-950' : 'bg-slate-700 hover:bg-slate-600 text-white';
        return `<div class="bg-slate-800 rounded-2xl p-8 border ${ring} flex flex-col">
          ${plan.highlight ? '<span class="text-xs font-black text-teal-400 uppercase tracking-widest mb-2">Most Popular</span>' : ''}
          <h3 class="text-xl font-black text-white">${e(plan.name)}</h3>
          <p class="text-3xl font-black text-white mt-2">${e(plan.price)}</p>
          <p class="text-slate-400 text-sm mt-1 mb-6">${e(plan.desc)}</p>
          <ul class="space-y-2 mb-8 flex-1">${feats}</ul>
          <a href="${e(plan.href)}" class="${btnCls} font-bold py-2.5 rounded-lg text-sm text-center transition">${e(plan.cta)}</a>
        </div>`;
      }).join('');
      return `<section class="${e(p.bg)} ${e(p.padding)} px-6">
        <div class="max-w-5xl mx-auto">
          <h2 class="text-3xl font-black text-white text-center mb-10">${e(p.heading)}</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">${cards}</div>
        </div>
      </section>`;
    }
  });

  // ═══════════════════════════════════════════════════════════
  // CONTENT
  // ═══════════════════════════════════════════════════════════

  register({
    type: 'text_block',
    label: 'Text Block',
    category: 'Content',
    icon: 'fas fa-align-left',
    defaultProps: { heading: 'Section Title', body: 'Your content goes here. Describe your product, service, or story.', bg: 'bg-slate-900', align: 'text-left', padding: 'py-12' },
    props: [
      { key: 'heading', label: 'Heading',   type: 'text' },
      { key: 'body',    label: 'Body Text', type: 'textarea' },
      { key: 'bg', label: 'Background', type: 'select', options: [
        { value: 'bg-slate-900', label: 'Dark' }, { value: 'bg-slate-950', label: 'Darker' }, { value: 'bg-white', label: 'White' }
      ]},
      { key: 'align', label: 'Text Align', type: 'select', options: [
        { value: 'text-left', label: 'Left' }, { value: 'text-center', label: 'Center' }
      ]},
      { key: 'padding', label: 'Padding', type: 'select', options: [
        { value: 'py-8', label: 'Small' }, { value: 'py-12', label: 'Medium' }, { value: 'py-16', label: 'Large' }
      ]},
    ],
    render: (p) =>
      `<section class="${e(p.bg)} ${e(p.padding)} ${e(p.align)} px-6">
        <div class="max-w-3xl mx-auto">
          <h2 class="text-2xl font-black text-white">${e(p.heading)}</h2>
          <p class="text-slate-300 mt-3 leading-relaxed">${e(p.body)}</p>
        </div>
      </section>`
  });

  register({
    type: 'image_block',
    label: 'Image',
    category: 'Content',
    icon: 'fas fa-image',
    defaultProps: { src: 'https://placehold.co/1200x500/1e293b/94a3b8?text=Your+Image', alt: 'Image', caption: '', padding: 'py-8' },
    props: [
      { key: 'src',     label: 'Image URL', type: 'text' },
      { key: 'alt',     label: 'Alt Text',  type: 'text' },
      { key: 'caption', label: 'Caption',   type: 'text' },
    ],
    render: (p) => {
      const caption = p.caption ? `<p class="text-slate-400 text-sm mt-3">${e(p.caption)}</p>` : '';
      return `<section class="bg-slate-900 ${e(p.padding)} px-6 text-center">
        <div class="max-w-4xl mx-auto">
          <img src="${e(p.src)}" alt="${e(p.alt)}" class="w-full rounded-xl shadow-xl">${caption}
        </div>
      </section>`;
    }
  });

  register({
    type: 'testimonials',
    label: 'Testimonials',
    category: 'Content',
    icon: 'fas fa-quote-left',
    defaultProps: {
      heading: 'What Our Users Say',
      bg: 'bg-slate-900',
      padding: 'py-16',
      items: [
        { quote: 'WebCraft transformed how we build sites. Incredible tool!', author: 'Jane D.', role: 'Product Manager' },
        { quote: 'Fast, intuitive, and beautiful. Exactly what we needed.',   author: 'Mark R.', role: 'Developer' },
      ]
    },
    props: [
      { key: 'heading', label: 'Heading', type: 'text' },
      { key: 'bg', label: 'Background', type: 'select', options: [
        { value: 'bg-slate-900', label: 'Dark' }, { value: 'bg-slate-950', label: 'Darker' }
      ]},
      {
        key: 'items', label: 'Testimonials', type: 'array',
        itemLabel: 'Testimonial',
        itemDefault: { quote: 'Add a quote here.', author: 'Author Name', role: 'Role / Company' },
        fields: [
          { key: 'quote',  label: 'Quote',  type: 'textarea' },
          { key: 'author', label: 'Author', type: 'text' },
          { key: 'role',   label: 'Role',   type: 'text' },
        ]
      },
    ],
    render: (p) => {
      const cards = (p.items ?? []).map(t => `
        <div class="bg-slate-800 rounded-xl p-6 border border-slate-700">
          <i class="fas fa-quote-left text-teal-400 text-lg mb-3"></i>
          <p class="text-slate-300 text-sm leading-relaxed italic">${e(t.quote)}</p>
          <div class="mt-4">
            <p class="text-white font-bold text-sm">${e(t.author)}</p>
            <p class="text-slate-500 text-xs">${e(t.role)}</p>
          </div>
        </div>`).join('');
      return `<section class="${e(p.bg)} ${e(p.padding)} px-6">
        <div class="max-w-4xl mx-auto">
          <h2 class="text-3xl font-black text-white text-center mb-10">${e(p.heading)}</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">${cards}</div>
        </div>
      </section>`;
    }
  });

  register({
    type: 'contact_form',
    label: 'Contact Form',
    category: 'Content',
    icon: 'fas fa-envelope',
    defaultProps: { heading: 'Get In Touch', subtext: 'We will get back to you within 24 hours.', bg: 'bg-slate-900', submit_label: 'Send Message', action: '' },
    props: [
      { key: 'heading',      label: 'Heading',        type: 'text' },
      { key: 'subtext',      label: 'Subtext',         type: 'text' },
      { key: 'submit_label', label: 'Button Label',    type: 'text' },
      { key: 'action',       label: 'Form Action URL', type: 'text' },
    ],
    render: (p) =>
      `<section class="${e(p.bg)} py-16 px-6">
        <div class="max-w-lg mx-auto">
          <h2 class="text-2xl font-black text-white text-center">${e(p.heading)}</h2>
          <p class="text-slate-400 text-sm text-center mt-2 mb-8">${e(p.subtext)}</p>
          <form action="${e(p.action)}" method="POST" class="space-y-4">
            <input type="text"  name="name"    placeholder="Your Name"    class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:border-teal-500">
            <input type="email" name="email"   placeholder="Email Address" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:border-teal-500">
            <textarea name="message" rows="4"  placeholder="Your message…" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:border-teal-500 resize-none"></textarea>
            <button type="submit" class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-3 rounded-lg text-sm transition">${e(p.submit_label)}</button>
          </form>
        </div>
      </section>`
  });

  // ═══════════════════════════════════════════════════════════
  // ADVANCED
  // ═══════════════════════════════════════════════════════════

  register({
    type: 'html_block',
    label: 'Custom HTML',
    category: 'Advanced',
    icon: 'fas fa-code',
    defaultProps: { html: '<div class="p-8 text-white text-center bg-slate-800 rounded-xl"><p class="text-teal-400 font-bold">Custom HTML Block</p><p class="text-slate-400 text-sm mt-2">Edit in the properties panel.</p></div>' },
    props: [
      { key: 'html', label: 'Raw HTML', type: 'textarea' }
    ],
    render: (p) => p.html ?? ''
  });

  register({
    type: 'spacer',
    label: 'Spacer',
    category: 'Advanced',
    icon: 'fas fa-arrows-alt-v',
    defaultProps: { height: 'h-16', bg: 'bg-transparent' },
    props: [
      { key: 'height', label: 'Height', type: 'select', options: [
        { value: 'h-8',  label: 'Small (h-8)' },
        { value: 'h-16', label: 'Medium (h-16)' },
        { value: 'h-24', label: 'Large (h-24)' },
        { value: 'h-32', label: 'XL (h-32)' },
      ]},
      { key: 'bg', label: 'Background', type: 'select', options: [
        { value: 'bg-transparent', label: 'Transparent' },
        { value: 'bg-slate-900',   label: 'Dark Slate' },
        { value: 'bg-slate-950',   label: 'Near Black' },
      ]},
    ],
    render: (p) => `<div class="${e(p.height)} ${e(p.bg)} w-full"></div>`
  });

  return { register, get, categories };

})();
