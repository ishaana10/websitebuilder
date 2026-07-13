/**
 * WebCraft Live Interactive Workspace Core Engine
 * Manages full lifecycle drag-and-drop, dynamic customizations, automatic API serialization
 */

let activeSelectedElement = null;

document.addEventListener('DOMContentLoaded', () => {
    initializeComponentsShelf();
    loadProjectState();
    initializeGlobalCanvasListeners();
});

/**
 * Build Left Components Shelf categorized beautifully
 */
function initializeComponentsShelf() {
    const shelf = document.getElementById('components-shelf');
    if (!shelf) return;

    // Group components by Category
    const categories = {};
    UI_COMPONENTS.forEach(comp => {
        if (!categories[comp.category]) {
            categories[comp.category] = [];
        }
        categories[comp.category].push(comp);
    });

    shelf.innerHTML = '';

    for (const [cat, items] of Object.entries(categories)) {
        const catContainer = document.createElement('div');
        catContainer.className = 'space-y-2';

        const title = document.createElement('h3');
        title.className = 'text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2';
        title.innerText = cat;
        catContainer.appendChild(title);

        const itemsGrid = document.createElement('div');
        itemsGrid.className = 'grid grid-cols-2 gap-2';

        items.forEach(comp => {
            const btn = document.createElement('div');
            btn.className = 'bg-slate-950 hover:bg-slate-800 border border-slate-800 rounded p-3 text-center cursor-grab transition-all duration-200 select-none';
            btn.setAttribute('draggable', 'true');
            btn.setAttribute('data-component-id', comp.id);
            btn.innerHTML = `
                <div class="text-teal-400 text-lg mb-1.5"><i class="${comp.icon}"></i></div>
                <div class="text-[10px] text-slate-300 font-medium truncate">${comp.name}</div>
            `;

            // HTML5 Drag and Drop Handlers
            btn.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', comp.id);
                e.dataTransfer.effectAllowed = 'copy';
                btn.classList.add('opacity-50');
            });

            btn.addEventListener('dragend', () => {
                btn.classList.remove('opacity-50');
            });

            itemsGrid.appendChild(btn);
        });

        catContainer.appendChild(itemsGrid);
        shelf.appendChild(catContainer);
    }
}

/**
 * Handle incoming layout drops in our central canvas
 */
function handleCanvasDrop(e) {
    e.preventDefault();
    const container = document.getElementById('canvas-container');
    container.classList.remove('canvas-dragover');

    const componentId = e.dataTransfer.getData('text/plain');
    const compDef = UI_COMPONENTS.find(c => c.id === componentId);

    if (compDef) {
        appendComponentToCanvas(compDef.id, compDef.html);
        showToast('Component Added', `Successfully appended standard ${compDef.name}.`);
        saveProject(true); // Autoclose save
    }
}

/**
 * Insert fresh HTML layout structure with custom workspace frames
 */
function appendComponentToCanvas(componentId, innerHtml) {
    const contentArea = document.getElementById('canvas-content');
    const emptyState = document.getElementById('canvas-empty-state');

    if (emptyState) emptyState.classList.add('hidden');

    const wrapper = document.createElement('div');
    wrapper.className = 'group relative border border-transparent hover:border-teal-500/50 rounded-lg p-2 transition-all duration-200 cursor-pointer';
    wrapper.setAttribute('data-component-instance', componentId);

    // Layout helper header visual on hover
    const controlOverlay = document.createElement('div');
    controlOverlay.className = 'absolute -top-3 right-3 bg-teal-500 text-slate-950 font-bold text-[9px] px-2 py-0.5 rounded shadow opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-10 flex gap-2 items-center pointer-events-auto';
    controlOverlay.innerHTML = `
        <span class="uppercase">${componentId}</span>
        <button onclick="moveComponentUp(this.parentNode.parentNode); event.stopPropagation();" title="Move Up"><i class="fas fa-arrow-up"></i></button>
        <button onclick="moveComponentDown(this.parentNode.parentNode); event.stopPropagation();" title="Move Down"><i class="fas fa-arrow-down"></i></button>
        <button onclick="deleteComponentInstance(this.parentNode.parentNode); event.stopPropagation();" class="text-slate-900 hover:text-red-950 font-bold" title="Remove"><i class="fas fa-trash-alt"></i></button>
    `;

    const cleanContainer = document.createElement('div');
    cleanContainer.className = 'canvas-inner-html';
    cleanContainer.innerHTML = innerHtml;

    wrapper.appendChild(controlOverlay);
    wrapper.appendChild(cleanContainer);

    // Active Selection handler
    wrapper.addEventListener('click', (e) => {
        e.stopPropagation();
        selectComponentInstance(wrapper);
    });

    contentArea.appendChild(wrapper);
}

