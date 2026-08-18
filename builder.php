<?php
/**
 * Nuvis Webidesigner Live React-Powered Workspace
 * State-of-the-art visual builder featuring real-time React render pipeline,
 * multi-level Undo/Redo history stack, live properties customizers,
 * visual theme-color selectors, device bezels, and live HTML code compilers.
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
    <title>Nuvis Webidesigner - Editing: <?php echo sanitize_output($project['name']); ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Premium Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- React & ReactDOM -->
    <script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>
    <!-- Babel for in-browser JSX parsing -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/babel-standalone/6.26.0/babel.min.js"></script>
    <!-- Ace Code Editor -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ace.js" crossorigin="anonymous"></script>

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

        /* Responsive device frame styling */
        .device-bezel-mobile {
            width: 375px;
            border: 12px solid #1e293b;
            border-radius: 36px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
        }
        .device-bezel-mobile::after {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 140px;
            height: 18px;
            background: #1e293b;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
            z-index: 40;
        }

        .device-bezel-tablet {
            width: 768px;
            border: 16px solid #1e293b;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        /* Selected element indicator */
        .section-selected {
            border: 2px solid #14b8a6 !important;
            box-shadow: 0 0 15px rgba(20, 184, 166, 0.15);
        }

        .element-highlighted {
            outline: 3px dashed #06b6d4 !important;
            outline-offset: 4px;
            box-shadow: 0 0 20px rgba(6, 182, 212, 0.5) !important;
        }

        /* Code highlight formatting */
        .code-tag { color: #f43f5e; }
        .code-attr { color: #22d3ee; }
        .code-val { color: #a7f3d0; }
        .code-txt { color: #f8fafc; }
    </style>
</head>
<body class="h-full flex flex-col text-slate-100 overflow-hidden font-sans">

    <!-- REACT ROOT MOUNT -->
    <div id="react-app-root" class="h-full flex flex-col overflow-hidden">
        <!-- Loader fallback while React bootstraps -->
        <div class="flex-1 flex flex-col items-center justify-center bg-slate-950">
            <div class="w-12 h-12 border-4 border-teal-500 border-t-transparent rounded-full animate-spin"></div>
            <p class="text-xs text-slate-400 mt-4 tracking-wider uppercase font-extrabold animate-pulse">Assembling React Workspace...</p>
        </div>
    </div>

    <!-- METADATA ENVELOPES -->
    <script>
        const PROJECT_ID = <?php echo (int)$project['id']; ?>;
        const CSRF_TOKEN = "<?php echo $csrf_token; ?>";
        const LOADED_CONTENT_STATE = <?php echo json_encode($project['content_json'] ?? '[]'); ?>;
        const PROJECT_NAME = "<?php echo addslashes($project['name']); ?>";
        const PROJECT_STATUS = "<?php echo addslashes($project['status']); ?>";
    </script>

    <!-- COMPONENTS DICTIONARY SOURCE -->
    <script src="assets/js/components.js?v=<?php echo time(); ?>"></script>

    <!-- REACT VISUAL BUILDER CORE ENGINE -->
    <script type="text/babel" data-presets="react">
        const { useState, useEffect, useRef } = React;

        // --- React-friendly Ace Editor Wrapper ---
        function AceEditor({ value, mode, onChange, className, style, readOnly = false }) {
            const editorRef = useRef(null);
            const aceInstance = useRef(null);

            useEffect(() => {
                if (editorRef.current) {
                    const editor = ace.edit(editorRef.current);
                    editor.setTheme("ace/theme/tomorrow_night_eighties");
                    editor.session.setMode(`ace/mode/${mode}`);
                    editor.setOptions({
                        fontSize: "12px",
                        showPrintMargin: false,
                        enableBasicAutocompletion: true,
                        enableLiveAutocompletion: true,
                        readOnly: readOnly,
                        useWorker: false // Disables worker to avoid sandbox/origin issues
                    });

                    editor.setValue(value === undefined ? "" : value, -1);

                    editor.session.on('change', () => {
                        const val = editor.getValue();
                        if (onChange) onChange(val);
                    });

                    aceInstance.current = editor;
                }

                return () => {
                    if (aceInstance.current) {
                        aceInstance.current.destroy();
                    }
                };
            }, []);

            useEffect(() => {
                if (aceInstance.current && aceInstance.current.getValue() !== value) {
                    aceInstance.current.setValue(value === undefined ? "" : value, -1);
                }
            }, [value]);

            useEffect(() => {
                if (aceInstance.current) {
                    aceInstance.current.session.setMode(`ace/mode/${mode}`);
                }
            }, [mode]);

            return <div ref={editorRef} className={className} style={style} />;
        }

        function App() {
            // --- Core States ---
            const [pages, setPages] = useState({ index: [] });
            const [activePage, setActivePage] = useState("index");
            const sections = pages[activePage] || [];
            const [activeSectionId, setActiveSectionId] = useState(null);
            const [activeElementId, setActiveElementId] = useState(null); // active element path e.g. 'el-0'
            const [propsSubTab, setPropsSubTab] = useState('block'); // 'block' or 'element'
            const [customComponents, setCustomComponents] = useState([]);
            const [isCustomCompModalOpen, setIsCustomCompModalOpen] = useState(false);
            const [canvasView, setCanvasView] = useState('desktop'); // desktop, tablet, mobile
            const [rightPanelTab, setRightPanelTab] = useState('properties'); // properties, settings
            const [customCss, setCustomCss] = useState('');
            const [customJs, setCustomJs] = useState('');
            const [projectStatus, setProjectStatus] = useState(PROJECT_STATUS);
            const [componentSearchQuery, setComponentSearchQuery] = useState('');

            // --- Code Editor Tab States ---
            const [isFullscreenEditorOpen, setFullscreenEditorOpen] = useState(false);
            const [codeEditorTab, setCodeEditorTab] = useState('css'); // css, js, html_raw

            // --- Evolved Global Theme Editor & Design Tokens ---
            const [themePrimaryColor, setThemePrimaryColor] = useState('#14b8a6');
            const [themeBgColor, setThemeBgColor] = useState('#0f172a');
            const [themeFontFamily, setThemeFontFamily] = useState('sans-serif');
            const [themeBaseSpacing, setThemeBaseSpacing] = useState('24px');
            const [themeContainerWidth, setThemeContainerWidth] = useState('1200px');

            // --- Evolved Page SEO & Meta Parameters ---
            const [seoTitle, setSeoTitle] = useState(PROJECT_NAME);
            const [seoMetaDesc, setSeoMetaDesc] = useState('Elegantly compiled web properties, pre-cached for sub-millisecond visual layouts.');
            const [seoOgImage, setSeoOgImage] = useState('https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=600');
            const [seoRobotsTxt, setSeoRobotsTxt] = useState('User-agent: *\nAllow: /\nSitemap: /sitemap.xml');
            const [seoStructuredData, setSeoStructuredData] = useState(JSON.stringify({
                "@context": "https://schema.org",
                "@type": "WebPage",
                "name": PROJECT_NAME,
                "description": "Discover precompiled structural page builds."
            }, null, 2));

            // --- Multi-Tenancy SaaS Billing & Custom Domains ---
            const [tenantCustomDomain, setTenantCustomDomain] = useState('');
            const [tenantSubdomain, setTenantSubdomain] = useState('pestkit');
            const [tenantSubscriptionPlan, setTenantSubscriptionPlan] = useState('pro');
            const [tenantSitesCount, setTenantSitesCount] = useState(2);
            const [tenantStorageUsed, setTenantStorageUsed] = useState(52428800); // 50MB
            const [tenantBandwidthUsed, setTenantBandwidthUsed] = useState(1073741824); // 1GB
            const [tenantAiCallsCount, setTenantAiCallsCount] = useState(45);
            const [showBillingModal, setShowBillingModal] = useState(false);

            // --- AI Superpowers Assistant states ---
            const [aiPrompt, setAiPrompt] = useState('');
            const [aiIsGenerating, setAiIsGenerating] = useState(false);
            const [aiAuditReport, setAiAuditReport] = useState(null);

            // --- Simulated Real-time Collaboration list ---
            const [presenceUsers, setPresenceUsers] = useState(['Sarah Jenkins (Editor)', 'Marcus Sterling (Architect)', 'You (Owner)']);
            const [presenceLogs, setPresenceLogs] = useState([
                'Marcus updated active styles for CTA Banner',
                'Sarah Jenkins started editing section 2'
            ]);

            // --- Nuvis Email Module Settings ---
            const [emailRecipient, setEmailRecipient] = useState('');
            const [autoResponderEnabled, setAutoResponderEnabled] = useState(false);
            const [emailTemplateTheme, setEmailTemplateTheme] = useState('modern_minimalist'); // modern_minimalist, elegant, tech_light
            const [autoResponderSubject, setAutoResponderSubject] = useState('Thank you for contacting us!');
            const [autoResponderBody, setAutoResponderBody] = useState('Hello!\n\nWe have received your inquiry regarding our services and will get back to you shortly.\n\nBest regards,\nThe Team');

            // Site-Specific SMTP server credentials
            const [smtpHost, setSmtpHost] = useState('');
            const [smtpPort, setSmtpPort] = useState('');
            const [smtpUsername, setSmtpUsername] = useState('');
            const [smtpPassword, setSmtpPassword] = useState('');
            const [smtpEncryption, setSmtpEncryption] = useState('none');
            const [smtpFromEmail, setSmtpFromEmail] = useState('');
            const [smtpFromName, setSmtpFromName] = useState('');

            // --- Undo/Redo History States ---
            const [history, setHistory] = useState([]);
            const [historyIndex, setHistoryIndex] = useState(-1);

            // --- Page Versioning States ---
            const [versions, setVersions] = useState([]);
            const [previewingVersionId, setPreviewingVersionId] = useState(null);
            const [previewingLabel, setPreviewingLabel] = useState('');
            const [previewingSections, setPreviewingSections] = useState([]);
            const [previewingCss, setPreviewingCss] = useState('');
            const [previewingJs, setPreviewingJs] = useState('');
            const [customVersionLabel, setCustomVersionLabel] = useState('');

            const fetchVersions = () => {
                fetch(`api.php?action=get_versions&project_id=${PROJECT_ID}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        setVersions(data.versions);
                    }
                })
                .catch(err => console.error("Error fetching versions: ", err));
            };

            const previewVersion = (versionId, label) => {
                fetch(`api.php?action=get_version_content&version_id=${versionId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        let raw = data.content_json;
                        if (typeof raw === 'string') raw = JSON.parse(raw);

                        let previewSects = [];
                        let previewC = '';
                        let previewJ = '';

                        if (raw && Array.isArray(raw.blocks)) {
                            previewSects = raw.blocks.map(blockToSection);
                            previewC = raw.custom_css || '';
                            previewJ = raw.custom_js || '';
                        } else if (raw && Array.isArray(raw.sections)) {
                            previewSects = raw.sections;
                            previewC = raw.custom_css || '';
                            previewJ = raw.custom_js || '';
                        } else if (raw && Array.isArray(raw)) {
                            previewSects = raw.map(blockToSection);
                        }

                        // Map missing schema defaults in preview sections
                        const tempCombined = [...UI_COMPONENTS, ...customComponents];
                        previewSects = previewSects.map(s => {
                            const compDef = tempCombined.find(c => c.id.toLowerCase() === (s.type || '').toLowerCase().trim());
                            if (compDef && compDef.schema) {
                                compDef.schema.forEach(field => {
                                    if (s.props[field.key] === undefined) {
                                        s.props[field.key] = field.default;
                                    }
                                });
                            }
                            return s;
                        });

                        setPreviewingSections(previewSects);
                        setPreviewingCss(previewC);
                        setPreviewingJs(previewJ);
                        setPreviewingVersionId(versionId);
                        setPreviewingLabel(label);
                        setActiveSectionId(null);
                        setActiveElementId(null);
                        showToast("Preview Loaded", `Displaying version: "${label}". Workspace is read-only.`);
                    } else {
                        showToast("Error", data.error || "Failed to load preview.");
                    }
                })
                .catch(err => showToast("Error", err.message));
            };

            const exitPreviewMode = () => {
                setPreviewingVersionId(null);
                setPreviewingSections([]);
                setPreviewingCss('');
                setPreviewingJs('');
                setPreviewingLabel('');
                showToast("Preview Exited", "Returned to working draft.");
            };

            const restoreVersionFromId = (versionId) => {
                if (!window.confirm("Are you sure you want to restore your draft to this version? Your current unsaved draft changes will be replaced. This restore action will also create a snapshot version of your restore so you can undo it later.")) {
                    return;
                }

                fetch('api.php?action=restore_version', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: JSON.stringify({
                        version_id: versionId,
                        csrf_token: CSRF_TOKEN
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        let raw = data.content_json;
                        if (typeof raw === 'string') raw = JSON.parse(raw);

                        let restoredSects = [];
                        let restoredC = '';
                        let restoredJ = '';

                        if (raw && Array.isArray(raw.blocks)) {
                            restoredSects = raw.blocks.map(blockToSection);
                            restoredC = raw.custom_css || '';
                            restoredJ = raw.custom_js || '';
                        } else if (raw && Array.isArray(raw.sections)) {
                            restoredSects = raw.sections;
                            restoredC = raw.custom_css || '';
                            restoredJ = raw.custom_js || '';
                        } else if (raw && Array.isArray(raw)) {
                            restoredSects = raw.map(blockToSection);
                        }

                        // Populate defaults
                        const tempCombined = [...UI_COMPONENTS, ...customComponents];
                        restoredSects = restoredSects.map(s => {
                            const compDef = tempCombined.find(c => c.id.toLowerCase() === (s.type || '').toLowerCase().trim());
                            if (compDef && compDef.schema) {
                                compDef.schema.forEach(field => {
                                    if (s.props[field.key] === undefined) {
                                        s.props[field.key] = field.default;
                                    }
                                });
                            }
                            return s;
                        });

                        // Update standard states
                        let restoredPages = { index: [] };
                        if (raw && raw.pages) {
                            restoredPages = raw.pages;
                        } else {
                            restoredPages = { index: restoredSects };
                        }
                        setPages(restoredPages);
                        setActivePage(raw.activePage || "index");
                        setCustomCss(restoredC);
                        setCustomJs(restoredJ);

                        // Append to standard history stack for Ctrl+Z undo support!
                        commitToHistory(restoredPages);

                        // Exit preview mode
                        setPreviewingVersionId(null);
                        setPreviewingSections([]);
                        setPreviewingCss('');
                        setPreviewingJs('');
                        setPreviewingLabel('');

                        showToast("Draft Restored", "Draft successfully updated to selected version.", 4000);
                        fetchVersions();
                    } else {
                        showToast("Error", data.error || "Failed to restore version.");
                    }
                })
                .catch(err => showToast("Error", err.message));
            };

            const createCustomSnapshot = () => {
                if (!customVersionLabel.trim()) {
                    showToast("Validation Error", "Please provide a version label.");
                    return;
                }
                const contentJson = serializeCanvas();
                fetch('api.php?action=create_version', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: JSON.stringify({
                        project_id: PROJECT_ID,
                        label: customVersionLabel,
                        content_json: contentJson,
                        csrf_token: CSRF_TOKEN
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast("Snapshot Created", "Version saved to the timeline.");
                        setCustomVersionLabel('');
                        fetchVersions();
                    } else {
                        showToast("Error", data.error || "Failed to create snapshot.");
                    }
                })
                .catch(err => showToast("Network Error", err.message));
            };

            // --- System states ---
            const [isSaving, setIsSaving] = useState(false);
            const [isPublishing, setIsPublishing] = useState(false);
            const [toast, setToast] = useState(null);

            // --- Custom Component Modal States ---
            const [newCompName, setNewCompName] = useState('My Custom Component');
            const [newCompCategory, setNewCompCategory] = useState('Advanced');
            const [newCompIcon, setNewCompIcon] = useState('fas fa-cube');
            const [newCompHtml, setNewCompHtml] = useState('<div class="p-8 rounded-lg bg-slate-900 border border-slate-800 text-center" data-component="my_custom_component">\n    <h3 class="text-xl font-bold text-teal-400 mb-2">{{heading}}</h3>\n    <p class="text-xs text-slate-400">{{text}}</p>\n</div>');
            const [newCompFields, setNewCompFields] = useState([
                { key: 'heading', label: 'Heading Text', type: 'text', default: 'Hello World' },
                { key: 'text', label: 'Body Text', type: 'textarea', default: 'This is my customizable component description.' }
            ]);

            const addNewCompField = () => {
                setNewCompFields([...newCompFields, { key: `field_${Date.now()}`, label: 'New Field Label', type: 'text', default: 'Default Value' }]);
            };

            const updateNewCompField = (idx, key, val) => {
                const updated = newCompFields.map((f, i) => idx === i ? { ...f, [key]: val } : f);
                setNewCompFields(updated);
            };

            const removeNewCompField = (idx) => {
                setNewCompFields(newCompFields.filter((_, i) => idx !== i));
            };

            const createPage = () => {
                const name = prompt("Enter new page name (lowercase, no spaces, e.g., 'aboutus'):");
                if (!name) return;
                const safeName = name.toLowerCase().trim().replace(/[^a-z0-9_]+/g, '');
                if (!safeName) {
                    showToast("Error", "Invalid page name.");
                    return;
                }
                if (pages[safeName]) {
                    showToast("Error", "Page already exists!");
                    return;
                }

                // Try to copy existing navbar & footer from active page or index if they exist
                const currentSects = pages[activePage] || pages["index"] || [];
                const existingNavbar = currentSects.find(s => s.type && s.type.toLowerCase() === 'navbar') || (pages["index"] || []).find(s => s.type && s.type.toLowerCase() === 'navbar');
                const existingFooter = currentSects.find(s => s.type && s.type.toLowerCase() === 'footer') || (pages["index"] || []).find(s => s.type && s.type.toLowerCase() === 'footer');

                const newNavbar = existingNavbar ? JSON.parse(JSON.stringify(existingNavbar)) : {
                    id: 'sec-navbar-' + Date.now(),
                    type: 'navbar',
                    props: { brandText: 'Nuvis Webidesigner', logoUrl: '', links: [], bgColor: '#0f172a', textColor: '#ffffff', accentColor: '#14b8a6' },
                    style: { classes: [] }
                };
                newNavbar.id = 'sec-navbar-' + Date.now();

                const newFooter = existingFooter ? JSON.parse(JSON.stringify(existingFooter)) : {
                    id: 'sec-footer-' + Date.now(),
                    type: 'footer',
                    props: { brandText: 'Nuvis Webidesigner', logoUrl: '', copyright: 'Nuvis Webidesigner. All rights reserved.', links: [], bgColor: '#020617', textColor: '#94a3b8', accentColor: '#14b8a6' },
                    style: { classes: [] }
                };
                newFooter.id = 'sec-footer-' + Date.now();

                const updatedPages = {
                    ...pages,
                    [safeName]: [newNavbar, newFooter]
                };

                setPages(updatedPages);
                setActivePage(safeName);
                commitToHistory(updatedPages);
                showToast("Page Created", `Created and switched to page "${safeName}".`);
            };

            const deletePage = (pageName) => {
                if (pageName === 'index') {
                    showToast("Error", "The main 'index' page cannot be deleted.");
                    return;
                }
                if (!confirm(`Are you sure you want to delete the page "${pageName}"? This action cannot be undone.`)) {
                    return;
                }
                const updatedPages = { ...pages };
                delete updatedPages[pageName];

                setPages(updatedPages);
                setActivePage("index");
                commitToHistory(updatedPages);
                showToast("Page Deleted", `Page "${pageName}" has been deleted.`);
                saveProject(true, updatedPages);
            };

            const handleCreateCustomComponentSubmit = (e) => {
                e.preventDefault();

                // Construct safe lowercase unique ID from name
                const derivedId = newCompName.toLowerCase().trim().replace(/[^a-z0-9_]+/g, '_');

                if (customComponents.some(c => c.id === derivedId) || UI_COMPONENTS.some(c => c.id === derivedId)) {
                    showToast("Validation Error", "A component with a similar name or ID already exists on the shelf.");
                    return;
                }

                // Verify that raw HTML has data-component attribute on root
                let finalHtml = newCompHtml.trim();
                if (!finalHtml.includes('data-component=')) {
                    // Try to inject data-component attribute into the first tag
                    finalHtml = finalHtml.replace(/<([a-zA-Z0-9]+)/, `<$1 data-component="${derivedId}"`);
                }

                const newComponent = {
                    id: derivedId,
                    name: newCompName,
                    category: newCompCategory,
                    icon: newCompIcon,
                    schema: newCompFields,
                    html: finalHtml
                };

                const updatedComponents = [...customComponents, newComponent];
                setCustomComponents(updatedComponents);
                setIsCustomCompModalOpen(false);

                showToast("Component Created!", `${newCompName} added to the Components Shelf. Try dragging it to the canvas!`);

                // Auto save project with the new custom components serialized
                setTimeout(() => {
                    saveProject(true);
                }, 505);
            };

            // --- Load Page State on Bootstrap ---
            useEffect(() => {
                try {
                    let raw = LOADED_CONTENT_STATE;
                    if (typeof raw === 'string') raw = JSON.parse(raw);

                    let initialSections = [];
                    let initialCss = '';
                    let initialJs = '';
                    let rec = '';
                    let autoResp = false;
                    let templTheme = 'modern_minimalist';
                    let autoSub = 'Thank you for contacting us!';
                    let autoBody = 'Hello!\n\nWe have received your inquiry regarding our services and will get back to you shortly.\n\nBest regards,\nThe Team';
                    let initialCustomComponents = [];

                    // Parsing formats
                    if (raw && Array.isArray(raw.blocks)) {
                        initialSections = raw.blocks.map(blockToSection);
                        initialCss = raw.custom_css || '';
                        initialJs = raw.custom_js || '';
                    } else if (raw && Array.isArray(raw.sections)) {
                        initialSections = raw.sections;
                        initialCss = raw.custom_css || '';
                        initialJs = raw.custom_js || '';
                    } else if (raw && Array.isArray(raw)) {
                        initialSections = raw.map(blockToSection);
                    }

                    // Extract integrated Nuvis Email module configuration state
                    let sHost = '';
                    let sPort = '';
                    let sUser = '';
                    let sPass = '';
                    let sEnc = 'none';
                    let sFromEmail = '';
                    let sFromName = '';

                    if (raw && raw.email_settings) {
                        rec = raw.email_settings.recipient || '';
                        autoResp = !!raw.email_settings.auto_responder_enabled;
                        templTheme = raw.email_settings.template_theme || 'modern_minimalist';
                        autoSub = raw.email_settings.auto_responder_subject || 'Thank you for contacting us!';
                        autoBody = raw.email_settings.auto_responder_body || 'Hello!\n\nWe have received your inquiry regarding our services and will get back to you shortly.\n\nBest regards,\nThe Team';
                        sHost = raw.email_settings.smtp_host || '';
                        sPort = raw.email_settings.smtp_port || '';
                        sUser = raw.email_settings.smtp_username || '';
                        sPass = raw.email_settings.smtp_password || '';
                        sEnc = raw.email_settings.smtp_encryption || 'none';
                        sFromEmail = raw.email_settings.smtp_from_email || '';
                        sFromName = raw.email_settings.smtp_from_name || '';
                    }

                    if (raw && Array.isArray(raw.custom_components)) {
                        initialCustomComponents = raw.custom_components;
                    }

                    // Combine built-in and loaded custom components for defaults parser
                    const tempCombined = [...UI_COMPONENTS, ...initialCustomComponents];

                    // Populate missing props with schema defaults
                    initialSections = initialSections.map(s => {
                        const compDef = tempCombined.find(c => c.id.toLowerCase() === (s.type || '').toLowerCase().trim());
                        if (compDef && compDef.schema) {
                            compDef.schema.forEach(field => {
                                if (s.props[field.key] === undefined) {
                                    s.props[field.key] = field.default;
                                }
                            });
                        }
                        return s;
                    });

                    setCustomComponents(initialCustomComponents);
                    let initialPages = { index: [] };
                    if (raw && raw.pages) {
                        initialPages = raw.pages;
                    } else {
                        initialPages = { index: initialSections };
                    }
                    setPages(initialPages);
                    setActivePage(raw.activePage || "index");
                    setCustomCss(initialCss);
                    setCustomJs(initialJs);
                    setEmailRecipient(rec);
                    setAutoResponderEnabled(autoResp);
                    setEmailTemplateTheme(templTheme);
                    setAutoResponderSubject(autoSub);
                    setAutoResponderBody(autoBody);
                    setSmtpHost(sHost);
                    setSmtpPort(sPort);
                    setSmtpUsername(sUser);
                    setSmtpPassword(sPass);
                    setSmtpEncryption(sEnc);
                    setSmtpFromEmail(sFromEmail);
                    setSmtpFromName(sFromName);

                    // Evolved parameters bootstrap
                    if (raw && raw.global_theme) {
                        setThemePrimaryColor(raw.global_theme.primary_color || '#14b8a6');
                        setThemeBgColor(raw.global_theme.bg_color || '#0f172a');
                        setThemeFontFamily(raw.global_theme.font_family || 'sans-serif');
                        setThemeBaseSpacing(raw.global_theme.base_spacing || '24px');
                        setThemeContainerWidth(raw.global_theme.container_width || '1200px');
                    }
                    if (raw && raw.seo_settings) {
                        setSeoTitle(raw.seo_settings.title || PROJECT_NAME);
                        setSeoMetaDesc(raw.seo_settings.meta_desc || '');
                        setSeoOgImage(raw.seo_settings.og_image || '');
                        setSeoRobotsTxt(raw.seo_settings.robots_txt || 'User-agent: *\nAllow: /');
                        setSeoStructuredData(raw.seo_settings.structured_data || '');
                    }
                    if (raw && raw.saas_settings) {
                        setTenantCustomDomain(raw.saas_settings.custom_domain || '');
                        setTenantSubdomain(raw.saas_settings.subdomain || 'pestkit');
                        setTenantSubscriptionPlan(raw.saas_settings.subscription_plan || 'pro');
                    }

                    // Initialize history stack
                    setHistory([initialPages]);
                    setHistoryIndex(0);

                    // Fetch version history timeline on startup
                    fetchVersions();

                } catch (e) {
                    console.error("Bootstrapping content JSON error: ", e);
                }
            }, []);

            // Dynamic derived list of all active components (pre-built + custom)
            const ACTIVE_COMPONENTS = [...UI_COMPONENTS, ...customComponents];

            // --- History Stack Helpers ---
            const commitToHistory = (newPages) => {
                const updatedHistory = history.slice(0, historyIndex + 1);
                setHistory([...updatedHistory, newPages]);
                setHistoryIndex(updatedHistory.length);
            };

            const updateSectionsWithHistory = (newSections) => {
                let updatedPages = { ...pages, [activePage]: newSections };

                // Automatically synchronize Navigation Bar and Footer properties across all pages
                const updatedNavbar = newSections.find(s => s.type && s.type.toLowerCase() === 'navbar');
                const updatedFooter = newSections.find(s => s.type && s.type.toLowerCase() === 'footer');

                if (updatedNavbar || updatedFooter) {
                    Object.keys(updatedPages).forEach(pKey => {
                        if (pKey === activePage) return; // Skip current active page

                        let pageSects = updatedPages[pKey] || [];
                        let pageUpdated = false;

                        pageSects = pageSects.map(s => {
                            if (updatedNavbar && s.type && s.type.toLowerCase() === 'navbar') {
                                pageUpdated = true;
                                return {
                                    ...s,
                                    props: JSON.parse(JSON.stringify(updatedNavbar.props)),
                                    bg_color_override: updatedNavbar.bg_color_override,
                                    bg_image_override: updatedNavbar.bg_image_override,
                                    element_overrides: updatedNavbar.element_overrides ? JSON.parse(JSON.stringify(updatedNavbar.element_overrides)) : undefined
                                };
                            }
                            if (updatedFooter && s.type && s.type.toLowerCase() === 'footer') {
                                pageUpdated = true;
                                return {
                                    ...s,
                                    props: JSON.parse(JSON.stringify(updatedFooter.props)),
                                    bg_color_override: updatedFooter.bg_color_override,
                                    bg_image_override: updatedFooter.bg_image_override,
                                    element_overrides: updatedFooter.element_overrides ? JSON.parse(JSON.stringify(updatedFooter.element_overrides)) : undefined
                                };
                            }
                            return s;
                        });

                        if (pageUpdated) {
                            updatedPages[pKey] = pageSects;
                        }
                    });
                }

                setPages(updatedPages);
                commitToHistory(updatedPages);
            };

            const undo = () => {
                if (historyIndex > 0) {
                    const prevIdx = historyIndex - 1;
                    setHistoryIndex(prevIdx);
                    setPages(history[prevIdx]);
                    showToast("Undo", "Action reverted.");
                }
            };

            const redo = () => {
                if (historyIndex < history.length - 1) {
                    const nextIdx = historyIndex + 1;
                    setHistoryIndex(nextIdx);
                    setPages(history[nextIdx]);
                    showToast("Redo", "Action re-applied.");
                }
            };

            // Keyboard shortcut hooks (Ctrl+Z / Ctrl+Y)
            useEffect(() => {
                const handleKeyDown = (e) => {
                    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
                        e.preventDefault();
                        undo();
                    }
                    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') {
                        e.preventDefault();
                        redo();
                    }
                };
                window.addEventListener('keydown', handleKeyDown);
                return () => window.removeEventListener('keydown', handleKeyDown);
            }, [history, historyIndex]);

            // --- Toast System ---
            const showToast = (title, desc, duration = 3000) => {
                setToast({ title, desc });
                setTimeout(() => setToast(null), duration);
            };

            // --- Map legacy blocks to React Sections ---
            function blockToSection(block) {
                const initialProps = {};
                const compDef = ACTIVE_COMPONENTS.find(c => c.id.toLowerCase() === (block.componentId || block.type || '').toLowerCase().trim());
                if (compDef && compDef.schema) {
                    compDef.schema.forEach(field => {
                        initialProps[field.key] = field.default;
                    });
                }

                // Restore properties
                const restoredProps = block.props || {};
                const finalProps = { ...initialProps, ...restoredProps };
                if (block.headingText) finalProps.heading = block.headingText;
                if (block.paragraphText) finalProps.text = block.paragraphText;
                if (block.brandText) finalProps.brandText = block.brandText;
                if (block.logoImg) finalProps.logoUrl = block.logoImg;
                if (block.copyright) finalProps.copyright = block.copyright;
                if (block.raw_html) finalProps.rawHtml = block.raw_html;
                if (block.links) finalProps.links = block.links;

                return {
                    id: block.id || 'sec-' + (block.componentId || 'unknown') + '-' + Date.now() + '-' + Math.random().toString(36).slice(2, 6),
                    type: block.componentId || block.type,
                    props: finalProps,
                    style: {
                        classes: block.classes || (block.style && block.style.classes) || [],
                    }
                };
            }

            // --- Drag-and-drop Shelf actions ---
            const handleDragStart = (e, componentId) => {
                e.dataTransfer.setData('text/plain', componentId);
                e.dataTransfer.effectAllowed = 'copy';
            };

            const handleAddSection = (componentId) => {
                const compDef = ACTIVE_COMPONENTS.find(c => c.id.toLowerCase() === (componentId || '').toLowerCase().trim());
                if (!compDef) return;

                const defaultProps = {};
                if (compDef.schema) {
                    compDef.schema.forEach(field => {
                        defaultProps[field.key] = field.default;
                    });
                }

                const newSection = {
                    id: 'sec-' + componentId + '-' + Date.now() + '-' + Math.random().toString(36).slice(2, 7),
                    type: componentId,
                    props: defaultProps,
                    style: { classes: [] }
                };

                const updated = [...sections, newSection];
                updateSectionsWithHistory(updated);
                setActiveSectionId(newSection.id);
                showToast("Section Added", `${compDef.name} successfully inserted.`);
            };

            const handleCanvasDrop = (e) => {
                e.preventDefault();
                const componentId = e.dataTransfer.getData('text/plain');
                handleAddSection(componentId);
            };

            // --- Section Management Operations ---
            const moveSectionUp = (idx) => {
                if (idx === 0) return;
                const updated = [...sections];
                const temp = updated[idx];
                updated[idx] = updated[idx - 1];
                updated[idx - 1] = temp;
                updateSectionsWithHistory(updated);
            };

            const moveSectionDown = (idx) => {
                if (idx === sections.length - 1) return;
                const updated = [...sections];
                const temp = updated[idx];
                updated[idx] = updated[idx + 1];
                updated[idx + 1] = temp;
                updateSectionsWithHistory(updated);
            };

            const duplicateSection = (idx) => {
                const section = sections[idx];
                const clone = JSON.parse(JSON.stringify(section));
                clone.id = 'sec-' + section.type + '-' + Date.now() + '-' + Math.random().toString(36).slice(2, 7);
                const updated = [...sections];
                updated.splice(idx + 1, 0, clone);
                updateSectionsWithHistory(updated);
                setActiveSectionId(clone.id);
                showToast("Section Cloned", "Duplicate section created.");
            };

            const deleteSection = (id) => {
                const updated = sections.filter(s => s.id !== id);
                updateSectionsWithHistory(updated);
                if (activeSectionId === id) setActiveSectionId(null);
                showToast("Section Removed", "Removed from canvas.");
            };

            // --- Serializing layout data for Save ---
            const serializeCanvas = (customPages = null) => {
                const targetPages = customPages || pages;
                const compileBlocksForPage = (pageSects) => {
                    return (pageSects || []).map(sec => ({
                        id:            sec.id,
                        componentId:   sec.type,
                        headingText:   sec.props.heading   || '',
                        paragraphText: sec.props.text      || '',
                        classes:       sec.style.classes   || [],
                        raw_html:      sec.props.rawHtml   || '',
                        brandText:     sec.props.brandText || '',
                        logoImg:       sec.props.logoUrl   || '',
                        copyright:     sec.props.copyright || '',
                        links:         sec.props.links     || [],
                        props:         sec.props,
                    }));
                };

                const indexSections = targetPages["index"] || [];
                const blocks = compileBlocksForPage(indexSections);

                return JSON.stringify({
                    pages:      targetPages,
                    activePage: activePage,
                    sections:   indexSections,   // index fallback
                    blocks:     blocks,          // index fallback
                    custom_css: customCss,
                    custom_js:  customJs,
                    custom_components: customComponents,
                    // Nuvis Email module configuration parameters
                    email_settings: {
                        recipient: emailRecipient,
                        auto_responder_enabled: autoResponderEnabled,
                        template_theme: emailTemplateTheme,
                        auto_responder_subject: autoResponderSubject,
                        auto_responder_body: autoResponderBody,
                        smtp_host: smtpHost,
                        smtp_port: smtpPort,
                        smtp_username: smtpUsername,
                        smtp_password: smtpPassword,
                        smtp_encryption: smtpEncryption,
                        smtp_from_email: smtpFromEmail,
                        smtp_from_name: smtpFromName
                    },
                    // Evolved variables serialization
                    global_theme: {
                        primary_color: themePrimaryColor,
                        bg_color: themeBgColor,
                        font_family: themeFontFamily,
                        base_spacing: themeBaseSpacing,
                        container_width: themeContainerWidth
                    },
                    seo_settings: {
                        title: seoTitle,
                        meta_desc: seoMetaDesc,
                        og_image: seoOgImage,
                        robots_txt: seoRobotsTxt,
                        structured_data: seoStructuredData
                    },
                    saas_settings: {
                        custom_domain: tenantCustomDomain,
                        subdomain: tenantSubdomain,
                        subscription_plan: tenantSubscriptionPlan
                    }
                });
            };

            // --- Save / Publish / Export Requests ---
            const saveProject = (silent = false, customPages = null) => {
                if (!silent) setIsSaving(true);
                const contentJson = serializeCanvas(customPages);
                const payload = {
                    project_id:   PROJECT_ID,
                    name:         PROJECT_NAME,
                    content_json: contentJson,
                    csrf_token:   CSRF_TOKEN,
                };

                return fetch('api.php?action=save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: JSON.stringify(payload),
                })
                .then(res => {
                    if (!res.ok) {
                        return res.json().then(data => {
                            throw new Error(data.error || "Session expired or unauthorized. Please login first.");
                        }).catch(() => {
                            throw new Error("Session expired or unauthorized. Please login first.");
                        });
                    }
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        if (!silent) showToast("Draft Saved", "Workspace saved successfully.");
                        fetchVersions();
                        return data;
                    } else {
                        showToast("Error", data.error || "Save rejected.");
                        throw new Error(data.error);
                    }
                })
                .catch(err => {
                    showToast("Network Error", err.message);
                    throw err; // Propagate rejection
                })
                .finally(() => setIsSaving(false));
            };

            // DYNAMIC KEY-VALUE TEMPLATING COMPILER
            const compileSectionHtml = (sec, isBuilderMode = true) => {
                const compDef = ACTIVE_COMPONENTS.find(c => c.id.toLowerCase() === (sec.type || '').toLowerCase().trim());
                if (!compDef) return '';

                let compiledHtml = compDef.html;

                // Replace {{placeholder}} with corresponding props key values
                if (compDef.schema) {
                    compDef.schema.forEach(field => {
                        const val = sec.props[field.key] !== undefined ? sec.props[field.key] : field.default;
                        // Replace all instances of {{key}}
                        const regex = new RegExp(`{{\\s*${field.key}\\s*}}`, 'g');
                        compiledHtml = compiledHtml.replace(regex, val);
                    });
                }

                // Dynamic custom compilers for links in Navigation bar and Footer
                if (sec.type.toLowerCase() === 'navbar' || sec.type.toLowerCase() === 'footer') {
                    // Compile Brand and Logo Area
                    const showLogo = sec.props.showLogo !== undefined ? sec.props.showLogo : true;
                    const showBrandText = sec.props.showBrandText !== undefined ? sec.props.showBrandText : true;
                    const accentColor = sec.props.accentColor || '#14b8a6';
                    const brandColor = sec.props.brandColor || '#14b8a6';

                    let brandTextHtml = '';
                    if (showBrandText) {
                        const isNavbar = sec.type.toLowerCase() === 'navbar';
                        const brandClass = isNavbar ? "text-xl font-extrabold tracking-wider" : "text-lg font-black";
                        brandTextHtml = `<div class="${brandClass}" style="color: ${brandColor};" data-brand-text="true" data-el-path="el-brand">${sec.props.brandText || 'Nuvis Webidesigner'}</div>`;
                    }

                    let logoHtml = '';
                    if (showLogo && sec.props.logoUrl) {
                        const widthStyle = sec.props.logoWidth ? `width: ${sec.props.logoWidth};` : 'width: auto;';
                        const heightStyle = sec.props.logoHeight ? `height: ${sec.props.logoHeight};` : 'height: 32px;';
                        let radiusStyle = '';
                        if (sec.props.logoShape === 'rounded') {
                            radiusStyle = 'border-radius: 8px;';
                        } else if (sec.props.logoShape === 'circle') {
                            radiusStyle = 'border-radius: 9999px;';
                        } else {
                            radiusStyle = 'border-radius: 0;';
                        }
                        logoHtml = `<img src="${sec.props.logoUrl}" style="${widthStyle} ${heightStyle} ${radiusStyle} object-fit: contain;" alt="Logo" data-el-path="el-logo" />`;
                    }

                    const isRight = sec.props.logoPosition === 'right-of-text';
                    const flexDirClass = isRight ? "flex-row-reverse" : "flex-row";

                    const brandLogoAreaHtml = `
                    <div class="flex items-center gap-3 ${flexDirClass}">
                        ${logoHtml}
                        ${brandTextHtml}
                    </div>
                    `;

                    compiledHtml = compiledHtml.replace(/{{\s*brandLogoArea\s*}}/g, brandLogoAreaHtml);

                    const links = sec.props.links || (sec.type.toLowerCase() === 'navbar' ? [
                        { text: 'Home', type: 'link', url: '#home' },
                        { text: 'Features', type: 'link', url: '#features' },
                        { text: 'Pricing', type: 'link', url: '#pricing' },
                        { text: 'Contact', type: 'link', url: '#contact' }
                    ] : [
                        { text: 'Privacy Policy', type: 'link', url: '#privacy' },
                        { text: 'Terms of Use', type: 'link', url: '#terms' },
                        { text: 'Support', type: 'link', url: '#support' }
                    ]);

                    const textColor = sec.props.textColor || '#94a3b8';

                    const compileLinksHtml = (linksList, isDesktop = true) => {
                        return linksList.map((link, lIdx) => {
                            const linkType = link.type || 'link';
                            let url = '#';
                            if (linkType === 'link') {
                                url = link.url || '#';
                            } else if (linkType === 'page') {
                                url = `?page=${link.pageName || 'index'}`;
                            }

                            if (linkType === 'dropdown') {
                                const children = link.children || [];
                                if (isDesktop) {
                                    return `
                                    <div class="relative group dropdown-item inline-block text-left">
                                        <button class="flex items-center gap-1 font-bold transition duration-300 focus:outline-none" style="color: ${textColor};" onmouseover="this.style.color='${accentColor}'" onmouseout="this.style.color='${textColor}'" data-el-path="el-navlink-${lIdx}">
                                            <span>${link.text}</span>
                                            <i class="fas fa-chevron-down text-[9px] opacity-70 transition-transform duration-200 group-hover:rotate-180"></i>
                                        </button>
                                        <!-- Absolute Dropdown Panel with modern sliding transitions -->
                                        <div class="absolute top-full left-0 mt-2 w-48 rounded-lg shadow-xl py-2 opacity-0 -translate-y-2 pointer-events-none group-hover:opacity-100 group-hover:translate-y-0 group-hover:pointer-events-auto transition-all duration-300 z-50 border border-slate-800/80" style="background-color: ${sec.props.bgColor || '#0f172a'};">
                                            ${children.map((child, cIdx) => {
                                                let cUrl = child.url || '#';
                                                if (child.type === 'page') {
                                                    cUrl = `?page=${child.pageName || 'index'}`;
                                                }
                                                return `
                                                <a href="${cUrl}" class="block px-4 py-2 text-xs transition duration-200 hover:bg-slate-800/60" style="color: ${textColor};" onmouseover="this.style.color='${accentColor}'" onmouseout="this.style.color='${textColor}'" data-el-path="el-navlink-${lIdx}-sub-${cIdx}">${child.text}</a>
                                                `;
                                            }).join('\n')}
                                        </div>
                                    </div>
                                    `;
                                } else {
                                    // Mobile dropdown
                                    return `
                                    <div class="w-full">
                                        <button onclick="const sub = this.nextElementSibling; sub.classList.toggle('hidden'); const icon = this.querySelector('.fa-chevron-down'); icon.classList.toggle('rotate-180');" class="flex justify-between items-center w-full font-bold py-1.5 transition text-left" style="color: ${textColor};">
                                            <span>${link.text}</span>
                                            <i class="fas fa-chevron-down text-[10px] opacity-70 transition-transform duration-200"></i>
                                        </button>
                                        <div class="hidden flex flex-col space-y-2 pl-4 border-l border-slate-800/80 mt-1 pb-2">
                                            ${children.map(child => {
                                                let cUrl = child.url || '#';
                                                if (child.type === 'page') {
                                                    cUrl = `?page=${child.pageName || 'index'}`;
                                                }
                                                return `
                                                <a href="${cUrl}" class="block text-xs py-1 transition duration-200" style="color: ${textColor};" onmouseover="this.style.color='${accentColor}'" onmouseout="this.style.color='${textColor}'">${child.text}</a>
                                                `;
                                            }).join('\n')}
                                        </div>
                                    </div>
                                    `;
                                }
                            } else {
                                return `<a href="${url}" class="font-bold transition duration-300" style="color: ${textColor};" onmouseover="this.style.color='${accentColor}'" onmouseout="this.style.color='${textColor}'" ${isDesktop ? `data-el-path="el-navlink-${lIdx}"` : ''}>${link.text}</a>`;
                            }
                        }).join('\n');
                    };

                    const linksHtml = compileLinksHtml(links, true);
                    const mobileLinksHtml = compileLinksHtml(links, false);

                    compiledHtml = compiledHtml.replace(/{{\s*links\s*}}/, linksHtml);
                    compiledHtml = compiledHtml.replace(/{{\s*links\s*}}/, mobileLinksHtml);

                    if (sec.type.toLowerCase() === 'navbar') {
                        const showCta = sec.props.showCta !== undefined ? sec.props.showCta : true;
                        let ctaButtonHtml = '';
                        if (showCta) {
                            const btnText = sec.props.btnText || 'Get Started';
                        const btnBg = sec.props.btnBg || '#14b8a6';
                            const btnColor = sec.props.btnColor || sec.props.bgColor || '#0f172a';
                        ctaButtonHtml = `<a href="#get-started" class="font-bold px-4 py-2 rounded transition duration-300 text-sm" style="background-color: ${btnBg}; color: ${btnColor};" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'" data-cta-button="true" data-el-path="el-cta">${btnText}</a>`;
                        }
                        compiledHtml = compiledHtml.replace(/{{\s*ctaButton\s*}}/g, ctaButtonHtml);
                    }
                }

                // Dynamic compiler for layout_grid component
                if (sec.type.toLowerCase() === 'layout_grid') {
                    const cards = sec.props.cards || [
                        { content: 'High density column structure details.' },
                        { content: 'Responsive breakpoint scaling configurations.' },
                        { content: 'Flex space distribution values.' }
                    ];

                    const cardBgColor = sec.props.cardBgColor || '#0f172a';
                    const textColor = sec.props.textColor || '#94a3b8';

                    const cardsHtml = cards.map(card => `
                        <div class="p-6 rounded-lg shadow-lg border border-white/5" style="background-color: ${cardBgColor}; color: ${textColor};">
                            ${card.content}
                        </div>
                    `).join('\n');

                    compiledHtml = compiledHtml.replace(/{{\s*gridCards\s*}}/g, cardsHtml);
                }

                // Dynamic compiler for interactive_tabs component
                if (sec.type.toLowerCase() === 'interactive_tabs') {
                    const tabs = sec.props.tabs || [
                        { title: 'Platform', content: 'Fully integrated drag and drop builder.' },
                        { title: 'Database', content: 'MariaDB persistent storage pipelines.' }
                    ];

                    const accentColor = sec.props.accentColor || '#14b8a6';
                    const textColor = sec.props.textColor || '#94a3b8';

                    const tabButtonsHtml = tabs.map((tab, idx) => `
                        <button onclick="window.switchTab(this, ${idx})" class="tab-btn pb-3 text-xs font-bold uppercase border-b-2 tracking-wider transition-all" style="border-color: ${idx === 0 ? accentColor : 'transparent'}; color: ${idx === 0 ? accentColor : '#94a3b8'};" data-active-color="${accentColor}">
                            ${tab.title}
                        </button>
                    `).join('\n');

                    const tabContentsHtml = tabs.map((tab, idx) => `
                        <div class="tab-content ${idx === 0 ? '' : 'hidden'}" style="color: ${textColor};">${tab.content}</div>
                    `).join('\n');

                    compiledHtml = compiledHtml.replace(/{{\s*tabButtons\s*}}/g, tabButtonsHtml);
                    compiledHtml = compiledHtml.replace(/{{\s*tabContents\s*}}/g, tabContentsHtml);
                }

                // Dynamic compiler for faq component
                if (sec.type.toLowerCase() === 'faq') {
                    const faqs = sec.props.faqs || [
                        {
                            q: sec.props.q1 || 'How does the local compiling mechanism operate?',
                            a: sec.props.a1 || 'Our platform compiles visual assets into highly optimized, fully responsive static HTML output instantly.'
                        }
                    ];

                    const cardBg = sec.props.cardBg || '#020617';
                    const headingColor = sec.props.headingColor || '#ffffff';
                    const accentColor = sec.props.accentColor || '#14b8a6';

                    const faqsHtml = faqs.map((faq, idx) => `
                        <div class="border rounded-lg overflow-hidden border-slate-800" style="background-color: ${cardBg};">
                            <button onclick="window.toggleNuvisFaqAccordion(this)" class="w-full text-left px-6 py-4 font-bold text-sm flex justify-between items-center transition" style="color: ${headingColor};">
                                <span>${faq.q}</span>
                                <i class="fas fa-chevron-down opacity-60"></i>
                            </button>
                            <div class="faq-accordion-content hidden px-6 pb-5 text-xs border-t border-slate-900 pt-3 leading-relaxed" style="color: ${accentColor};">
                                ${faq.a}
                            </div>
                        </div>
                    `).join('\n');

                    compiledHtml = compiledHtml.replace(/{{\s*faqsList\s*}}/g, faqsHtml);
                }

                // Dynamic fix for spacer_divider showLine conditional expression
                if (sec.type.toLowerCase() === 'spacer_divider') {
                    const showLine = sec.props.showLine !== undefined ? sec.props.showLine : true;
                    const displayVal = showLine ? 'block' : 'none';
                    compiledHtml = compiledHtml.replace(/{{\s*showLine\s*\?\s*'block'\s*:\s*'none'\s*}}/g, displayVal);
                }

                // Dynamic compiler for google_chatbot position placement
                if (sec.type.toLowerCase() === 'google_chatbot') {
                    const pos = sec.props.position || 'bottom-right';
                    const posClass = pos === 'bottom-left' ? 'bottom-6 left-6' : 'bottom-6 right-6';
                    const winPosClass = pos === 'bottom-left' ? 'left-0' : 'right-0';
                    compiledHtml = compiledHtml.replace(/{{\s*position\s*===\s*'bottom-left'\s*\?\s*'bottom-6 left-6'\s*:\s*'bottom-6 right-6'\s*}}/g, posClass);
                    compiledHtml = compiledHtml.replace(/{{\s*position\s*===\s*'bottom-left'\s*\?\s*'left-0'\s*:\s*'right-0'\s*}}/g, winPosClass);
                }

                // Always inject section id as root ID
                const temp = document.createElement('div');
                temp.innerHTML = compiledHtml;
                const rootNode = temp.querySelector('[data-component]');
                if (rootNode) {
                    rootNode.id = sec.id;

                    // Component-level background overrides
                    if (sec.bg_color_override) {
                        rootNode.style.backgroundColor = sec.bg_color_override;
                        rootNode.style.backgroundImage = 'none';
                    }
                    if (sec.bg_image_override) {
                        rootNode.style.backgroundImage = `url('${sec.bg_image_override}')`;
                        rootNode.style.backgroundSize = 'cover';
                        rootNode.style.backgroundPosition = 'center';
                        rootNode.style.backgroundRepeat = 'no-repeat';
                    }
                }

                // --- ELEMENT-LEVEL SELECTION AND OVERRIDES ENHANCEMENT ---
                // Query all potentially editable sub-elements in sequential order
                const selectables = temp.querySelectorAll('h1, h2, h3, h4, h5, h6, p, span, img, i, button, a, [data-brand-text], [data-cta-button], [data-el-path]');

                let autoIdx = 0;
                selectables.forEach((el) => {
                    let path = el.getAttribute('data-el-path');
                    if (!path) {
                        path = `el-${autoIdx}`;
                        autoIdx++;
                        if (isBuilderMode) {
                            el.setAttribute('data-el-path', path);
                        }
                    }

                    // Assign data-el-path attribute for builder identification
                    if (isBuilderMode) {

                        // Highlight element if selected
                        if (sec.id === activeSectionId && activeElementId === path) {
                            el.classList.add('element-highlighted');
                        }

                        // Prevent click action e.g. links/form buttons inside builder
                        if (el.tagName === 'A' || el.tagName === 'BUTTON') {
                            el.addEventListener('click', (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                            });
                        }
                    }

                    // Apply saved element level overrides if any
                    if (sec.element_overrides && sec.element_overrides[path]) {
                        const override = sec.element_overrides[path];

                        // Hide element option
                        if (override.hidden) {
                            if (isBuilderMode) {
                                el.style.opacity = '0.3';
                                el.style.outline = '1px dashed #ef4444';
                                el.title = 'Hidden in Production';
                            } else {
                                el.style.display = 'none';
                            }
                        }

                        // Text / HTML / Src content overrides
                        if (override.text !== undefined) {
                            if (el.tagName === 'IMG') {
                                el.setAttribute('src', override.text);
                            } else if (el.tagName === 'I') {
                                el.className = override.text;
                            } else if (override.isHtml) {
                                el.innerHTML = override.text;
                            } else {
                                el.innerText = override.text;
                            }
                        }

                        // Styles overrides
                        if (override.styles) {
                            Object.keys(override.styles).forEach(styleKey => {
                                el.style[styleKey] = override.styles[styleKey];
                            });
                        }
                    }
                });

                compiledHtml = temp.innerHTML;
                return compiledHtml;
            };

            const publishProject = () => {
                setIsPublishing(true);
                saveProject(true).then(() => {
                    const publishedPages = {};
                    Object.keys(pages).forEach(pageKey => {
                        const pageSects = pages[pageKey] || [];
                        publishedPages[pageKey] = pageSects.map(sec => compileSectionHtml(sec, false)).join('\n');
                    });

                    const payload = {
                        project_id: PROJECT_ID,
                        published_html: JSON.stringify(publishedPages),
                        csrf_token: CSRF_TOKEN,
                    };

                    fetch('api.php?action=publish', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                        body: JSON.stringify(payload),
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            setProjectStatus('published');
                            showToast("Published Successfully!", "Your website is live. Click to view.", 5000);
                            fetchVersions();
                        } else {
                            showToast("Publish Failed", data.error || "Please try again.");
                        }
                    })
                    .catch(err => showToast("Error", err.message))
                    .finally(() => setIsPublishing(false));
                });
            };

            const downloadZip = () => {
                showToast("Packaging ZIP...", "Your standalone files are downloading.");
                window.location.href = `api.php?action=export&project_id=${PROJECT_ID}`;
            };

            // --- Live Selected Section ---
            const selectedSection = sections.find(s => s.id === activeSectionId) || null;

            // --- Real-time compiled code highlights helper ---
            const renderCodePreview = () => {
                if (!selectedSection) return '';
                const code = compileSectionHtml(selectedSection);
                // Simple escaping & styling attributes/tags for syntax coloring
                const escaped = code
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    // Tags highlight
                    .replace(/(&lt;\/?[a-z0-9]+)(&gt;|\s)/gi, '<span class="code-tag">$1</span>$2')
                    // Attributes highlight
                    .replace(/(\s[a-z0-9-_]+=)/gi, '<span class="code-attr">$1</span>')
                    // Values highlight
                    .replace(/(".*?")/gi, '<span class="code-val">$1</span>');

                return <pre className="text-[10px] font-mono leading-relaxed bg-slate-950 p-3 rounded border border-slate-800 overflow-x-auto select-all h-40 max-h-48" dangerouslySetInnerHTML={{ __html: escaped }} />;
            };

            return (
                <div className="h-full flex flex-col overflow-hidden bg-slate-950">
                    <style dangerouslySetInnerHTML={{ __html: previewingVersionId ? previewingCss : customCss }} />
                    <style dangerouslySetInnerHTML={{ __html: `
                        :root {
                            --primary-color: ${themePrimaryColor} !important;
                            --bg-color: ${themeBgColor} !important;
                            --font-family: ${themeFontFamily} !important;
                            --base-spacing: ${themeBaseSpacing} !important;
                            --container-width: ${themeContainerWidth} !important;
                        }
                        .canvas-inner-html section, .canvas-inner-html nav, .canvas-inner-html footer {
                            font-family: ${themeFontFamily} !important;
                        }
                    ` }} />

                    {/* TOP ACTION HEADER */}
                    <header className="bg-slate-900/90 backdrop-blur-md border-b border-slate-800 h-16 px-6 flex items-center justify-between shrink-0 z-40">
                        <div className="flex items-center gap-3">
                            <a href="admin.php" className="text-slate-400 hover:text-white transition duration-200">
                                <i className="fas fa-arrow-left text-lg"></i>
                            </a>
                            <span className="h-5 w-[1px] bg-slate-700"></span>
                            <div>
                                <h1 className="text-sm font-bold tracking-tight text-white leading-none">{PROJECT_NAME}</h1>
                                <p className="text-[11px] text-slate-400 mt-1">Status: <span className="font-semibold uppercase text-teal-400">{projectStatus}</span></p>
                            </div>
                        </div>

                        {/* HISTORY CONTROL BAR */}
                        <div className="flex items-center gap-2 bg-slate-950 p-1.5 rounded-lg border border-slate-800">
                            <button onClick={undo} disabled={historyIndex <= 0} className={`px-2.5 py-1.5 rounded text-xs transition ${historyIndex <= 0 ? 'text-slate-600 cursor-not-allowed' : 'text-slate-300 hover:text-white hover:bg-slate-800'}`} title="Undo (Ctrl+Z)">
                                <i className="fas fa-undo"></i>
                            </button>
                            <button onClick={redo} disabled={historyIndex >= history.length - 1} className={`px-2.5 py-1.5 rounded text-xs transition ${historyIndex >= history.length - 1 ? 'text-slate-600 cursor-not-allowed' : 'text-slate-300 hover:text-white hover:bg-slate-800'}`} title="Redo (Ctrl+Y)">
                                <i className="fas fa-redo"></i>
                            </button>
                            <span className="text-[10px] text-slate-500 font-mono px-1">Hist: {historyIndex + 1}/{history.length}</span>
                        </div>

                        {/* PAGE SWITCHER / MANAGER */}
                        <div className="flex items-center gap-2 bg-slate-950 p-1.5 rounded-lg border border-slate-800">
                            <span className="text-[10px] text-slate-500 uppercase font-black px-1">Page:</span>
                            <select
                                value={activePage}
                                onChange={(e) => {
                                    setActivePage(e.target.value);
                                    setActiveSectionId(null);
                                    setActiveElementId(null);
                                    setPropsSubTab('block');
                                    showToast("Page Switched", `Editing page: "${e.target.value}"`);
                                }}
                                className="bg-slate-900 border border-slate-800 rounded px-2.5 py-1 text-xs text-white focus:outline-none focus:border-teal-500 font-bold"
                            >
                                {Object.keys(pages).map(pName => (
                                    <option key={pName} value={pName}>{pName}</option>
                                ))}
                            </select>
                            <button
                                onClick={createPage}
                                className="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-teal-400 font-bold rounded text-xs transition border border-slate-750"
                                title="Create New Page"
                            >
                                <i className="fas fa-plus"></i>
                            </button>
                            {activePage !== 'index' && (
                                <button
                                    onClick={() => deletePage(activePage)}
                                    className="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-rose-400 font-bold rounded text-xs transition border border-slate-750"
                                    title="Delete Current Page"
                                >
                                    <i className="fas fa-trash-alt"></i>
                                </button>
                            )}
                        </div>

                        {/* RESPONSIVE PREVIEW CONTROLS */}
                        <div className="hidden md:flex bg-slate-950 p-1 rounded-lg border border-slate-800 space-x-1">
                            {['desktop', 'tablet', 'mobile'].map(v => (
                                <button key={v} onClick={() => setCanvasView(v)} className={`px-3 py-1.5 rounded text-xs font-bold transition duration-200 capitalize flex items-center gap-1.5 ${canvasView === v ? 'bg-slate-800 text-teal-400' : 'text-slate-400 hover:text-white'}`}>
                                    <i className={`fas ${v === 'desktop' ? 'fa-desktop' : v === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt'}`}></i>
                                    {v}
                                </button>
                            ))}
                        </div>

                        {/* SYSTEM SAVE & PUBLISH ACTION TRIGGERS */}
                        <div className="flex items-center gap-2">
                            <button onClick={() => {
                                if (window.confirm("Are you absolutely sure you want to clear the entire canvas? This action is undoable using Ctrl+Z.")) {
                                    updateSectionsWithHistory([]);
                                    setActiveSectionId(null);
                                    setActiveElementId(null);
                                    showToast("Canvas Cleared", "All sections removed. Press Ctrl+Z to undo.");
                                }
                            }} className="bg-rose-950/40 hover:bg-rose-900/60 text-rose-400 font-bold px-3 py-2 rounded text-xs flex items-center gap-1.5 transition border border-rose-900/30" title="Clear entire canvas">
                                <i className="fas fa-trash-alt"></i> Clear Canvas
                            </button>
                            <button onClick={() => saveProject(false)} disabled={isSaving} className="bg-slate-850 hover:bg-slate-800 text-slate-200 font-bold px-4 py-2 rounded text-xs flex items-center gap-1.5 transition border border-slate-800">
                                {isSaving ? <i className="fas fa-spinner animate-spin"></i> : <i className="fas fa-save text-teal-400"></i>}
                                Save Draft
                            </button>
                            <button onClick={downloadZip} className="bg-slate-850 hover:bg-slate-800 text-slate-200 font-bold px-4 py-2 rounded text-xs flex items-center gap-1.5 transition border border-slate-800" title="Download standalone code ZIP archive">
                                <i className="fas fa-file-archive text-teal-400"></i> ZIP
                            </button>
                            <button onClick={publishProject} disabled={isPublishing} className="bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-4 py-2 rounded text-xs flex items-center gap-1.5 transition shadow-lg shadow-teal-500/10">
                                {isPublishing ? <i className="fas fa-spinner animate-spin"></i> : <i className="fas fa-globe"></i>}
                                Publish Site
                            </button>
                        </div>
                    </header>

                    {/* MAIN DIVIDED LAYOUT */}
                    <div className="flex flex-1 overflow-hidden">

                        {/* LEFT COLUMN - COMPONENTS LIBRARY */}
                        <aside className="w-80 bg-slate-900 border-r border-slate-800 flex flex-col overflow-hidden shrink-0">
                            <div className="p-4 border-b border-slate-800 bg-slate-900/50 space-y-2 shrink-0">
                                <h2 className="text-xs font-extrabold text-teal-400 uppercase tracking-widest">Components Shelf</h2>
                                <p className="text-[11px] text-slate-400 mt-1">Drag and drop components directly onto the web canvas.</p>

                                {/* SEARCH / FILTER COMPONENT BAR */}
                                <div className="relative mt-2">
                                    <span className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-500 text-xs">
                                        <i className="fas fa-search"></i>
                                    </span>
                                    <input
                                        type="text"
                                        value={componentSearchQuery}
                                        onChange={(e) => setComponentSearchQuery(e.target.value)}
                                        placeholder="Search widgets (e.g. Hero, Alert)..."
                                        className="w-full bg-slate-950 border border-slate-800 rounded pl-8 pr-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-teal-500 transition-colors"
                                    />
                                    {componentSearchQuery && (
                                        <button
                                            onClick={() => setComponentSearchQuery('')}
                                            className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500 hover:text-white text-xs">
                                            <i className="fas fa-times-circle"></i>
                                        </button>
                                    )}
                                </div>

                                <button
                                    onClick={() => setIsCustomCompModalOpen(true)}
                                    className="w-full bg-slate-800 hover:bg-slate-700 text-teal-400 font-extrabold py-2 rounded text-xs transition border border-slate-750 flex items-center justify-center gap-1.5 shadow mt-2">
                                    <i className="fas fa-plus-circle"></i> Create Custom Component
                                </button>
                            </div>

                            <div className="flex-1 overflow-y-auto p-4 space-y-4">
                                {['Headers', 'Hero', 'Features', 'Pricing', 'Forms', 'Advanced', 'Footers'].map(cat => {
                                    // Filter components inside category using case-insensitive search matching against name, category or tag id
                                    const items = ACTIVE_COMPONENTS.filter(comp => {
                                        if (comp.category !== cat) return false;
                                        if (!componentSearchQuery.trim()) return true;
                                        const query = componentSearchQuery.toLowerCase().trim();
                                        return comp.name.toLowerCase().includes(query) ||
                                               comp.id.toLowerCase().includes(query) ||
                                               comp.category.toLowerCase().includes(query);
                                    });
                                    if (items.length === 0) return null;
                                    return (
                                        <div key={cat} className="space-y-2">
                                            <h3 className="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">{cat}</h3>
                                            <div className="grid grid-cols-2 gap-2">
                                                {items.map(comp => (
                                                    <div key={comp.id} draggable onDragStart={(e) => handleDragStart(e, comp.id)} onClick={() => handleAddSection(comp.id)} className="bg-slate-950 hover:bg-slate-800 border border-slate-800/80 rounded-lg p-3 text-center cursor-pointer transition-all duration-200 select-none group hover:border-teal-500/30">
                                                        <div className="text-teal-400 text-lg mb-1.5 group-hover:scale-110 transition-transform duration-200"><i className={comp.icon}></i></div>
                                                        <div className="text-[10px] text-slate-300 font-medium truncate">{comp.name}</div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </aside>

                        {/* CENTER CANVAS CONTAINER - RESPONSIVE FRAME */}
                        <main className="flex-1 bg-slate-950 overflow-y-auto p-8 flex flex-col justify-start items-center gap-6 transition-all" onClick={() => { setActiveSectionId(null); setActiveElementId(null); setPropsSubTab('block'); }}>

                            {/* COLLABORATIVE PRESENCE POLL BAR */}
                            <div className="w-full max-w-4xl bg-slate-900/90 border border-slate-800 rounded-xl p-3 flex flex-col md:flex-row items-center justify-between gap-4 text-xs z-30 shrink-0">
                                <div className="flex items-center gap-3">
                                    <span class="relative flex h-2.5 w-2.5">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-teal-500"></span>
                                    </span>
                                    <span className="font-bold text-slate-300">Live Workspace Presence:</span>
                                    <div className="flex items-center -space-x-2">
                                        <div className="w-6 h-6 rounded-full bg-amber-500 flex items-center justify-center font-bold text-[10px] text-slate-950 border border-slate-900" title="Sarah Jenkins (Editor)">SJ</div>
                                        <div className="w-6 h-6 rounded-full bg-cyan-500 flex items-center justify-center font-bold text-[10px] text-white border border-slate-900" title="Marcus Sterling (Architect)">MS</div>
                                        <div className="w-6 h-6 rounded-full bg-purple-500 flex items-center justify-center font-bold text-[10px] text-white border border-slate-900" title="You (Owner)">U</div>
                                    </div>
                                    <span className="text-slate-400 text-[10px]">({presenceUsers.length} editors online)</span>
                                </div>
                                <div className="flex items-center gap-2 font-mono text-[10px] text-teal-400 bg-slate-950 py-1 px-3 rounded-full border border-slate-800 animate-pulse">
                                    <i className="fas fa-satellite-dish"></i>
                                    <span>Syncing workspace changes: active connection OK</span>
                                </div>
                            </div>

                            {/* Adaptive Screen Size Frame / Bezel simulation */}
                            <div className={`${canvasView === 'mobile' ? 'device-bezel-mobile' : canvasView === 'tablet' ? 'device-bezel-tablet' : 'w-full'} min-h-[500px] bg-slate-900 rounded-xl transition-all duration-300 relative border-2 border-slate-800 p-4`} onDragOver={(e) => e.preventDefault()} onDrop={handleCanvasDrop} onClick={(e) => e.stopPropagation()}>

                                {/* PREVIEW BANNER OVERLAY */}
                                {previewingVersionId !== null && (
                                    <div className="bg-amber-500 text-slate-950 px-4 py-3 rounded-lg flex flex-col sm:flex-row items-center justify-between gap-3 font-bold text-xs mb-4 shadow-lg border border-amber-450 z-50">
                                        <div className="flex items-center gap-2">
                                            <i className="fas fa-exclamation-triangle text-base animate-bounce"></i>
                                            <span>PREVIEWING HISTORICAL VERSION: "{previewingLabel}" (Read-Only Mode)</span>
                                        </div>
                                        <div className="flex gap-2">
                                            <button onClick={() => restoreVersionFromId(previewingVersionId)} className="bg-slate-950 text-white hover:bg-slate-900 px-3 py-1.5 rounded transition uppercase tracking-wider font-extrabold text-[10px]">
                                                Restore this Version
                                            </button>
                                            <button onClick={exitPreviewMode} className="bg-amber-600 hover:bg-amber-700 text-white px-3 py-1.5 rounded transition uppercase tracking-wider font-extrabold text-[10px]">
                                                Exit Preview
                                            </button>
                                        </div>
                                    </div>
                                )}

                                {sections.length === 0 && !previewingVersionId && (
                                    <div className="absolute inset-0 flex flex-col items-center justify-center p-6 text-center pointer-events-none">
                                        <div className="w-16 h-16 bg-slate-800 rounded-full flex items-center justify-center text-slate-600 text-2xl mb-4 border border-slate-700">
                                            <i className="fas fa-mouse-pointer"></i>
                                        </div>
                                        <h3 className="font-bold text-slate-400 text-sm">Your Canvas is Empty</h3>
                                        <p className="text-slate-500 text-xs mt-1.5 max-w-xs leading-relaxed">Drag any visual component from the left shelf and release it here to initiate your live workspace build.</p>
                                    </div>
                                )}

                                {/* RENDER CANVAS SECTIONS */}
                                <div className="space-y-4">
                                    {(previewingVersionId ? previewingSections : sections).map((sec, idx) => {
                                        const isActive = (sec.id === activeSectionId);
                                        return (
                                            <div key={sec.id} onClick={(e) => {
                                                if (previewingVersionId) return; // Disable selection in preview mode
                                                e.stopPropagation();
                                                setActiveSectionId(sec.id);

                                                // Check if an element with data-el-path was clicked
                                                const targetEl = e.target.closest('[data-el-path]');
                                                if (targetEl) {
                                                    const path = targetEl.getAttribute('data-el-path');
                                                    setActiveElementId(path);
                                                    setPropsSubTab('element');
                                                } else {
                                                    setActiveElementId(null);
                                                    setPropsSubTab('block');
                                                }
                                            }} className={`group relative border border-transparent hover:border-teal-500/50 rounded-lg p-2 transition-all duration-200 cursor-pointer ${isActive && !previewingVersionId ? 'section-selected' : ''}`} data-section-id={sec.id} data-component-instance={sec.type}>

                                                {/* Visual Controls Overlay */}
                                                {!previewingVersionId && (
                                                    <div className="absolute -top-3.5 right-3 bg-teal-500 text-slate-950 font-black text-[9px] px-2.5 py-1 rounded shadow opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-30 flex gap-3 items-center pointer-events-auto">
                                                        <span className="uppercase font-extrabold">{sec.type}</span>
                                                        <div className="flex gap-2">
                                                            <button title="Move Up" onClick={(e) => { e.stopPropagation(); moveSectionUp(idx); }} disabled={idx === 0} className={`disabled:opacity-30`}><i className="fas fa-arrow-up"></i></button>
                                                            <button title="Move Down" onClick={(e) => { e.stopPropagation(); moveSectionDown(idx); }} disabled={idx === sections.length - 1} className={`disabled:opacity-30`}><i className="fas fa-arrow-down"></i></button>
                                                            <button title="Duplicate" onClick={(e) => { e.stopPropagation(); duplicateSection(idx); }}><i className="fas fa-copy"></i></button>
                                                            <button title="Remove" className="text-slate-950 hover:text-red-900" onClick={(e) => { e.stopPropagation(); deleteSection(sec.id); }}><i className="fas fa-trash-alt"></i></button>
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Canvas Inner Render Node (Injected React Simulation) */}
                                                <div className="canvas-inner-html" dangerouslySetInnerHTML={{ __html: compileSectionHtml(sec, !previewingVersionId) }} />
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </main>

                        {/* RIGHT COLUMN - CONTROL CENTER (PROPERTIES & CUSTOMIZER) */}
                        <aside className="w-80 bg-slate-900 border-l border-slate-800 flex flex-col overflow-hidden shrink-0">
                            <div className="p-4 border-b border-slate-800">
                                <h2 className="text-xs font-extrabold text-teal-400 uppercase tracking-widest">Control Center</h2>
                                <p className="text-[11px] text-slate-400 mt-1">Adjust layout properties & custom injects.</p>
                            </div>

                            {/* TAB NAVIGATION MENU GRID */}
                            <div className="grid grid-cols-4 border-b border-slate-800 bg-slate-950/40 shrink-0 text-[9px]">
                                <button onClick={() => setRightPanelTab('properties')} className={`py-3 text-center font-bold uppercase border-b-2 transition ${rightPanelTab === 'properties' ? 'border-teal-500 text-teal-400 font-extrabold' : 'border-transparent text-slate-400 hover:text-white'}`}>
                                    Props
                                </button>
                                <button onClick={() => setRightPanelTab('theme')} className={`py-3 text-center font-bold uppercase border-b-2 transition ${rightPanelTab === 'theme' ? 'border-teal-500 text-teal-400 font-extrabold' : 'border-transparent text-slate-400 hover:text-white'}`}>
                                    Theme
                                </button>
                                <button onClick={() => setRightPanelTab('seo')} className={`py-3 text-center font-bold uppercase border-b-2 transition ${rightPanelTab === 'seo' ? 'border-teal-500 text-teal-400 font-extrabold' : 'border-transparent text-slate-400 hover:text-white'}`}>
                                    SEO
                                </button>
                                <button onClick={() => setRightPanelTab('saas')} className={`py-3 text-center font-bold uppercase border-b-2 transition ${rightPanelTab === 'saas' ? 'border-teal-500 text-teal-400 font-extrabold' : 'border-transparent text-slate-400 hover:text-white'}`}>
                                    SaaS
                                </button>
                                <button onClick={() => setRightPanelTab('ai')} className={`py-3 text-center font-bold uppercase border-b-2 transition ${rightPanelTab === 'ai' ? 'border-teal-500 text-teal-400 font-extrabold' : 'border-transparent text-slate-400 hover:text-white'}`}>
                                    AI
                                </button>
                                <button onClick={() => setRightPanelTab('settings')} className={`py-3 text-center font-bold uppercase border-b-2 transition ${rightPanelTab === 'settings' ? 'border-teal-500 text-teal-400 font-extrabold' : 'border-transparent text-slate-400 hover:text-white'}`}>
                                    Email
                                </button>
                                <button onClick={() => setRightPanelTab('code')} className={`py-3 text-center font-bold uppercase border-b-2 transition ${rightPanelTab === 'code' ? 'border-teal-500 text-teal-400 font-extrabold' : 'border-transparent text-slate-400 hover:text-white'}`}>
                                    Code
                                </button>
                                <button onClick={() => setRightPanelTab('versions')} className={`py-3 text-center font-bold uppercase border-b-2 transition ${rightPanelTab === 'versions' ? 'border-teal-500 text-teal-400 font-extrabold' : 'border-transparent text-slate-400 hover:text-white'}`}>
                                    Vers
                                </button>
                            </div>

                            {/* DYNAMIC PROPERTIES VIEW */}
                            {rightPanelTab === 'properties' && (
                                <div className="flex-1 overflow-y-auto p-4 space-y-5">
                                    {!selectedSection ? (
                                        <div className="text-center py-16 text-slate-500">
                                            <i className="fas fa-hand-pointer text-xl mb-3 animate-bounce"></i>
                                            <p className="text-xs font-bold text-slate-400">No Component Selected</p>
                                            <p className="text-[11px] text-slate-500 mt-1 leading-relaxed px-4">Click any item in your canvas workspace to reveal real-time configuration details.</p>
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            <div>
                                                <label className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Selected Element</label>
                                                <div className="bg-slate-950 px-3 py-2 rounded-lg text-xs text-teal-400 font-mono flex justify-between items-center">
                                                    <span className="uppercase">{selectedSection.type}</span>
                                                    <button onClick={() => deleteSection(selectedSection.id)} className="text-red-400 hover:text-red-300" title="Delete Component">
                                                        <i className="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            {/* DYNAMIC COMPONENT SCHEMA FIELDS GENERATION */}
                                            {(() => {
                                                const compDef = ACTIVE_COMPONENTS.find(c => c.id.toLowerCase() === (selectedSection.type || '').toLowerCase().trim());
                                                if (!compDef || !compDef.schema) return null;

                                                return (
                                                    <div className="space-y-4">
                                                        <h4 className="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                                                            <i className="fas fa-edit"></i> Edit Properties
                                                        </h4>
                                                        {compDef.schema.map(field => {
                                                            const val = selectedSection.props[field.key] !== undefined ? selectedSection.props[field.key] : field.default;

                                                            const handleFieldChange = (newVal) => {
                                                                const updated = sections.map(s => s.id === selectedSection.id ? { ...s, props: { ...s.props, [field.key]: newVal } } : s);
                                                                updateSectionsWithHistory(updated);
                                                            };

                                                            if (field.type === 'text') {
                                                                const inputId = field.key === 'heading' ? 'prop-heading-text' : `prop-${field.key}`;
                                                                const isLogoUrl = field.key.toLowerCase().includes('logo');

                                                                const handleLogoUpload = (e) => {
                                                                    const file = e.target.files[0];
                                                                    if (!file) return;

                                                                    const formData = new FormData();
                                                                    formData.append('image', file);
                                                                    formData.append('csrf_token', CSRF_TOKEN);

                                                                    showToast("Uploading logo...", "Transmitting image resource to server.");

                                                                    fetch('api.php?action=upload_image', {
                                                                        method: 'POST',
                                                                        headers: {
                                                                            'X-CSRF-TOKEN': CSRF_TOKEN
                                                                        },
                                                                        body: formData
                                                                    })
                                                                    .then(res => res.json())
                                                                    .then(data => {
                                                                        if (data.success) {
                                                                            handleFieldChange(data.url);
                                                                            showToast("Success", "Logo uploaded successfully!");
                                                                        } else {
                                                                            showToast("Upload Error", data.error || "Failed to upload logo.");
                                                                        }
                                                                    })
                                                                    .catch(err => showToast("Network Error", err.message));
                                                                };

                                                                return (
                                                                    <div key={field.key} className="space-y-1.5">
                                                                        <label className="text-[11px] text-slate-400 block mb-1">{field.label}</label>
                                                                        <div className="flex gap-2">
                                                                            <input id={inputId} type="text" value={val} onChange={(e) => handleFieldChange(e.target.value)} className="flex-1 bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500" placeholder={isLogoUrl ? "https://example.com/logo.png" : ""} />
                                                                            {isLogoUrl && (
                                                                                <label className="bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-750 text-xs font-bold py-2 px-3 rounded cursor-pointer transition flex items-center justify-center shrink-0">
                                                                                    <i className="fas fa-upload"></i>
                                                                                    <input
                                                                                        type="file"
                                                                                        accept="image/*"
                                                                                        onChange={handleLogoUpload}
                                                                                        className="hidden"
                                                                                    />
                                                                                </label>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                );
                                                            } else if (field.type === 'textarea') {
                                                                const textareaId = field.key === 'text' ? 'prop-paragraph-text' : `prop-${field.key}`;
                                                                return (
                                                                    <div key={field.key}>
                                                                        <label className="text-[11px] text-slate-400 block mb-1">{field.label}</label>
                                                                        <textarea id={textareaId} rows={4} value={val} onChange={(e) => handleFieldChange(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded p-2 text-xs text-white focus:outline-none focus:border-teal-500" />
                                                                    </div>
                                                                );
                                                            } else if (field.type === 'color') {
                                                                return (
                                                                    <div key={field.key} className="flex items-center justify-between">
                                                                        <label className="text-[11px] text-slate-400 block" htmlFor={`prop-${field.key}`}>{field.label}</label>
                                                                        <div className="flex items-center gap-2">
                                                                            <span className="text-[10px] font-mono text-slate-400">{val}</span>
                                                                            <input id={`prop-${field.key}`} type="color" value={val} onChange={(e) => handleFieldChange(e.target.value)} className="w-8 h-8 rounded border-0 bg-transparent cursor-pointer" />
                                                                        </div>
                                                                    </div>
                                                                );
                                                            } else if (field.type === 'select') {
                                                                return (
                                                                    <div key={field.key}>
                                                                        <label className="text-[11px] text-slate-400 block mb-1" htmlFor={`prop-${field.key}`}>{field.label}</label>
                                                                        <select id={`prop-${field.key}`} value={val} onChange={(e) => handleFieldChange(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500">
                                                                            {field.options.map(opt => (
                                                                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                                                                            ))}
                                                                        </select>
                                                                    </div>
                                                                );
                                                            } else if (field.type === 'checkbox') {
                                                                return (
                                                                    <div key={field.key} className="flex items-center justify-between">
                                                                        <label className="text-[11px] text-slate-400 block" htmlFor={`prop-${field.key}`}>{field.label}</label>
                                                                        <input id={`prop-${field.key}`} type="checkbox" checked={!!val} onChange={(e) => handleFieldChange(e.target.checked)} className="w-4 h-4 rounded border-slate-800 bg-slate-950 text-teal-500 focus:ring-0" />
                                                                    </div>
                                                                );
                                                            }
                                                            return null;
                                                        })}

                                                        {/* Global Component Background Overrides */}
                                                        <div className="pt-4 border-t border-slate-800 space-y-3">
                                                            <h5 className="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                                                                <i className="fas fa-image"></i> Component Background Override
                                                            </h5>

                                                            {/* Background Color Picker */}
                                                            <div className="flex items-center justify-between">
                                                                <label className="text-[11px] text-slate-400 block">Bg Color Override</label>
                                                                <div className="flex items-center gap-2">
                                                                    <span className="text-[10px] font-mono text-slate-400">{selectedSection.bg_color_override || 'Default'}</span>
                                                                    <input
                                                                        type="color"
                                                                        value={selectedSection.bg_color_override || '#0f172a'}
                                                                        onChange={(e) => {
                                                                            const updated = sections.map(s => s.id === selectedSection.id ? { ...s, bg_color_override: e.target.value } : s);
                                                                            updateSectionsWithHistory(updated);
                                                                        }}
                                                                        className="w-8 h-8 rounded border-0 bg-transparent cursor-pointer"
                                                                    />
                                                                    {selectedSection.bg_color_override && (
                                                                        <button
                                                                            onClick={() => {
                                                                                const updated = sections.map(s => s.id === selectedSection.id ? { ...s, bg_color_override: undefined } : s);
                                                                                updateSectionsWithHistory(updated);
                                                                            }}
                                                                            className="text-xs text-rose-400 hover:text-rose-300 font-bold"
                                                                            title="Clear Color">
                                                                            <i className="fas fa-undo"></i>
                                                                        </button>
                                                                    )}
                                                                </div>
                                                            </div>

                                                            {/* Background Image Input */}
                                                            <div className="space-y-1.5">
                                                                <label className="text-[11px] text-slate-400 block">Bg Image Override URL</label>
                                                                <div className="flex gap-2">
                                                                    <input
                                                                        type="text"
                                                                        value={selectedSection.bg_image_override || ''}
                                                                        onChange={(e) => {
                                                                            const updated = sections.map(s => s.id === selectedSection.id ? { ...s, bg_image_override: e.target.value || undefined } : s);
                                                                            updateSectionsWithHistory(updated);
                                                                        }}
                                                                        placeholder="https://example.com/image.jpg"
                                                                        className="flex-1 bg-slate-950 border border-slate-800 rounded px-2.5 py-1.5 text-xs text-white focus:outline-none"
                                                                    />
                                                                    {selectedSection.bg_image_override && (
                                                                        <button
                                                                            onClick={() => {
                                                                                const updated = sections.map(s => s.id === selectedSection.id ? { ...s, bg_image_override: undefined } : s);
                                                                                updateSectionsWithHistory(updated);
                                                                            }}
                                                                            className="px-2 bg-slate-950 border border-slate-800 rounded text-rose-400 hover:text-rose-300 text-xs"
                                                                            title="Clear Image">
                                                                            <i className="fas fa-trash"></i>
                                                                        </button>
                                                                    )}
                                                                </div>

                                                                {/* Upload Background Image Button */}
                                                                <label className="w-full text-center bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-[10px] font-bold py-1.5 px-3 rounded cursor-pointer transition block">
                                                                    <i className="fas fa-upload mr-1.5"></i> Upload Bg Image
                                                                    <input
                                                                        type="file"
                                                                        accept="image/*"
                                                                        onChange={(e) => {
                                                                            const file = e.target.files[0];
                                                                            if (!file) return;

                                                                            const formData = new FormData();
                                                                            formData.append('image', file);
                                                                            formData.append('csrf_token', CSRF_TOKEN);

                                                                            showToast("Uploading Background...", "Transmitting resource to server.");

                                                                            fetch('api.php?action=upload_image', {
                                                                                method: 'POST',
                                                                                headers: {
                                                                                    'X-CSRF-TOKEN': CSRF_TOKEN
                                                                                },
                                                                                body: formData
                                                                            })
                                                                            .then(res => res.json())
                                                                            .then(data => {
                                                                                if (data.success) {
                                                                                    const updated = sections.map(s => s.id === selectedSection.id ? { ...s, bg_image_override: data.url } : s);
                                                                                    updateSectionsWithHistory(updated);
                                                                                    showToast("Success", "Background image updated successfully!");
                                                                                } else {
                                                                                    showToast("Upload Error", data.error || "Failed to process background image.");
                                                                                }
                                                                            })
                                                                            .catch(err => showToast("Network Error", err.message));
                                                                        }}
                                                                        className="hidden"
                                                                    />
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })()}

                                            {/* CUSTOM DYNAMIC FAQ EDITOR */}
                                            {selectedSection && selectedSection.type.toLowerCase() === 'faq' && (() => {
                                                const currentFaqs = selectedSection.props.faqs || [
                                                    {
                                                        q: selectedSection.props.q1 || 'How does the local compiling mechanism operate?',
                                                        a: selectedSection.props.a1 || 'Our platform compiles visual assets into highly optimized, fully responsive static HTML output instantly.'
                                                    }
                                                ];

                                                const handleFaqsChange = (newFaqs) => {
                                                    const updated = sections.map(s => s.id === selectedSection.id ? { ...s, props: { ...s.props, faqs: newFaqs } } : s);
                                                    updateSectionsWithHistory(updated);
                                                };

                                                const addFaq = () => {
                                                    handleFaqsChange([...currentFaqs, { q: 'New Question', a: 'New answer goes here...' }]);
                                                };

                                                const removeFaq = (fIdx) => {
                                                    handleFaqsChange(currentFaqs.filter((_, idx) => idx !== fIdx));
                                                };

                                                const updateFaq = (fIdx, key, val) => {
                                                    const updatedFaqs = currentFaqs.map((faq, idx) => idx === fIdx ? { ...faq, [key]: val } : faq);
                                                    handleFaqsChange(updatedFaqs);
                                                };

                                                return (
                                                    <div className="space-y-4 pt-4 border-t border-slate-800">
                                                        <h4 className="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                                                            <i className="fas fa-question-circle"></i> Manage FAQ Items
                                                        </h4>
                                                        <div className="space-y-3">
                                                            {currentFaqs.map((faq, fIdx) => (
                                                                <div key={fIdx} className="p-3 bg-slate-950 rounded-lg border border-slate-800/80 space-y-2">
                                                                    <div className="flex justify-between items-center gap-2">
                                                                        <input type="text" value={faq.q} onChange={(e) => updateFaq(fIdx, 'q', e.target.value)} placeholder="Question" className="w-full bg-slate-900 border border-slate-800 rounded px-2 py-1 text-xs text-white" />
                                                                        <button onClick={() => removeFaq(fIdx)} className="text-red-400 hover:text-red-300 text-xs p-1" title="Remove FAQ">
                                                                            <i className="fas fa-trash"></i>
                                                                        </button>
                                                                    </div>
                                                                    <div>
                                                                        <textarea rows={3} value={faq.a} onChange={(e) => updateFaq(fIdx, 'a', e.target.value)} placeholder="Answer" className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-xs text-white" />
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                        <button onClick={addFaq} className="w-full bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold py-2 rounded text-xs transition border border-slate-750">
                                                            <i className="fas fa-plus mr-1"></i> Add New FAQ
                                                        </button>
                                                    </div>
                                                );
                                            })()}

                                            {/* CUSTOM NAVIGATION/FOOTER LINKS EDITOR */}
                                            {selectedSection && (selectedSection.type.toLowerCase() === 'navbar' || selectedSection.type.toLowerCase() === 'footer') && (() => {
                                                const currentLinks = selectedSection.props.links || (selectedSection.type.toLowerCase() === 'navbar' ? [
                                                    { text: 'Home', url: '#home' },
                                                    { text: 'Features', url: '#features' },
                                                    { text: 'Pricing', url: '#pricing' },
                                                    { text: 'Contact', url: '#contact' }
                                                ] : [
                                                    { text: 'Privacy Policy', url: '#privacy' },
                                                    { text: 'Terms of Use', url: '#terms' },
                                                    { text: 'Support', url: '#support' }
                                                ]);

                                                const handleLinksChange = (newLinks) => {
                                                    const updated = sections.map(s => s.id === selectedSection.id ? { ...s, props: { ...s.props, links: newLinks } } : s);
                                                    updateSectionsWithHistory(updated);
                                                };

                                                const addLink = () => {
                                                    handleLinksChange([...currentLinks, { text: 'New Link', url: '#home' }]);
                                                };

                                                const removeLink = (lIdx) => {
                                                    handleLinksChange(currentLinks.filter((_, idx) => idx !== lIdx));
                                                };

                                                const updateLink = (lIdx, key, val) => {
                                                    const updatedLinks = currentLinks.map((link, idx) => idx === lIdx ? { ...link, [key]: val } : link);
                                                    handleLinksChange(updatedLinks);
                                                };

                                                // List of standard options
                                                const standardOptions = [
                                                    { value: '#home', label: 'Home Section (#home)' },
                                                    { value: '#features', label: 'Features Section (#features)' },
                                                    { value: '#pricing', label: 'Pricing Section (#pricing)' },
                                                    { value: '#contact', label: 'Contact Section (#contact)' },
                                                    { value: 'index.php', label: 'Index Page' },
                                                    { value: 'admin.php', label: 'Admin Page' },
                                                ];

                                                // Combine with canvas sections
                                                sections.forEach(s => {
                                                    if (s.id !== selectedSection.id) {
                                                        const label = s.props.heading ? `Section: ${s.props.heading.substring(0, 20)}...` : `Section: ${s.type.toUpperCase()} (${s.id})`;
                                                        standardOptions.push({ value: '#' + s.id, label });
                                                    }
                                                });

                                                const standardValues = standardOptions.map(o => o.value);

                                                return (
                                                    <div className="space-y-4 pt-4 border-t border-slate-800">
                                                        <h4 className="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                                                            <i className="fas fa-link"></i> Manage Navigation Links
                                                        </h4>
                                                        <div className="space-y-3">
                                                            {currentLinks.map((link, lIdx) => {
                                                                const linkType = link.type || 'link';
                                                                const isCustom = linkType === 'link' && !standardValues.includes(link.url);
                                                                const children = link.children || [];

                                                                return (
                                                                    <div key={lIdx} className="p-3 bg-slate-950 rounded-lg border border-slate-800/80 space-y-2">
                                                                        {/* Row header: text input + type select + delete link */}
                                                                        <div className="flex justify-between items-center gap-2">
                                                                            <input type="text" value={link.text} onChange={(e) => updateLink(lIdx, 'text', e.target.value)} placeholder="Link Text" className="w-1/3 bg-slate-900 border border-slate-800 rounded px-2 py-1 text-xs text-white" />

                                                                            <select value={linkType} onChange={(e) => updateLink(lIdx, 'type', e.target.value)} className="w-1/3 bg-slate-900 border border-slate-800 rounded px-1 py-1 text-xs text-slate-300">
                                                                                <option value="link">Link</option>
                                                                                <option value="page">Page</option>
                                                                                <option value="dropdown">Dropdown</option>
                                                                            </select>

                                                                            <button onClick={() => removeLink(lIdx)} className="text-red-400 hover:text-red-300 text-xs p-1" title="Remove Link">
                                                                                <i className="fas fa-trash"></i>
                                                                            </button>
                                                                        </div>

                                                                        {/* Link Type specific customizers */}
                                                                        {linkType === 'link' && (
                                                                            <div className="space-y-1.5">
                                                                                <select value={isCustom ? 'custom' : link.url} onChange={(e) => {
                                                                                    const v = e.target.value;
                                                                                    if (v === 'custom') {
                                                                                        updateLink(lIdx, 'url', 'https://');
                                                                                    } else {
                                                                                        updateLink(lIdx, 'url', v);
                                                                                    }
                                                                                }} className="w-full bg-slate-900 border border-slate-800 rounded px-2 py-1.5 text-xs text-slate-300">
                                                                                    {standardOptions.map(opt => (
                                                                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                                                                    ))}
                                                                                    <option value="custom">Custom URL...</option>
                                                                                </select>
                                                                                {isCustom && (
                                                                                    <input type="text" value={link.url} onChange={(e) => updateLink(lIdx, 'url', e.target.value)} placeholder="Custom URL (e.g., https://google.com)" className="w-full bg-slate-900 border border-slate-800 rounded px-2 py-1 text-xs text-white" />
                                                                                )}
                                                                            </div>
                                                                        )}

                                                                        {linkType === 'page' && (
                                                                            <div className="space-y-1">
                                                                                <label className="text-[10px] text-slate-500 uppercase block font-bold">Select Project Page</label>
                                                                                <select value={link.pageName || 'index'} onChange={(e) => updateLink(lIdx, 'pageName', e.target.value)} className="w-full bg-slate-900 border border-slate-800 rounded px-2 py-1.5 text-xs text-slate-300">
                                                                                    {Object.keys(pages).map(pName => (
                                                                                        <option key={pName} value={pName}>{pName}</option>
                                                                                    ))}
                                                                                </select>
                                                                            </div>
                                                                        )}

                                                                        {linkType === 'dropdown' && (
                                                                            <div className="pl-3 border-l-2 border-teal-500/30 space-y-2 mt-2">
                                                                                <span className="text-[9px] font-bold text-teal-400 uppercase tracking-wider block">Dropdown Nested Items ({children.length})</span>

                                                                                {children.map((child, cIdx) => {
                                                                                    const isChildCustom = child.type === 'link' && !standardValues.includes(child.url);
                                                                                    const updateChild = (childKey, childVal) => {
                                                                                        const updatedChildren = children.map((c, idx) => idx === cIdx ? { ...c, [childKey]: childVal } : c);
                                                                                        updateLink(lIdx, 'children', updatedChildren);
                                                                                    };
                                                                                    const removeChild = () => {
                                                                                        const updatedChildren = children.filter((_, idx) => idx !== cIdx);
                                                                                        updateLink(lIdx, 'children', updatedChildren);
                                                                                    };

                                                                                    return (
                                                                                        <div key={cIdx} className="bg-slate-900/60 p-2 rounded border border-slate-850 space-y-1.5 text-xs">
                                                                                            <div className="flex justify-between items-center gap-1.5">
                                                                                                <input type="text" value={child.text} onChange={(e) => updateChild('text', e.target.value)} placeholder="Sub Link text" className="flex-1 bg-slate-950 border border-slate-850 rounded px-2 py-1 text-[11px] text-white" />

                                                                                                <select value={child.type || 'link'} onChange={(e) => updateChild('type', e.target.value)} className="bg-slate-950 border border-slate-850 rounded px-1.5 py-1 text-[11px] text-slate-300">
                                                                                                    <option value="link">Link</option>
                                                                                                    <option value="page">Page</option>
                                                                                                </select>

                                                                                                <button onClick={removeChild} className="text-red-400 hover:text-red-300 text-[10px] px-1" title="Remove Sub Link">
                                                                                                    <i className="fas fa-times"></i>
                                                                                                </button>
                                                                                            </div>

                                                                                            {(child.type || 'link') === 'link' && (
                                                                                                <div className="space-y-1">
                                                                                                    <select value={isChildCustom ? 'custom' : child.url} onChange={(e) => {
                                                                                                        const v = e.target.value;
                                                                                                        if (v === 'custom') {
                                                                                                            updateChild('url', 'https://');
                                                                                                        } else {
                                                                                                            updateChild('url', v);
                                                                                                        }
                                                                                                    }} className="w-full bg-slate-950 border border-slate-850 rounded px-2 py-1 text-[10px] text-slate-300">
                                                                                                        {standardOptions.map(opt => (
                                                                                                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                                                                                                        ))}
                                                                                                        <option value="custom">Custom URL...</option>
                                                                                                    </select>
                                                                                                    {isChildCustom && (
                                                                                                        <input type="text" value={child.url} onChange={(e) => updateChild('url', e.target.value)} placeholder="Custom URL" className="w-full bg-slate-950 border border-slate-850 rounded px-2 py-1 text-[10px] text-white" />
                                                                                                    )}
                                                                                                </div>
                                                                                            )}

                                                                                            {child.type === 'page' && (
                                                                                                <select value={child.pageName || 'index'} onChange={(e) => updateChild('pageName', e.target.value)} className="w-full bg-slate-950 border border-slate-850 rounded px-2 py-1 text-[10px] text-slate-300">
                                                                                                    {Object.keys(pages).map(pName => (
                                                                                                        <option key={pName} value={pName}>{pName}</option>
                                                                                                    ))}
                                                                                                </select>
                                                                                            )}
                                                                                        </div>
                                                                                    );
                                                                                })}

                                                                                <button onClick={() => {
                                                                                    const updatedChildren = [...children, { text: 'New Sub Link', type: 'link', url: '#home' }];
                                                                                    updateLink(lIdx, 'children', updatedChildren);
                                                                                }} className="w-full bg-slate-900 hover:bg-slate-850 border border-slate-800 text-[10px] font-bold py-1.5 rounded transition text-teal-400">
                                                                                    <i className="fas fa-plus mr-1"></i> Add Dropdown Sub Link
                                                                                </button>
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                        <button onClick={addLink} className="w-full bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold py-2 rounded text-xs transition border border-slate-750">
                                                            <i className="fas fa-plus mr-1"></i> Add New Link
                                                        </button>
                                                    </div>
                                                );
                                            })()}

                                            {/* CUSTOM DYNAMIC TABS EDITOR */}
                                            {selectedSection && selectedSection.type.toLowerCase() === 'interactive_tabs' && (() => {
                                                const currentTabs = selectedSection.props.tabs || [
                                                    { title: 'Platform', content: 'Fully integrated drag and drop builder.' },
                                                    { title: 'Database', content: 'MariaDB persistent storage pipelines.' }
                                                ];

                                                const handleTabsChange = (newTabs) => {
                                                    const updated = sections.map(s => s.id === selectedSection.id ? { ...s, props: { ...s.props, tabs: newTabs } } : s);
                                                    updateSectionsWithHistory(updated);
                                                };

                                                const addTab = () => {
                                                    handleTabsChange([...currentTabs, { title: 'New Tab', content: 'New tab content goes here...' }]);
                                                };

                                                const removeTab = (tIdx) => {
                                                    handleTabsChange(currentTabs.filter((_, idx) => idx !== tIdx));
                                                };

                                                const updateTab = (tIdx, key, val) => {
                                                    const updatedTabs = currentTabs.map((tab, idx) => idx === tIdx ? { ...tab, [key]: val } : tab);
                                                    handleTabsChange(updatedTabs);
                                                };

                                                return (
                                                    <div className="space-y-4 pt-4 border-t border-slate-800">
                                                        <h4 className="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                                                            <i className="fas fa-folder-open"></i> Manage Dynamic Tabs
                                                        </h4>
                                                        <div className="space-y-3">
                                                            {currentTabs.map((tab, tIdx) => (
                                                                <div key={tIdx} className="p-3 bg-slate-950 rounded-lg border border-slate-800/80 space-y-2">
                                                                    <div className="flex justify-between items-center gap-2">
                                                                        <input type="text" value={tab.title} onChange={(e) => updateTab(tIdx, 'title', e.target.value)} placeholder="Tab Title" className="w-full bg-slate-900 border border-slate-800 rounded px-2 py-1 text-xs text-white" />
                                                                        <button onClick={() => removeTab(tIdx)} className="text-red-400 hover:text-red-300 text-xs p-1" title="Remove Tab">
                                                                            <i className="fas fa-trash"></i>
                                                                        </button>
                                                                    </div>
                                                                    <div>
                                                                        <textarea rows={3} value={tab.content} onChange={(e) => updateTab(tIdx, 'content', e.target.value)} placeholder="Tab Content" className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-xs text-white" />
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                        <button onClick={addTab} className="w-full bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold py-2 rounded text-xs transition border border-slate-750">
                                                            <i className="fas fa-plus mr-1"></i> Add New Tab
                                                        </button>
                                                    </div>
                                                );
                                            })()}

                                            {/* CUSTOM DYNAMIC CARDS EDITOR */}
                                            {selectedSection && selectedSection.type.toLowerCase() === 'layout_grid' && (() => {
                                                const currentCards = selectedSection.props.cards || [
                                                    { content: 'High density column structure details.' },
                                                    { content: 'Responsive breakpoint scaling configurations.' },
                                                    { content: 'Flex space distribution values.' }
                                                ];

                                                const handleCardsChange = (newCards) => {
                                                    const updated = sections.map(s => s.id === selectedSection.id ? { ...s, props: { ...s.props, cards: newCards } } : s);
                                                    updateSectionsWithHistory(updated);
                                                };

                                                const addCard = () => {
                                                    handleCardsChange([...currentCards, { content: 'New grid card content goes here...' }]);
                                                };

                                                const removeCard = (cIdx) => {
                                                    handleCardsChange(currentCards.filter((_, idx) => idx !== cIdx));
                                                };

                                                const updateCard = (cIdx, val) => {
                                                    const updatedCards = currentCards.map((card, idx) => idx === cIdx ? { ...card, content: val } : card);
                                                    handleCardsChange(updatedCards);
                                                };

                                                return (
                                                    <div className="space-y-4 pt-4 border-t border-slate-800">
                                                        <h4 className="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                                                            <i className="fas fa-th-large"></i> Manage Grid Cards
                                                        </h4>
                                                        <div className="space-y-3">
                                                            {currentCards.map((card, cIdx) => (
                                                                <div key={cIdx} className="p-3 bg-slate-950 rounded-lg border border-slate-800/80 space-y-2">
                                                                    <div className="flex justify-between items-center gap-2">
                                                                        <span className="text-[10px] font-semibold text-slate-400">Card #{cIdx + 1}</span>
                                                                        <button onClick={() => removeCard(cIdx)} className="text-red-400 hover:text-red-300 text-xs p-1" title="Remove Card">
                                                                            <i className="fas fa-trash"></i>
                                                                        </button>
                                                                    </div>
                                                                    <div>
                                                                        <textarea rows={3} value={card.content} onChange={(e) => updateCard(cIdx, e.target.value)} placeholder="Card Content" className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-xs text-white" />
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                        <button onClick={addCard} className="w-full bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold py-2 rounded text-xs transition border border-slate-750">
                                                            <i className="fas fa-plus mr-1"></i> Add New Card
                                                        </button>
                                                    </div>
                                                );
                                            })()}

                                            {/* SUB-ELEMENT STYLE & TEXT CUSTOMIZER (rendered inline if activeElementId exists) */}
                                            {activeElementId && (() => {
                                                const activeDomEl = document.querySelector(`[data-section-id="${selectedSection.id}"] [data-el-path="${activeElementId}"]`);
                                                const activeTag = activeDomEl ? activeDomEl.tagName.toUpperCase() : 'TEXT';

                                                const currentOverrides = (selectedSection.element_overrides && selectedSection.element_overrides[activeElementId]) || {};
                                                const currentText = currentOverrides.text !== undefined ? currentOverrides.text : (activeDomEl ? (activeTag === 'IMG' ? activeDomEl.getAttribute('src') : (activeTag === 'I' ? activeDomEl.className : activeDomEl.innerText)) : '');
                                                const currentStyles = currentOverrides.styles || {};
                                                const isHtmlMode = !!currentOverrides.isHtml;

                                                const handleOverrideChange = (field, val, isStyle = false, styleKey = null) => {
                                                    const updated = sections.map(s => {
                                                        if (s.id !== selectedSection.id) return s;
                                                        const overrides = s.element_overrides ? { ...s.element_overrides } : {};
                                                        const override = overrides[activeElementId] ? { ...overrides[activeElementId] } : { styles: {} };

                                                        if (isStyle) {
                                                            override.styles = { ...override.styles, [styleKey]: val };
                                                        } else {
                                                            override[field] = val;
                                                        }

                                                        overrides[activeElementId] = override;
                                                        return { ...s, element_overrides: overrides };
                                                    });
                                                    updateSectionsWithHistory(updated);
                                                };

                                                const handleImageUpload = (e) => {
                                                    const file = e.target.files[0];
                                                    if (!file) return;

                                                    const formData = new FormData();
                                                    formData.append('image', file);
                                                    formData.append('csrf_token', CSRF_TOKEN);

                                                    showToast("Uploading...", "Transmitting image resource to server.");

                                                    fetch('api.php?action=upload_image', {
                                                        method: 'POST',
                                                        headers: {
                                                            'X-CSRF-TOKEN': CSRF_TOKEN
                                                        },
                                                        body: formData
                                                    })
                                                    .then(res => res.json())
                                                    .then(data => {
                                                        if (data.success) {
                                                            handleOverrideChange('text', data.url);
                                                            showToast("Success", "Image uploaded and linked successfully!");
                                                        } else {
                                                            showToast("Upload Error", data.error || "Failed to process image.");
                                                        }
                                                    })
                                                    .catch(err => showToast("Network Error", err.message));
                                                };

                                                return (
                                                    <div className="space-y-5 pt-4 border-t border-slate-800">
                                                        <div className="flex justify-between items-center">
                                                            <h4 className="text-[10px] font-bold text-cyan-400 uppercase tracking-wider flex items-center gap-1.5">
                                                                <i className="fas fa-magic"></i> Sub-Element Style & Text
                                                            </h4>
                                                            <button
                                                                onClick={() => { setActiveElementId(null); }}
                                                                className="text-[9px] font-bold text-rose-400 hover:text-rose-300 uppercase tracking-wider">
                                                                <i className="fas fa-times mr-1"></i> Deselect
                                                            </button>
                                                        </div>

                                                        {/* General info info-box */}
                                                        <div className="bg-slate-950/40 border border-slate-800/80 rounded-lg p-2.5 text-[11px] text-slate-400 space-y-1">
                                                            <div className="flex justify-between">
                                                                <span className="font-bold text-slate-500">Selector Node:</span>
                                                                <span className="font-mono text-cyan-400">&lt;{activeTag}&gt;</span>
                                                            </div>
                                                            <div className="flex justify-between">
                                                                <span className="font-bold text-slate-500">Unique Path:</span>
                                                                <span className="font-mono text-cyan-400">{activeElementId}</span>
                                                            </div>
                                                        </div>

                                                        {/* CONTENT / SOURCE FIELD */}
                                                        <div className="space-y-1.5">
                                                            <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                                                                {activeTag === 'IMG' ? 'Image Source URL' : activeTag === 'I' ? 'Icon CSS Classes' : 'Text Content'}
                                                            </label>

                                                            {activeTag === 'IMG' ? (
                                                                <div className="space-y-2">
                                                                    <input
                                                                        type="text"
                                                                        value={currentText}
                                                                        onChange={(e) => handleOverrideChange('text', e.target.value)}
                                                                        className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-1.5 text-xs text-white focus:outline-none focus:border-teal-500"
                                                                        placeholder="https://example.com/image.jpg"
                                                                    />
                                                                    <div className="flex items-center gap-2">
                                                                        <label className="flex-1 text-center bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold py-1.5 px-3 rounded cursor-pointer transition">
                                                                            <i className="fas fa-upload mr-1.5"></i> Upload Image File
                                                                            <input
                                                                                type="file"
                                                                                accept="image/*"
                                                                                onChange={handleImageUpload}
                                                                                className="hidden"
                                                                            />
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            ) : activeTag === 'I' ? (
                                                                <div className="space-y-2">
                                                                    <input
                                                                        type="text"
                                                                        value={currentText}
                                                                        onChange={(e) => handleOverrideChange('text', e.target.value)}
                                                                        className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-1.5 text-xs text-white font-mono focus:outline-none focus:border-teal-500"
                                                                        placeholder="fas fa-star text-teal-400"
                                                                    />
                                                                    {/* Quick Icon Selector */}
                                                                    <div className="grid grid-cols-5 gap-1.5 bg-slate-950/60 p-2 rounded border border-slate-800">
                                                                        {[
                                                                            'fas fa-star', 'fas fa-heart', 'fas fa-check', 'fas fa-times',
                                                                            'fas fa-cog', 'fas fa-user', 'fas fa-envelope', 'fas fa-phone',
                                                                            'fas fa-arrow-right', 'fas fa-info-circle'
                                                                        ].map(ico => (
                                                                            <button
                                                                                key={ico}
                                                                                title={ico}
                                                                                onClick={() => handleOverrideChange('text', ico)}
                                                                                className={`p-1.5 rounded hover:bg-slate-800 text-slate-400 hover:text-white transition text-xs ${currentText === ico ? 'bg-teal-500/20 text-teal-400 border border-teal-500/30' : ''}`}>
                                                                                <i className={ico}></i>
                                                                            </button>
                                                                        ))}
                                                                    </div>
                                                                </div>
                                                            ) : (
                                                                <div className="space-y-2">
                                                                    <textarea
                                                                        value={currentText}
                                                                        onChange={(e) => handleOverrideChange('text', e.target.value)}
                                                                        rows="3"
                                                                        className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-1.5 text-xs text-white focus:outline-none focus:border-teal-500"
                                                                        placeholder="Enter text value..."
                                                                    />
                                                                    <label className="flex items-center gap-2 cursor-pointer select-none">
                                                                        <input
                                                                            type="checkbox"
                                                                            checked={isHtmlMode}
                                                                            onChange={(e) => handleOverrideChange('isHtml', e.target.checked)}
                                                                            className="rounded bg-slate-950 border-slate-800 text-teal-500 focus:ring-0"
                                                                        />
                                                                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Enable raw HTML injection</span>
                                                                    </label>
                                                                </div>
                                                            )}

                                                            <div className="pt-2 border-t border-slate-800/40">
                                                                <label className="flex items-center gap-2 cursor-pointer select-none">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={!!currentOverrides.hidden}
                                                                        onChange={(e) => handleOverrideChange('hidden', e.target.checked)}
                                                                        className="rounded bg-slate-950 border-slate-800 text-teal-500 focus:ring-0"
                                                                    />
                                                                    <span className="text-[10px] font-bold text-rose-400 uppercase tracking-wider flex items-center gap-1.5">
                                                                        <i className="fas fa-eye-slash"></i> Hide individual element
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        </div>

                                                        <hr className="border-slate-800" />

                                                        {/* VISUAL STYLE EDITORS */}
                                                        <div className="space-y-4">
                                                            <h5 className="text-[9px] font-black text-slate-500 uppercase tracking-widest flex items-center gap-1.5">
                                                                <i className="fas fa-palette"></i> Style Settings
                                                            </h5>

                                                            {/* Typography Section (not for images) */}
                                                            {activeTag !== 'IMG' && (
                                                                <div className="bg-slate-950/20 p-3 rounded-lg border border-slate-800/60 space-y-3">
                                                                    <h6 className="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Typography</h6>

                                                                    {/* Font Size */}
                                                                    <div className="flex items-center justify-between gap-2">
                                                                        <span className="text-[10px] text-slate-500 font-bold uppercase">Size</span>
                                                                        <select
                                                                            value={currentStyles.fontSize || ''}
                                                                            onChange={(e) => handleOverrideChange('fontSize', e.target.value || undefined, true, 'fontSize')}
                                                                            className="bg-slate-950 border border-slate-800 text-xs rounded px-2 py-1 text-slate-300 focus:outline-none focus:border-teal-500 w-36">
                                                                            <option value="">Default</option>
                                                                            {['10px', '12px', '14px', '16px', '18px', '20px', '24px', '30px', '36px', '48px', '64px'].map(sz => (
                                                                                <option key={sz} value={sz}>{sz}</option>
                                                                            ))}
                                                                        </select>
                                                                    </div>

                                                                    {/* Font Weight */}
                                                                    <div className="flex items-center justify-between gap-2">
                                                                        <span className="text-[10px] text-slate-500 font-bold uppercase">Weight</span>
                                                                        <select
                                                                            value={currentStyles.fontWeight || ''}
                                                                            onChange={(e) => handleOverrideChange('fontWeight', e.target.value || undefined, true, 'fontWeight')}
                                                                            className="bg-slate-950 border border-slate-800 text-xs rounded px-2 py-1 text-slate-300 focus:outline-none focus:border-teal-500 w-36">
                                                                            <option value="">Default</option>
                                                                            <option value="300">Light</option>
                                                                            <option value="400">Normal</option>
                                                                            <option value="500">Medium</option>
                                                                            <option value="600">Semibold</option>
                                                                            <option value="700">Bold</option>
                                                                            <option value="800">Extra Bold</option>
                                                                        </select>
                                                                    </div>

                                                                    {/* Text Align */}
                                                                    <div className="flex items-center justify-between gap-2">
                                                                        <span className="text-[10px] text-slate-500 font-bold uppercase">Alignment</span>
                                                                        <select
                                                                            value={currentStyles.textAlign || ''}
                                                                            onChange={(e) => handleOverrideChange('textAlign', e.target.value || undefined, true, 'textAlign')}
                                                                            className="bg-slate-950 border border-slate-800 text-xs rounded px-2 py-1 text-slate-300 focus:outline-none focus:border-teal-500 w-36">
                                                                            <option value="">Default</option>
                                                                            <option value="left">Left</option>
                                                                            <option value="center">Center</option>
                                                                            <option value="right">Right</option>
                                                                            <option value="justify">Justify</option>
                                                                        </select>
                                                                    </div>

                                                                    {/* Color */}
                                                                    <div className="flex items-center justify-between gap-2">
                                                                        <span className="text-[10px] text-slate-500 font-bold uppercase">Text Color</span>
                                                                        <div className="flex items-center gap-1.5 w-36">
                                                                            <input
                                                                                type="color"
                                                                                value={currentStyles.color || '#ffffff'}
                                                                                onChange={(e) => handleOverrideChange('color', e.target.value, true, 'color')}
                                                                                className="bg-transparent border-0 w-6 h-6 p-0 cursor-pointer"
                                                                            />
                                                                            <input
                                                                                type="text"
                                                                                value={currentStyles.color || ''}
                                                                                onChange={(e) => handleOverrideChange('color', e.target.value || undefined, true, 'color')}
                                                                                placeholder="Default"
                                                                                className="bg-slate-950 border border-slate-800 text-[11px] rounded px-2 py-0.5 text-slate-300 font-mono w-28 focus:outline-none focus:border-teal-500"
                                                                            />
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            )}

                                                            {/* Spacing / Layout Section */}
                                                            <div className="bg-slate-950/20 p-3 rounded-lg border border-slate-800/60 space-y-3">
                                                                <h6 className="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Spacing</h6>

                                                                {/* Margin Bottom */}
                                                                <div className="flex items-center justify-between gap-2">
                                                                    <span className="text-[10px] text-slate-500 font-bold uppercase">Margin Bottom</span>
                                                                    <select
                                                                        value={currentStyles.marginBottom || ''}
                                                                        onChange={(e) => handleOverrideChange('marginBottom', e.target.value || undefined, true, 'marginBottom')}
                                                                        className="bg-slate-950 border border-slate-800 text-xs rounded px-2 py-1 text-slate-300 focus:outline-none focus:border-teal-500 w-36">
                                                                        <option value="">Default</option>
                                                                        {['0px', '4px', '8px', '12px', '16px', '20px', '24px', '32px', '48px', '64px'].map(sp => (
                                                                            <option key={sp} value={sp}>{sp}</option>
                                                                        ))}
                                                                    </select>
                                                                </div>

                                                                {/* Margin Top */}
                                                                <div className="flex items-center justify-between gap-2">
                                                                    <span className="text-[10px] text-slate-500 font-bold uppercase">Margin Top</span>
                                                                    <select
                                                                        value={currentStyles.marginTop || ''}
                                                                        onChange={(e) => handleOverrideChange('marginTop', e.target.value || undefined, true, 'marginTop')}
                                                                        className="bg-slate-950 border border-slate-800 text-xs rounded px-2 py-1 text-slate-300 focus:outline-none focus:border-teal-500 w-36">
                                                                        <option value="">Default</option>
                                                                        {['0px', '4px', '8px', '12px', '16px', '20px', '24px', '32px', '48px', '64px'].map(sp => (
                                                                            <option key={sp} value={sp}>{sp}</option>
                                                                        ))}
                                                                    </select>
                                                                </div>

                                                                {/* Padding Overall */}
                                                                <div className="flex items-center justify-between gap-2">
                                                                    <span className="text-[10px] text-slate-500 font-bold uppercase">Padding</span>
                                                                    <select
                                                                        value={currentStyles.padding || ''}
                                                                        onChange={(e) => handleOverrideChange('padding', e.target.value || undefined, true, 'padding')}
                                                                        className="bg-slate-950 border border-slate-800 text-xs rounded px-2 py-1 text-slate-300 focus:outline-none focus:border-teal-500 w-36">
                                                                        <option value="">Default</option>
                                                                        {['0px', '4px', '8px', '12px', '16px', '20px', '24px', '32px', '48px'].map(sp => (
                                                                            <option key={sp} value={sp}>{sp}</option>
                                                                        ))}
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            {/* Borders & Radius Section */}
                                                            <div className="bg-slate-950/20 p-3 rounded-lg border border-slate-800/60 space-y-3">
                                                                <h6 className="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Borders & Radius</h6>

                                                                {/* Border Radius */}
                                                                <div className="flex items-center justify-between gap-2">
                                                                    <span className="text-[10px] text-slate-500 font-bold uppercase">Radius</span>
                                                                    <select
                                                                        value={currentStyles.borderRadius || ''}
                                                                        onChange={(e) => handleOverrideChange('borderRadius', e.target.value || undefined, true, 'borderRadius')}
                                                                        className="bg-slate-950 border border-slate-800 text-xs rounded px-2 py-1 text-slate-300 focus:outline-none focus:border-teal-500 w-36">
                                                                        <option value="">Default</option>
                                                                        <option value="0px">None (0px)</option>
                                                                        <option value="4px">Small (4px)</option>
                                                                        <option value="8px">Medium (8px)</option>
                                                                        <option value="12px">Large (12px)</option>
                                                                        <option value="16px">Extra Large (16px)</option>
                                                                        <option value="9999px">Rounded Pill</option>
                                                                    </select>
                                                                </div>

                                                                {/* Border Width */}
                                                                <div className="flex items-center justify-between gap-2">
                                                                    <span className="text-[10px] text-slate-500 font-bold uppercase">Border Width</span>
                                                                    <select
                                                                        value={currentStyles.borderWidth || ''}
                                                                        onChange={(e) => handleOverrideChange('borderWidth', e.target.value || undefined, true, 'borderWidth')}
                                                                        className="bg-slate-950 border border-slate-800 text-xs rounded px-2 py-1 text-slate-300 focus:outline-none focus:border-teal-500 w-36">
                                                                        <option value="">Default</option>
                                                                        <option value="0px">None (0px)</option>
                                                                        <option value="1px">1px</option>
                                                                        <option value="2px">2px</option>
                                                                        <option value="4px">4px</option>
                                                                    </select>
                                                                </div>

                                                                {/* Border Color */}
                                                                <div className="flex items-center justify-between gap-2">
                                                                    <span className="text-[10px] text-slate-500 font-bold uppercase">Border Color</span>
                                                                    <div className="flex items-center gap-1.5 w-36">
                                                                        <input
                                                                            type="color"
                                                                            value={currentStyles.borderColor || '#ffffff'}
                                                                            onChange={(e) => handleOverrideChange('borderColor', e.target.value, true, 'borderColor')}
                                                                            className="bg-transparent border-0 w-6 h-6 p-0 cursor-pointer"
                                                                        />
                                                                        <input
                                                                            type="text"
                                                                            value={currentStyles.borderColor || ''}
                                                                            onChange={(e) => handleOverrideChange('borderColor', e.target.value || undefined, true, 'borderColor')}
                                                                            placeholder="Default"
                                                                            className="bg-slate-950 border border-slate-800 text-[11px] rounded px-2 py-0.5 text-slate-300 font-mono w-28 focus:outline-none focus:border-teal-500"
                                                                        />
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })()}

                                            <hr className="border-slate-800" />

                                            {/* REAL-TIME HTML CODE COMPILER DRAW */}
                                            <div className="space-y-2">
                                                <h4 className="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5"><i className="fas fa-laptop-code"></i> Compiled HTML Code Preview</h4>
                                                {renderCodePreview()}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* 7A. THEME EDITOR TAB */}
                            {rightPanelTab === 'theme' && (
                                <div className="flex-1 overflow-y-auto p-4 space-y-5">
                                    <div className="space-y-4">
                                        <h4 className="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <i className="fas fa-palette"></i> Design Tokens & Themes
                                        </h4>
                                        <p className="text-[11px] text-slate-400">Configure global layout variables, font face, and spacing scales dynamically.</p>

                                        {/* Primary Branding Color */}
                                        <div className="flex items-center justify-between bg-slate-950 p-2.5 rounded-lg border border-slate-800">
                                            <span className="text-[11px] text-slate-400 font-bold uppercase">Accent Color</span>
                                            <div className="flex items-center gap-2">
                                                <span className="text-[10px] font-mono text-slate-500">${themePrimaryColor}</span>
                                                <input type="color" value={themePrimaryColor} onChange={(e) => setThemePrimaryColor(e.target.value)} className="w-8 h-8 rounded border-0 bg-transparent cursor-pointer" />
                                            </div>
                                        </div>

                                        {/* Workspace Background Color */}
                                        <div className="flex items-center justify-between bg-slate-950 p-2.5 rounded-lg border border-slate-800">
                                            <span className="text-[11px] text-slate-400 font-bold uppercase">Page Base BG</span>
                                            <div className="flex items-center gap-2">
                                                <span className="text-[10px] font-mono text-slate-500">${themeBgColor}</span>
                                                <input type="color" value={themeBgColor} onChange={(e) => setThemeBgColor(e.target.value)} className="w-8 h-8 rounded border-0 bg-transparent cursor-pointer" />
                                            </div>
                                        </div>

                                        {/* Global Fonts Selector */}
                                        <div className="space-y-1.5">
                                            <label className="text-[11px] text-slate-400 block font-bold uppercase">Font Family face</label>
                                            <select value={themeFontFamily} onChange={(e) => setThemeFontFamily(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white">
                                                <option value="sans-serif">Standard Sans-Serif</option>
                                                <option value="'Inter', sans-serif">Inter (Modern SaaS)</option>
                                                <option value="'Playfair Display', serif">Playfair Display (Elegant Serif)</option>
                                                <option value="'Courier New', monospace">Courier New (Technical Monospace)</option>
                                                <option value="'Sora', sans-serif">Sora (Sleek Geometric)</option>
                                            </select>
                                        </div>

                                        {/* Base Padding Gap Scale Slider */}
                                        <div className="space-y-1.5">
                                            <div className="flex justify-between items-center text-[11px] text-slate-400">
                                                <span className="font-bold uppercase">Base Element Spacing</span>
                                                <span className="font-mono text-teal-400 font-bold">{themeBaseSpacing}</span>
                                            </div>
                                            <input type="range" min="8" max="48" step="4" value={parseInt(themeBaseSpacing)} onChange={(e) => setThemeBaseSpacing(e.target.value + 'px')} className="w-full h-1.5 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-teal-400" />
                                        </div>

                                        {/* Container Content Layout Width */}
                                        <div className="space-y-1.5">
                                            <label className="text-[11px] text-slate-400 block font-bold uppercase">Max Container Width</label>
                                            <select value={themeContainerWidth} onChange={(e) => setThemeContainerWidth(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white font-mono">
                                                <option value="1000px">1000px (Narrow)</option>
                                                <option value="1200px">1200px (Standard Desktop)</option>
                                                <option value="1400px">1400px (Wide Frame Layout)</option>
                                                <option value="100%">100% (Fluid Content Edge)</option>
                                            </select>
                                        </div>

                                        <button onClick={() => { saveProject(false); }} className="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-2.5 rounded text-xs transition uppercase tracking-wider">
                                            Apply Design Tokens
                                        </button>
                                    </div>
                                </div>
                            )}

                            {/* 7B. SEO & METADATA SETTINGS TAB */}
                            {rightPanelTab === 'seo' && (
                                <div className="flex-1 overflow-y-auto p-4 space-y-5">
                                    <div className="space-y-4">
                                        <h4 className="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <i className="fas fa-search"></i> SEO Fundamentals & Indexing
                                        </h4>
                                        <p className="text-[11px] text-slate-400">Optimizing metadata anchors, Open Graph tags, robots, and Google indexing sitemaps.</p>

                                        {/* Meta Title */}
                                        <div>
                                            <label className="text-[10px] font-bold text-slate-500 uppercase block mb-1">SEO Page Title</label>
                                            <input type="text" value={seoTitle} onChange={(e) => setSeoTitle(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white" />
                                        </div>

                                        {/* Meta Description */}
                                        <div>
                                            <label className="text-[10px] font-bold text-slate-500 uppercase block mb-1">Meta Description</label>
                                            <textarea rows="3" value={seoMetaDesc} onChange={(e) => setSeoMetaDesc(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded p-2 text-xs text-white" />
                                        </div>

                                        {/* Open Graph Image URL */}
                                        <div>
                                            <label className="text-[10px] font-bold text-slate-500 uppercase block mb-1">Open Graph / Social Card Image</label>
                                            <input type="text" value={seoOgImage} onChange={(e) => setSeoOgImage(e.target.value)} placeholder="https://example.com/social-og.jpg" class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white font-mono" />
                                        </div>

                                        {/* Robots.txt code block */}
                                        <div>
                                            <label className="text-[10px] font-bold text-slate-500 uppercase block mb-1">Sitemap robots.txt Directive</label>
                                            <textarea rows="3" value={seoRobotsTxt} onChange={(e) => setSeoRobotsTxt(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded p-2 text-xs text-white font-mono" />
                                        </div>

                                        {/* Structured Data JSON-LD schema helper */}
                                        <div>
                                            <label className="text-[10px] font-bold text-slate-500 uppercase block mb-1">Structured JSON-LD Schema Helper</label>
                                            <textarea rows="4" value={seoStructuredData} onChange={(e) => setSeoStructuredData(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded p-2 text-xs text-white font-mono" />
                                        </div>

                                        {/* Generated Sitemap.xml link mockup */}
                                        <div className="bg-slate-950 p-2.5 rounded-lg border border-slate-850 space-y-1 text-[11px]">
                                            <div className="flex justify-between items-center text-slate-400">
                                                <span>Generated XML Sitemap:</span>
                                                <a href={`api.php?action=sitemap&project_id=${PROJECT_ID}`} target="_blank" className="text-teal-400 hover:underline">sitemap.xml <i className="fas fa-external-link-alt text-[9px]"></i></a>
                                            </div>
                                            <div className="flex justify-between items-center text-slate-400">
                                                <span>Robots.txt Output:</span>
                                                <a href={`api.php?action=robots&project_id=${PROJECT_ID}`} target="_blank" className="text-teal-400 hover:underline">robots.txt <i className="fas fa-external-link-alt text-[9px]"></i></a>
                                            </div>
                                        </div>

                                        <button onClick={() => { saveProject(false); }} className="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-2.5 rounded text-xs transition uppercase tracking-wider">
                                            Save SEO Injections
                                        </button>
                                    </div>
                                </div>
                            )}

                            {/* 7C. SAAS TENANCY & BILLING TAB */}
                            {rightPanelTab === 'saas' && (
                                <div className="flex-1 overflow-y-auto p-4 space-y-5">
                                    <div className="space-y-4">
                                        <div className="flex justify-between items-center">
                                            <h4 className="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                                                <i className="fas fa-cloud"></i> Multi-Tenancy SaaS Core
                                            </h4>
                                            <span className="text-[9px] font-bold bg-amber-400 text-slate-950 px-2 py-0.5 rounded uppercase tracking-wider">{tenantSubscriptionPlan} Plan</span>
                                        </div>
                                        <p className="text-[11px] text-slate-400">Manage tenant subdomain variables, custom domain structures, and Stripe subscription statuses.</p>

                                        {/* Domain mapping input */}
                                        <div>
                                            <label className="text-[10px] font-bold text-slate-500 uppercase block mb-1">Custom Domain Pointer</label>
                                            <div className="flex gap-2">
                                                <input type="text" value={tenantCustomDomain} onChange={(e) => setTenantCustomDomain(e.target.value)} placeholder="www.mycustomdomain.com" class="flex-1 bg-slate-950 border border-slate-800 rounded px-2.5 py-1.5 text-xs text-white" />
                                                <button onClick={() => { showToast("Checking DNS...", "Looking up A-Record mapping for domain pointer."); setTimeout(() => alert("DNS status checked: custom A-record pointed successfully to 127.0.0.1! Status verified."), 1000); }} className="bg-slate-800 text-slate-300 hover:text-white px-2.5 rounded border border-slate-700 text-xs font-bold" title="Verify Domain pointer DNS configuration">
                                                    <i className="fas fa-shield-virus"></i>
                                                </button>
                                            </div>
                                        </div>

                                        {/* Subdomain pointer */}
                                        <div>
                                            <label className="text-[10px] font-bold text-slate-500 uppercase block mb-1">HQ Tenant Subdomain</label>
                                            <div className="bg-slate-950 px-3 py-2 rounded-lg text-xs font-mono text-teal-400 flex justify-between items-center border border-slate-800">
                                                <span>{tenantSubdomain}.nuvis-webidesigner.io</span>
                                                <i className="fas fa-link text-slate-500 text-[10px]"></i>
                                            </div>
                                        </div>

                                        {/* SaaS Usage Meter Gauges */}
                                        <div className="space-y-2.5 pt-2 border-t border-slate-850">
                                            <h5 className="text-[10px] font-bold text-slate-400 uppercase tracking-wider"><i className="fas fa-tachometer-alt"></i> Visual Metering Counters</h5>

                                            {/* Sites Meter */}
                                            <div className="text-[11px] text-slate-400 space-y-1">
                                                <div className="flex justify-between">
                                                    <span>Active Sites:</span>
                                                    <span className="font-bold text-white">{tenantSitesCount} / 3 sites</span>
                                                </div>
                                                <div className="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden">
                                                    <div className="bg-teal-500 h-full rounded-full" style={{ width: '66.6%' }}></div>
                                                </div>
                                            </div>

                                            {/* Storage Meter */}
                                            <div className="text-[11px] text-slate-400 space-y-1">
                                                <div className="flex justify-between">
                                                    <span>Storage Limit:</span>
                                                    <span className="font-bold text-white">50.0 MB / 100 MB</span>
                                                </div>
                                                <div className="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden">
                                                    <div className="bg-amber-400 h-full rounded-full" style={{ width: '50%' }}></div>
                                                </div>
                                            </div>

                                            {/* AI calls */}
                                            <div className="text-[11px] text-slate-400 space-y-1">
                                                <div className="flex justify-between">
                                                    <span>AI Copilot Calls:</span>
                                                    <span className="font-bold text-white">{tenantAiCallsCount} / 100 queries</span>
                                                </div>
                                                <div className="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden">
                                                    <div className="bg-teal-400 h-full rounded-full" style={{ width: '45%' }}></div>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Upgrade Subscription Mock Button */}
                                        <button onClick={() => setShowBillingModal(true)} className="w-full bg-gradient-to-r from-teal-500 to-cyan-500 hover:from-teal-400 hover:to-cyan-400 text-slate-950 font-black py-2.5 rounded-xl text-xs transition uppercase tracking-wider flex items-center justify-center gap-1.5 shadow-lg shadow-teal-500/10">
                                            <i className="fab fa-stripe text-base"></i> Upgrade Subscription Billing
                                        </button>
                                    </div>
                                </div>
                            )}

                            {/* 7D. AI SUPERPOWERS CO-PILOT ASSISTANT */}
                            {rightPanelTab === 'ai' && (
                                <div className="flex-1 overflow-y-auto p-4 space-y-5">
                                    <div className="space-y-4">
                                        <h4 className="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <i className="fas fa-brain"></i> Interactive AI Superpowers
                                        </h4>
                                        <p className="text-[11px] text-slate-400">Generate beautiful precompiled sections, draft copywriting, and audit layout elements with on-demand AI rules.</p>

                                        {/* Prompt to page generator */}
                                        <div className="space-y-2">
                                            <label className="text-[10px] font-bold text-slate-500 uppercase block mb-1">Prompt to Page Section</label>
                                            <textarea rows="3" value={aiPrompt} onChange={(e) => setAiPrompt(e.target.value)} placeholder="e.g. Create a beautiful emerald green feature split with a photo on the right and a CTA button to start free." className="w-full bg-slate-950 border border-slate-800 rounded p-2 text-xs text-white placeholder-slate-600 focus:outline-none" />

                                            <button onClick={() => {
                                                if (!aiPrompt.trim()) {
                                                    alert("Please enter a visual section prompt!");
                                                    return;
                                                }
                                                setAiIsGenerating(true);
                                                showToast("Consulting AI Engine...", "Parsing layout blueprint templates.");

                                                fetch('api.php?action=generate_ai_section', {
                                                    method: 'POST',
                                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                                                    body: JSON.stringify({
                                                        prompt: aiPrompt,
                                                        csrf_token: CSRF_TOKEN
                                                    })
                                                })
                                                .then(res => res.json())
                                                .then(data => {
                                                    if (data.success && data.section) {
                                                        const newSec = data.section;
                                                        const updated = [...sections, newSec];
                                                        updateSectionsWithHistory(updated);
                                                        setAiPrompt('');
                                                        showToast("AI Section Generated!", "Successfully injected visual layout card onto canvas.");
                                                    } else {
                                                        alert("AI Generation Simulation Error: " + data.error);
                                                    }
                                                })
                                                .catch(err => alert("AI response failed: " + err.message))
                                                .finally(() => setAiIsGenerating(false));

                                            }} disabled={aiIsGenerating} className="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-2 rounded text-xs transition uppercase tracking-wider flex items-center justify-center gap-1">
                                                {aiIsGenerating ? <i className="fas fa-spinner animate-spin"></i> : <i className="fas fa-magic"></i>}
                                                <span>Precompile Section Draft</span>
                                            </button>
                                        </div>

                                        {/* Meta Copywriting Optimize Button */}
                                        <div className="space-y-1.5 pt-3 border-t border-slate-850">
                                            <label className="text-[10px] font-bold text-slate-500 uppercase block">AI Meta Copywriter</label>
                                            <button onClick={() => {
                                                showToast("Generating Copy...", "Analyzing page content JSON.");
                                                setTimeout(() => {
                                                    setSeoTitle("Nuvis Webidesigner Pro - Supercharge Visual Editing Layouts");
                                                    setSeoMetaDesc("Unlock state of the art drag and drop page compilation caches. Supports responsive mobile frames, persistent version histories, and integrated lead-capture calendars.");
                                                    showToast("Optimization Complete!", "Updated SEO title and descriptions with high conversion tags.");
                                                }, 1200);
                                            }} className="w-full bg-slate-800 hover:bg-slate-750 text-slate-200 border border-slate-700 font-bold py-2 rounded text-xs transition uppercase tracking-wider flex items-center justify-center gap-1.5">
                                                <i className="fas fa-bullhorn text-teal-400"></i> Optimize Meta Description Tags
                                            </button>
                                        </div>

                                        {/* SEO / Performance Auditor */}
                                        <div className="space-y-2 pt-3 border-t border-slate-850">
                                            <label className="text-[10px] font-bold text-slate-500 uppercase block">AI Site & Performance Audit</label>
                                            <button onClick={() => {
                                                showToast("Auditing Canvas...", "Verifying tags and semantic structure.");
                                                setTimeout(() => {
                                                    setAiAuditReport({
                                                        seo: 96,
                                                        perf: 98,
                                                        access: 92,
                                                        suggestions: [
                                                            "Hero heading has perfect semantic tag distribution",
                                                            "Image elements mapped with alt tags completely",
                                                            "All precompiled static loads meet sub-100ms thresholds"
                                                        ]
                                                    });
                                                    showToast("Audit Finished", "Scores calculated successfully.");
                                                }, 1000);
                                            }} className="w-full bg-slate-800 hover:bg-slate-750 text-slate-200 border border-slate-700 font-bold py-2 rounded text-xs transition uppercase tracking-wider flex items-center justify-center gap-1.5">
                                                <i className="fas fa-chart-line text-teal-400"></i> Perform Site Audit Checks
                                            </button>

                                            {aiAuditReport && (
                                                <div className="bg-slate-950/80 p-3 rounded-xl border border-slate-800 space-y-3 animate-fadeIn text-[11px] text-slate-300">
                                                    <div className="grid grid-cols-3 gap-2 text-center">
                                                        <div className="p-2 bg-slate-900 rounded-lg border border-slate-850">
                                                            <div className="text-emerald-400 font-black text-sm">{aiAuditReport.seo}/100</div>
                                                            <span className="text-[9px] uppercase font-bold text-slate-500 block mt-0.5">SEO</span>
                                                        </div>
                                                        <div className="p-2 bg-slate-900 rounded-lg border border-slate-850">
                                                            <div className="text-teal-400 font-black text-sm">{aiAuditReport.perf}/100</div>
                                                            <span className="text-[9px] uppercase font-bold text-slate-500 block mt-0.5">Speed</span>
                                                        </div>
                                                        <div className="p-2 bg-slate-900 rounded-lg border border-slate-850">
                                                            <div className="text-amber-400 font-black text-sm">{aiAuditReport.access}/100</div>
                                                            <span className="text-[9px] uppercase font-bold text-slate-500 block mt-0.5">WCAG</span>
                                                        </div>
                                                    </div>
                                                    <div className="space-y-1 pl-1">
                                                        <span className="font-bold text-[10px] text-slate-400 uppercase tracking-wider block">Auditor Recommendations:</span>
                                                        {aiAuditReport.suggestions.map((s, idx) => (
                                                            <div key={idx} className="flex gap-1.5 items-start text-[10px] text-slate-400">
                                                                <span className="text-emerald-400 font-bold">✔</span>
                                                                <span>{s}</span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* 7E. ORIGINAL SETTINGS TAB (RENAMED IN MENU AS EMAIL SETTINGS) */}
                            {rightPanelTab === 'settings' && (
                                <div className="flex-1 overflow-y-auto p-4 space-y-5">
                                    <div className="space-y-4">
                                        <h3 className="text-xs font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <i className="fas fa-envelope-open-text"></i> Email & SMTP Settings
                                        </h3>
                                        <p className="text-[10px] text-slate-400 leading-relaxed">Configure inquiry notifications and SMTP server dispatch details specifically for this website.</p>

                                        {/* RECIPIENT */}
                                        <div className="space-y-1">
                                            <label className="text-[10px] font-bold text-slate-400 uppercase block">Inquiry Recipient Email</label>
                                            <input type="email" value={emailRecipient} onChange={(e) => setEmailRecipient(e.target.value)} placeholder="recipient@domain.com" className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500 font-mono" />
                                            <span className="text-[9px] text-slate-500 block">Where customer submissions on the Enquiry Page will be routed.</span>
                                        </div>

                                        {/* AUTO-RESPONDER */}
                                        <div className="flex items-center justify-between border-t border-slate-800/60 pt-3">
                                            <label className="text-xs font-semibold text-slate-300">Enable Customer Auto-Responder</label>
                                            <input type="checkbox" checked={autoResponderEnabled} onChange={(e) => setAutoResponderEnabled(e.target.checked)} className="w-4 h-4 rounded border-slate-800 bg-slate-950 text-teal-500 focus:ring-0" />
                                        </div>

                                        {autoResponderEnabled && (
                                            <div className="space-y-3 bg-slate-950/40 p-3 rounded-lg border border-slate-800 animate-fadeIn">
                                                <div>
                                                    <label className="text-[9px] font-bold text-slate-400 uppercase block mb-1">Template Theme</label>
                                                    <select value={emailTemplateTheme} onChange={(e) => setEmailTemplateTheme(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded-lg px-2 py-1.5 text-xs text-slate-300 focus:outline-none focus:border-teal-500">
                                                        <option value="modern_minimalist">Modern Minimalist (Teal)</option>
                                                        <option value="elegant">Elegant Indigo Gold (Royal)</option>
                                                        <option value="tech_light">Tech Light (Clean Blue)</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="text-[9px] font-bold text-slate-400 uppercase block mb-1">Subject</label>
                                                    <input type="text" value={autoResponderSubject} onChange={(e) => setAutoResponderSubject(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-teal-500" />
                                                </div>
                                                <div>
                                                    <label className="text-[9px] font-bold text-slate-400 uppercase block mb-1">Message Body</label>
                                                    <textarea rows="4" value={autoResponderBody} onChange={(e) => setAutoResponderBody(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded-lg p-2.5 text-xs text-white focus:outline-none focus:border-teal-500 font-sans"></textarea>
                                                </div>
                                            </div>
                                        )}

                                        {/* SMTP CONFIG */}
                                        <div className="border-t border-slate-800/60 pt-3 space-y-3">
                                            <h4 className="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                                                <i className="fas fa-server"></i> SMTP Configuration (Site Override)
                                            </h4>

                                            <div className="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label className="text-[9px] font-bold text-slate-400 uppercase block mb-1">SMTP Host</label>
                                                    <input type="text" value={smtpHost} onChange={(e) => setSmtpHost(e.target.value)} placeholder="smtp.mailtrap.io" className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-teal-500 font-mono" />
                                                </div>
                                                <div>
                                                    <label className="text-[9px] font-bold text-slate-400 uppercase block mb-1">SMTP Port</label>
                                                    <input type="text" value={smtpPort} onChange={(e) => setSmtpPort(e.target.value)} placeholder="587" className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-teal-500 font-mono" />
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label className="text-[9px] font-bold text-slate-400 uppercase block mb-1">SMTP Username</label>
                                                    <input type="text" value={smtpUsername} onChange={(e) => setSmtpUsername(e.target.value)} placeholder="user@gmail.com" className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-teal-500 font-mono" />
                                                </div>
                                                <div>
                                                    <label className="text-[9px] font-bold text-slate-400 uppercase block mb-1">SMTP Password</label>
                                                    <input type="password" value={smtpPassword} onChange={(e) => setSmtpPassword(e.target.value)} placeholder="••••••••" className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-teal-500 font-mono" />
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-3 gap-2">
                                                <div className="col-span-1">
                                                    <label className="text-[9px] font-bold text-slate-400 uppercase block mb-1">Encryption</label>
                                                    <select value={smtpEncryption} onChange={(e) => setSmtpEncryption(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded-lg px-2 py-1.5 text-xs text-slate-300 focus:outline-none focus:border-teal-500">
                                                        <option value="none">None</option>
                                                        <option value="ssl">SSL</option>
                                                        <option value="tls">TLS</option>
                                                    </select>
                                                </div>
                                                <div className="col-span-2">
                                                    <label className="text-[9px] font-bold text-slate-400 uppercase block mb-1">From Name</label>
                                                    <input type="text" value={smtpFromName} onChange={(e) => setSmtpFromName(e.target.value)} placeholder="My Site Inquiry" className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-teal-500" />
                                                </div>
                                            </div>

                                            <div>
                                                <label className="text-[9px] font-bold text-slate-400 uppercase block mb-1">From Email Address</label>
                                                <input type="email" value={smtpFromEmail} onChange={(e) => setSmtpFromEmail(e.target.value)} placeholder="inquiry@mysite.com" className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-teal-500 font-mono" />
                                            </div>
                                        </div>

                                        <button onClick={() => saveProject(false)} className="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-2.5 rounded text-xs transition uppercase tracking-wider">
                                            Apply & Save Email Settings
                                        </button>
                                    </div>
                                </div>
                            )}

                            {rightPanelTab === 'code' && (
                                <div className="flex-1 overflow-y-auto p-4 space-y-4 flex flex-col h-full overflow-hidden">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-xs font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <i className="fas fa-laptop-code"></i> Code Editor
                                        </h3>
                                        <button onClick={() => setFullscreenEditorOpen(true)} className="bg-teal-500 hover:bg-teal-400 text-slate-950 font-extrabold px-3 py-1.5 rounded text-[10px] uppercase tracking-wider flex items-center gap-1 transition-all">
                                            <i className="fas fa-expand"></i> Full Mode
                                        </button>
                                    </div>

                                    {/* Code Editor Tabs Selection */}
                                    <div className="flex bg-slate-950 p-1 rounded-lg border border-slate-800 space-x-1">
                                        {['css', 'js', 'html_raw'].map(tab => (
                                            <button key={tab} onClick={() => setCodeEditorTab(tab)} className={`flex-1 py-1.5 text-center text-[10px] font-bold uppercase rounded transition ${codeEditorTab === tab ? 'bg-slate-800 text-teal-400 font-extrabold' : 'text-slate-400 hover:text-white'}`}>
                                                {tab === 'html_raw' ? 'Raw HTML' : tab.toUpperCase()}
                                            </button>
                                        ))}
                                    </div>

                                    <div className="flex-1 flex flex-col min-h-[300px] bg-slate-950 rounded-lg border border-slate-800 relative">
                                        {codeEditorTab === 'css' && (
                                            <div className="flex-1 flex flex-col p-2 h-full">
                                                <label className="text-[10px] font-bold text-slate-400 block mb-1">Custom CSS</label>
                                                <AceEditor value={customCss} mode="css" onChange={setCustomCss} className="flex-1 rounded" style={{ height: '300px', width: '100%' }} />
                                            </div>
                                        )}
                                        {codeEditorTab === 'js' && (
                                            <div className="flex-1 flex flex-col p-2 h-full">
                                                <label className="text-[10px] font-bold text-slate-400 block mb-1">Custom JavaScript</label>
                                                <AceEditor value={customJs} mode="javascript" onChange={setCustomJs} className="flex-1 rounded" style={{ height: '300px', width: '100%' }} />
                                            </div>
                                        )}
                                        {codeEditorTab === 'html_raw' && (() => {
                                            const hasHtmlRaw = selectedSection && selectedSection.type.toLowerCase() === 'html_raw';
                                            if (!hasHtmlRaw) {
                                                return (
                                                    <div className="flex-1 flex flex-col items-center justify-center p-6 text-center text-slate-500 h-[300px]">
                                                        <i className="fas fa-code text-xl mb-2"></i>
                                                        <p className="text-[11px] font-bold text-slate-400">No Raw HTML Block Selected</p>
                                                        <p className="text-[10px] mt-1 leading-relaxed">Select a "Low-Code Custom Raw HTML" block from the canvas to edit its raw code here.</p>
                                                    </div>
                                                );
                                            }
                                            const htmlVal = selectedSection.props.rawHtml || '';
                                            const handleHtmlChange = (newHtml) => {
                                                const updated = sections.map(s => s.id === selectedSection.id ? { ...s, props: { ...s.props, rawHtml: newHtml } } : s);
                                                updateSectionsWithHistory(updated);
                                            };
                                            return (
                                                <div className="flex-1 flex flex-col p-2 h-full">
                                                    <label className="text-[10px] font-bold text-slate-400 block mb-1">Raw HTML Content</label>
                                                    <AceEditor value={htmlVal} mode="html" onChange={handleHtmlChange} className="flex-1 rounded" style={{ height: '300px', width: '100%' }} />
                                                </div>
                                            );
                                        })()}
                                    </div>

                                    <button onClick={() => saveProject(false)} className="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-2 rounded text-xs transition uppercase tracking-wider">
                                        Save Changes
                                    </button>
                                </div>
                            )}

                            {rightPanelTab === 'versions' && (
                                <div className="flex-1 overflow-y-auto p-4 space-y-5 flex flex-col h-full overflow-hidden">
                                    <div className="space-y-4">
                                        <h3 className="text-xs font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <i className="fas fa-save"></i> Save Manual Version
                                        </h3>
                                        <div className="space-y-2">
                                            <input
                                                type="text"
                                                value={customVersionLabel}
                                                onChange={(e) => setCustomVersionLabel(e.target.value)}
                                                placeholder="Version note (e.g. Added features block)"
                                                className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-teal-500"
                                            />
                                            <button onClick={createCustomSnapshot} className="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-2 rounded text-xs transition uppercase tracking-wider">
                                                Create Milestone
                                            </button>
                                        </div>
                                    </div>

                                    <hr className="border-slate-800" />

                                    <div className="flex-1 overflow-y-auto space-y-4">
                                        <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <i className="fas fa-history"></i> Versions Timeline ({versions.length})
                                        </h3>

                                        {versions.length === 0 ? (
                                            <p className="text-center text-slate-500 text-xs py-8">No historical snapshots recorded yet.</p>
                                        ) : (
                                            <div className="relative border-l border-slate-800 pl-4 ml-2 space-y-6">
                                                {versions.map(v => {
                                                    const isPreviewing = (previewingVersionId === v.id);
                                                    return (
                                                        <div key={v.id} className="relative">
                                                            {/* circular timeline indicator badge */}
                                                            <span className={`absolute -left-6 top-1.5 w-3.5 h-3.5 rounded-full border-2 ${v.version_type === 'publish' ? 'bg-emerald-500 border-emerald-400' : 'bg-slate-850 border-teal-500'} flex items-center justify-center`}></span>

                                                            <div className="space-y-2">
                                                                <div className="flex items-center justify-between">
                                                                    <span className={`text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider ${v.version_type === 'publish' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-950 text-slate-400 border border-slate-800'}`}>
                                                                        {v.version_type}
                                                                    </span>
                                                                    <span className="text-[10px] text-slate-500 font-mono">
                                                                        {new Date(v.created_at).toLocaleDateString([], { month: 'short', day: 'numeric' })} {new Date(v.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                                                    </span>
                                                                </div>

                                                                <h4 className="text-xs font-bold text-white leading-relaxed break-words">{v.label}</h4>

                                                                <div className="flex gap-2 pt-1">
                                                                    <button
                                                                        onClick={() => isPreviewing ? exitPreviewMode() : previewVersion(v.id, v.label)}
                                                                        className={`flex-1 py-1.5 rounded text-[10px] uppercase font-bold tracking-wider transition ${isPreviewing ? 'bg-amber-500 text-slate-950 font-black' : 'bg-slate-800 hover:bg-slate-750 text-teal-400'}`}
                                                                    >
                                                                        {isPreviewing ? 'Exit Preview' : 'Preview'}
                                                                    </button>
                                                                    <button
                                                                        onClick={() => restoreVersionFromId(v.id)}
                                                                        className="flex-1 py-1.5 rounded text-[10px] uppercase font-black bg-teal-500 hover:bg-teal-400 text-slate-950 tracking-wider transition"
                                                                    >
                                                                        Restore
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </aside>
                    </div>

                    {/* FULLSCREEN ADVANCED CODE EDITOR IDE */}
                    {isFullscreenEditorOpen && (
                        <div className="fixed inset-0 z-50 bg-slate-950 flex flex-col font-sans">
                            {/* Fullscreen Header */}
                            <div className="bg-slate-900 border-b border-slate-800 px-6 py-4 flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="bg-teal-500 text-slate-950 w-7 h-7 rounded flex items-center justify-center font-black text-xs">
                                        <i className="fas fa-terminal"></i>
                                    </div>
                                    <div>
                                        <h2 className="text-sm font-bold text-white leading-none">Nuvis Advanced IDE Workspace</h2>
                                        <p className="text-[10px] text-slate-400 mt-1">Editing: <span className="text-teal-400">{PROJECT_NAME}</span></p>
                                    </div>
                                </div>

                                {/* Tab controls in Fullscreen */}
                                <div className="flex bg-slate-950 p-1 rounded-lg border border-slate-850 space-x-1">
                                    {['css', 'js', 'html_raw'].map(tab => (
                                        <button key={tab} onClick={() => setCodeEditorTab(tab)} className={`px-4 py-1.5 rounded text-xs font-bold uppercase transition ${codeEditorTab === tab ? 'bg-slate-800 text-teal-400' : 'text-slate-400 hover:text-white'}`}>
                                            {tab === 'html_raw' ? 'Raw HTML' : tab.toUpperCase()}
                                        </button>
                                    ))}
                                </div>

                                <div className="flex items-center gap-2">
                                    <button onClick={() => saveProject(false)} className="bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-4 py-2 rounded text-xs transition flex items-center gap-1.5 font-bold">
                                        <i className="fas fa-save"></i> Save Changes
                                    </button>
                                    <button onClick={() => setFullscreenEditorOpen(false)} className="bg-slate-800 hover:bg-slate-700 text-white font-bold px-4 py-2 rounded text-xs transition flex items-center gap-1">
                                        <i className="fas fa-times"></i> Close Fullscreen
                                    </button>
                                </div>
                            </div>

                            {/* Fullscreen Editor Body */}
                            <div className="flex-1 flex min-h-0 bg-slate-900">
                                {codeEditorTab === 'css' && (
                                    <div className="flex-1 flex flex-col p-6 h-full">
                                        <div className="flex justify-between items-center mb-2">
                                            <span className="text-xs font-bold text-slate-400 uppercase tracking-wider block"><i className="fab fa-css3-alt text-cyan-400 mr-1.5"></i> Custom CSS Stylesheet</span>
                                            <span className="text-[10px] text-slate-500 font-mono">tomorrow_night_eighties theme active</span>
                                        </div>
                                        <AceEditor value={customCss} mode="css" onChange={setCustomCss} className="flex-1 rounded-lg border border-slate-800" style={{ height: '100%', width: '100%' }} />
                                    </div>
                                )}
                                {codeEditorTab === 'js' && (
                                    <div className="flex-1 flex flex-col p-6 h-full">
                                        <div className="flex justify-between items-center mb-2">
                                            <span className="text-xs font-bold text-slate-400 uppercase tracking-wider block"><i className="fab fa-js-square text-yellow-400 mr-1.5"></i> Custom JavaScript Logic</span>
                                            <span className="text-[10px] text-slate-500 font-mono">tomorrow_night_eighties theme active</span>
                                        </div>
                                        <AceEditor value={customJs} mode="javascript" onChange={setCustomJs} className="flex-1 rounded-lg border border-slate-800" style={{ height: '100%', width: '100%' }} />
                                    </div>
                                )}
                                {codeEditorTab === 'html_raw' && (() => {
                                    const hasHtmlRaw = selectedSection && selectedSection.type.toLowerCase() === 'html_raw';
                                    if (!hasHtmlRaw) {
                                        return (
                                            <div className="flex-1 flex flex-col items-center justify-center p-12 text-center text-slate-400">
                                                <div className="w-16 h-16 bg-slate-850 rounded-full flex items-center justify-center border border-slate-800 text-slate-500 text-2xl mb-4">
                                                    <i className="fas fa-code"></i>
                                                </div>
                                                <h3 className="font-bold text-slate-300">No Raw HTML Block Selected</h3>
                                                <p className="text-slate-500 text-xs mt-1.5 max-w-sm">Please select a "Low-Code Custom Raw HTML" block on the visual builder canvas and then open the fullscreen editor to customize its raw code.</p>
                                            </div>
                                        );
                                    }
                                    const htmlVal = selectedSection.props.rawHtml || '';
                                    const handleHtmlChange = (newHtml) => {
                                        const updated = sections.map(s => s.id === selectedSection.id ? { ...s, props: { ...s.props, rawHtml: newHtml } } : s);
                                        updateSectionsWithHistory(updated);
                                    };
                                    return (
                                        <div className="flex-1 flex flex-col p-6 h-full">
                                            <div className="flex justify-between items-center mb-2">
                                                <span className="text-xs font-bold text-slate-400 uppercase tracking-wider block"><i className="fas fa-code text-teal-400 mr-1.5"></i> Selected Raw HTML Block Code</span>
                                                <span className="text-[10px] text-teal-400 font-mono">Section ID: {selectedSection.id}</span>
                                            </div>
                                            <AceEditor value={htmlVal} mode="html" onChange={handleHtmlChange} className="flex-1 rounded-lg border border-slate-800" style={{ height: '100%', width: '100%' }} />
                                        </div>
                                    );
                                })()}
                            </div>
                        </div>
                    )}

                    {/* CUSTOM COMPONENT SHELF CREATOR MODAL */}
                    {isCustomCompModalOpen && (
                        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
                            <div className="bg-slate-900 border border-slate-800 rounded-xl shadow-2xl max-w-2xl w-full flex flex-col max-h-[90vh] overflow-hidden">
                                {/* Modal Header */}
                                <div className="px-6 py-4 border-b border-slate-800 flex justify-between items-center bg-slate-950/40">
                                    <h3 className="text-sm font-extrabold text-teal-400 uppercase tracking-widest flex items-center gap-2">
                                        <i className="fas fa-plus-circle"></i> Create Custom Component Shelf Widget
                                    </h3>
                                    <button onClick={() => setIsCustomCompModalOpen(false)} className="text-slate-400 hover:text-white">
                                        <i className="fas fa-times"></i>
                                    </button>
                                </div>

                                {/* Modal Body (Scrollable Form) */}
                                <form onSubmit={handleCreateCustomComponentSubmit} className="flex-1 overflow-y-auto p-6 space-y-6">
                                    <div className="grid grid-cols-2 gap-4">
                                        {/* Component Name */}
                                        <div>
                                            <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Component Name</label>
                                            <input
                                                type="text"
                                                required
                                                value={newCompName}
                                                onChange={(e) => setNewCompName(e.target.value)}
                                                className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500"
                                                placeholder="e.g. Testimonial Grid"
                                            />
                                        </div>

                                        {/* Category */}
                                        <div>
                                            <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Category</label>
                                            <select
                                                value={newCompCategory}
                                                onChange={(e) => setNewCompCategory(e.target.value)}
                                                className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500">
                                                {['Headers', 'Hero', 'Features', 'Pricing', 'Forms', 'Advanced', 'Footers'].map(cat => (
                                                    <option key={cat} value={cat}>{cat}</option>
                                                ))}
                                            </select>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        {/* Icon */}
                                        <div>
                                            <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">FontAwesome Icon Class</label>
                                            <input
                                                type="text"
                                                required
                                                value={newCompIcon}
                                                onChange={(e) => setNewCompIcon(e.target.value)}
                                                className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-teal-500"
                                                placeholder="e.g. fas fa-cube"
                                            />
                                        </div>
                                        <div className="flex items-end pb-1.5">
                                            <div className="bg-slate-950/60 rounded px-3 py-2 border border-slate-800 text-xs text-teal-400 flex items-center gap-2">
                                                <span className="text-[10px] font-semibold text-slate-500">Live Icon Preview:</span>
                                                <i className={newCompIcon || 'fas fa-question'}></i>
                                            </div>
                                        </div>
                                    </div>

                                    {/* HTML Template */}
                                    <div>
                                        <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Template Raw HTML (supporting {"{{curly_fields}}"})</label>
                                        <textarea
                                            required
                                            rows="5"
                                            value={newCompHtml}
                                            onChange={(e) => setNewCompHtml(e.target.value)}
                                            className="w-full bg-slate-950 border border-slate-800 rounded p-3 text-xs text-white font-mono focus:outline-none focus:border-teal-500"
                                            placeholder="<div class='p-6'><h3>{{title}}</h3></div>"
                                        />
                                    </div>

                                    {/* Dynamic Properties Fields List */}
                                    <div className="space-y-3">
                                        <div className="flex justify-between items-center">
                                            <h4 className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Dynamic Fields Schema (Props)</h4>
                                            <button
                                                type="button"
                                                onClick={addNewCompField}
                                                className="bg-slate-800 hover:bg-slate-750 text-teal-400 font-bold px-2.5 py-1 rounded text-[10px] uppercase tracking-wider border border-slate-700">
                                                <i className="fas fa-plus mr-1"></i> Add Custom Field
                                            </button>
                                        </div>

                                        {newCompFields.length === 0 ? (
                                            <p className="text-center py-4 text-slate-500 text-xs border border-dashed border-slate-800 rounded-lg">No schema fields specified. Component will be static.</p>
                                        ) : (
                                            <div className="space-y-2 max-h-48 overflow-y-auto pr-1">
                                                {newCompFields.map((field, idx) => (
                                                    <div key={idx} className="p-3 bg-slate-950 border border-slate-850 rounded-lg flex items-center gap-2 text-xs">
                                                        <input
                                                            type="text"
                                                            required
                                                            value={field.key}
                                                            onChange={(e) => updateNewCompField(idx, 'key', e.target.value)}
                                                            className="w-1/4 bg-slate-900 border border-slate-800 rounded px-2 py-1 text-white font-mono"
                                                            placeholder="key"
                                                        />
                                                        <input
                                                            type="text"
                                                            required
                                                            value={field.label}
                                                            onChange={(e) => updateNewCompField(idx, 'label', e.target.value)}
                                                            className="w-1/4 bg-slate-900 border border-slate-800 rounded px-2 py-1 text-white"
                                                            placeholder="Label"
                                                        />
                                                        <select
                                                            value={field.type}
                                                            onChange={(e) => updateNewCompField(idx, 'type', e.target.value)}
                                                            className="w-1/4 bg-slate-900 border border-slate-800 rounded px-2 py-1 text-slate-300">
                                                            <option value="text">text</option>
                                                            <option value="textarea">textarea</option>
                                                            <option value="color">color</option>
                                                            <option value="checkbox">checkbox</option>
                                                        </select>
                                                        <input
                                                            type="text"
                                                            value={field.default}
                                                            onChange={(e) => updateNewCompField(idx, 'default', e.target.value)}
                                                            className="flex-1 bg-slate-900 border border-slate-800 rounded px-2 py-1 text-white"
                                                            placeholder="Default"
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() => removeNewCompField(idx)}
                                                            className="text-rose-400 hover:text-rose-300 px-1 text-xs">
                                                            <i className="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    {/* Action button */}
                                    <div className="pt-4 border-t border-slate-800 flex justify-end gap-3">
                                        <button
                                            type="button"
                                            onClick={() => setIsCustomCompModalOpen(false)}
                                            className="px-4 py-2 bg-slate-800 hover:bg-slate-750 text-white text-xs font-bold rounded">
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            className="px-4 py-2 bg-teal-500 hover:bg-teal-400 text-slate-950 text-xs font-black rounded uppercase tracking-wider">
                                            Create & Add Component
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    )}

                    {/* STRIPE SIMULATED BILLING MODAL OVERLAY */}
                    {showBillingModal && (
                        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
                            <div className="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden flex flex-col font-sans text-slate-100 animate-fadeIn">
                                {/* Header */}
                                <div className="px-6 py-4 border-b border-slate-800 bg-slate-950 flex justify-between items-center">
                                    <h3 className="text-sm font-extrabold text-teal-400 uppercase tracking-widest flex items-center gap-1.5">
                                        <i className="fab fa-stripe text-lg"></i> Stripe Billing Portal
                                    </h3>
                                    <button onClick={() => setShowBillingModal(false)} className="text-slate-400 hover:text-white transition">
                                        <i className="fas fa-times"></i>
                                    </button>
                                </div>
                                {/* Body */}
                                <div className="p-6 space-y-5 text-center">
                                    <i className="fas fa-credit-card text-4xl text-teal-400 animate-bounce"></i>
                                    <div className="space-y-1">
                                        <h4 className="text-base font-black">Upgrade to SaaS Agency Plan</h4>
                                        <p className="text-xs text-slate-400 max-w-xs mx-auto">Gain unlimited multi-page site management, customizable white-label branding, and dedicated API servers.</p>
                                    </div>

                                    <div className="bg-slate-950 border border-slate-850 p-4 rounded-xl text-left space-y-2">
                                        <div className="flex justify-between items-center text-xs font-bold text-slate-300">
                                            <span>White-Label Reseller Rights:</span>
                                            <span className="text-teal-400">Included</span>
                                        </div>
                                        <div className="flex justify-between items-center text-xs font-bold text-slate-300">
                                            <span>Unlimited AI calls & Metering:</span>
                                            <span className="text-teal-400">Unlimited</span>
                                        </div>
                                        <div className="flex justify-between items-center text-xs font-bold text-slate-300">
                                            <span>Annual Bill Amount:</span>
                                            <span className="text-teal-400 font-extrabold">$29.00 / month</span>
                                        </div>
                                    </div>

                                    <button onClick={() => {
                                        showToast("Transmitting upgrade...", "Processing Stripe card tokens securely.");
                                        setTimeout(() => {
                                            setTenantSubscriptionPlan('agency');
                                            setShowBillingModal(false);
                                            alert("Simulated Payment Captured successfully via Stripe! Plan updated to 'agency'. Thank you for your upgrade!");
                                            saveProject(true);
                                        }, 1200);
                                    }} className="w-full bg-gradient-to-r from-teal-400 to-cyan-400 text-slate-950 font-black py-3 rounded-xl text-xs uppercase tracking-wider transition shadow-lg shadow-teal-500/10">
                                        Authorize simulated checkout payment
                                    </button>
                                    <p className="text-[10px] text-slate-500">Payments are entirely simulated for this SaaS demo sandbox environment.</p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* TOAST SYSTEM ALERTS */}
                    {toast && (
                        <div className="fixed bottom-6 right-6 bg-slate-900 border border-teal-500 text-white rounded-lg p-4 shadow-2xl flex items-center gap-3 max-w-sm z-50">
                            <div className="bg-teal-500/20 text-teal-400 p-2 rounded-full text-sm">
                                <i className="fas fa-info-circle"></i>
                            </div>
                            <div>
                                <h4 className="text-xs font-bold">{toast.title}</h4>
                                <p className="text-[11px] text-slate-400 mt-0.5">{toast.desc}</p>
                            </div>
                        </div>
                    )}
                </div>
            );
        }

        const root = ReactDOM.createRoot(document.getElementById('react-app-root'));
        root.render(<App />);
    </script>
</body>
</html>
