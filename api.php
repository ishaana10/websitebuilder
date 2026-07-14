<?php
/**
 * WebCraft REST API  v2.1
 * Actions: save_project, publish_project, delete_project, load_project, export_zip
 * Legacy aliases: save, publish, delete, load, export
 *
 * ob_start() at the very top captures any stray PHP warnings/notices/output
 * that would otherwise corrupt the JSON response.
 */
ob_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/render.php';

// Discard anything that config.php / render.php may have printed (shouldn't happen, but safety net)
ob_end_clean();

// From this point on, only our own echo statements go to the client.
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please login first.']);
    exit;
}

$db      = get_db_connection();
$user_id = $_SESSION['user_id'];
$input   = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Helpers ───────────────────────────────────────────────────────────────────

function check_project_ownership($db, $project_id, $user_id) {
    $stmt = $db->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
    $stmt->execute([$project_id, $user_id]);
    return $stmt->fetch();
}

function csrf_check(array $input): bool {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
    return verify_csrf_token($token);
}

function require_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        exit;
    }
}

function require_get(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        exit;
    }
}

/**
 * Compile a v2 schema (blocks[]) to HTML using the server-side renderers in render.php.
 */
function compile_schema_to_html(array $schema): string {
    if (isset($schema['blocks']) && is_array($schema['blocks'])) {
        $html = '';
        foreach ($schema['blocks'] as $block) {
            if (is_array($block)) $html .= render_block($block);
        }
        return $html;
    }
    return '';
}

function make_slug(string $name): string {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
    return $slug ?: 'project-' . time();
}

function unique_slug($db, int $user_id, string $slug, ?int $exclude_id = null): string {
    $sql = 'SELECT id FROM projects WHERE user_id = ? AND slug = ?';
    $params = [$user_id, $slug];
    if ($exclude_id) { $sql .= ' AND id != ?'; $params[] = $exclude_id; }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetch()) {
        $slug .= '-' . rand(100, 999);
    }
    return $slug;
}

// ── Router ────────────────────────────────────────────────────────────────────

$action = $_GET['action'] ?? ($input['action'] ?? '');

$aliases = ['save' => 'save_project', 'publish' => 'publish_project', 'delete' => 'delete_project', 'load' => 'load_project', 'export' => 'export_zip'];
if (isset($aliases[$action])) $action = $aliases[$action];

