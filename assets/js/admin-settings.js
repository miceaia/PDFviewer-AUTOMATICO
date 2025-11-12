/**
 * Admin Settings JavaScript
 * Para la página de configuración del plugin
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Inicializar color pickers
        if ($.fn.wpColorPicker) {
            $('.spv-color-picker').wpColorPicker({
                change: function(event, ui) {
                    updatePreview();
                }
            });
        }

        // Actualizar preview al cambiar cualquier campo
        $('input, select, textarea').on('change keyup', debounce(updatePreview, 300));

        /**
         * Actualizar la vista previa de la marca de agua
         */
        function updatePreview() {
            const parts = [];

            // Usuario
            if ($('input[name="spv_settings[watermark_show_user]"]').is(':checked')) {
                const userName = $('#watermark-preview').data('user-name') || 'Usuario Demo';
                parts.push('Usuario: ' + userName);
            }

            // Email
            if ($('input[name="spv_settings[watermark_show_email]"]').is(':checked')) {
                const userEmail = $('#watermark-preview').data('user-email') || 'usuario@ejemplo.com';
                parts.push(userEmail);
            }

            // Fecha
            if ($('input[name="spv_settings[watermark_show_date]"]').is(':checked')) {
                const today = new Date().toLocaleDateString();
                parts.push('Fecha: ' + today);
            }

            // Texto personalizado
            const customText = $('input[name="spv_settings[watermark_custom_text]"]').val();
            if (customText) {
                parts.push(customText);
            }

            // Generar texto final
            const text = parts.join(' · ');

            // Obtener estilos
            const fontSize = $('input[name="spv_settings[watermark_font_size]"]').val() || 10;
            const opacity = $('input[name="spv_settings[watermark_opacity]"]').val() || 0.15;
            const color = $('input[name="spv_settings[watermark_color]"]').val() || '#000000';

            // Aplicar a preview
            $('#watermark-preview').html(
                '<span style="font-size: ' + fontSize + 'px; opacity: ' + opacity + '; color: ' + color + ';">' +
                (text || 'Sin marca de agua') +
                '</span>'
            );
        }

        /**
         * Debounce function para evitar múltiples llamadas
         */
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Actualizar preview al cargar la página
        updatePreview();

        /**
         * Validación del formulario antes de enviar
         */
        $('form').on('submit', function(e) {
            let isValid = true;
            const errors = [];

            // Validar dimensiones
            const width = $('input[name="spv_settings[default_width]"]').val();
            const height = $('input[name="spv_settings[default_height]"]').val();

            if (!width) {
                errors.push('El ancho predeterminado es requerido');
                isValid = false;
            }

            if (!height) {
                errors.push('La altura predeterminada es requerida');
                isValid = false;
            }

            // Validar zoom
            const zoom = parseFloat($('input[name="spv_settings[default_zoom]"]').val());
            if (zoom < 0.5 || zoom > 3) {
                errors.push('El zoom debe estar entre 0.5 y 3.0');
                isValid = false;
            }

            // Validar autosave
            const autosave = parseInt($('input[name="spv_settings[autosave_delay]"]').val());
            if (autosave < 1 || autosave > 30) {
                errors.push('El tiempo de autosave debe estar entre 1 y 30 segundos');
                isValid = false;
            }

            // Mostrar errores si los hay
            if (!isValid) {
                e.preventDefault();
                alert('Errores de validación:\n\n' + errors.join('\n'));
            }

            return isValid;
        });

        /**
         * Tooltip hover effects
         */
        $('.dashicons-editor-help').hover(
            function() {
                $(this).css('color', '#16a085');
            },
            function() {
                $(this).css('color', '#1ABC9C');
            }
        );

        /**
         * Animación al guardar cambios
         */
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('settings-updated')) {
            // Scroll suave al notice de éxito
            $('html, body').animate({
                scrollTop: $('.updated').offset().top - 100
            }, 500);

            // Auto-ocultar después de 5 segundos
            setTimeout(function() {
                $('.updated').fadeOut(500);
            }, 5000);
        }

        /**
         * Resetear a valores predeterminados
         */
        $('.spv-reset-defaults').on('click', function(e) {
            e.preventDefault();

            if (confirm('¿Estás seguro de que quieres restaurar la configuración predeterminada?')) {
                // Valores predeterminados
                $('input[name="spv_settings[default_width]"]').val('100%');
                $('input[name="spv_settings[default_height]"]').val('600px');
                $('input[name="spv_settings[watermark_show_user]"]').prop('checked', true);
                $('input[name="spv_settings[watermark_show_email]"]').prop('checked', false);
                $('input[name="spv_settings[watermark_show_date]"]').prop('checked', true);
                $('input[name="spv_settings[watermark_custom_text]"]').val('Curso 2024-2025');
                $('select[name="spv_settings[watermark_position]"]').val('bottom-right');
                $('input[name="spv_settings[watermark_font_size]"]').val(10);
                $('input[name="spv_settings[watermark_opacity]"]').val(0.15);
                $('input[name="spv_settings[watermark_color]"]').val('#000000').trigger('change');
                $('input[name="spv_settings[default_zoom]"]').val(1.5);
                $('input[name="spv_settings[autosave_delay]"]').val(3);
                $('input[name="spv_settings[toolbar_color]"]').val('#24333F').trigger('change');

                updatePreview();

                // Mostrar mensaje
                alert('Configuración restaurada a valores predeterminados. Haz clic en "Guardar Configuración" para aplicar los cambios.');
            }
        });

        /**
         * Copiar shortcode al portapapeles
         */
        $('.spv-copy-shortcode').on('click', function(e) {
            e.preventDefault();
            const shortcode = $(this).data('shortcode');

            // Crear elemento temporal
            const temp = $('<textarea>');
            $('body').append(temp);
            temp.val(shortcode).select();
            document.execCommand('copy');
            temp.remove();

            // Feedback visual
            const originalText = $(this).text();
            $(this).text('¡Copiado!');
            setTimeout(() => {
                $(this).text(originalText);
            }, 2000);
        });
    });

})(jQuery);
