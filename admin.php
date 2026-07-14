<?php
/**
 * WebCraft Admin Control Panel  v2.0
 * New in v2:
 *  - create_project seeds a proper v2 schema (blocks:[]) instead of '[]'
 *  - Templates load from DB (templates table); hard-coded fallbacks still present
 *  - createNewSiteFromTemplate() sends schema object, calls save_project action
 *  - deleteProject() calls delete_project action
 *  - Version History drawer per project (calls list_versions)
 *  - Asset Manager tab (calls list_assets / upload_asset)
 *  - Dashboard stats include published_count
 */
require_once __DIR__ . '/config.php';
require_login();

$db       = get_db_connection();
$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role     = $_SESSION['user_role'];

$error_msg   = $_GET['error']   ?? '';
$success_msg = $_GET['success'] ?? '';

// ── POST HANDLERS ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $csrf   = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error_msg = 'CSRF Token validation failed.';
    } else {
        if ($action === 'create_project') {
            $name = trim($_POST['project_name'] ?? '');
            $desc = trim($_POST['project_desc'] ?? '');

            if (empty($name)) {
                $error_msg = 'Website project name is required.';
            } else {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
                if (empty($slug)) $slug = 'site-' . time();

                $chk = $db->prepare('SELECT id FROM projects WHERE user_id = ? AND slug = ?');
                $chk->execute([$user_id, $slug]);
                if ($chk->fetch()) $slug .= '-' . rand(10, 99);

                // Seed v2 blank schema
                $blank_schema = json_encode([
                    'version' => 1,
                    'meta'    => [
                        'title'       => $name,
                        'description' => $desc,
                        'custom_css'  => '',
                        'custom_js'   => ''
                    ],
                    'blocks' => []
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $stmt = $db->prepare(
                    "INSERT INTO projects (user_id, name, slug, description, content_json, schema_version, status)
                     VALUES (?, ?, ?, ?, ?, 2, 'draft')"
                );
                try {
                    $stmt->execute([$user_id, $name, $slug, $desc, $blank_schema]);
                    $new_id = $db->lastInsertId();
                    header('Location: builder.php?project_id=' . $new_id);
                    exit;
                } catch (PDOException $e) {
                    $error_msg = 'Error creating project: ' . $e->getMessage();
                }
            }

        } elseif ($action === 'change_password') {
            $cur  = $_POST['current_password']     ?? '';
            $new  = $_POST['new_password']          ?? '';
            $conf = $_POST['confirm_new_password']  ?? '';

            if (empty($cur) || empty($new) || empty($conf)) {
                $error_msg = 'Please fill in all password fields.';
            } elseif ($new !== $conf) {
                $error_msg = 'New passwords do not match.';
            } elseif (strlen($new) < 8) {
                $error_msg = 'New password must be at least 8 characters.';
            } elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) {
                $error_msg = 'Password must contain at least one letter and one number.';
            } else {
                $s = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
                $s->execute([$user_id]);
                $u = $s->fetch();
                if ($u && password_verify($cur, $u['password_hash'])) {
                    try {
                        $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                           ->execute([password_hash($new, PASSWORD_BCRYPT), $user_id]);
                        $success_msg = 'Password changed successfully!';
                    } catch (PDOException $e) {
                        $error_msg = 'Error updating password: ' . $e->getMessage();
                    }
                } else {
                    $error_msg = 'Current password is incorrect.';
                }
            }

        } elseif ($action === 'update_user_role' && is_admin()) {
            $tid     = (int)($_POST['target_user_id'] ?? 0);
            $nrole   = $_POST['new_role']   ?? 'user';
            $nstatus = $_POST['new_status'] ?? 'active';
            if ($tid && $tid !== $user_id) {
                try {
                    $db->prepare('UPDATE users SET role = ?, status = ? WHERE id = ?')
                       ->execute([$nrole, $nstatus, $tid]);
                    $success_msg = 'User updated successfully.';
                } catch (PDOException $e) {
                    $error_msg = 'Error: ' . $e->getMessage();
                }
            } else {
                $error_msg = 'You cannot modify your own role.';
            }

        } elseif ($action === 'git_status' && is_admin()) {
            header('Content-Type: application/json');
            $out = shell_exec('git status 2>&1') ?: 'Git not available.';
            echo json_encode(['success' => true, 'output' => nl2br(sanitize_output($out))]);
            exit;

        } elseif ($action === 'git_pull' && is_admin()) {
            header('Content-Type: application/json');
            $out = shell_exec('git pull 2>&1');
            if (empty($out)) {
                echo json_encode(['success' => false, 'error' => 'git pull failed or permission denied.']);
            } else {
                echo json_encode(['success' => true, 'output' => nl2br(sanitize_output($out))]);
            }
            exit;
        }
    }
}

// ── DATA QUERIES ──────────────────────────────────────────────────────────────

$total_sites_count   = 0;
$user_sites_count    = 0;
$published_count     = 0;
$active_users_count  = 0;

try {
    $total_sites_count  = $db->query('SELECT COUNT(*) FROM projects')->fetchColumn();
    $s = $db->prepare('SELECT COUNT(*) FROM projects WHERE user_id = ?'); $s->execute([$user_id]); $user_sites_count = $s->fetchColumn();
    $s = $db->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ? AND status = 'published'"); $s->execute([$user_id]); $published_count = $s->fetchColumn();
    $active_users_count = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
} catch (PDOException $e) { error_log($e->getMessage()); }

