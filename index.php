<?php
/**
 * WebCraft Master Landing Page & Access Gate
 * Combines highly polished, interactive dark-themed landing presentation,
 * secure unified Login/Signup forms, feature breakdowns, and automated active session routing.
 */
require_once __DIR__ . '/config.php';

// If session is active, route directly to Admin Dashboard
if (is_logged_in()) {
    header("Location: admin.php");
    exit;
}

$action = $_GET['action'] ?? 'login'; // 'login' or 'register'
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebCraft - Open Source Commercial Grade Website Builder</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Premium Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="h-full text-slate-100 flex flex-col md:flex-row font-sans overflow-y-auto md:overflow-hidden">

    <!-- LEFT COVER: MARKETING DISPLAY -->
    <div class="flex-1 bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 p-8 md:p-16 flex flex-col justify-between relative min-h-[400px] md:min-h-0 border-b md:border-b-0 md:border-r border-slate-800">

        <!-- Branding top -->
        <div class="flex items-center gap-3">
            <div class="bg-teal-500 text-slate-950 w-10 h-10 rounded-xl flex items-center justify-center font-black text-lg shadow-lg shadow-teal-500/20">WC</div>
            <div>
                <span class="font-black text-md tracking-wider text-white">WEBCRAFT</span>
                <span class="bg-teal-500/10 text-teal-400 font-extrabold px-2 py-0.5 rounded text-[9px] uppercase ml-1 border border-teal-500/15">Open Source</span>
            </div>
        </div>

        <!-- Marketing Pitch -->
        <div class="my-12 md:my-0 max-w-lg">
            <span class="bg-teal-500/10 text-teal-400 font-semibold px-4 py-1.5 rounded-full text-xs uppercase tracking-widest border border-teal-500/15">Commercial Grade Low-Code</span>
            <h1 class="text-3xl md:text-5xl font-black text-white mt-6 tracking-tight leading-none">Craft Enterprise Websites Effortlessly</h1>
            <p class="text-slate-300 mt-4 text-sm md:text-md leading-relaxed">
                The ultimate visual drag-and-drop workspace powered by PHP 7.4+ & MariaDB/MySQL.
                Compile blazing-fast clean Tailwind layouts, inject custom low-code widgets, and scale without constraints.
            </p>

            <!-- Specs Grid -->
            <div class="grid grid-cols-2 gap-4 mt-8 pt-8 border-t border-slate-800/80">
                <div class="flex items-start gap-2.5">
                    <div class="text-teal-400 text-xs mt-1"><i class="fas fa-shield-alt"></i></div>
                    <div>
                        <h4 class="text-xs font-bold text-white">Full Security</h4>
                        <p class="text-[11px] text-slate-500 mt-0.5">XSS, CSRF & SQLi Shields</p>
                    </div>
                </div>
                <div class="flex items-start gap-2.5">
                    <div class="text-teal-400 text-xs mt-1"><i class="fas fa-bolt"></i></div>
                    <div>
                        <h4 class="text-xs font-bold text-white">100ms Load Speed</h4>
                        <p class="text-[11px] text-slate-500 mt-0.5">Precompiled Static Caching</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer signature -->
        <div class="text-xs text-slate-600">
            &copy; <?php echo date('Y'); ?> WebCraft Open-Source. MIT Licensed.
        </div>
    </div>

    <!-- RIGHT COVER: UNIFIED LOGIN / REGISTER FORMS -->
    <div class="w-full md:w-[500px] bg-slate-900/40 p-8 md:p-16 flex flex-col justify-center shrink-0">
        <div class="w-full max-w-sm mx-auto">

            <!-- STATE MESSAGES -->
            <?php if (!empty($error)): ?>
            <div class="bg-red-950/40 border border-red-500/30 text-red-300 rounded-lg p-4 mb-6 flex items-center gap-3 text-xs">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo sanitize_output($error); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
            <div class="bg-emerald-950/40 border border-emerald-500/30 text-emerald-300 rounded-lg p-4 mb-6 flex items-center gap-3 text-xs">
                <i class="fas fa-check-circle"></i>
                <span><?php echo sanitize_output($success); ?></span>
            </div>
            <?php endif; ?>

            <!-- FORM 1: LOGIN -->
            <?php if ($action === 'login'): ?>
            <div>
                <h2 class="text-2xl font-black text-white tracking-tight">Access Dashboard</h2>
                <p class="text-slate-400 text-xs mt-1.5">Sign in to your open-source commercial builder portal.</p>

                <form action="auth.php?auth_action=login" method="POST" class="space-y-4 mt-8">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div>
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Username or Email</label>
                        <input type="text" name="username_or_email" required placeholder="e.g., admin" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-3 text-xs text-white focus:outline-none focus:border-teal-500">
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Password</label>
                        </div>
                        <input type="password" name="password" required placeholder="••••••••" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-3 text-xs text-white focus:outline-none focus:border-teal-500">
                    </div>
                    <button type="submit" class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-3 rounded-lg text-xs transition duration-300 shadow-md shadow-teal-500/5">
                        Authenticate Account
                    </button>
                </form>

                <div class="text-center mt-6 pt-6 border-t border-slate-800/60 text-xs text-slate-500">
                    Don't have an account? <a href="index.php?action=register" class="text-teal-400 font-bold hover:underline">Register standard builder</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- FORM 2: REGISTER -->
            <?php if ($action === 'register'): ?>
            <div>
                <h2 class="text-2xl font-black text-white tracking-tight">Create Account</h2>
                <p class="text-slate-400 text-xs mt-1.5">Configure developer access credential details below.</p>

                <form action="auth.php?auth_action=register" method="POST" class="space-y-4 mt-8">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div>
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Username</label>
                        <input type="text" name="username" required placeholder="e.g., jsmith" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-3 text-xs text-white focus:outline-none focus:border-teal-500">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Email Address</label>
                        <input type="email" name="email" required placeholder="e.g., john@example.com" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-3 text-xs text-white focus:outline-none focus:border-teal-500">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Password</label>
                        <input type="password" name="password" required placeholder="Minimum 6 characters" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-3 text-xs text-white focus:outline-none focus:border-teal-500">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Confirm Password</label>
                        <input type="password" name="confirm_password" required placeholder="Re-enter password" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-3 text-xs text-white focus:outline-none focus:border-teal-500">
                    </div>
                    <button type="submit" class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-3 rounded-lg text-xs transition duration-300">
                        Create Developer Account
                    </button>
                </form>

                <div class="text-center mt-6 pt-6 border-t border-slate-800/60 text-xs text-slate-500">
                    Already have an account? <a href="index.php?action=login" class="text-teal-400 font-bold hover:underline">Log in here</a>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>
