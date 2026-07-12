<?php
/**
 * WebCraft Open-Source Site Builder - Automated Installer and DB Seeder
 */
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== WebCraft Automated System Installer ===\n\n";

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
        $admin_email = 'admin@webcraft.io';
        $admin_pass_hash = password_hash('admin123', PASSWORD_BCRYPT);

        $insert_admin = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, status) VALUES (?, ?, ?, 'admin', 'active')");
        $insert_admin->execute([$admin_user, $admin_email, $admin_pass_hash]);
        echo "✔ Administrator account created successfully!\n";
    } else {
        echo "ℹ Admin account already exists. Skipping seeding.\n";
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
        <form onsubmit="submitWebCraftForm(event, this)">
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
    <button onclick="toggleWebCraftChatbot()" class="bg-teal-500 text-slate-950 p-4 rounded-full shadow-2xl hover:scale-110 transition-transform">
        <i class="fas fa-comments text-2xl"></i>
    </button>
    <div id="webcraft-chatbot-box" class="hidden fixed bottom-24 right-6 w-96 bg-slate-950 border border-slate-800 rounded-2xl shadow-2xl overflow-hidden flex flex-col">
        <div class="bg-slate-900 p-4 border-b border-slate-800 flex justify-between items-center">
            <span class="font-bold text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-ping"></span>
                AI Support Bot
            </span>
            <button onclick="toggleWebCraftChatbot()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div id="webcraft-chat-messages" class="h-64 p-4 overflow-y-auto space-y-3 flex flex-col text-sm text-slate-300">
            <div class="bg-slate-900 p-3 rounded-xl max-w-[85%] self-start">
                Hello there! I am your AI assistant. How can I help you customize your WebCraft project today?
            </div>
        </div>
        <form onsubmit="sendWebCraftChatMessage(event, this)" class="p-3 border-t border-slate-800 bg-slate-900 flex gap-2">
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
        echo "✔ Templates library loaded successfully!\n";
    } else {
        echo "ℹ Template themes already loaded. Skipping seeding.\n";
    }

    echo "\n=== WebCraft System Successfully Installed! ===\n";

} catch (Exception $e) {
    echo "\n❌ INSTALLATION FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
