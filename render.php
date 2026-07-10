<?php
/**
 * WebCraft High Performance Production Delivery Engine
 * Dynamically resolves, compiles, and renders optimized responsive websites built with WebCraft
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

// Compile cached published HTML or prompt draft message
$body_content = $project['published_html'];
$is_published = ($project['status'] === 'published');

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
    <title><?php echo sanitize_output($project['name']); ?></title>
    <meta name="description" content="<?php echo sanitize_output($project['description'] ?? ''); ?>">
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
</head>
<body class="min-h-screen">

    <!-- MASTER CONTAINER FOR CACHED BUILDER OUTPUT -->
    <main class="space-y-4">
        <?php echo $body_content; ?>
    </main>

    <!-- Optional site branding badge -->
    <div class="fixed bottom-4 left-4 bg-slate-900/90 backdrop-blur-md text-slate-400 text-[10px] font-bold px-3 py-1.5 rounded-lg border border-slate-800 shadow-xl flex items-center gap-1.5 hover:text-white transition z-50">
        <span class="w-1.5 h-1.5 rounded-full bg-teal-400"></span>
        <span>Built with WebCraft</span>
    </div>

</body>
</html>