/**
 * Set active focus states
 */
function selectComponentInstance(wrapper) {
    if (activeSelectedElement) {
        activeSelectedElement.classList.remove('component-selected');
    }

    activeSelectedElement = wrapper;
    activeSelectedElement.classList.add('component-selected');

    // Reveal properties sidebar panel
    document.getElementById('no-selection-state').classList.add('hidden');
    document.getElementById('selection-controls').classList.remove('hidden');

    const compId = wrapper.getAttribute('data-component-instance');
    document.getElementById('selected-component-type').innerText = compId.toUpperCase();

    // Fill Property Input States dynamically
    const hEl = wrapper.querySelector('h1, h2, h3');
    document.getElementById('prop-heading-text').value = hEl ? hEl.innerText.trim() : '';

    const pEl = wrapper.querySelector('p');
    document.getElementById('prop-paragraph-text').value = pEl ? pEl.innerText.trim() : '';

    // Show/Hide custom sections based on component type
    const navbarSection = document.getElementById('navbar-custom-section');
    const footerSection = document.getElementById('footer-custom-section');
    const textSection = document.getElementById('prop-group-text');

    if (compId === 'navbar') {
        navbarSection.classList.remove('hidden');
        footerSection.classList.add('hidden');
        textSection.classList.add('hidden');
        loadNavbarCustomizerFields(wrapper);
    } else if (compId === 'footer') {
        navbarSection.classList.add('hidden');
        footerSection.classList.remove('hidden');
        textSection.classList.add('hidden');
        loadFooterCustomizerFields(wrapper);
    } else {
        navbarSection.classList.add('hidden');
        footerSection.classList.add('hidden');
        textSection.classList.remove('hidden');
    }

    // Advanced raw HTML panel trigger
    const rawHtmlSection = document.getElementById('low-code-html-section');
    if (compId === 'html_raw') {
        rawHtmlSection.classList.remove('hidden');
        const container = wrapper.querySelector('.custom-html-container');
        document.getElementById('prop-raw-html').value = container ? container.innerHTML : '';
    } else {
        rawHtmlSection.classList.add('hidden');
    }
}

/**
 * Delete Active Selected Component
 */
function deleteSelectedComponent() {
    if (activeSelectedElement) {
        deleteComponentInstance(activeSelectedElement);
    }
}

function deleteComponentInstance(wrapper) {
    wrapper.remove();
    activeSelectedElement = null;

    // Reset controls UI
    document.getElementById('no-selection-state').classList.remove('hidden');
    document.getElementById('selection-controls').classList.add('hidden');

    // Toggle Empty state if required
    const contentArea = document.getElementById('canvas-content');
    if (contentArea.children.length === 0) {
        const emptyState = document.getElementById('canvas-empty-state');
        if (emptyState) emptyState.classList.remove('hidden');
    }

    showToast('Removed', 'Component removed from live workspace.');
    saveProject(true);
}

/**
 * Move block layers dynamically
 */
function moveComponentUp(wrapper) {
    const previous = wrapper.previousElementSibling;
    if (previous) {
        wrapper.parentNode.insertBefore(wrapper, previous);
        saveProject(true);
    }
}

function moveComponentDown(wrapper) {
    const next = wrapper.nextElementSibling;
    if (next) {
        wrapper.parentNode.insertBefore(next, wrapper);
        saveProject(true);
    }
}

/**
 * Apply element configurations
 */
function updateActiveElementContent(selector, value) {
    if (!activeSelectedElement) return;
    const target = activeSelectedElement.querySelector(selector);
    if (target) {
        target.innerText = value;
    }
}

function updateActiveElementClass(allClasses, newClass) {
    if (!activeSelectedElement) return;
    // We update class lists on the topmost container inner-html child tag
    const target = activeSelectedElement.querySelector('.canvas-inner-html > *');
    if (target) {
        allClasses.forEach(c => target.classList.remove(c));
        if (newClass) {
            target.classList.add(newClass);
        }
    }
}

/**
 * Process Low-Code raw HTML inserts securely
 */
