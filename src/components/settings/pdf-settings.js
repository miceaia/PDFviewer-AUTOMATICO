(function () {
    'use strict';

    const data = window.spvPdfSettings || null;

    function createStyles() {
        if (document.getElementById('spv-pdf-settings-styles')) {
            return;
        }

        const styles = document.createElement('style');
        styles.id = 'spv-pdf-settings-styles';
        styles.textContent = `
            .spv-pdf-settings-form {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                border-radius: 8px;
                max-width: 960px;
                margin-top: 16px;
            }
            .spv-pdf-settings-columns {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 16px;
            }
            .spv-pdf-settings-field {
                margin-bottom: 16px;
            }
            .spv-pdf-settings-field label {
                font-weight: 600;
                display: block;
                margin-bottom: 4px;
            }
            .spv-pdf-settings-actions {
                margin-top: 20px;
                display: flex;
                gap: 10px;
            }
            .spv-pdf-settings-message {
                margin-top: 15px;
            }
            .spv-pdf-settings-field small {
                color: #555d66;
                display: block;
                margin-top: 4px;
            }
            .spv-pdf-settings-group {
                margin-bottom: 24px;
                padding-bottom: 16px;
                border-bottom: 1px solid #f0f0f1;
            }
            .spv-pdf-settings-group:last-child {
                border-bottom: none;
            }
            .spv-pdf-settings-description {
                margin: 0 0 12px;
                color: #555d66;
            }
            .spv-pdf-color-control {
                display: flex;
                gap: 8px;
                align-items: center;
            }
            .spv-pdf-color-control input[type="color"] {
                width: 48px;
                height: 34px;
                padding: 0;
                border: none;
                background: transparent;
                cursor: pointer;
            }
            .spv-pdf-color-control input[type="text"] {
                flex: 1;
                font-family: SFMono-Regular, Consolas, monospace;
                text-transform: uppercase;
            }
            .spv-pdf-settings-field input.invalid {
                border-color: #d63638;
                box-shadow: 0 0 0 1px rgba(214, 54, 56, 0.2);
            }
        `;
        document.head.appendChild(styles);
    }

    function normalizeHex(value) {
        if (!value) {
            return '';
        }
        let hex = value.trim().replace(/^#+/, '');
        if (hex.length === 3) {
            hex = hex.split('').map(function (char) { return char + char; }).join('');
        }
        if (hex.length !== 6 || /[^0-9a-f]/i.test(hex)) {
            return '';
        }
        return '#' + hex.toUpperCase();
    }

    function renderApp() {
        const root = document.getElementById('spv-pdf-settings-root');
        if (!root) {
            return;
        }

        if (!data || !data.restUrl || !data.nonce || !data.defaults) {
            root.textContent = 'No se pudieron cargar los ajustes. Verifica tus permisos.';
            return;
        }

        createStyles();

        const form = document.createElement('form');
        form.className = 'spv-pdf-settings-form';

        const message = document.createElement('div');
        message.className = 'spv-pdf-settings-message';

        const themeSection = document.createElement('section');
        themeSection.className = 'spv-pdf-settings-group';
        themeSection.innerHTML = '<h3>Tema del visor</h3>';
        const themeColumns = document.createElement('div');
        themeColumns.className = 'spv-pdf-settings-columns';

        function appendDescription(section, text) {
            const description = document.createElement('p');
            description.className = 'spv-pdf-settings-description';
            description.textContent = text;
            section.appendChild(description);
        }

        function createColorPickerField(options) {
            const initialValue = normalizeHex(options.value) || '#FFFFFF';
            const field = document.createElement('div');
            field.className = 'spv-pdf-settings-field';
            const label = document.createElement('label');
            label.setAttribute('for', options.id);
            label.textContent = options.labelText;

            const wrapper = document.createElement('div');
            wrapper.className = 'spv-pdf-color-control';

            const colorInput = document.createElement('input');
            colorInput.type = 'color';
            colorInput.id = options.id;
            colorInput.name = options.name;
            colorInput.value = initialValue;

            const textInput = document.createElement('input');
            textInput.type = 'text';
            textInput.id = options.id + '-text';
            textInput.value = initialValue.toUpperCase();
            textInput.placeholder = '#AABBCC';
            textInput.autocomplete = 'off';
            textInput.spellcheck = false;
            textInput.setAttribute('aria-label', 'Código HEX para ' + options.labelText);
            textInput.title = 'Ingresa un color en formato HEX (#RRGGBB).';
            textInput.pattern = '^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$';
            textInput.maxLength = 7;

            wrapper.appendChild(colorInput);
            wrapper.appendChild(textInput);

            field.appendChild(label);
            field.appendChild(wrapper);

            if (options.hint) {
                const hint = document.createElement('small');
                hint.textContent = options.hint;
                field.appendChild(hint);
            }

            colorInput.addEventListener('input', function () {
                textInput.value = colorInput.value.toUpperCase();
                textInput.classList.remove('invalid');
            });

            textInput.addEventListener('input', function () {
                const normalized = normalizeHex(textInput.value);
                if (normalized) {
                    colorInput.value = normalized;
                    textInput.classList.remove('invalid');
                } else {
                    textInput.classList.toggle('invalid', textInput.value.trim().length > 0);
                }
            });

            textInput.addEventListener('blur', function () {
                const normalized = normalizeHex(textInput.value) || colorInput.value;
                textInput.value = normalized.toUpperCase();
                colorInput.value = normalized;
                textInput.classList.remove('invalid');
            });

            return { field: field, colorInput: colorInput, textInput: textInput };
        }

        function updateLinkedColorTextInput(id, value) {
            const textInput = document.getElementById(id + '-text');
            if (textInput) {
                textInput.value = (value || '').toUpperCase();
                textInput.classList.remove('invalid');
            }
        }

        const themeLabels = {
            base: 'Color base',
            base_dark: 'Color oscuro / hover',
            base_contrast: 'Color de texto sobre el tema'
        };

        const themeFallbacks = {
            base: '#1abc9c',
            base_dark: '#16a085',
            base_contrast: '#ffffff'
        };

        const themeInputs = {};

        appendDescription(themeSection, 'Define los colores base del visor que verán los usuarios al abrir un PDF.');

        Object.keys(themeLabels).forEach(function (key) {
            const value = (data.defaults.theme_colors && data.defaults.theme_colors[key]) || themeFallbacks[key];
            const colorField = createColorPickerField({
                labelText: themeLabels[key],
                id: 'spv-theme-' + key,
                name: 'theme_colors[' + key + ']',
                value: value,
                hint: 'Puedes escribir el código HEX o elegirlo desde el selector.'
            });
            themeColumns.appendChild(colorField.field);
            themeInputs[key] = colorField.colorInput;
        });

        themeSection.appendChild(themeColumns);

        const highlightSection = document.createElement('section');
        highlightSection.className = 'spv-pdf-settings-group';
        highlightSection.innerHTML = '<h3>Colores y anotaciones</h3>';
        const highlightColumns = document.createElement('div');
        highlightColumns.className = 'spv-pdf-settings-columns';

        const colorLabels = {
            yellow: 'Color amarillo',
            green: 'Color verde',
            blue: 'Color azul',
            pink: 'Color rosa'
        };

        const highlightDefaults = data.defaults.highlight_colors || {};

        appendDescription(highlightSection, 'Configura los colores de resaltado y protecciones que se aplicarán a las anotaciones.');

        Object.keys(colorLabels).forEach(function (key) {
            const colorField = createColorPickerField({
                labelText: colorLabels[key],
                id: 'spv-color-' + key,
                name: 'highlight_colors[' + key + ']',
                value: highlightDefaults[key] || '#ffffff'
            });
            colorField.colorInput.dataset.colorKey = key;
            highlightColumns.appendChild(colorField.field);
        });

        const opacityField = document.createElement('div');
        opacityField.className = 'spv-pdf-settings-field';
        const opacityLabel = document.createElement('label');
        opacityLabel.setAttribute('for', 'spv-highlight-opacity');
        opacityLabel.textContent = 'Opacidad del resaltado';
        const opacityInput = document.createElement('input');
        opacityInput.type = 'number';
        opacityInput.step = '0.05';
        opacityInput.min = '0';
        opacityInput.max = '1';
        opacityInput.id = 'spv-highlight-opacity';
        opacityInput.name = 'highlight_opacity';
        opacityInput.value = data.defaults.highlight_opacity;
        const opacityHint = document.createElement('small');
        opacityHint.textContent = 'Valor entre 0 y 1 (0 = transparente, 1 = sólido).';
        opacityField.appendChild(opacityLabel);
        opacityField.appendChild(opacityInput);
        opacityField.appendChild(opacityHint);

        const copyField = document.createElement('div');
        copyField.className = 'spv-pdf-settings-field';
        const copyLabel = document.createElement('label');
        copyLabel.setAttribute('for', 'spv-copy-protection');
        copyLabel.textContent = 'Protección contra copiado';
        const copyCheckbox = document.createElement('input');
        copyCheckbox.type = 'checkbox';
        copyCheckbox.id = 'spv-copy-protection';
        copyCheckbox.name = 'copy_protection';
        copyCheckbox.checked = !!data.defaults.copy_protection;
        const copyText = document.createElement('small');
        copyText.textContent = 'Desactiva la selección y atajos de teclado para copiar del canvas.';
        copyField.appendChild(copyLabel);
        copyField.appendChild(copyCheckbox);
        copyField.appendChild(copyText);

        highlightSection.appendChild(highlightColumns);
        highlightSection.appendChild(opacityField);
        highlightSection.appendChild(copyField);

        const watermarkSection = document.createElement('section');
        watermarkSection.className = 'spv-pdf-settings-group';
        watermarkSection.innerHTML = '<h3>Marca de agua</h3>';
        appendDescription(watermarkSection, 'Personaliza el mensaje y estilo que se superpone en cada página.');

        const watermarkEnableField = document.createElement('div');
        watermarkEnableField.className = 'spv-pdf-settings-field';
        const watermarkEnableLabel = document.createElement('label');
        watermarkEnableLabel.setAttribute('for', 'spv-watermark-enabled');
        watermarkEnableLabel.textContent = 'Activar marca de agua';
        const watermarkEnableInput = document.createElement('input');
        watermarkEnableInput.type = 'checkbox';
        watermarkEnableInput.id = 'spv-watermark-enabled';
        watermarkEnableInput.name = 'watermark_enabled';
        watermarkEnableInput.checked = !!data.defaults.watermark_enabled;
        watermarkEnableField.appendChild(watermarkEnableLabel);
        watermarkEnableField.appendChild(watermarkEnableInput);

        const watermarkTextField = document.createElement('div');
        watermarkTextField.className = 'spv-pdf-settings-field';
        const watermarkTextLabel = document.createElement('label');
        watermarkTextLabel.setAttribute('for', 'spv-watermark-text');
        watermarkTextLabel.textContent = 'Texto de la marca de agua';
        const watermarkTextarea = document.createElement('textarea');
        watermarkTextarea.id = 'spv-watermark-text';
        watermarkTextarea.name = 'watermark_text';
        watermarkTextarea.rows = 2;
        watermarkTextarea.style.width = '100%';
        watermarkTextarea.value = data.defaults.watermark_text;
        const watermarkHint = document.createElement('small');
        watermarkHint.textContent = 'Variables disponibles: {{username}}, {{email}}, {{pdfId}}, {{date}}.';
        watermarkTextField.appendChild(watermarkTextLabel);
        watermarkTextField.appendChild(watermarkTextarea);
        watermarkTextField.appendChild(watermarkHint);

        const watermarkColumns = document.createElement('div');
        watermarkColumns.className = 'spv-pdf-settings-columns';

        const watermarkColorField = createColorPickerField({
            labelText: 'Color',
            id: 'spv-watermark-color',
            name: 'watermark_color',
            value: data.defaults.watermark_color,
            hint: 'Utiliza un color suave para que no distraiga al lector.'
        });

        const watermarkOpacityField = document.createElement('div');
        watermarkOpacityField.className = 'spv-pdf-settings-field';
        const watermarkOpacityLabel = document.createElement('label');
        watermarkOpacityLabel.setAttribute('for', 'spv-watermark-opacity');
        watermarkOpacityLabel.textContent = 'Opacidad';
        const watermarkOpacityInput = document.createElement('input');
        watermarkOpacityInput.type = 'number';
        watermarkOpacityInput.step = '0.05';
        watermarkOpacityInput.min = '0';
        watermarkOpacityInput.max = '1';
        watermarkOpacityInput.id = 'spv-watermark-opacity';
        watermarkOpacityInput.name = 'watermark_opacity';
        watermarkOpacityInput.value = data.defaults.watermark_opacity;
        watermarkOpacityField.appendChild(watermarkOpacityLabel);
        watermarkOpacityField.appendChild(watermarkOpacityInput);

        const watermarkFontSizeField = document.createElement('div');
        watermarkFontSizeField.className = 'spv-pdf-settings-field';
        const watermarkFontLabel = document.createElement('label');
        watermarkFontLabel.setAttribute('for', 'spv-watermark-font');
        watermarkFontLabel.textContent = 'Tamaño de fuente (px)';
        const watermarkFontInput = document.createElement('input');
        watermarkFontInput.type = 'number';
        watermarkFontInput.min = '8';
        watermarkFontInput.max = '72';
        watermarkFontInput.id = 'spv-watermark-font';
        watermarkFontInput.name = 'watermark_font_size';
        watermarkFontInput.value = data.defaults.watermark_font_size;
        watermarkFontSizeField.appendChild(watermarkFontLabel);
        watermarkFontSizeField.appendChild(watermarkFontInput);

        const watermarkFontFamilyField = document.createElement('div');
        watermarkFontFamilyField.className = 'spv-pdf-settings-field';
        const watermarkFontFamilyLabel = document.createElement('label');
        watermarkFontFamilyLabel.setAttribute('for', 'spv-watermark-font-family');
        watermarkFontFamilyLabel.textContent = 'Fuente';
        const watermarkFontFamilySelect = document.createElement('select');
        watermarkFontFamilySelect.id = 'spv-watermark-font-family';
        watermarkFontFamilySelect.name = 'watermark_font_family';
        const fontOptions = [
            'Arial',
            'Helvetica',
            'Times New Roman',
            'Courier New',
            'Georgia',
            'Verdana',
            'Roboto',
            'Monospace'
        ];
        fontOptions.forEach(function (font) {
            const option = document.createElement('option');
            option.value = font;
            option.textContent = font;
            watermarkFontFamilySelect.appendChild(option);
        });
        watermarkFontFamilySelect.value = data.defaults.watermark_font_family || 'Arial';
        watermarkFontFamilyField.appendChild(watermarkFontFamilyLabel);
        watermarkFontFamilyField.appendChild(watermarkFontFamilySelect);

        const watermarkRotationField = document.createElement('div');
        watermarkRotationField.className = 'spv-pdf-settings-field';
        const watermarkRotationLabel = document.createElement('label');
        watermarkRotationLabel.setAttribute('for', 'spv-watermark-rotation');
        watermarkRotationLabel.textContent = 'Rotación (°)';
        const watermarkRotationInput = document.createElement('input');
        watermarkRotationInput.type = 'number';
        watermarkRotationInput.min = '-90';
        watermarkRotationInput.max = '90';
        watermarkRotationInput.id = 'spv-watermark-rotation';
        watermarkRotationInput.name = 'watermark_rotation';
        watermarkRotationInput.value = data.defaults.watermark_rotation;
        watermarkRotationField.appendChild(watermarkRotationLabel);
        watermarkRotationField.appendChild(watermarkRotationInput);

        const watermarkColorInput = watermarkColorField.colorInput;

        watermarkColumns.appendChild(watermarkColorField.field);
        watermarkColumns.appendChild(watermarkOpacityField);
        watermarkColumns.appendChild(watermarkFontSizeField);
        watermarkColumns.appendChild(watermarkRotationField);
        watermarkColumns.appendChild(watermarkFontFamilyField);
        watermarkColumns.appendChild(watermarkPositionField);

        watermarkSection.appendChild(watermarkEnableField);
        watermarkSection.appendChild(watermarkTextField);
        watermarkSection.appendChild(watermarkColumns);

        const watermarkPreviewField = document.createElement('div');
        watermarkPreviewField.className = 'spv-pdf-settings-field spv-watermark-preview-field';
        const watermarkPreviewLabel = document.createElement('label');
        watermarkPreviewLabel.textContent = 'Vista previa';
        watermarkPreviewLabel.setAttribute('for', 'spv-watermark-preview');
        const watermarkPreviewCanvas = document.createElement('canvas');
        watermarkPreviewCanvas.id = 'spv-watermark-preview';
        watermarkPreviewCanvas.width = 360;
        watermarkPreviewCanvas.height = 220;
        const watermarkPreviewHint = document.createElement('small');
        watermarkPreviewHint.textContent = 'Se muestran datos de ejemplo para validar texto, color y posición.';
        watermarkPreviewField.appendChild(watermarkPreviewLabel);
        watermarkPreviewField.appendChild(watermarkPreviewCanvas);
        watermarkPreviewField.appendChild(watermarkPreviewHint);

        watermarkSection.appendChild(watermarkPreviewField);

        const zoomSection = document.createElement('section');
        zoomSection.className = 'spv-pdf-settings-group';
        zoomSection.innerHTML = '<h3>Zoom predeterminado</h3>';
        appendDescription(zoomSection, 'Define el nivel de zoom inicial y los límites que podrá usar el lector.');
        const zoomColumns = document.createElement('div');
        zoomColumns.className = 'spv-pdf-settings-columns';

        function createZoomField(id, labelText, name, value, min, max, step) {
            const wrapper = document.createElement('div');
            wrapper.className = 'spv-pdf-settings-field';
            const label = document.createElement('label');
            label.setAttribute('for', id);
            label.textContent = labelText;
            const input = document.createElement('input');
            input.type = 'number';
            input.step = step || '0.1';
            input.min = min;
            input.max = max;
            input.id = id;
            input.name = name;
            input.value = value;
            wrapper.appendChild(label);
            wrapper.appendChild(input);
            return wrapper;
        }

        zoomColumns.appendChild(createZoomField('spv-zoom-default', 'Zoom por defecto', 'default_zoom', data.defaults.default_zoom, '0.1', '5', '0.1'));
        zoomColumns.appendChild(createZoomField('spv-zoom-min', 'Zoom mínimo', 'min_zoom', data.defaults.min_zoom, '0.1', '5', '0.1'));
        zoomColumns.appendChild(createZoomField('spv-zoom-max', 'Zoom máximo', 'max_zoom', data.defaults.max_zoom, '0.5', '10', '0.1'));

        zoomSection.appendChild(zoomColumns);

        const actions = document.createElement('div');
        actions.className = 'spv-pdf-settings-actions';
        const saveButton = document.createElement('button');
        saveButton.type = 'submit';
        saveButton.className = 'button button-primary';
        saveButton.textContent = 'Guardar cambios';
        const resetButton = document.createElement('button');
        resetButton.type = 'button';
        resetButton.className = 'button';
        resetButton.textContent = 'Restablecer predeterminados';
        actions.appendChild(saveButton);
        actions.appendChild(resetButton);

        resetButton.addEventListener('click', function () {
            fillForm(data.defaults);
            showMessage('Valores restablecidos a los predeterminados. No olvides guardar.', 'notice-warning');
        });

        form.appendChild(themeSection);
        form.appendChild(highlightSection);
        form.appendChild(watermarkSection);
        form.appendChild(zoomSection);
        form.appendChild(actions);

        root.appendChild(form);
        root.appendChild(message);

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            saveSettings();
        });

        const watermarkPreviewCtx = watermarkPreviewCanvas.getContext('2d');
        const previewSampleData = {
            username: 'Laura Gómez',
            email: 'laura@example.com',
            pdfid: 'demo-42',
            userid: '42'
        };

        function interpolatePreviewWatermark(template) {
            if (!template) {
                return '';
            }

            const now = new Date();
            const replacements = {
                username: previewSampleData.username,
                user_name: previewSampleData.username,
                name: previewSampleData.username,
                email: previewSampleData.email,
                user_email: previewSampleData.email,
                pdfid: previewSampleData.pdfid,
                pdf_id: previewSampleData.pdfid,
                userid: previewSampleData.userid,
                user_id: previewSampleData.userid,
                date: now.toLocaleDateString(),
                datetime: now.toLocaleString()
            };

            return template.replace(/\{\{\s*([^}]+)\s*\}\}|\{([^}]+)\}/g, function (match, doubleToken, singleToken) {
                const rawToken = (doubleToken || singleToken || '').toLowerCase().replace(/[^a-z0-9_]/g, '');
                if (!rawToken) {
                    return '';
                }
                return Object.prototype.hasOwnProperty.call(replacements, rawToken)
                    ? replacements[rawToken]
                    : '';
            });
        }

        function getPreviewPositions(position) {
            const width = watermarkPreviewCanvas.width;
            const height = watermarkPreviewCanvas.height;
            const base = {
                center: [ { x: width / 2, y: height / 2 } ],
                top_left: [ { x: width * 0.2, y: height * 0.2 } ],
                top_right: [ { x: width * 0.8, y: height * 0.2 } ],
                bottom_left: [ { x: width * 0.2, y: height * 0.8 } ],
                bottom_right: [ { x: width * 0.8, y: height * 0.8 } ],
                tile: [
                    { x: width / 2, y: height / 2 },
                    { x: width * 0.2, y: height * 0.2 },
                    { x: width * 0.8, y: height * 0.2 },
                    { x: width * 0.2, y: height * 0.8 },
                    { x: width * 0.8, y: height * 0.8 }
                ]
            };

            if (!position || !base[position]) {
                return base.center;
            }

            return base[position];
        }

        function renderWatermarkPreview() {
            if (!watermarkPreviewCtx) {
                return;
            }

            const width = watermarkPreviewCanvas.width;
            const height = watermarkPreviewCanvas.height;

            watermarkPreviewCtx.clearRect(0, 0, width, height);
            watermarkPreviewCtx.save();
            watermarkPreviewCtx.fillStyle = '#ffffff';
            watermarkPreviewCtx.fillRect(0, 0, width, height);
            watermarkPreviewCtx.strokeStyle = '#ccd0d4';
            watermarkPreviewCtx.strokeRect(0.5, 0.5, width - 1, height - 1);

            if (!watermarkEnableInput.checked) {
                watermarkPreviewCtx.fillStyle = '#7b8a8b';
                watermarkPreviewCtx.font = '14px sans-serif';
                watermarkPreviewCtx.textAlign = 'center';
                watermarkPreviewCtx.textBaseline = 'middle';
                watermarkPreviewCtx.fillText('La marca de agua está desactivada.', width / 2, height / 2);
                watermarkPreviewCtx.restore();
                return;
            }

            const previewText = interpolatePreviewWatermark(watermarkTextarea.value);
            if (!previewText) {
                watermarkPreviewCtx.fillStyle = '#7b8a8b';
                watermarkPreviewCtx.font = '14px sans-serif';
                watermarkPreviewCtx.textAlign = 'center';
                watermarkPreviewCtx.textBaseline = 'middle';
                watermarkPreviewCtx.fillText('Añade un texto para ver la vista previa.', width / 2, height / 2);
                watermarkPreviewCtx.restore();
                return;
            }

            const fontSize = parseFloat(watermarkFontInput.value) || 14;
            const fontFamily = watermarkFontFamilySelect.value || 'Arial';
            const opacityValue = parseFloat(watermarkOpacityInput.value);
            let opacity = Number.isFinite(opacityValue) ? opacityValue : 0.15;
            if (opacity < 0) {
                opacity = 0;
            } else if (opacity > 1) {
                opacity = 1;
            }
            const color = watermarkColorInput.value || '#000000';
            const rotationValue = parseFloat(watermarkRotationInput.value) || 0;
            const positions = getPreviewPositions(watermarkPositionSelect.value);

            positions.forEach(function (point) {
                watermarkPreviewCtx.save();
                watermarkPreviewCtx.globalAlpha = opacity;
                watermarkPreviewCtx.fillStyle = color;
                watermarkPreviewCtx.font = `${fontSize}px ${fontFamily}`;
                watermarkPreviewCtx.textAlign = 'center';
                watermarkPreviewCtx.textBaseline = 'middle';
                watermarkPreviewCtx.translate(point.x, point.y);
                if (rotationValue) {
                    watermarkPreviewCtx.rotate((rotationValue * Math.PI) / 180);
                }
                watermarkPreviewCtx.fillText(previewText, 0, 0);
                watermarkPreviewCtx.restore();
            });

            watermarkPreviewCtx.restore();
        }

        function bindPreviewListener(element) {
            if (!element) {
                return;
            }
            element.addEventListener('input', renderWatermarkPreview);
            element.addEventListener('change', renderWatermarkPreview);
        }

        [
            watermarkEnableInput,
            watermarkTextarea,
            watermarkColorInput,
            watermarkOpacityInput,
            watermarkFontInput,
            watermarkRotationInput,
            watermarkFontFamilySelect,
            watermarkPositionSelect
        ].forEach(bindPreviewListener);

        function fillForm(values) {
            const highlightColors = values.highlight_colors || {};

            Object.keys(colorLabels).forEach(function (key) {
                const el = document.getElementById('spv-color-' + key);
                if (el) {
                    const newValue = highlightColors[key] || '#ffffff';
                    el.value = newValue;
                    updateLinkedColorTextInput('spv-color-' + key, newValue);
                }
            });
            Object.keys(themeInputs).forEach(function (key) {
                const themeValue = (values.theme_colors && values.theme_colors[key])
                    || (data.defaults.theme_colors && data.defaults.theme_colors[key])
                    || themeFallbacks[key];
                themeInputs[key].value = themeValue;
                updateLinkedColorTextInput('spv-theme-' + key, themeValue);
            });
            opacityInput.value = values.highlight_opacity;
            copyCheckbox.checked = !!values.copy_protection;
            watermarkEnableInput.checked = !!values.watermark_enabled;
            watermarkTextarea.value = values.watermark_text;
            watermarkColorInput.value = values.watermark_color;
            updateLinkedColorTextInput('spv-watermark-color', values.watermark_color);
            watermarkOpacityInput.value = values.watermark_opacity;
            watermarkFontInput.value = values.watermark_font_size;
            watermarkRotationInput.value = values.watermark_rotation;
            watermarkFontFamilySelect.value = values.watermark_font_family || 'Arial';
            if (!watermarkFontFamilySelect.value) {
                watermarkFontFamilySelect.value = 'Arial';
            }
            if (watermarkFontFamilySelect.selectedIndex === -1) {
                watermarkFontFamilySelect.value = 'Arial';
            }
            watermarkPositionSelect.value = values.watermark_position || 'center';
            if (!watermarkPositionSelect.value) {
                watermarkPositionSelect.value = 'center';
            }
            if (watermarkPositionSelect.selectedIndex === -1) {
                watermarkPositionSelect.value = 'center';
            }
            document.getElementById('spv-zoom-default').value = values.default_zoom;
            document.getElementById('spv-zoom-min').value = values.min_zoom;
            document.getElementById('spv-zoom-max').value = values.max_zoom;
            renderWatermarkPreview();
        }

        function showMessage(text, type) {
            message.textContent = text;
            message.className = 'spv-pdf-settings-message notice ' + (type || 'notice-success');
        }

        function disableForm(isDisabled) {
            const controls = form.querySelectorAll('input, textarea, button');
            controls.forEach(function (el) {
                el.disabled = isDisabled;
            });
        }

        function saveSettings() {
            disableForm(true);
            showMessage('Guardando ajustes...', 'notice-info');

            const payload = collectPayload();

            window.fetch(data.restUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': data.nonce
                },
                body: JSON.stringify(payload)
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Error HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function (result) {
                    if (result && result.settings) {
                        data.defaults = result.settings;
                        fillForm(result.settings);
                        showMessage('Ajustes guardados correctamente.', 'notice-success');
                    } else {
                        showMessage('Los ajustes se guardaron pero la respuesta fue inesperada.', 'notice-warning');
                    }
                })
                .catch(function (error) {
                    console.error('SPV settings error:', error);
                    showMessage('Error al guardar los ajustes: ' + error.message, 'notice-error');
                })
                .finally(function () {
                    disableForm(false);
                });
        }

        function collectPayload() {
            const payload = {
                highlight_colors: {},
                theme_colors: {},
                highlight_opacity: parseFloat(opacityInput.value) || 0.4,
                copy_protection: copyCheckbox.checked ? 1 : 0,
                watermark_enabled: watermarkEnableInput.checked ? 1 : 0,
                watermark_text: watermarkTextarea.value,
                watermark_color: watermarkColorInput.value,
                watermark_opacity: parseFloat(watermarkOpacityInput.value) || 0.15,
                watermark_font_size: parseFloat(watermarkFontInput.value) || 14,
                watermark_font_family: watermarkFontFamilySelect.value || 'Arial',
                watermark_rotation: parseFloat(watermarkRotationInput.value) || -30,
                watermark_position: watermarkPositionSelect.value || 'center',
                default_zoom: parseFloat(document.getElementById('spv-zoom-default').value) || 1.5,
                min_zoom: parseFloat(document.getElementById('spv-zoom-min').value) || 0.5,
                max_zoom: parseFloat(document.getElementById('spv-zoom-max').value) || 3
            };

            Object.keys(colorLabels).forEach(function (key) {
                const el = document.getElementById('spv-color-' + key);
                if (el) {
                    payload.highlight_colors[key] = el.value;
                }
            });

            Object.keys(themeInputs).forEach(function (key) {
                if (themeInputs[key]) {
                    payload.theme_colors[key] = themeInputs[key].value;
                }
            });

            if (payload.min_zoom > payload.max_zoom) {
                payload.min_zoom = data.defaults.min_zoom;
                payload.max_zoom = data.defaults.max_zoom;
            }

            return payload;
        }

        fillForm(data.defaults);
    }

    document.addEventListener('DOMContentLoaded', renderApp);
})();
