<?php
/**
 * WebCraft Render Engine v2.0
 * Compiles page JSON schema (blocks[]) -> HTML at request time.
 * No longer depends on a pre-compiled published_html column.
 *
 * Safe to require_once from api.php — page-output code only runs
 * when this file is the entry-point script (direct browser request).
 */
require_once __DIR__ . '/config.php';

// ── Block renderers (safe to include anywhere) ─────────────────

function p(array $props, string $key, string $default = ''): string {
    return htmlspecialchars($props[$key] ?? $default, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function render_links(array $links, string $cls): string {
    return implode('', array_map(fn($l) =>
        '<a href="' . htmlspecialchars($l['href'] ?? '#', ENT_QUOTES) . '" class="' . $cls . '">' .
        htmlspecialchars($l['label'] ?? '', ENT_QUOTES) . '</a>',
        $links
    ));
}

function render_block(array $block): string {
    $type  = $block['type'] ?? '';
    $props = $block['props'] ?? [];

    return match ($type) {
        'navbar'         => render_navbar($props),
        'footer'         => render_footer($props),
        'hero'           => render_hero($props),
        'text_block'     => render_text_block($props),
        'image_block'    => render_image_block($props),
        'features_grid'  => render_features_grid($props),
        'cta_banner'     => render_cta_banner($props),
        'pricing_cards'  => render_pricing_cards($props),
        'testimonials'   => render_testimonials($props),
        'contact_form'   => render_contact_form($props),
        'spacer'         => render_spacer($props),
        'html_block'     => $props['html'] ?? '',
        default          => "<!-- unknown block: " . htmlspecialchars($type, ENT_QUOTES) . " -->"
    };
}

function render_navbar(array $props): string {
    $links = render_links($props['links'] ?? [], 'text-slate-300 hover:text-white text-sm font-medium transition');
    $logo  = !empty($props['logo_url']) ? '<img src="' . p($props,'logo_url') . '" class="h-8 w-auto">' : '';
    return '<nav class="' . p($props,'bg','bg-slate-900') . ' px-6 py-4 flex items-center justify-between">
        <a href="#" class="font-black text-white text-lg flex items-center gap-2">' . $logo . p($props,'brand','Brand') . '</a>
        <div class="hidden md:flex gap-6">' . $links . '</div>
    </nav>';
}

function render_footer(array $props): string {
    $links = render_links($props['links'] ?? [], 'text-slate-400 hover:text-white text-sm transition');
    $logo  = !empty($props['logo_url']) ? '<img src="' . p($props,'logo_url') . '" class="h-7">' : '';
    return '<footer class="bg-slate-950 border-t border-slate-800 py-10 px-6">
        <div class="max-w-5xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">' . $logo . '<span class="font-black text-white">' . p($props,'brand') . '</span></div>
            <div class="flex gap-5">' . $links . '</div>
            <p class="text-slate-500 text-xs">' . p($props,'copyright') . '</p>
        </div>
    </footer>';
}

function render_hero(array $props): string {
    $btn = !empty($props['button_text'])
        ? '<a href="' . p($props,'button_href','#') . '" class="inline-block mt-8 bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-8 py-3 rounded-lg text-sm transition">' . p($props,'button_text') . '</a>'
        : '';
    return '<section class="' . p($props,'bg','bg-slate-900') . ' ' . p($props,'padding','py-20') . ' ' . p($props,'align','text-center') . ' px-6">
        <div class="max-w-3xl mx-auto">
            <h1 class="text-4xl font-black text-white leading-tight">' . p($props,'heading') . '</h1>
            <p class="text-slate-300 mt-4 text-lg leading-relaxed">' . p($props,'subheading') . '</p>' . $btn . '
        </div>
    </section>';
}

function render_text_block(array $props): string {
    return '<section class="' . p($props,'bg','bg-slate-900') . ' ' . p($props,'padding','py-12') . ' ' . p($props,'align','text-left') . ' px-6">
        <div class="max-w-3xl mx-auto">
            <h2 class="text-2xl font-black text-white">' . p($props,'heading') . '</h2>
            <p class="text-slate-300 mt-3 leading-relaxed">' . p($props,'body') . '</p>
        </div>
    </section>';
}

function render_image_block(array $props): string {
    $caption = !empty($props['caption']) ? '<p class="text-slate-400 text-sm mt-3">' . p($props,'caption') . '</p>' : '';
    return '<section class="bg-slate-900 ' . p($props,'padding','py-8') . ' px-6 text-center">
        <div class="max-w-4xl mx-auto">
            <img src="' . p($props,'src') . '" alt="' . p($props,'alt') . '" class="w-full rounded-xl shadow-xl">' . $caption . '
        </div>
    </section>';
}

function render_features_grid(array $props): string {
    $items = '';
    foreach ($props['features'] ?? [] as $f) {
        $items .= '<div class="bg-slate-800 rounded-xl p-6 border border-slate-700">
            <i class="' . htmlspecialchars($f['icon'] ?? '', ENT_QUOTES) . ' text-teal-400 text-2xl mb-4"></i>
            <h3 class="font-black text-white text-lg">' . htmlspecialchars($f['title'] ?? '', ENT_QUOTES) . '</h3>
            <p class="text-slate-400 text-sm mt-2 leading-relaxed">' . htmlspecialchars($f['desc'] ?? '', ENT_QUOTES) . '</p>
        </div>';
    }
    return '<section class="' . p($props,'bg','bg-slate-900') . ' ' . p($props,'padding','py-16') . ' px-6">
        <div class="max-w-5xl mx-auto">
            <h2 class="text-3xl font-black text-white text-center mb-10">' . p($props,'heading') . '</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">' . $items . '</div>
        </div>
    </section>';
}

function render_cta_banner(array $props): string {
    return '<section class="' . p($props,'bg','bg-teal-900') . ' py-16 px-6 text-center">
        <h2 class="text-3xl font-black text-white">' . p($props,'heading') . '</h2>
        <p class="text-teal-100 mt-3">' . p($props,'subtext') . '</p>
        <a href="' . p($props,'button_href','#') . '" class="inline-block mt-6 bg-white text-teal-900 font-black px-8 py-3 rounded-lg text-sm hover:bg-teal-50 transition">' . p($props,'button_text') . '</a>
    </section>';
}

function render_pricing_cards(array $props): string {
    $cards = '';
    foreach ($props['plans'] ?? [] as $plan) {
        $feats = implode('', array_map(fn($f) =>
            '<li class="flex items-center gap-2 text-slate-300 text-sm"><i class="fas fa-check text-teal-400 text-xs"></i>' . htmlspecialchars($f, ENT_QUOTES) . '</li>',
            $plan['features'] ?? []
        ));
        $ring    = !empty($plan['highlight']) ? 'border-teal-500 shadow-xl shadow-teal-500/10' : 'border-slate-700';
        $btnCls  = !empty($plan['highlight']) ? 'bg-teal-500 hover:bg-teal-400 text-slate-950' : 'bg-slate-700 hover:bg-slate-600 text-white';
        $popular = !empty($plan['highlight']) ? '<span class="text-xs font-black text-teal-400 uppercase tracking-widest mb-2 block">Most Popular</span>' : '';
        $cards  .= '<div class="bg-slate-800 rounded-2xl p-8 border ' . $ring . ' flex flex-col">' . $popular .
            '<h3 class="text-xl font-black text-white">' . htmlspecialchars($plan['name'] ?? '', ENT_QUOTES) . '</h3>' .
            '<p class="text-3xl font-black text-white mt-2">' . htmlspecialchars($plan['price'] ?? '', ENT_QUOTES) . '</p>' .
            '<p class="text-slate-400 text-sm mt-1 mb-6">' . htmlspecialchars($plan['desc'] ?? '', ENT_QUOTES) . '</p>' .
            '<ul class="space-y-2 mb-8 flex-1">' . $feats . '</ul>' .
            '<a href="' . htmlspecialchars($plan['href'] ?? '#', ENT_QUOTES) . '" class="' . $btnCls . ' font-bold py-2.5 rounded-lg text-sm text-center transition">' . htmlspecialchars($plan['cta'] ?? '', ENT_QUOTES) . '</a>' .
        '</div>';
    }
    return '<section class="' . p($props,'bg','bg-slate-950') . ' ' . p($props,'padding','py-16') . ' px-6">
        <div class="max-w-5xl mx-auto">
            <h2 class="text-3xl font-black text-white text-center mb-10">' . p($props,'heading') . '</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">' . $cards . '</div>
        </div>
    </section>';
}

function render_testimonials(array $props): string {
    $cards = '';
    foreach ($props['items'] ?? [] as $t) {
        $cards .= '<div class="bg-slate-800 rounded-xl p-6 border border-slate-700">
            <i class="fas fa-quote-left text-teal-400 text-lg mb-3"></i>
            <p class="text-slate-300 text-sm leading-relaxed italic">' . htmlspecialchars($t['quote'] ?? '', ENT_QUOTES) . '</p>
            <div class="mt-4">
                <p class="text-white font-bold text-sm">' . htmlspecialchars($t['author'] ?? '', ENT_QUOTES) . '</p>
                <p class="text-slate-500 text-xs">' . htmlspecialchars($t['role'] ?? '', ENT_QUOTES) . '</p>
            </div>
        </div>';
    }
    return '<section class="' . p($props,'bg','bg-slate-900') . ' ' . p($props,'padding','py-16') . ' px-6">
        <div class="max-w-4xl mx-auto">
            <h2 class="text-3xl font-black text-white text-center mb-10">' . p($props,'heading') . '</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">' . $cards . '</div>
        </div>
    </section>';
}

function render_contact_form(array $props): string {
    return '<section class="' . p($props,'bg','bg-slate-900') . ' py-16 px-6">
        <div class="max-w-lg mx-auto">
            <h2 class="text-2xl font-black text-white text-center">' . p($props,'heading') . '</h2>
            <p class="text-slate-400 text-sm text-center mt-2 mb-8">' . p($props,'subtext') . '</p>
            <form action="' . p($props,'action') . '" method="POST" class="space-y-4">
                <input type="text"  name="name"    placeholder="Your Name"     class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:border-teal-500">
                <input type="email" name="email"   placeholder="Email Address" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:border-teal-500">
                <textarea name="message" rows="4"  placeholder="Your message…" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:border-teal-500 resize-none"></textarea>
                <button type="submit" class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-3 rounded-lg text-sm transition">' . p($props,'submit_label','Send Message') . '</button>
            </form>
        </div>
    </section>';
}

function render_spacer(array $props): string {
    return '<div class="' . p($props,'height','h-16') . ' ' . p($props,'bg','bg-transparent') . ' w-full"></div>';
}

// ── Page rendering — ONLY runs when accessed directly ──────────
// When require_once'd by api.php, this block is skipped entirely.
if (basename($_SERVER['SCRIPT_FILENAME']) === 'render.php') {

    $slug     = $_GET['slug']     ?? '';
    $username = $_GET['user']     ?? '';

    if (empty($slug) || empty($username)) {
        http_response_code(400);
        die('<h1>Bad Request</h1><p>Missing slug or username.</p>');
    }

    $db   = get_db_connection();
    $stmt = $db->prepare('
        SELECT p.* FROM projects p
        JOIN users u ON p.user_id = u.id
        WHERE p.slug = ? AND u.username = ?
    ');
    $stmt->execute([$slug, $username]);
    $project = $stmt->fetch();

    if (!$project) {
        http_response_code(404);
        die('<h1>404 Not Found</h1><p>The requested website does not exist or has been unpublished.</p>');
    }

    $raw    = $project['content_json'] ?? '{}';
    $schema = json_decode($raw, true) ?? [];

    if (isset($schema[0]) || (is_array($schema) && array_key_exists(0, $schema))) {
        $blocks = $schema;
        $meta   = [];
    } else {
        $blocks = $schema['blocks'] ?? [];
        $meta   = $schema['meta']   ?? [];
    }

    $is_draft = ($project['status'] !== 'published');

    if ($is_draft) {
        if (!is_logged_in() || $_SESSION['user_id'] !== $project['user_id']) {
            http_response_code(403);
            die('<h1>403 Forbidden</h1><p>This site is currently a draft.</p>');
        }
    }

    $compiled_html = '';
    foreach ($blocks as $block) {
        if (is_array($block)) {
            $compiled_html .= render_block($block);
        }
    }
    ?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($meta['description'] ?? $project['description'] ?? '', ENT_QUOTES); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family: ui-sans-serif, system-ui, sans-serif; background-color: #020617; }</style>
    <?php if (!empty($meta['custom_css'])): ?>
    <style><?php echo $meta['custom_css']; ?></style>
    <?php endif; ?>
</head>
<body class="min-h-screen">

    <main id="wc-page"><?php echo $compiled_html; ?></main>

    <div class="fixed bottom-4 left-4 bg-slate-900/90 backdrop-blur-md text-slate-400 text-[10px] font-bold px-3 py-1.5 rounded-lg border border-slate-800 shadow-xl flex items-center gap-1.5 hover:text-white transition z-50">
        <span class="w-1.5 h-1.5 rounded-full bg-teal-400"></span>
        <span>Built with WebCraft</span>
    </div>

    <script>const PROJECT_ID = <?php echo (int)$project['id']; ?>;</script>
    <?php if (!empty($meta['custom_js'])): ?>
    <script><?php echo $meta['custom_js']; ?></script>
    <?php endif; ?>

</body>
</html>
    <?php
} // end direct-access guard
