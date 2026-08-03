<?php
/**
 * Public Contact Form Handler
 * Processes customer inputs, saves them securely, and simulates SMTP notification emails
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/EmailService.php';

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
    SELECT projects.name AS project_name, projects.content_json, users.email AS owner_email, users.username AS owner_name
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

// Extract global email settings as the default configurations
$custom_recipient = '';
$auto_responder_enabled = false;
$template_theme = 'modern_minimalist';
$auto_responder_subject = 'Thank you for contacting us!';
$auto_responder_body = "Hello!\n\nWe have received your inquiry regarding our services and will get back to you shortly.\n\nBest regards,\nThe Team";

try {
    $stmt_global = $db->query("SELECT * FROM email_settings LIMIT 1");
    $global_email = $stmt_global->fetch();
    if ($global_email) {
        $custom_recipient = trim($global_email['recipient_email'] ?? '');
        $auto_responder_enabled = !empty($global_email['auto_responder_enabled']);
        $template_theme = $global_email['template_theme'] ?? 'modern_minimalist';
        $auto_responder_subject = $global_email['auto_responder_subject'] ?? $auto_responder_subject;
        $auto_responder_body = $global_email['auto_responder_body'] ?? $auto_responder_body;
    }
} catch (Exception $e) {
    error_log("Failed to load global email settings: " . $e->getMessage());
}

// Fallback to project-level email settings if they exist
try {
    $content_json = json_decode($project_info['content_json'] ?? '[]', true);
    if ($content_json && isset($content_json['email_settings']) && !empty($content_json['email_settings']['recipient'])) {
        $email_settings = $content_json['email_settings'];
        $custom_recipient = trim($email_settings['recipient'] ?? $custom_recipient);
        $auto_responder_enabled = !empty($email_settings['auto_responder_enabled']);
        $template_theme = $email_settings['template_theme'] ?? $template_theme;
        $auto_responder_subject = $email_settings['auto_responder_subject'] ?? $auto_responder_subject;
        $auto_responder_body = $email_settings['auto_responder_body'] ?? $auto_responder_body;
    }
} catch (Exception $e) {
    error_log("Failed to parse project email configurations: " . $e->getMessage());
}

try {
    $db->beginTransaction();

    // 2. Insert secure contact submission entry
    $stmt_insert = $db->prepare("INSERT INTO contact_submissions (project_id, name, email, message) VALUES (?, ?, ?, ?)");
    $stmt_insert->execute([$project_id, $name, $email, $message]);
    $submission_id = $db->lastInsertId();

    // 3. Resolve Destination notification recipient
    $recipient = !empty($custom_recipient) ? $custom_recipient : $project_info['owner_email'];

    // 4. Construct beautiful notification template for administrator
    $admin_subject = "Nuvis Webbuilder Alert: New Contact Submission on [" . $project_info['project_name'] . "]";
    $admin_content = "Hello " . $project_info['owner_name'] . ",\n\n" .
                     "You received a new message from a site visitor on your Nuvis Webbuilder page:\n\n" .
                     "Name: " . $name . "\n" .
                     "Email: " . $email . "\n" .
                     "Message: " . $message;

    $admin_html_body = EmailService::getTemplate($template_theme, $admin_subject, $admin_content, "Securely processed by Nuvis Webbuilder Notification Module.");

    // Send and Log Administrator notification alert
    EmailService::send($recipient, $admin_subject, $admin_html_body, $admin_content, $submission_id);

    // 5. Send automated HTML email response template back to the customer if configured
    if ($auto_responder_enabled) {
        $customer_subject = $auto_responder_subject;
        $customer_content = "Dear " . $name . ",\n\n" . $auto_responder_body;

        $customer_html_body = EmailService::getTemplate($template_theme, $customer_subject, $customer_content, "Sent on behalf of " . $project_info['project_name']);

        // Dispatch auto-responder
        EmailService::send($email, $customer_subject, $customer_html_body, $customer_content, $submission_id);
    }

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
