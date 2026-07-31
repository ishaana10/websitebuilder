/**
 * Nuvis Webbuilder Pre-Built High-Quality UI Widgets & Components
 * Tailored for modern, beautiful, and fully customizable responsive websites
 */

const UI_COMPONENTS = [
    {
        id: 'navbar',
        name: 'Responsive Navigation Bar',
        category: 'Headers',
        icon: 'fas fa-bars',
        html: `
<nav class="bg-slate-900 text-white py-4 px-6 flex justify-between items-center shadow-md rounded-lg" data-component="navbar">
    <div class="text-xl font-extrabold tracking-wider text-teal-400">NUVIS WEBBUILDER</div>
    <div class="hidden md:flex space-x-6">
        <a href="#home" class="hover:text-teal-300 transition duration-300">Home</a>
        <a href="#features" class="hover:text-teal-300 transition duration-300">Features</a>
        <a href="#pricing" class="hover:text-teal-300 transition duration-300">Pricing</a>
        <a href="#contact" class="hover:text-teal-300 transition duration-300">Contact</a>
    </div>
    <div>
        <a href="#get-started" class="bg-teal-500 text-slate-950 font-bold px-4 py-2 rounded hover:bg-teal-400 transition duration-300 text-sm">Get Started</a>
    </div>
</nav>`
    },
    {
        id: 'hero',
        name: 'Premium Hero Section',
        category: 'Hero',
        icon: 'fas fa-rocket',
        html: `
<section class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white py-20 px-8 rounded-lg text-center" data-component="hero">
    <div class="max-w-3xl mx-auto">
        <span class="bg-teal-500/10 text-teal-400 font-semibold px-4 py-1.5 rounded-full text-xs uppercase tracking-widest border border-teal-500/20">All-In-One Solution</span>
        <h1 class="text-4xl md:text-6xl font-black mt-6 tracking-tight leading-none">Build Stunning Websites In Minutes</h1>
        <p class="text-slate-300 mt-6 text-lg md:text-xl leading-relaxed">The ultimate low-code drag and drop page builder designed to transform complex ideas into high-converting responsive web solutions.</p>
        <div class="mt-10 flex flex-wrap justify-center gap-4">
            <button class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-extrabold px-8 py-4 rounded-lg shadow-lg shadow-teal-500/20 transition-all duration-300 transform hover:-translate-y-0.5">Start For Free</button>
            <button class="bg-slate-800 hover:bg-slate-700 text-white font-bold px-8 py-4 rounded-lg border border-slate-700 transition-all duration-300">Learn More</button>
        </div>
    </div>
</section>`
    },
    {
        id: 'feature_split',
        name: 'Side-by-Side Split Feature',
        category: 'Features',
        icon: 'fas fa-columns',
        html: `
<section class="py-16 px-8 bg-slate-900 text-white rounded-lg" data-component="feature_split">
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center gap-12">
        <div class="flex-1 space-y-6">
            <span class="bg-teal-500/10 text-teal-400 font-semibold px-3 py-1 rounded-full text-xs uppercase tracking-wider">Next-Gen Interface</span>
            <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight">Elegance meets pure performance.</h2>
            <p class="text-slate-300 text-base leading-relaxed">Craft a beautifully structured layout where your imagery directly interfaces with your product description. Adjust photo alignments and style typography to match your layout's specific branding tone perfectly.</p>
            <div>
                <a href="#action" class="inline-block bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold px-6 py-3 rounded transition duration-300 text-sm">Explore Details</a>
            </div>
        </div>
        <div class="flex-1 w-full">
            <img src="https://images.unsplash.com/photo-1551434678-e076c223a692?w=800&auto=format&fit=crop&q=60" alt="Visual Split Illustration" class="w-full object-cover rounded-xl shadow-lg border border-slate-800" />
        </div>
    </div>
</section>`
    },
    {
        id: 'features',
        name: 'Three-Column Features Grid',
        category: 'Features',
        icon: 'fas fa-th-large',
        html: `
<section class="py-16 px-8 bg-slate-50 text-slate-800 rounded-lg" data-component="features">
    <div class="max-w-6xl mx-auto text-center">
        <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Supercharged Features</h2>
        <p class="text-slate-500 mt-2 text-lg">Engineered for performance, customizability, and raw speed.</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-12">
            <div class="bg-white p-8 rounded-xl shadow-sm border border-slate-100 hover:shadow-md transition duration-300">
                <div class="bg-teal-500/10 text-teal-600 w-12 h-12 rounded-lg flex items-center justify-center text-xl font-bold mx-auto mb-4">
                    <i class="fas fa-bolt"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900">Blazing Fast</h3>
                <p class="text-slate-500 mt-2 text-sm leading-relaxed">Lightning-fast static page compiling ensures search engine performance optimization and perfect load times.</p>
            </div>
            <div class="bg-white p-8 rounded-xl shadow-sm border border-slate-100 hover:shadow-md transition duration-300">
                <div class="bg-teal-500/10 text-teal-600 w-12 h-12 rounded-lg flex items-center justify-center text-xl font-bold mx-auto mb-4">
                    <i class="fas fa-lock"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900">Highly Secure</h3>
                <p class="text-slate-500 mt-2 text-sm leading-relaxed">Integrated XSS filtering, CSRF mitigation safeguards, and secure parameterized queries defend your data.</p>
            </div>
            <div class="bg-white p-8 rounded-xl shadow-sm border border-slate-100 hover:shadow-md transition duration-300">
                <div class="bg-teal-500/10 text-teal-600 w-12 h-12 rounded-lg flex items-center justify-center text-xl font-bold mx-auto mb-4">
                    <i class="fas fa-edit"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900">Low Code Custom</h3>
                <p class="text-slate-500 mt-2 text-sm leading-relaxed">Write raw custom HTML or adjust margins, paddings, borders, colors, and button pathways dynamically.</p>
            </div>
        </div>
    </div>
</section>`
    },
    {
        id: 'gallery',
        name: 'Premium Media Gallery',
        category: 'Advanced',
        icon: 'fas fa-images',
        html: `
<section class="py-16 px-8 bg-slate-900 text-white rounded-lg" data-component="gallery">
    <div class="max-w-6xl mx-auto text-center">
        <h2 class="text-3xl font-extrabold tracking-tight">Our Premium Showcase</h2>
        <p class="text-slate-400 mt-2 text-sm max-w-xl mx-auto">Explore high-fidelity visual representations of our work, system architectures, and client results.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 mt-12">
            <div class="overflow-hidden rounded-lg shadow-md border border-slate-800 bg-slate-950 group">
                <img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600&auto=format&fit=crop&q=60" alt="Showcase 1" class="w-full h-48 object-cover transition duration-300 group-hover:scale-105" />
                <div class="p-4 text-left">
                    <h4 class="font-bold text-xs text-teal-400 uppercase tracking-widest">Workspace</h4>
                    <p class="text-xs text-slate-300 mt-1">Stunning layout interfaces with zero drag lag.</p>
                </div>
            </div>
            <div class="overflow-hidden rounded-lg shadow-md border border-slate-800 bg-slate-950 group">
                <img src="https://images.unsplash.com/photo-1504868584819-f8e8b4b6d7e3?w=600&auto=format&fit=crop&q=60" alt="Showcase 2" class="w-full h-48 object-cover transition duration-300 group-hover:scale-105" />
                <div class="p-4 text-left">
                    <h4 class="font-bold text-xs text-teal-400 uppercase tracking-widest">Analytics</h4>
                    <p class="text-xs text-slate-300 mt-1">Track interaction insights natively on client forms.</p>
                </div>
            </div>
            <div class="overflow-hidden rounded-lg shadow-md border border-slate-800 bg-slate-950 group">
                <img src="https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=600&auto=format&fit=crop&q=60" alt="Showcase 3" class="w-full h-48 object-cover transition duration-300 group-hover:scale-105" />
                <div class="p-4 text-left">
                    <h4 class="font-bold text-xs text-teal-400 uppercase tracking-widest">AI Networks</h4>
                    <p class="text-xs text-slate-300 mt-1">Integrate automated chatbot layers to boost signups.</p>
                </div>
            </div>
        </div>
    </div>
</section>`
    },
    {
        id: 'team',
        name: 'Team Grid Showcase',
        category: 'Features',
        icon: 'fas fa-users',
        html: `
<section class="py-16 px-8 bg-slate-50 text-slate-800 rounded-lg" data-component="team">
    <div class="max-w-6xl mx-auto text-center">
        <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Meet the Innovators</h2>
        <p class="text-slate-500 mt-2 text-sm max-w-md mx-auto">The engineering powerhouses behind our state-of-the-art visual builder operations.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-8 mt-12">
            <div class="bg-white p-6 rounded-xl border border-slate-100 text-center shadow-sm">
                <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300&auto=format&fit=crop&q=60" alt="Sarah Connor" class="w-20 h-20 rounded-full object-cover mx-auto mb-4 border-2 border-teal-500 shadow-sm" />
                <h4 class="font-bold text-slate-900 text-base">Sarah Connor</h4>
                <p class="text-xs text-slate-500 mt-1">Founder & CEO</p>
            </div>
            <div class="bg-white p-6 rounded-xl border border-slate-100 text-center shadow-sm">
                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=300&auto=format&fit=crop&q=60" alt="Marcus Wright" class="w-20 h-20 rounded-full object-cover mx-auto mb-4 border-2 border-teal-500 shadow-sm" />
                <h4 class="font-bold text-slate-900 text-base">Marcus Wright</h4>
                <p class="text-xs text-slate-500 mt-1">Lead Architect</p>
            </div>
            <div class="bg-white p-6 rounded-xl border border-slate-100 text-center shadow-sm">
                <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=300&auto=format&fit=crop&q=60" alt="Elena Rostova" class="w-20 h-20 rounded-full object-cover mx-auto mb-4 border-2 border-teal-500 shadow-sm" />
                <h4 class="font-bold text-slate-900 text-base">Elena Rostova</h4>
                <p class="text-xs text-slate-500 mt-1">Lead Front-end</p>
            </div>
            <div class="bg-white p-6 rounded-xl border border-slate-100 text-center shadow-sm">
                <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=300&auto=format&fit=crop&q=60" alt="John Reese" class="w-20 h-20 rounded-full object-cover mx-auto mb-4 border-2 border-teal-500 shadow-sm" />
                <h4 class="font-bold text-slate-900 text-base">John Reese</h4>
                <p class="text-xs text-slate-500 mt-1">Security Engineering</p>
            </div>
        </div>
    </div>
</section>`
    },
    {
        id: 'faq',
        name: 'Interactive FAQ Accordion',
        category: 'Advanced',
        icon: 'fas fa-question-circle',
        html: `
<section class="py-16 px-8 bg-slate-900 text-white rounded-lg" data-component="faq">
    <div class="max-w-4xl mx-auto">
        <h2 class="text-3xl font-extrabold text-center tracking-tight">Frequently Asked Questions</h2>
        <p class="text-slate-400 text-center mt-2 text-sm">Everything you need to know about our products, licenses, and visual architectures.</p>

        <div class="mt-12 space-y-4">
            <div class="bg-slate-950 border border-slate-800 rounded-lg overflow-hidden">
                <button onclick="window.toggleNuvisFaqAccordion(this)" class="w-full text-left px-6 py-4 font-bold text-sm flex justify-between items-center hover:bg-slate-900 transition">
                    <span>How does the local compiling mechanism operate?</span>
                    <i class="fas fa-chevron-down text-slate-500 transition-transform"></i>
                </button>
                <div class="faq-accordion-content hidden px-6 pb-5 text-xs text-slate-400 border-t border-slate-900/50 pt-3 leading-relaxed">
                    Our platform compiles visual assets into highly optimized, fully responsive static HTML output instantly. There are no client-side rendering bottlenecks or unnecessary database calls.
                </div>
            </div>
            <div class="bg-slate-950 border border-slate-800 rounded-lg overflow-hidden">
                <button onclick="window.toggleNuvisFaqAccordion(this)" class="w-full text-left px-6 py-4 font-bold text-sm flex justify-between items-center hover:bg-slate-900 transition">
                    <span>Can I export and host the compiled pages on my own server?</span>
                    <i class="fas fa-chevron-down text-slate-500 transition-transform"></i>
                </button>
                <div class="faq-accordion-content hidden px-6 pb-5 text-xs text-slate-400 border-t border-slate-900/50 pt-3 leading-relaxed">
                    Yes! With our absolute export capability, you can click 'ZIP' to immediately download an entire production bundle including styling sheets, customized JavaScript nodes, and static HTML templates.
                </div>
            </div>
            <div class="bg-slate-950 border border-slate-800 rounded-lg overflow-hidden">
                <button onclick="window.toggleNuvisFaqAccordion(this)" class="w-full text-left px-6 py-4 font-bold text-sm flex justify-between items-center hover:bg-slate-900 transition">
                    <span>Are my custom-injected scripts filtered or fully sanitized?</span>
                    <i class="fas fa-chevron-down text-slate-500 transition-transform"></i>
                </button>
                <div class="faq-accordion-content hidden px-6 pb-5 text-xs text-slate-400 border-t border-slate-900/50 pt-3 leading-relaxed">
                    Custom-injected CSS and JS styling scripts are kept safe for visual preview compiling but undergo strict server-side validation upon publishing, protecting your public web users from visual scripting injections.
                </div>
            </div>
        </div>
    </div>
</section>`
    },
    {
        id: 'testimonials',
        name: 'Testimonials Grid',
        category: 'Features',
        icon: 'fas fa-star',
        html: `
<section class="py-16 px-8 bg-slate-950 text-white rounded-lg" data-component="testimonials">
    <div class="max-w-6xl mx-auto text-center">
        <h2 class="text-3xl font-extrabold tracking-tight">Trusted Worldwide</h2>
        <p class="text-slate-400 mt-2 text-sm max-w-sm mx-auto">Join thousands of software engineers building faster than ever before.</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-12">
            <div class="bg-slate-900 p-8 rounded-2xl border border-slate-800 text-left flex flex-col justify-between">
                <p class="text-sm text-slate-300 italic leading-relaxed">"Nuvis Webbuilder solved all our quick deployment needs. Drag-and-drop combined with raw CSS injection is a developer's dream come true."</p>
                <div class="flex items-center gap-3 mt-6">
                    <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&auto=format&fit=crop&q=60" alt="Clara Jenkins" class="w-10 h-10 rounded-full object-cover" />
                    <div>
                        <h4 class="font-bold text-xs">Clara Jenkins</h4>
                        <p class="text-[10px] text-slate-500">Tech Lead at Netcore</p>
                    </div>
                </div>
            </div>
            <div class="bg-slate-900 p-8 rounded-2xl border border-slate-800 text-left flex flex-col justify-between">
                <p class="text-sm text-slate-300 italic leading-relaxed">"Rebuilding the builder into React makes it completely seamless. State tracking, live preview compiler, and zero canvas reload lag are incredible features."</p>
                <div class="flex items-center gap-3 mt-6">
                    <img src="https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=150&auto=format&fit=crop&q=60" alt="David Miller" class="w-10 h-10 rounded-full object-cover" />
                    <div>
                        <h4 class="font-bold text-xs">David Miller</h4>
                        <p class="text-[10px] text-slate-500">Fullstack Engineer</p>
                    </div>
                </div>
            </div>
            <div class="bg-slate-900 p-8 rounded-2xl border border-slate-800 text-left flex flex-col justify-between">
                <p class="text-sm text-slate-300 italic leading-relaxed">"We compiled 15 pages in one afternoon, and absolute loading times decreased significantly. The mobile viewport bezel and undo hotkeys make editing rapid."</p>
                <div class="flex items-center gap-3 mt-6">
                    <img src="https://images.unsplash.com/photo-1517841905240-472988babdf9?w=150&auto=format&fit=crop&q=60" alt="Samantha Wu" class="w-10 h-10 rounded-full object-cover" />
                    <div>
                        <h4 class="font-bold text-xs">Samantha Wu</h4>
                        <p class="text-[10px] text-slate-500">SaaS Growth Specialist</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>`
    },
    {
        id: 'pricing',
        name: 'Pricing Plans Block',
        category: 'Pricing',
        icon: 'fas fa-tags',
        html: `
<section class="py-16 px-8 bg-white text-slate-800 rounded-lg" data-component="pricing">
    <div class="max-w-5xl mx-auto text-center">
        <h2 class="text-3xl font-extrabold text-slate-900">Transparent Premium Pricing</h2>
        <p class="text-slate-500 mt-2">Pick a plan that matches your production needs. No hidden fees.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-12 max-w-3xl mx-auto">
            <!-- Free Plan -->
            <div class="bg-slate-50 p-8 rounded-2xl border border-slate-200 flex flex-col justify-between hover:border-slate-300 transition duration-300">
                <div>
                    <h3 class="text-lg font-bold text-slate-700">Developer Plan</h3>
                    <div class="text-4xl font-black mt-4">$0 <span class="text-sm font-normal text-slate-500">/mo</span></div>
                    <p class="text-slate-500 text-xs mt-2">Perfect for side projects and local prototyping</p>
                    <ul class="mt-6 text-left space-y-3 text-sm">
                        <li class="flex items-center text-slate-600"><i class="fas fa-check text-emerald-500 mr-2"></i> 3 Projects Sandbox</li>
                        <li class="flex items-center text-slate-600"><i class="fas fa-check text-emerald-500 mr-2"></i> HTML5 Export Ready</li>
                        <li class="flex items-center text-slate-400 line-through"><i class="fas fa-times text-slate-300 mr-2"></i> Custom Domain Linking</li>
                    </ul>
                </div>
                <button class="bg-slate-800 hover:bg-slate-700 text-white font-bold w-full py-3 rounded-lg mt-8 transition">Get Started</button>
            </div>
            <!-- Pro Plan -->
            <div class="bg-slate-900 text-white p-8 rounded-2xl border-2 border-teal-500 flex flex-col justify-between shadow-xl relative">
                <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-teal-500 text-slate-950 font-black text-xs px-3 py-1 rounded-full uppercase tracking-wider">Most Popular</span>
                <div>
                    <h3 class="text-lg font-bold text-teal-400">Enterprise Pro</h3>
                    <div class="text-4xl font-black mt-4">$29 <span class="text-sm font-normal text-slate-400">/mo</span></div>
                    <p class="text-slate-400 text-xs mt-2">For custom scale deployment of premium apps</p>
                    <ul class="mt-6 text-left space-y-3 text-sm">
                        <li class="flex items-center text-slate-200"><i class="fas fa-check text-teal-400 mr-2"></i> Unlimited Sites</li>
                        <li class="flex items-center text-slate-200"><i class="fas fa-check text-teal-400 mr-2"></i> Priority Live Compiles</li>
                        <li class="flex items-center text-slate-200"><i class="fas fa-check text-teal-400 mr-2"></i> Full Raw HTML Access</li>
                        <li class="flex items-center text-slate-200"><i class="fas fa-check text-teal-400 mr-2"></i> Premium Developer Templates</li>
                    </ul>
                </div>
                <button class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-black w-full py-3 rounded-lg mt-8 transition">Go Enterprise</button>
            </div>
        </div>
    </div>
</section>`
    },
    {
        id: 'cta',
        name: 'Urgent Call To Action',
        category: 'Hero',
        icon: 'fas fa-bullhorn',
        html: `
<section class="py-16 px-8 bg-gradient-to-r from-teal-500 to-emerald-600 text-slate-950 rounded-lg text-center relative overflow-hidden" data-component="cta">
    <div class="max-w-4xl mx-auto space-y-6 relative z-10">
        <h2 class="text-3xl md:text-5xl font-black tracking-tight uppercase leading-none">Ready to start compiling?</h2>
        <p class="text-slate-900 font-medium text-base md:text-lg max-w-xl mx-auto leading-relaxed">Deploy premium single-page web applications with absolute precision and unmatched modern aesthetics.</p>
        <div class="flex justify-center gap-4 pt-4">
            <a href="#register" class="bg-slate-950 hover:bg-slate-900 text-teal-400 font-extrabold px-8 py-3 rounded-lg text-sm tracking-wide shadow-xl transition transform hover:-translate-y-0.5">Start Now - Free</a>
        </div>
    </div>
    <div class="absolute -right-16 -bottom-16 w-64 h-64 bg-white/5 rounded-full blur-2xl"></div>
</section>`
    },
    {
        id: 'contact',
        name: 'Secure Contact Form',
        category: 'Forms',
        icon: 'fas fa-envelope',
        html: `
<section class="py-16 px-8 bg-slate-900 text-white rounded-lg" data-component="contact">
    <div class="max-w-md mx-auto text-center">
        <h2 class="text-3xl font-extrabold text-teal-400">Get In Touch</h2>
        <p class="text-slate-400 mt-2">Have questions? Drop us a line and we'll reply shortly.</p>

        <!-- Live AJAX Interactive Form -->
        <form class="mt-8 space-y-4" onsubmit="event.preventDefault(); window.submitNuvisWebbuilderForm(this);">
            <div class="nuvis-webbuilder-form-status hidden p-3 rounded text-xs font-bold text-center"></div>
            <input type="text" name="name" placeholder="Full Name" required class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white focus:border-teal-500 focus:outline-none text-sm" />
            <input type="email" name="email" placeholder="Email Address" required class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white focus:border-teal-500 focus:outline-none text-sm" />
            <textarea name="message" placeholder="Write message..." rows="4" required class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white focus:border-teal-500 focus:outline-none text-sm"></textarea>
            <button type="submit" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold w-full py-3 rounded-lg transition-all text-sm tracking-wide flex items-center justify-center gap-2">
                <span>Send Message</span>
            </button>
        </form>
    </div>
</section>`
    },
    {
        id: 'chatbot',
        name: 'Interactive AI Chatbot',
        category: 'Forms',
        icon: 'fas fa-comments',
        html: `
<div class="fixed bottom-6 right-6 z-50 font-sans" data-component="chatbot">
    <!-- Floating Bubble Button -->
    <button onclick="window.toggleNuvisWebbuilderChat()" class="bg-teal-500 hover:bg-teal-400 text-slate-950 w-14 h-14 rounded-full flex items-center justify-center shadow-2xl transition duration-300 focus:outline-none">
        <i class="fas fa-comments text-xl"></i>
    </button>

    <!-- Chat Dialog Window (Hidden by default) -->
    <div id="nuvis-webbuilder-chat-window" class="hidden absolute bottom-16 right-0 w-80 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl overflow-hidden flex flex-col">
        <div class="bg-slate-950 p-4 border-b border-slate-800 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="font-bold text-xs text-white uppercase tracking-wider">AI Support Bot</span>
            </div>
            <button onclick="window.toggleNuvisWebbuilderChat()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <!-- Conversation logs -->
        <div id="nuvis-webbuilder-chat-logs" class="p-4 h-48 overflow-y-auto space-y-3 flex flex-col text-xs text-slate-300">
            <div class="bg-slate-800/80 p-2 rounded-lg self-start max-w-[85%] leading-relaxed">
                Hello there! Welcome to our website. How can I assist your operations today?
            </div>
        </div>

        <!-- Chat form input -->
        <form onsubmit="event.preventDefault(); window.sendNuvisWebbuilderChatMessage(this);" class="p-3 bg-slate-950 border-t border-slate-800 flex gap-2">
            <input type="text" name="chat_msg" placeholder="Ask something..." required class="flex-1 bg-slate-850 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-teal-500">
            <button type="submit" class="bg-teal-500 text-slate-950 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-teal-400 transition"><i class="fas fa-paper-plane"></i></button>
        </form>
    </div>
</div>`
    },
    {
        id: 'html_raw',
        name: 'Low-Code Custom Raw HTML',
        category: 'Advanced',
        icon: 'fas fa-code',
        html: `
<div class="bg-slate-100 p-8 rounded-lg border-2 border-dashed border-slate-300 text-center" data-component="html_raw">
    <div class="text-slate-400 mb-2"><i class="fas fa-code text-2xl"></i></div>
    <div class="font-bold text-slate-700 text-sm">Low-Code Raw HTML Area</div>
    <div class="text-slate-500 text-xs mt-1">Select this block and click 'Edit HTML' in properties to insert raw customized layout code.</div>
    <div class="custom-html-container hidden mt-4 text-left"></div>
</div>`
    },
    {
        id: 'footer',
        name: 'Corporate Footer Block',
        category: 'Footers',
        icon: 'fas fa-shoe-prints',
        html: `
<footer class="bg-slate-950 text-slate-400 py-12 px-8 rounded-lg text-center" data-component="footer">
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6">
        <div class="text-lg font-black text-white">NUVIS WEBBUILDER BUILDER</div>
        <div class="flex space-x-6 text-sm">
            <a href="#" class="hover:text-white transition">Privacy Policy</a>
            <a href="#" class="hover:text-white transition">Terms of Use</a>
            <a href="#" class="hover:text-white transition">Support</a>
        </div>
        <div class="text-xs text-slate-600">&copy; ${new Date().getFullYear()} Nuvis Webbuilder. All rights reserved. Open Source under MIT.</div>
    </div>
</footer>`
    }
];

// Global runtime scripts injection for live compiled renderings (Contact, Chatbot, and FAQ mechanics)
if (typeof window !== 'undefined') {
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
        // Find associated active project metadata context on compile
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
        userDiv.className = "bg-teal-500 text-slate-950 p-2 rounded-lg self-end max-w-[85%] leading-relaxed font-bold";
        userDiv.innerText = userMsg;
        logs.appendChild(userDiv);
        logs.scrollTop = logs.scrollHeight;

        // Simulate AI Bot typing
        setTimeout(() => {
            const aiDiv = document.createElement('div');
            aiDiv.className = "bg-slate-800/80 p-2 rounded-lg self-start max-w-[85%] leading-relaxed";

            // Standard AI Knowledge template responses
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
            if (isHidden) {
                accordionContent.classList.remove('hidden');
                if (icon) icon.className = "fas fa-chevron-up text-teal-400 transition-transform";
            } else {
                accordionContent.classList.add('hidden');
                if (icon) icon.className = "fas fa-chevron-down text-slate-500 transition-transform";
            }
        }
    };
}
