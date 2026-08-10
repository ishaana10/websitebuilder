<?php
/**
 * Nuvis Webbuilder High Performance Production Delivery Engine
 * Dynamically resolves, compiles, and renders optimized responsive websites built with Nuvis Webbuilder
 */
require_once __DIR__ . '/config.php';

$slug = $_GET['slug'] ?? '';
$username = $_GET['user'] ?? '';

if (empty($slug) || empty($username)) {
    http_response_code(400);
    die("<h1>Bad Request</h1><p>Missing website project slug or username context.</p>");
}

$db = get_db_connection();

// Secure parameterized resolution
$stmt = $db->prepare("
    SELECT projects.* FROM projects
    JOIN users ON projects.user_id = users.id
    WHERE projects.slug = ? AND users.username = ?
");
$stmt->execute([$slug, $username]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    die("<h1>Website Not Found</h1><p>The requested website does not exist, is set to private, or has been unpublished.</p>");
}

// Extract custom CSS/JS from content_json if present
$custom_css = '';
$custom_js = '';
if (!empty($project['content_json'])) {
    $parsed_json = json_decode($project['content_json'], true);
    if ($parsed_json && is_array($parsed_json) && !isset($parsed_json[0])) {
        $custom_css = $parsed_json['custom_css'] ?? '';
        $custom_js = $parsed_json['custom_js'] ?? '';
    }
}

// Compile cached published HTML or prompt draft message
$body_content = $project['published_html'];
$is_published = ($project['status'] === 'published');

if ($is_published && !empty($body_content)) {
    // Check if body content is JSON representing multiple pages
    $decoded_pages = json_decode($body_content, true);
    if ($decoded_pages !== null && is_array($decoded_pages)) {
        $req_page = $_GET['page'] ?? 'index';
        if (isset($decoded_pages[$req_page])) {
            $body_content = $decoded_pages[$req_page];
        } else {
            $body_content = $decoded_pages['index'] ?? "<h1>Page Not Found</h1><p>The page '" . sanitize_output($req_page) . "' was not found inside this website project.</p>";
        }
    }
}

if (!$is_published || empty($body_content)) {
    // If it's the owner visiting, we show a nice notice, otherwise a generic error
    if (is_logged_in() && $_SESSION['user_id'] === $project['user_id']) {
        $body_content = "
        <div class='min-h-screen bg-slate-950 flex flex-col items-center justify-center p-8 text-white text-center font-sans'>
            <div class='w-16 h-16 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-full flex items-center justify-center text-xl mb-4'>
                <i class='fas fa-exclamation-triangle'></i>
            </div>
            <h1 class='text-2xl font-black tracking-tight'>Project is not Published</h1>
            <p class='text-slate-400 mt-2 text-sm max-w-sm leading-relaxed'>Your website is currently configured as a draft. Open the visual builder workspace and click 'Publish Site' to generate high-performance static caching.</p>
            <a href='builder.php?project_id=" . $project['id'] . "' class='bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-6 py-2.5 rounded-lg text-xs mt-6 transition'>Open Visual Builder</a>
        </div>";
    } else {
        http_response_code(403);
        die("<h1>403 Forbidden</h1><p>This website project is currently in draft state and cannot be previewed publicly.</p>");
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $seo_title = !empty($project['seo_title']) ? $project['seo_title'] : $project['name'];
    $seo_desc = !empty($project['seo_meta_desc']) ? $project['seo_meta_desc'] : ($project['description'] ?? '');
    $seo_og_image = !empty($project['seo_og_image']) ? $project['seo_og_image'] : '';
    $seo_structured = !empty($project['seo_structured_data']) ? $project['seo_structured_data'] : '';
    ?>
    <title><?php echo sanitize_output($seo_title); ?></title>
    <meta name="description" content="<?php echo sanitize_output($seo_desc); ?>">

    <!-- Open Graph / Facebook / Twitter -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo sanitize_output($seo_title); ?>">
    <meta property="og:description" content="<?php echo sanitize_output($seo_desc); ?>">
    <?php if (!empty($seo_og_image)): ?>
    <meta property="og:image" content="<?php echo sanitize_output($seo_og_image); ?>">
    <?php endif; ?>

    <!-- Dynamic robots sitemap indicators link -->
    <link rel="sitemap" type="application/xml" title="Sitemap" href="api.php?action=sitemap&project_id=<?php echo intval($project['id']); ?>">

    <!-- Structured Data JSON-LD Schema Helper -->
    <?php if (!empty($seo_structured)): ?>
    <script type="application/ld+json">
        <?php echo $seo_structured; ?>
    </script>
    <?php endif; ?>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Premium Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            background-color: #020617; /* Default fallback dark background */
        }
    </style>
    <?php if (!empty($custom_css)): ?>
    <style>
        <?php echo $custom_css; ?>
    </style>
    <?php endif; ?>
</head>
<body class="min-h-screen">

    <!-- MASTER CONTAINER FOR CACHED BUILDER OUTPUT -->
    <main class="space-y-4">
        <?php echo $body_content; ?>
    </main>

    <!-- Optional site branding badge -->
    <div class="fixed bottom-4 left-4 bg-slate-900/90 backdrop-blur-md text-slate-400 text-[10px] font-bold px-3 py-1.5 rounded-lg border border-slate-800 shadow-xl flex items-center gap-1.5 hover:text-white transition z-50">
        <span class="w-1.5 h-1.5 rounded-full bg-teal-400"></span>
        <span>Built with Nuvis Webbuilder</span>
    </div>

    <!-- Inject runtime parameters and interactivity logic -->
    <script>
        const PROJECT_ID = <?php echo (int)$project['id']; ?>;
    </script>
    <script src="assets/js/components.js?v=<?php echo time(); ?>"></script>
    <?php if (!empty($custom_js)): ?>
    <script>
        <?php echo $custom_js; ?>
    </script>
    <?php endif; ?>

</body>
</html>
