<?php
/**
 * WebCraft REST API v2.4
 *
 * Content-Type is set per-action, NOT globally, so export_zip can stream
 * a file without fighting a pre-set application/json header.
 */
define('WEBCRAFT_INCLUDED', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/render.php';

// All non-export actions return JSON; export_zip sets its own headers.
// We determine the action early so we only set JSON headers when needed.
$action  = $_GET['action'] ?? '';
$input   = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true) ?? [];
    if (empty($action)) $action = $input['action'] ?? '';
}

// Resolve short aliases
$aliases = ['save'=>'save_project','publish'=>'publish_project','delete'=>'delete_project','load'=>'load_project','export'=>'export_zip'];
if (isset($aliases[$action])) $action = $aliases[$action];

// Set JSON content-type for every action EXCEPT export_zip (which streams a file)
if ($action !== 'export_zip') {
    header('Content-Type: application/json; charset=UTF-8');
}

if (!is_logged_in()) {
    if ($action !== 'export_zip') {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized. Please login first.']);
    } else {
        http_response_code(401);
        echo 'Unauthorized.';
    }
    exit;
}

$db      = get_db_connection();
$user_id = $_SESSION['user_id'];

// ── Helpers ────────────────────────────────────────────────────

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
        http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit;
    }
}

function require_get(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit;
    }
}

function make_slug(string $name): string {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
    return $slug ?: 'project-' . time();
}

function unique_slug($db, int $user_id, string $slug, ?int $exclude_id = null): string {
    $sql    = 'SELECT id FROM projects WHERE user_id = ? AND slug = ?';
    $params = [$user_id, $slug];
    if ($exclude_id) { $sql .= ' AND id != ?'; $params[] = $exclude_id; }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetch()) $slug .= '-' . rand(100, 999);
    return $slug;
}

// ── Router ─────────────────────────────────────────────────────

