(function () {
    const settings = window.cloudsyncOAuthData || {
        popupName: 'cloudsync-oauth-popup',
        popupWidth: 600,
        popupHeight: 700,
    };

    function handleConnectClick(event) {
        const trigger = event.currentTarget;
        const url = trigger.getAttribute('data-oauth-url');

        if (!url) {
            return;
        }

        event.preventDefault();

        const left = window.screenX + Math.max(0, (window.outerWidth - settings.popupWidth) / 2);
        const top = window.screenY + Math.max(0, (window.outerHeight - settings.popupHeight) / 2);
        const features = `width=${settings.popupWidth},height=${settings.popupHeight},left=${left},top=${top},resizable=yes,scrollbars=yes`;

        const popup = window.open(url, settings.popupName, features);

        if (popup) {
            popup.focus();
        }
    }

    function bindConnectButtons() {
        const buttons = document.querySelectorAll('.js-cloudsync-connect');

        buttons.forEach((button) => {
            button.addEventListener('click', handleConnectClick);
        });
    }

    function listenForCompletion() {
        window.addEventListener('message', (event) => {
            if (!event.data || event.data.type !== 'cloudsync_oauth_complete') {
                return;
            }

            window.location.reload();
        });
    }

    function notifyParentIfPopup() {
        if (!window.opener || window.opener.closed) {
            return;
        }

        try {
            const params = new URLSearchParams(window.location.search);
            if (params.get('cloudsync_popup') === '1') {
                window.opener.postMessage({ type: 'cloudsync_oauth_complete' }, window.location.origin);
                window.close();
            }
        } catch (error) {
            // Silently fail if URL parsing is not available.
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        bindConnectButtons();
        listenForCompletion();
        notifyParentIfPopup();
    });
})();