function applyRawHtml() {
    if (!activeSelectedElement) return;
    const compId = activeSelectedElement.getAttribute('data-component-instance');
    if (compId !== 'html_raw') return;

    const rawCode = document.getElementById('prop-raw-html').value;
    const placeholder = activeSelectedElement.querySelector('.text-center');
    const container = activeSelectedElement.querySelector('.custom-html-container');

    if (container) {
        container.innerHTML = rawCode;
        container.classList.remove('hidden');
        if (placeholder) {
            // Hide standard instruction placeholder inside workspace
            placeholder.style.display = 'none';
        }
        showToast('HTML Configured', 'Raw layouts inserted successfully.');
        saveProject(true);
    }
}

/**
 * Adjust Canvas preview sizes dynamically
 */
function setCanvasView(size) {
    const container = document.getElementById('canvas-container');
    const bDesktop = document.getElementById('view-desktop');
    const bTablet = document.getElementById('view-tablet');
    const bMobile = document.getElementById('view-mobile');

    bDesktop.className = bTablet.className = bMobile.className = 'px-3 py-1.5 rounded text-xs font-bold transition duration-200 text-slate-400 hover:text-white';

    if (size === 'desktop') {
        container.style.width = '100%';
        bDesktop.className = 'px-3 py-1.5 rounded text-xs font-bold transition duration-200 bg-slate-800 text-teal-400';
    } else if (size === 'tablet') {
        container.style.width = '768px';
        bTablet.className = 'px-3 py-1.5 rounded text-xs font-bold transition duration-200 bg-slate-800 text-teal-400';
    } else if (size === 'mobile') {
        container.style.width = '375px';
        bMobile.className = 'px-3 py-1.5 rounded text-xs font-bold transition duration-200 bg-slate-800 text-teal-400';
    }
}

/**
 * Serialize full workspace canvas configurations
 */
function serializeCanvasContent() {
    const wrappers = document.querySelectorAll('#canvas-content [data-component-instance]');
    const blocks = [];

    wrappers.forEach(wrap => {
        const componentId = wrap.getAttribute('data-component-instance');
        const hEl = wrap.querySelector('h1, h2, h3');
        const pEl = wrap.querySelector('p');

        const layoutClasses = [];
        const innerTag = wrap.querySelector('.canvas-inner-html > *');
        if (innerTag) {
            innerTag.classList.forEach(cls => layoutClasses.push(cls));
        }

        const customHtmlContainer = wrap.querySelector('.custom-html-container');
        const raw_html = customHtmlContainer ? customHtmlContainer.innerHTML : '';

        let brandText = '';
        let logoImg = '';
        let copyright = '';
        let links = [];

        if (componentId === 'navbar') {
            const brandEl = wrap.querySelector('[data-component="navbar"] div:first-of-type span, [data-component="navbar"] div:first-of-type img');
            brandText = brandEl ? (brandEl.tagName === 'IMG' ? '' : brandEl.innerText.trim()) : '';
            const imgEl = wrap.querySelector('[data-component="navbar"] div:first-of-type img');
            logoImg = imgEl ? imgEl.getAttribute('src') : '';

            const linkEls = wrap.querySelectorAll('[data-component="navbar"] .hidden.md\\:flex a');
            linkEls.forEach(el => {
                links.push({
                    text: el.innerText.trim(),
                    url: el.getAttribute('href')
                });
            });
        } else if (componentId === 'footer') {
            const brandEl = wrap.querySelector('footer div:first-of-type div:first-of-type, footer div:first-of-type img');
            brandText = brandEl ? (brandEl.tagName === 'IMG' ? '' : brandEl.innerText.trim()) : '';
            const imgEl = wrap.querySelector('footer div:first-of-type img');
            logoImg = imgEl ? imgEl.getAttribute('src') : '';

            const copyEl = wrap.querySelector('footer .text-xs');
            copyright = copyEl ? copyEl.innerHTML : '';

            const linkEls = wrap.querySelectorAll('footer .flex.space-x-6 a');
            linkEls.forEach(el => {
                links.push({
                    text: el.innerText.trim(),
                    url: el.getAttribute('href')
                });
            });
        }

        blocks.push({
            componentId: componentId,
            headingText: hEl ? hEl.innerText.trim() : '',
            paragraphText: pEl ? pEl.innerText.trim() : '',
            classes: layoutClasses,
            raw_html: raw_html,
            brandText: brandText,
            logoImg: logoImg,
            copyright: copyright,
            links: links
        });
    });

    const cssArea = document.getElementById('project-custom-css');
    const jsArea = document.getElementById('project-custom-js');
    const customCss = cssArea ? cssArea.value : '';
    const customJs = jsArea ? jsArea.value : '';

    return JSON.stringify({
        blocks: blocks,
        custom_css: customCss,
        custom_js: customJs
    });
}

