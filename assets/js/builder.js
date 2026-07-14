/**
 * WebCraft Builder — JSON-driven render pipeline
 * Single source of truth: `page` object (from LOADED_CONTENT_STATE).
 * DOM is always a pure projection of page.sections.
 */

// ─── Core state ──────────────────────────────────────────────────────────────
let page = { sections: [] };
let activeSectionId = null;

// ─── Bootstrap ───────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initPage();
    initializeComponentsShelf();
    initializeGlobalCanvasListeners();
    wirePropertyPanelListeners();
});

function initPage() {
    try {
        let raw = LOADED_CONTENT_STATE;
        if (typeof raw === 'string') raw = JSON.parse(raw);

        // Legacy format: { blocks: [...] }  →  convert to sections array
        if (raw && Array.isArray(raw.blocks)) {
            page = { sections: raw.blocks.map(blockToSection) };

            // Restore custom CSS/JS if present
            const cssArea = document.getElementById('project-custom-css');
            const jsArea  = document.getElementById('project-custom-js');
            if (cssArea) cssArea.value = raw.custom_css || '';
            if (jsArea)  jsArea.value  = raw.custom_js  || '';

        } else if (raw && Array.isArray(raw.sections)) {
            page = raw;
        } else {
            page = { sections: [] };
        }
    } catch (e) {
        console.error('Failed to parse LOADED_CONTENT_STATE', e);
        page = { sections: [] };
    }
    renderPage(page);
}

/**
 * Convert a legacy serialized block back into the new section shape.
 */
function blockToSection(block) {
    return {
        id: 'sec-' + (block.componentId || 'unknown') + '-' + Date.now() + '-' + Math.random().toString(36).slice(2, 6),
        type: block.componentId,
        props: {
            heading:   block.headingText   || '',
            text:      block.paragraphText || '',
            brandText: block.brandText     || '',
            logoUrl:   block.logoImg       || '',
            copyright: block.copyright     || '',
            links:     block.links         || [],
            rawHtml:   block.raw_html      || '',
        },
        style: {
            classes: block.classes || [],
        }
    };
}

// ─── renderPage ──────────────────────────────────────────────────────────────
function renderPage(pg) {
    const canvasContent = document.getElementById('canvas-content');
    const emptyState    = document.getElementById('canvas-empty-state');
    if (!canvasContent) return;

    canvasContent.innerHTML = '';

    if (!pg.sections || pg.sections.length === 0) {
        if (emptyState) emptyState.classList.remove('hidden');
        return;
    }
    if (emptyState) emptyState.classList.add('hidden');

    pg.sections.forEach(section => {
        canvasContent.appendChild(renderSection(section));
    });

    // Re-apply selection highlight if a section was active
    if (activeSectionId) {
        const el = canvasContent.querySelector(`[data-section-id="${activeSectionId}"]`);
        if (el) el.classList.add('component-selected');
    }
}

// ─── renderSection ────────────────────────────────────────────────────────────
function renderSection(section) {
    // Outer wrapper (mirrors legacy wrapper div for CSS/controls)
    const wrapper = document.createElement('div');
    wrapper.className = 'group relative border border-transparent hover:border-teal-500/50 rounded-lg p-2 transition-all duration-200 cursor-pointer component-section';
    wrapper.setAttribute('data-section-id', section.id);
    wrapper.setAttribute('data-component-instance', section.type); // keep legacy attr for CSS

    // Per-section controls overlay
    const controlOverlay = document.createElement('div');
    controlOverlay.className = 'absolute -top-3 right-3 bg-teal-500 text-slate-950 font-bold text-[9px] px-2 py-0.5 rounded shadow opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-10 flex gap-2 items-center pointer-events-auto';
    controlOverlay.innerHTML = `
        <span class="uppercase">${section.type}</span>
        <button title="Move Up"   onclick="moveSectionUp('${section.id}');   event.stopPropagation();"><i class="fas fa-arrow-up"></i></button>
        <button title="Move Down" onclick="moveSectionDown('${section.id}'); event.stopPropagation();"><i class="fas fa-arrow-down"></i></button>
        <button title="Duplicate" onclick="duplicateSection('${section.id}'); event.stopPropagation();"><i class="fas fa-copy"></i></button>
        <button title="Remove" class="text-slate-900 hover:text-red-950 font-bold" onclick="deleteSection('${section.id}'); event.stopPropagation();"><i class="fas fa-trash-alt"></i></button>
    `;

    // Inner HTML rendered from props
    const inner = document.createElement('div');
    inner.className = 'canvas-inner-html';
    buildSectionInner(inner, section);

    wrapper.appendChild(controlOverlay);
    wrapper.appendChild(inner);

    // Click → select
    wrapper.addEventListener('click', (e) => {
        e.stopPropagation();
        selectSection(section.id);
    });

    return wrapper;
}