switch ($action) {

    // ══════════════════════════════════════════════════════════════════════════
    // SAVE PROJECT (draft)
    // ══════════════════════════════════════════════════════════════════════════
    case 'save_project':
        require_post();
        if (!csrf_check($input)) { http_response_code(403); echo json_encode(['error' => 'Invalid CSRF token.']); exit; }

        $project_id       = (int)($input['project_id'] ?? 0) ?: null;
        $schema           = $input['schema'] ?? null;
        $content_json_raw = $input['content_json'] ?? null;

        if ($schema !== null) {
            $to_store = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $name     = trim($schema['meta']['title'] ?? ($input['name'] ?? ''));
        } else {
            $to_store = is_string($content_json_raw) ? $content_json_raw : json_encode($content_json_raw);
            $name     = trim($input['name'] ?? '');
        }

        $description = trim($input['description'] ?? ($schema['meta']['description'] ?? ''));

        if (!$project_id) {
            if (empty($name)) { http_response_code(400); echo json_encode(['error' => 'Project name is required.']); exit; }
            $slug = unique_slug($db, $user_id, make_slug($name));
            $stmt = $db->prepare('INSERT INTO projects (user_id, name, slug, description, content_json, status) VALUES (?, ?, ?, ?, ?, \'draft\')');
            try {
                $stmt->execute([$user_id, $name, $slug, $description, $to_store]);
                $new_id = $db->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Project created.', 'project_id' => $new_id, 'slug' => $slug]);
            } catch (PDOException $e) {
                http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
            }
        } else {
            $project = check_project_ownership($db, $project_id, $user_id);
            if (!$project) { http_response_code(404); echo json_encode(['error' => 'Project not found or unauthorized.']); exit; }

            $new_name = !empty($name) ? $name : $project['name'];
            $slug     = unique_slug($db, $user_id, make_slug($new_name), $project_id);

            $stmt = $db->prepare('UPDATE projects SET name = ?, slug = ?, description = ?, content_json = ?, updated_at = NOW() WHERE id = ?');
            try {
                $stmt->execute([$new_name, $slug, $description ?: $project['description'], $to_store, $project_id]);

                try {
                    $vstmt = $db->prepare('INSERT INTO page_versions (project_id, schema_json, status, created_by) VALUES (?, ?, \'draft\', ?)');
                    $vstmt->execute([$project_id, $to_store, $user_id]);
                } catch (PDOException $ve) { /* non-fatal */ }

                echo json_encode(['success' => true, 'message' => 'Draft saved.', 'project_id' => $project_id, 'slug' => $slug]);
            } catch (PDOException $e) {
                http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
            }
        }
        break;

    // ══════════════════════════════════════════════════════════════════════════
    // PUBLISH PROJECT
    // ══════════════════════════════════════════════════════════════════════════
    case 'publish_project':
        require_post();
        if (!csrf_check($input)) { http_response_code(403); echo json_encode(['error' => 'Invalid CSRF token.']); exit; }

        $project_id = (int)($input['project_id'] ?? 0);
        if (!$project_id) { http_response_code(400); echo json_encode(['error' => 'Missing project ID.']); exit; }

        $project = check_project_ownership($db, $project_id, $user_id);
        if (!$project) { http_response_code(404); echo json_encode(['error' => 'Project not found or unauthorized.']); exit; }

        $schema = $input['schema'] ?? null;

        if ($schema !== null) {
            $to_store = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $to_store = $project['content_json'];
            $schema   = json_decode($to_store, true) ?? [];
        }

        $stmt = $db->prepare('UPDATE projects SET content_json = ?, status = \'published\', updated_at = NOW() WHERE id = ?');
        try {
            $stmt->execute([$to_store, $project_id]);

            try {
                $vstmt = $db->prepare('INSERT INTO page_versions (project_id, schema_json, status, created_by) VALUES (?, ?, \'published\', ?)');
                $vstmt->execute([$project_id, $to_store, $user_id]);
            } catch (PDOException $ve) { /* non-fatal */ }

            $render_url = 'render.php?slug=' . urlencode($project['slug']) . '&user=' . urlencode($_SESSION['username']);
            echo json_encode([
                'success' => true,
                'message' => 'Site published successfully!',
                'url'     => $render_url
            ]);
        } catch (PDOException $e) {
            http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    // ══════════════════════════════════════════════════════════════════════════
    // DELETE PROJECT
    // ══════════════════════════════════════════════════════════════════════════
    case 'delete_project':
        require_post();
        if (!csrf_check($input)) { http_response_code(403); echo json_encode(['error' => 'Invalid CSRF token.']); exit; }

        $project_id = (int)($input['project_id'] ?? 0);
        if (!$project_id) { http_response_code(400); echo json_encode(['error' => 'Missing project ID.']); exit; }

        $project = check_project_ownership($db, $project_id, $user_id);
        if (!$project) { http_response_code(404); echo json_encode(['error' => 'Project not found or unauthorized.']); exit; }

        try {
            $db->prepare('DELETE FROM projects WHERE id = ?')->execute([$project_id]);
            echo json_encode(['success' => true, 'message' => 'Project deleted.']);
        } catch (PDOException $e) {
            http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    // ══════════════════════════════════════════════════════════════════════════
    // LOAD PROJECT
    // ══════════════════════════════════════════════════════════════════════════
    case 'load_project':
        require_get();
        $project_id = (int)($_GET['project_id'] ?? 0);
        if (!$project_id) { http_response_code(400); echo json_encode(['error' => 'Missing project ID.']); exit; }

        $project = check_project_ownership($db, $project_id, $user_id);
        if (!$project) { http_response_code(404); echo json_encode(['error' => 'Project not found or unauthorized.']); exit; }

        $schema = json_decode($project['content_json'] ?? '{}', true);
        echo json_encode([
            'success' => true,
            'project' => [
                'id'           => $project['id'],
                'name'         => $project['name'],
                'slug'         => $project['slug'],
                'description'  => $project['description'],
                'status'       => $project['status'],
                'content_json' => $project['content_json'],
                'schema'       => $schema,
                'updated_at'   => $project['updated_at'],
            ]
        ]);
        break;

    // ══════════════════════════════════════════════════════════════════════════
    // LIST VERSIONS
    // ══════════════════════════════════════════════════════════════════════════
    case 'list_versions':
        require_get();
        $project_id = (int)($_GET['project_id'] ?? 0);
        if (!$project_id) { http_response_code(400); echo json_encode(['error' => 'Missing project ID.']); exit; }
        if (!check_project_ownership($db, $project_id, $user_id)) { http_response_code(404); echo json_encode(['error' => 'Project not found or unauthorized.']); exit; }

        try {
            $stmt = $db->prepare('SELECT id, project_id, status, created_at FROM page_versions WHERE project_id = ? ORDER BY id DESC LIMIT 30');
            $stmt->execute([$project_id]);
            echo json_encode(['success' => true, 'versions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    // ══════════════════════════════════════════════════════════════════════════
    // RESTORE VERSION
    // ══════════════════════════════════════════════════════════════════════════
    case 'restore_version':
        require_post();
        if (!csrf_check($input)) { http_response_code(403); echo json_encode(['error' => 'Invalid CSRF token.']); exit; }

        $project_id = (int)($input['project_id'] ?? 0);
        $version_id = (int)($input['version_id'] ?? 0);
        if (!$project_id || !$version_id) { http_response_code(400); echo json_encode(['error' => 'Missing project_id or version_id.']); exit; }

        $project = check_project_ownership($db, $project_id, $user_id);
        if (!$project) { http_response_code(404); echo json_encode(['error' => 'Project not found.']); exit; }

        try {
            $vstmt = $db->prepare('SELECT schema_json FROM page_versions WHERE id = ? AND project_id = ?');
            $vstmt->execute([$version_id, $project_id]);
            $ver = $vstmt->fetch();
            if (!$ver) { http_response_code(404); echo json_encode(['error' => 'Version not found.']); exit; }

            $db->prepare('UPDATE projects SET content_json = ?, status = \'draft\', updated_at = NOW() WHERE id = ?')
               ->execute([$ver['schema_json'], $project_id]);

            echo json_encode(['success' => true, 'message' => 'Version restored as draft.', 'schema' => json_decode($ver['schema_json'], true)]);
        } catch (PDOException $e) {
            http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    // ══════════════════════════════════════════════════════════════════════════
    // EXPORT ZIP
    // ══════════════════════════════════════════════════════════════════════════
    case 'export_zip':
        require_get();
        $csrf = $_GET['csrf_token'] ?? '';
        if (!verify_csrf_token($csrf)) { http_response_code(403); echo json_encode(['error' => 'Invalid CSRF token.']); exit; }

        $project_id = (int)($_GET['project_id'] ?? 0);
        if (!$project_id) { http_response_code(400); echo json_encode(['error' => 'Missing project ID.']); exit; }

        $project = check_project_ownership($db, $project_id, $user_id);
        if (!$project) { http_response_code(404); echo json_encode(['error' => 'Project not found or unauthorized.']); exit; }

        $schema     = json_decode($project['content_json'] ?? '{}', true) ?? [];
        $blocks     = $schema['blocks'] ?? (is_array($schema) && isset($schema[0]) ? $schema : []);
        $meta       = $schema['meta']   ?? [];
        $custom_css = $meta['custom_css'] ?? '';
        $custom_js  = $meta['custom_js']  ?? '';

        $body_html = '';
        foreach ($blocks as $block) {
            if (is_array($block)) $body_html .= render_block($block);
        }

        $full_html = '<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($project['name'], ENT_QUOTES) . '</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family: ui-sans-serif, system-ui, sans-serif; background-color: #020617; }' . "\n" . htmlspecialchars($custom_css, ENT_NOQUOTES) . '</style>
</head>
<body class="min-h-screen">
    <main id="wc-page">' . $body_html . '</main>
    <script>const PROJECT_ID = ' . (int)$project['id'] . ';</script>' .
    (!empty($custom_js) ? '<script>' . htmlspecialchars($custom_js, ENT_NOQUOTES) . '</script>' : '') . '
</body>
</html>';

        $zip          = new ZipArchive();
        $zip_filename = tempnam(sys_get_temp_dir(), 'webcraft_export_') . '.zip';

        if ($zip->open($zip_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            http_response_code(500); echo json_encode(['error' => 'Could not create ZIP archive.']); exit;
        }

        $zip->addFromString('index.html', $full_html);
        $zip->close();

        header_remove();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $project['slug'] . '-export.zip"');
        header('Content-Length: ' . filesize($zip_filename));
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($zip_filename);
        unlink($zip_filename);
        exit;

    // ══════════════════════════════════════════════════════════════════════════
    // UPLOAD ASSET
    // ══════════════════════════════════════════════════════════════════════════
    case 'upload_asset':
        require_post();
        $csrf = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($csrf)) { http_response_code(403); echo json_encode(['error' => 'Invalid CSRF token.']); exit; }

        $project_id = (int)($_POST['project_id'] ?? 0);
        if (!$project_id) { http_response_code(400); echo json_encode(['error' => 'Missing project ID.']); exit; }
        if (!check_project_ownership($db, $project_id, $user_id)) { http_response_code(404); echo json_encode(['error' => 'Project not found.']); exit; }

        if (empty($_FILES['file'])) { http_response_code(400); echo json_encode(['error' => 'No file uploaded.']); exit; }

        $file      = $_FILES['file'];
        $allowed   = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $mime      = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowed)) { http_response_code(400); echo json_encode(['error' => 'Invalid file type.']); exit; }

        $max_bytes = 5 * 1024 * 1024;
        if ($file['size'] > $max_bytes) { http_response_code(400); echo json_encode(['error' => 'File exceeds 5 MB limit.']); exit; }

        $ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safe_name = uniqid('asset_', true) . '.' . strtolower($ext);
        $upload_dir = __DIR__ . '/uploads/' . $project_id . '/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        if (!move_uploaded_file($file['tmp_name'], $upload_dir . $safe_name)) {
            http_response_code(500); echo json_encode(['error' => 'Upload failed.']); exit;
        }

        $public_url = 'uploads/' . $project_id . '/' . $safe_name;

        try {
            $db->prepare('INSERT INTO project_assets (project_id, uploaded_by, filename, file_url, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([$project_id, $user_id, $file['name'], $public_url, $mime, $file['size']]);
        } catch (PDOException $e) { /* non-fatal */ }

        echo json_encode(['success' => true, 'url' => $public_url, 'filename' => $safe_name]);
        break;

    // ══════════════════════════════════════════════════════════════════════════
    // LIST ASSETS
    // ══════════════════════════════════════════════════════════════════════════
    case 'list_assets':
        require_get();
        $project_id = (int)($_GET['project_id'] ?? 0);
        if (!$project_id) { http_response_code(400); echo json_encode(['error' => 'Missing project ID.']); exit; }
        if (!check_project_ownership($db, $project_id, $user_id)) { http_response_code(404); echo json_encode(['error' => 'Project not found.']); exit; }

        try {
            $stmt = $db->prepare('SELECT id, filename, file_url, mime_type, file_size, created_at FROM project_assets WHERE project_id = ? ORDER BY id DESC');
            $stmt->execute([$project_id]);
            echo json_encode(['success' => true, 'assets' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or unspecified API action.', 'available_actions' => [
            'save_project', 'publish_project', 'delete_project', 'load_project',
            'list_versions', 'restore_version', 'export_zip', 'upload_asset', 'list_assets'
        ]]);
        break;
}
