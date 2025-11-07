(function () {
    const settings = window.cloudsyncOAuthData || {
        popupName: 'cloudsync-oauth-popup',
        popupWidth: 600,
        popupHeight: 700,
    };

    let activeGuide = null;
    let lastGuideTrigger = null;

    window.cloudsyncOAuthPopup = function (url) {
        if (!url) {
            return false;
        }

        const width = settings.popupWidth || 600;
        const height = settings.popupHeight || 700;
        const dualScreenLeft = window.screenLeft !== undefined ? window.screenLeft : window.screenX;
        const dualScreenTop = window.screenTop !== undefined ? window.screenTop : window.screenY;
        const screenWidth = window.innerWidth || document.documentElement.clientWidth || screen.width;
        const screenHeight = window.innerHeight || document.documentElement.clientHeight || screen.height;
        const left = dualScreenLeft + Math.max(0, (screenWidth - width) / 2);
        const top = dualScreenTop + Math.max(0, (screenHeight - height) / 2);
        const features = `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`;

        const popup = window.open(url, settings.popupName || 'cloudsync-oauth-popup', features);

        if (popup) {
            popup.focus();

            const watcher = window.setInterval(() => {
                if (popup.closed) {
                    window.clearInterval(watcher);
                    window.location.reload();
                }
            }, 800);
        }

        return false;
    };

    function handleConnectClick(event) {
        const trigger = event.currentTarget;
        const url = trigger.getAttribute('data-oauth-url');

        if (!url) {
            return;
        }

        event.preventDefault();
        window.cloudsyncOAuthPopup(url);
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

    document.addEventListener('DOMContentLoaded', () => {
        bindConnectButtons();
        bindGuideButtons();
        bindGuideClosers();
    });
})();