switch ($action) {

    case 'save_project':
        require_post();
        if (!csrf_check($input)) { http_response_code(403); echo json_encode(['error'=>'Invalid CSRF token.']); exit; }
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
            if (empty($name)) { http_response_code(400); echo json_encode(['error'=>'Project name is required.']); exit; }
            $slug = unique_slug($db, $user_id, make_slug($name));
            try {
                $db->prepare('INSERT INTO projects (user_id,name,slug,description,content_json,status) VALUES (?,?,?,?,?,\'draft\')')
                   ->execute([$user_id,$name,$slug,$description,$to_store]);
                echo json_encode(['success'=>true,'message'=>'Project created.','project_id'=>$db->lastInsertId(),'slug'=>$slug]);
            } catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
        } else {
            $project = check_project_ownership($db, $project_id, $user_id);
            if (!$project) { http_response_code(404); echo json_encode(['error'=>'Project not found or unauthorized.']); exit; }
            $new_name = !empty($name) ? $name : $project['name'];
            $slug     = unique_slug($db, $user_id, make_slug($new_name), $project_id);
            try {
                $db->prepare('UPDATE projects SET name=?,slug=?,description=?,content_json=?,updated_at=NOW() WHERE id=?')
                   ->execute([$new_name,$slug,$description ?: $project['description'],$to_store,$project_id]);
                try { $db->prepare('INSERT INTO page_versions (project_id,schema_json,status,created_by) VALUES (?,?,\'draft\',?)')->execute([$project_id,$to_store,$user_id]); } catch (PDOException $ve) {}
                echo json_encode(['success'=>true,'message'=>'Draft saved.','project_id'=>$project_id,'slug'=>$slug]);
            } catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
        }
        break;

    case 'publish_project':
        require_post();
        if (!csrf_check($input)) { http_response_code(403); echo json_encode(['error'=>'Invalid CSRF token.']); exit; }
        $project_id = (int)($input['project_id'] ?? 0);
        if (!$project_id) { http_response_code(400); echo json_encode(['error'=>'Missing project ID.']); exit; }
        $project = check_project_ownership($db, $project_id, $user_id);
        if (!$project) { http_response_code(404); echo json_encode(['error'=>'Project not found or unauthorized.']); exit; }
        $schema   = $input['schema'] ?? null;
        $to_store = $schema !== null
            ? json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : $project['content_json'];
        try {
            $db->prepare('UPDATE projects SET content_json=?,status=\'published\',updated_at=NOW() WHERE id=?')->execute([$to_store,$project_id]);
            try { $db->prepare('INSERT INTO page_versions (project_id,schema_json,status,created_by) VALUES (?,?,\'published\',?)')->execute([$project_id,$to_store,$user_id]); } catch (PDOException $ve) {}
            echo json_encode([
                'success' => true,
                'message' => 'Site published successfully!',
                'url'     => 'render.php?slug=' . urlencode($project['slug']) . '&user=' . urlencode($_SESSION['username'] ?? '')
            ]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
        break;

    case 'delete_project':
        require_post();
        if (!csrf_check($input)) { http_response_code(403); echo json_encode(['error'=>'Invalid CSRF token.']); exit; }
        $project_id = (int)($input['project_id'] ?? 0);
        if (!$project_id) { http_response_code(400); echo json_encode(['error'=>'Missing project ID.']); exit; }
        $project = check_project_ownership($db, $project_id, $user_id);
        if (!$project) { http_response_code(404); echo json_encode(['error'=>'Project not found or unauthorized.']); exit; }
        try {
            $db->prepare('DELETE FROM projects WHERE id=?')->execute([$project_id]);
            echo json_encode(['success'=>true,'message'=>'Project deleted.']);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
        break;

    case 'load_project':
        require_get();
        $project_id = (int)($_GET['project_id'] ?? 0);
        if (!$project_id) { http_response_code(400); echo json_encode(['error'=>'Missing project ID.']); exit; }
        $project = check_project_ownership($db, $project_id, $user_id);
        if (!$project) { http_response_code(404); echo json_encode(['error'=>'Project not found or unauthorized.']); exit; }
        echo json_encode(['success'=>true,'project'=>[
            'id'=>$project['id'],'name'=>$project['name'],'slug'=>$project['slug'],
            'description'=>$project['description'],'status'=>$project['status'],
            'content_json'=>$project['content_json'],
            'schema'=>json_decode($project['content_json']??'{}',true),
            'updated_at'=>$project['updated_at']
        ]]);
        break;

    case 'list_versions':
        require_get();
        $project_id = (int)($_GET['project_id'] ?? 0);
        if (!$project_id) { http_response_code(400); echo json_encode(['error'=>'Missing project ID.']); exit; }
        if (!check_project_ownership($db,$project_id,$user_id)) { http_response_code(404); echo json_encode(['error'=>'Project not found.']); exit; }
        try {
            $stmt = $db->prepare('SELECT id,project_id,status,created_at FROM page_versions WHERE project_id=? ORDER BY id DESC LIMIT 30');
            $stmt->execute([$project_id]);
            echo json_encode(['success'=>true,'versions'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
        break;

    case 'restore_version':
        require_post();
        if (!csrf_check($input)) { http_response_code(403); echo json_encode(['error'=>'Invalid CSRF token.']); exit; }
        $project_id = (int)($input['project_id'] ?? 0);
        $version_id = (int)($input['version_id'] ?? 0);
        if (!$project_id || !$version_id) { http_response_code(400); echo json_encode(['error'=>'Missing project_id or version_id.']); exit; }
        $project = check_project_ownership($db, $project_id, $user_id);
        if (!$project) { http_response_code(404); echo json_encode(['error'=>'Project not found.']); exit; }
        try {
            $vstmt = $db->prepare('SELECT schema_json FROM page_versions WHERE id=? AND project_id=?');
            $vstmt->execute([$version_id,$project_id]);
            $ver = $vstmt->fetch();
            if (!$ver) { http_response_code(404); echo json_encode(['error'=>'Version not found.']); exit; }
            $db->prepare('UPDATE projects SET content_json=?,status=\'draft\',updated_at=NOW() WHERE id=?')->execute([$ver['schema_json'],$project_id]);
            echo json_encode(['success'=>true,'message'=>'Version restored as draft.','schema'=>json_decode($ver['schema_json'],true)]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
        break;

    case 'export_zip':
        // No JSON header here — we stream a file download
        if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
            http_response_code(403);
            header('Content-Type: text/plain');
            echo 'Invalid or expired CSRF token. Please reload the builder and try again.';
            exit;
        }
        $project_id = (int)($_GET['project_id'] ?? 0);
        if (!$project_id) { http_response_code(400); header('Content-Type: text/plain'); echo 'Missing project ID.'; exit; }
        $project = check_project_ownership($db, $project_id, $user_id);
        if (!$project) { http_response_code(404); header('Content-Type: text/plain'); echo 'Project not found.'; exit; }

        $schema    = json_decode($project['content_json'] ?? '{}', true) ?? [];
        $blocks    = $schema['blocks'] ?? (isset($schema[0]) ? $schema : []);
        $meta      = $schema['meta']   ?? [];
        $body_html = '';
        foreach ($blocks as $block) { if (is_array($block)) $body_html .= render_block($block); }

        $page_title = htmlspecialchars($project['name'], ENT_QUOTES);
        $custom_css = !empty($meta['custom_css']) ? $meta['custom_css'] : '';
        $custom_js  = !empty($meta['custom_js'])  ? '<script>' . $meta['custom_js'] . '</script>' : '';

        $full_html = <<<HTML
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$page_title}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body{font-family:ui-sans-serif,system-ui,sans-serif;background-color:#020617;}{$custom_css}</style>
</head>
<body class="min-h-screen">
<main id="wc-page">{$body_html}</main>
{$custom_js}
</body>
</html>
HTML;

        $filename = ($project['slug'] ?? 'export');

        // Try ZipArchive; fall back to plain .html download
        if (class_exists('ZipArchive')) {
            $zip_path = sys_get_temp_dir() . '/webcraft_' . uniqid() . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $zip->addFromString('index.html', $full_html);
                $zip->close();
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $filename . '-export.zip"');
                header('Content-Length: ' . filesize($zip_path));
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
                readfile($zip_path);
                @unlink($zip_path);
                exit;
            }
        }

        // Fallback: plain HTML file download
        $html_bytes = strlen($full_html);
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.html"');
        header('Content-Length: ' . $html_bytes);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo $full_html;
        exit;

    case 'upload_asset':
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { http_response_code(403); echo json_encode(['error'=>'Invalid CSRF token.']); exit; }
        $project_id = (int)($_POST['project_id'] ?? 0);
        if (!$project_id || !check_project_ownership($db,$project_id,$user_id)) { http_response_code(400); echo json_encode(['error'=>'Invalid project.']); exit; }
        if (empty($_FILES['file'])) { http_response_code(400); echo json_encode(['error'=>'No file uploaded.']); exit; }
        $file    = $_FILES['file'];
        $allowed = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
        $mime    = mime_content_type($file['tmp_name']);
        if (!in_array($mime,$allowed)) { http_response_code(400); echo json_encode(['error'=>'Invalid file type.']); exit; }
        if ($file['size'] > 5*1024*1024) { http_response_code(400); echo json_encode(['error'=>'File exceeds 5 MB.']); exit; }
        $ext        = strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
        $safe_name  = uniqid('asset_',true) . '.' . $ext;
        $upload_dir = __DIR__ . '/uploads/' . $project_id . '/';
        if (!is_dir($upload_dir)) mkdir($upload_dir,0755,true);
        if (!move_uploaded_file($file['tmp_name'],$upload_dir.$safe_name)) { http_response_code(500); echo json_encode(['error'=>'Upload failed.']); exit; }
        $public_url = 'uploads/' . $project_id . '/' . $safe_name;
        try { $db->prepare('INSERT INTO project_assets (project_id,uploaded_by,filename,file_url,mime_type,file_size) VALUES (?,?,?,?,?,?)')->execute([$project_id,$user_id,$file['name'],$public_url,$mime,$file['size']]); } catch (PDOException $e) {}
        echo json_encode(['success'=>true,'url'=>$public_url,'filename'=>$safe_name]);
        break;

    case 'list_assets':
        require_get();
        $project_id = (int)($_GET['project_id'] ?? 0);
        if (!$project_id || !check_project_ownership($db,$project_id,$user_id)) { http_response_code(400); echo json_encode(['error'=>'Invalid project.']); exit; }
        try {
            $stmt = $db->prepare('SELECT id,filename,file_url,mime_type,file_size,created_at FROM project_assets WHERE project_id=? ORDER BY id DESC');
            $stmt->execute([$project_id]);
            echo json_encode(['success'=>true,'assets'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error'=>'Invalid action.','available_actions'=>['save_project','publish_project','delete_project','load_project','list_versions','restore_version','export_zip','upload_asset','list_assets']]);
        break;
}