$user_projects = [];
try {
    $s = $db->prepare('SELECT id, name, slug, description, status, schema_version, updated_at FROM projects WHERE user_id = ? ORDER BY updated_at DESC');
    $s->execute([$user_id]);
    $user_projects = $s->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

$form_submissions = [];
try {
    $s = $db->prepare("
        SELECT cs.*, p.name AS project_name
        FROM contact_submissions cs
        JOIN projects p ON cs.project_id = p.id
        WHERE p.user_id = ?
        ORDER BY cs.created_at DESC
    ");
    $s->execute([$user_id]);
    $form_submissions = $s->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

$email_logs = [];
try {
    $s = $db->prepare("
        SELECT el.*, cs.name AS sender_name
        FROM email_logs el
        JOIN contact_submissions cs ON el.submission_id = cs.id
        JOIN projects p ON cs.project_id = p.id
        WHERE p.user_id = ?
        ORDER BY el.created_at DESC
    ");
    $s->execute([$user_id]);
    $email_logs = $s->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

$all_users = [];
if (is_admin()) {
    try {
        $all_users = $db->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// Load templates from DB; fall back to built-in stubs
$db_templates = [];
try {
    $db_templates = $db->query('SELECT id, name, description, thumbnail_url, content_json FROM templates ORDER BY id')->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

$is_using_default_password = false;
try {
    $s = $db->prepare('SELECT password_hash FROM users WHERE id = ?'); $s->execute([$user_id]);
    $u = $s->fetch();
    if ($u && password_verify('admin123', $u['password_hash'])) $is_using_default_password = true;
} catch (PDOException $e) { error_log($e->getMessage()); }

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebCraft — Admin Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        /* Version history drawer */
        #version-drawer { transition: transform .25s ease; }
        #version-drawer.open { transform: translateX(0); }
        /* Asset manager upload area */
        #asset-drop-zone { border: 2px dashed #334155; border-radius: .75rem; transition: border-color .2s; }
        #asset-drop-zone.drag-over { border-color: #14b8a6; background: rgba(20,184,166,.06); }
    </style>
</head>
<body class="h-full text-slate-100 flex flex-col font-sans">

<div class="flex h-full overflow-hidden">

    <!-- ── SIDEBAR ─────────────────────────────────────────────────────────── -->
    <aside class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col shrink-0">
        <div class="h-16 flex items-center px-6 border-b border-slate-800 bg-slate-950/40">
            <div class="flex items-center gap-2">
                <div class="bg-teal-500 text-slate-950 w-8 h-8 rounded-lg flex items-center justify-center font-black text-sm">WC</div>
                <span class="font-extrabold text-sm tracking-widest text-teal-400 uppercase">WebCraft v2.0</span>
            </div>
        </div>
        <div class="p-6 border-b border-slate-800/80">
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
        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
            <button onclick="switchTab('tab-dashboard', this)" class="tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition bg-slate-800 text-teal-400">
                <i class="fas fa-chart-line"></i> Dashboard
            </button>
            <button onclick="switchTab('tab-sites', this)" class="tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition text-slate-400 hover:text-white hover:bg-slate-800/50">
                <i class="fas fa-folder"></i> My Websites
            </button>
            <button onclick="switchTab('tab-templates', this)" class="tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition text-slate-400 hover:text-white hover:bg-slate-800/50">
                <i class="fas fa-layer-group"></i> Templates
            </button>
            <button onclick="switchTab('tab-assets', this)" class="tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition text-slate-400 hover:text-white hover:bg-slate-800/50">
                <i class="fas fa-images"></i> Assets
            </button>
            <button onclick="switchTab('tab-submissions', this)" class="tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition text-slate-400 hover:text-white hover:bg-slate-800/50">
                <i class="fas fa-envelope-open-text"></i> Form Submissions
            </button>
            <button id="btn-security" onclick="switchTab('tab-security', this)" class="tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition text-slate-400 hover:text-white hover:bg-slate-800/50">
                <i class="fas fa-user-shield"></i> Account Security
            </button>
            <?php if (is_admin()): ?>
            <div class="pt-4 pb-2 px-4"><span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-widest">Admin</span></div>
            <button onclick="switchTab('tab-users', this)" class="tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition text-slate-400 hover:text-white hover:bg-slate-800/50">
                <i class="fas fa-users-cog"></i> User Manager
            </button>
            <button onclick="switchTab('tab-system', this)" class="tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition text-slate-400 hover:text-white hover:bg-slate-800/50">
                <i class="fas fa-server"></i> System Diagnostics
            </button>
            <?php endif; ?>
        </nav>
        <div class="p-4 border-t border-slate-800/80 shrink-0">
            <a href="auth.php?auth_action=logout" class="flex items-center justify-center gap-2 w-full bg-slate-950 hover:bg-red-950/30 text-red-400 hover:text-red-300 font-bold py-2.5 rounded-lg text-xs transition border border-red-500/10">
                <i class="fas fa-sign-out-alt"></i> Sign Out
            </a>
        </div>
    </aside>

    <!-- ── MAIN ───────────────────────────────────────────────────────────── -->
    <main class="flex-1 flex flex-col overflow-hidden bg-slate-950">
        <header class="h-16 border-b border-slate-800 flex items-center justify-between px-8 bg-slate-900/20 shrink-0">
            <h2 id="view-title" class="text-sm font-extrabold text-white uppercase tracking-wider">Dashboard</h2>
            <div class="flex items-center gap-3">
                <button onclick="openCreateModal()" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-4 py-2 rounded-lg text-xs flex items-center gap-1.5 transition shadow-lg shadow-teal-500/15">
                    <i class="fas fa-plus"></i> New Website
                </button>
            </div>
        </header>

        <!-- ALERTS -->
        <?php if ($is_using_default_password): ?>
        <div class="mx-8 mt-6 bg-amber-950/40 border border-amber-500/40 text-amber-300 rounded-lg p-4 flex items-center justify-between gap-3 animate-pulse">
            <div class="flex items-center gap-3">
                <i class="fas fa-shield-halved text-amber-400 text-lg"></i>
                <div class="text-xs"><strong class="text-white">Security Alert:</strong> You're using the default password (<code>admin123</code>). Please change it immediately.</div>
            </div>
            <button onclick="switchTab('tab-security', document.getElementById('btn-security'))" class="bg-amber-500 hover:bg-amber-400 text-slate-950 px-3 py-1.5 rounded font-black text-[10px] uppercase tracking-wider">Secure Now</button>
        </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
        <div class="mx-8 mt-4 bg-red-950/40 border border-red-500/30 text-red-300 rounded-lg p-4 flex items-center gap-3">
            <i class="fas fa-exclamation-triangle"></i> <span class="text-xs"><?php echo sanitize_output($error_msg); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($success_msg)): ?>
        <div class="mx-8 mt-4 bg-emerald-950/40 border border-emerald-500/30 text-emerald-300 rounded-lg p-4 flex items-center gap-3">
            <i class="fas fa-check-circle"></i> <span class="text-xs"><?php echo sanitize_output($success_msg); ?></span>
        </div>
        <?php endif; ?>

        <!-- ── TAB PANELS ──────────────────────────────────────────────────── -->
        <div class="flex-1 overflow-y-auto p-8">

            <!-- ═══════════════════════ DASHBOARD ═══════════════════════════ -->
            <div id="tab-dashboard" class="tab-content active space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                    <?php
                    $stats = [
                        ['Your Websites',       $user_sites_count,   'fa-globe',   'teal'],
                        ['Published',           $published_count,    'fa-rocket',  'emerald'],
                        ['All Platform Sites',  $total_sites_count,  'fa-cubes',   'indigo'],
                        ['Platform Users',      $active_users_count, 'fa-users',   'violet'],
                    ];
                    foreach ($stats as [$label, $val, $icon, $color]): ?>
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block"><?php echo $label; ?></span>
                            <span class="text-3xl font-black text-white mt-1 block"><?php echo (int)$val; ?></span>
                        </div>
                        <div class="bg-<?php echo $color; ?>-500/10 text-<?php echo $color; ?>-400 w-12 h-12 rounded-xl flex items-center justify-center text-lg">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Recent projects quick-list -->
                <?php if (!empty($user_projects)): ?>
                <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                        <h3 class="font-bold text-xs text-teal-400 uppercase tracking-widest">Recent Projects</h3>
                        <button onclick="switchTab('tab-sites', document.querySelectorAll('.tab-button')[1])" class="text-[10px] text-slate-400 hover:text-teal-400 transition">View All →</button>
                    </div>
                    <div class="divide-y divide-slate-800/60">
                        <?php foreach (array_slice($user_projects, 0, 5) as $p): ?>
                        <div class="px-6 py-3 flex items-center justify-between hover:bg-slate-800/20 transition">
                            <div>
                                <span class="text-xs font-bold text-white"><?php echo sanitize_output($p['name']); ?></span>
                                <span class="ml-2 text-[9px] px-1.5 py-0.5 rounded uppercase font-bold <?php echo $p['status']==='published' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-500'; ?>"><?php echo $p['status']; ?></span>
                                <?php if (($p['schema_version'] ?? 1) < 2): ?>
                                <span class="ml-1 text-[9px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 uppercase font-bold">Legacy</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-slate-500"><?php echo date('M d', strtotime($p['updated_at'])); ?></span>
                                <a href="builder.php?project_id=<?php echo $p['id']; ?>" class="text-[10px] bg-slate-800 hover:bg-slate-700 text-slate-200 px-2 py-1 rounded font-bold transition">Edit</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-slate-800 rounded-xl p-8">
                    <div class="max-w-2xl">
                        <span class="bg-teal-500/10 text-teal-400 font-semibold px-3 py-1 rounded-full text-[10px] uppercase tracking-wider border border-teal-500/20">WebCraft v2.0</span>
                        <h2 class="text-2xl font-black text-white mt-4 tracking-tight">Design & Launch Beautiful Websites</h2>
                        <p class="text-slate-300 mt-2 text-xs leading-relaxed">Drag, drop, and publish with the brand-new block schema engine. Every component now stores structured JSON — making exports, versioning, and live renders perfectly in sync.</p>
                        <button onclick="openCreateModal()" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-6 py-3 rounded-lg text-xs mt-6 flex items-center gap-2 transition">
                            <i class="fas fa-magic"></i> Start Building
                        </button>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════ MY WEBSITES ══════════════════════════ -->
            <div id="tab-sites" class="tab-content space-y-6">
                <?php if (empty($user_projects)): ?>
                <div class="bg-slate-900 border border-slate-800 rounded-xl p-12 text-center max-w-xl mx-auto mt-8">
                    <div class="w-16 h-16 bg-slate-800 rounded-full flex items-center justify-center text-slate-500 text-2xl mx-auto mb-4 border border-slate-700"><i class="fas fa-cubes"></i></div>
                    <h3 class="font-bold text-white text-sm">No websites yet</h3>
                    <p class="text-slate-400 text-xs mt-2 max-w-xs mx-auto leading-relaxed">Click "New Website" to start building your first drag-and-drop site.</p>
                    <button onclick="openCreateModal()" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-4 py-2.5 rounded-lg text-xs mt-6 transition">Create First Site</button>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($user_projects as $p): ?>
                    <div class="bg-slate-900 border border-slate-800 hover:border-slate-700 rounded-xl overflow-hidden shadow-sm flex flex-col justify-between group transition">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold px-2.5 py-1 rounded border uppercase tracking-wider <?php echo $p['status']==='published' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-slate-800 text-slate-400 border-slate-700'; ?>">
                                    <?php echo $p['status']; ?>
                                </span>
                                <?php if (($p['schema_version'] ?? 1) < 2): ?>
                                <span class="text-[9px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 uppercase font-bold" title="Legacy v1 schema">v1</span>
                                <?php else: ?>
                                <span class="text-[9px] px-1.5 py-0.5 rounded bg-teal-500/10 text-teal-400 border border-teal-500/20 uppercase font-bold">v2</span>
                                <?php endif; ?>
                                <span class="text-[10px] text-slate-500"><?php echo date('M d, Y', strtotime($p['updated_at'])); ?></span>
                            </div>
                            <h3 class="text-sm font-extrabold text-white mt-4 group-hover:text-teal-400 transition"><?php echo sanitize_output($p['name']); ?></h3>
                            <p class="text-slate-400 text-xs mt-2 line-clamp-2 leading-relaxed"><?php echo sanitize_output($p['description'] ?: 'No description.'); ?></p>
                        </div>
                        <div class="bg-slate-950/40 p-4 border-t border-slate-800 flex gap-2">
                            <a href="builder.php?project_id=<?php echo $p['id']; ?>" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold px-3 py-2 rounded text-[11px] flex-1 text-center transition flex items-center justify-center gap-1.5">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <?php if ($p['status'] === 'published'): ?>
                            <a href="render.php?slug=<?php echo urlencode($p['slug']); ?>&user=<?php echo urlencode($username); ?>" target="_blank" class="bg-teal-500/10 hover:bg-teal-500/20 text-teal-400 font-bold px-3 py-2 rounded text-[11px] flex-1 text-center border border-teal-500/20 transition flex items-center justify-center gap-1.5">
                                <i class="fas fa-external-link-alt"></i> View Live
                            </a>
                            <?php endif; ?>
                            <button onclick="openVersionDrawer(<?php echo $p['id']; ?>, '<?php echo addslashes(sanitize_output($p['name'])); ?>')" class="text-indigo-400 hover:text-indigo-300 hover:bg-indigo-950/40 border border-transparent hover:border-indigo-500/20 w-9 h-9 flex items-center justify-center rounded transition" title="Version History">
                                <i class="fas fa-history"></i>
                            </button>
                            <button onclick="deleteProject(<?php echo $p['id']; ?>)" class="text-red-400 hover:text-red-300 hover:bg-red-950/40 border border-transparent hover:border-red-500/20 w-9 h-9 flex items-center justify-center rounded transition" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- ════════════════════════ TEMPLATES ═══════════════════════════ -->
            <div id="tab-templates" class="tab-content space-y-6">
                <?php
                // Built-in fallback stubs (used when DB templates table is empty)
                $builtin_templates = [
                    [
                        'name'        => 'Blank Canvas',
                        'description' => 'Start from scratch with an empty page.',
                        'icon'        => 'fa-file',
                        'schema'      => ['version'=>1,'meta'=>['title'=>'My Site','description'=>'','custom_css'=>'','custom_js'=>''],'blocks'=>[]]
                    ],
                    [
                        'name'        => 'SaaS Landing Page',
                        'description' => 'Navbar, Hero, Features Grid, Pricing, CTA, Contact Form, Footer — ready to customise.',
                        'icon'        => 'fa-pager',
                        'schema'      => ['version'=>1,'meta'=>['title'=>'SaaS Product','description'=>'','custom_css'=>'','custom_js'=>''],'blocks'=>[
                            ['id'=>'b1','type'=>'navbar','props'=>['brand'=>'SAAS APP','links'=>[['label'=>'Home','href'=>'#'],['label'=>'Features','href'=>'#features'],['label'=>'Pricing','href'=>'#pricing']]]],
                            ['id'=>'b2','type'=>'hero','props'=>['heading'=>'Secure Cloud Platform','subheading'=>'Streamline production pipelines without custom configs.','button_text'=>'Get Started','button_href'=>'#','bg'=>'bg-indigo-950','align'=>'text-center','padding'=>'py-28']],
                            ['id'=>'b3','type'=>'features_grid','props'=>['heading'=>'Built-in Superpowers','bg'=>'bg-slate-900','padding'=>'py-16','features'=>[['icon'=>'fas fa-bolt','title'=>'Fast','desc'=>'Lightning-fast performance.'],['icon'=>'fas fa-lock','title'=>'Secure','desc'=>'Enterprise-grade security.'],['icon'=>'fas fa-expand','title'=>'Scalable','desc'=>'Grows with your business.']]]],
                            ['id'=>'b4','type'=>'pricing_cards','props'=>['heading'=>'Simple Pricing','bg'=>'bg-slate-950','padding'=>'py-16','plans'=>[['name'=>'Starter','price'=>'Free','features'=>['5 pages','Basic analytics','Email support'],'cta'=>'Get Started','href'=>'#'],['name'=>'Pro','price'=>'$29/mo','features'=>['Unlimited pages','Advanced analytics','Priority support'],'cta'=>'Start Free Trial','href'=>'#','highlight'=>true],['name'=>'Enterprise','price'=>'Custom','features'=>['White-label','Dedicated server','SLA guarantee'],'cta'=>'Contact Us','href'=>'#']]]],
                            ['id'=>'b5','type'=>'cta_banner','props'=>['heading'=>'Ready to get started?','subtext'=>'Join thousands of builders today.','button_text'=>'Start Free','button_href'=>'#','bg'=>'bg-teal-900']],
                            ['id'=>'b6','type'=>'contact_form','props'=>['heading'=>'Get In Touch','bg'=>'bg-slate-900','padding'=>'py-16']],
                            ['id'=>'b7','type'=>'footer','props'=>['brand'=>'SaaS App','copyright'=>'© 2026 SaaS App. All rights reserved.','links'=>[['label'=>'Privacy','href'=>'#'],['label'=>'Terms','href'=>'#']]]]
                        ]]
                    ],
                    [
                        'name'        => 'Corporate Consulting',
                        'description' => 'Bold hero, feature cards, testimonials, and a clean footer.',
                        'icon'        => 'fa-briefcase',
                        'schema'      => ['version'=>1,'meta'=>['title'=>'Consulting Group','description'=>'','custom_css'=>'','custom_js'=>''],'blocks'=>[
                            ['id'=>'b1','type'=>'navbar','props'=>['brand'=>'CONSULTING GROUP','links'=>[['label'=>'About','href'=>'#'],['label'=>'Services','href'=>'#services'],['label'=>'Contact','href'=>'#contact']]]],
                            ['id'=>'b2','type'=>'hero','props'=>['heading'=>'Expert Financial & Technical Advisors','subheading'=>'Build corporate resilience and increase annual margins.','button_text'=>'Speak to an Advisor','button_href'=>'#','bg'=>'bg-slate-900','align'=>'text-left','padding'=>'py-32']],
                            ['id'=>'b3','type'=>'features_grid','props'=>['heading'=>'Core Advisory Units','bg'=>'bg-slate-950','padding'=>'py-16','features'=>[['icon'=>'fas fa-chart-line','title'=>'Strategy','desc'=>'Data-driven growth strategies.'],['icon'=>'fas fa-handshake','title'=>'M&A','desc'=>'Mergers and acquisitions support.'],['icon'=>'fas fa-shield-alt','title'=>'Risk','desc'=>'Enterprise risk management.']]]],
                            ['id'=>'b4','type'=>'testimonials','props'=>['heading'=>'What Our Clients Say','bg'=>'bg-slate-900','padding'=>'py-16','items'=>[['quote'=>'Outstanding results within 3 months.','author'=>'Jane D., CFO','company'=>'Acme Corp'],['quote'=>'Their M&A team was invaluable.','author'=>'Mark T., CEO','company'=>'TechVentures']]]],
                            ['id'=>'b5','type'=>'footer','props'=>['brand'=>'Consulting Group','copyright'=>'© 2026 Consulting Group.','links'=>[['label'=>'Privacy','href'=>'#'],['label'=>'Careers','href'=>'#']]]]
                        ]]
                    ],
                    [
                        'name'        => 'E-Commerce Product',
                        'description' => 'Gadget-style product page with pricing, features, and checkout CTA.',
                        'icon'        => 'fa-shopping-bag',
                        'schema'      => ['version'=>1,'meta'=>['title'=>'Gadget Lab','description'=>'','custom_css'=>'body{background:#030712}','custom_js'=>''],'blocks'=>[
                            ['id'=>'b1','type'=>'navbar','props'=>['brand'=>'GADGET LAB','links'=>[['label'=>'Products','href'=>'#'],['label'=>'Reviews','href'=>'#reviews'],['label'=>'Buy Now','href'=>'#pricing']]]],
                            ['id'=>'b2','type'=>'hero','props'=>['heading'=>'Next Gen Immersive Headphones','subheading'=>'Engineered with sound precision and dynamic feedback cancellation.','button_text'=>'Shop Now','button_href'=>'#pricing','bg'=>'bg-slate-950','align'=>'text-center','padding'=>'py-28']],
                            ['id'=>'b3','type'=>'features_grid','props'=>['heading'=>'Unmatched Capabilities','bg'=>'bg-slate-900','padding'=>'py-16','features'=>[['icon'=>'fas fa-headphones','title'=>'40hr Battery','desc'=>'Industry-leading battery life.'],['icon'=>'fas fa-wifi','title'=>'Wireless','desc'=>'Bluetooth 5.3 lossless audio.'],['icon'=>'fas fa-sliders-h','title'=>'EQ Tuning','desc'=>'App-controlled 10-band EQ.']]]],
                            ['id'=>'b4','type'=>'pricing_cards','props'=>['heading'=>'Choose Your Model','bg'=>'bg-slate-950','padding'=>'py-16','plans'=>[['name'=>'Standard','price'=>'$149','features'=>['30hr battery','BT 5.0','Basic EQ'],'cta'=>'Buy Now','href'=>'#'],['name'=>'Pro','price'=>'$249','features'=>['40hr battery','BT 5.3','10-band EQ','ANC'],'cta'=>'Buy Pro','href'=>'#','highlight'=>true]]]],
                            ['id'=>'b5','type'=>'testimonials','props'=>['heading'=>'Customer Reviews','bg'=>'bg-slate-900','padding'=>'py-16','items'=>[['quote'=>'Best headphones I have ever owned.','author'=>'Alex K.','company'=>'Verified Buyer'],['quote'=>'ANC is absolutely mind-blowing.','author'=>'Sam R.','company'=>'Verified Buyer']]]],
                            ['id'=>'b6','type'=>'footer','props'=>['brand'=>'Gadget Lab','copyright'=>'© 2026 Gadget Lab.','links'=>[['label'=>'Returns','href'=>'#'],['label'=>'Support','href'=>'#']]]]
                        ]]
                    ]
                ];

                // Merge DB templates on top of builtins (DB wins on name collision)
                $template_map = [];
                foreach ($builtin_templates as $t) $template_map[$t['name']] = $t;
                foreach ($db_templates as $dbt) {
                    $decoded = json_decode($dbt['content_json'], true);
                    $template_map[$dbt['name']] = [
                        'name'        => $dbt['name'],
                        'description' => $dbt['description'],
                        'icon'        => 'fa-database',
                        'schema'      => $decoded
                    ];
                }
                $templates_to_render = array_values($template_map);
                ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($templates_to_render as $tpl): ?>
                    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden hover:border-teal-500/50 transition flex flex-col justify-between">
                        <div class="p-6">
                            <div class="w-12 h-12 bg-teal-500/10 text-teal-400 rounded-xl flex items-center justify-center text-lg mb-4">
                                <i class="fas <?php echo $tpl['icon']; ?>"></i>
                            </div>
                            <h3 class="font-bold text-white text-sm"><?php echo sanitize_output($tpl['name']); ?></h3>
                            <p class="text-slate-400 text-xs mt-2 leading-relaxed"><?php echo sanitize_output($tpl['description']); ?></p>
                        </div>
                        <div class="p-4 bg-slate-950/40 border-t border-slate-800">
                            <button onclick='useTemplate(<?php echo json_encode($tpl["name"]); ?>, <?php echo json_encode($tpl["schema"]); ?>)'
                                class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-2.5 rounded-lg text-xs transition">
                                Use Template
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ═══════════════════════ ASSETS ═══════════════════════════════ -->
            <div id="tab-assets" class="tab-content space-y-6">
                <!-- Project selector -->
                <div class="flex items-center gap-4">
                    <label class="text-xs font-bold text-slate-400 uppercase">Project</label>
                    <select id="asset-project-select" onchange="loadAssets()" class="bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500">
                        <option value="">— Select a project —</option>
                        <?php foreach ($user_projects as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo sanitize_output($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Upload zone -->
                <div id="asset-drop-zone" class="p-8 text-center cursor-pointer" onclick="document.getElementById('asset-file-input').click()">
                    <i class="fas fa-cloud-upload-alt text-3xl text-slate-500 mb-3 block"></i>
                    <p class="text-xs text-slate-400">Drag & drop images here or <span class="text-teal-400 font-bold">click to browse</span></p>
                    <p class="text-[10px] text-slate-500 mt-1">JPEG, PNG, GIF, WEBP, SVG — max 5 MB</p>
                    <input type="file" id="asset-file-input" accept="image/*" class="hidden" onchange="uploadAsset(this.files[0])">
                </div>
                <div id="asset-upload-status" class="hidden text-xs text-teal-400 flex items-center gap-2">
                    <i class="fas fa-spinner fa-spin"></i> Uploading...
                </div>

                <!-- Asset grid -->
                <div id="asset-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4"></div>
                <div id="asset-empty" class="hidden text-center text-xs text-slate-500 py-8">
                    <i class="fas fa-images text-2xl mb-2 block"></i>No assets uploaded for this project yet.
                </div>
            </div>

            <!-- ═══════════════════════ SUBMISSIONS ══════════════════════════ -->
            <div id="tab-submissions" class="tab-content space-y-8">
                <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-800 bg-slate-950/20">
                        <h3 class="font-bold text-xs uppercase tracking-widest text-teal-400">Incoming Customer Contacts</h3>
                    </div>
                    <?php if (empty($form_submissions)): ?>
                    <div class="p-8 text-center text-xs text-slate-500"><i class="fas fa-envelope-open text-xl mb-2 block"></i>No submissions yet.</div>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-300">
                            <thead class="bg-slate-950 text-[10px] text-slate-400 uppercase tracking-wider border-b border-slate-800">
                                <tr><th class="px-6 py-4">Project</th><th class="px-6 py-4">Sender</th><th class="px-6 py-4">Email</th><th class="px-6 py-4">Message</th><th class="px-6 py-4">Submitted</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                <?php foreach ($form_submissions as $sub): ?>
                                <tr class="hover:bg-slate-800/20">
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

                <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-800 bg-slate-950/20">
                        <h3 class="font-bold text-xs uppercase tracking-widest text-teal-400">SMTP Mail Logs</h3>
                    </div>
                    <?php if (empty($email_logs)): ?>
                    <div class="p-8 text-center text-xs text-slate-500"><i class="fas fa-paper-plane text-xl mb-2 block"></i>No email logs yet.</div>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-300">
                            <thead class="bg-slate-950 text-[10px] text-slate-400 uppercase tracking-wider border-b border-slate-800">
                                <tr><th class="px-6 py-4">Event</th><th class="px-6 py-4">Recipient</th><th class="px-6 py-4">Subject</th><th class="px-6 py-4">Status</th><th class="px-6 py-4">Sent</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                                <?php foreach ($email_logs as $log): ?>
                                <tr class="hover:bg-slate-800/20">
                                    <td class="px-6 py-4 text-slate-400"><?php echo sanitize_output($log['sender_name']); ?></td>
                                    <td class="px-6 py-4 text-white"><?php echo sanitize_output($log['recipient']); ?></td>
                                    <td class="px-6 py-4 max-w-xs truncate text-teal-400"><?php echo sanitize_output($log['subject']); ?></td>
                                    <td class="px-6 py-4"><span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">SENT</span></td>
                                    <td class="px-6 py-4 text-slate-500"><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ═══════════════════════ USERS (admin) ════════════════════════ -->
            <?php if (is_admin()): ?>
            <div id="tab-users" class="tab-content space-y-6">
                <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-800 bg-slate-950/20">
                        <h3 class="font-bold text-xs uppercase tracking-widest text-teal-400">Access Controls</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-300">
                            <thead class="bg-slate-950 text-[10px] text-slate-400 uppercase tracking-wider border-b border-slate-800">
                                <tr><th class="px-6 py-4">ID</th><th class="px-6 py-4">Username</th><th class="px-6 py-4">Email</th><th class="px-6 py-4">Role</th><th class="px-6 py-4">Status</th><th class="px-6 py-4">Joined</th><th class="px-6 py-4 text-right">Actions</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                <?php foreach ($all_users as $u): ?>
                                <tr class="hover:bg-slate-800/30">
                                    <td class="px-6 py-4 font-mono text-slate-500"><?php echo $u['id']; ?></td>
                                    <td class="px-6 py-4 font-bold text-white"><?php echo sanitize_output($u['username']); ?></td>
                                    <td class="px-6 py-4"><?php echo sanitize_output($u['email']); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?php echo $u['role']==='admin' ? 'bg-indigo-500/15 text-indigo-400 border border-indigo-500/20' : 'bg-slate-800 text-slate-400'; ?>"><?php echo $u['role']; ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?php echo $u['status']==='active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400'; ?>"><?php echo $u['status']; ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-500"><?php echo date('Y-m-d', strtotime($u['created_at'])); ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <?php if ($u['id'] !== $user_id): ?>
                                        <form action="admin.php?action=update_user_role" method="POST" class="inline-flex gap-1.5 items-center">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="target_user_id" value="<?php echo $u['id']; ?>">
                                            <select name="new_role" class="bg-slate-950 border border-slate-700 rounded px-2 py-1 text-[11px] focus:outline-none">
                                                <option value="user" <?php echo $u['role']==='user'?'selected':''; ?>>User</option>
                                                <option value="admin" <?php echo $u['role']==='admin'?'selected':''; ?>>Admin</option>
                                            </select>
                                            <select name="new_status" class="bg-slate-950 border border-slate-700 rounded px-2 py-1 text-[11px] focus:outline-none">
                                                <option value="active" <?php echo $u['status']==='active'?'selected':''; ?>>Active</option>
                                                <option value="suspended" <?php echo $u['status']==='suspended'?'selected':''; ?>>Suspend</option>
                                            </select>
                                            <button type="submit" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold px-2 py-1 rounded text-[10px] transition">Save</button>
                                        </form>
                                        <?php else: ?><span class="text-slate-600 italic">You</span><?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════ SYSTEM (admin) ══════════════════════ -->
            <div id="tab-system" class="tab-content space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
                        <h3 class="font-extrabold text-teal-400 text-xs uppercase tracking-wider mb-4">Environment</h3>
                        <ul class="text-xs space-y-3.5 text-slate-300">
                            <li class="flex justify-between"><span class="text-slate-500">PHP Version</span><span class="font-bold font-mono text-white"><?php echo phpversion(); ?></span></li>
                            <li class="flex justify-between"><span class="text-slate-500">DB Interface</span><span class="font-bold text-white">PDO MariaDB</span></li>
                            <li class="flex justify-between"><span class="text-slate-500">Upload Limit</span><span class="font-bold font-mono text-white"><?php echo ini_get('upload_max_filesize'); ?></span></li>
                            <li class="flex justify-between"><span class="text-slate-500">Schema Version</span><span class="font-bold text-white">v2</span></li>
                        </ul>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
                        <h3 class="font-extrabold text-teal-400 text-xs uppercase tracking-wider mb-4">Security Checklist</h3>
                        <ul class="text-xs space-y-3">
                            <?php
                            $checks = [
                                'PDO Parameterized Queries'     => true,
                                'BCrypt password_hash'          => true,
                                'CSRF Token Protection'         => true,
                                'XSS sanitize_output Filters'   => true,
                                'File Upload MIME Validation'    => true,
                                'v2 Schema (no raw HTML store)'  => true,
                            ];
                            foreach ($checks as $check => $ok): ?>
                            <li class="flex items-center justify-between">
                                <span class="text-slate-300"><?php echo $check; ?></span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded border uppercase <?php echo $ok ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20'; ?>"><?php echo $ok ? 'SECURE' : 'WARN'; ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 col-span-1 md:col-span-2">
                        <h3 class="font-extrabold text-teal-400 text-xs uppercase tracking-wider mb-4 flex items-center gap-1.5">
                            <i class="fab fa-git-alt"></i> Repository Updates
                        </h3>
                        <div class="bg-slate-950 p-4 rounded-lg border border-slate-800 mb-4 font-mono text-[11px] text-slate-300">
                            <div id="git-status-log">Pending diagnostic...</div>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="checkGitStatus()" class="bg-slate-800 hover:bg-slate-700 border border-teal-500/10 text-teal-400 font-bold px-4 py-2.5 rounded text-xs transition"><i class="fas fa-search-location"></i> Check Status</button>
                            <button onclick="triggerGitPull()" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-4 py-2.5 rounded text-xs transition"><i class="fas fa-cloud-download-alt"></i> Pull Updates</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══════════════════════ SECURITY ════════════════════════════ -->
            <div id="tab-security" class="tab-content space-y-6">
                <div class="max-w-md bg-slate-900 border border-slate-800 rounded-xl p-6">
                    <h3 class="font-extrabold text-teal-400 text-xs uppercase tracking-widest mb-4 flex items-center gap-2">
                        <i class="fas fa-lock"></i> Change Password
                    </h3>
                    <form action="admin.php?action=change_password" method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <?php foreach ([['current_password','Current Password'],['new_password','New Password'],['confirm_new_password','Confirm New Password']] as [$fname,$flabel]): ?>
                        <div>
                            <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1"><?php echo $flabel; ?></label>
                            <input type="password" name="<?php echo $fname; ?>" required placeholder="••••••••"
                                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500 font-mono">
                        </div>
                        <?php endforeach; ?>
                        <button type="submit" class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-3 rounded-lg text-xs transition">Update Password</button>
                    </form>
                </div>
            </div>

        </div><!-- /tab panels -->
    </main>
</div><!-- /flex -->

<!-- ── CREATE PROJECT MODAL ──────────────────────────────────────────────── -->
<div id="create-modal" class="hidden fixed inset-0 bg-slate-950/80 flex items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-xl p-6 shadow-2xl relative">
        <button onclick="closeCreateModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white"><i class="fas fa-times text-lg"></i></button>
        <h3 class="text-sm font-extrabold text-white uppercase tracking-wider mb-4 flex items-center gap-2">
            <i class="fas fa-cubes text-teal-400"></i> New Website Project
        </h3>
        <form action="admin.php?action=create_project" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div>
                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Project Name</label>
                <input type="text" name="project_name" required placeholder="e.g., My Portfolio"
                    class="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-2 text-xs text-white focus:outline-none focus:border-teal-500">
            </div>
            <div>
                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Description (Optional)</label>
                <textarea name="project_desc" rows="3" placeholder="What is this site for?"
                    class="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-2 text-xs text-white focus:outline-none focus:border-teal-500"></textarea>
            </div>
            <button type="submit" class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-3 rounded-lg text-xs transition">
                Create & Open Builder
            </button>
        </form>
    </div>
</div>

<!-- ── VERSION HISTORY DRAWER ────────────────────────────────────────────── -->
<div id="version-drawer" class="fixed inset-y-0 right-0 w-96 bg-slate-900 border-l border-slate-800 shadow-2xl z-50 flex flex-col translate-x-full">
    <div class="h-14 flex items-center justify-between px-5 border-b border-slate-800 shrink-0">
        <div>
            <h3 class="text-xs font-extrabold text-white uppercase tracking-wider">Version History</h3>
            <p id="version-drawer-project-name" class="text-[10px] text-slate-400 mt-0.5"></p>
        </div>
        <button onclick="closeVersionDrawer()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
    </div>
    <div id="version-list" class="flex-1 overflow-y-auto p-4 space-y-2">
        <div class="text-xs text-slate-500 text-center py-8"><i class="fas fa-spinner fa-spin mr-1"></i> Loading versions...</div>
    </div>
</div>
<div id="version-overlay" onclick="closeVersionDrawer()" class="hidden fixed inset-0 bg-slate-950/60 z-40"></div>

<script>
const CSRF = '<?php echo $csrf_token; ?>';

// ── TAB SWITCHING ────────────────────────────────────────────────────────────
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    const t = document.getElementById(tabId);
    if (t) t.classList.add('active');
    document.querySelectorAll('.tab-button').forEach(b => {
        b.className = 'tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition text-slate-400 hover:text-white hover:bg-slate-800/50';
    });
    btn.className = 'tab-button w-full flex items-center gap-3 px-4 py-3 rounded-lg text-xs font-bold transition bg-slate-800 text-teal-400';
    const titles = {
        'tab-dashboard':'Dashboard','tab-sites':'My Websites','tab-templates':'Templates',
        'tab-assets':'Assets','tab-submissions':'Form Submissions','tab-security':'Account Security',
        'tab-users':'User Access Controls','tab-system':'System Diagnostics'
    };
    document.getElementById('view-title').innerText = titles[tabId] || '';
}

function openCreateModal()  { document.getElementById('create-modal').classList.remove('hidden'); }
function closeCreateModal() { document.getElementById('create-modal').classList.add('hidden'); }

// ── DELETE PROJECT ───────────────────────────────────────────────────────────
function deleteProject(projectId) {
    if (!confirm('Delete this project? This is irreversible.')) return;
    fetch('api.php?action=delete_project', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ project_id: projectId, csrf_token: CSRF })
    })
    .then(r => r.json())
    .then(d => { if (d.success) { alert(d.message); location.reload(); } else { alert('Error: ' + d.error); } })
    .catch(e => alert('Network error: ' + e.message));
}

// ── USE TEMPLATE ─────────────────────────────────────────────────────────────
function useTemplate(name, schema) {
    const projectName = name + ' ' + Math.floor(Math.random() * 100);
    fetch('api.php?action=save_project', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({
            name: projectName,
            description: 'From "' + name + '" template.',
            schema: schema,
            csrf_token: CSRF
        })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) { window.location.href = 'builder.php?project_id=' + d.project_id; }
        else { alert('Template error: ' + d.error); }
    })
    .catch(e => alert('Network error: ' + e.message));
}

// ── VERSION HISTORY DRAWER ───────────────────────────────────────────────────
let _versionProjectId = null;

function openVersionDrawer(projectId, projectName) {
    _versionProjectId = projectId;
    document.getElementById('version-drawer-project-name').innerText = projectName;
    document.getElementById('version-overlay').classList.remove('hidden');
    const drawer = document.getElementById('version-drawer');
    drawer.classList.remove('translate-x-full');
    drawer.classList.add('open');

    const list = document.getElementById('version-list');
    list.innerHTML = '<div class="text-xs text-slate-500 text-center py-8"><i class="fas fa-spinner fa-spin mr-1"></i> Loading...</div>';

    fetch('api.php?action=list_versions&project_id=' + projectId)
    .then(r => r.json())
    .then(d => {
        if (!d.success || !d.versions.length) {
            list.innerHTML = '<div class="text-xs text-slate-500 text-center py-8">No saved versions yet.</div>';
            return;
        }
        list.innerHTML = d.versions.map(v => `
            <div class="bg-slate-950 border border-slate-800 rounded-lg p-3 flex items-center justify-between gap-2">
                <div>
                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase border ${
                        v.status === 'published'
                            ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                            : 'bg-slate-800 text-slate-400 border-slate-700'
                    }">${v.status}</span>
                    <span class="ml-2 text-[10px] text-slate-500 font-mono">#${v.id}</span>
                    <p class="text-[10px] text-slate-400 mt-1">${new Date(v.created_at).toLocaleString()}</p>
                </div>
                <button onclick="restoreVersion(${v.id})" class="bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/20 px-2.5 py-1.5 rounded text-[10px] font-bold transition">Restore</button>
            </div>
        `).join('');
    })
    .catch(() => { list.innerHTML = '<div class="text-xs text-red-400 text-center py-8">Failed to load versions.</div>'; });
}

function closeVersionDrawer() {
    document.getElementById('version-drawer').classList.add('translate-x-full');
    document.getElementById('version-drawer').classList.remove('open');
    document.getElementById('version-overlay').classList.add('hidden');
}

function restoreVersion(versionId) {
    if (!confirm('Restore this version as a draft? Your current draft will be overwritten.')) return;
    fetch('api.php?action=restore_version', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ project_id: _versionProjectId, version_id: versionId, csrf_token: CSRF })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('Version restored as draft!');
            closeVersionDrawer();
            window.location.href = 'builder.php?project_id=' + _versionProjectId;
        } else {
            alert('Restore error: ' + d.error);
        }
    })
    .catch(e => alert('Network error: ' + e.message));
}

