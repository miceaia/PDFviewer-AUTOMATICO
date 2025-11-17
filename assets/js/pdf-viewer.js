(function($) {
    'use strict';

    const DEFAULT_VIEWER_SETTINGS = {
        default_zoom: 1.5,
        min_zoom: 0.5,
        max_zoom: 3.0,
        highlight_opacity: 0.4,
        highlight_colors: {
            yellow: '#ffff00',
            green: '#00ff00',
            blue: '#00bfff',
            pink: '#ff69b4'
        },
        theme_colors: {
            base: '#1abc9c',
            base_dark: '#16a085',
            base_contrast: '#ffffff'
        },
        watermark_enabled: 1,
        watermark_text: 'Usuario: {user_name} · Fecha: {date}',
        watermark_color: '#000000',
        watermark_opacity: 0.15,
        watermark_font_size: 14,
        watermark_rotation: -30,
        copy_protection: 1
    };

    // Configurar PDF.js worker
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
    }

    class MiceaPDFViewer {
        constructor(container) {
            this.container = $(container);
            this.pdfUrl = this.container.data('pdf-url');
            this.pdfId = this.container.data('pdf-id') || 'default';
            this.canvas = this.container.find('.spv-pdf-canvas')[0];
            this.ctx = this.canvas.getContext('2d');
            this.canvasContainer = this.container.find('.spv-canvas-container');

            const perViewerSettings = this.container.data('viewer-settings') || this.container.data('viewerSettings') || {};
            const globalSettings = (window.spvViewerSettings && window.spvViewerSettings.defaults) || {};
            this.preferences = $.extend(true, {}, DEFAULT_VIEWER_SETTINGS, globalSettings, perViewerSettings);

            this.applyThemeColors();

            // PDF.js
            this.pdfDoc = null;
            this.currentPage = 1;
            this.totalPages = 0;
            this.scale = this.preferences.default_zoom;
            this.rendering = false;
            this.maxScale = this.preferences.max_zoom;
            this.minScale = this.preferences.min_zoom;
            this.highlightOpacity = this.preferences.highlight_opacity;
            this.watermarkEnabled = !!this.preferences.watermark_enabled;

            // Usuario
            const userData = this.container.data('user-info') || this.container.data('userInfo') || {};
            const siteInfo = this.container.data('site-info') || this.container.data('siteInfo') || {};
            this.userId = userData.id || 'anonymous';
            this.userName = userData.name || 'Usuario';
            this.userEmail = userData.email || '';
            this.userRole = userData.role || '';
            this.userLogin = userData.login || '';
            this.siteInfo = siteInfo;

            // Highlights system
            this.highlights = {}; // { highlightId: Highlight }
            this.highlightsByPage = {}; // { page: [highlightIds] }
            this.currentColor = null;
            this.eraserMode = false;

            // Undo/Redo stacks
            this.undoStack = [];
            this.redoStack = [];

            // Persistencia
            this.isDirty = false;
            this.lastSavedAt = null;
            this.autosaveTimer = null;

            // Layers
            this.textLayer = null;
            this.highlightsLayer = null;

            // Fullscreen
            this.isFullscreen = false;

            this.init();
        }

        init() {
            this.applyViewerPreferences();
            this.createLayers();
            this.bindEvents();
            this.bindKeyboardShortcuts();
            this.loadPDF();
            this.loadUserAnnotations();
            if (this.preferences.copy_protection) {
                this.preventCopyPaste();
            }
        }

        applyViewerPreferences() {
            this.scale = this.preferences.default_zoom;
            this.maxScale = this.preferences.max_zoom;
            this.minScale = this.preferences.min_zoom;
            this.highlightOpacity = this.preferences.highlight_opacity;
            this.watermarkEnabled = !!this.preferences.watermark_enabled;
            this.updateZoomLabel();
        }

        applyThemeColors() {
            const colors = this.preferences.theme_colors || {};
            const root = document.documentElement;

            Object.keys(colors).forEach((key) => {
                const value = colors[key];
                if (!value) {
                    return;
                }
                const varName = `--spv-theme-${key.replace(/_/g, '-')}`;
                root.style.setProperty(varName, value);
            });

            if (colors.base) {
                const rgb = this.hexToRgb(colors.base);
                if (rgb) {
                    root.style.setProperty('--spv-theme-base-rgb', `${rgb.r}, ${rgb.g}, ${rgb.b}`);
                }
            }
        }

        hexToRgb(hex) {
            if (typeof hex !== 'string') {
                return null;
            }

            let sanitized = hex.replace('#', '');
            if (sanitized.length === 3) {
                sanitized = sanitized.split('').map(char => char + char).join('');
            }

            if (sanitized.length !== 6) {
                return null;
            }

            const r = parseInt(sanitized.substring(0, 2), 16);
            const g = parseInt(sanitized.substring(2, 4), 16);
            const b = parseInt(sanitized.substring(4, 6), 16);

            if ([r, g, b].some(value => Number.isNaN(value))) {
                return null;
            }

            return { r, g, b };
        }

        createLayers() {
            const layersContainer = $('<div class="spv-layers-container"></div>').css({
                position: 'absolute',
                top: 0,
                left: 0,
                width: '100%',
                height: '100%',
                pointerEvents: 'none'
            });

            // Text layer para selección
            this.textLayer = $('<div class="spv-text-layer"></div>')[0];

            // SVG layer para highlights
            this.highlightsLayer = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            this.highlightsLayer.classList.add('spv-highlights-layer');
            this.highlightsLayer.style.width = '100%';
            this.highlightsLayer.style.height = '100%';

            layersContainer.append(this.highlightsLayer);
            layersContainer.append(this.textLayer);

            $(this.canvas).parent().css('position', 'relative').append(layersContainer);
        }

        async loadPDF() {
            try {
                this.showLoading();

                const loadingTask = pdfjsLib.getDocument({
                    url: this.pdfUrl,
                    withCredentials: false
                });

                this.pdfDoc = await loadingTask.promise;
                this.totalPages = this.pdfDoc.numPages;

                console.log('PDF cargado:', this.totalPages, 'páginas');

                this.updatePageInfo();
                this.hideLoading();

                await this.renderPage(this.currentPage);
                this.updateButtons();

            } catch (error) {
                console.error('Error cargando PDF:', error);
                this.showError('Error al cargar el PDF. Verifica que la URL sea correcta y el archivo sea accesible.');
            }
        }

        async renderPage(pageNum) {
            if (this.rendering || !this.pdfDoc) {
                return;
            }

            if (pageNum < 1 || pageNum > this.totalPages) {
                return;
            }

            this.rendering = true;
            this.showLoading();

            try {
                const page = await this.pdfDoc.getPage(pageNum);

                const originalViewport = page.getViewport({ scale: 1.0 });
                const containerWidth = this.canvasContainer.width() - 40;
                const containerScale = containerWidth / originalViewport.width;
                const effectiveScale = this.scale;

                const viewport = page.getViewport({ scale: effectiveScale });

                // Ajustar canvas
                this.canvas.width = viewport.width;
                this.canvas.height = viewport.height;
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

                // Renderizar PDF
                const renderContext = {
                    canvasContext: this.ctx,
                    viewport: viewport
                };

                await page.render(renderContext).promise;

                // Renderizar text layer
                await this.renderTextLayer(page, viewport);

                // Renderizar highlights de esta página
                this.renderHighlights(pageNum);

                // Agregar marca de agua
                this.addWatermarkToPage();

                this.currentPage = pageNum;
                this.updatePageInfo();
                this.updateButtons();

            } catch (error) {
                console.error('Error renderizando página:', error);
                this.showError('Error al renderizar la página ' + pageNum);
            } finally {
                this.rendering = false;
                this.hideLoading();
            }
        }

        async renderTextLayer(page, viewport) {
            // Limpiar text layer anterior
            $(this.textLayer).empty();

            try {
                const textContent = await page.getTextContent();

                // Configurar dimensiones del text layer
                $(this.textLayer).css({
                    width: viewport.width + 'px',
                    height: viewport.height + 'px'
                });

                // Renderizar texto
                pdfjsLib.renderTextLayer({
                    textContentSource: textContent,
                    container: this.textLayer,
                    viewport: viewport,
                    textDivs: []
                });

            } catch (error) {
                console.error('Error renderizando text layer:', error);
            }
        }

        renderHighlights(pageNum) {
            // Limpiar SVG
            while (this.highlightsLayer.firstChild) {
                this.highlightsLayer.removeChild(this.highlightsLayer.firstChild);
            }

            // Ajustar tamaño del SVG
            this.highlightsLayer.setAttribute('width', this.canvas.width);
            this.highlightsLayer.setAttribute('height', this.canvas.height);

            // Renderizar highlights de esta página
            const pageHighlights = this.highlightsByPage[pageNum] || [];

            pageHighlights.forEach(highlightId => {
                const highlight = this.highlights[highlightId];
                if (highlight) {
                    this.drawHighlight(highlight);
                }
            });
        }

        drawHighlight(highlight) {
            highlight.quads.forEach(quad => {
                const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                rect.setAttribute('x', quad.x);
                rect.setAttribute('y', quad.y);
                rect.setAttribute('width', quad.w);
                rect.setAttribute('height', quad.h);
                rect.setAttribute('fill', highlight.color);
                rect.setAttribute('opacity', String(this.highlightOpacity));
                rect.setAttribute('data-highlight-id', highlight.id);
                rect.classList.add('spv-highlight-rect');

                // Event para borrar
                $(rect).on('click', (e) => {
                    if (this.eraserMode) {
                        e.stopPropagation();
                        this.removeHighlight(highlight.id);
                    }
                });

                this.highlightsLayer.appendChild(rect);
            });
        }

        bindHighlightButtons() {
            const buttons = [
                { selector: '#hl-yellow', key: 'yellow' },
                { selector: '#hl-green', key: 'green' },
                { selector: '#hl-blue', key: 'blue' },
                { selector: '#hl-pink', key: 'pink' }
            ];

            buttons.forEach(btn => {
                const button = $(btn.selector, this.container);
                if (!button.length) {
                    return;
                }

                const color = button.data('color') || this.getHighlightColor(btn.key);
                const textColor = this.getAccessibleTextColor(color);

                button.css({
                    background: color,
                    color: textColor
                });

                button.off('click').on('click', () => this.selectHighlightColor(color, btn.key));
            });
        }

        getHighlightColor(key) {
            const palette = this.preferences.highlight_colors || {};
            if (palette[key]) {
                return palette[key];
            }
            return DEFAULT_VIEWER_SETTINGS.highlight_colors[key] || '#ffff00';
        }

        getAccessibleTextColor(color) {
            if (typeof color !== 'string' || color.indexOf('#') !== 0) {
                return '#333333';
            }

            const hex = color.replace('#', '');
            const r = parseInt(hex.substring(0, 2), 16) || 0;
            const g = parseInt(hex.substring(2, 4), 16) || 0;
            const b = parseInt(hex.substring(4, 6), 16) || 0;
            const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
            return luminance > 0.6 ? '#333333' : '#ffffff';
        }

        getWatermarkText() {
            const template = this.preferences.watermark_text || '';
            if (!template) {
                return '';
            }

            const fallbackOrigin = window.location && window.location.origin
                ? window.location.origin
                : `${window.location.protocol}//${window.location.host}`;

            const replacements = {
                '{user_name}': this.userName,
                '{user_email}': this.userEmail,
                '{user_id}': this.userId,
                '{user_role}': this.userRole,
                '{user_login}': this.userLogin,
                '{date}': new Date().toLocaleDateString(),
                '{pdf_id}': this.pdfId,
                '{site_name}': (this.siteInfo && this.siteInfo.name) || document.title || '',
                '{site_url}': (this.siteInfo && this.siteInfo.url) || fallbackOrigin || ''
            };

            let text = template;

            Object.keys(replacements).forEach(token => {
                const value = replacements[token] || '';
                text = text.replace(new RegExp(token, 'g'), value);
            });

            return text;
        }

        addWatermarkToPage() {
            if (!this.watermarkEnabled) {
                return;
            }

            const watermarkText = this.getWatermarkText();

            if (!watermarkText) {
                return;
            }

            this.ctx.save();
            this.ctx.globalAlpha = this.preferences.watermark_opacity || 0.15;
            this.ctx.fillStyle = this.preferences.watermark_color || '#000000';
            const fontSize = this.preferences.watermark_font_size || 14;
            this.ctx.font = `${fontSize}px Arial`;

            const centerX = this.canvas.width / 2;
            const centerY = this.canvas.height / 2;

            this.ctx.translate(centerX, centerY);
            const rotationRadians = (this.preferences.watermark_rotation || -30) * Math.PI / 180;
            this.ctx.rotate(rotationRadians);

            const textWidth = this.ctx.measureText(watermarkText).width;
            this.ctx.fillText(watermarkText, -textWidth / 2, 0);
            this.ctx.fillText(watermarkText, -textWidth / 2, -this.canvas.height / 3);
            this.ctx.fillText(watermarkText, -textWidth / 2, this.canvas.height / 3);

            this.ctx.restore();
        }

        bindEvents() {
            const self = this;

            // Navegación
            $('#btn-prev', this.container).on('click', () => this.prevPage());
            $('#btn-next', this.container).on('click', () => this.nextPage());

            // Zoom
            $('#btn-zoom-in', this.container).on('click', () => this.zoomIn());
            $('#btn-zoom-out', this.container).on('click', () => this.zoomOut());

            this.bindHighlightButtons();

            // Borrador
            $('#hl-erase', this.container).on('click', () => this.toggleEraserMode());

            // Undo/Redo
            $('#btn-undo', this.container).on('click', () => this.undo());
            $('#btn-redo', this.container).on('click', () => this.redo());

            // Pantalla completa
            $('#btn-fullscreen', this.container).on('click', () => this.toggleFullscreen());

            // Guardar
            $('#btn-save', this.container).on('click', () => this.saveAnnotations(true));

            // Selección de texto
            $(this.textLayer).on('mouseup', () => {
                setTimeout(() => this.handleTextSelection(), 10);
            });

            // Responsive
            let resizeTimer;
            $(window).on('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    if (this.pdfDoc && !this.rendering) {
                        this.renderPage(this.currentPage);
                    }
                }, 250);
            });

            // Fullscreen change
            $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange msfullscreenchange', () => {
                this.isFullscreen = !!(document.fullscreenElement || document.webkitFullscreenElement ||
                                      document.mozFullScreenElement || document.msFullscreenElement);
                this.updateFullscreenButton();
            });

            // Protección
            $(this.canvas).on('contextmenu dragstart', (e) => {
                e.preventDefault();
                return false;
            });
        }

        bindKeyboardShortcuts() {
            $(document).on('keydown', (e) => {
                // Solo si el visor está visible
                if (!this.container.is(':visible')) return;

                // Undo: Ctrl+Z o Cmd+Z
                if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
                    e.preventDefault();
                    this.undo();
                    return false;
                }

                // Redo: Ctrl+Y o Cmd+Shift+Z
                if (((e.ctrlKey || e.metaKey) && e.key === 'y') ||
                    ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'z')) {
                    e.preventDefault();
                    this.redo();
                    return false;
                }

                // Navegación con flechas
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    this.prevPage();
                    return false;
                }

                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    this.nextPage();
                    return false;
                }

                // Escape para salir de fullscreen
                if (e.key === 'Escape' && this.isFullscreen) {
                    // El navegador maneja esto automáticamente
                }

                // Prevenir Ctrl+S, Ctrl+P, Ctrl+C
                if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'p' || e.key === 'c')) {
                    e.preventDefault();
                    return false;
                }
            });
        }

        handleTextSelection() {
            const selection = window.getSelection();

            if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
                return;
            }

            // Verificar que la selección está dentro del textLayer
            const range = selection.getRangeAt(0);
            if (!$(range.commonAncestorContainer).closest(this.textLayer).length) {
                return;
            }

            // Si hay un color seleccionado, crear highlight
            if (this.currentColor && !this.eraserMode) {
                this.createHighlightFromSelection(selection);
                selection.removeAllRanges();
            }
        }

        createHighlightFromSelection(selection) {
            const range = selection.getRangeAt(0);
            const rects = range.getClientRects();

            if (rects.length === 0) return;

            const canvasRect = this.canvas.getBoundingClientRect();
            const quads = [];

            // Convertir rects de DOM a coordenadas del canvas
            for (let i = 0; i < rects.length; i++) {
                const rect = rects[i];

                const quad = {
                    x: rect.left - canvasRect.left,
                    y: rect.top - canvasRect.top,
                    w: rect.width,
                    h: rect.height,
                    page: this.currentPage,
                    scale: this.scale
                };

                // Validar que está dentro del canvas
                if (quad.x >= 0 && quad.y >= 0 &&
                    quad.x + quad.w <= this.canvas.width &&
                    quad.y + quad.h <= this.canvas.height) {
                    quads.push(quad);
                }
            }

            if (quads.length === 0) return;

            // Crear highlight
            const highlight = {
                id: 'hl_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                page: this.currentPage,
                color: this.currentColor,
                quads: quads,
                createdAt: Date.now(),
                createdBy: this.userId
            };

            this.addHighlight(highlight);
        }

        addHighlight(highlight) {
            // Agregar a colección
            this.highlights[highlight.id] = highlight;

            if (!this.highlightsByPage[highlight.page]) {
                this.highlightsByPage[highlight.page] = [];
            }
            this.highlightsByPage[highlight.page].push(highlight.id);

            // Agregar a undo stack
            this.pushAction({
                type: 'ADD_HIGHLIGHT',
                payload: highlight
            });

            // Re-renderizar highlights
            this.renderHighlights(this.currentPage);

            // Marcar como dirty y autosave
            this.markDirty();
        }

        removeHighlight(highlightId) {
            const highlight = this.highlights[highlightId];
            if (!highlight) return;

            // Remover de colecciones
            delete this.highlights[highlightId];

            const pageHighlights = this.highlightsByPage[highlight.page];
            if (pageHighlights) {
                const index = pageHighlights.indexOf(highlightId);
                if (index > -1) {
                    pageHighlights.splice(index, 1);
                }
            }

            // Agregar a undo stack
            this.pushAction({
                type: 'REMOVE_HIGHLIGHT',
                payload: { id: highlightId, highlight: highlight }
            });

            // Re-renderizar
            this.renderHighlights(this.currentPage);

            // Marcar como dirty
            this.markDirty();
        }

        pushAction(action) {
            this.undoStack.push(action);
            this.redoStack = []; // Limpiar redo stack
            this.updateUndoRedoButtons();
        }

        undo() {
            if (this.undoStack.length === 0) return;

            const action = this.undoStack.pop();
            this.redoStack.push(action);

            // Revertir acción
            if (action.type === 'ADD_HIGHLIGHT') {
                // Remover el highlight sin agregar a undo
                const hl = action.payload;
                delete this.highlights[hl.id];
                const pageHighlights = this.highlightsByPage[hl.page];
                if (pageHighlights) {
                    const index = pageHighlights.indexOf(hl.id);
                    if (index > -1) pageHighlights.splice(index, 1);
                }
            } else if (action.type === 'REMOVE_HIGHLIGHT') {
                // Re-agregar el highlight sin agregar a undo
                const hl = action.payload.highlight;
                this.highlights[hl.id] = hl;
                if (!this.highlightsByPage[hl.page]) {
                    this.highlightsByPage[hl.page] = [];
                }
                this.highlightsByPage[hl.page].push(hl.id);
            }

            this.renderHighlights(this.currentPage);
            this.updateUndoRedoButtons();
            this.markDirty();
        }

        redo() {
            if (this.redoStack.length === 0) return;

            const action = this.redoStack.pop();
            this.undoStack.push(action);

            // Re-aplicar acción
            if (action.type === 'ADD_HIGHLIGHT') {
                const hl = action.payload;
                this.highlights[hl.id] = hl;
                if (!this.highlightsByPage[hl.page]) {
                    this.highlightsByPage[hl.page] = [];
                }
                this.highlightsByPage[hl.page].push(hl.id);
            } else if (action.type === 'REMOVE_HIGHLIGHT') {
                const hl = action.payload.highlight;
                delete this.highlights[hl.id];
                const pageHighlights = this.highlightsByPage[hl.page];
                if (pageHighlights) {
                    const index = pageHighlights.indexOf(hl.id);
                    if (index > -1) pageHighlights.splice(index, 1);
                }
            }

            this.renderHighlights(this.currentPage);
            this.updateUndoRedoButtons();
            this.markDirty();
        }

        selectHighlightColor(color, colorName) {
            this.currentColor = color;
            this.eraserMode = false;

            // Actualizar UI
            $('.spv-color-btn', this.container).removeClass('active');
            $(`#hl-${colorName}`, this.container).addClass('active');
            $('#hl-erase', this.container).removeClass('active');

            this.container.removeClass('spv-eraser-mode');
        }

        toggleEraserMode() {
            this.eraserMode = !this.eraserMode;
            this.currentColor = null;

            // Actualizar UI
            if (this.eraserMode) {
                $('.spv-color-btn', this.container).removeClass('active');
                $('#hl-erase', this.container).addClass('active');
                this.container.addClass('spv-eraser-mode');
            } else {
                $('#hl-erase', this.container).removeClass('active');
                this.container.removeClass('spv-eraser-mode');
            }
        }

        prevPage() {
            if (this.currentPage > 1 && !this.rendering) {
                this.renderPage(this.currentPage - 1);
            }
        }

        nextPage() {
            if (this.currentPage < this.totalPages && !this.rendering) {
                this.renderPage(this.currentPage + 1);
            }
        }

        zoomIn() {
            this.scale = Math.min(this.scale + 0.1, this.maxScale);
            this.updateZoomLabel();
            this.renderPage(this.currentPage);
        }

        zoomOut() {
            this.scale = Math.max(this.scale - 0.1, this.minScale);
            this.updateZoomLabel();
            this.renderPage(this.currentPage);
        }

        updateZoomLabel() {
            const percent = Math.round(this.scale * 100);
            $('#zoom-label', this.container).text(percent + '%');
        }

        toggleFullscreen() {
            const elem = this.container[0];

            if (!this.isFullscreen) {
                if (elem.requestFullscreen) {
                    elem.requestFullscreen();
                } else if (elem.webkitRequestFullscreen) {
                    elem.webkitRequestFullscreen();
                } else if (elem.mozRequestFullScreen) {
                    elem.mozRequestFullScreen();
                } else if (elem.msRequestFullscreen) {
                    elem.msRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
        }

        updateFullscreenButton() {
            const icon = $('#btn-fullscreen .dashicons', this.container);
            if (this.isFullscreen) {
                icon.removeClass('dashicons-fullscreen-alt').addClass('dashicons-fullscreen-exit-alt');
            } else {
                icon.removeClass('dashicons-fullscreen-exit-alt').addClass('dashicons-fullscreen-alt');
            }
        }

        markDirty() {
            this.isDirty = true;
            this.scheduleAutosave();
        }

        scheduleAutosave() {
            if (this.autosaveTimer) {
                clearTimeout(this.autosaveTimer);
            }

            this.autosaveTimer = setTimeout(() => {
                this.saveAnnotations(false);
            }, 1500); // 1.5 segundos
        }

        async saveAnnotations(manual = false) {
            if (!this.isDirty && !manual) return;

            const saveStatus = $('#save-status', this.container);
            saveStatus.removeClass('saved error').addClass('visible saving').text('Guardando...');

            const annotationsData = {
                userId: this.userId,
                pdfId: this.pdfId,
                highlights: Object.values(this.highlights),
                lastSavedAt: Date.now()
            };

            try {
                // Guardar en localStorage
                localStorage.setItem(
                    `micea_pdf_annotations_${this.userId}_${this.pdfId}`,
                    JSON.stringify(annotationsData)
                );

                // Intentar guardar en servidor
                await $.ajax({
                    url: spvAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spv_save_annotations',
                        nonce: spvAjax.nonce,
                        pdf_id: this.pdfId,
                        annotations: JSON.stringify(annotationsData)
                    }
                });

                this.isDirty = false;
                this.lastSavedAt = Date.now();

                const time = new Date().toLocaleTimeString('es-ES', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                saveStatus.removeClass('saving').addClass('saved').text(`Guardado ✓ ${time}`);

                setTimeout(() => {
                    saveStatus.removeClass('visible');
                }, 3000);

            } catch (error) {
                console.error('Error guardando anotaciones:', error);
                saveStatus.removeClass('saving').addClass('error').text('Error al guardar');

                setTimeout(() => {
                    saveStatus.removeClass('visible');
                }, 3000);
            }
        }

        async loadUserAnnotations() {
            try {
                // Intentar cargar desde localStorage primero
                const localData = localStorage.getItem(
                    `micea_pdf_annotations_${this.userId}_${this.pdfId}`
                );

                if (localData) {
                    const data = JSON.parse(localData);
                    this.restoreAnnotations(data);
                }

                // Intentar cargar desde servidor
                const response = await $.ajax({
                    url: spvAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spv_load_annotations',
                        nonce: spvAjax.nonce,
                        pdf_id: this.pdfId
                    }
                });

                if (response.success && response.data.annotations) {
                    const data = JSON.parse(response.data.annotations);
                    this.restoreAnnotations(data);
                }

            } catch (error) {
                console.error('Error cargando anotaciones:', error);
            }
        }

        restoreAnnotations(data) {
            if (!data || !data.highlights) return;

            // Restaurar highlights
            this.highlights = {};
            this.highlightsByPage = {};

            data.highlights.forEach(hl => {
                this.highlights[hl.id] = hl;

                if (!this.highlightsByPage[hl.page]) {
                    this.highlightsByPage[hl.page] = [];
                }
                this.highlightsByPage[hl.page].push(hl.id);
            });

            // Inicializar undo/redo
            this.undoStack = [];
            this.redoStack = [];

            // Re-renderizar
            if (this.currentPage) {
                this.renderHighlights(this.currentPage);
            }

            this.lastSavedAt = data.lastSavedAt;
            this.isDirty = false;

            this.updateUndoRedoButtons();
        }

        updateUndoRedoButtons() {
            $('#btn-undo', this.container).prop('disabled', this.undoStack.length === 0);
            $('#btn-redo', this.container).prop('disabled', this.redoStack.length === 0);
        }

        updateButtons() {
            $('#btn-prev', this.container).prop('disabled', this.currentPage <= 1);
            $('#btn-next', this.container).prop('disabled', this.currentPage >= this.totalPages);
        }

        updatePageInfo() {
            $('.spv-current-page', this.container).text(this.currentPage);
            $('.spv-total-pages', this.container).text(this.totalPages);
        }

        preventCopyPaste() {
            $(this.canvas).css({
                '-webkit-user-select': 'none',
                '-moz-user-select': 'none',
                '-ms-user-select': 'none',
                'user-select': 'none',
                '-webkit-touch-callout': 'none'
            });
        }

        showLoading() {
            this.container.find('.spv-loading').show();
        }

        hideLoading() {
            this.container.find('.spv-loading').hide();
        }

        showError(message) {
            this.hideLoading();
            this.container.find('.spv-error').remove();
            this.container.find('.spv-canvas-container').prepend(
                '<div class="spv-error" style="position: absolute; top: 20px; left: 20px; right: 20px; z-index: 100; background: #e74c3c; color: white; padding: 15px; border-radius: 4px; text-align: center;">' +
                message +
                '</div>'
            );
        }
    }

    // Inicializar al cargar el documento
    $(document).ready(function() {
        $('.spv-viewer-container').each(function() {
            new MiceaPDFViewer(this);
        });
    });

})(jQuery);