/**
 * Fill `inner` div with rendered HTML for the section type.
 * Each branch mirrors the original UI_COMPONENTS HTML structure so
 * existing CSS, customizer code, and navbar/footer helpers all work.
 */
function buildSectionInner(inner, section) {
    const p = section.props || {};
    const compDef = UI_COMPONENTS.find(c => c.id === section.type);

    switch (section.type) {

        case 'navbar': {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = compDef ? compDef.html : '';
            // Apply saved navbar props via existing reconstructor
            reconstructNavbarComponent(tempDiv, {
                brandText: p.brandText,
                logoImg:   p.logoUrl,
                links:     p.links,
            });
            inner.appendChild(tempDiv.firstElementChild || tempDiv);
            break;
        }

        case 'footer': {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = compDef ? compDef.html : '';
            reconstructFooterComponent(tempDiv, {
                brandText: p.brandText,
                logoImg:   p.logoUrl,
                copyright: p.copyright,
                links:     p.links,
            });
            inner.appendChild(tempDiv.firstElementChild || tempDiv);
            break;
        }

        case 'html_raw': {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = compDef ? compDef.html : '';
            if (p.rawHtml) {
                const container   = tempDiv.querySelector('.custom-html-container');
                const placeholder = tempDiv.querySelector('.text-center');
                if (container) {
                    container.innerHTML = p.rawHtml;
                    container.classList.remove('hidden');
                    if (placeholder) placeholder.style.display = 'none';
                }
            }
            inner.appendChild(tempDiv.firstElementChild || tempDiv);
            break;
        }

        case 'hero':
        case 'features':
        case 'pricing':
        case 'contact':
        case 'chatbot':
        default: {
            // Start from the library template, then overwrite heading/paragraph
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = compDef ? compDef.html : `<div class="p-8 text-slate-400 text-xs">Unknown component: ${section.type}</div>`;

            if (p.heading) {
                const hEl = tempDiv.querySelector('h1, h2, h3');
                if (hEl) hEl.innerText = p.heading;
            }
            if (p.text) {
                const pEl = tempDiv.querySelector('p');
                if (pEl) pEl.innerText = p.text;
            }
            // Re-apply layout classes saved in style
            if (section.style && section.style.classes && section.style.classes.length) {
                const innerTag = tempDiv.querySelector('[data-component] > *') || tempDiv.querySelector('[data-component]');
                if (innerTag) innerTag.className = section.style.classes.join(' ');
            }
            inner.appendChild(tempDiv.firstElementChild || tempDiv);
            break;
        }
    }
}

// ─── Selection & property panel ──────────────────────────────────────────────
function selectSection(id) {
    activeSectionId = id;

    // Highlight
    document.querySelectorAll('#canvas-content .component-section').forEach(el => {
        el.classList.toggle('component-selected', el.dataset.sectionId === id);
    });

    const section = page.sections.find(s => s.id === id);
    if (!section) return;

    // Show right panel
    document.getElementById('no-selection-state').classList.add('hidden');
    document.getElementById('selection-controls').classList.remove('hidden');
    document.getElementById('selected-component-type').innerText = section.type.toUpperCase();

    populatePropertiesPanel(section);
}

