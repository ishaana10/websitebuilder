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
    const data = [];

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

        data.push({
            componentId: componentId,
            headingText: hEl ? hEl.innerText.trim() : '',
            paragraphText: pEl ? pEl.innerText.trim() : '',
            classes: layoutClasses,
            raw_html: raw_html
        });
    });

    return JSON.stringify(data);
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
        const blocks = JSON.parse(LOADED_CONTENT_STATE);
        if (!blocks || blocks.length === 0) return;

        const emptyState = document.getElementById('canvas-empty-state');
        if (emptyState) emptyState.classList.add('hidden');

        blocks.forEach(block => {
            const compDef = UI_COMPONENTS.find(c => c.id === block.componentId);
            if (!compDef) return;

            // Dynamically instantiate template
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = compDef.html;

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
