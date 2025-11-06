(function () {
    const settings = window.cloudsyncOAuthData || {
        popupName: 'cloudsync-oauth-popup',
        popupWidth: 600,
        popupHeight: 700,
    };

    let activeGuide = null;
    let lastGuideTrigger = null;

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

    function closeGuideModal() {
        const backdrop = document.querySelector('[data-cloudsync-guide-backdrop]');

        if (activeGuide) {
            activeGuide.setAttribute('aria-hidden', 'true');
            activeGuide.setAttribute('hidden', 'hidden');
            activeGuide = null;
        }

        if (backdrop) {
            backdrop.setAttribute('hidden', 'hidden');
        }

        document.body.classList.remove('cloudsync-guide-open');

        if (lastGuideTrigger && typeof lastGuideTrigger.focus === 'function') {
            lastGuideTrigger.focus();
        }

        lastGuideTrigger = null;
    }

    function openGuideModal(modal, trigger) {
        const backdrop = document.querySelector('[data-cloudsync-guide-backdrop]');

        if (!modal) {
            return;
        }

        modal.removeAttribute('hidden');
        modal.setAttribute('aria-hidden', 'false');

        if (backdrop) {
            backdrop.removeAttribute('hidden');
        }

        document.body.classList.add('cloudsync-guide-open');
        activeGuide = modal;
        lastGuideTrigger = trigger || null;

        try {
            modal.focus();
        } catch (error) {
            // Ignore focus errors in legacy browsers.
        }
    }

    function handleGuideClick(event) {
        const trigger = event.currentTarget;
        const service = trigger.getAttribute('data-service');

        if (!service) {
            return;
        }

        event.preventDefault();

        const modal = document.getElementById(`cloudsync-guide-${service}`);

        if (!modal) {
            return;
        }

        openGuideModal(modal, trigger);
    }

    function bindGuideButtons() {
        const guideButtons = document.querySelectorAll('.js-cloudsync-guide');

        guideButtons.forEach((button) => {
            button.addEventListener('click', handleGuideClick);
        });
    }

    function bindGuideClosers() {
        const closeButtons = document.querySelectorAll('.cloudsync-guide-modal__close');
        const backdrop = document.querySelector('[data-cloudsync-guide-backdrop]');

        closeButtons.forEach((button) => {
            button.addEventListener('click', closeGuideModal);
        });

        if (backdrop) {
            backdrop.addEventListener('click', closeGuideModal);
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeGuideModal();
            }
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
        bindGuideButtons();
        bindGuideClosers();
        listenForCompletion();
        notifyParentIfPopup();
    });
})();