function populatePropertiesPanel(section) {
    const p = section.props || {};

    // Basic text props
    const headingInput = document.getElementById('prop-heading-text');
    const paraInput    = document.getElementById('prop-paragraph-text');
    if (headingInput) headingInput.value = p.heading || '';
    if (paraInput)    paraInput.value    = p.text    || '';

    // Section-type-specific panels
    const navbarSection  = document.getElementById('navbar-custom-section');
    const footerSection  = document.getElementById('footer-custom-section');
    const textSection    = document.getElementById('prop-group-text');
    const rawHtmlSection = document.getElementById('low-code-html-section');

    // Hide all first, show relevant
    [navbarSection, footerSection, textSection, rawHtmlSection].forEach(el => {
        if (el) el.classList.add('hidden');
    });

    if (section.type === 'navbar') {
        if (navbarSection) navbarSection.classList.remove('hidden');
        // Load into customizer — find the wrapper DOM node
        const wrapperEl = document.querySelector(`[data-section-id="${section.id}"]`);
        if (wrapperEl) loadNavbarCustomizerFields(wrapperEl);

    } else if (section.type === 'footer') {
        if (footerSection) footerSection.classList.remove('hidden');
        const wrapperEl = document.querySelector(`[data-section-id="${section.id}"]`);
        if (wrapperEl) loadFooterCustomizerFields(wrapperEl);

    } else if (section.type === 'html_raw') {
        if (rawHtmlSection) rawHtmlSection.classList.remove('hidden');
        const wrapperEl  = document.querySelector(`[data-section-id="${section.id}"]`);
        const container  = wrapperEl ? wrapperEl.querySelector('.custom-html-container') : null;
        const rawEl      = document.getElementById('prop-raw-html');
        if (rawEl) rawEl.value = container ? container.innerHTML : (p.rawHtml || '');

    } else {
        if (textSection) textSection.classList.remove('hidden');
    }
}

// ─── Property panel input listeners ──────────────────────────────────────────
function wirePropertyPanelListeners() {
    const headingInput = document.getElementById('prop-heading-text');
    const paraInput    = document.getElementById('prop-paragraph-text');

    if (headingInput) {
        headingInput.addEventListener('input', e => {
            const section = getActiveSection();
            if (!section) return;
            section.props.heading = e.target.value;
            rerenderSection(section.id);
        });
    }

    if (paraInput) {
        paraInput.addEventListener('input', e => {
            const section = getActiveSection();
            if (!section) return;
            section.props.text = e.target.value;
            rerenderSection(section.id);
        });
    }
}

function getActiveSection() {
    return page.sections.find(s => s.id === activeSectionId) || null;
}

/**
 * Re-render only one section node in-place (no full page repaint).
 */
function rerenderSection(id) {
    const section = page.sections.find(s => s.id === id);
    if (!section) return;
    const existing = document.querySelector(`[data-section-id="${id}"]`);
    if (!existing) return;
    const newNode = renderSection(section);
    if (activeSectionId === id) newNode.classList.add('component-selected');
    existing.replaceWith(newNode);
}

// ─── Drag-drop: add new section from shelf ───────────────────────────────────
function handleCanvasDrop(e) {
    e.preventDefault();
    const container = document.getElementById('canvas-container');
    if (container) container.classList.remove('canvas-dragover');

    const componentId = e.dataTransfer.getData('text/plain');
    const compDef     = UI_COMPONENTS.find(c => c.id === componentId);
    if (!compDef) return;

    const newSection = {
        id:    generateId(),
        type:  componentId,
        props: {
            heading:   '',
            text:      '',
            brandText: 'WEBCRAFT',
            logoUrl:   '',
            copyright: '',
            links:     [],
            rawHtml:   '',
        },
        style: { classes: [] }
    };

    page.sections.push(newSection);
    renderPage(page);
    selectSection(newSection.id);
    showToast('Component Added', `${compDef.name} added to canvas.`);
    saveProject(true);
}

// ─── Section controls ────────────────────────────────────────────────────────
function moveSectionUp(id) {
    const idx = page.sections.findIndex(s => s.id === id);
    if (idx <= 0) return;
    [page.sections[idx - 1], page.sections[idx]] = [page.sections[idx], page.sections[idx - 1]];
    renderPage(page);
    saveProject(true);
}

function moveSectionDown(id) {
    const idx = page.sections.findIndex(s => s.id === id);
    if (idx < 0 || idx >= page.sections.length - 1) return;
    [page.sections[idx], page.sections[idx + 1]] = [page.sections[idx + 1], page.sections[idx]];
    renderPage(page);
    saveProject(true);
}

function duplicateSection(id) {
    const idx = page.sections.findIndex(s => s.id === id);
    if (idx < 0) return;
    const clone = JSON.parse(JSON.stringify(page.sections[idx]));
    clone.id = generateId();
    page.sections.splice(idx + 1, 0, clone);
    renderPage(page);
    selectSection(clone.id);
    saveProject(true);
}

