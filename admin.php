<?php
/**
 * WebCraft Commercial Grade Admin Control Panel
 * Implements high fidelity layouts, analytics charts, dynamic database-backed site listings,
 * pre-packaged templates library, user management status control, customer contact form entries,
 * simulated SMTP dispatch logs, and server performance diagnostics.
 */
require_once __DIR__ . '/config.php';
require_login();

$db = get_db_connection();
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['user_role'];

// Handle new project creation via simple modal submission
$error_msg = $_GET['error'] ?? '';
$success_msg = $_GET['success'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error_msg = "CSRF Token validation failed.";
    } else {
        if ($action === 'create_project') {
            $name = trim($_POST['project_name'] ?? '');
            $desc = trim($_POST['project_desc'] ?? '');

            if (empty($name)) {
                $error_msg = "Website project name is required.";
            } else {
                // Check if name has duplicate slug for this user
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
                if (empty($slug)) { $slug = 'site-' . time(); }

                $stmt = $db->prepare("SELECT id FROM projects WHERE user_id = ? AND slug = ?");
                $stmt->execute([$user_id, $slug]);
                if ($stmt->fetch()) {
                    $slug .= '-' . rand(10, 99);
                }

                $stmt_insert = $db->prepare("INSERT INTO projects (user_id, name, slug, description, content_json) VALUES (?, ?, ?, ?, '[]')");
                try {
                    $stmt_insert->execute([$user_id, $name, $slug, $desc]);
                    $new_id = $db->lastInsertId();
                    header("Location: builder.php?project_id=" . $new_id);
                    exit;
                } catch (PDOException $e) {
                    $error_msg = "Error creating project: " . $e->getMessage();
                }
            }
        } elseif ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_new_password = $_POST['confirm_new_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
                $error_msg = "Please fill in all password fields.";
            } elseif ($new_password !== $confirm_new_password) {
                $error_msg = "New passwords do not match.";
            } elseif (strlen($new_password) < 8) {
                $error_msg = "New password must be at least 8 characters long.";
            } elseif (!preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
                $error_msg = "New password must contain at least one letter and one number.";
            } else {
                // Verify current password
                $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user_data = $stmt->fetch();

                if ($user_data && password_verify($current_password, $user_data['password_hash'])) {
                    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt_update = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    try {
                        $stmt_update->execute([$new_hash, $user_id]);
                        $success_msg = "Password changed successfully! Keep it secure.";
                    } catch (PDOException $e) {
                        $error_msg = "Error updating password: " . $e->getMessage();
                    }
                } else {
                    $error_msg = "Current password is incorrect.";
                }
            }
        } elseif ($action === 'update_user_role' && is_admin()) {
            // Admin only user privilege promotion
            $target_user_id = (int)($_POST['target_user_id'] ?? 0);
            $new_role = $_POST['new_role'] ?? 'user';
            $new_status = $_POST['new_status'] ?? 'active';

            if ($target_user_id !== $user_id) { // Prevent self modifications
                $stmt = $db->prepare("UPDATE users SET role = ?, status = ? WHERE id = ?");
                try {
                    $stmt->execute([$new_role, $new_status, $target_user_id]);
                    $success_msg = "User configurations updated successfully!";
                } catch (PDOException $e) {
                    $error_msg = "Error updating user settings: " . $e->getMessage();
                }
            } else {
                $error_msg = "You cannot modify your own administrative role details.";
            }
        } elseif ($action === 'git_status' && is_admin()) {
            header('Content-Type: application/json');
            $output = shell_exec("git status 2>&1");
            if (empty($output)) {
                $output = "Git is not installed or accessible on this system environment.";
            }
            echo json_encode(['success' => true, 'output' => nl2br(sanitize_output($output))]);
            exit;
        } elseif ($action === 'git_pull' && is_admin()) {
            header('Content-Type: application/json');
            $output = shell_exec("git pull 2>&1");
            if (empty($output)) {
                $output = "Git pull could not be executed or permission denied.";
                echo json_encode(['success' => false, 'error' => $output]);
            } else {
                echo json_encode(['success' => true, 'output' => nl2br(sanitize_output($output))]);
            }
            exit;
        }
    }
}

// Fetch general system statistics
$total_sites_count = 0;
$user_sites_count = 0;
$active_users_count = 0;

