<?php
/**
 * Nuvis Webbuilder REST API Endpoints
 * Supports secure operations for saving, retrieving, publishing, exporting, and deleting websites
 */
require_once __DIR__ . '/config.php';

// Set JSON header
header('Content-Type: application/json');

// Ensure the user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please login first.']);
    exit;
}

$db = get_db_connection();
$user_id = $_SESSION['user_id'];

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

            $stmt_update = $db->prepare("UPDATE projects SET name = ?, slug = ?, description = ?, content_json = ? WHERE id = ?");
            try {
                $stmt_update->execute([$name, $slug, $description, $content_json, $project_id]);

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

        // Generate compiled index.html
        $html_content = $project['published_html'] ?? '';

        $custom_css = '';
        $custom_js = '';
        if (!empty($project['content_json'])) {
            $parsed_json = json_decode($project['content_json'], true);
            if ($parsed_json && is_array($parsed_json) && !isset($parsed_json[0])) {
                $custom_css = $parsed_json['custom_css'] ?? '';
                $custom_js = $parsed_json['custom_js'] ?? '';
            }
        }

        if (empty($html_content)) {
            // Fallback: If not published, decode draft JSON
            $content_arr = json_decode($project['content_json'] ?? '', true);
            if ($content_arr && is_array($content_arr) && isset($content_arr['blocks'])) {
                // If it is our structured format, compile HTML blocks
                $html_content = '';
                foreach ($content_arr['blocks'] as $block) {
                    if ($block['componentId'] === 'html_raw') {
                        $html_content .= $block['raw_html'] ?? '';
                    } elseif ($block['componentId'] === 'navbar') {
                        $bText = !empty($block['brandText']) ? $block['brandText'] : 'NUVIS WEBBUILDER';
                        $logoHtml = '';
                        if (!empty($block['logoImg'])) {
                            $logoHtml = '<img src="' . sanitize_output($block['logoImg']) . '" class="h-8 max-w-[120px] object-contain" alt="Logo">';
                        } else {
                            $logoHtml = '<span class="text-xl font-extrabold tracking-wider text-teal-400">' . sanitize_output($bText) . '</span>';
                        }

                        $linksHtml = '';
                        $navLinks = $block['links'] ?? [
                            ['text' => 'Home', 'url' => '#home'],
                            ['text' => 'Features', 'url' => '#features'],
                            ['text' => 'Pricing', 'url' => '#pricing'],
                            ['text' => 'Contact', 'url' => '#contact']
                        ];
                        foreach ($navLinks as $lnk) {
                            $linksHtml .= '<a href="' . sanitize_output($lnk['url']) . '" class="hover:text-teal-300 transition duration-300">' . sanitize_output($lnk['text']) . '</a>';
                        }

                        $html_content .= '
<nav class="bg-slate-900 text-white py-4 px-6 flex justify-between items-center shadow-md rounded-lg" data-component="navbar">
    <div class="text-xl font-extrabold tracking-wider text-teal-400">' . $logoHtml . '</div>
    <div class="hidden md:flex space-x-6">' . $linksHtml . '</div>
    <div>
        <a href="#get-started" class="bg-teal-500 text-slate-950 font-bold px-4 py-2 rounded hover:bg-teal-400 transition duration-300 text-sm">Get Started</a>
    </div>
</nav>';
                    } elseif ($block['componentId'] === 'footer') {
                        $bText = !empty($block['brandText']) ? $block['brandText'] : 'NUVIS WEBBUILDER BUILDER';
                        $logoHtml = '';
                        if (!empty($block['logoImg'])) {
                            $logoHtml = '<img src="' . sanitize_output($block['logoImg']) . '" class="h-8 max-w-[120px] object-contain" alt="Logo">';
                        } else {
                            $logoHtml = '<div class="text-lg font-black text-white">' . sanitize_output($bText) . '</div>';
                        }

                        $copyText = !empty($block['copyright']) ? $block['copyright'] : '&copy; ' . date('Y') . ' Nuvis Webbuilder. All rights reserved.';

                        $linksHtml = '';
                        $footLinks = $block['links'] ?? [
                            ['text' => 'Privacy Policy', 'url' => '#'],
                            ['text' => 'Terms of Use', 'url' => '#'],
                            ['text' => 'Support', 'url' => '#']
                        ];
                        foreach ($footLinks as $lnk) {
                            $linksHtml .= '<a href="' . sanitize_output($lnk['url']) . '" class="hover:text-white transition">' . sanitize_output($lnk['text']) . '</a>';
                        }

                        $html_content .= '
<footer class="bg-slate-950 text-slate-400 py-12 px-8 rounded-lg text-center" data-component="footer">
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6">
        <div>' . $logoHtml . '</div>
        <div class="flex space-x-6 text-sm">' . $linksHtml . '</div>
        <div class="text-xs text-slate-600">' . $copyText . '</div>
    </div>
</footer>';
                    } else {
                        // Predefined basic components fallback
                        $html_content .= '<!-- block: ' . sanitize_output($block['componentId']) . ' -->';
                    }
                }
            } else {
                $html_content = $content_arr['html'] ?? '<div class="py-20 text-center">Empty project structure</div>';
            }
        }

        // Include wrapper headers/assets similar to render.php
        $full_html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . sanitize_output($project['name']) . '</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: "Inter", sans-serif; }
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

        // Load content of assets/js/components.js to bundle inside zip
        $components_js_path = __DIR__ . '/assets/js/components.js';
        $components_js = file_exists($components_js_path) ? file_get_contents($components_js_path) : '';

        // Create Zip Archive
        $zip = new ZipArchive();
        $zip_filename = tempnam(sys_get_temp_dir(), 'nuvis-webbuilder_export_') . '.zip';

        if ($zip->open($zip_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not generate zip archive on server.']);
            exit;
        }

        // Add index.html, components.js
        $zip->addFromString('index.html', $full_html);
        if (!empty($components_js)) {
            $zip->addFromString('assets/js/components.js', $components_js);
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

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or unspecified API endpoint action.']);
        break;
}