function deleteSection(id) {
    page.sections = page.sections.filter(s => s.id !== id);
    if (activeSectionId === id) {
        activeSectionId = null;
        const noSel = document.getElementById('no-selection-state');
        const selCtrl = document.getElementById('selection-controls');
        if (noSel)  noSel.classList.remove('hidden');
        if (selCtrl) selCtrl.classList.add('hidden');
    }
    renderPage(page);
    showToast('Removed', 'Section removed from canvas.');
    saveProject(true);
}

// Keep legacy alias so any HTML onclick="deleteSelectedComponent()" still works
function deleteSelectedComponent() {
    if (activeSectionId) deleteSection(activeSectionId);
}

// ─── applyRawHtml (property panel button) ────────────────────────────────────
function applyRawHtml() {
    const section = getActiveSection();
    if (!section || section.type !== 'html_raw') return;
    const rawCode = document.getElementById('prop-raw-html')?.value || '';
    section.props.rawHtml = rawCode;
    rerenderSection(section.id);
    showToast('HTML Configured', 'Raw layout inserted.');
    saveProject(true);
}

// ─── Serialisation ────────────────────────────────────────────────────────────
/**
 * Serialise `page` back to the DB format expected by api.php (blocks array).
 * This keeps save/publish/export working without changes to the backend.
 */
function serializeCanvasContent() {
    const blocks = page.sections.map(section => {
        const p = section.props || {};
        return {
            componentId:   section.type,
            headingText:   p.heading   || '',
            paragraphText: p.text      || '',
            classes:       (section.style && section.style.classes) || [],
            raw_html:      p.rawHtml   || '',
            brandText:     p.brandText || '',
            logoImg:       p.logoUrl   || '',
            copyright:     p.copyright || '',
            links:         p.links     || [],
        };
    });

    const cssArea = document.getElementById('project-custom-css');
    const jsArea  = document.getElementById('project-custom-js');

    return JSON.stringify({
        sections:   page.sections,   // new canonical format
        blocks:     blocks,          // legacy compat for render.php / export
        custom_css: cssArea ? cssArea.value : '',
        custom_js:  jsArea  ? jsArea.value  : '',
    });
}

/**
 * Compile clean HTML for publish / export (no builder chrome).
 */
function compileFullPageHtml() {
    return page.sections.map(section => {
        const tempWrap = document.createElement('div');
        buildSectionInner(tempWrap, section);
        return tempWrap.innerHTML;
    }).join('\n');
}

// ─── Save / Publish / Export ──────────────────────────────────────────────────
function saveProject(silent = false) {
    const contentJson = serializeCanvasContent();
    const payload = {
        project_id:   PROJECT_ID,
        name:         document.title.replace('WebCraft Builder - Editing: ', ''),
        content_json: contentJson,
        csrf_token:   CSRF_TOKEN,
    };

    if (!silent) showToast('Saving...', 'Syncing workspace with database.');

    return fetch('api.php?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
        body: JSON.stringify(payload),
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (!silent) showToast('Draft Saved', 'Workspace saved successfully.');
            return data;
        } else {
            showToast('Save Error', data.error || 'Server rejected request.', 'bg-red-900 border-red-500 text-white');
            throw new Error(data.error);
        }
    })
    .catch(err => {
        showToast('Save Error', err.message || 'Check network.', 'bg-red-900 border-red-500 text-white');
    });
}

function publishProject() {
    saveProject(true).then(() => {
        showToast('Compiling...', 'Assembling production HTML.');
        const payload = {
            project_id:    PROJECT_ID,
            published_html: compileFullPageHtml(),
            csrf_token:    CSRF_TOKEN,
        };
        return fetch('api.php?action=publish', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: JSON.stringify(payload),
        });
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('status-badge').innerText = 'PUBLISHED';
            showToast('Site Published!', 'Click to open live preview.', 'bg-slate-900 border-teal-400 text-teal-400', () => window.open(data.url, '_blank'));
        } else {
            showToast('Publish Failed', data.error || 'Check config.', 'bg-red-900 border-red-500 text-white');
        }
    })
    .catch(err => showToast('Error', err.message, 'bg-red-900 border-red-500 text-white'));
}

function exportProjectZip() {
    showToast('Exporting...', 'Packaging static HTML into ZIP.');
    window.location.href = `api.php?action=export&project_id=${PROJECT_ID}`;
}