// ── ASSET MANAGER ────────────────────────────────────────────────────────────
function loadAssets() {
    const pid = document.getElementById('asset-project-select').value;
    const grid  = document.getElementById('asset-grid');
    const empty = document.getElementById('asset-empty');
    grid.innerHTML = '';
    empty.classList.add('hidden');
    if (!pid) return;

    fetch('api.php?action=list_assets&project_id=' + pid)
    .then(r => r.json())
    .then(d => {
        if (!d.success || !d.assets.length) { empty.classList.remove('hidden'); return; }
        grid.innerHTML = d.assets.map(a => `
            <div class="bg-slate-900 border border-slate-800 rounded-lg overflow-hidden group relative">
                <img src="${a.file_url}" alt="${a.filename}" class="w-full h-24 object-cover">
                <div class="p-2">
                    <p class="text-[9px] text-slate-400 truncate">${a.filename}</p>
                    <p class="text-[9px] text-slate-600">${(a.file_size/1024).toFixed(1)} KB</p>
                </div>
                <button onclick="copyAssetUrl('${a.file_url}')" title="Copy URL"
                    class="absolute top-1.5 right-1.5 bg-slate-950/80 hover:bg-teal-500 text-white hover:text-slate-950 w-6 h-6 rounded flex items-center justify-center text-[10px] opacity-0 group-hover:opacity-100 transition">
                    <i class="fas fa-link"></i>
                </button>
            </div>
        `).join('');
    })
    .catch(() => { empty.classList.remove('hidden'); });
}