/**
 * Compile high performance preview representation
 */
function compileFullPageHtml() {
    const wrappers = document.querySelectorAll('#canvas-content [data-component-instance]');
    let body = '';

    wrappers.forEach(wrap => {
        // Deep copy clean component layout
        const inner = wrap.querySelector('.canvas-inner-html').cloneNode(true);

        // If raw HTML block exist, render its custom contents
        const compId = wrap.getAttribute('data-component-instance');
        if (compId === 'html_raw') {
            const rawContainer = inner.querySelector('.custom-html-container');
            body += rawContainer ? rawContainer.innerHTML : '';
        } else {
            body += inner.innerHTML;
        }
    });

    return body;
}

/**
 * Handle API AJAX saves
 */
function saveProject(silent = false) {
    const contentJson = serializeCanvasContent();
    const payload = {
        project_id: PROJECT_ID,
        name: document.title.replace('WebCraft Builder - Editing: ', ''),
        content_json: contentJson,
        csrf_token: CSRF_TOKEN
    };

    if (!silent) showToast('Saving...', 'Syncing workspace configuration with standard database.');

    return fetch('api.php?action=save', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN
        },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (!silent) showToast('Draft Saved', 'Your workspace details have been safely updated.');
            return data;
        } else {
            showToast('Save Error', data.error || 'Server rejected request.', 'bg-red-900 border-red-500 text-white');
            throw new Error(data.error);
        }
    })
    .catch(err => {
        showToast('Save Error', err.message || 'Check network connection.', 'bg-red-900 border-red-500 text-white');
    });
}

/**
 * Publish Site Compile endpoint
 */
function publishProject() {
    saveProject(true)
    .then(() => {
        showToast('Compiling...', 'Assembling Tailwind grids, optimizing assets, and caching production HTML.');

        const compiledHtml = compileFullPageHtml();
        const payload = {
            project_id: PROJECT_ID,
            published_html: compiledHtml,
            csrf_token: CSRF_TOKEN
        };

        return fetch('api.php?action=publish', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify(payload)
        });
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('status-badge').innerText = 'PUBLISHED';
            showToast('Site Published!', 'Click here to launch live preview.', 'bg-slate-900 border-teal-400 text-teal-400', () => {
                window.open(data.url, '_blank');
            });
        } else {
            showToast('Publish Failed', data.error || 'Check configurations.', 'bg-red-900 border-red-500 text-white');
        }
    })
    .catch(err => {
        showToast('Error', err.message, 'bg-red-900 border-red-500 text-white');
    });
}

/**
 * Load initial saved project state
 */
function loadProjectState() {
    try {
        let blocks = [];
        let customCss = '';
        let customJs = '';

        const parsed = JSON.parse(LOADED_CONTENT_STATE);
        if (Array.isArray(parsed)) {
            blocks = parsed;
        } else if (parsed && typeof parsed === 'object') {
            blocks = parsed.blocks || [];
            customCss = parsed.custom_css || '';
            customJs = parsed.custom_js || '';
        }

        // Fill custom CSS/JS textareas
        const cssArea = document.getElementById('project-custom-css');
        if (cssArea) cssArea.value = customCss;
        const jsArea = document.getElementById('project-custom-js');
        if (jsArea) jsArea.value = customJs;

        if (!blocks || blocks.length === 0) return;

        const emptyState = document.getElementById('canvas-empty-state');
        if (emptyState) emptyState.classList.add('hidden');

        blocks.forEach(block => {
            const compDef = UI_COMPONENTS.find(c => c.id === block.componentId);
            if (!compDef) return;

            // Dynamically instantiate template
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = compDef.html;

            // Re-apply custom navbar details
            if (block.componentId === 'navbar') {
                reconstructNavbarComponent(tempDiv, block);
            }
            // Re-apply custom footer details
            if (block.componentId === 'footer') {
                reconstructFooterComponent(tempDiv, block);
            }

            // Re-apply properties
            const hEl = tempDiv.querySelector('h1, h2, h3');
            if (hEl && block.headingText) hEl.innerText = block.headingText;

            const pEl = tempDiv.querySelector('p');
            if (pEl && block.paragraphText) pEl.innerText = block.paragraphText;

            // Re-apply layout classes
            const innerTag = tempDiv.querySelector('[data-component] > *') || tempDiv.querySelector('[data-component]');
            if (innerTag && block.classes && block.classes.length > 0) {
                innerTag.className = block.classes.join(' ');
            }

            // Re-apply raw HTML configuration if present
            if (block.componentId === 'html_raw') {
                const rawContainer = tempDiv.querySelector('.custom-html-container');
                const placeholder = tempDiv.querySelector('.text-center');
                if (rawContainer && block.raw_html) {
                    rawContainer.innerHTML = block.raw_html;
                    rawContainer.classList.remove('hidden');
                    if (placeholder) placeholder.style.display = 'none';
                }
            }

            appendComponentToCanvas(block.componentId, tempDiv.innerHTML);
        });
    } catch (e) {
        console.error("Failed to parse loaded project state: ", e);
    }
}