// ─── Canvas view size ─────────────────────────────────────────────────────────
function setCanvasView(size) {
    const container = document.getElementById('canvas-container');
    const bDesktop  = document.getElementById('view-desktop');
    const bTablet   = document.getElementById('view-tablet');
    const bMobile   = document.getElementById('view-mobile');
    const base      = 'px-3 py-1.5 rounded text-xs font-bold transition duration-200 text-slate-400 hover:text-white';
    const active    = 'px-3 py-1.5 rounded text-xs font-bold transition duration-200 bg-slate-800 text-teal-400';

    bDesktop.className = bTablet.className = bMobile.className = base;

    if (size === 'desktop') { container.style.width = '100%';    bDesktop.className = active; }
    if (size === 'tablet')  { container.style.width = '768px';   bTablet.className  = active; }
    if (size === 'mobile')  { container.style.width = '375px';   bMobile.className  = active; }
}

// ─── Control panel tabs ───────────────────────────────────────────────────────
function switchControlPanelTab(tab) {
    const pPanel  = document.getElementById('property-panel');
    const sPanel  = document.getElementById('settings-panel');
    const btnProp = document.getElementById('control-tab-btn-properties');
    const btnSet  = document.getElementById('control-tab-btn-settings');
    const on  = 'flex-1 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider border-b-2 border-teal-500 text-teal-400';
    const off = 'flex-1 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider border-b-2 border-transparent text-slate-400 hover:text-white';

    if (tab === 'properties') {
        pPanel.classList.remove('hidden'); sPanel.classList.add('hidden');
        btnProp.className = on; btnSet.className = off;
    } else {
        pPanel.classList.add('hidden');  sPanel.classList.remove('hidden');
        btnProp.className = off; btnSet.className = on;
    }
}

// ─── Left-shelf: build component cards ───────────────────────────────────────
function initializeComponentsShelf() {
    const shelf = document.getElementById('components-shelf');
    if (!shelf) return;

    const categories = {};
    UI_COMPONENTS.forEach(comp => {
        if (!categories[comp.category]) categories[comp.category] = [];
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

        const grid = document.createElement('div');
        grid.className = 'grid grid-cols-2 gap-2';

        items.forEach(comp => {
            const btn = document.createElement('div');
            btn.className = 'bg-slate-950 hover:bg-slate-800 border border-slate-800 rounded p-3 text-center cursor-grab transition-all duration-200 select-none';
            btn.setAttribute('draggable', 'true');
            btn.setAttribute('data-component-id', comp.id);
            btn.innerHTML = `
                <div class="text-teal-400 text-lg mb-1.5"><i class="${comp.icon}"></i></div>
                <div class="text-[10px] text-slate-300 font-medium truncate">${comp.name}</div>
            `;
            btn.addEventListener('dragstart', e => {
                e.dataTransfer.setData('text/plain', comp.id);
                e.dataTransfer.effectAllowed = 'copy';
                btn.classList.add('opacity-50');
            });
            btn.addEventListener('dragend', () => btn.classList.remove('opacity-50'));
            grid.appendChild(btn);
        });

        catContainer.appendChild(grid);
        shelf.appendChild(catContainer);
    }
}

// ─── Global click-out deselect ────────────────────────────────────────────────
function initializeGlobalCanvasListeners() {
    document.addEventListener('click', e => {
        const sidebarRight = document.querySelector('aside:last-of-type');
        const sidebarLeft  = document.querySelector('aside:first-of-type');
        const activeEl     = activeSectionId
            ? document.querySelector(`[data-section-id="${activeSectionId}"]`)
            : null;

        if (
            activeSectionId &&
            activeEl && !activeEl.contains(e.target) &&
            (!sidebarRight || !sidebarRight.contains(e.target)) &&
            (!sidebarLeft  || !sidebarLeft.contains(e.target))
        ) {
            activeEl.classList.remove('component-selected');
            activeSectionId = null;
            document.getElementById('no-selection-state')?.classList.remove('hidden');
            document.getElementById('selection-controls')?.classList.add('hidden');
        }
    });
}

// ─── Navbar customizer (unchanged logic, just called from new pipeline) ───────
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
        linksContainer.querySelectorAll('a').forEach((lnk, idx) => {
            appendNavbarLinkRowElement(lnk.innerText.trim(), lnk.getAttribute('href'), idx);
        });
    }
}

