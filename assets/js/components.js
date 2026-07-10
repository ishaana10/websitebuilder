/**
 * WebCraft Pre-Built High-Quality UI Widgets & Components
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
    <div class="text-xl font-extrabold tracking-wider text-teal-400">WEBCRAFT</div>
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
        id: 'contact',
        name: 'Secure Contact Form',
        category: 'Forms',
        icon: 'fas fa-envelope',
        html: `
<section class="py-16 px-8 bg-slate-900 text-white rounded-lg" data-component="contact">
    <div class="max-w-md mx-auto text-center">
        <h2 class="text-3xl font-extrabold text-teal-400">Get In Touch</h2>
        <p class="text-slate-400 mt-2">Have questions? Drop us a line and we'll reply shortly.</p>
        <form class="mt-8 space-y-4" onsubmit="event.preventDefault(); alert('WebCraft Low Code Form Simulation Triggered!');">
            <input type="text" placeholder="Full Name" required class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white focus:border-teal-500 focus:outline-none text-sm" />
            <input type="email" placeholder="Email Address" required class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white focus:border-teal-500 focus:outline-none text-sm" />
            <textarea placeholder="Write message..." rows="4" required class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white focus:border-teal-500 focus:outline-none text-sm"></textarea>
            <button type="submit" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold w-full py-3 rounded-lg transition-all text-sm tracking-wide">Send Message</button>
        </form>
    </div>
</section>`
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
        <div class="text-lg font-black text-white">WEBCRAFT BUILDER</div>
        <div class="flex space-x-6 text-sm">
            <a href="#" class="hover:text-white transition">Privacy Policy</a>
            <a href="#" class="hover:text-white transition">Terms of Use</a>
            <a href="#" class="hover:text-white transition">Support</a>
        </div>
        <div class="text-xs text-slate-600">&copy; ${new Date().getFullYear()} WebCraft. All rights reserved. Open Source under MIT.</div>
    </div>
</footer>`
    }
];
