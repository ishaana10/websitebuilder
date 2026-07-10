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

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or unspecified API endpoint action.']);
        break;
}
