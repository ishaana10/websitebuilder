<?php
/**
 * Nuvis Webbuilder Live React-Powered Workspace
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
    <title>Nuvis Webbuilder - Editing: <?php echo sanitize_output($project['name']); ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Premium Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- React & ReactDOM -->
    <script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>
    <!-- Babel for in-browser JSX parsing -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/babel-standalone/6.26.0/babel.min.js"></script>

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
    <script src="assets/js/components.js"></script>

    <!-- REACT VISUAL BUILDER CORE ENGINE -->
    <script type="text/babel" data-presets="react">
        const { useState, useEffect, useRef } = React;

        function App() {
            // --- Core States ---
            const [sections, setSections] = useState([]);
            const [activeSectionId, setActiveSectionId] = useState(null);
            const [canvasView, setCanvasView] = useState('desktop'); // desktop, tablet, mobile
            const [rightPanelTab, setRightPanelTab] = useState('properties'); // properties, settings
            const [customCss, setCustomCss] = useState('');
            const [customJs, setCustomJs] = useState('');
            const [projectStatus, setProjectStatus] = useState(PROJECT_STATUS);

            // --- Nuvis Email Module Settings ---
            const [emailRecipient, setEmailRecipient] = useState('');
            const [autoResponderEnabled, setAutoResponderEnabled] = useState(false);
            const [emailTemplateTheme, setEmailTemplateTheme] = useState('modern_minimalist'); // modern_minimalist, elegant, tech_light
            const [autoResponderSubject, setAutoResponderSubject] = useState('Thank you for contacting us!');
            const [autoResponderBody, setAutoResponderBody] = useState('Hello!\n\nWe have received your inquiry regarding our services and will get back to you shortly.\n\nBest regards,\nThe Team');

            // --- Undo/Redo History States ---
            const [history, setHistory] = useState([]);
            const [historyIndex, setHistoryIndex] = useState(-1);

            // --- System states ---
            const [isSaving, setIsSaving] = useState(false);
            const [isPublishing, setIsPublishing] = useState(false);
            const [toast, setToast] = useState(null);

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
                    if (raw && raw.email_settings) {
                        rec = raw.email_settings.recipient || '';
                        autoResp = !!raw.email_settings.auto_responder_enabled;
                        templTheme = raw.email_settings.template_theme || 'modern_minimalist';
                        autoSub = raw.email_settings.auto_responder_subject || 'Thank you for contacting us!';
                        autoBody = raw.email_settings.auto_responder_body || 'Hello!\n\nWe have received your inquiry regarding our services and will get back to you shortly.\n\nBest regards,\nThe Team';
                    }

                    // Populate missing props with schema defaults
                    initialSections = initialSections.map(s => {
                        const compDef = UI_COMPONENTS.find(c => c.id === s.type);
                        if (compDef && compDef.schema) {
                            compDef.schema.forEach(field => {
                                if (s.props[field.key] === undefined) {
                                    s.props[field.key] = field.default;
                                }
                            });
                        }
                        return s;
                    });

                    setSections(initialSections);
                    setCustomCss(initialCss);
                    setCustomJs(initialJs);
                    setEmailRecipient(rec);
                    setAutoResponderEnabled(autoResp);
                    setEmailTemplateTheme(templTheme);
                    setAutoResponderSubject(autoSub);
                    setAutoResponderBody(autoBody);

                    // Initialize history stack
                    setHistory([initialSections]);
                    setHistoryIndex(0);

                } catch (e) {
                    console.error("Bootstrapping content JSON error: ", e);
                }
            }, []);

            // --- History Stack Helpers ---
            const commitToHistory = (newSections) => {
                const updatedHistory = history.slice(0, historyIndex + 1);
                setHistory([...updatedHistory, newSections]);
                setHistoryIndex(updatedHistory.length);
            };

            const updateSectionsWithHistory = (newSections) => {
                setSections(newSections);
                commitToHistory(newSections);
            };

            const undo = () => {
                if (historyIndex > 0) {
                    const prevIdx = historyIndex - 1;
                    setHistoryIndex(prevIdx);
                    setSections(history[prevIdx]);
                    showToast("Undo", "Action reverted.");
                }
            };

            const redo = () => {
                if (historyIndex < history.length - 1) {
                    const nextIdx = historyIndex + 1;
                    setHistoryIndex(nextIdx);
                    setSections(history[nextIdx]);
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
                const compDef = UI_COMPONENTS.find(c => c.id === (block.componentId || block.type));
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

            const handleCanvasDrop = (e) => {
                e.preventDefault();
                const componentId = e.dataTransfer.getData('text/plain');
                const compDef = UI_COMPONENTS.find(c => c.id === componentId);
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
            const serializeCanvas = () => {
                const blocks = sections.map(sec => ({
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

                return JSON.stringify({
                    sections:   sections,
                    blocks:     blocks,
                    custom_css: customCss,
                    custom_js:  customJs,
                    // Nuvis Email module configuration parameters
                    email_settings: {
                        recipient: emailRecipient,
                        auto_responder_enabled: autoResponderEnabled,
                        template_theme: emailTemplateTheme,
                        auto_responder_subject: autoResponderSubject,
                        auto_responder_body: autoResponderBody
                    }
                });
            };

            // --- Save / Publish / Export Requests ---
            const saveProject = (silent = false) => {
                if (!silent) setIsSaving(true);
                const contentJson = serializeCanvas();
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
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (!silent) showToast("Draft Saved", "Workspace saved successfully.");
                        return data;
                    } else {
                        showToast("Error", data.error || "Save rejected.");
                        throw new Error(data.error);
                    }
                })
                .catch(err => showToast("Network Error", err.message))
                .finally(() => setIsSaving(false));
            };

            // DYNAMIC KEY-VALUE TEMPLATING COMPILER
            const compileSectionHtml = (sec) => {
                const compDef = UI_COMPONENTS.find(c => c.id === sec.type);
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

                // Always inject section id as root ID
                const temp = document.createElement('div');
                temp.innerHTML = compiledHtml;
                const rootNode = temp.querySelector('[data-component]');
                if (rootNode) {
                    rootNode.id = sec.id;
                    compiledHtml = temp.innerHTML;
                }

                return compiledHtml;
            };

            const publishProject = () => {
                setIsPublishing(true);
                saveProject(true).then(() => {
                    const fullHtml = sections.map(compileSectionHtml).join('\n');
                    const payload = {
                        project_id: PROJECT_ID,
                        published_html: fullHtml,
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
                            <div className="p-4 border-b border-slate-800 bg-slate-900/50">
                                <h2 className="text-xs font-extrabold text-teal-400 uppercase tracking-widest">Components Shelf</h2>
                                <p className="text-[11px] text-slate-400 mt-1">Drag and drop components directly onto the web canvas.</p>
                            </div>

                            <div className="flex-1 overflow-y-auto p-4 space-y-4">
                                {['Headers', 'Hero', 'Features', 'Pricing', 'Forms', 'Advanced', 'Footers'].map(cat => {
                                    const items = UI_COMPONENTS.filter(comp => comp.category === cat);
                                    if (items.length === 0) return null;
                                    return (
                                        <div key={cat} className="space-y-2">
                                            <h3 className="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">{cat}</h3>
                                            <div className="grid grid-cols-2 gap-2">
                                                {items.map(comp => (
                                                    <div key={comp.id} draggable onDragStart={(e) => handleDragStart(e, comp.id)} className="bg-slate-950 hover:bg-slate-800 border border-slate-800/80 rounded-lg p-3 text-center cursor-grab transition-all duration-200 select-none group hover:border-teal-500/30">
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
                        <main className="flex-1 bg-slate-950 overflow-y-auto p-8 flex justify-center items-start transition-all" onClick={() => setActiveSectionId(null)}>

                            {/* Adaptive Screen Size Frame / Bezel simulation */}
                            <div className={`${canvasView === 'mobile' ? 'device-bezel-mobile' : canvasView === 'tablet' ? 'device-bezel-tablet' : 'w-full'} min-h-[500px] bg-slate-900 rounded-xl transition-all duration-300 relative border-2 border-slate-800 p-4`} onDragOver={(e) => e.preventDefault()} onDrop={handleCanvasDrop} onClick={(e) => e.stopPropagation()}>

                                {sections.length === 0 && (
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
                                    {sections.map((sec, idx) => {
                                        const isActive = (sec.id === activeSectionId);
                                        return (
                                            <div key={sec.id} onClick={(e) => { e.stopPropagation(); setActiveSectionId(sec.id); }} className={`group relative border border-transparent hover:border-teal-500/50 rounded-lg p-2 transition-all duration-200 cursor-pointer ${isActive ? 'section-selected' : ''}`} data-section-id={sec.id} data-component-instance={sec.type}>

                                                {/* Visual Controls Overlay */}
                                                <div className="absolute -top-3.5 right-3 bg-teal-500 text-slate-950 font-black text-[9px] px-2.5 py-1 rounded shadow opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-30 flex gap-3 items-center pointer-events-auto">
                                                    <span className="uppercase font-extrabold">{sec.type}</span>
                                                    <div className="flex gap-2">
                                                        <button title="Move Up" onClick={(e) => { e.stopPropagation(); moveSectionUp(idx); }} disabled={idx === 0} className={`disabled:opacity-30`}><i className="fas fa-arrow-up"></i></button>
                                                        <button title="Move Down" onClick={(e) => { e.stopPropagation(); moveSectionDown(idx); }} disabled={idx === sections.length - 1} className={`disabled:opacity-30`}><i className="fas fa-arrow-down"></i></button>
                                                        <button title="Duplicate" onClick={(e) => { e.stopPropagation(); duplicateSection(idx); }}><i className="fas fa-copy"></i></button>
                                                        <button title="Remove" className="text-slate-950 hover:text-red-900" onClick={(e) => { e.stopPropagation(); deleteSection(sec.id); }}><i className="fas fa-trash-alt"></i></button>
                                                    </div>
                                                </div>

                                                {/* Canvas Inner Render Node (Injected React Simulation) */}
                                                <div className="canvas-inner-html" dangerouslySetInnerHTML={{ __html: compileSectionHtml(sec) }} />
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

                            {/* TAB NAVIGATION MENU */}
                            <div className="flex border-b border-slate-800 bg-slate-950/40 shrink-0">
                                <button onClick={() => setRightPanelTab('properties')} className={`flex-1 py-3 text-center text-[10px] font-bold uppercase tracking-wider border-b-2 transition ${rightPanelTab === 'properties' ? 'border-teal-500 text-teal-400' : 'border-transparent text-slate-400 hover:text-white'}`}>
                                    Properties
                                </button>
                                <button onClick={() => setRightPanelTab('settings')} className={`flex-1 py-3 text-center text-[10px] font-bold uppercase tracking-wider border-b-2 transition ${rightPanelTab === 'settings' ? 'border-teal-500 text-teal-400' : 'border-transparent text-slate-400 hover:text-white'}`}>
                                    Project Settings & Mail
                                </button>
                            </div>

                            {/* DYNAMIC PROPERTIES VIEW */}
                            {rightPanelTab === 'properties' ? (
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

                                            <hr className="border-slate-800" />

                                            {/* DYNAMIC COMPONENT SCHEMA FIELDS GENERATION */}
                                            {(() => {
                                                const compDef = UI_COMPONENTS.find(c => c.id === selectedSection.type);
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
                                                                return (
                                                                    <div key={field.key}>
                                                                        <label className="text-[11px] text-slate-400 block mb-1">{field.label}</label>
                                                                        <input id={inputId} type="text" value={val} onChange={(e) => handleFieldChange(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500" />
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
                                                                        <label className="text-[11px] text-slate-400 block">{field.label}</label>
                                                                        <div className="flex items-center gap-2">
                                                                            <span className="text-[10px] font-mono text-slate-400">{val}</span>
                                                                            <input type="color" value={val} onChange={(e) => handleFieldChange(e.target.value)} className="w-8 h-8 rounded border-0 bg-transparent cursor-pointer" />
                                                                        </div>
                                                                    </div>
                                                                );
                                                            } else if (field.type === 'select') {
                                                                return (
                                                                    <div key={field.key}>
                                                                        <label className="text-[11px] text-slate-400 block mb-1">{field.label}</label>
                                                                        <select value={val} onChange={(e) => handleFieldChange(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-500">
                                                                            {field.options.map(opt => (
                                                                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                                                                            ))}
                                                                        </select>
                                                                    </div>
                                                                );
                                                            } else if (field.type === 'checkbox') {
                                                                return (
                                                                    <div key={field.key} className="flex items-center justify-between">
                                                                        <label className="text-[11px] text-slate-400 block">{field.label}</label>
                                                                        <input type="checkbox" checked={!!val} onChange={(e) => handleFieldChange(e.target.checked)} className="w-4 h-4 rounded border-slate-800 bg-slate-950 text-teal-500 focus:ring-0" />
                                                                    </div>
                                                                );
                                                            }
                                                            return null;
                                                        })}
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
                            ) : (
                                /* ADVANCED CUSTOM SETTINGS TAB (CSS/JS INJECTIONS & EMAIL CONFIGURATION MODULE) */
                                <div className="flex-1 overflow-y-auto p-4 space-y-5">
                                    {/* NUVIS EMAIL CONFIGURATION MODULE */}
                                    <div className="space-y-4 bg-slate-950/40 p-3.5 rounded-lg border border-slate-850">
                                        <h4 className="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <i className="fas fa-paper-plane"></i> Nuvis Email Module
                                        </h4>
                                        <p className="text-[10px] text-slate-400 leading-relaxed">Configure form-notification alerts, SMTP dispatch routes, and automatic responder templates securely.</p>

                                        <div>
                                            <label className="text-[11px] text-slate-400 block mb-1">Inquiry Notification Recipient</label>
                                            <input type="email" value={emailRecipient} onChange={(e) => setEmailRecipient(e.target.value)} placeholder="e.g., sales@nuvis.com" className="w-full bg-slate-950 border border-slate-800 rounded px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-teal-500" />
                                            <span className="text-[9px] text-slate-500 mt-1 block">Recipient email for form notifications (fallback to your account email).</span>
                                        </div>

                                        <div className="flex items-center justify-between border-t border-slate-800/80 pt-2.5">
                                            <label className="text-[11px] text-slate-400 block font-semibold">Enable Customer Auto-Responder</label>
                                            <input type="checkbox" checked={autoResponderEnabled} onChange={(e) => setAutoResponderEnabled(e.target.checked)} className="w-4 h-4 rounded border-slate-800 bg-slate-950 text-teal-500 focus:ring-0" />
                                        </div>

                                        {autoResponderEnabled && (
                                            <div className="space-y-3.5 border-t border-slate-800/80 pt-2.5">
                                                <div>
                                                    <label className="text-[11px] text-slate-400 block mb-1">Email Template Style</label>
                                                    <select value={emailTemplateTheme} onChange={(e) => setEmailTemplateTheme(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded px-2.5 py-1.5 text-xs text-white focus:outline-none">
                                                        <option value="modern_minimalist">Modern Minimalist (Teal)</option>
                                                        <option value="elegant">Elegant Indigo Gold (Royal)</option>
                                                        <option value="tech_light">Tech Light (Clean Blue)</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="text-[11px] text-slate-400 block mb-1">Auto-Response Subject</label>
                                                    <input type="text" value={autoResponderSubject} onChange={(e) => setAutoResponderSubject(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-teal-500" />
                                                </div>
                                                <div>
                                                    <label className="text-[11px] text-slate-400 block mb-1">Auto-Response Message Body</label>
                                                    <textarea rows={4} value={autoResponderBody} onChange={(e) => setAutoResponderBody(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded px-2.5 py-1.5 text-xs font-sans text-slate-200 focus:outline-none focus:border-teal-500" />
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    <hr className="border-slate-850" />

                                    {/* STANDARD SCRIPT AND STYLE INJECTIONS */}
                                    <div className="space-y-4">
                                        <h4 className="text-[10px] font-bold text-teal-400 uppercase tracking-wider flex items-center gap-1.5"><i className="fas fa-sliders-h"></i> Custom Script Injection</h4>

                                        <div>
                                            <label className="text-[11px] text-slate-400 block mb-1">Custom CSS Stylesheet</label>
                                            <textarea rows={4} value={customCss} onChange={(e) => setCustomCss(e.target.value)} placeholder="body { background-color: #0b0f19; }" className="w-full bg-slate-950 border border-slate-800 rounded p-2 text-xs font-mono text-cyan-400 focus:outline-none focus:border-teal-500" />
                                        </div>

                                        <div>
                                            <label className="text-[11px] text-slate-400 block mb-1">Custom JavaScript Logic</label>
                                            <textarea rows={4} value={customJs} onChange={(e) => setCustomJs(e.target.value)} placeholder="console.log('Nuvis Webbuilder custom scripts active');" className="w-full bg-slate-950 border border-slate-800 rounded p-2 text-xs font-mono text-emerald-400 focus:outline-none focus:border-teal-500" />
                                        </div>

                                        <button onClick={() => saveProject(false)} className="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-2.5 rounded text-xs transition">
                                            Apply & Save Settings
                                        </button>
                                    </div>
                                </div>
                            )}
                        </aside>
                    </div>

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
