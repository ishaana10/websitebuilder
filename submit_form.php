<?php
/**
 * Public Contact Form Handler
 * Processes customer inputs, saves them securely, and simulates SMTP notification emails
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$db = get_db_connection();

$project_id = (int)($_POST['project_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid project context.']);
    exit;
}

if (empty($name) || empty($email) || empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email address.']);
    exit;
}

// 1. Resolve project and owner email to notify them
$stmt_proj = $db->prepare("
    SELECT projects.name AS project_name, users.email AS owner_email, users.username AS owner_name
    FROM projects
    JOIN users ON projects.user_id = users.id
    WHERE projects.id = ?
");
$stmt_proj->execute([$project_id]);
$project_info = $stmt_proj->fetch();

if (!$project_info) {
    http_response_code(404);
    echo json_encode(['error' => 'Associated project details not found.']);
    exit;
}

try {
    $db->beginTransaction();

    // 2. Insert secure contact submission entry
    $stmt_insert = $db->prepare("INSERT INTO contact_submissions (project_id, name, email, message) VALUES (?, ?, ?, ?)");
    $stmt_insert->execute([$project_id, $name, $email, $message]);
    $submission_id = $db->lastInsertId();

    // 3. Prepare Simulated SMTP Email Notification Details
    $recipient = $project_info['owner_email'];
    $subject = "WebCraft Alert: New Contact Submission on [" . $project_info['project_name'] . "]";
    $body = "Hello " . $project_info['owner_name'] . ",\n\n" .
            "You received a new message from a site visitor on your WebCraft page:\n\n" .
            "Name: " . $name . "\n" .
            "Email: " . $email . "\n" .
            "Message: " . $message . "\n\n" .
            "Regards,\nWebCraft Automated Engine";

    // Insert Simulated Email Log Entry
    $stmt_email = $db->prepare("INSERT INTO email_logs (submission_id, recipient, subject, body, status) VALUES (?, ?, ?, ?, 'sent')");
    $stmt_email->execute([$submission_id, $recipient, $subject, $body]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Your message has been delivered. Site administrator has been notified via simulated SMTP email dispatch!'
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Server failed to process submission: ' . $e->getMessage()]);
}