/**
 * Global click out handles
 */
function initializeGlobalCanvasListeners() {
    document.addEventListener('click', (e) => {
        const wrap = document.getElementById('canvas-wrapper');
        const sidebarLeft = document.querySelector('aside:first-of-type');
        const sidebarRight = document.querySelector('aside:last-of-type');

        if (activeSelectedElement && !activeSelectedElement.contains(e.target) && !sidebarRight.contains(e.target) && !sidebarLeft.contains(e.target)) {
            activeSelectedElement.classList.remove('component-selected');
            activeSelectedElement = null;
            document.getElementById('no-selection-state').classList.remove('hidden');
            document.getElementById('selection-controls').classList.add('hidden');
        }
    });
}

/**
 * Premium custom Notification display
 */
function showToast(title, description, customClass = 'bg-slate-900 border-teal-500 text-white', clickHandler = null) {
    const toast = document.getElementById('notification-toast');
    const tTitle = document.getElementById('notification-title');
    const tDesc = document.getElementById('notification-desc');

    if (!toast) return;

    toast.className = `fixed bottom-6 right-6 border rounded-lg p-4 shadow-2xl flex items-center gap-3 max-w-sm transform transition-all duration-300 z-50 cursor-pointer ${customClass}`;
    tTitle.innerText = title;
    tDesc.innerText = description;

    // Trigger animations
    toast.classList.remove('translate-y-24', 'opacity-0');
    toast.classList.add('translate-y-0', 'opacity-100');

    // Click behavior
    if (clickHandler) {
        toast.onclick = clickHandler;
    } else {
        toast.onclick = null;
    }

    setTimeout(() => {
        toast.classList.remove('translate-y-0', 'opacity-100');
        toast.classList.add('translate-y-24', 'opacity-0');
    }, 4500);
}

/**
 * Switch Control Panel tabs (Properties vs Project CSS/JS)
 */
function switchControlPanelTab(tab) {
    const pPanel = document.getElementById('property-panel');
    const sPanel = document.getElementById('settings-panel');
    const btnProp = document.getElementById('control-tab-btn-properties');
    const btnSet = document.getElementById('control-tab-btn-settings');

    if (tab === 'properties') {
        pPanel.classList.remove('hidden');
        sPanel.classList.add('hidden');
        btnProp.className = 'flex-1 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider border-b-2 border-teal-500 text-teal-400';
        btnSet.className = 'flex-1 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider border-b-2 border-transparent text-slate-400 hover:text-white';
    } else {
        pPanel.classList.add('hidden');
        sPanel.classList.remove('hidden');
        btnProp.className = 'flex-1 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider border-b-2 border-transparent text-slate-400 hover:text-white';
        btnSet.className = 'flex-1 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider border-b-2 border-teal-500 text-teal-400';
    }
}

/**
 * Export compiled visual layout and scripts as standalone ZIP download
 */
function exportProjectZip() {
    showToast('Exporting...', 'Packaging standalone static HTML and interactive assets into a ZIP file.');
    window.location.href = `api.php?action=export&project_id=${PROJECT_ID}`;
}

/**
 * Reconstruct Navbar layout from block state
 */
function reconstructNavbarComponent(tempDiv, block) {
    const nav = tempDiv.querySelector('[data-component="navbar"]');
    if (!nav) return;

    // Brand / Logo Customization
    const logoContainer = nav.querySelector('div:first-of-type');
    if (logoContainer) {
        if (block.logoImg) {
            logoContainer.innerHTML = `<img src="${block.logoImg}" class="h-8 max-w-[120px] object-contain" alt="Logo">`;
        } else {
            const bText = block.brandText || 'WEBCRAFT';
            logoContainer.innerHTML = `<span class="text-xl font-extrabold tracking-wider text-teal-400">${bText}</span>`;
        }
    }

    // Links / Tabs Customization
    const linksContainer = nav.querySelector('.hidden.md\\:flex');
    if (linksContainer) {
        linksContainer.innerHTML = '';
        const navLinks = block.links || [
            { text: 'Home', url: '#home' },
            { text: 'Features', url: '#features' },
            { text: 'Pricing', url: '#pricing' },
            { text: 'Contact', url: '#contact' }
        ];
        navLinks.forEach(lnk => {
            const a = document.createElement('a');
            a.className = 'hover:text-teal-300 transition duration-300';
            a.setAttribute('href', lnk.url);
            a.innerText = lnk.text;
            linksContainer.appendChild(a);
        });
    }
}

