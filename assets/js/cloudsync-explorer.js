(function ($, settings) {
    'use strict';

    function getLabel(key) {
        if (settings.labels && settings.labels[key]) {
            return settings.labels[key];
        }

        return key;
    }

    function toggleLoading($section, isLoading) {
        var $tree = $section.find('.cloudsync-tree').first();
        var $loading = $section.find('.cloudsync-tree__loading').first();

        if (! $tree.length || ! $loading.length) {
            return;
        }

        if (isLoading) {
            $tree.attr('aria-busy', 'true');
            $loading.removeAttr('hidden');
        } else {
            $tree.attr('aria-busy', 'false');
            $loading.attr('hidden', 'hidden');
        }
    }

    function renderItems($section, $list, items) {
        $list.empty();

        if (!items || !items.length) {
            $('<li/>', { 'class': 'cloudsync-tree__empty', text: getLabel('empty') }).appendTo($list);
            return;
        }

        items.forEach(function (item) {
            var isFolder = item.type === 'folder';
            var $item = $('<li/>', {
                'class': 'cloudsync-tree__item',
                'data-id': item.id || '',
                'data-type': item.type || 'file',
                'role': 'treeitem',
                'aria-expanded': 'false'
            });

            var $row = $('<div/>', { 'class': 'cloudsync-tree__row' });

            if (isFolder) {
                $('<button/>', {
                    'type': 'button',
                    'class': 'cloudsync-tree__toggle',
                    'aria-label': getLabel('toggle'),
                    'aria-expanded': 'false'
                }).appendTo($row);
            } else {
                $('<span/>', {
                    'class': 'cloudsync-tree__spacer',
                    'aria-hidden': 'true'
                }).appendTo($row);
            }

            $('<span/>', {
                'class': 'cloudsync-tree__icon',
                'aria-hidden': 'true',
                text: isFolder ? '📁' : '📄'
            }).appendTo($row);

            $('<span/>', {
                'class': 'cloudsync-tree__name',
                text: item.name || ''
            }).appendTo($row);

            var $meta = $('<span/>', { 'class': 'cloudsync-tree__meta' });

            if (item.modified_human) {
                $('<span/>', { 'class': 'cloudsync-tree__modified', text: item.modified_human }).appendTo($meta);
            }

            if (item.size_human) {
                $('<span/>', { 'class': 'cloudsync-tree__size', text: item.size_human }).appendTo($meta);
            }

            $meta.appendTo($row);

            var $actions = $('<span/>', { 'class': 'cloudsync-tree__actions' });

            if (item.web_url) {
                $('<a/>', {
                    'class': 'button button-small',
                    'href': item.web_url,
                    'target': '_blank',
                    'rel': 'noopener noreferrer',
                    'text': getLabel('open')
                }).appendTo($actions);

                $('<button/>', {
                    'type': 'button',
                    'class': 'button button-secondary button-small cloudsync-tree__copy',
                    'data-url': item.web_url,
                    'text': getLabel('copy')
                }).appendTo($actions);
            }

            $actions.appendTo($row);
            $row.appendTo($item);

            if (isFolder) {
                $('<ul/>', {
                    'class': 'cloudsync-tree__children',
                    'role': 'group',
                    'data-loaded': 'false'
                }).appendTo($item);
            }

            $list.append($item);
        });
    }

    function requestItems(service, parentId, onSuccess, onError, restUrl, nonce) {
        var params = { service: service };

        if (typeof parentId !== 'undefined' && parentId !== null) {
            params.parent = parentId;
        }

        $.ajax({
            url: restUrl,
            method: 'GET',
            data: params,
            dataType: 'json',
            beforeSend: function (xhr) {
                if (nonce) {
                    xhr.setRequestHeader('X-WP-Nonce', nonce);
                }
            }
        }).done(function (response) {
            onSuccess(response || []);
        }).fail(function (jqXHR) {
            var message = getLabel('error');

            if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                message = jqXHR.responseJSON.message;
            }

            onError(message);
        });
    }

    function initialiseExplorer() {
        var restUrl = settings.restUrl || '';
        var nonce = settings.nonce || '';
        var $container = $('#cloudsync-explorer');

        if (! $container.length || ! restUrl) {
            return;
        }

        function loadSection($section, parentId, $list) {
            var service = $section.data('service');

            if (!service || !$list.length) {
                return;
            }

            var $error = $section.find('.cloudsync-tree__error').first();
            $error.attr('hidden', 'hidden').text('');

            toggleLoading($section, true);

            requestItems(service, parentId, function (items) {
                renderItems($section, $list, items);
                toggleLoading($section, false);
                $list.attr('data-loaded', 'true');
            }, function (message) {
                toggleLoading($section, false);
                $list.empty();
                $error.text(message).removeAttr('hidden');
            }, restUrl, nonce);
        }

        function loadChildren($section, $item, $children) {
            var service = $section.data('service');
            var parentId = $item.data('id');
            var $error = $section.find('.cloudsync-tree__error').first();

            $error.attr('hidden', 'hidden').text('');
            toggleLoading($section, true);

            requestItems(service, parentId, function (items) {
                renderItems($section, $children, items);
                toggleLoading($section, false);
                $children.attr('data-loaded', 'true');
            }, function (message) {
                toggleLoading($section, false);
                $children.empty().append(
                    $('<li/>', { 'class': 'cloudsync-tree__empty', text: message })
                );
                $children.attr('data-loaded', 'true');
                $error.text(message).removeAttr('hidden');
            }, restUrl, nonce);
        }

        function initialLoad() {
            $container.find('.cloudsync-explorer-service').each(function () {
                var $section = $(this);

                if (String($section.data('connected')) !== '1') {
                    return;
                }

                var root = $section.data('root');
                var $list = $section.find('.cloudsync-tree__list').first();
                loadSection($section, root, $list);
            });
        }

        $container.on('click', '.cloudsync-tree__toggle', function (event) {
            event.preventDefault();

            var $toggle = $(this);
            var $item = $toggle.closest('.cloudsync-tree__item');
            var expanded = $item.attr('aria-expanded') === 'true';
            var $children = $item.children('.cloudsync-tree__children').first();

            if (!$children.length) {
                return;
            }

            if (expanded) {
                $item.attr('aria-expanded', 'false');
                $toggle.attr('aria-expanded', 'false');
                $children.slideUp(120);

                return;
            }

            $item.attr('aria-expanded', 'true');
            $toggle.attr('aria-expanded', 'true');

            if ($children.attr('data-loaded') === 'true') {
                $children.slideDown(120);
                return;
            }

            $children.slideDown(120);
            loadChildren($item.closest('.cloudsync-explorer-service'), $item, $children);
        });

        $container.on('click', '.cloudsync-tree__copy', function (event) {
            event.preventDefault();

            var $button = $(this);
            var url = $button.data('url');

            if (!url) {
                return;
            }

            var feedback = function () {
                var originalText = $button.data('original-text');

                if (!originalText) {
                    originalText = $button.text();
                    $button.data('original-text', originalText);
                }

                $button.text(getLabel('copied'));

                setTimeout(function () {
                    $button.text(getLabel('copy'));
                }, 2000);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(feedback);
                return;
            }

            var $temp = $('<input type="text" readonly style="position:absolute; left:-9999px;" />');
            $('body').append($temp);
            $temp.val(url).select();

            try {
                document.execCommand('copy');
            } catch (err) {
                // Ignore errors for older browsers.
            }

            $temp.remove();
            feedback();
        });

        $container.on('click', '.cloudsync-explorer-refresh', function (event) {
            event.preventDefault();

            var $section = $(this).closest('.cloudsync-explorer-service');

            if (! $section.length) {
                return;
            }

            var root = $section.data('root');
            var $list = $section.find('.cloudsync-tree__list').first();

            $list.attr('data-loaded', 'false');
            loadSection($section, root, $list);
        });

        initialLoad();
    }

    $(initialiseExplorer);
})(jQuery, window.cloudsyncExplorerData || {});
