<?php
/**
 * WebCraft REST API Endpoints
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
                        $bText = !empty($block['brandText']) ? $block['brandText'] : 'WEBCRAFT';
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
                        $bText = !empty($block['brandText']) ? $block['brandText'] : 'WEBCRAFT BUILDER';
                        $logoHtml = '';
                        if (!empty($block['logoImg'])) {
                            $logoHtml = '<img src="' . sanitize_output($block['logoImg']) . '" class="h-8 max-w-[120px] object-contain" alt="Logo">';
                        } else {
                            $logoHtml = '<div class="text-lg font-black text-white">' . sanitize_output($bText) . '</div>';
                        }

                        $copyText = !empty($block['copyright']) ? $block['copyright'] : '&copy; ' . date('Y') . ' WebCraft. All rights reserved.';

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
        $zip_filename = tempnam(sys_get_temp_dir(), 'webcraft_export_') . '.zip';

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