/**
 * Load saved navbar fields inside customizer
 */
function loadNavbarCustomizerFields(wrapper) {
    const nav = wrapper.querySelector('[data-component="navbar"]');
    if (!nav) return;

    const brandSpan = nav.querySelector('div:first-of-type span');
    document.getElementById('navbar-brand-text').value = brandSpan ? brandSpan.innerText.trim() : '';

    const brandImg = nav.querySelector('div:first-of-type img');
    document.getElementById('navbar-logo-img').value = brandImg ? brandImg.getAttribute('src') : '';

    const linksContainer = nav.querySelector('.hidden.md\\:flex');
    const linksList = document.getElementById('navbar-links-list');
    linksList.innerHTML = '';

    if (linksContainer) {
        const links = linksContainer.querySelectorAll('a');
        links.forEach((lnk, idx) => {
            appendNavbarLinkRowElement(lnk.innerText.trim(), lnk.getAttribute('href'), idx);
        });
    }
}

/**
 * Append nav link row element inside sidebar lists
 */
function appendNavbarLinkRowElement(text, url, idx) {
    const list = document.getElementById('navbar-links-list');
    const div = document.createElement('div');
    div.className = 'flex gap-1 items-center navbar-link-row bg-slate-950 p-2 rounded border border-slate-850';
    div.innerHTML = `
        <input type="text" placeholder="Tab Text" value="${text}" oninput="updateNavbarFromFields()" class="w-1/2 bg-slate-900 border border-slate-800 rounded px-2 py-1 text-[11px] text-white focus:outline-none focus:border-teal-500">
        <select onchange="updateNavbarFromFields()" class="w-1/2 bg-slate-900 border border-slate-800 rounded px-2 py-1 text-[11px] text-white focus:outline-none focus:border-teal-500">
            <option value="#home" ${url === '#home' ? 'selected' : ''}>Home Section</option>
            <option value="#features" ${url === '#features' ? 'selected' : ''}>Features Section</option>
            <option value="#pricing" ${url === '#pricing' ? 'selected' : ''}>Pricing Section</option>
            <option value="#contact" ${url === '#contact' ? 'selected' : ''}>Contact Section</option>
            <option value="index.php" ${url === 'index.php' ? 'selected' : ''}>Index Page</option>
            <option value="admin.php" ${url === 'admin.php' ? 'selected' : ''}>Admin Page</option>
            <option value="${!url.startsWith('#') && url !== 'index.php' && url !== 'admin.php' ? url : ''}" ${!url.startsWith('#') && url !== 'index.php' && url !== 'admin.php' ? 'selected' : ''} class="custom-url-option">Custom URL...</option>
        </select>
        <button onclick="this.parentNode.remove(); updateNavbarFromFields();" class="text-red-400 hover:text-red-300 p-1" title="Delete Tab">
            <i class="fas fa-trash-alt text-[10px]"></i>
        </button>
    `;

    const select = div.querySelector('select');
    select.addEventListener('change', (e) => {
        if (e.target.value === '') {
            const customUrl = prompt('Enter custom target link URL (e.g. https://google.com):');
            if (customUrl) {
                let opt = select.querySelector('.custom-url-option');
                if (!opt) {
                    opt = document.createElement('option');
                    opt.className = 'custom-url-option';
                    select.appendChild(opt);
                }
                opt.value = customUrl;
                opt.innerText = customUrl;
                opt.selected = true;
                updateNavbarFromFields();
            } else {
                select.selectedIndex = 0;
            }
        }
    });

    list.appendChild(div);
}

function addNavbarLinkRow() {
    appendNavbarLinkRowElement('New Tab', '#home', Date.now());
    updateNavbarFromFields();
}

/**
 * Handle live preview and DB state syncing for Navbar Customizer
 */
