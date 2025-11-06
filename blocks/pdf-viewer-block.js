(function (blocks, element, blockEditor, components, data, i18n, notices) {
    var el = element.createElement;
    var Fragment = element.Fragment;
    var useMemo = element.useMemo;
    var registerBlockType = blocks.registerBlockType;
    var MediaUpload = blockEditor.MediaUpload;
    var MediaUploadCheck = blockEditor.MediaUploadCheck;
    var InspectorControls = blockEditor.InspectorControls;
    var MediaReplaceFlow = blockEditor.MediaReplaceFlow;
    var useBlockProps = blockEditor.useBlockProps;
    var blockEditorStore = blockEditor.store;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var ToolbarButton = components.ToolbarButton;
    var Button = components.Button;
    var __ = i18n.__;
    var useDispatch = data.useDispatch;
    var useSelect = data.useSelect;
    var noticesStore = notices && notices.store;

    var iconEl = el(
        'svg',
        { width: 24, height: 24, viewBox: '0 0 24 24', role: 'img', focusable: 'false' },
        el('path', {
            d: 'M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z',
            fill: 'currentColor'
        })
    );

    registerBlockType('secure-pdf-viewer/pdf-viewer', {
        title: __('Secure PDF Viewer', 'secure-pdf-viewer'),
        description: __('Embed secured PDF documents with a branded container.', 'secure-pdf-viewer'),
        icon: iconEl,
        category: 'embed',
        supports: {
            align: true,
            alignWide: true,
            spacing: {
                margin: true,
                padding: true
            }
        },
        attributes: {
            pdfUrl: {
                type: 'string',
                default: ''
            },
            pdfId: {
                type: 'number',
                default: 0
            },
            title: {
                type: 'string',
                default: ''
            },
            width: {
                type: 'string',
                default: '100%'
            },
            height: {
                type: 'string',
                default: '600px'
            }
        },

        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var pdfUrl = attributes.pdfUrl;
            var blockProps = useBlockProps({
                className: pdfUrl ? 'spv-block spv-block--has-pdf' : 'spv-block spv-block--placeholder'
            });

            var onSelectPDF = function (media) {
                if (!media) {
                    return;
                }

                setAttributes({
                    pdfUrl: media.url || '',
                    pdfId: media.id || 0,
                    title: media.title || ''
                });
            };

            var onSelectURL = function (url) {
                if (!url) {
                    return;
                }

                setAttributes({
                    pdfUrl: url,
                    pdfId: 0
                });
            };

            var onRemovePDF = function () {
                setAttributes({
                    pdfUrl: '',
                    pdfId: 0
                });
            };

            var inspectorControls = el(
                InspectorControls,
                {},
                el(
                    PanelBody,
                    { title: __('Document settings', 'secure-pdf-viewer'), initialOpen: true },
                    el(TextControl, {
                        label: __('Custom title', 'secure-pdf-viewer'),
                        value: attributes.title,
                        onChange: function (value) {
                            setAttributes({ title: value });
                        }
                    }),
                    el(TextControl, {
                        label: __('Width', 'secure-pdf-viewer'),
                        value: attributes.width,
                        onChange: function (value) {
                            setAttributes({ width: value });
                        },
                        help: __('Accepts CSS units such as %, px, or vw.', 'secure-pdf-viewer')
                    }),
                    el(TextControl, {
                        label: __('Height', 'secure-pdf-viewer'),
                        value: attributes.height,
                        onChange: function (value) {
                            setAttributes({ height: value });
                        },
                        help: __('Accepts CSS units such as px or vh.', 'secure-pdf-viewer')
                    })
                )
            );

            var placeholderCard = el(
                'div',
                { className: 'spv-card' },
                el(
                    'div',
                    { className: 'spv-card__header' },
                    el(
                        'div',
                        { className: 'spv-card__icon' },
                        iconEl
                    ),
                    el(
                        'div',
                        { className: 'spv-card__titles' },
                        el('p', { className: 'spv-card__title' }, __('Secure PDF Viewer', 'secure-pdf-viewer')),
                        el('p', { className: 'spv-card__subtitle' }, __('Upload a file or pick one from your media library for embed.', 'secure-pdf-viewer'))
                    )
                ),
                el(
                    'div',
                    { className: 'spv-card__actions' },
                    el(
                        MediaUploadCheck,
                        {},
                        el(MediaUpload, {
                            onSelect: onSelectPDF,
                            allowedTypes: ['application/pdf'],
                            value: attributes.pdfId,
                            render: function (obj) {
                                return el(Button, {
                                    variant: 'primary',
                                    onClick: obj.open
                                }, __('Biblioteca de medios', 'secure-pdf-viewer'));
                            }
                        })
                    )
                ),
                el(
                    'div',
                    { className: 'spv-card__footer' },
                    el(
                        'a',
                        {
                            href: 'https://wordpress.org/support/article/embeds/',
                            target: '_blank',
                            rel: 'noopener noreferrer',
                            className: 'spv-card__link'
                        },
                        el('span', { className: 'dashicons dashicons-external', 'aria-hidden': 'true' }),
                        el('span', { className: 'spv-card__link-text' }, __('Learn more about Embedded document', 'secure-pdf-viewer'))
                    )
                )
            );

            var documentTitle = useMemo(function () {
                if (attributes.title) {
                    return attributes.title;
                }

                if (pdfUrl) {
                    return pdfUrl.split('/').pop();
                }

                return '';
            }, [attributes.title, pdfUrl]);

            var previewCard = el(
                'div',
                { className: 'spv-preview' },
                el(
                    'div',
                    { className: 'spv-preview__header' },
                    el(
                        'div',
                        { className: 'spv-preview__meta' },
                        el('span', { className: 'dashicons dashicons-media-document', 'aria-hidden': 'true' }),
                        el('span', { className: 'spv-preview__title' }, documentTitle)
                    ),
                    el(
                        'div',
                        { className: 'spv-preview__actions' },
                        MediaReplaceFlow ? el(MediaReplaceFlow, {
                            mediaId: attributes.pdfId,
                            mediaURL: pdfUrl,
                            allowedTypes: ['application/pdf'],
                            onSelect: onSelectPDF,
                            onSelectURL: onSelectURL,
                            render: function (obj) {
                                return el(Button, {
                                    variant: 'secondary',
                                    className: 'spv-preview__action',
                                    onClick: obj.open
                                }, __('Reemplazar documento', 'secure-pdf-viewer'));
                            }
                        }) : null,
                        el(Button, {
                            variant: 'secondary',
                            isDestructive: true,
                            className: 'spv-preview__action spv-preview__action--remove',
                            onClick: onRemovePDF
                        }, __('Eliminar documento', 'secure-pdf-viewer'))
                    )
                ),
                el(
                    'div',
                    { className: 'spv-preview__body' },
                    el('iframe', {
                        src: pdfUrl,
                        title: documentTitle || __('Embedded document preview', 'secure-pdf-viewer'),
                        style: {
                            width: attributes.width || '100%',
                            height: attributes.height || '600px',
                            border: '0'
                        }
                    })
                )
            );

            return el(
                Fragment,
                {},
                inspectorControls,
                el('div', blockProps, pdfUrl ? previewCard : placeholderCard)
            );
        },

        save: function () {
            return null;
        }
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.data,
    window.wp.i18n,
    window.wp.notices
);