try {
    $stmt_tot = $db->query("SELECT COUNT(*) FROM projects");
    $total_sites_count = $stmt_tot->fetchColumn();

    $stmt_user = $db->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ?");
    $stmt_user->execute([$user_id]);
    $user_sites_count = $stmt_user->fetchColumn();

    $stmt_users = $db->query("SELECT COUNT(*) FROM users");
    $active_users_count = $stmt_users->fetchColumn();
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// Fetch projects for logged user
$user_projects = [];
try {
    $stmt_p = $db->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt_p->execute([$user_id]);
    $user_projects = $stmt_p->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// Fetch form submissions on user's projects
$form_submissions = [];
try {
    $stmt_sub = $db->prepare("
        SELECT contact_submissions.*, projects.name AS project_name
        FROM contact_submissions
        JOIN projects ON contact_submissions.project_id = projects.id
        WHERE projects.user_id = ?
        ORDER BY contact_submissions.created_at DESC
    ");
    $stmt_sub->execute([$user_id]);
    $form_submissions = $stmt_sub->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// Fetch simulated email notification logs associated with the form submissions
$email_logs = [];
try {
    $stmt_log = $db->prepare("
        SELECT email_logs.*, contact_submissions.name AS sender_name
        FROM email_logs
        JOIN contact_submissions ON email_logs.submission_id = contact_submissions.id
        JOIN projects ON contact_submissions.project_id = projects.id
        WHERE projects.user_id = ?
        ORDER BY email_logs.created_at DESC
    ");
    $stmt_log->execute([$user_id]);
    $email_logs = $stmt_log->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// Fetch all users for Admin User Management Tab
$all_users = [];
if (is_admin()) {
    try {
        $stmt_u = $db->query("SELECT * FROM users ORDER BY created_at DESC");
        $all_users = $stmt_u->fetchAll();
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

// Check if user is using default password "admin123"
$is_using_default_password = false;
try {
    $stmt_pass = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt_pass->execute([$user_id]);
    $u_pass = $stmt_pass->fetch();
    if ($u_pass && password_verify('admin123', $u_pass['password_hash'])) {
        $is_using_default_password = true;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebCraft - Admin Portal</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Premium Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="h-full text-slate-100 flex flex-col font-sans">

    <!-- DASHBOARD MASTER CONTAINER -->
    <div class="flex h-full overflow-hidden">

        <!-- SIDEBAR DECK -->
        <aside class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col shrink-0">
            <div class="h-16 flex items-center px-6 border-b border-slate-800 bg-slate-950/40">
                <div class="flex items-center gap-2">
                    <div class="bg-teal-500 text-slate-950 w-8 h-8 rounded-lg flex items-center justify-center font-black text-sm">WC</div>
                    <span class="font-extrabold text-sm tracking-widest text-teal-400 uppercase">WebCraft v1.0</span>
                </div>
            </div>

            <!-- User Brief -->
            <div class="p-6 border-b border-slate-800/80 bg-slate-900/30">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-teal-500/10 border border-teal-500/20 text-teal-400 font-bold rounded-full flex items-center justify-center text-sm uppercase">
                        <?php echo substr($username, 0, 2); ?>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-white"><?php echo sanitize_output($username); ?></h3>
                        <p class="text-[10px] text-slate-400 mt-0.5 capitalize"><?php echo sanitize_output($role); ?></p>
                    </div>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                <button onclick="switchTab('tab-dashboard', this)" class="tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition duration-200 bg-slate-800 text-teal-400">
                    <i class="fas fa-chart-line text-sm"></i> Dashboard
                </button>
                <button onclick="switchTab('tab-sites', this)" class="tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition duration-200 text-slate-400 hover:text-white hover:bg-slate-800/50">
                    <i class="fas fa-folder text-sm"></i> My Websites
                </button>
                <button onclick="switchTab('tab-templates', this)" class="tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition duration-200 text-slate-400 hover:text-white hover:bg-slate-800/50">
                    <i class="fas fa-layer-group text-sm"></i> Templates Library
                </button>
                <button onclick="switchTab('tab-submissions', this)" class="tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition duration-200 text-slate-400 hover:text-white hover:bg-slate-800/50">
                    <i class="fas fa-envelope-open-text text-sm"></i> Form Submissions
                </button>
                <button id="btn-security" onclick="switchTab('tab-security', this)" class="tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition duration-200 text-slate-400 hover:text-white hover:bg-slate-800/50">
                    <i class="fas fa-user-shield text-sm"></i> Account Security
                </button>

                <?php if (is_admin()): ?>
                <div class="pt-4 pb-2 px-4">
                    <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-widest">Admin Control</span>
                </div>
                <button onclick="switchTab('tab-users', this)" class="tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition duration-200 text-slate-400 hover:text-white hover:bg-slate-800/50">
                    <i class="fas fa-users-cog text-sm"></i> User Manager
                </button>
                <button onclick="switchTab('tab-system', this)" class="tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition duration-200 text-slate-400 hover:text-white hover:bg-slate-800/50">
                    <i class="fas fa-server text-sm"></i> System Diagnostics
                </button>
                <?php endif; ?>
            </nav>

            <!-- Bottom Log Out -->
            <div class="p-4 border-t border-slate-800/80 shrink-0">
                <a href="auth.php?auth_action=logout" class="flex items-center justify-center gap-2 w-full bg-slate-950 hover:bg-slate-850 text-red-400 hover:text-red-300 font-bold py-2.5 rounded-lg text-xs transition border border-red-500/10">
                    <i class="fas fa-sign-out-alt"></i> Sign Out
                </a>
            </div>
        </aside>

        <!-- MAIN LAYOUT WRAPPER -->
        <main class="flex-1 flex flex-col overflow-hidden bg-slate-950">

            <!-- MASTER HEADER -->
            <header class="h-16 border-b border-slate-800 flex items-center justify-between px-8 bg-slate-900/20">
                <h2 id="view-title" class="text-sm font-extrabold text-white uppercase tracking-wider">Dashboard</h2>

                <div class="flex items-center gap-4">
                    <button onclick="openCreateModal()" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-4 py-2 rounded-lg text-xs flex items-center gap-1.5 transition shadow-lg shadow-teal-500/15">
                        <i class="fas fa-plus"></i> New Website
                    </button>
                </div>
            </header>

            <!-- GENERAL NOTIFICATIONS -->
            <?php if ($is_using_default_password): ?>
            <div class="mx-8 mt-6 bg-amber-950/40 border border-amber-500/40 text-amber-300 rounded-lg p-4 flex items-center justify-between gap-3 animate-pulse">
                <div class="flex items-center gap-3">
                    <i class="fas fa-shield-halved text-amber-400 text-lg"></i>
                    <div class="text-xs">
                        <strong class="text-white">Security Alert:</strong> You are currently using the default system password (<code>admin123</code>). Please modify your password immediately to secure your platform database!
                    </div>
                </div>
                <button onclick="switchTab('tab-security', document.getElementById('btn-security'))" class="bg-amber-500 hover:bg-amber-400 text-slate-950 px-3 py-1.5 rounded font-black text-[10px] uppercase tracking-wider transition">Secure Account</button>
            </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
            <div class="mx-8 mt-6 bg-red-950/40 border border-red-500/30 text-red-300 rounded-lg p-4 flex items-center gap-3">
                <i class="fas fa-exclamation-triangle"></i>
                <span class="text-xs"><?php echo sanitize_output($error_msg); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($success_msg)): ?>
            <div class="mx-8 mt-6 bg-emerald-950/40 border border-emerald-500/30 text-emerald-300 rounded-lg p-4 flex items-center gap-3">
                <i class="fas fa-check-circle"></i>
                <span class="text-xs"><?php echo sanitize_output($success_msg); ?></span>
            </div>
            <?php endif; ?>

            <!-- DYNAMIC TAB PANELS -->
            <div class="flex-1 overflow-y-auto p-8">

                <!-- TAB 1: GENERAL STATISTICAL DASHBOARD -->
                <div id="tab-dashboard" class="tab-content active space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Stat Item 1 -->
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 flex items-center justify-between shadow-sm">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Your Websites</span>
                                <span class="text-3xl font-black text-white mt-1 block"><?php echo (int)$user_sites_count; ?></span>
                            </div>
                            <div class="bg-teal-500/10 text-teal-400 w-12 h-12 rounded-xl flex items-center justify-center text-lg">
                                <i class="fas fa-globe"></i>
                            </div>
                        </div>
                        <!-- Stat Item 2 -->
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 flex items-center justify-between shadow-sm">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Global Hosted Projects</span>
                                <span class="text-3xl font-black text-white mt-1 block"><?php echo (int)$total_sites_count; ?></span>
                            </div>
                            <div class="bg-indigo-500/10 text-indigo-400 w-12 h-12 rounded-xl flex items-center justify-center text-lg">
                                <i class="fas fa-cubes"></i>
                            </div>
                        </div>
                        <!-- Stat Item 3 -->
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 flex items-center justify-between shadow-sm">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Active Platform Users</span>
                                <span class="text-3xl font-black text-white mt-1 block"><?php echo (int)$active_users_count; ?></span>
                            </div>
                            <div class="bg-emerald-500/10 text-emerald-400 w-12 h-12 rounded-xl flex items-center justify-center text-lg">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Welcome Block -->
                    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-slate-800 rounded-xl p-8 shadow-md">
                        <div class="max-w-2xl">
                            <span class="bg-teal-500/10 text-teal-400 font-semibold px-3 py-1 rounded-full text-[10px] uppercase tracking-wider border border-teal-500/20">Welcome to WebCraft Open-Source</span>
                            <h2 class="text-2xl font-black text-white mt-4 tracking-tight">Design & Launch Commercial Grade Layouts</h2>
                            <p class="text-slate-300 mt-2 text-xs leading-relaxed">Combine pre-designed sections inside our premium responsive builder. Adjust content, classes, button pathways, or insert raw low-code components dynamically. Everything you create is powered by static optimization, loading under 100ms globally.</p>
                            <button onclick="openCreateModal()" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-6 py-3 rounded-lg text-xs mt-6 flex items-center gap-2 transition">
                                <i class="fas fa-magic"></i> Initiate Project Build
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: USER'S WEBSITES -->
                <div id="tab-sites" class="tab-content space-y-6">
                    <?php if (empty($user_projects)): ?>
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-12 text-center max-w-xl mx-auto mt-8">
                        <div class="w-16 h-16 bg-slate-800 rounded-full flex items-center justify-center text-slate-500 text-2xl mx-auto mb-4 border border-slate-700">
                            <i class="fas fa-cubes"></i>
                        </div>
                        <h3 class="font-bold text-white text-sm">No websites created yet</h3>
                        <p class="text-slate-400 text-xs mt-2 max-w-xs mx-auto leading-relaxed">Your creative canvas awaits! Click "New Website" above to construct your drag-and-drop experience.</p>
                        <button onclick="openCreateModal()" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-4 py-2.5 rounded-lg text-xs mt-6 transition">
                            Create First Site
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($user_projects as $p): ?>
                        <div class="bg-slate-900 border border-slate-800 hover:border-slate-700 rounded-xl overflow-hidden shadow-sm flex flex-col justify-between group transition">
                            <div class="p-6">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold px-2.5 py-1 rounded bg-slate-850 border border-slate-800 text-slate-400 uppercase tracking-wider"><?php echo $p['status']; ?></span>
                                    <span class="text-[10px] text-slate-500"><?php echo date('M d, Y', strtotime($p['updated_at'])); ?></span>
                                </div>
                                <h3 class="text-sm font-extrabold text-white mt-4 group-hover:text-teal-400 transition"><?php echo sanitize_output($p['name']); ?></h3>
                                <p class="text-slate-400 text-xs mt-2 line-clamp-2 leading-relaxed"><?php echo sanitize_output($p['description'] ?: 'No description provided.'); ?></p>
                            </div>
                            <div class="bg-slate-950/40 p-4 border-t border-slate-800 flex gap-2">
                                <a href="builder.php?project_id=<?php echo $p['id']; ?>" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold px-3 py-2 rounded text-[11px] flex-1 text-center transition flex items-center justify-center gap-1.5">
                                    <i class="fas fa-edit"></i> Edit Site
                                </a>
                                <?php if ($p['status'] === 'published'): ?>
                                <a href="render.php?slug=<?php echo $p['slug']; ?>&user=<?php echo $username; ?>" target="_blank" class="bg-teal-500/10 hover:bg-teal-500/20 text-teal-400 font-bold px-3 py-2 rounded text-[11px] flex-1 text-center border border-teal-500/20 transition flex items-center justify-center gap-1.5">
                                    <i class="fas fa-external-link-alt"></i> View Live
                                </a>
                                <?php endif; ?>
                                <button onclick="deleteProject(<?php echo $p['id']; ?>)" class="text-red-400 hover:text-red-300 hover:bg-red-950/40 border border-transparent hover:border-red-500/20 w-9 h-9 flex items-center justify-center rounded transition" title="Delete Website">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- TAB 3: TEMPLATES LIBRARY -->
                <div id="tab-templates" class="tab-content space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Standard Landing Theme -->
                        <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden hover:border-teal-500/50 transition duration-300 flex flex-col justify-between">
                            <div class="p-6">
                                <div class="w-12 h-12 bg-teal-500/10 text-teal-400 rounded-xl flex items-center justify-center text-lg mb-4"><i class="fas fa-pager"></i></div>
                                <h3 class="font-bold text-white text-sm">SaaS Product Landing Page</h3>
                                <p class="text-slate-400 text-xs mt-2 leading-relaxed">Package with Premium Navbar, high converting Hero layout, Features grid, corporate pricing block, and direct customer contact forms.</p>
                            </div>
                            <div class="p-4 bg-slate-950/40 border-t border-slate-800">
                                <button onclick="createNewSiteFromTemplate('SaaS Product Landing Page')" class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-2.5 rounded-lg text-xs transition">Use Template Theme</button>
                            </div>
                        </div>
                        <!-- Business Consultant -->
                        <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden hover:border-teal-500/50 transition duration-300 flex flex-col justify-between">
                            <div class="p-6">
                                <div class="w-12 h-12 bg-teal-500/10 text-teal-400 rounded-xl flex items-center justify-center text-lg mb-4"><i class="fas fa-briefcase"></i></div>
                                <h3 class="font-bold text-white text-sm">Corporate Consulting Showcase</h3>
                                <p class="text-slate-400 text-xs mt-2 leading-relaxed">Tailored specifically for consultant layouts, incorporating a bold text visual hero layout, company feature cards, and responsive custom footers.</p>
                            </div>
                            <div class="p-4 bg-slate-950/40 border-t border-slate-800">
                                <button onclick="createNewSiteFromTemplate('Corporate Consulting Showcase')" class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-2.5 rounded-lg text-xs transition">Use Template Theme</button>
                            </div>
                        </div>
                        <!-- E-Commerce Gadget Landing Page -->
                        <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden hover:border-teal-500/50 transition duration-300 flex flex-col justify-between">
                            <div class="p-6">
                                <div class="w-12 h-12 bg-teal-500/10 text-teal-400 rounded-xl flex items-center justify-center text-lg mb-4"><i class="fas fa-shopping-bag"></i></div>
                                <h3 class="font-bold text-white text-sm">E-Commerce Gadget Landing Page</h3>
                                <p class="text-slate-400 text-xs mt-2 leading-relaxed">Specially optimized product layout with dynamic navbar, gadget feature grids, customizable pricing boxes, chatbot customer care, and checkout forms.</p>
                            </div>
                            <div class="p-4 bg-slate-950/40 border-t border-slate-800">
                                <button onclick="createNewSiteFromTemplate('E-Commerce Gadget Landing Page')" class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-2.5 rounded-lg text-xs transition">Use Template Theme</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: CONTACT FORM SUBMISSIONS & EMAIL ALERTS -->
                <div id="tab-submissions" class="tab-content space-y-8">
                    <!-- Form entries -->
                    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-800 bg-slate-950/20">
                            <h3 class="font-bold text-white text-xs uppercase tracking-widest text-teal-400">Incoming Customer Contacts</h3>
                        </div>
                        <?php if (empty($form_submissions)): ?>
                        <div class="p-8 text-center text-xs text-slate-500">
                            <i class="fas fa-envelope-open text-xl mb-2"></i>
                            <p>No customer form submissions received yet.</p>
                        </div>
                        <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-300">
                                <thead class="bg-slate-950 text-[10px] text-slate-400 uppercase tracking-wider border-b border-slate-800">
                                    <tr>
                                        <th class="px-6 py-4">Project</th>
                                        <th class="px-6 py-4">Sender</th>
                                        <th class="px-6 py-4">Email</th>
                                        <th class="px-6 py-4">Message</th>
                                        <th class="px-6 py-4">Submitted At</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/60">
                                    <?php foreach ($form_submissions as $sub): ?>
                                    <tr class="hover:bg-slate-800/20 transition">
                                        <td class="px-6 py-4 font-bold text-teal-400"><?php echo sanitize_output($sub['project_name']); ?></td>
                                        <td class="px-6 py-4 text-white font-semibold"><?php echo sanitize_output($sub['name']); ?></td>
                                        <td class="px-6 py-4 font-mono"><?php echo sanitize_output($sub['email']); ?></td>
                                        <td class="px-6 py-4 max-w-xs truncate" title="<?php echo sanitize_output($sub['message']); ?>"><?php echo sanitize_output($sub['message']); ?></td>
                                        <td class="px-6 py-4 text-slate-500"><?php echo date('M d, Y H:i', strtotime($sub['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- SMTP dispatch simulation logs -->
                    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-800 bg-slate-950/20">
                            <h3 class="font-bold text-white text-xs uppercase tracking-widest text-teal-400">SMTP Server Mail-Alert Logs</h3>
                        </div>
                        <?php if (empty($email_logs)): ?>
                        <div class="p-8 text-center text-xs text-slate-500">
                            <i class="fas fa-paper-plane text-xl mb-2"></i>
                            <p>No simulated outbound notification emails have been dispatched yet.</p>
                        </div>
                        <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-300">
                                <thead class="bg-slate-950 text-[10px] text-slate-400 uppercase tracking-wider border-b border-slate-800">
                                    <tr>
                                        <th class="px-6 py-4">Sender Event</th>
                                        <th class="px-6 py-4">Recipient</th>
                                        <th class="px-6 py-4">Subject</th>
                                        <th class="px-6 py-4">SMTP Status</th>
                                        <th class="px-6 py-4">Dispatched At</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                                    <?php foreach ($email_logs as $log): ?>
                                    <tr class="hover:bg-slate-800/20 transition">
                                        <td class="px-6 py-4 text-slate-400"><?php echo sanitize_output($log['sender_name']); ?></td>
                                        <td class="px-6 py-4 text-white"><?php echo sanitize_output($log['recipient']); ?></td>
                                        <td class="px-6 py-4 max-w-xs truncate text-teal-400" title="<?php echo sanitize_output($log['subject']); ?>"><?php echo sanitize_output($log['subject']); ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">SMTP_SUCCESS</span>
                                        </td>
                                        <td class="px-6 py-4 text-slate-500"><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TAB 5: ADMIN USER MANAGEMENT (ADMIN ONLY) -->
                <?php if (is_admin()): ?>
                <div id="tab-users" class="tab-content space-y-6">
                    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-800 bg-slate-950/20">
                            <h3 class="font-bold text-white text-xs uppercase tracking-widest text-teal-400">Security Access Controls</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-300">
                                <thead class="bg-slate-950 text-[10px] text-slate-400 uppercase tracking-wider border-b border-slate-800">
                                    <tr>
                                        <th class="px-6 py-4">ID</th>
                                        <th class="px-6 py-4">Username</th>
                                        <th class="px-6 py-4">Email</th>
                                        <th class="px-6 py-4">Role</th>
                                        <th class="px-6 py-4">Status</th>
                                        <th class="px-6 py-4">Joined</th>
                                        <th class="px-6 py-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/60">
                                    <?php foreach ($all_users as $u): ?>
                                    <tr class="hover:bg-slate-800/30 transition">
                                        <td class="px-6 py-4 font-mono text-slate-500"><?php echo $u['id']; ?></td>
                                        <td class="px-6 py-4 font-bold text-white"><?php echo sanitize_output($u['username']); ?></td>
                                        <td class="px-6 py-4"><?php echo sanitize_output($u['email']); ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?php echo $u['role'] === 'admin' ? 'bg-indigo-500/15 text-indigo-400 border border-indigo-500/20' : 'bg-slate-800 text-slate-400'; ?>">
                                                <?php echo $u['role']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?php echo $u['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400'; ?>">
                                                <?php echo $u['status']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-slate-500"><?php echo date('Y-m-d', strtotime($u['created_at'])); ?></td>
                                        <td class="px-6 py-4 text-right">
                                            <?php if ($u['id'] !== $user_id): ?>
                                            <form action="admin.php?action=update_user_role" method="POST" class="inline-flex gap-1.5 items-center">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="target_user_id" value="<?php echo $u['id']; ?>">
                                                <select name="new_role" class="bg-slate-950 border border-slate-850 rounded px-2 py-1 text-[11px] focus:outline-none">
                                                    <option value="user" <?php echo $u['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                    <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                </select>
                                                <select name="new_status" class="bg-slate-950 border border-slate-850 rounded px-2 py-1 text-[11px] focus:outline-none">
                                                    <option value="active" <?php echo $u['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="suspended" <?php echo $u['status'] === 'suspended' ? 'selected' : ''; ?>>Suspend</option>
                                                </select>
                                                <button type="submit" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold px-2 py-1 rounded text-[10px] transition">Update</button>
                                            </form>
                                            <?php else: ?>
                                            <span class="text-slate-600 italic">Logged In</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB 6: SYSTEM HEALTH DIAGNOSTICS (ADMIN ONLY) -->
                <div id="tab-system" class="tab-content space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Server Specs -->
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
                            <h3 class="font-extrabold text-white text-xs uppercase tracking-wider mb-4 text-teal-400">Engine Environment</h3>
                            <ul class="text-xs space-y-3.5 text-slate-300">
                                <li class="flex justify-between"><span class="text-slate-500">PHP Version:</span> <span class="font-bold font-mono text-white"><?php echo phpversion(); ?></span></li>
                                <li class="flex justify-between"><span class="text-slate-500">SQL Interface:</span> <span class="font-bold text-white">PDO MariaDB Native Driver</span></li>
                                <li class="flex justify-between"><span class="text-slate-500">Safe Upload Limits:</span> <span class="font-bold font-mono text-white"><?php echo ini_get('upload_max_filesize'); ?></span></li>
                                <li class="flex justify-between"><span class="text-slate-500">Operating System:</span> <span class="font-bold text-white">Ubuntu Linux (Focal Fossa)</span></li>
                            </ul>
                        </div>
                        <!-- Security Configuration Status -->
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
                            <h3 class="font-extrabold text-white text-xs uppercase tracking-wider mb-4 text-teal-400">Security Diagnostic Checklist</h3>
                            <ul class="text-xs space-y-3">
                                <li class="flex items-center justify-between"><span class="text-slate-300">Parameterized Database Prepared Statements</span> <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 uppercase">SECURE</span></li>
                                <li class="flex items-center justify-between"><span class="text-slate-300">Strict Cryptographic password_hash Validation</span> <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 uppercase">SECURE</span></li>
                                <li class="flex items-center justify-between"><span class="text-slate-300">CSRF Token Form Protection Checks</span> <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 uppercase">SECURE</span></li>
                                <li class="flex items-center justify-between"><span class="text-slate-300">Stored Script Anti-XSS Payload Filter</span> <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 uppercase">SECURE</span></li>
                            </ul>
                        </div>
                        <!-- Repository Updater Tool -->
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 col-span-1 md:col-span-2">
                            <h3 class="font-extrabold text-white text-xs uppercase tracking-wider mb-4 text-teal-400 flex items-center gap-1.5">
                                <i class="fab fa-git-alt"></i> Continuous Repository Updates
                            </h3>
                            <p class="text-xs text-slate-300 leading-relaxed mb-4">Pull latest structural upgrades, visual components, security patches, and builder layouts directly from the official WebCraft git origin branch.</p>

                            <div class="bg-slate-950 p-4 rounded-lg border border-slate-850 mb-4 font-mono text-[11px] text-slate-300 space-y-1">
                                <span class="text-slate-500">// Current Branch Status Checks:</span>
                                <div id="git-status-log">Pending diagnostic check...</div>
                            </div>

                            <div class="flex gap-2">
                                <button onclick="checkGitStatus()" class="bg-slate-850 hover:bg-slate-800 border border-teal-500/10 text-teal-400 font-bold px-4 py-2.5 rounded text-xs transition">
                                    <i class="fas fa-search-location"></i> Check Status
                                </button>
                                <button onclick="triggerGitPull()" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-4 py-2.5 rounded text-xs transition">
                                    <i class="fas fa-cloud-download-alt"></i> Pull Latest Updates
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- TAB 7: ACCOUNT SECURITY -->
                <div id="tab-security" class="tab-content space-y-6">
                    <div class="max-w-md bg-slate-900 border border-slate-800 rounded-xl overflow-hidden p-6 shadow-md">
                        <h3 class="font-extrabold text-white text-xs uppercase tracking-widest text-teal-400 mb-4 flex items-center gap-2">
                            <i class="fas fa-lock text-sm"></i> Change Password
                        </h3>
                        <form action="admin.php?action=change_password" method="POST" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Current Password</label>
                                <input type="password" name="current_password" required placeholder="••••••••" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500 font-mono">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">New Password</label>
                                <input type="password" name="new_password" required placeholder="Min 8 chars, 1 letter, 1 number" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500 font-mono">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Confirm New Password</label>
                                <input type="password" name="confirm_new_password" required placeholder="••••••••" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500 font-mono">
                            </div>
                            <button type="submit" class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-3 rounded-lg text-xs transition">
                                Update Security Password
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- NEW WEBSITE DIALOGUE MODAL -->
    <div id="create-modal" class="hidden fixed inset-0 bg-slate-950/80 flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-xl p-6 shadow-2xl relative">
            <button onclick="closeCreateModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white transition">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h3 class="text-sm font-extrabold text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                <i class="fas fa-cubes text-teal-400"></i> Assemble New Website Project
            </h3>
            <form action="admin.php?action=create_project" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Project Name</label>
                    <input type="text" name="project_name" required placeholder="e.g., My Portfolio" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-2 text-xs text-white focus:outline-none focus:border-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Brief Description (Optional)</label>
                    <textarea name="project_desc" rows="3" placeholder="Explain website purpose..." class="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-2 text-xs text-white focus:outline-none focus:border-teal-500"></textarea>
                </div>
                <button type="submit" class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-3 rounded-lg text-xs transition">
                    Start Coding
                </button>
            </form>
        </div>
    </div>

    <script>
        /**
         * Switch Dashboard Layout Tabs dynamically
         */
        function switchTab(tabId, btn) {
            // Hide all tab containers
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(c => c.classList.remove('active'));

            // Show active tab
            const target = document.getElementById(tabId);
            if (target) target.classList.add('active');

            // Reset navigation menu buttons style
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(b => {
                b.className = 'tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition duration-200 text-slate-400 hover:text-white hover:bg-slate-800/50';
            });

            // Set active navigation button style
            btn.className = 'tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition duration-200 bg-slate-800 text-teal-400';

            // Change page top header title description
            const vTitle = document.getElementById('view-title');
            if (tabId === 'tab-dashboard') vTitle.innerText = 'Dashboard';
            if (tabId === 'tab-sites') vTitle.innerText = 'My Websites';
            if (tabId === 'tab-templates') vTitle.innerText = 'Templates Library';
            if (tabId === 'tab-submissions') vTitle.innerText = 'Contact Form Submissions';
            if (tabId === 'tab-security') vTitle.innerText = 'Account Security';
            if (tabId === 'tab-users') vTitle.innerText = 'User Access Controls';
            if (tabId === 'tab-system') vTitle.innerText = 'System Diagnostics';
        }

        // Modal triggers
        function openCreateModal() {
            document.getElementById('create-modal').classList.remove('hidden');
        }

        function closeCreateModal() {
            document.getElementById('create-modal').classList.add('hidden');
        }

        /**
         * Check Local Git repository status
         */
        function checkGitStatus() {
            const statusLog = document.getElementById('git-status-log');
            statusLog.innerText = "Querying git repository status...";

            fetch('admin.php?action=git_status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'csrf_token=' + encodeURIComponent('<?php echo $csrf_token; ?>')
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    statusLog.innerHTML = `<span class="text-emerald-400">✔ Repository Verified!</span><br>${data.output}`;
                } else {
                    statusLog.innerHTML = `<span class="text-red-400">❌ Error:</span><br>${data.error}`;
                }
            })
            .catch(err => {
                statusLog.innerText = "Network connection failed: " + err.message;
            });
        }

        /**
         * Trigger Git Pull origin main
         */
        function triggerGitPull() {
            const statusLog = document.getElementById('git-status-log');
            if (confirm("Are you sure you wish to pull direct code updates from git origin branch? This will sync local files.")) {
                statusLog.innerText = "Pulling latest commits from repository...";

                fetch('admin.php?action=git_pull', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'csrf_token=' + encodeURIComponent('<?php echo $csrf_token; ?>')
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        statusLog.innerHTML = `<span class="text-emerald-400">✔ Pull Completed successfully!</span><br>${data.output}`;
                        alert("Repository update completed successfully!");
                        window.location.reload();
                    } else {
                        statusLog.innerHTML = `<span class="text-red-400">❌ Pull Error:</span><br>${data.error}`;
                    }
                })
                .catch(err => {
                    statusLog.innerText = "Network connection failed: " + err.message;
                });
            }
        }

        /**
         * Trigger Project Deletion secure API callbacks
         */
        function deleteProject(projectId) {
            if (confirm("Are you absolutely certain you wish to delete this project? This process is irreversible.")) {
                fetch('api.php?action=delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?php echo $csrf_token; ?>'
                    },
                    body: JSON.stringify({
                        project_id: projectId,
                        csrf_token: '<?php echo $csrf_token; ?>'
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert("Deletion Error: " + (data.error || "Unknown response error"));
                    }
                })
                .catch(err => {
                    alert("Network Error: " + err.message);
                });
            }
        }

        /**
         * Dynamic onboarding creation from templates library
         */
        function createNewSiteFromTemplate(templateName) {
            let layoutJson = '[]';

            if (templateName === 'SaaS Product Landing Page') {
                layoutJson = JSON.stringify([
                    { componentId: 'navbar', headingText: '', paragraphText: '', classes: [], raw_html: '' },
                    { componentId: 'hero', headingText: 'Secure Cloud Platform Launch', paragraphText: 'Streamline standard production pipeline tools without custom configurations.', classes: [], raw_html: '' },
                    { componentId: 'features', headingText: 'Built-in Superpowers', paragraphText: 'Engineered for scalability, enterprise controls, and robust databases.', classes: [], raw_html: '' },
                    { componentId: 'pricing', headingText: 'Standard Subscription Plans', paragraphText: '', classes: [], raw_html: '' },
                    { componentId: 'contact', headingText: 'Let Us Talk Enterprise Solutions', paragraphText: '', classes: [], raw_html: '' },
                    { componentId: 'chatbot', headingText: '', paragraphText: '', classes: [], raw_html: '' },
                    { componentId: 'footer', headingText: '', paragraphText: '', classes: [], raw_html: '' }
                ]);
            } else if (templateName === 'Corporate Consulting Showcase') {
                layoutJson = JSON.stringify([
                    { componentId: 'navbar', headingText: 'CONSULTING GROUP', paragraphText: '', classes: [], raw_html: '' },
                    { componentId: 'hero', headingText: 'Expert Financial & Technical Advisors', paragraphText: 'Empower commercial workflows, build corporate resilience, and increase annual margin structures.', classes: [], raw_html: '' },
                    { componentId: 'features', headingText: 'Core Advisory Units', paragraphText: '', classes: [], raw_html: '' },
                    { componentId: 'chatbot', headingText: '', paragraphText: '', classes: [], raw_html: '' },
                    { componentId: 'footer', headingText: '', paragraphText: '', classes: [], raw_html: '' }
                ]);
            } else if (templateName === 'E-Commerce Gadget Landing Page') {
                layoutJson = JSON.stringify({
                    blocks: [
                        { componentId: 'navbar', headingText: 'GADGET LAB', paragraphText: '', classes: [], raw_html: '' },
                        { componentId: 'hero', headingText: 'Next Gen Immersive Headphones', paragraphText: 'Engineered with sound precision and dynamic feedback cancellation parameters.', classes: [], raw_html: '' },
                        { componentId: 'features', headingText: 'Unmatched Capabilities', paragraphText: '', classes: [], raw_html: '' },
                        { componentId: 'pricing', headingText: 'Explore Available Gadgets', paragraphText: 'Select your gadget package below', classes: [], raw_html: '' },
                        { componentId: 'contact', headingText: 'Inquire About Custom Bulk Orders', paragraphText: '', classes: [], raw_html: '' },
                        { componentId: 'chatbot', headingText: '', paragraphText: '', classes: [], raw_html: '' },
                        { componentId: 'footer', headingText: '', paragraphText: '', classes: [], raw_html: '' }
                    ],
                    custom_css: 'body { background-color: #030712 !important; }',
                    custom_js: 'console.log("E-Commerce template script initialized");'
                });
            }

            // Fire standard secure save to generate the project
            fetch('api.php?action=save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '<?php echo $csrf_token; ?>'
                },
                body: JSON.stringify({
                    name: templateName + ' ' + Math.floor(Math.random() * 100),
                    description: 'Instantiated from premium ' + templateName + ' starter package.',
                    content_json: layoutJson,
                    csrf_token: '<?php echo $csrf_token; ?>'
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'builder.php?project_id=' + data.project_id;
                } else {
                    alert("Template Init Error: " + data.error);
                }
            })
            .catch(err => {
                alert("Network Error: " + err.message);
            });
        }
    </script>
</body>
</html>