function updateNavbarFromFields() {
    if (!activeSelectedElement) return;
    const nav = activeSelectedElement.querySelector('[data-component="navbar"]');
    if (!nav) return;

    const bText = document.getElementById('navbar-brand-text').value.trim();
    const logoImgUrl = document.getElementById('navbar-logo-img').value.trim();
    const logoContainer = nav.querySelector('div:first-of-type');

    if (logoContainer) {
        if (logoImgUrl) {
            logoContainer.innerHTML = `<img src="${logoImgUrl}" class="h-8 max-w-[120px] object-contain" alt="Logo">`;
        } else {
            logoContainer.innerHTML = `<span class="text-xl font-extrabold tracking-wider text-teal-400">${bText || 'WEBCRAFT'}</span>`;
        }
    }

    const linksList = document.getElementById('navbar-links-list');
    const rows = linksList.querySelectorAll('.navbar-link-row');
    const linksContainer = nav.querySelector('.hidden.md\\:flex');

    if (linksContainer) {
        linksContainer.innerHTML = '';
        rows.forEach(row => {
            const textInput = row.querySelector('input');
            const selectSel = row.querySelector('select');

            const linkText = textInput ? textInput.value.trim() : 'Tab';
            const linkUrl = selectSel ? selectSel.value : '#home';

            const a = document.createElement('a');
            a.className = 'hover:text-teal-300 transition duration-300';
            a.setAttribute('href', linkUrl);
            a.innerText = linkText;
            linksContainer.appendChild(a);
        });
    }

    saveProject(true);
}

function updateNavbarLogoText(val) {
    updateNavbarFromFields();
}

function reconstructFooterComponent(tempDiv, block) {
    const foot = tempDiv.querySelector('footer');
    if (!foot) return;

    // Brand Logo
    const brandContainer = foot.querySelector('div:first-of-type div:first-of-type') || foot.querySelector('div:first-of-type img');
    if (brandContainer) {
        if (block.logoImg) {
            brandContainer.outerHTML = `<img src="${block.logoImg}" class="h-8 max-w-[120px] object-contain" alt="Logo">`;
        } else {
            const bText = block.brandText || 'WEBCRAFT BUILDER';
            brandContainer.outerHTML = `<div class="text-lg font-black text-white">${bText}</div>`;
        }
    }

    // Copyright Text
    const copyEl = foot.querySelector('.text-xs');
    if (copyEl && block.copyright) {
        copyEl.innerText = block.copyright;
    }

    // Links list
    const linksContainer = foot.querySelector('.flex.space-x-6');
    if (linksContainer) {
        linksContainer.innerHTML = '';
        const footLinks = block.links || [
            { text: 'Privacy Policy', url: '#' },
            { text: 'Terms of Use', url: '#' },
            { text: 'Support', url: '#' }
        ];
        footLinks.forEach(lnk => {
            const a = document.createElement('a');
            a.className = 'hover:text-white transition';
            a.setAttribute('href', lnk.url);
            a.innerText = lnk.text;
            linksContainer.appendChild(a);
        });
    }
}

/**
 * Load saved footer fields inside customizer
 */
function loadFooterCustomizerFields(wrapper) {
    const foot = wrapper.querySelector('footer');
    if (!foot) return;

    const brandDiv = foot.querySelector('div:first-of-type div:first-of-type') || foot.querySelector('div:first-of-type img');
    document.getElementById('footer-brand-text').value = (brandDiv && brandDiv.tagName !== 'IMG') ? brandDiv.innerText.trim() : 'WEBCRAFT BUILDER';

    const brandImg = foot.querySelector('div:first-of-type img');
    document.getElementById('footer-logo-img').value = brandImg ? brandImg.getAttribute('src') : '';

    const copyEl = foot.querySelector('.text-xs');
    document.getElementById('footer-copyright').value = copyEl ? copyEl.innerText.trim() : '';

    const linksContainer = foot.querySelector('.flex.space-x-6');
    const linksList = document.getElementById('footer-links-list');
    linksList.innerHTML = '';

    if (linksContainer) {
        const links = linksContainer.querySelectorAll('a');
        links.forEach((lnk, idx) => {
            appendFooterLinkRowElement(lnk.innerText.trim(), lnk.getAttribute('href'), idx);
        });
    }
}

/**
 * Append footer link row element inside sidebar lists
 */
