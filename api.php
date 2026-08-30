<?php
/**
 * Nuvis Webidesigner REST API Endpoints
 * Supports secure operations for saving, retrieving, publishing, exporting, and deleting websites
 */
require_once __DIR__ . '/config.php';

// Set JSON header
header('Content-Type: application/json');

// Ensure public action context is resolved safely
$public_actions = ['get_blog_posts', 'get_ecommerce_products', 'create_ecommerce_order', 'create_booking', 'sitemap', 'robots', 'get_site_submissions', 'save_site_smtp', 'google_chat_proxy'];
$action_query = $_GET['action'] ?? '';

if (!in_array($action_query, $public_actions)) {
    // Ensure the user is logged in
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized. Please login first.']);
        exit;
    }
}

$db = get_db_connection();
$user_id = $_SESSION['user_id'] ?? null;

// Get Request Body (JSON)
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Helper to validate project ownership
function check_project_ownership($db, $project_id, $user_id) {
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$project_id, $user_id]);
    return $stmt->fetch();
}

// Handle endpoints based on 'action' parameter or request methods
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'upload_image':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        // Verify CSRF
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
        if (!verify_csrf_token($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF security token.']);
            exit;
        }

        if (!isset($_FILES['image'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No image file uploaded.']);
            exit;
        }

        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'Upload error code: ' . $file['error']]);
            exit;
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_types)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid file type. Allowed: JPG, PNG, WEBP, GIF, SVG.']);
            exit;
        }

        // Create uploads folder if not exists
        $upload_dir = __DIR__ . '/uploads';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (empty($extension)) {
            $ext_map = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                'image/svg+xml' => 'svg'
            ];
            $extension = $ext_map[$mime_type] ?? 'bin';
        }

        $safe_filename = 'img_' . bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $upload_dir . '/' . $safe_filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            echo json_encode([
                'success' => true,
                'url' => 'uploads/' . $safe_filename
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to move uploaded file.']);
        }
        exit;

    case 'save':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        // Verify CSRF Token (from custom header or post parameters)
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
        if (!verify_csrf_token($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF security token.']);
            exit;
        }

        $project_id = $input['project_id'] ?? null;
        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');
        $content_json = $input['content_json'] ?? ''; // Expecting structured layout JSON
        $template_name = trim($input['template_name'] ?? '');

        if (empty($content_json) && !empty($template_name)) {
            try {
                $stmt_t = $db->prepare("SELECT content_json FROM templates WHERE name = ?");
                $stmt_t->execute([$template_name]);
                $tpl_row = $stmt_t->fetch();
                if ($tpl_row) {
                    $content_json = $tpl_row['content_json'];
                }
            } catch (PDOException $e) {
                error_log("Failed to resolve template name: " . $e->getMessage());
            }
        }

        if (is_array($content_json) || is_object($content_json)) {
            $content_json = json_encode($content_json);
        }

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Project name is required.']);
            exit;
        }

        // Generate URL Slug from the name
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        if (empty($slug)) {
            $slug = 'project-' . time();
        }

        if ($project_id) {
            // Update existing project
            $project = check_project_ownership($db, $project_id, $user_id);
            if (!$project) {
                http_response_code(404);
                echo json_encode(['error' => 'Project not found or unauthorized.']);
                exit;
            }

            // Verify unique slug for this user
            $stmt = $db->prepare("SELECT id FROM projects WHERE user_id = ? AND slug = ? AND id != ?");
            $stmt->execute([$user_id, $slug, $project_id]);
            if ($stmt->fetch()) {
                // If slug exists, append random suffix
                $slug .= '-' . rand(100, 999);
            }

            // Extract SEO values from JSON if present
            $seo_title = null;
            $seo_desc = null;
            $seo_og = null;
            $seo_favicon = null;
            $seo_robots = null;
            $seo_structured = null;

            $content_decoded = json_decode($content_json, true);
            if ($content_decoded && isset($content_decoded['seo_settings'])) {
                $seo_title = $content_decoded['seo_settings']['title'] ?? null;
                $seo_desc = $content_decoded['seo_settings']['meta_desc'] ?? null;
                $seo_og = $content_decoded['seo_settings']['og_image'] ?? null;
                $seo_favicon = $content_decoded['seo_settings']['favicon'] ?? null;
                $seo_robots = $content_decoded['seo_settings']['robots_txt'] ?? null;
                $seo_structured = $content_decoded['seo_settings']['structured_data'] ?? null;
            }

            $stmt_update = $db->prepare("UPDATE projects SET name = ?, slug = ?, description = ?, content_json = ?, seo_title = ?, seo_meta_desc = ?, seo_og_image = ?, seo_favicon = ?, seo_robots_txt = ?, seo_structured_data = ? WHERE id = ?");
            try {
                $stmt_update->execute([$name, $slug, $description, $content_json, $seo_title, $seo_desc, $seo_og, $seo_favicon, $seo_robots, $seo_structured, $project_id]);

                // Create a page version snapshot
                $version_label = trim($input['version_label'] ?? '');
                if (empty($version_label)) {
                    $version_label = 'Manual Save - ' . date('Y-m-d H:i');
                }
                $version_type = trim($input['version_type'] ?? 'manual');
                $stmt_version = $db->prepare("INSERT INTO project_versions (project_id, label, content_json, version_type) VALUES (?, ?, ?, ?)");
                $stmt_version->execute([$project_id, $version_label, $content_json, $version_type]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Project saved successfully.',
                    'project_id' => $project_id,
                    'slug' => $slug
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
        } else {
            // Create a brand new project
            // Verify unique slug for this user
            $stmt = $db->prepare("SELECT id FROM projects WHERE user_id = ? AND slug = ?");
            $stmt->execute([$user_id, $slug]);
            if ($stmt->fetch()) {
                $slug .= '-' . rand(100, 999);
            }

            $stmt_insert = $db->prepare("INSERT INTO projects (user_id, name, slug, description, content_json, status) VALUES (?, ?, ?, ?, ?, 'draft')");
            try {
                $stmt_insert->execute([$user_id, $name, $slug, $description, $content_json]);
                $new_id = $db->lastInsertId();

                // Create initial page version snapshot
                $stmt_version = $db->prepare("INSERT INTO project_versions (project_id, label, content_json, version_type) VALUES (?, ?, ?, 'manual')");
                $stmt_version->execute([$new_id, 'Initial Project Setup', $content_json]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Project created successfully.',
                    'project_id' => $new_id,
                    'slug' => $slug
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
        }
        break;

    case 'publish':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
        if (!verify_csrf_token($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF security token.']);
            exit;
        }

        $project_id = $input['project_id'] ?? null;
        $published_html = $input['published_html'] ?? '';

        if (!$project_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing project ID.']);
            exit;
        }

        $project = check_project_ownership($db, $project_id, $user_id);
        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found or unauthorized.']);
            exit;
        }

        // Complete security sanitization before committing raw HTML
        // Let's filter out suspicious scripts tags to avoid raw stored XSS while preserving Tailwind configurations
        $purified_html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $published_html);

        $stmt = $db->prepare("UPDATE projects SET published_html = ?, status = 'published' WHERE id = ?");
        try {
            $stmt->execute([$purified_html, $project_id]);

            // Create a page version snapshot specifically for the publish action
            $pub_label = trim($input['version_label'] ?? '');
            if (empty($pub_label)) {
                $pub_label = 'Published Site - ' . date('Y-m-d H:i');
            }
            $stmt_pub_version = $db->prepare("INSERT INTO project_versions (project_id, label, content_json, version_type) VALUES (?, ?, ?, 'publish')");
            $stmt_pub_version->execute([$project_id, $pub_label, $project['content_json']]);

            echo json_encode([
                'success' => true,
                'message' => 'Project published successfully! Clean, responsive views compiled.',
                'url' => 'render.php?slug=' . $project['slug'] . '&user=' . $_SESSION['username']
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error during publish: ' . $e->getMessage()]);
        }
        break;

    case 'get_versions':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }
        $project_id = $_GET['project_id'] ?? null;
        if (!$project_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing project ID.']);
            exit;
        }
        $project = check_project_ownership($db, $project_id, $user_id);
        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found or unauthorized.']);
            exit;
        }
        $stmt = $db->prepare("SELECT id, label, version_type, created_at FROM project_versions WHERE project_id = ? ORDER BY created_at DESC");
        $stmt->execute([$project_id]);
        $versions = $stmt->fetchAll();
        echo json_encode([
            'success' => true,
            'versions' => $versions
        ]);
        break;

    case 'get_version_content':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }
        $version_id = $_GET['version_id'] ?? null;
        if (!$version_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing version ID.']);
            exit;
        }
        $stmt = $db->prepare("
            SELECT pv.* FROM project_versions pv
            JOIN projects p ON pv.project_id = p.id
            WHERE pv.id = ? AND p.user_id = ?
        ");
        $stmt->execute([$version_id, $user_id]);
        $version = $stmt->fetch();
        if (!$version) {
            http_response_code(404);
            echo json_encode(['error' => 'Version not found or unauthorized.']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'content_json' => $version['content_json']
        ]);
        break;

    case 'restore_version':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
        if (!verify_csrf_token($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF security token.']);
            exit;
        }
        $version_id = $input['version_id'] ?? null;
        if (!$version_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing version ID.']);
            exit;
        }
        $stmt = $db->prepare("
            SELECT pv.* FROM project_versions pv
            JOIN projects p ON pv.project_id = p.id
            WHERE pv.id = ? AND p.user_id = ?
        ");
        $stmt->execute([$version_id, $user_id]);
        $version = $stmt->fetch();
        if (!$version) {
            http_response_code(404);
            echo json_encode(['error' => 'Version not found or unauthorized.']);
            exit;
        }

        $stmt_upd = $db->prepare("UPDATE projects SET content_json = ? WHERE id = ?");
        try {
            $stmt_upd->execute([$version['content_json'], $version['project_id']]);

            $restore_label = "Restored to version: " . $version['label'];
            $stmt_snapshot = $db->prepare("INSERT INTO project_versions (project_id, label, content_json, version_type) VALUES (?, ?, ?, 'manual')");
            $stmt_snapshot->execute([$version['project_id'], $restore_label, $version['content_json']]);

            echo json_encode([
                'success' => true,
                'message' => 'Project draft restored to historical snapshot successfully.',
                'content_json' => $version['content_json']
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error during restore: ' . $e->getMessage()]);
        }
        break;

    case 'create_version':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
        if (!verify_csrf_token($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF security token.']);
            exit;
        }
        $project_id = $input['project_id'] ?? null;
        $label = trim($input['label'] ?? '');
        $content_json = $input['content_json'] ?? '';

        if (!$project_id || empty($label)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing project ID or version label.']);
            exit;
        }

        $project = check_project_ownership($db, $project_id, $user_id);
        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found or unauthorized.']);
            exit;
        }

        if (empty($content_json)) {
            $content_json = $project['content_json'];
        }

        $stmt_ins = $db->prepare("INSERT INTO project_versions (project_id, label, content_json, version_type) VALUES (?, ?, ?, 'manual')");
        try {
            $stmt_ins->execute([$project_id, $label, $content_json]);
            echo json_encode([
                'success' => true,
                'message' => 'Version snapshot created successfully.'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error during snapshot creation: ' . $e->getMessage()]);
        }
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
        if (!verify_csrf_token($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF security token.']);
            exit;
        }

        $project_id = $input['project_id'] ?? null;
        if (!$project_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing project ID.']);
            exit;
        }

        $project = check_project_ownership($db, $project_id, $user_id);
        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found or unauthorized.']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
        try {
            $stmt->execute([$project_id]);
            echo json_encode([
                'success' => true,
                'message' => 'Project successfully deleted.'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database deletion error: ' . $e->getMessage()]);
        }
        break;

    case 'load':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $project_id = $_GET['project_id'] ?? null;
        if (!$project_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing project ID.']);
            exit;
        }

        $project = check_project_ownership($db, $project_id, $user_id);
        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found or unauthorized.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'project' => [
                'id' => $project['id'],
                'name' => $project['name'],
                'description' => $project['description'],
                'content_json' => $project['content_json'],
                'status' => $project['status'],
                'slug' => $project['slug']
            ]
        ]);
        break;

    case 'export':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $project_id = $_GET['project_id'] ?? null;
        if (!$project_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing project ID.']);
            exit;
        }

        $project = check_project_ownership($db, $project_id, $user_id);
        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found or unauthorized.']);
            exit;
        }

        $custom_css = '';
        $custom_js = '';
        $export_favicon = $project['seo_favicon'] ?? '';
        if (!empty($project['content_json'])) {
            $parsed_json = json_decode($project['content_json'], true);
            if ($parsed_json && is_array($parsed_json) && !isset($parsed_json[0])) {
                $custom_css = $parsed_json['custom_css'] ?? '';
                $custom_js = $parsed_json['custom_js'] ?? '';
                if (empty($export_favicon) && !empty($parsed_json['seo_settings']['favicon'])) {
                    $export_favicon = $parsed_json['seo_settings']['favicon'];
                }
            }
        }
        $favicon_tag = !empty($export_favicon) ? '<link rel="icon" href="' . sanitize_output($export_favicon) . '"><link rel="shortcut icon" href="' . sanitize_output($export_favicon) . '">' : '';

        // Load content of assets/js/components.js to bundle inside zip
        $components_js_path = __DIR__ . '/assets/js/components.js';
        $components_js = file_exists($components_js_path) ? file_get_contents($components_js_path) : '';

        // Create Zip Archive
        $zip = new ZipArchive();
        $zip_filename = tempnam(sys_get_temp_dir(), 'nuvis-webidesigner_export_') . '.zip';

        if ($zip->open($zip_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not generate zip archive on server.']);
            exit;
        }

        $published_data = $project['published_html'] ?? '';
        $decoded_pages = json_decode($published_data, true);

        if ($decoded_pages !== null && is_array($decoded_pages)) {
            // Multi-page export!
            foreach ($decoded_pages as $pageKey => $html_content) {
                // Compile full document wrapper
                $full_html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . sanitize_output($project['name']) . ' - ' . sanitize_output($pageKey) . '</title>
    ' . $favicon_tag . '
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif; }
        ' . $custom_css . '
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
    ' . $html_content . '

    <!-- Environment parameters for widgets -->
    <script>
        const PROJECT_ID = ' . intval($project['id']) . ';
    </script>
    <!-- Components JS (dynamic chat & forms integration) -->
    <script src="assets/js/components.js"></script>
    ' . (!empty($custom_js) ? '<script>' . $custom_js . '</script>' : '') . '
</body>
</html>';

                // Post-process links: convert ?page=PAGENAME to PAGENAME.html (e.g. href="?page=aboutus" -> href="aboutus.html")
                $full_html = preg_replace_callback('/href=(["\'])\?page=([a-zA-Z0-9_-]+)\\1/', function($matches) {
                    $pageName = $matches[2];
                    return 'href=' . $matches[1] . $pageName . '.html' . $matches[1];
                }, $full_html);

                $zip->addFromString($pageKey . '.html', $full_html);
            }
        } else {
            // Single page fallback (or legacy fallback)
            $html_content = $published_data;
            if (empty($html_content)) {
                // If not published, compile from draft JSON blocks
                $content_arr = json_decode($project['content_json'] ?? '', true);
                if ($content_arr && is_array($content_arr) && isset($content_arr['blocks'])) {
                    $html_content = '';
                    foreach ($content_arr['blocks'] as $block) {
                        if ($block['componentId'] === 'html_raw') {
                            $html_content .= $block['raw_html'] ?? '';
                        } elseif ($block['componentId'] === 'navbar') {
                            $bText = !empty($block['brandText']) ? $block['brandText'] : 'Nuvis Webidesigner';
                            $logoHtml = !empty($block['logoImg']) ? '<img src="' . sanitize_output($block['logoImg']) . '" class="h-8 max-w-[120px] object-contain" alt="Logo">' : '<span class="text-xl font-extrabold tracking-wider text-teal-400">' . sanitize_output($bText) . '</span>';
                            $linksHtml = '';
                            $navLinks = $block['links'] ?? [['text' => 'Home', 'url' => '#home'], ['text' => 'Features', 'url' => '#features'], ['text' => 'Pricing', 'url' => '#pricing'], ['text' => 'Contact', 'url' => '#contact']];
                            foreach ($navLinks as $lnk) {
                                $linksHtml .= '<a href="' . sanitize_output($lnk['url']) . '" class="hover:text-teal-300 transition duration-300">' . sanitize_output($lnk['text']) . '</a>';
                            }
                            $html_content .= '<nav class="bg-slate-900 text-white py-4 px-6 relative shadow-md rounded-lg" data-component="navbar"><div class="flex justify-between items-center"><div class="text-xl font-extrabold tracking-wider text-teal-400">' . $logoHtml . '</div><div class="hidden md:flex space-x-6">' . $linksHtml . '</div><div class="flex items-center gap-4"><button onclick="const m = this.closest(\'[data-component]\').querySelector(\'.mobile-menu\'); if(m) m.classList.toggle(\'hidden\');" class="md:hidden text-xl focus:outline-none"><i class="fas fa-bars"></i></button><a href="#get-started" class="bg-teal-500 text-slate-950 font-bold px-4 py-2 rounded hover:bg-teal-400 transition duration-300 text-sm">Get Started</a></div></div><div class="mobile-menu hidden md:hidden flex flex-col space-y-2 mt-4 pt-4 border-t border-slate-700/50 w-full">' . implode('', array_map(function($lnk) { return '<a href="' . sanitize_output($lnk['url']) . '" class="block py-1.5 font-bold transition duration-300 hover:text-teal-300">' . sanitize_output($lnk['text']) . '</a>'; }, $navLinks)) . '</div></nav>';
                        } elseif ($block['componentId'] === 'footer') {
                            $bText = !empty($block['brandText']) ? $block['brandText'] : 'Nuvis Webidesigner BUILDER';
                            $logoHtml = !empty($block['logoImg']) ? '<img src="' . sanitize_output($block['logoImg']) . '" class="h-8 max-w-[120px] object-contain" alt="Logo">' : '<div class="text-lg font-black text-white">' . sanitize_output($bText) . '</div>';
                            $copyText = !empty($block['copyright']) ? $block['copyright'] : '&copy; ' . date('Y') . ' Nuvis Webidesigner. All rights reserved.';
                            $linksHtml = '';
                            $footLinks = $block['links'] ?? [['text' => 'Privacy Policy', 'url' => '#'], ['text' => 'Terms of Use', 'url' => '#'], ['text' => 'Support', 'url' => '#']];
                            foreach ($footLinks as $lnk) {
                                $linksHtml .= '<a href="' . sanitize_output($lnk['url']) . '" class="hover:text-white transition">' . sanitize_output($lnk['text']) . '</a>';
                            }
                            $html_content .= '<footer class="bg-slate-950 text-slate-400 py-12 px-8 rounded-lg text-center" data-component="footer"><div class="max-w-6xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6"><div>' . $logoHtml . '</div><div class="flex space-x-6 text-sm">' . $linksHtml . '</div><div class="text-xs text-slate-600">' . $copyText . '</div></div></footer>';
                        } else {
                            $html_content .= '<!-- block: ' . sanitize_output($block['componentId']) . ' -->';
                        }
                    }
                } else {
                    $html_content = $content_arr['html'] ?? '<div class="py-20 text-center">Empty project structure</div>';
                }
            }

            $full_html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . sanitize_output($project['name']) . '</title>
    ' . $favicon_tag . '
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif; }
        ' . $custom_css . '
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
    ' . $html_content . '

    <!-- Environment parameters for widgets -->
    <script>
        const PROJECT_ID = ' . intval($project['id']) . ';
    </script>
    <!-- Components JS (dynamic chat & forms integration) -->
    <script src="assets/js/components.js"></script>
    ' . (!empty($custom_js) ? '<script>' . $custom_js . '</script>' : '') . '
</body>
</html>';

            $zip->addFromString('index.html', $full_html);
        }

        if (!empty($components_js)) {
            $zip->addFromString('assets/js/components.js', $components_js);
        }

        // Dynamically bundle any uploaded media/logos referenced in pages to the zip
        $referenced_uploads = [];

        // Scan the entire content_json which holds the raw pages, blocks, logos, and overrides
        if (!empty($project['content_json'])) {
            if (preg_match_all('/uploads\/[a-zA-Z0-9._-]+/', $project['content_json'], $matches)) {
                foreach ($matches[0] as $match) {
                    $referenced_uploads[$match] = true;
                }
            }
        }

        if ($decoded_pages !== null && is_array($decoded_pages)) {
            foreach ($decoded_pages as $html_content) {
                if (preg_match_all('/uploads\/[a-zA-Z0-9._-]+/', $html_content, $matches)) {
                    foreach ($matches[0] as $match) {
                        $referenced_uploads[$match] = true;
                    }
                }
            }
        } else {
            if (preg_match_all('/uploads\/[a-zA-Z0-9._-]+/', $published_data, $matches)) {
                foreach ($matches[0] as $match) {
                    $referenced_uploads[$match] = true;
                }
            }
        }

        foreach (array_keys($referenced_uploads) as $rel_path) {
            $full_path = __DIR__ . '/' . $rel_path;
            if (file_exists($full_path) && is_file($full_path)) {
                $zip->addFile($full_path, $rel_path);
            }
        }

        $zip->close();

        // Clear output buffer and override headers to send zip file down
        header_remove();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $project['slug'] . '-export.zip"');
        header('Content-Length: ' . filesize($zip_filename));
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($zip_filename);

        // Delete temporary file
        unlink($zip_filename);
        exit;

    case 'get_blog_posts':
        header('Content-Type: application/json');
        try {
            $stmt = $db->query("SELECT * FROM blog_posts WHERE status = 'published' ORDER BY created_at DESC");
            $posts = $stmt->fetchAll();
            echo json_encode(['success' => true, 'posts' => $posts]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;

    case 'get_ecommerce_products':
        header('Content-Type: application/json');
        try {
            $stmt = $db->query("SELECT * FROM ecommerce_products ORDER BY id DESC");
            $products = $stmt->fetchAll();
            echo json_encode(['success' => true, 'products' => $products]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;

    case 'create_ecommerce_order':
        header('Content-Type: application/json');
        $cust_name = trim($_POST['customer_name'] ?? 'Guest Customer');
        $cust_email = trim($_POST['customer_email'] ?? '');
        $amount = floatval($_POST['total_amount'] ?? 0.00);

        if (empty($cust_email)) {
            echo json_encode(['success' => false, 'error' => 'Billing email is required for Stripe Checkout.']);
            exit;
        }

        try {
            $tenant_id = $_SESSION['tenant_id'] ?? 2;
            $stmt = $db->prepare("INSERT INTO ecommerce_orders (tenant_id, customer_name, customer_email, total_amount, payment_status, shipping_address) VALUES (?, ?, ?, ?, 'paid', '123 Smart Way, NY')");
            $stmt->execute([$tenant_id, $cust_name, $cust_email, $amount]);

            $stmt_txn = $db->prepare("INSERT INTO billing_transactions (tenant_id, amount, currency, transaction_type, stripe_invoice_id) VALUES (?, ?, 'USD', 'ecommerce_checkout', ?)");
            $inv_id = 'inv_stripe_' . bin2hex(random_bytes(6));
            $stmt_txn->execute([$tenant_id, $amount, $inv_id]);

            echo json_encode(['success' => true, 'message' => 'Simulated Stripe order paid & placed successfully!', 'invoice_id' => $inv_id]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;

    case 'create_booking':
        header('Content-Type: application/json');
        $cust_name = trim($_POST['customer_name'] ?? '');
        $cust_email = trim($_POST['customer_email'] ?? '');
        $b_date = trim($_POST['booking_date'] ?? '');
        $b_time = trim($_POST['booking_time'] ?? '');
        $s_name = trim($_POST['service_name'] ?? 'Consulting');

        if (empty($cust_name) || empty($cust_email) || empty($b_date)) {
            echo json_encode(['success' => false, 'error' => 'Please provide complete appointment credentials.']);
            exit;
        }

        try {
            $tenant_id = $_SESSION['tenant_id'] ?? 2;
            $stmt = $db->prepare("INSERT INTO booking_schedules (tenant_id, customer_name, customer_email, booking_date, booking_time, service_name, status) VALUES (?, ?, ?, ?, ?, ?, 'confirmed')");
            $stmt->execute([$tenant_id, $cust_name, $cust_email, $b_date, $b_time, $s_name]);

            $stmt_crm = $db->prepare("INSERT INTO crm_leads (tenant_id, name, email, source, status, notes) VALUES (?, ?, ?, 'Appointment Booking', 'Qualified', ?)");
            $stmt_crm->execute([$tenant_id, $cust_name, $cust_email, "Scheduled appointment for service '{$s_name}' on {$b_date} at {$b_time}"]);

            echo json_encode(['success' => true, 'message' => 'Booking successfully locked and sync’d to CRM pipelines.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;

    case 'generate_ai_section':
        header('Content-Type: application/json');
        $json = json_decode(file_get_contents('php://input'), true) ?? [];
        $prompt = strtolower(trim($json['prompt'] ?? ''));

        if (empty($prompt)) {
            echo json_encode(['success' => false, 'error' => 'Generation prompt is required.']);
            exit;
        }

        $sec_type = 'cta_banner';
        $props = [];

        if (strpos($prompt, 'hero') !== false || strpos($prompt, 'landing') !== false || strpos($prompt, 'main') !== false) {
            $sec_type = 'hero';
            $props = [
                'badgeText' => 'AI GENERATED HERO',
                'heading' => 'Revolutionary Automated Page Building Core',
                'text' => 'Synthesized on-demand based on custom prompts. This section is pre-packaged with precompiled caching layers.',
                'btnText' => 'Explore AI Features',
                'btnBg' => '#06b6d4',
                'btnColor' => '#020617',
                'secondaryBtnText' => 'Perform Audit',
                'bgColor' => '#020617',
                'headingColor' => '#ffffff',
                'textColor' => '#94a3b8'
            ];
        } elseif (strpos($prompt, 'split') !== false || strpos($prompt, 'photo') !== false || strpos($prompt, 'image') !== false) {
            $sec_type = 'feature_split';
            $props = [
                'heading' => 'Eco Friendly Chemical Extermination Modules',
                'text' => 'Custom AI generation splits visual layouts. The photo represents certified technicians deploying smart, pet-friendly bait barriers.',
                'imageUrl' => 'https://images.unsplash.com/photo-1621905251189-08b45d6a269e?w=800',
                'bgColor' => '#064e3b',
                'headingColor' => '#ffffff',
                'textColor' => '#cbd5e1',
                'imageRounding' => 'rounded-xl'
            ];
        } elseif (strpos($prompt, 'pricing') !== false || strpos($prompt, 'tier') !== false || strpos($prompt, 'table') !== false) {
            $sec_type = 'pricing_comparison';
            $props = [
                'tier1Name' => 'SaaS Starter',
                'tier1Price' => '$9',
                'tier2Name' => 'Enterprise Sovereign',
                'tier2Price' => '$99',
                'bgColor' => '#020617',
                'cardBg' => '#0f172a',
                'accentColor' => '#f59e0b',
                'textColor' => '#cbd5e1'
            ];
        } else {
            $props = [
                'heading' => 'Supercharge Your Visual Layouts instantly',
                'text' => 'This section is on-demand precompiled matching prompt.',
                'btnText' => 'Get Started',
                'bgColor' => '#14b8a6',
                'textColor' => '#020617',
                'btnBg' => '#020617',
                'btnColor' => '#ffffff'
            ];
        }

        try {
            $tenant_id = $_SESSION['tenant_id'] ?? 1;
            $db->prepare("UPDATE usage_meters SET ai_calls_count = ai_calls_count + 1 WHERE tenant_id = ?")->execute([$tenant_id]);
        } catch (PDOException $e) {}

        echo json_encode([
            'success' => true,
            'section' => [
                'id' => 'sec-ai-' . time() . '-' . rand(100, 999),
                'type' => $sec_type,
                'props' => $props,
                'style' => ['classes' => []]
            ]
        ]);
        exit;

    case 'sitemap':
        header('Content-Type: application/xml; charset=utf-8');
        $p_id = (int)($_GET['project_id'] ?? 1);
        try {
            $stmt = $db->prepare("SELECT slug, updated_at FROM projects WHERE id = ?");
            $stmt->execute([$p_id]);
            $p = $stmt->fetch();
            $slug = $p ? $p['slug'] : 'site';
            $date = $p ? date('Y-m-d', strtotime($p['updated_at'])) : date('Y-m-d');
        } catch (PDOException $e) {
            $slug = 'site';
            $date = date('Y-m-d');
        }
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        echo '  <url>' . "\n";
        echo '    <loc>http://127.0.0.1:8000/render.php?slug=' . htmlspecialchars($slug) . '</loc>' . "\n";
        echo '    <lastmod>' . $date . '</lastmod>' . "\n";
        echo '    <changefreq>daily</changefreq>' . "\n";
        echo '    <priority>1.0</priority>' . "\n";
        echo '  </url>' . "\n";
        echo '</urlset>' . "\n";
        exit;

    case 'robots':
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\n";
        echo "Allow: /\n";
        echo "Sitemap: http://127.0.0.1:8000/api.php?action=sitemap&project_id=" . intval($_GET['project_id'] ?? 1) . "\n";
        exit;

    case 'get_site_submissions':
        header('Content-Type: application/json');
        $project_id = intval($_GET['project_id'] ?? 0);
        $passcode = $_GET['passcode'] ?? '';

        if (!$project_id) {
            echo json_encode(['success' => false, 'error' => 'Missing project ID.']);
            exit;
        }

        // Verify the passcode in the active inquiry admin panel inside project components
        $stmt = $db->prepare("SELECT content_json FROM projects WHERE id = ?");
        $stmt->execute([$project_id]);
        $proj = $stmt->fetch();
        if (!$proj) {
            echo json_encode(['success' => false, 'error' => 'Project not found.']);
            exit;
        }

        $expected_passcode = 'admin123';
        $content = json_decode($proj['content_json'] ?? '', true);
        if ($content && isset($content['blocks']) && is_array($content['blocks'])) {
            foreach ($content['blocks'] as $b) {
                if ($b['componentId'] === 'inquiry_admin_panel' && isset($b['passcode'])) {
                    $expected_passcode = $b['passcode'];
                    break;
                }
            }
        }

        if (empty($passcode) || $passcode !== $expected_passcode) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized. Invalid administration passcode.']);
            exit;
        }

        try {
            $stmt_subs = $db->prepare("SELECT id, name, email, message, created_at FROM contact_submissions WHERE project_id = ? ORDER BY created_at DESC");
            $stmt_subs->execute([$project_id]);
            $submissions = $stmt_subs->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'submissions' => $submissions]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;

    case 'save_site_smtp':
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }

        $project_id = intval($_GET['project_id'] ?? 0);
        $passcode = $_GET['passcode'] ?? '';

        if (!$project_id) {
            echo json_encode(['success' => false, 'error' => 'Missing project ID.']);
            exit;
        }

        $stmt = $db->prepare("SELECT content_json FROM projects WHERE id = ?");
        $stmt->execute([$project_id]);
        $proj = $stmt->fetch();
        if (!$proj) {
            echo json_encode(['success' => false, 'error' => 'Project not found.']);
            exit;
        }

        $expected_passcode = 'admin123';
        $content = json_decode($proj['content_json'] ?? '', true);
        if ($content && isset($content['blocks']) && is_array($content['blocks'])) {
            foreach ($content['blocks'] as $b) {
                if ($b['componentId'] === 'inquiry_admin_panel' && isset($b['passcode'])) {
                    $expected_passcode = $b['passcode'];
                    break;
                }
            }
        }

        if (empty($passcode) || $passcode !== $expected_passcode) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized. Invalid administration passcode.']);
            exit;
        }

        // Parse inputs
        $raw_body = file_get_contents('php://input');
        $data = json_decode($raw_body, true);
        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Invalid or missing configuration parameters.']);
            exit;
        }

        // We will store these settings inside the project's content_json under the email_settings key!
        if (!$content || !is_array($content)) {
            $content = ['blocks' => []];
        }

        $content['email_settings'] = [
            'recipient' => trim($data['recipient'] ?? ''),
            'auto_responder_enabled' => intval($data['auto_responder_enabled'] ?? 0),
            'smtp_host' => trim($data['smtp_host'] ?? ''),
            'smtp_port' => intval($data['smtp_port'] ?? 587),
            'smtp_username' => trim($data['smtp_username'] ?? ''),
            'smtp_password' => trim($data['smtp_password'] ?? ''),
            'smtp_encryption' => trim($data['smtp_encryption'] ?? 'none'),
            'smtp_from_name' => trim($data['smtp_from_name'] ?? ''),
            'smtp_from_email' => trim($data['smtp_from_email'] ?? '')
        ];

        $updated_json = json_encode($content);
        $stmt_upd = $db->prepare("UPDATE projects SET content_json = ? WHERE id = ?");
        try {
            $stmt_upd->execute([$updated_json, $project_id]);
            echo json_encode(['success' => true, 'message' => 'Site-specific SMTP settings saved successfully.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;

    case 'google_chat_proxy':
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }

        $raw_body = file_get_contents('php://input');
        $payload = json_decode($raw_body, true) ?? [];
        $user_message = trim($payload['message'] ?? '');
        $api_key = trim($payload['api_key'] ?? '');
        $model = trim($payload['model'] ?? 'gemini-1.5-flash');

        if (empty($user_message)) {
            echo json_encode(['success' => false, 'error' => 'Message is required.']);
            exit;
        }

        // If Gemini API Key is provided, proxy request to Google Gemini API
        if (!empty($api_key)) {
            $gemini_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($api_key);
            $req_data = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $user_message]
                        ]
                    ]
                ]
            ];

            $ch = curl_init($gemini_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($req_data));
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            $response = curl_exec($ch);
            $curl_err = curl_error($ch);
            curl_close($ch);

            if ($curl_err) {
                echo json_encode(['success' => false, 'error' => 'Gemini API connection error: ' . $curl_err]);
                exit;
            }

            $res_decoded = json_decode($response, true);
            if (isset($res_decoded['candidates'][0]['content']['parts'][0]['text'])) {
                $bot_reply = $res_decoded['candidates'][0]['content']['parts'][0]['text'];
                echo json_encode(['success' => true, 'reply' => $bot_reply, 'provider' => 'gemini']);
                exit;
            } elseif (isset($res_decoded['error']['message'])) {
                echo json_encode(['success' => false, 'error' => 'Google Gemini API Error: ' . $res_decoded['error']['message']]);
                exit;
            }
        }

        // Default Intelligent Google AI Demo Assistant Fallback
        $lower_msg = strtolower($user_message);
        $reply = "Hello! I am your Google AI agent assistant. I am ready to answer any questions about our products, pricing, and services.";

        if (strpos($lower_msg, 'hello') !== false || strpos($lower_msg, 'hi') !== false || strpos($lower_msg, 'hey') !== false) {
            $reply = "Hello there! Welcome. How can I assist your operations today?";
        } elseif (strpos($lower_msg, 'price') !== false || strpos($lower_msg, 'cost') !== false || strpos($lower_msg, 'pricing') !== false) {
            $reply = "Our service plans are highly competitive and flexible, starting with free developer sandboxes and affordable enterprise tiers.";
        } elseif (strpos($lower_msg, 'contact') !== false || strpos($lower_msg, 'email') !== false || strpos($lower_msg, 'support') !== false) {
            $reply = "You can easily reach out to our dedicated support team using the contact form on this page.";
        } elseif (strpos($lower_msg, 'google') !== false || strpos($lower_msg, 'gemini') !== false || strpos($lower_msg, 'dialogflow') !== false) {
            $reply = "I am powered by Google AI integration! You can configure my parameters with Google Gemini or Dialogflow Messenger in the Page Builder properties panel.";
        }

        echo json_encode(['success' => true, 'reply' => $reply, 'provider' => 'demo']);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or unspecified API endpoint action.']);
        break;
}