function appendNavbarLinkRowElement(text, url, idx) {
    const list = document.getElementById('navbar-links-list');
    const div = document.createElement('div');
    div.className = 'flex gap-1 items-center navbar-link-row bg-slate-950 p-2 rounded border border-slate-850';
    div.innerHTML = `
        <input type="text" placeholder="Tab Text" value="${text}" oninput="updateNavbarFromFields()" class="w-1/2 bg-slate-900 border border-slate-800 rounded px-2 py-1 text-[11px] text-white focus:outline-none focus:border-teal-500">
        <select onchange="updateNavbarFromFields()" class="w-1/2 bg-slate-900 border border-slate-800 rounded px-2 py-1 text-[11px] text-white focus:outline-none focus:border-teal-500">
            <option value="#home"     ${url==='#home'?'selected':''}>Home Section</option>
            <option value="#features" ${url==='#features'?'selected':''}>Features Section</option>
            <option value="#pricing"  ${url==='#pricing'?'selected':''}>Pricing Section</option>
            <option value="#contact"  ${url==='#contact'?'selected':''}>Contact Section</option>
            <option value="index.php" ${url==='index.php'?'selected':''}>Index Page</option>
            <option value="admin.php" ${url==='admin.php'?'selected':''}>Admin Page</option>
            <option value="${(!url.startsWith('#')&&url!=='index.php'&&url!=='admin.php')?url:''}" ${(!url.startsWith('#')&&url!=='index.php'&&url!=='admin.php')?'selected':''} class="custom-url-option">Custom URL...</option>
        </select>
        <button onclick="this.parentNode.remove(); updateNavbarFromFields();" class="text-red-400 hover:text-red-300 p-1" title="Delete Tab">
            <i class="fas fa-trash-alt text-[10px]"></i>
        </button>
    `;
    const select = div.querySelector('select');
    select.addEventListener('change', e => {
        if (e.target.value === '') {
            const customUrl = prompt('Enter custom URL:');
            if (customUrl) {
                let opt = select.querySelector('.custom-url-option');
                if (!opt) { opt = document.createElement('option'); opt.className = 'custom-url-option'; select.appendChild(opt); }
                opt.value = customUrl; opt.innerText = customUrl; opt.selected = true;
                updateNavbarFromFields();
            } else { select.selectedIndex = 0; }
        }
    });
    list.appendChild(div);
}

function addNavbarLinkRow() { appendNavbarLinkRowElement('New Tab', '#home', Date.now()); updateNavbarFromFields(); }

function updateNavbarFromFields() {
    if (!activeSectionId) return;
    const wrapperEl = document.querySelector(`[data-section-id="${activeSectionId}"]`);
    const nav = wrapperEl ? wrapperEl.querySelector('[data-component="navbar"]') : null;
    if (!nav) return;

    const bText     = document.getElementById('navbar-brand-text').value.trim();
    const logoImgUrl = document.getElementById('navbar-logo-img').value.trim();
    const logoContainer = nav.querySelector('div:first-of-type');
    if (logoContainer) {
        logoContainer.innerHTML = logoImgUrl
            ? `<img src="${logoImgUrl}" class="h-8 max-w-[120px] object-contain" alt="Logo">`
            : `<span class="text-xl font-extrabold tracking-wider text-teal-400">${bText||'WEBCRAFT'}</span>`;
    }

    const rows = document.getElementById('navbar-links-list').querySelectorAll('.navbar-link-row');
    const linksContainer = nav.querySelector('.hidden.md\\:flex');
    if (linksContainer) {
        linksContainer.innerHTML = '';
        rows.forEach(row => {
            const a = document.createElement('a');
            a.className = 'hover:text-teal-300 transition duration-300';
            a.setAttribute('href', row.querySelector('select').value);
            a.innerText = row.querySelector('input').value.trim();
            linksContainer.appendChild(a);
        });
    }

    // Sync back to page JSON
    const section = getActiveSection();
    if (section) {
        section.props.brandText = bText;
        section.props.logoUrl   = logoImgUrl;
        section.props.links     = Array.from(rows).map(row => ({
            text: row.querySelector('input').value.trim(),
            url:  row.querySelector('select').value,
        }));
    }
    saveProject(true);
}

function updateNavbarLogoText() { updateNavbarFromFields(); }
function updateNavbarLogoImage() { updateNavbarFromFields(); }

