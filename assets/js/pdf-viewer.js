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
            this.pdfDoc = null;
            this.currentPage = 1;
            this.totalPages = 0;
            this.scale = 1.5;
            this.rendering = false;
            this.maxScale = 3.0;
            this.minScale = 0.5;
            
            this.init();
        }

        init() {
            this.loadPDF();
            this.bindEvents();
            this.preventCopyPaste();
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

                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

                const renderContext = {
                    canvasContext: this.ctx,
                    viewport: viewport
                };

                await page.render(renderContext).promise;
                
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

        bindEvents() {
            const self = this;

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

            $(this.canvas).on('contextmenu', function(e) {
                e.preventDefault();
                return false;
            });

            $(this.canvas).on('dragstart', function(e) {
                e.preventDefault();
                return false;
            });

            let resizeTimer;
            $(window).on('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    if (self.pdfDoc && !self.rendering) {
                        self.renderPage(self.currentPage);
                    }
                }, 250);
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