function appendFooterLinkRowElement(text, url, idx) {
    const list = document.getElementById('footer-links-list');
    const div = document.createElement('div');
    div.className = 'flex gap-1 items-center footer-link-row bg-slate-950 p-2 rounded border border-slate-850';
    div.innerHTML = `
        <input type="text" placeholder="Link Text" value="${text}" oninput="updateFooterFromFields()" class="w-1/2 bg-slate-900 border border-slate-800 rounded px-2 py-1 text-[11px] text-white focus:outline-none focus:border-teal-500">
        <select onchange="updateFooterFromFields()" class="w-1/2 bg-slate-900 border border-slate-800 rounded px-2 py-1 text-[11px] text-white focus:outline-none focus:border-teal-500">
            <option value="#home" ${url === '#home' ? 'selected' : ''}>Home Section</option>
            <option value="#features" ${url === '#features' ? 'selected' : ''}>Features Section</option>
            <option value="#pricing" ${url === '#pricing' ? 'selected' : ''}>Pricing Section</option>
            <option value="#contact" ${url === '#contact' ? 'selected' : ''}>Contact Section</option>
            <option value="index.php" ${url === 'index.php' ? 'selected' : ''}>Index Page</option>
            <option value="admin.php" ${url === 'admin.php' ? 'selected' : ''}>Admin Page</option>
            <option value="${!url.startsWith('#') && url !== 'index.php' && url !== 'admin.php' ? url : ''}" ${!url.startsWith('#') && url !== 'index.php' && url !== 'admin.php' ? 'selected' : ''} class="custom-footer-url-option">Custom URL...</option>
        </select>
        <button onclick="this.parentNode.remove(); updateFooterFromFields();" class="text-red-400 hover:text-red-300 p-1" title="Delete Link">
            <i class="fas fa-trash-alt text-[10px]"></i>
        </button>
    `;

    const select = div.querySelector('select');
    select.addEventListener('change', (e) => {
        if (e.target.value === '') {
            const customUrl = prompt('Enter custom target link URL (e.g. https://google.com):');
            if (customUrl) {
                let opt = select.querySelector('.custom-footer-url-option');
                if (!opt) {
                    opt = document.createElement('option');
                    opt.className = 'custom-footer-url-option';
                    select.appendChild(opt);
                }
                opt.value = customUrl;
                opt.innerText = customUrl;
                opt.selected = true;
                updateFooterFromFields();
            } else {
                select.selectedIndex = 0;
            }
        }
    });

    list.appendChild(div);
}

function addFooterLinkRow() {
    appendFooterLinkRowElement('New Link', '#home', Date.now());
    updateFooterFromFields();
}

/**
 * Handle live preview and DB state syncing for Footer Customizer
 */
function updateFooterFromFields() {
    if (!activeSelectedElement) return;
    const foot = activeSelectedElement.querySelector('footer');
    if (!foot) return;

    const bText = document.getElementById('footer-brand-text').value.trim();
    const logoImgUrl = document.getElementById('footer-logo-img').value.trim();
    const logoContainer = foot.querySelector('div:first-of-type div:first-of-type') || foot.querySelector('div:first-of-type img');

    if (logoContainer) {
        if (logoImgUrl) {
            if (logoContainer.tagName === 'IMG') {
                logoContainer.setAttribute('src', logoImgUrl);
            } else {
                logoContainer.outerHTML = `<img src="${logoImgUrl}" class="h-8 max-w-[120px] object-contain" alt="Logo">`;
            }
        } else {
            if (logoContainer.tagName === 'IMG') {
                logoContainer.outerHTML = `<div class="text-lg font-black text-white">${bText || 'WEBCRAFT BUILDER'}</div>`;
            } else {
                logoContainer.innerText = bText || 'WEBCRAFT BUILDER';
            }
        }
    }

    const copyText = document.getElementById('footer-copyright').value.trim();
    const copyEl = foot.querySelector('.text-xs');
    if (copyEl) {
        copyEl.innerText = copyText || `© ${new Date().getFullYear()} WebCraft. All rights reserved. Open Source under MIT.`;
    }

    const linksList = document.getElementById('footer-links-list');
    const rows = linksList.querySelectorAll('.footer-link-row');
    const linksContainer = foot.querySelector('.flex.space-x-6');

    if (linksContainer) {
        linksContainer.innerHTML = '';
        rows.forEach(row => {
            const textInput = row.querySelector('input');
            const selectSel = row.querySelector('select');

            const linkText = textInput ? textInput.value.trim() : 'Link';
            const linkUrl = selectSel ? selectSel.value : '#home';

            const a = document.createElement('a');
            a.className = 'hover:text-white transition';
            a.setAttribute('href', linkUrl);
            a.innerText = linkText;
            linksContainer.appendChild(a);
        });
    }

    saveProject(true);
}

function updateFooterBrandText(val) {
    updateFooterFromFields();
}

function updateNavbarLogoImage(val) {
    updateFooterFromFields();
}

function updateFooterCopyright(val) {
    updateFooterFromFields();
}