function reconstructNavbarComponent(tempDiv, block) {
    const nav = tempDiv.querySelector('[data-component="navbar"]');
    if (!nav) return;
    const logoContainer = nav.querySelector('div:first-of-type');
    if (logoContainer) {
        if (block.logoImg) {
            logoContainer.innerHTML = `<img src="${block.logoImg}" class="h-8 max-w-[120px] object-contain" alt="Logo">`;
        } else {
            logoContainer.innerHTML = `<span class="text-xl font-extrabold tracking-wider text-teal-400">${block.brandText||'WEBCRAFT'}</span>`;
        }
    }
    const linksContainer = nav.querySelector('.hidden.md\\:flex');
    if (linksContainer) {
        linksContainer.innerHTML = '';
        const navLinks = block.links && block.links.length ? block.links : [
            {text:'Home',url:'#home'},{text:'Features',url:'#features'},
            {text:'Pricing',url:'#pricing'},{text:'Contact',url:'#contact'},
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

// ─── Footer customizer ────────────────────────────────────────────────────────
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
        linksContainer.querySelectorAll('a').forEach((lnk, idx) => {
            appendFooterLinkRowElement(lnk.innerText.trim(), lnk.getAttribute('href'), idx);
        });
    }
}

function appendFooterLinkRowElement(text, url, idx) {
    const list = document.getElementById('footer-links-list');
    const div = document.createElement('div');
    div.className = 'flex gap-1 items-center footer-link-row bg-slate-950 p-2 rounded border border-slate-850';
    div.innerHTML = `
        <input type="text" placeholder="Link Text" value="${text}" oninput="updateFooterFromFields()" class="w-1/2 bg-slate-900 border border-slate-800 rounded px-2 py-1 text-[11px] text-white focus:outline-none focus:border-teal-500">
        <select onchange="updateFooterFromFields()" class="w-1/2 bg-slate-900 border border-slate-800 rounded px-2 py-1 text-[11px] text-white focus:outline-none focus:border-teal-500">
            <option value="#home"     ${url==='#home'?'selected':''}>Home Section</option>
            <option value="#features" ${url==='#features'?'selected':''}>Features Section</option>
            <option value="#pricing"  ${url==='#pricing'?'selected':''}>Pricing Section</option>
            <option value="#contact"  ${url==='#contact'?'selected':''}>Contact Section</option>
            <option value="index.php" ${url==='index.php'?'selected':''}>Index Page</option>
            <option value="admin.php" ${url==='admin.php'?'selected':''}>Admin Page</option>
            <option value="${(!url.startsWith('#')&&url!=='index.php'&&url!=='admin.php')?url:''}" ${(!url.startsWith('#')&&url!=='index.php'&&url!=='admin.php')?'selected':''} class="custom-footer-url-option">Custom URL...</option>
        </select>
        <button onclick="this.parentNode.remove(); updateFooterFromFields();" class="text-red-400 hover:text-red-300 p-1" title="Delete Link">
            <i class="fas fa-trash-alt text-[10px]"></i>
        </button>
    `;
    const select = div.querySelector('select');
    select.addEventListener('change', e => {
        if (e.target.value === '') {
            const customUrl = prompt('Enter custom URL:');
            if (customUrl) {
                let opt = select.querySelector('.custom-footer-url-option');
                if (!opt) { opt = document.createElement('option'); opt.className = 'custom-footer-url-option'; select.appendChild(opt); }
                opt.value = customUrl; opt.innerText = customUrl; opt.selected = true;
                updateFooterFromFields();
            } else { select.selectedIndex = 0; }
        }
    });
    list.appendChild(div);
}

function addFooterLinkRow() { appendFooterLinkRowElement('New Link', '#home', Date.now()); updateFooterFromFields(); }

function updateFooterFromFields() {
    if (!activeSectionId) return;
    const wrapperEl = document.querySelector(`[data-section-id="${activeSectionId}"]`);
    const foot = wrapperEl ? wrapperEl.querySelector('footer') : null;
    if (!foot) return;

    const bText      = document.getElementById('footer-brand-text').value.trim();
    const logoImgUrl = document.getElementById('footer-logo-img').value.trim();
    const logoContainer = foot.querySelector('div:first-of-type div:first-of-type') || foot.querySelector('div:first-of-type img');

    if (logoContainer) {
        if (logoImgUrl) {
            logoContainer.tagName === 'IMG'
                ? logoContainer.setAttribute('src', logoImgUrl)
                : (logoContainer.outerHTML = `<img src="${logoImgUrl}" class="h-8 max-w-[120px] object-contain" alt="Logo">`);
        } else {
            logoContainer.tagName === 'IMG'
                ? (logoContainer.outerHTML = `<div class="text-lg font-black text-white">${bText||'WEBCRAFT BUILDER'}</div>`)
                : (logoContainer.innerText = bText || 'WEBCRAFT BUILDER');
        }
    }

    const copyEl = foot.querySelector('.text-xs');
    const copyText = document.getElementById('footer-copyright').value.trim();
    if (copyEl) copyEl.innerText = copyText || `© ${new Date().getFullYear()} WebCraft. All rights reserved.`;

    const rows = document.getElementById('footer-links-list').querySelectorAll('.footer-link-row');
    const linksContainer = foot.querySelector('.flex.space-x-6');
    if (linksContainer) {
        linksContainer.innerHTML = '';
        rows.forEach(row => {
            const a = document.createElement('a');
            a.className = 'hover:text-white transition';
            a.setAttribute('href', row.querySelector('select').value);
            a.innerText = row.querySelector('input').value.trim();
            linksContainer.appendChild(a);
        });
    }

    // Sync back to page JSON
    const section = getActiveSection();
    if (section) {
        section.props.brandText = bText;
        section.props.logoUrl   = logoImgUrl;
        section.props.copyright = copyText;
        section.props.links     = Array.from(rows).map(row => ({
            text: row.querySelector('input').value.trim(),
            url:  row.querySelector('select').value,
        }));
    }
    saveProject(true);
}

function updateFooterBrandText()  { updateFooterFromFields(); }
function updateFooterCopyright()  { updateFooterFromFields(); }
function updateFooterLogoImage()  { updateFooterFromFields(); }

function reconstructFooterComponent(tempDiv, block) {
    const foot = tempDiv.querySelector('footer');
    if (!foot) return;
    const brandContainer = foot.querySelector('div:first-of-type div:first-of-type') || foot.querySelector('div:first-of-type img');
    if (brandContainer) {
        if (block.logoImg) {
            brandContainer.outerHTML = `<img src="${block.logoImg}" class="h-8 max-w-[120px] object-contain" alt="Logo">`;
        } else {
            brandContainer.outerHTML = `<div class="text-lg font-black text-white">${block.brandText||'WEBCRAFT BUILDER'}</div>`;
        }
    }
    const copyEl = foot.querySelector('.text-xs');
    if (copyEl && block.copyright) copyEl.innerText = block.copyright;

    const linksContainer = foot.querySelector('.flex.space-x-6');
    if (linksContainer) {
        linksContainer.innerHTML = '';
        const footLinks = block.links && block.links.length ? block.links : [
            {text:'Privacy Policy',url:'#'},{text:'Terms of Use',url:'#'},{text:'Support',url:'#'},
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

// ─── updateActiveElementClass (alignment/padding buttons in property panel) ───
function updateActiveElementClass(allClasses, newClass) {
    const section = getActiveSection();
    if (!section) return;
    if (!section.style) section.style = { classes: [] };
    section.style.classes = section.style.classes.filter(c => !allClasses.includes(c));
    if (newClass) section.style.classes.push(newClass);
    rerenderSection(section.id);
    saveProject(true);
}

// ─── Toast notifications ──────────────────────────────────────────────────────
function showToast(title, description, customClass = 'bg-slate-900 border-teal-500 text-white', clickHandler = null) {
    const toast = document.getElementById('notification-toast');
    const tTitle = document.getElementById('notification-title');
    const tDesc  = document.getElementById('notification-desc');
    if (!toast) return;

    toast.className = `fixed bottom-6 right-6 border rounded-lg p-4 shadow-2xl flex items-center gap-3 max-w-sm transform transition-all duration-300 z-50 cursor-pointer ${customClass}`;
    tTitle.innerText = title;
    tDesc.innerText  = description;
    toast.classList.remove('translate-y-24', 'opacity-0');
    toast.classList.add('translate-y-0', 'opacity-100');
    toast.onclick = clickHandler || null;

    setTimeout(() => {
        toast.classList.remove('translate-y-0', 'opacity-100');
        toast.classList.add('translate-y-24', 'opacity-0');
    }, 4500);
}

// ─── Utility ──────────────────────────────────────────────────────────────────
function generateId() {
    return 'sec-' + Date.now() + '-' + Math.random().toString(36).slice(2, 7);
}