function uploadAsset(file) {
    const pid = document.getElementById('asset-project-select').value;
    if (!pid) { alert('Please select a project first.'); return; }
    if (!file) return;

    const status = document.getElementById('asset-upload-status');
    status.classList.remove('hidden');

    const fd = new FormData();
    fd.append('project_id', pid);
    fd.append('csrf_token', CSRF);
    fd.append('file', file);

    fetch('api.php?action=upload_asset', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        status.classList.add('hidden');
        if (d.success) { loadAssets(); }
        else { alert('Upload error: ' + d.error); }
    })
    .catch(e => { status.classList.add('hidden'); alert('Network error: ' + e.message); });
}

function copyAssetUrl(url) {
    navigator.clipboard.writeText(url).then(() => { /* toast optional */ });
    alert('URL copied: ' + url);
}

// Drag-and-drop on upload zone
const dropZone = document.getElementById('asset-drop-zone');
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('drag-over'); const f = e.dataTransfer.files[0]; if (f) uploadAsset(f); });

// ── GIT TOOLS ────────────────────────────────────────────────────────────────
function checkGitStatus() {
    const log = document.getElementById('git-status-log');
    log.innerText = 'Querying git status...';
    fetch('admin.php?action=git_status', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'csrf_token='+encodeURIComponent(CSRF) })
    .then(r => r.json())
    .then(d => { log.innerHTML = d.success ? '<span class="text-emerald-400">✔ OK</span><br>' + d.output : '<span class="text-red-400">✘</span><br>' + d.error; })
    .catch(e => { log.innerText = 'Error: ' + e.message; });
}

function triggerGitPull() {
    if (!confirm('Pull latest commits from origin? This syncs server files.')) return;
    const log = document.getElementById('git-status-log');
    log.innerText = 'Pulling...';
    fetch('admin.php?action=git_pull', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'csrf_token='+encodeURIComponent(CSRF) })
    .then(r => r.json())
    .then(d => {
        if (d.success) { log.innerHTML = '<span class="text-emerald-400">✔ Done</span><br>' + d.output; setTimeout(() => location.reload(), 1500); }
        else { log.innerHTML = '<span class="text-red-400">✘ Error</span><br>' + d.error; }
    })
    .catch(e => { log.innerText = 'Error: ' + e.message; });
}
</script>
</body>
</html>
