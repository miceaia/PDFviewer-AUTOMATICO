(function($) {
    'use strict';

    // Configurar PDF.js worker
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
    }

    class SecurePDFViewer {
        constructor(container) {
            this.container = $(container);
            this.pdfUrl = this.container.data('pdf-url');
            this.canvas = this.container.find('.spv-pdf-canvas')[0];
            this.ctx = this.canvas.getContext('2d');
            this.canvasContainer = this.container.find('.spv-canvas-container');

            // Canvas para anotaciones
            this.annotationCanvas = null;
            this.annotationCtx = null;

            this.pdfDoc = null;
            this.currentPage = 1;
            this.totalPages = 0;
            this.scale = 1.5;
            this.rendering = false;
            this.maxScale = 3.0;
            this.minScale = 0.5;

            // Anotaciones
            this.annotations = {}; // Por página
            this.currentTool = 'none'; // none, highlight, eraser
            this.currentColor = '#ffff00'; // amarillo por defecto
            this.isDrawing = false;
            this.lastX = 0;
            this.lastY = 0;

            // Historial para deshacer/rehacer
            this.history = [];
            this.historyStep = -1;

            // Fullscreen
            this.isFullscreen = false;

            this.init();
        }

        init() {
            this.loadPDF();
            this.createAnnotationCanvas();
            this.bindEvents();
            this.preventCopyPaste();
            this.addWatermark();
            this.loadUserAnnotations();
        }

        createAnnotationCanvas() {
            // Crear canvas overlay para anotaciones
            const canvasOverlay = document.createElement('canvas');
            canvasOverlay.className = 'spv-annotation-canvas';
            canvasOverlay.style.position = 'absolute';
            canvasOverlay.style.top = '0';
            canvasOverlay.style.left = '0';
            canvasOverlay.style.pointerEvents = 'none';
            canvasOverlay.style.zIndex = '2';

            this.annotationCanvas = canvasOverlay;
            this.annotationCtx = canvasOverlay.getContext('2d');

            // Agregar al container del canvas
            $(this.canvas).parent().css('position', 'relative').append(canvasOverlay);
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
            if (this.rendering) {
                return;
            }

            this.rendering = true;
            this.showLoading();

            try {
                const page = await this.pdfDoc.getPage(pageNum);

                const originalViewport = page.getViewport({ scale: 1.0 });
                const containerWidth = this.canvasContainer.width() - 40;
                const containerScale = containerWidth / originalViewport.width;
                const effectiveScale = Math.min(this.scale, containerScale * 1.5);

                const viewport = page.getViewport({ scale: effectiveScale });

                this.canvas.width = viewport.width;
                this.canvas.height = viewport.height;

                // Actualizar canvas de anotaciones también
                this.annotationCanvas.width = viewport.width;
                this.annotationCanvas.height = viewport.height;

                // Posicionar annotation canvas
                const canvasRect = this.canvas.getBoundingClientRect();
                const containerRect = this.canvas.parentElement.getBoundingClientRect();
                $(this.annotationCanvas).css({
                    left: (canvasRect.left - containerRect.left) + 'px',
                    top: (canvasRect.top - containerRect.top) + 'px'
                });

                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

                const renderContext = {
                    canvasContext: this.ctx,
                    viewport: viewport
                };

                await page.render(renderContext).promise;

                this.currentPage = pageNum;
                this.updatePageInfo();
                this.updateButtons();

                // Redibujar anotaciones de esta página
                this.redrawAnnotations();

            } catch (error) {
                console.error('Error renderizando página:', error);
                this.showError('Error al renderizar la página ' + pageNum);
            } finally {
                this.rendering = false;
                this.hideLoading();
            }
        }

        bindEvents() {
            const self = this;

            // Navegación
            this.container.find('.spv-prev').on('click', function() {
                if (self.currentPage > 1 && !self.rendering) {
                    self.renderPage(self.currentPage - 1);
                }
            });

            this.container.find('.spv-next').on('click', function() {
                if (self.currentPage < self.totalPages && !self.rendering) {
                    self.renderPage(self.currentPage + 1);
                }
            });

            // Zoom
            this.container.find('.spv-zoom-in').on('click', function() {
                self.scale += 0.25;
                if (self.scale > self.maxScale) self.scale = self.maxScale;
                self.updateZoomLevel();
                self.renderPage(self.currentPage);
            });

            this.container.find('.spv-zoom-out').on('click', function() {
                self.scale -= 0.25;
                if (self.scale < self.minScale) self.scale = self.minScale;
                self.updateZoomLevel();
                self.renderPage(self.currentPage);
            });

            // Herramientas de anotación
            this.container.find('.spv-highlight-tool').on('click', function() {
                self.currentTool = 'highlight';
                self.updateToolButtons();
                $(self.annotationCanvas).css('pointerEvents', 'auto');
            });

            this.container.find('.spv-eraser-tool').on('click', function() {
                self.currentTool = 'eraser';
                self.updateToolButtons();
                $(self.annotationCanvas).css('pointerEvents', 'auto');
            });

            this.container.find('.spv-select-tool').on('click', function() {
                self.currentTool = 'none';
                self.updateToolButtons();
                $(self.annotationCanvas).css('pointerEvents', 'none');
            });

            // Selector de color
            this.container.find('.spv-color-picker').on('change', function() {
                self.currentColor = $(this).val();
            });

            // Color presets
            this.container.find('.spv-color-preset').on('click', function() {
                const color = $(this).data('color');
                self.currentColor = color;
                self.container.find('.spv-color-picker').val(color);
            });

            // Deshacer/Rehacer
            this.container.find('.spv-undo').on('click', function() {
                self.undo();
            });

            this.container.find('.spv-redo').on('click', function() {
                self.redo();
            });

            // Pantalla completa
            this.container.find('.spv-fullscreen').on('click', function() {
                self.toggleFullscreen();
            });

            // Guardar anotaciones
            this.container.find('.spv-save-annotations').on('click', function() {
                self.saveAnnotations();
            });

            // Dibujo en canvas de anotaciones
            $(this.annotationCanvas).on('mousedown', function(e) {
                if (self.currentTool === 'none') return;
                self.isDrawing = true;
                const rect = self.annotationCanvas.getBoundingClientRect();
                self.lastX = e.clientX - rect.left;
                self.lastY = e.clientY - rect.top;
            });

            $(this.annotationCanvas).on('mousemove', function(e) {
                if (!self.isDrawing || self.currentTool === 'none') return;

                const rect = self.annotationCanvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                self.draw(self.lastX, self.lastY, x, y);

                self.lastX = x;
                self.lastY = y;
            });

            $(this.annotationCanvas).on('mouseup', function() {
                if (self.isDrawing) {
                    self.isDrawing = false;
                    self.saveAnnotationState();
                }
            });

            $(this.annotationCanvas).on('mouseleave', function() {
                self.isDrawing = false;
            });

            // Protección
            $(this.canvas).on('contextmenu', function(e) {
                e.preventDefault();
                return false;
            });

            $(this.canvas).on('dragstart', function(e) {
                e.preventDefault();
                return false;
            });

            // Responsive
            let resizeTimer;
            $(window).on('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    if (self.pdfDoc && !self.rendering) {
                        self.renderPage(self.currentPage);
                    }
                }, 250);
            });

            // Fullscreen change
            $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange msfullscreenchange', function() {
                self.isFullscreen = !!(document.fullscreenElement || document.webkitFullscreenElement ||
                                      document.mozFullScreenElement || document.msFullscreenElement);
                self.updateFullscreenButton();
            });
        }

        draw(x1, y1, x2, y2) {
            this.annotationCtx.beginPath();
            this.annotationCtx.moveTo(x1, y1);
            this.annotationCtx.lineTo(x2, y2);

            if (this.currentTool === 'highlight') {
                this.annotationCtx.strokeStyle = this.currentColor;
                this.annotationCtx.globalAlpha = 0.5;
                this.annotationCtx.lineWidth = 20;
                this.annotationCtx.lineCap = 'round';
            } else if (this.currentTool === 'eraser') {
                this.annotationCtx.globalCompositeOperation = 'destination-out';
                this.annotationCtx.lineWidth = 30;
                this.annotationCtx.lineCap = 'round';
            }

            this.annotationCtx.stroke();

            // Restaurar valores
            this.annotationCtx.globalAlpha = 1.0;
            this.annotationCtx.globalCompositeOperation = 'source-over';
        }

        redrawAnnotations() {
            // Limpiar canvas de anotaciones
            this.annotationCtx.clearRect(0, 0, this.annotationCanvas.width, this.annotationCanvas.height);

            // Redibujar anotaciones de la página actual
            const pageAnnotations = this.annotations[this.currentPage];
            if (pageAnnotations) {
                const img = new Image();
                img.onload = () => {
                    this.annotationCtx.drawImage(img, 0, 0);
                };
                img.src = pageAnnotations;
            }
        }

        saveAnnotationState() {
            // Guardar estado actual de anotaciones para esta página
            this.annotations[this.currentPage] = this.annotationCanvas.toDataURL();

            // Agregar al historial
            if (this.historyStep < this.history.length - 1) {
                this.history = this.history.slice(0, this.historyStep + 1);
            }

            this.history.push({
                page: this.currentPage,
                state: JSON.parse(JSON.stringify(this.annotations))
            });

            this.historyStep++;
            this.updateUndoRedoButtons();
        }

        undo() {
            if (this.historyStep > 0) {
                this.historyStep--;
                this.annotations = JSON.parse(JSON.stringify(this.history[this.historyStep].state));
                this.redrawAnnotations();
                this.updateUndoRedoButtons();
            }
        }

        redo() {
            if (this.historyStep < this.history.length - 1) {
                this.historyStep++;
                this.annotations = JSON.parse(JSON.stringify(this.history[this.historyStep].state));
                this.redrawAnnotations();
                this.updateUndoRedoButtons();
            }
        }

        updateUndoRedoButtons() {
            this.container.find('.spv-undo').prop('disabled', this.historyStep <= 0);
            this.container.find('.spv-redo').prop('disabled', this.historyStep >= this.history.length - 1);
        }

        updateToolButtons() {
            this.container.find('.spv-highlight-tool, .spv-eraser-tool, .spv-select-tool').removeClass('active');

            if (this.currentTool === 'highlight') {
                this.container.find('.spv-highlight-tool').addClass('active');
            } else if (this.currentTool === 'eraser') {
                this.container.find('.spv-eraser-tool').addClass('active');
            } else {
                this.container.find('.spv-select-tool').addClass('active');
            }
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
            const icon = this.container.find('.spv-fullscreen .dashicons');
            if (this.isFullscreen) {
                icon.removeClass('dashicons-fullscreen-alt').addClass('dashicons-fullscreen-exit-alt');
            } else {
                icon.removeClass('dashicons-fullscreen-exit-alt').addClass('dashicons-fullscreen-alt');
            }
        }

        addWatermark() {
            // Obtener datos del usuario
            const userData = this.container.data('user-info') || {};
            const userName = userData.name || 'Usuario';
            const userEmail = userData.email || '';
            const currentDate = new Date().toLocaleDateString();

            // Crear overlay de marca de agua
            const watermark = $('<div class="spv-watermark"></div>');
            watermark.html(`
                <div class="spv-watermark-content">
                    <span class="dashicons dashicons-admin-users"></span>
                    ${userName}<br>
                    <small>${userEmail}</small><br>
                    <small>${currentDate}</small>
                </div>
            `);

            this.canvasContainer.append(watermark);
        }

        async saveAnnotations() {
            // Guardar anotaciones en el servidor vía AJAX
            const self = this;
            const pdfId = this.container.data('pdf-id');

            $.ajax({
                url: spvAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spv_save_annotations',
                    nonce: spvAjax.nonce,
                    pdf_id: pdfId,
                    annotations: JSON.stringify(this.annotations)
                },
                success: function(response) {
                    if (response.success) {
                        self.container.find('.spv-save-message').remove();
                        self.container.find('.spv-controls').append(
                            '<span class="spv-save-message" style="color: #2ecc71; margin-left: 10px;">✓ Guardado</span>'
                        );
                        setTimeout(function() {
                            self.container.find('.spv-save-message').fadeOut(function() {
                                $(this).remove();
                            });
                        }, 2000);
                    }
                },
                error: function() {
                    alert('Error al guardar anotaciones. Inténtalo de nuevo.');
                }
            });
        }

        async loadUserAnnotations() {
            // Cargar anotaciones guardadas del usuario
            const self = this;
            const pdfId = this.container.data('pdf-id');

            if (!pdfId) return;

            $.ajax({
                url: spvAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spv_load_annotations',
                    nonce: spvAjax.nonce,
                    pdf_id: pdfId
                },
                success: function(response) {
                    if (response.success && response.data.annotations) {
                        self.annotations = JSON.parse(response.data.annotations);
                        self.redrawAnnotations();

                        // Inicializar historial con estado cargado
                        self.history = [{
                            page: self.currentPage,
                            state: JSON.parse(JSON.stringify(self.annotations))
                        }];
                        self.historyStep = 0;
                    }
                }
            });
        }

        preventCopyPaste() {
            $(this.canvas).css({
                '-webkit-user-select': 'none',
                '-moz-user-select': 'none',
                '-ms-user-select': 'none',
                'user-select': 'none',
                '-webkit-touch-callout': 'none'
            });

            const self = this;
            $(document).on('keydown', function(e) {
                if (!self.container.is(':visible')) return;

                if ((e.ctrlKey || e.metaKey) &&
                    (e.key === 's' || e.key === 'p' || e.key === 'c')) {
                    e.preventDefault();
                    return false;
                }
            });
        }

        updatePageInfo() {
            this.container.find('.spv-current-page').text(this.currentPage);
            this.container.find('.spv-total-pages').text(this.totalPages);
        }

        updateButtons() {
            const prevBtn = this.container.find('.spv-prev');
            const nextBtn = this.container.find('.spv-next');

            prevBtn.prop('disabled', this.currentPage <= 1);
            nextBtn.prop('disabled', this.currentPage >= this.totalPages);
        }

        updateZoomLevel() {
            const zoomPercent = Math.round(this.scale * 100);
            this.container.find('.spv-zoom-level').text(zoomPercent + '%');
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
                '<div class="spv-error" style="position: absolute; top: 20px; left: 20px; right: 20px; z-index: 100;">' +
                message +
                '</div>'
            );
        }
    }

    $(document).ready(function() {
        $('.spv-viewer-container').each(function() {
            new SecurePDFViewer(this);
        });
    });

})(jQuery);
