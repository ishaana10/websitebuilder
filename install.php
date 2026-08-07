<?php
/**
 * Nuvis Webbuilder Open-Source Site Builder - Automated Installer and DB Seeder
 */
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Nuvis Webbuilder Automated System Installer ===\n\n";

try {
    // 1. Establish initial DB connection using credentials in config.php
    $pdo = get_db_connection();
    echo "✔ Connected to Database Server successfully!\n";

    // 2. Load and parse schema.sql to execute queries
    $schema_file = __DIR__ . '/schema.sql';
    if (!file_exists($schema_file)) {
        throw new Exception("Schema file (schema.sql) not found in root directory.");
    }

    echo "⌛ Initializing and creating tables from schema.sql...\n";
    $schema_sql = file_get_contents($schema_file);

    // Split schema into individual queries safely
    // Note: This matches standard SQL formatting.
    $queries = preg_split("/;[\r\n]+/", $schema_sql);

    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }
    echo "✔ Database schema imported and verified successfully!\n";

    // 3. Seed initial admin user if not already present
    $stmt = $pdo->query("SELECT COUNT(*) as admin_count FROM users WHERE username = 'admin' OR role = 'admin'");
    $res = $stmt->fetch();

    if ($res['admin_count'] == 0) {
        echo "⌛ Seeding default admin credentials ('admin' / 'admin123')...\n";
        $admin_user = 'admin';
        $admin_email = 'admin@nuvis-webbuilder.io';
        $admin_pass_hash = password_hash('admin123', PASSWORD_BCRYPT);

        $insert_admin = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, status) VALUES (?, ?, ?, 'admin', 'active')");
        $insert_admin->execute([$admin_user, $admin_email, $admin_pass_hash]);
        echo "✔ Administrator account created successfully!\n";
    } else {
        echo "ℹ Admin account already exists. Skipping seeding.\n";
    }

    // 3.5 Seed email_settings table if empty
    $pdo->exec("CREATE TABLE IF NOT EXISTS `email_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `recipient_email` VARCHAR(255) NOT NULL DEFAULT 'admin@nuvis-webbuilder.io',
        `auto_responder_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `auto_responder_subject` VARCHAR(255) NOT NULL DEFAULT 'Thank you for contacting us!',
        `auto_responder_body` TEXT NOT NULL,
        `template_theme` VARCHAR(50) NOT NULL DEFAULT 'modern_minimalist'
    ) ENGINE=InnoDB;");

    // Ensure page versioning table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `project_versions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT NOT NULL,
        `label` VARCHAR(150) NOT NULL,
        `content_json` LONGTEXT NOT NULL,
        `version_type` VARCHAR(50) NOT NULL DEFAULT 'manual',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
        INDEX `idx_project_version_id` (`project_id`)
    ) ENGINE=InnoDB;");

    $stmt_email = $pdo->query("SELECT COUNT(*) as email_count FROM email_settings");
    $res_email = $stmt_email->fetch();

    if ($res_email['email_count'] == 0) {
        echo "⌛ Seeding default global email settings...\n";
        $insert_email = $pdo->prepare("INSERT INTO email_settings (recipient_email, auto_responder_enabled, auto_responder_subject, auto_responder_body, template_theme) VALUES (?, ?, ?, ?, ?)");
        $insert_email->execute([
            'admin@nuvis-webbuilder.io',
            1,
            'Thank you for contacting us!',
            "Hello!\n\nWe have received your inquiry regarding our services and will get back to you shortly.\n\nBest regards,\nThe Team",
            'modern_minimalist'
        ]);
        echo "✔ Global email settings seeded successfully!\n";
    }

    // 4. Ensure templates table has the primary default SaaS templates seeded
    $stmt_tpl = $pdo->query("SELECT COUNT(*) as tpl_count FROM templates");
    $res_tpl = $stmt_tpl->fetch();

    if ($res_tpl['tpl_count'] == 0) {
        echo "⌛ Seeding standard responsive templates...\n";

        // Seed Template 1 (SaaS Product Landing Page)
        $html_content_1 = '
<div data-component-instance="hero" class="bg-slate-900 text-white py-24 px-6 text-center border-b border-slate-800">
    <div class="max-w-4xl mx-auto">
        <span class="bg-teal-500/10 text-teal-400 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider">All-in-One Solution</span>
        <h1 class="text-5xl md:text-6xl font-extrabold tracking-tight mt-6 mb-4 leading-tight">Supercharged Commercial Solutions</h1>
        <p class="text-xl text-slate-400 mb-8 max-w-2xl mx-auto">Streamline standard production pipeline tools without custom configurations.</p>
        <div class="flex justify-center gap-4">
            <button class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold px-8 py-3 rounded-lg transition-all duration-200">Start For Free</button>
            <button class="bg-slate-800 hover:bg-slate-700 text-white font-bold px-8 py-3 rounded-lg border border-slate-700 transition-all duration-200">Learn More</button>
        </div>
    </div>
</div>
<div data-component-instance="features" class="py-20 bg-slate-950 text-slate-300 px-6">
    <div class="max-w-6xl mx-auto text-center">
        <h2 class="text-4xl font-bold text-white mb-4">Built-in Superpowers</h2>
        <p class="text-slate-400 mb-12 max-w-xl mx-auto">Engineered for scalability, enterprise controls, and robust databases.</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-slate-900 p-8 rounded-xl border border-slate-800">
                <div class="text-teal-400 text-3xl mb-4"><i class="fas fa-shield-alt"></i></div>
                <h3 class="text-xl font-bold text-white mb-2">Ironclad Security</h3>
                <p class="text-sm text-slate-400">CSRF checks, bcrypt hashing, and script parsing filters deployed globally.</p>
            </div>
            <div class="bg-slate-900 p-8 rounded-xl border border-slate-800">
                <div class="text-teal-400 text-3xl mb-4"><i class="fas fa-bolt"></i></div>
                <h3 class="text-xl font-bold text-white mb-2">Static Compilers</h3>
                <p class="text-sm text-slate-400">Delivers optimized compiled caches with sub-millisecond static loads.</p>
            </div>
            <div class="bg-slate-900 p-8 rounded-xl border border-slate-800">
                <div class="text-teal-400 text-3xl mb-4"><i class="fas fa-cubes"></i></div>
                <h3 class="text-xl font-bold text-white mb-2">Modular Blocks</h3>
                <p class="text-sm text-slate-400">Interactive widgets like chatbots and automated forms included natively.</p>
            </div>
        </div>
    </div>
</div>
<div data-component-instance="contact" class="bg-slate-900 py-16 px-6 text-slate-300">
    <div class="max-w-lg mx-auto bg-slate-950 p-8 rounded-2xl border border-slate-800">
        <h2 class="text-3xl font-extrabold text-white mb-6 text-center">Get in Touch</h2>
        <form onsubmit="submitNuvisWebbuilderForm(event, this)">
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Name</label>
                <input type="text" name="name" required class="w-full bg-slate-900 border border-slate-800 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-teal-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Email Address</label>
                <input type="email" name="email" required class="w-full bg-slate-900 border border-slate-800 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-teal-500">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-semibold mb-2">Message</label>
                <textarea name="message" rows="4" required class="w-full bg-slate-900 border border-slate-800 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-teal-500"></textarea>
            </div>
            <button type="submit" class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold py-3 rounded-lg transition-all">Send Message</button>
        </form>
    </div>
</div>
<div data-component="chatbot" class="fixed bottom-6 right-6 z-50">
    <button onclick="toggleNuvisWebbuilderChatbot()" class="bg-teal-500 text-slate-950 p-4 rounded-full shadow-2xl hover:scale-110 transition-transform">
        <i class="fas fa-comments text-2xl"></i>
    </button>
    <div id="nuvis-webbuilder-chatbot-box" class="hidden fixed bottom-24 right-6 w-96 bg-slate-950 border border-slate-800 rounded-2xl shadow-2xl overflow-hidden flex flex-col">
        <div class="bg-slate-900 p-4 border-b border-slate-800 flex justify-between items-center">
            <span class="font-bold text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-ping"></span>
                AI Support Bot
            </span>
            <button onclick="toggleNuvisWebbuilderChatbot()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div id="nuvis-webbuilder-chat-messages" class="h-64 p-4 overflow-y-auto space-y-3 flex flex-col text-sm text-slate-300">
            <div class="bg-slate-900 p-3 rounded-xl max-w-[85%] self-start">
                Hello there! I am your AI assistant. How can I help you customize your Nuvis Webbuilder project today?
            </div>
        </div>
        <form onsubmit="sendNuvisWebbuilderChatMessage(event, this)" class="p-3 border-t border-slate-800 bg-slate-900 flex gap-2">
            <input type="text" name="chat_msg" placeholder="Ask something..." class="flex-1 bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-teal-500 text-sm">
            <button type="submit" class="bg-teal-500 hover:bg-teal-400 text-slate-950 px-3 py-2 rounded-lg"><i class="fas fa-paper-plane"></i></button>
        </form>
    </div>
</div>
';

        // Seed Template 2 (Creative Agency Portfolio)
        $html_content_2 = '
<div data-component-instance="hero" class="bg-slate-950 text-white py-28 px-6 text-center">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-6xl font-black mb-6 tracking-tight bg-gradient-to-r from-teal-400 via-sky-400 to-indigo-500 bg-clip-text text-transparent">We Build Digital Masterpieces</h1>
        <p class="text-lg text-slate-400 mb-8 max-w-xl mx-auto">An award-winning agency specializing in interactive products and responsive layouts.</p>
        <button class="bg-white text-slate-950 hover:bg-slate-200 font-bold px-8 py-3.5 rounded-full transition-all duration-200">Explore Portfolio</button>
    </div>
</div>
<div data-component-instance="features" class="py-16 bg-slate-900 text-slate-300 px-6">
    <div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-12">
        <div class="flex flex-col justify-center">
            <h2 class="text-3xl font-extrabold text-white mb-4">Focused on Craft</h2>
            <p class="text-slate-400 mb-6 text-sm">Every template, pixel block, and visual dynamic script is hand-crafted for elite responsive experiences.</p>
            <div class="space-y-3">
                <div class="flex items-center gap-3"><i class="fas fa-check-circle text-teal-400"></i> High-speed load caches</div>
                <div class="flex items-center gap-3"><i class="fas fa-check-circle text-teal-400"></i> Safe SQL integration</div>
                <div class="flex items-center gap-3"><i class="fas fa-check-circle text-teal-400"></i> Customizable component nodes</div>
            </div>
        </div>
        <div class="bg-slate-950 rounded-2xl border border-slate-800 p-12 text-center flex flex-col justify-center">
            <h3 class="text-5xl font-black text-white mb-2">99.9%</h3>
            <p class="text-teal-400 uppercase tracking-widest text-xs font-bold">Uptime Security</p>
        </div>
    </div>
</div>
';

        $insert_tpl = $pdo->prepare("INSERT INTO templates (name, description, content_json) VALUES (?, ?, ?)");
        $insert_tpl->execute([
            'SaaS Product Landing Page',
            'SaaS Theme containing visual headers, responsive card groups, fully functional chatbot, and secure dynamic dynamic inquiry forms.',
            json_encode(['html' => $html_content_1])
        ]);
        $insert_tpl->execute([
            'Creative Agency Portfolio',
            'Award-winning layout configured for creative studios and graphic design professionals.',
            json_encode(['html' => $html_content_2])
        ]);
        $insert_tpl->execute([
            'E-Commerce Gadget Landing Page',
            'Optimized commercial product-led layouts featuring dynamic grids, chatbots, and bulk custom checkout form flows.',
            json_encode([
                'blocks' => [
                    ['componentId' => 'navbar', 'headingText' => 'GADGET LAB', 'paragraphText' => '', 'classes' => [], 'raw_html' => ''],
                    ['componentId' => 'hero', 'headingText' => 'Next Gen Immersive Headphones', 'paragraphText' => 'Engineered with sound precision and dynamic feedback cancellation parameters.', 'classes' => [], 'raw_html' => ''],
                    ['componentId' => 'features', 'headingText' => 'Unmatched Capabilities', 'paragraphText' => '', 'classes' => [], 'raw_html' => ''],
                    ['componentId' => 'pricing', 'headingText' => 'Explore Available Gadgets', 'paragraphText' => 'Select your gadget package below', 'classes' => [], 'raw_html' => ''],
                    ['componentId' => 'contact', 'headingText' => 'Inquire About Custom Bulk Orders', 'paragraphText' => '', 'classes' => [], 'raw_html' => ''],
                    ['componentId' => 'chatbot', 'headingText' => '', 'paragraphText' => '', 'classes' => [], 'raw_html' => ''],
                    ['componentId' => 'footer', 'headingText' => '', 'paragraphText' => '', 'classes' => [], 'raw_html' => '']
                ],
                'custom_css' => 'body { background-color: #030712 !important; }',
                'custom_js' => 'console.log("E-Commerce template script initialized");'
            ])
        ]);

        // Seed Template 4 (PestKit Pest Control Demo)
        $pest_topbar = <<<HTML
<div class="bg-emerald-950 text-slate-200 text-xs py-2 px-6 border-b border-emerald-800/40 hidden md:block" data-component="fullwidth_raw_html">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
        <div class="flex items-center gap-6">
            <span class="flex items-center gap-2 text-emerald-400">
                <i class="fas fa-map-marker-alt text-amber-400"></i>
                <span class="text-slate-300 font-medium">123 Street, NY, USA</span>
            </span>
            <span class="flex items-center gap-2 text-emerald-400">
                <i class="fas fa-phone-alt text-amber-400"></i>
                <a href="tel:+0123456789" class="text-slate-300 hover:text-emerald-300 transition font-medium">+012 3456 7890</a>
            </span>
            <span class="flex items-center gap-2 text-emerald-400">
                <i class="fas fa-envelope text-amber-400"></i>
                <a href="mailto:info@example.com" class="text-slate-300 hover:text-emerald-300 transition font-medium">info@example.com</a>
            </span>
        </div>
        <div class="flex items-center gap-4">
            <a href="#" class="text-slate-400 hover:text-amber-400 transition"><i class="fab fa-facebook-f"></i></a>
            <a href="#" class="text-slate-400 hover:text-amber-400 transition"><i class="fab fa-twitter"></i></a>
            <a href="#" class="text-slate-400 hover:text-amber-400 transition"><i class="fab fa-linkedin-in"></i></a>
            <a href="#" class="text-slate-400 hover:text-amber-400 transition"><i class="fab fa-instagram"></i></a>
        </div>
    </div>
</div>
HTML;

        $pest_navbar = <<<HTML
<nav class="bg-white/95 backdrop-blur-md text-slate-800 py-4 px-6 md:px-12 sticky top-0 shadow-sm z-50 border-b border-slate-100 flex justify-between items-center" data-component="fullwidth_raw_html">
    <div class="flex items-center gap-2">
        <div class="text-2xl font-black tracking-tight text-emerald-600 flex items-center gap-2 font-sans">
            <span class="p-1.5 bg-amber-400 rounded-lg text-emerald-950 shadow-sm"><i class="fas fa-shield-virus"></i></span>
            <span>Pest<span class="text-amber-500">Kit</span></span>
        </div>
    </div>

    <div class="hidden lg:flex items-center gap-8 font-semibold text-sm">
        <a href="#home" class="text-emerald-600 transition hover:text-emerald-500">Home</a>
        <a href="#about" class="text-slate-600 transition hover:text-emerald-600">About</a>
        <a href="#services" class="text-slate-600 transition hover:text-emerald-600">Services</a>
        <a href="#projects" class="text-slate-600 transition hover:text-emerald-600">Projects</a>
        <a href="#pricing" class="text-slate-600 transition hover:text-emerald-600">Pricing</a>
        <a href="#team" class="text-slate-600 transition hover:text-emerald-600">Team</a>
        <a href="#testimonials" class="text-slate-600 transition hover:text-emerald-600">Testimonials</a>
        <a href="#contact" class="text-slate-600 transition hover:text-emerald-600">Contact</a>
    </div>

    <div class="flex items-center gap-4">
        <a href="#contact" class="hidden sm:inline-flex bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-2.5 px-5 rounded-lg shadow-md transition duration-300 uppercase tracking-wider items-center gap-2">
            <i class="fas fa-phone-alt"></i> Get Callback
        </a>
        <button onclick="const menu = this.closest('nav').querySelector('.mobile-dropdown'); menu.classList.toggle('hidden');" class="lg:hidden p-2 text-slate-600 hover:text-emerald-600 focus:outline-none text-xl">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- Mobile Dropdown links -->
    <div class="mobile-dropdown hidden absolute top-full left-0 right-0 bg-white border-b border-slate-200 shadow-lg flex flex-col p-4 space-y-3 font-semibold text-sm lg:hidden animate-fadeIn">
        <a href="#home" class="text-emerald-600 block py-1.5 border-b border-slate-100">Home</a>
        <a href="#about" class="text-slate-600 block py-1.5 border-b border-slate-100 hover:text-emerald-600">About</a>
        <a href="#services" class="text-slate-600 block py-1.5 border-b border-slate-100 hover:text-emerald-600">Services</a>
        <a href="#projects" class="text-slate-600 block py-1.5 border-b border-slate-100 hover:text-emerald-600">Projects</a>
        <a href="#pricing" class="text-slate-600 block py-1.5 border-b border-slate-100 hover:text-emerald-600">Pricing</a>
        <a href="#team" class="text-slate-600 block py-1.5 border-b border-slate-100 hover:text-emerald-600">Team</a>
        <a href="#testimonials" class="text-slate-600 block py-1.5 border-b border-slate-100 hover:text-emerald-600">Testimonials</a>
        <a href="#contact" class="text-slate-600 block py-1.5 hover:text-emerald-600 font-semibold">Contact</a>
    </div>
</nav>
HTML;

        $pest_hero = <<<HTML
<section id="home" class="relative bg-emerald-950 py-24 md:py-36 px-6 md:px-12 text-center md:text-left overflow-hidden" data-component="fullwidth_raw_html">
    <div class="absolute inset-0 z-0 bg-cover bg-center opacity-25" style="background-image: url('https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=1600&auto=format&fit=crop&q=80');"></div>
    <div class="absolute inset-0 bg-gradient-to-r from-emerald-950 via-emerald-950/90 to-transparent z-0"></div>

    <div class="max-w-7xl mx-auto relative z-10 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
        <div class="space-y-6">
            <span class="inline-flex items-center gap-2 bg-amber-400/10 text-amber-400 border border-amber-400/30 text-xs font-bold px-4 py-1.5 rounded-full uppercase tracking-wider">
                <i class="fas fa-award"></i> No 1 Pest Control Services
            </span>
            <h1 class="text-4xl md:text-6xl font-black text-white leading-tight tracking-tight">
                Enjoy Your Home <br class="hidden md:inline" />
                <span class="text-amber-400">Totally Pest Free</span>
            </h1>
            <p class="text-slate-300 text-sm md:text-lg leading-relaxed max-w-xl">
                We protect families and businesses from destructive, unsanitary, and irritating pests through modern, eco-friendly, and completely guaranteed exterminator techniques.
            </p>
            <div class="flex flex-col sm:flex-row justify-center lg:justify-start gap-4 pt-2">
                <a href="#about" class="bg-emerald-600 hover:bg-emerald-500 text-white font-black px-8 py-3.5 rounded-lg shadow-lg hover:shadow-emerald-500/20 transition-all text-sm uppercase tracking-wider text-center">
                    Read More <i class="fas fa-arrow-right ml-2"></i>
                </a>
                <a href="#contact" class="bg-amber-400 hover:bg-amber-500 text-emerald-950 font-black px-8 py-3.5 rounded-lg shadow-lg hover:shadow-amber-400/20 transition-all text-sm uppercase tracking-wider text-center">
                    Contact Us <i class="fas fa-envelope ml-2"></i>
                </a>
            </div>
        </div>
        <div class="hidden lg:block">
            <img src="https://images.unsplash.com/photo-1628177142898-93e36e4e3a50?w=800&auto=format&fit=crop&q=80" alt="Pest Extermination Service" class="rounded-2xl shadow-2xl border-4 border-emerald-800/30 max-h-[450px] object-cover w-full transform -rotate-2 hover:rotate-0 transition duration-500" />
        </div>
    </div>
</section>
HTML;

        $pest_finder = <<<HTML
<section class="py-12 bg-white px-6 md:px-12 relative z-20 -mt-8 max-w-6xl mx-auto rounded-2xl shadow-xl border border-slate-100" data-component="fullwidth_raw_html">
    <div class="text-center mb-8">
        <span class="text-emerald-600 font-bold uppercase tracking-wider text-xs block mb-1">Instant Booking</span>
        <h2 class="text-2xl font-black text-slate-800">Find Your Pest Control Services</h2>
    </div>
    <form class="grid grid-cols-1 md:grid-cols-4 gap-4" onsubmit="event.preventDefault(); window.submitNuvisWebbuilderForm(this);">
        <div class="nuvis-webbuilder-form-status hidden col-span-1 md:col-span-4 p-3 rounded text-xs font-bold text-center"></div>

        <input type="hidden" name="name" value="Quick Finder Callback Request" />

        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1.5">Type of Service</label>
            <select name="message" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-xs text-slate-700 focus:outline-none focus:border-emerald-500 font-medium">
                <option value="Spider Eradication Inquiry">Spider Extermination</option>
                <option value="Mosquito Reduction Program">Mosquito Control</option>
                <option value="Rodent Extermination Request">Rodent & Mouse Control</option>
                <option value="Termite Inspection & Defense">Termite Treatment</option>
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1.5">Your Name</label>
            <input type="text" name="name" placeholder="John Doe" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-xs text-slate-700 focus:outline-none focus:border-emerald-500" />
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1.5">Phone/Email Address</label>
            <input type="text" name="email" placeholder="email@address.com" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-xs text-slate-700 focus:outline-none focus:border-emerald-500" />
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full bg-amber-400 hover:bg-amber-500 text-emerald-950 font-black py-3 px-6 rounded-lg text-xs transition duration-300 uppercase tracking-wider shadow-md flex items-center justify-center gap-2">
                <i class="fas fa-search"></i> Find Services
            </button>
        </div>
    </form>
</section>
HTML;

        $pest_about = <<<HTML
<section id="about" class="py-20 bg-slate-50 px-6 md:px-12" data-component="fullwidth_raw_html">
    <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
        <!-- Left Visual Image Block -->
        <div class="lg:col-span-6 relative">
            <div class="relative p-6">
                <!-- Decorative background elements -->
                <div class="absolute top-0 left-0 w-11/12 h-[35%] bg-slate-800 rounded-2xl z-0"></div>
                <div class="absolute bottom-0 left-0 w-11/12 h-[55%] bg-emerald-600 rounded-2xl z-0"></div>

                <!-- Main Image -->
                <img src="https://images.unsplash.com/photo-1621905251189-08b45d6a269e?w=800&auto=format&fit=crop&q=80" alt="Professional Cleaning" class="rounded-xl shadow-xl relative z-10 w-full h-[380px] object-cover border-4 border-white" />

                <!-- Floating Years of Experience badge -->
                <div class="absolute -top-4 right-4 bg-amber-400 text-emerald-950 p-6 rounded-xl shadow-2xl z-20 text-center w-36 border-4 border-white flex flex-col justify-center items-center">
                    <span class="text-4xl font-black block leading-none">20</span>
                    <span class="text-[9px] font-black uppercase tracking-widest mt-1">Years of Experience</span>
                </div>
            </div>
        </div>

        <!-- Right Info Block -->
        <div class="lg:col-span-6 space-y-6">
            <div class="space-y-2">
                <span class="text-emerald-600 font-extrabold uppercase tracking-wider text-xs block"><i class="fas fa-bug text-amber-500"></i> About PestKit</span>
                <h2 class="text-3xl md:text-4xl font-black text-slate-800 leading-tight">World Best Pest Control Services Since 2005</h2>
            </div>
            <p class="text-slate-600 text-sm leading-relaxed">
                PestKit is an industry leader in safe, clean, and highly secure pest eradication. We understand that your home is your sanctuary, and we deploy localized, environment-friendly baiting, structural shielding, and targeted treatments to restore complete hygiene.
            </p>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm"><i class="fas fa-home"></i></div>
                    <span class="font-bold text-slate-700 text-xs uppercase tracking-wide">Building Cleaning</span>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm"><i class="fas fa-school"></i></div>
                    <span class="font-bold text-slate-700 text-xs uppercase tracking-wide">Education Center</span>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm"><i class="fas fa-warehouse"></i></div>
                    <span class="font-bold text-slate-700 text-xs uppercase tracking-wide">Warehouse Clean</span>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm"><i class="fas fa-hospital"></i></div>
                    <span class="font-bold text-slate-700 text-xs uppercase tracking-wide">Hospital Clean</span>
                </div>
            </div>
            <div class="pt-2">
                <a href="#services" class="inline-flex bg-emerald-600 hover:bg-emerald-700 text-white font-black px-8 py-3 rounded-lg shadow-md transition-all text-xs uppercase tracking-wider gap-2 items-center">
                    Find Services <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>
HTML;

        $pest_services = <<<HTML
<section id="services" class="py-20 bg-white px-6 md:px-12" data-component="fullwidth_raw_html">
    <div class="max-w-7xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-16 space-y-2">
            <span class="text-emerald-600 font-extrabold uppercase tracking-wider text-xs block mb-1"><i class="fas fa-spider text-amber-500"></i> Our Services</span>
            <h2 class="text-3xl md:text-4xl font-black text-slate-800">Common Pest Control Services</h2>
            <p class="text-slate-500 text-xs md:text-sm">We provide targeted treatments tailored specifically for your structural infestation types.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <!-- Service Card 1 -->
            <div class="bg-slate-50 hover:bg-emerald-950 border border-slate-100 rounded-2xl p-6 text-center transition-all duration-300 hover:-translate-y-2 group shadow-sm flex flex-col justify-between">
                <div class="space-y-4">
                    <div class="w-20 h-20 rounded-full bg-slate-800 text-white group-hover:bg-amber-400 group-hover:text-emerald-950 flex items-center justify-center text-2xl mx-auto shadow-md transition duration-300">
                        <i class="fas fa-spider group-hover:rotate-45 transition duration-300"></i>
                    </div>
                    <h3 class="text-lg font-black text-slate-800 group-hover:text-white transition">Spiders Control</h3>
                    <p class="text-slate-500 group-hover:text-slate-300 text-xs leading-relaxed transition">
                        Complete elimination of poisonous, crawling, and web-building spiders from structural cavities and ceilings.
                    </p>
                </div>
                <div class="pt-4">
                    <a href="#contact" class="inline-flex bg-emerald-600 hover:bg-emerald-500 text-white group-hover:bg-amber-400 group-hover:text-emerald-950 font-extrabold px-5 py-2 rounded-lg text-xs transition duration-300 uppercase tracking-wider">
                        Learn More
                    </a>
                </div>
            </div>

            <!-- Service Card 2 -->
            <div class="bg-slate-50 hover:bg-emerald-950 border border-slate-100 rounded-2xl p-6 text-center transition-all duration-300 hover:-translate-y-2 group shadow-sm flex flex-col justify-between">
                <div class="space-y-4">
                    <div class="w-20 h-20 rounded-full bg-slate-800 text-white group-hover:bg-amber-400 group-hover:text-emerald-950 flex items-center justify-center text-2xl mx-auto shadow-md transition duration-300">
                        <i class="fas fa-mosquito group-hover:rotate-45 transition duration-300"></i>
                    </div>
                    <h3 class="text-lg font-black text-slate-800 group-hover:text-white transition">Mosquito Control</h3>
                    <p class="text-slate-500 group-hover:text-slate-300 text-xs leading-relaxed transition font-medium">
                        Effective nesting and breeding ground treatment to significantly reduce outdoor mosquito populations.
                    </p>
                </div>
                <div class="pt-4">
                    <a href="#contact" class="inline-flex bg-emerald-600 hover:bg-emerald-500 text-white group-hover:bg-amber-400 group-hover:text-emerald-950 font-extrabold px-5 py-2 rounded-lg text-xs transition duration-300 uppercase tracking-wider">
                        Learn More
                    </a>
                </div>
            </div>

            <!-- Service Card 3 -->
            <div class="bg-slate-50 hover:bg-emerald-950 border border-slate-100 rounded-2xl p-6 text-center transition-all duration-300 hover:-translate-y-2 group shadow-sm flex flex-col justify-between">
                <div class="space-y-4">
                    <div class="w-20 h-20 rounded-full bg-slate-800 text-white group-hover:bg-amber-400 group-hover:text-emerald-950 flex items-center justify-center text-2xl mx-auto shadow-md transition duration-300">
                        <i class="fas fa-mice group-hover:rotate-45 transition duration-300"></i>
                    </div>
                    <h3 class="text-lg font-black text-slate-800 group-hover:text-white transition">Rodent Extermination</h3>
                    <p class="text-slate-500 group-hover:text-slate-300 text-xs leading-relaxed transition font-medium">
                        Baiting, locking, and trapping methods designed to safely remove mice and rats from commercial properties.
                    </p>
                </div>
                <div class="pt-4">
                    <a href="#contact" class="inline-flex bg-emerald-600 hover:bg-emerald-500 text-white group-hover:bg-amber-400 group-hover:text-emerald-950 font-extrabold px-5 py-2 rounded-lg text-xs transition duration-300 uppercase tracking-wider">
                        Learn More
                    </a>
                </div>
            </div>

            <!-- Service Card 4 -->
            <div class="bg-slate-50 hover:bg-emerald-950 border border-slate-100 rounded-2xl p-6 text-center transition-all duration-300 hover:-translate-y-2 group shadow-sm flex flex-col justify-between">
                <div class="space-y-4">
                    <div class="w-20 h-20 rounded-full bg-slate-800 text-white group-hover:bg-amber-400 group-hover:text-emerald-950 flex items-center justify-center text-2xl mx-auto shadow-md transition duration-300">
                        <i class="fas fa-shield-virus group-hover:rotate-45 transition duration-300"></i>
                    </div>
                    <h3 class="text-lg font-black text-slate-800 group-hover:text-white transition">Termites Defense</h3>
                    <p class="text-slate-500 group-hover:text-slate-300 text-xs leading-relaxed transition font-medium">
                        Comprehensive sub-soil structural treatments to defend your property's wood structure against colony decay.
                    </p>
                </div>
                <div class="pt-4">
                    <a href="#contact" class="inline-flex bg-emerald-600 hover:bg-emerald-500 text-white group-hover:bg-amber-400 group-hover:text-emerald-950 font-extrabold px-5 py-2 rounded-lg text-xs transition duration-300 uppercase tracking-wider">
                        Learn More
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
HTML;

        $pest_newsletter = <<<HTML
<section class="py-16 bg-emerald-950 relative overflow-hidden px-6 md:px-12 text-center text-white" data-component="fullwidth_raw_html">
    <div class="absolute inset-0 z-0 bg-cover bg-center opacity-10" style="background-image: url('https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=1600&auto=format&fit=crop&q=80');"></div>
    <div class="max-w-4xl mx-auto relative z-10 space-y-6">
        <span class="text-amber-400 text-xs font-bold uppercase tracking-widest"><i class="fas fa-paper-plane"></i> Stay Updated</span>
        <h2 class="text-3xl md:text-4xl font-black">Sign Up To Our Newsletter To Get The Latest Offers</h2>
        <p class="text-slate-300 text-xs md:text-sm max-w-xl mx-auto">Subscribe today to receive professional pest prevention tips, seasonal checklists, and premium discount codes directly.</p>
        <form class="flex flex-col sm:flex-row gap-3 max-w-lg mx-auto mt-6" onsubmit="event.preventDefault(); alert('Subscribed to PestKit Newsletter!');">
            <input type="email" required placeholder="Enter your email address" class="flex-1 bg-white/10 border border-emerald-800 rounded-lg px-4 py-3 text-xs text-white placeholder-slate-400 focus:outline-none focus:border-amber-400 focus:bg-white/20 transition-all font-mono" />
            <button type="submit" class="bg-amber-400 hover:bg-amber-500 text-emerald-950 font-black px-8 py-3 rounded-lg text-xs uppercase tracking-wider transition duration-300 shadow-md">Subscribe</button>
        </form>
    </div>
</section>
HTML;

        $pest_pricing = <<<HTML
<section id="pricing" class="py-20 bg-slate-50 px-6 md:px-12" data-component="fullwidth_raw_html">
    <div class="max-w-7xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-16 space-y-2">
            <span class="text-emerald-600 font-extrabold uppercase tracking-wider text-xs block mb-1"><i class="fas fa-tags text-amber-500"></i> Our Pricing</span>
            <h2 class="text-3xl md:text-4xl font-black text-slate-800">Affordable Pricing Plan For Pest Control</h2>
            <p class="text-slate-500 text-xs md:text-sm">We provide standard and transparent contract rates with no hidden fees.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Basic Plan -->
            <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden hover:border-emerald-600 hover:shadow-xl transition-all duration-300 flex flex-col justify-between">
                <div class="p-8 space-y-6">
                    <div class="text-center pb-4 border-b border-slate-100">
                        <span class="text-slate-400 text-xs font-bold uppercase tracking-wider">Basic Plan</span>
                        <div class="text-4xl font-black text-slate-800 mt-2">$60<span class="text-xs font-medium text-slate-400">/mo</span></div>
                    </div>
                    <ul class="space-y-3.5 text-xs text-slate-600 font-medium">
                        <li><i class="fas fa-check text-emerald-600 mr-2"></i> Household pests Control</li>
                        <li><i class="fas fa-check text-emerald-600 mr-2"></i> Rodent Control</li>
                        <li><i class="fas fa-check text-emerald-600 mr-2"></i> Re-Service at No-Charge</li>
                        <li class="opacity-40 line-through"><i class="fas fa-times text-slate-400 mr-2"></i> Termite Control</li>
                        <li class="opacity-40 line-through"><i class="fas fa-times text-slate-400 mr-2"></i> Mosquito Reduction</li>
                    </ul>
                </div>
                <div class="p-6 bg-slate-50 border-t border-slate-100 text-center">
                    <a href="#contact" class="block w-full bg-emerald-600 hover:bg-emerald-700 text-white font-black py-3 rounded-lg text-xs uppercase tracking-wider transition shadow-sm font-semibold font-semibold">Get Started</a>
                </div>
            </div>

            <!-- Standard Plan (Featured) -->
            <div class="bg-white border-2 border-emerald-600 rounded-2xl overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 relative flex flex-col justify-between">
                <div class="absolute -top-1.5 right-6 bg-amber-400 text-emerald-950 font-black text-[9px] uppercase tracking-widest px-3 py-1 rounded-b shadow-sm">Popular</div>
                <div class="p-8 space-y-6">
                    <div class="text-center pb-4 border-b border-slate-100">
                        <span class="text-emerald-600 text-xs font-bold uppercase tracking-wider">Standard Plan</span>
                        <div class="text-4xl font-black text-slate-800 mt-2">$80<span class="text-xs font-medium text-slate-400">/mo</span></div>
                    </div>
                    <ul class="space-y-3.5 text-xs text-slate-600 font-medium">
                        <li><i class="fas fa-check text-emerald-600 mr-2"></i> Household pests Control</li>
                        <li><i class="fas fa-check text-emerald-600 mr-2"></i> Rodent Control</li>
                        <li><i class="fas fa-check text-emerald-600 mr-2"></i> Re-Service at No-Charge</li>
                        <li><i class="fas fa-check text-emerald-600 mr-2"></i> Termite Control</li>
                        <li class="opacity-40 line-through"><i class="fas fa-times text-slate-400 mr-2"></i> Mosquito Reduction</li>
                    </ul>
                </div>
                <div class="p-6 bg-emerald-50 border-t border-slate-100 text-center">
                    <a href="#contact" class="block w-full bg-emerald-600 hover:bg-emerald-700 text-white font-black py-3 rounded-lg text-xs uppercase tracking-wider transition shadow-md shadow-emerald-600/10 font-semibold font-semibold">Get Started</a>
                </div>
            </div>

            <!-- Premium Plan -->
            <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden hover:border-emerald-600 hover:shadow-xl transition-all duration-300 flex flex-col justify-between">
                <div class="p-8 space-y-6">
                    <div class="text-center pb-4 border-b border-slate-100">
                        <span class="text-slate-400 text-xs font-bold uppercase tracking-wider">Premium Plan</span>
                        <div class="text-4xl font-black text-slate-800 mt-2">$120<span class="text-xs font-medium text-slate-400">/mo</span></div>
                    </div>
                    <ul class="space-y-3.5 text-xs text-slate-600 font-medium">
                        <li><i class="fas fa-check text-emerald-600 mr-2"></i> Household pests Control</li>
                        <li><i class="fas fa-check text-emerald-600 mr-2"></i> Rodent Control</li>
                        <li><i class="fas fa-check text-emerald-600 mr-2"></i> Re-Service at No-Charge</li>
                        <li><i class="fas fa-check text-emerald-600 mr-2"></i> Termite Control</li>
                        <li><i class="fas fa-check text-emerald-600 mr-2"></i> Mosquito Reduction</li>
                    </ul>
                </div>
                <div class="p-6 bg-slate-50 border-t border-slate-100 text-center">
                    <a href="#contact" class="block w-full bg-emerald-600 hover:bg-emerald-700 text-white font-black py-3 rounded-lg text-xs uppercase tracking-wider transition shadow-sm font-semibold font-semibold font-semibold font-semibold">Get Started</a>
                </div>
            </div>
        </div>
    </div>
</section>
HTML;

        $pest_projects = <<<HTML
<section id="projects" class="py-20 bg-white px-6 md:px-12" data-component="fullwidth_raw_html">
    <div class="max-w-7xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-16 space-y-2">
            <span class="text-emerald-600 font-extrabold uppercase tracking-wider text-xs block mb-1"><i class="fas fa-check-double text-amber-500"></i> Our Projects</span>
            <h2 class="text-3xl md:text-4xl font-black text-slate-800">Our Recently Completed Projects</h2>
            <p class="text-slate-500 text-xs md:text-sm">Explore our visual archive of successful residential and commercial cleanings.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Project 1 -->
            <div class="project-item relative group overflow-hidden rounded-2xl border border-slate-100 shadow-sm" style="padding: 0;">
                <img src="https://images.unsplash.com/photo-1603796846097-bee99e4a60c9?w=600&auto=format&fit=crop&q=80" alt="Home Sanitizing" class="w-full h-64 object-cover group-hover:scale-105 transition duration-500" />
                <div class="absolute inset-0 bg-gradient-to-t from-emerald-950/90 via-emerald-950/40 to-transparent flex flex-col justify-end p-6">
                    <span class="text-amber-400 text-[10px] font-black uppercase tracking-wider">Sanitization</span>
                    <h4 class="text-white text-lg font-black mt-1 font-semibold">Whole Home Sanitizing</h4>
                </div>
            </div>

            <!-- Project 2 -->
            <div class="project-item relative group overflow-hidden rounded-2xl border border-slate-100 shadow-sm" style="padding: 0;">
                <img src="https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=600&auto=format&fit=crop&q=80" alt="Education Center" class="w-full h-64 object-cover group-hover:scale-105 transition duration-500" />
                <div class="absolute inset-0 bg-gradient-to-t from-emerald-950/90 via-emerald-950/40 to-transparent flex flex-col justify-end p-6">
                    <span class="text-amber-400 text-[10px] font-black uppercase tracking-wider font-semibold">School Cleaning</span>
                    <h4 class="text-white text-lg font-black mt-1 font-semibold">Education Center Cleaning</h4>
                </div>
            </div>

            <!-- Project 3 -->
            <div class="project-item relative group overflow-hidden rounded-2xl border border-slate-100 shadow-sm" style="padding: 0;">
                <img src="https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?w=600&auto=format&fit=crop&q=80" alt="Warehouse Cleaning" class="w-full h-64 object-cover group-hover:scale-105 transition duration-500" />
                <div class="absolute inset-0 bg-gradient-to-t from-emerald-950/90 via-emerald-950/40 to-transparent flex flex-col justify-end p-6">
                    <span class="text-amber-400 text-[10px] font-black uppercase tracking-wider font-semibold">Industrial</span>
                    <h4 class="text-white text-lg font-black mt-1 font-semibold font-semibold">Warehouse Cleaning</h4>
                </div>
            </div>

            <!-- Project 4 -->
            <div class="project-item relative group overflow-hidden rounded-2xl border border-slate-100 shadow-sm" style="padding: 0;">
                <img src="https://images.unsplash.com/photo-1516549655169-df83a0774514?w=600&auto=format&fit=crop&q=80" alt="Hospital Clean" class="w-full h-64 object-cover group-hover:scale-105 transition duration-500" />
                <div class="absolute inset-0 bg-gradient-to-t from-emerald-950/90 via-emerald-950/40 to-transparent flex flex-col justify-end p-6">
                    <span class="text-amber-400 text-[10px] font-black uppercase tracking-wider font-semibold">Medical Care</span>
                    <h4 class="text-white text-lg font-black mt-1 font-semibold font-semibold">Hospital Cleaning</h4>
                </div>
            </div>

            <!-- Project 5 -->
            <div class="project-item relative group overflow-hidden rounded-2xl border border-slate-100 shadow-sm" style="padding: 0;">
                <img src="https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=600&auto=format&fit=crop&q=80" alt="Factory Clean" class="w-full h-64 object-cover group-hover:scale-105 transition duration-500" />
                <div class="absolute inset-0 bg-gradient-to-t from-emerald-950/90 via-emerald-950/40 to-transparent flex flex-col justify-end p-6">
                    <span class="text-amber-400 text-[10px] font-black uppercase tracking-wider font-semibold">Manufacturing</span>
                    <h4 class="text-white text-lg font-black mt-1 font-semibold font-semibold">Factory Cleaning</h4>
                </div>
            </div>

            <!-- Project 6 -->
            <div class="project-item relative group overflow-hidden rounded-2xl border border-slate-100 shadow-sm" style="padding: 0;">
                <img src="https://images.unsplash.com/photo-1540518614846-7eded433c457?w=600&auto=format&fit=crop&q=80" alt="Furniture Sanitizing" class="w-full h-64 object-cover group-hover:scale-105 transition duration-500" />
                <div class="absolute inset-0 bg-gradient-to-t from-emerald-950/90 via-emerald-950/40 to-transparent flex flex-col justify-end p-6">
                    <span class="text-amber-400 text-[10px] font-black uppercase tracking-wider font-semibold">Furniture Sanitization</span>
                    <h4 class="text-white text-lg font-black mt-1 font-semibold font-semibold font-semibold">Furniture Sanitizing</h4>
                </div>
            </div>
        </div>
    </div>
</section>
HTML;

        $pest_team = <<<HTML
<section id="team" class="py-20 bg-slate-50 px-6 md:px-12" data-component="fullwidth_raw_html">
    <div class="max-w-7xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-16 space-y-2">
            <span class="text-emerald-600 font-extrabold uppercase tracking-wider text-xs block mb-1"><i class="fas fa-users-cog text-amber-500"></i> Our Team</span>
            <h2 class="text-3xl md:text-4xl font-black text-slate-800">Our Team Members</h2>
            <p class="text-slate-500 text-xs md:text-sm">Our team members are experienced, certified, and background-checked specialists.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- Team Member 1 -->
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden text-center hover:shadow-xl transition-all duration-300">
                <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300&auto=format&fit=crop&q=80" alt="Full Name" class="w-full h-64 object-cover" />
                <div class="p-6 bg-slate-900 text-white">
                    <h4 class="text-base font-black text-amber-400 font-semibold">Dr. Alexandra Vance</h4>
                    <p class="text-xs text-slate-400 mt-1 uppercase tracking-wider font-semibold">Chief Entomologist</p>
                </div>
            </div>

            <!-- Team Member 2 -->
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden text-center hover:shadow-xl transition-all duration-300">
                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=300&auto=format&fit=crop&q=80" alt="Full Name" class="w-full h-64 object-cover" />
                <div class="p-6 bg-slate-900 text-white">
                    <h4 class="text-base font-black text-amber-400 font-semibold">Marcus Sterling</h4>
                    <p class="text-xs text-slate-400 mt-1 uppercase tracking-wider font-semibold">Director of Extermination</p>
                </div>
            </div>

            <!-- Team Member 3 -->
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden text-center hover:shadow-xl transition-all duration-300">
                <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=300&auto=format&fit=crop&q=80" alt="Full Name" class="w-full h-64 object-cover" />
                <div class="p-6 bg-slate-900 text-white">
                    <h4 class="text-base font-black text-amber-400 font-semibold font-semibold">Sonia Kova</h4>
                    <p class="text-xs text-slate-400 mt-1 uppercase tracking-wider font-semibold">Senior Bedbug Specialist</p>
                </div>
            </div>

            <!-- Team Member 4 -->
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden text-center hover:shadow-xl transition-all duration-300">
                <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=300&auto=format&fit=crop&q=80" alt="Full Name" class="w-full h-64 object-cover" />
                <div class="p-6 bg-slate-900 text-white">
                    <h4 class="text-base font-black text-amber-400 font-semibold font-semibold">Robert Chen</h4>
                    <p class="text-xs text-slate-400 mt-1 uppercase tracking-wider font-semibold">Termite Barrier Architect</p>
                </div>
            </div>
        </div>
    </div>
</section>
HTML;

        $pest_testimonials = <<<HTML
<section id="testimonials" class="py-20 bg-white px-6 md:px-12" data-component="fullwidth_raw_html">
    <div class="max-w-7xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-16 space-y-2">
            <span class="text-emerald-600 font-extrabold uppercase tracking-wider text-xs block mb-1"><i class="fas fa-quote-left text-amber-500"></i> Testimonials</span>
            <h2 class="text-3xl md:text-4xl font-black text-slate-800">What Clients Say About Our Services</h2>
            <p class="text-slate-500 text-xs md:text-sm">We take pride in absolute customer satisfaction. Read reviews from real property owners.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Review 1 -->
            <div class="bg-slate-50 border border-slate-100 rounded-2xl p-6 relative shadow-sm flex flex-col justify-between">
                <p class="text-slate-600 text-xs italic leading-relaxed">
                    "PestKit completely cleared our warehouse of rodents inside 48 hours. Excellent, fast service and they explained exactly what safe baits they used."
                </p>
                <div class="flex items-center gap-3.5 mt-6 pt-4 border-t border-slate-200/60">
                    <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&auto=format&fit=crop&q=80" alt="Client 1" class="w-10 h-10 rounded-full object-cover" />
                    <div>
                        <h4 class="font-black text-slate-800 text-xs font-semibold font-semibold">James Wilson</h4>
                        <span class="text-[10px] text-slate-400">Operations Manager</span>
                    </div>
                </div>
            </div>

            <!-- Review 2 -->
            <div class="bg-slate-50 border border-slate-100 rounded-2xl p-6 relative shadow-sm flex flex-col justify-between">
                <p class="text-slate-600 text-xs italic leading-relaxed">
                    "The termite inspection was extremely thorough. Dr Alexandra explained the subsoil barrier system, and it has kept our building decays safe since."
                </p>
                <div class="flex items-center gap-3.5 mt-6 pt-4 border-t border-slate-200/60">
                    <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=100&auto=format&fit=crop&q=80" alt="Client 2" class="w-10 h-10 rounded-full object-cover" />
                    <div>
                        <h4 class="font-black text-slate-800 text-xs font-semibold font-semibold">Sarah Jenkins</h4>
                        <span class="text-[10px] text-slate-400">Home Owner</span>
                    </div>
                </div>
            </div>

            <!-- Review 3 -->
            <div class="bg-slate-50 border border-slate-100 rounded-2xl p-6 relative shadow-sm flex flex-col justify-between">
                <p class="text-slate-600 text-xs italic leading-relaxed">
                    "Very friendly team and eco-friendly products! Our yard had heavy mosquito nesting and after the treatment we enjoyed outdoor summer hosting entirely bite-free."
                </p>
                <div class="flex items-center gap-3.5 mt-6 pt-4 border-t border-slate-200/60">
                    <img src="https://images.unsplash.com/photo-1517841905240-472988babdf9?w=100&auto=format&fit=crop&q=80" alt="Client 3" class="w-10 h-10 rounded-full object-cover" />
                    <div>
                        <h4 class="font-black text-slate-800 text-xs font-semibold font-semibold">Sonia Carter</h4>
                        <span class="text-[10px] text-slate-400">Restaurateur</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
HTML;

        $pest_contact = <<<HTML
<section id="contact" class="py-20 bg-slate-50 px-6 md:px-12" data-component="fullwidth_raw_html">
    <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
        <!-- Left: Map / Details -->
        <div class="lg:col-span-6 space-y-6">
            <div class="space-y-2">
                <span class="text-emerald-600 font-extrabold uppercase tracking-wider text-xs block mb-1"><i class="fas fa-map-marked-alt text-amber-500"></i> Get In Touch</span>
                <h2 class="text-3xl md:text-4xl font-black text-slate-800">Contact For Any Query</h2>
                <p class="text-slate-500 text-xs md:text-sm">Reach out to PestKit. We are available 24/7 for emergency structural traps.</p>
            </div>

            <!-- Map Embed -->
            <div class="rounded-2xl overflow-hidden shadow-md border border-slate-200">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3001583.639214438!2d-78.4099249913019!3d42.71993723844549!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x4ccc4bf0f123a5a9%3A0xddcfc6c1de189567!2sNew%20York%2C%20USA!5e0!3m2!1sen!2sbd!4v1687175686342!5m2!1sen!2sbd" class="w-full h-56 border-0" allowfullscreen="" loading="lazy"></iframe>
            </div>

            <!-- Small Contacts Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-4 rounded-xl border border-slate-200 flex flex-col items-center text-center shadow-sm">
                    <i class="fas fa-map-marker-alt text-emerald-600 text-lg mb-2"></i>
                    <span class="font-black text-slate-800 text-[10px] uppercase">Address</span>
                    <p class="text-[11px] text-slate-500 mt-1">123 Street, NY, USA</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-slate-200 flex flex-col items-center text-center shadow-sm">
                    <i class="fas fa-phone-alt text-emerald-600 text-lg mb-2"></i>
                    <span class="font-black text-slate-800 text-[10px] uppercase">Call Us</span>
                    <p class="text-[11px] text-slate-500 mt-1">+012 3456 7890</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-slate-200 flex flex-col items-center text-center shadow-sm">
                    <i class="fas fa-envelope text-emerald-600 text-lg mb-2"></i>
                    <span class="font-black text-slate-800 text-[10px] uppercase">Email Us</span>
                    <p class="text-[11px] text-slate-500 mt-1">info@example.com</p>
                </div>
            </div>
        </div>

        <!-- Right: Submit Form -->
        <div class="lg:col-span-6 bg-white p-8 rounded-2xl shadow-xl border border-slate-100">
            <h3 class="text-xl font-black text-slate-850 mb-6 flex items-center gap-2"><span class="w-2.5 h-2.5 bg-amber-400 rounded-full"></span> Request a Safe Inspection</h3>
            <form onsubmit="event.preventDefault(); window.submitNuvisWebbuilderForm(this);" class="space-y-4">
                <div class="nuvis-webbuilder-form-status hidden p-3 rounded text-xs font-bold text-center"></div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Your Full Name</label>
                    <input type="text" name="name" required placeholder="John Doe" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-xs text-slate-700 focus:outline-none focus:border-emerald-500" />
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Your Email Address</label>
                    <input type="email" name="email" required placeholder="email@address.com" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-xs text-slate-700 focus:outline-none focus:border-emerald-500 font-mono" />
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Detailed Message</label>
                    <textarea name="message" required rows="4" placeholder="Briefly describe the bug or pest concern..." class="w-full bg-slate-50 border border-slate-200 rounded-lg p-4 text-xs text-slate-700 focus:outline-none focus:border-emerald-500"></textarea>
                </div>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-black py-3 px-6 rounded-lg text-xs uppercase tracking-wider transition duration-300 shadow-md flex items-center justify-center gap-2">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </form>
        </div>
    </div>
</section>
HTML;

        $pest_footer = <<<HTML
<footer class="bg-slate-900 text-slate-300 pt-16 pb-8 px-6 md:px-12 relative overflow-hidden" data-component="fullwidth_raw_html">
    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-8 pb-12 border-b border-slate-800">
        <!-- Brand Info -->
        <div class="space-y-4">
            <h3 class="text-xl font-black text-white flex items-center gap-2">
                <span class="p-1 bg-amber-400 rounded text-emerald-950 text-xs"><i class="fas fa-shield-virus"></i></span>
                <span>Pest<span class="text-amber-500">Kit</span></span>
            </h3>
            <p class="text-slate-400 text-xs leading-relaxed">
                Nostrud exertation ullamco labor nisi aliquip ex ea commodo consequat duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore.
            </p>
        </div>

        <!-- Useful Links -->
        <div class="space-y-4">
            <h4 class="text-white font-bold text-xs uppercase tracking-widest border-l-2 border-emerald-500 pl-3">Useful Links</h4>
            <div class="flex flex-col space-y-2 text-xs text-slate-400 font-medium font-semibold">
                <a href="#about" class="hover:text-amber-400 transition">About Us</a>
                <a href="#contact" class="hover:text-amber-400 transition">Contact Us</a>
                <a href="#services" class="hover:text-amber-400 transition">Our Services</a>
                <a href="#pricing" class="hover:text-amber-400 transition">Terms & Condition</a>
            </div>
        </div>

        <!-- Services Link -->
        <div class="space-y-4">
            <h4 class="text-white font-bold text-xs uppercase tracking-widest border-l-2 border-emerald-500 pl-3">Services Link</h4>
            <div class="flex flex-col space-y-2 text-xs text-slate-400 font-medium font-semibold">
                <a href="#services" class="hover:text-amber-400 transition">Apartment Cleaning</a>
                <a href="#services" class="hover:text-amber-400 transition">Office Cleaning</a>
                <a href="#services" class="hover:text-amber-400 transition">Car Washing</a>
                <a href="#services" class="hover:text-amber-400 transition">Green Cleaning</a>
            </div>
        </div>

        <!-- Contact Us Info -->
        <div class="space-y-4">
            <h4 class="text-white font-bold text-xs uppercase tracking-widest border-l-2 border-emerald-500 pl-3">Contact Us</h4>
            <div class="flex flex-col space-y-3 text-xs text-slate-400">
                <span class="flex items-center gap-2"><i class="fas fa-map-marker-alt text-amber-400"></i> 123 Street, CA, USA</span>
                <span class="flex items-center gap-2"><i class="fas fa-phone-alt text-amber-400"></i> +012 345 67890</span>
                <span class="flex items-center gap-2"><i class="fas fa-envelope text-amber-400"></i> info@example.com</span>
            </div>
        </div>
    </div>

    <!-- Copyright banner -->
    <div class="max-w-7xl mx-auto pt-8 flex flex-col md:flex-row justify-between items-center text-xs text-slate-500 gap-4">
        <span>PestKit © 2025 All Right Reserved.</span>
        <div class="flex items-center gap-4">
            <span>Designed By <a href="https://htmlcodex.com/" target="_blank" class="hover:text-white underline font-semibold">HTML Codex</a></span>
            <span>Distributed By <a href="https://themewagon.com/" target="_blank" class="hover:text-white underline font-semibold font-semibold">ThemeWagon</a></span>
        </div>
    </div>
</footer>
HTML;

        $pest_layout_json = json_encode([
            'blocks' => [
                ['componentId' => 'fullwidth_raw_html', 'headingText' => '', 'paragraphText' => '', 'classes' => [], 'raw_html' => $pest_topbar],
                ['componentId' => 'fullwidth_raw_html', 'headingText' => '', 'paragraphText' => '', 'classes' => [], 'raw_html' => $pest_navbar],
                ['componentId' => 'fullwidth_raw_html', 'headingText' => '', 'paragraphText' => '', 'classes' => [], 'raw_html' => $pest_hero],
                ['componentId' => 'fullwidth_raw_html', 'headingText' => '', 'paragraphText' => '', 'classes' => [], 'raw_html' => $pest_finder],
                ['componentId' => 'fullwidth_raw_html', 'headingText' => '', 'paragraphText' => '', 'classes' => [], 'raw_html' => $pest_about],
                ['componentId' => 'fullwidth_raw_html', 'headingText' => '', 'paragraphText' => '', 'classes' => [], 'raw_html' => $pest_services],
                ['componentId' => 'fullwidth_raw_html', 'headingText' => '', 'paragraphText' => '', 'classes' => [], 'raw_html' => $pest_newsletter],
                ['componentId' => 'fullwidth_raw_html', 'headingText' => '', 'paragraphText' => '', 'classes' => [], 'raw_html' => $pest_pricing],
                ['componentId' => 'fullwidth_raw_html', 'headingText' => '', 'paragraphText' => '', 'classes' => [], 'raw_html' => $pest_projects],
                ['componentId' => 'fullwidth_raw_html', 'headingText' => '', 'paragraphText' => '', 'classes' => [], 'raw_html' => $pest_team],
                ['componentId' => 'fullwidth_raw_html', 'headingText' => '', 'paragraphText' => '', 'classes' => [], 'raw_html' => $pest_testimonials],
                ['componentId' => 'fullwidth_raw_html', 'headingText' => '', 'paragraphText' => '', 'classes' => [], 'raw_html' => $pest_contact],
                ['componentId' => 'fullwidth_raw_html', 'headingText' => '', 'paragraphText' => '', 'classes' => [], 'raw_html' => $pest_footer]
            ],
            'custom_css' => '@keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } } .animate-fadeIn { animation: fadeIn 0.3s ease-out forwards; }',
            'custom_js' => 'console.log("PestKit Pest Control Demo initialized successfully!");'
        ]);

        $insert_tpl->execute([
            'PestKit Pest Control Demo',
            'Authentic, high-fidelity responsive clone of the PestKit website builder demo featuring custom topbars, services grids, pricing cards, completed project galleries, and contact callback forms.',
            $pest_layout_json
        ]);

        echo "✔ Templates library loaded successfully!\n";
    } else {
        echo "ℹ Template themes already loaded. Skipping seeding.\n";
    }

    echo "\n=== Nuvis Webbuilder System Successfully Installed! ===\n";

} catch (Exception $e) {
    echo "\n❌ INSTALLATION FAILED!\n";
    $error_msg = $e->getMessage();
    echo "Error: " . $error_msg . "\n\n";

    // Detect 1044 / Access denied / site_builder defaults
    if (stripos($error_msg, '1044') !== false || stripos($error_msg, 'site_builder') !== false || stripos($error_msg, 'Access denied') !== false) {
        echo "💡 DIAGNOSTIC SUGGESTION & HOW TO FIX:\n";
        echo "---------------------------------------\n";
        echo "It looks like a database connection or privilege error has occurred.\n";
        echo "On shared hosting environments (such as cPanel), you cannot use the default database name 'site_builder'.\n";
        echo "Database names and users must be prefixed with your hosting username (e.g., 'ictfjcom_site_builder').\n\n";
        echo "Please follow these steps to resolve this:\n";
        echo "1. Log into your hosting control panel (cPanel) and create a MySQL database (e.g., 'ictfjcom_site_builder').\n";
        echo "2. Create a MySQL user (e.g., 'ictfjcom_webdev') and assign it to the database with ALL PRIVILEGES.\n";
        echo "3. Open the '.env' file in your web builder root directory.\n";
        echo "   (If '.env' does not exist, copy '.env.example' to '.env')\n";
        echo "4. Update the '.env' file with your correct database details:\n";
        echo "   DB_NAME=yourprefix_yourdbname\n";
        echo "   DB_USER=yourprefix_yourdbuser\n";
        echo "   DB_PASS=yourpassword\n\n";
        echo "Once configured, re-run this installer page to complete the setup successfully!\n";
    }
    exit(1);
}
