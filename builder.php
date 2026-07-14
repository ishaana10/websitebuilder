<?php
/**
 * WebCraft Live Interactive Workspace v2.0
 * Supports dynamic dragging, block palette, properties panel, and responsive preview.
 * JS logic lives in assets/js/builder.js (WCBuilder module)
 * Component registry lives in assets/js/components.js (WCComponents)
 */
require_once __DIR__ . '/config.php';
require_login();

$project_id = $_GET['project_id'] ?? null;
if (!$project_id) {
    header('Location: admin.php?error=' . urlencode('No project selected.'));
    exit;
}

$db = get_db_connection();
$stmt = $db->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$project_id, $_SESSION['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: admin.php?error=' . urlencode('Project not found or access denied.'));
    exit;
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebCraft Builder &mdash; <?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
        .component-selected {
            outline: 3px solid #14b8a6 !important;
            outline-offset: 2px;
        }
        .canvas-dragover {
            background-color: rgba(20,184,166,0.05) !important;
            border: 2px dashed #14b8a6 !important;
        }
        .wc-block { transition: outline 0.1s; }
        .wc-shelf-item:active { cursor: grabbing; }
    </style>
</head>
<body class="h-full flex flex-col text-slate-100 overflow-hidden font-sans">

    <!-- TOP CONTROL BAR -->
    <header class="bg-slate-900 border-b border-slate-800 h-16 px-6 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3">
            <a href="admin.php" class="text-slate-400 hover:text-white transition">
                <i class="fas fa-arrow-left text-lg"></i>
            </a>
            <span class="h-5 w-[1px] bg-slate-700"></span>
            <div>
                <h1 class="text-sm font-bold tracking-tight text-white leading-none"><?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?></h1>
                <p class="text-[11px] text-slate-400 mt-1">Status: <span id="status-badge" class="font-semibold uppercase text-teal-400"><?php echo htmlspecialchars(ucfirst($project['status']), ENT_QUOTES); ?></span></p>
            </div>
        </div>

        <!-- RESPONSIVE PREVIEW CONTROLS -->
        <div class="hidden sm:flex bg-slate-950 p-1 rounded-lg border border-slate-800 space-x-1">
            <button id="view-desktop" onclick="setCanvasView('desktop')" class="px-3 py-1.5 rounded text-xs font-bold transition bg-slate-800 text-teal-400">
                <i class="fas fa-desktop mr-1.5"></i> Desktop
            </button>
            <button id="view-tablet" onclick="setCanvasView('tablet')" class="px-3 py-1.5 rounded text-xs font-bold transition text-slate-400 hover:text-white">
                <i class="fas fa-tablet-alt mr-1.5"></i> Tablet
            </button>
            <button id="view-mobile" onclick="setCanvasView('mobile')" class="px-3 py-1.5 rounded text-xs font-bold transition text-slate-400 hover:text-white">
                <i class="fas fa-mobile-alt mr-1.5"></i> Mobile
            </button>
        </div>

        <!-- ACTIONS -->
        <div class="flex items-center gap-2">
            <button onclick="saveProject(false)" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold px-4 py-2 rounded text-xs flex items-center gap-1.5 transition">
                <i class="fas fa-save"></i> Save Draft
            </button>
            <button onclick="exportProjectZip()" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold px-4 py-2 rounded text-xs flex items-center gap-1.5 transition" title="Export as ZIP">
                <i class="fas fa-file-archive text-teal-400"></i> Export ZIP
            </button>
            <button onclick="publishProject()" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-4 py-2 rounded text-xs flex items-center gap-1.5 transition shadow-md shadow-teal-500/10">
                <i class="fas fa-globe"></i> Publish Site
            </button>
        </div>
    </header>

    <!-- MAIN WRAPPER -->
    <div class="flex flex-1 overflow-hidden">

        <!-- COMPONENT SHELF (LEFT) -->
        <aside class="w-72 bg-slate-900 border-r border-slate-800 flex flex-col overflow-hidden shrink-0">
            <div class="p-4 border-b border-slate-800 bg-slate-900/50">
                <h2 class="text-xs font-extrabold text-teal-400 uppercase tracking-widest">Components</h2>
                <p class="text-[11px] text-slate-400 mt-1">Drag components onto the canvas.</p>
            </div>
            <div class="flex-1 overflow-y-auto p-4 space-y-5" id="components-shelf">
                <!-- Dynamically populated by WCBuilder.buildComponentShelf() -->
            </div>
        </aside>

        <!-- CANVAS (CENTER) -->
        <main class="flex-1 bg-slate-950 overflow-y-auto p-8 flex justify-center items-start" id="canvas-wrapper">
            <div
                id="canvas-container"
                class="w-full min-h-[500px] bg-slate-900 rounded-xl shadow-2xl transition-all duration-300 relative border-2 border-slate-800 p-4"
                ondragover="event.preventDefault();"
                ondragenter="this.classList.add('canvas-dragover');"
                ondragleave="this.classList.remove('canvas-dragover');"
                ondrop="handleCanvasDrop(event)"
            >
                <!-- Empty state -->
                <div id="canvas-empty-state" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center pointer-events-none">
                    <div class="w-16 h-16 bg-slate-800 rounded-full flex items-center justify-center text-slate-600 text-2xl mb-4 border border-slate-700">
                        <i class="fas fa-mouse-pointer"></i>
                    </div>
                    <h3 class="font-bold text-slate-400 text-sm">Canvas is Empty</h3>
                    <p class="text-slate-500 text-xs mt-1.5 max-w-xs leading-relaxed">Drag a component from the left shelf to start building.</p>
                </div>
                <!-- Rendered blocks -->
                <div id="canvas-content" class="space-y-2"></div>
            </div>
        </main>

        <!-- PROPERTIES PANEL (RIGHT) -->
        <aside class="w-72 bg-slate-900 border-l border-slate-800 flex flex-col overflow-hidden shrink-0">
            <div class="p-4 border-b border-slate-800">
                <h2 class="text-xs font-extrabold text-teal-400 uppercase tracking-widest">Properties</h2>
                <p class="text-[11px] text-slate-400 mt-1">Select a block to edit its settings.</p>
            </div>

            <!-- TAB SWITCHER -->
            <div class="flex border-b border-slate-800 bg-slate-950/40 shrink-0">
                <button id="control-tab-btn-properties" onclick="switchControlPanelTab('properties')" class="flex-1 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider border-b-2 border-teal-500 text-teal-400">
                    Properties
                </button>
                <button id="control-tab-btn-settings" onclick="switchControlPanelTab('settings')" class="flex-1 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider border-b-2 border-transparent text-slate-400 hover:text-white">
                    CSS / JS
                </button>
            </div>

            <!-- PROPERTIES TAB -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4" id="property-panel">

                <!-- No-selection state -->
                <div id="no-selection-state" class="text-center py-12 text-slate-500">
                    <i class="fas fa-hand-pointer text-xl mb-3"></i>
                    <p class="text-xs">No block selected.</p>
                    <p class="text-[11px] text-slate-600 mt-1 leading-relaxed">Click any block on the canvas to edit it.</p>
                </div>

                <!-- Selection editing — shown when a block is selected -->
                <div id="selection-controls" class="hidden space-y-4">

                    <!-- Selected element header -->
                    <div>
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Selected Block</label>
                        <div class="bg-slate-950 px-3 py-2 rounded text-xs text-teal-400 font-mono flex justify-between items-center">
                            <span id="selected-component-type">—</span>
                            <button onclick="deleteSelectedComponent()" class="text-red-400 hover:text-red-300 ml-2" title="Delete Block">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>

                    <hr class="border-slate-800">

                    <!-- Dynamic prop fields injected here by WCBuilder.renderPropertiesPanel() -->
                    <!-- id="dynamic-prop-fields" is created/managed by builder.js -->

                    <hr class="border-slate-800">

                    <!-- Common layout overrides -->
                    <div class="space-y-3">
                        <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Raw HTML Override</h4>
                        <p class="text-[10px] text-slate-500 leading-relaxed">Use the Custom HTML block type from the shelf for free-form HTML injection.</p>
                    </div>

                </div>
            </div>

            <!-- SETTINGS TAB (CSS/JS injection) -->
            <div class="flex-1 overflow-y-auto p-4 space-y-5 hidden" id="settings-panel">
                <div class="space-y-4">
                    <h4 class="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fas fa-sliders-h"></i> Page CSS / JS Injection
                    </h4>
                    <p class="text-[10px] text-slate-400 leading-relaxed">Injected into the rendered page &lt;head&gt; and before &lt;/body&gt;.</p>
                    <div>
                        <label class="text-[11px] text-slate-400 block mb-1">Custom CSS</label>
                        <textarea id="project-custom-css" rows="7" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-xs font-mono text-cyan-400 focus:outline-none focus:border-teal-500" placeholder="body { background: #0b0f19; }"></textarea>
                    </div>
                    <div>
                        <label class="text-[11px] text-slate-400 block mb-1">Custom JS</label>
                        <textarea id="project-custom-js" rows="7" class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-xs font-mono text-emerald-400 focus:outline-none focus:border-teal-500" placeholder="console.log('WebCraft loaded');"></textarea>
                    </div>
                    <button onclick="saveProject(false)" class="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold py-2.5 rounded text-xs transition">
                        Save Settings
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <!-- TOAST NOTIFICATION -->
    <div id="notification-toast" class="fixed bottom-6 right-6 bg-slate-900 border border-teal-500 text-white rounded-lg p-4 shadow-2xl flex items-center gap-3 max-w-sm transform translate-y-24 opacity-0 transition-all duration-300 z-50">
        <div class="p-2 rounded-full text-sm bg-teal-500/20 text-teal-400">
            <i class="fas fa-info-circle"></i>
        </div>
        <div>
            <h4 class="text-xs font-bold" id="notification-title">Notification</h4>
            <p class="text-[11px] text-slate-400 mt-0.5" id="notification-desc">—</p>
        </div>
    </div>

    <!-- BACKEND METADATA -->
    <script>
        const PROJECT_ID          = <?php echo (int)$project['id']; ?>;
        const CSRF_TOKEN          = "<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>";
        const LOADED_CONTENT_STATE = <?php echo json_encode($project['content_json'] ?? '{}'); ?>;
    </script>

    <!-- Load component registry first, then builder core -->
    <script src="assets/js/components.js"></script>
    <script src="assets/js/builder.js"></script>

</body>
</html>
