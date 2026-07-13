<?php
/**
 * WebCraft Live Interactive Workspace
 * Supports dynamic dragging, custom style configurations, raw HTML editing, and dynamic views
 */
require_once __DIR__ . '/config.php';
require_login();

$project_id = $_GET['project_id'] ?? null;
if (!$project_id) {
    header("Location: admin.php?error=" . urlencode("No project selected."));
    exit;
}

$db = get_db_connection();
$stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$stmt->execute([$project_id, $_SESSION['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    header("Location: admin.php?error=" . urlencode("Project not found or access denied."));
    exit;
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebCraft Builder - Editing: <?php echo sanitize_output($project['name']); ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Premium Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #0f172a;
        }
        ::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #475569;
        }
        /* Active Component visual cue */
        .component-selected {
            outline: 3px solid #14b8a6 !important;
            outline-offset: 2px;
            position: relative;
        }
        /* Canvas controls when dragging over */
        .canvas-dragover {
            background-color: rgba(20, 184, 166, 0.05) !important;
            border: 2px dashed #14b8a6 !important;
        }
    </style>
</head>
<body class="h-full flex flex-col text-slate-100 overflow-hidden font-sans">

    <!-- TOP CONTROL BAR -->
    <header class="bg-slate-900 border-b border-slate-800 h-16 px-6 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3">
            <a href="admin.php" class="text-slate-400 hover:text-white transition duration-200">
                <i class="fas fa-arrow-left text-lg"></i>
            </a>
            <span class="h-5 w-[1px] bg-slate-700"></span>
            <div>
                <h1 class="text-sm font-bold tracking-tight text-white leading-none"><?php echo sanitize_output($project['name']); ?></h1>
                <p class="text-[11px] text-slate-400 mt-1">Status: <span id="status-badge" class="font-semibold uppercase text-teal-400"><?php echo sanitize_output(ucfirst($project['status'])); ?></span></p>
            </div>
        </div>

        <!-- RESPONSIVE PREVIEW CONTROLS -->
        <div class="hidden sm:flex bg-slate-950 p-1 rounded-lg border border-slate-800 space-x-1">
            <button id="view-desktop" onclick="setCanvasView('desktop')" class="px-3 py-1.5 rounded text-xs font-bold transition duration-200 bg-slate-800 text-teal-400">
                <i class="fas fa-desktop mr-1.5"></i> Desktop
            </button>
            <button id="view-tablet" onclick="setCanvasView('tablet')" class="px-3 py-1.5 rounded text-xs font-bold transition duration-200 text-slate-400 hover:text-white">
                <i class="fas fa-tablet-alt mr-1.5"></i> Tablet
            </button>
            <button id="view-mobile" onclick="setCanvasView('mobile')" class="px-3 py-1.5 rounded text-xs font-bold transition duration-200 text-slate-400 hover:text-white">
                <i class="fas fa-mobile-alt mr-1.5"></i> Mobile
            </button>
        </div>

        <!-- ACTIONS -->
        <div class="flex items-center gap-2">
            <button onclick="saveProject(false)" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold px-4 py-2 rounded text-xs flex items-center gap-1.5 transition">
                <i class="fas fa-save"></i> Save Draft
            </button>
            <button onclick="exportProjectZip()" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold px-4 py-2 rounded text-xs flex items-center gap-1.5 transition" title="Export as standalone ZIP archive">
                <i class="fas fa-file-archive text-teal-400"></i> Download ZIP
            </button>
            <button onclick="publishProject()" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-4 py-2 rounded text-xs flex items-center gap-1.5 transition shadow-md shadow-teal-500/10">
                <i class="fas fa-globe"></i> Publish Site
            </button>
        </div>
    </header>

    <!-- WRAPPER -->
    <div class="flex flex-1 overflow-hidden">

        <!-- WIDGETS SIDEBAR (LEFT) -->
        <aside class="w-80 bg-slate-900 border-r border-slate-800 flex flex-col overflow-hidden shrink-0">
            <div class="p-4 border-b border-slate-800 bg-slate-900/50">
                <h2 class="text-xs font-extrabold text-teal-400 uppercase tracking-widest">Components Shelf</h2>
                <p class="text-[11px] text-slate-400 mt-1">Drag and drop components straight onto the web canvas.</p>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-4" id="components-shelf">
                <!-- Javascript will dynamically build components categorized -->
            </div>
        </aside>

        <!-- CANVAS AREA (CENTER) -->
        <main class="flex-1 bg-slate-950 overflow-y-auto p-8 flex justify-center items-start transition-all" id="canvas-wrapper">
            <!-- Dynamic resizing container -->
            <div id="canvas-container" class="w-full h-auto min-h-[500px] bg-slate-900 rounded-xl shadow-2xl transition-all duration-300 relative border-2 border-slate-800 p-4" ondragover="event.preventDefault();" ondragenter="this.classList.add('canvas-dragover');" ondragleave="this.classList.remove('canvas-dragover');" ondrop="handleCanvasDrop(event)">
                <div id="canvas-empty-state" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center pointer-events-none">
                    <div class="w-16 h-16 bg-slate-800 rounded-full flex items-center justify-center text-slate-600 text-2xl mb-4 border border-slate-700">
                        <i class="fas fa-mouse-pointer"></i>
                    </div>
                    <h3 class="font-bold text-slate-400 text-sm">Your Canvas is Empty</h3>
                    <p class="text-slate-500 text-xs mt-1.5 max-w-xs leading-relaxed">Drag any visual component from the left shelf and release it here to initiate your live workspace build.</p>
                </div>
                <!-- Dynamic loaded content container -->
                <div id="canvas-content" class="space-y-4"></div>
            </div>
        </main>

        <!-- CONTROL PANEL (RIGHT) -->
        <aside class="w-80 bg-slate-900 border-l border-slate-800 flex flex-col overflow-hidden shrink-0">
            <div class="p-4 border-b border-slate-800">
                <h2 class="text-xs font-extrabold text-teal-400 uppercase tracking-widest">Control Center</h2>
                <p class="text-[11px] text-slate-400 mt-1">Adjust layout properties & custom injects.</p>
            </div>

            <!-- TAB SWITCHER -->
            <div class="flex border-b border-slate-800 bg-slate-950/40 shrink-0">
                <button id="control-tab-btn-properties" onclick="switchControlPanelTab('properties')" class="flex-1 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider border-b-2 border-teal-500 text-teal-400">
                    Properties
                </button>
                <button id="control-tab-btn-settings" onclick="switchControlPanelTab('settings')" class="flex-1 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider border-b-2 border-transparent text-slate-400 hover:text-white">
                    Project CSS/JS
                </button>
            </div>

            <!-- DYNAMIC EDITING CONTROLS -->
            <div class="flex-1 overflow-y-auto p-4 space-y-5" id="property-panel">
                <div id="no-selection-state" class="text-center py-12 text-slate-500">
                    <i class="fas fa-hand-pointer text-xl mb-3"></i>
                    <p class="text-xs">No component selected.</p>
                    <p class="text-[11px] text-slate-600 mt-1 leading-relaxed">Click any element in your workspace to reveal visual options.</p>
                </div>

                <!-- SELECTION EDITING FORM -->
                <div id="selection-controls" class="hidden space-y-4">
                    <div>
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Selected Element</label>
                        <div class="bg-slate-950 px-3 py-2 rounded text-xs text-teal-400 font-mono flex justify-between items-center">
                            <span id="selected-component-type">Navbar</span>
                            <button onclick="deleteSelectedComponent()" class="text-red-400 hover:text-red-300 ml-2" title="Delete Component">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>

                    <hr class="border-slate-800">

                    <!-- NAVBAR CUSTOMIZATION EXTENSION -->
                    <div id="navbar-custom-section" class="hidden space-y-4">
                        <h4 class="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1">
                            <i class="fas fa-bars"></i> Navbar Customizer
                        </h4>
                        <div>
                            <label class="text-[11px] text-slate-400 block mb-1">Brand Name / Logo Text</label>
                            <input type="text" id="navbar-brand-text" oninput="updateNavbarLogoText(this.value)" class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500">
                        </div>
                        <div>
                            <label class="text-[11px] text-slate-400 block mb-1">Brand Logo Image URL (Optional)</label>
                            <input type="text" id="navbar-logo-img" oninput="updateNavbarLogoImage(this.value)" class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500" placeholder="https://example.com/logo.png">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[11px] text-slate-400 block font-bold">Manage Navigation Links / Tabs</label>
                            <div id="navbar-links-list" class="space-y-2">
                                <!-- Dynamic Links Rows -->
                            </div>
                            <button onclick="addNavbarLinkRow()" class="w-full bg-slate-850 hover:bg-slate-800 text-teal-400 font-bold py-1.5 rounded text-[11px] border border-teal-500/10 transition">
                                <i class="fas fa-plus"></i> Add Nav Link / Tab
                            </button>
                        </div>
                    </div>

                    <!-- FOOTER CUSTOMIZATION EXTENSION -->
                    <div id="footer-custom-section" class="hidden space-y-4">
                        <h4 class="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1">
                            <i class="fas fa-shoe-prints"></i> Footer Customizer
                        </h4>
                        <div>
                            <label class="text-[11px] text-slate-400 block mb-1">Footer Brand Text</label>
                            <input type="text" id="footer-brand-text" oninput="updateFooterBrandText(this.value)" class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500">
                        </div>
                        <div>
                            <label class="text-[11px] text-slate-400 block mb-1">Footer Logo Image URL (Optional)</label>
                            <input type="text" id="footer-logo-img" oninput="updateFooterLogoImage(this.value)" class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500" placeholder="https://example.com/footer-logo.png">
                        </div>
                        <div>
                            <label class="text-[11px] text-slate-400 block mb-1">Copyright Note</label>
                            <input type="text" id="footer-copyright" oninput="updateFooterCopyright(this.value)" class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[11px] text-slate-400 block font-bold">Manage Footer Links</label>
                            <div id="footer-links-list" class="space-y-2">
                                <!-- Dynamic Links Rows -->
                            </div>
                            <button onclick="addFooterLinkRow()" class="w-full bg-slate-850 hover:bg-slate-800 text-teal-400 font-bold py-1.5 rounded text-[11px] border border-teal-500/10 transition">
                                <i class="fas fa-plus"></i> Add Footer Link
                            </button>
                        </div>
                    </div>

                    <hr class="border-slate-800">

                    <!-- TEXT / HEADING CUSTOMIZATION -->
                    <div id="prop-group-text" class="space-y-3">
                        <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Content Editor</h4>
                        <div>
                            <label class="text-[11px] text-slate-400 block mb-1">Aesthetic Heading / Title</label>
                            <input type="text" id="prop-heading-text" oninput="updateActiveElementContent('h1, h2, h3', this.value)" class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500">
                        </div>
                        <div>
                            <label class="text-[11px] text-slate-400 block mb-1">Body/Paragraph Text</label>
                            <textarea id="prop-paragraph-text" rows="3" oninput="updateActiveElementContent('p', this.value)" class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500"></textarea>
                        </div>
                    </div>

                    <hr class="border-slate-800">

                    <!-- DESIGN STYLING -->
                    <div class="space-y-3">
                        <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Design & Layout Spacers</h4>
                        <div>
                            <label class="text-[11px] text-slate-400 block mb-1">Align Text</label>
                            <select id="prop-align" onchange="updateActiveElementClass(['text-left', 'text-center', 'text-right'], this.value)" class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500">
                                <option value="">Default</option>
                                <option value="text-left">Left</option>
                                <option value="text-center">Center</option>
                                <option value="text-right">Right</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[11px] text-slate-400 block mb-1">Padding Spacer (Vertical)</label>
                            <select id="prop-padding" onchange="updateActiveElementClass(['py-8', 'py-12', 'py-16', 'py-20', 'py-24'], this.value)" class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500">
                                <option value="">Default</option>
                                <option value="py-8">Small (py-8)</option>
                                <option value="py-12">Medium (py-12)</option>
                                <option value="py-16">Standard (py-16)</option>
                                <option value="py-20">Spacious (py-20)</option>
                                <option value="py-24">Enterprise Large (py-24)</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[11px] text-slate-400 block mb-1">Theme Color Accents</label>
                            <select id="prop-accent" onchange="updateActiveElementClass(['bg-slate-900', 'bg-slate-50', 'bg-white', 'bg-teal-500/10', 'bg-teal-900', 'bg-indigo-950'], this.value)" class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500">
                                <option value="">Default Theme</option>
                                <option value="bg-slate-900">Dark Slate (bg-slate-900)</option>
                                <option value="bg-indigo-950">Deep Indigo (bg-indigo-950)</option>
                                <option value="bg-slate-50">Light Gray (bg-slate-50)</option>
                                <option value="bg-white">Pure White (bg-white)</option>
                                <option value="bg-teal-900">Rich Teal Dark (bg-teal-900)</option>
                            </select>
                        </div>
                    </div>

                    <hr class="border-slate-800">

                    <!-- LOW CODE RAW HTML EXTENSION -->
                    <div id="low-code-html-section" class="hidden space-y-3">
                        <h4 class="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1">
                            <i class="fas fa-terminal"></i> Low-Code HTML Editor
                        </h4>
                        <p class="text-[10px] text-slate-400">Directly modify raw container elements. Dynamic scripts are restricted.</p>
                        <textarea id="prop-raw-html" rows="8" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-xs font-mono text-emerald-400 focus:outline-none focus:border-teal-500" placeholder="<div class='bg-red-500 p-4'>Custom block</div>"></textarea>
                        <button onclick="applyRawHtml()" class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold py-2 rounded text-xs transition">
                            Apply Custom HTML
                        </button>
                    </div>
                </div>
            </div>

            <!-- PROJECT SETTINGS PANEL -->
            <div class="flex-1 overflow-y-auto p-4 space-y-5 hidden" id="settings-panel">
                <div class="space-y-4">
                    <h4 class="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fas fa-sliders-h"></i> Project Custom Injection
                    </h4>
                    <p class="text-[10px] text-slate-400 leading-relaxed">Inject customized stylesheet styling parameters and client-side behavioral callbacks directly into compiled pages.</p>

                    <div>
                        <label class="text-[11px] text-slate-400 block mb-1">Custom CSS Injection</label>
                        <textarea id="project-custom-css" rows="6" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-xs font-mono text-cyan-400 focus:outline-none focus:border-teal-500" placeholder="body { background-color: #0b0f19; }"></textarea>
                    </div>

                    <div>
                        <label class="text-[11px] text-slate-400 block mb-1">Custom JS Injection</label>
                        <textarea id="project-custom-js" rows="6" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-xs font-mono text-emerald-400 focus:outline-none focus:border-teal-500" placeholder="console.log('WebCraft Custom script active');"></textarea>
                    </div>

                    <button onclick="saveProject(false)" class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold py-2.5 rounded text-xs transition">
                        Apply & Save Settings
                    </button>
                </div>
            </div>

        </aside>
    </div>

    <!-- NOTIFICATION SYSTEM -->
    <div id="notification-toast" class="fixed bottom-6 right-6 bg-slate-900 border border-teal-500 text-white rounded-lg p-4 shadow-2xl flex items-center gap-3 max-w-sm transform translate-y-24 opacity-0 transition-all duration-300 z-50">
        <div class="bg-teal-500/20 text-teal-400 p-2 rounded-full text-sm">
            <i class="fas fa-info-circle"></i>
        </div>
        <div>
            <h4 class="text-xs font-bold" id="notification-title">System Toast</h4>
            <p class="text-[11px] text-slate-400 mt-0.5" id="notification-desc">Operational detail displayed here.</p>
        </div>
    </div>

    <!-- BACKEND METADATA WRAPPERS -->
    <script>
        const PROJECT_ID = <?php echo (int)$project['id']; ?>;
        const CSRF_TOKEN = "<?php echo $csrf_token; ?>";
        const LOADED_CONTENT_STATE = <?php echo json_encode($project['content_json'] ?? '[]'); ?>;
    </script>

    <!-- COMPONENTS SOURCE -->
    <script src="assets/js/components.js"></script>
    <!-- BUILDER CORE RUNTIME -->
    <script src="assets/js/builder.js"></script>

</body>
</html>
