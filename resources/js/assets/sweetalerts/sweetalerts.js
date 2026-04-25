import Swal from 'sweetalert2';

const iconMap = (type) => {
    switch (type) {
        case 'success':
            return 'success';
        case 'warning':
            return 'warning';
        case 'error':
            return 'error';
        case 'info':
        default:
            return 'info';
    }
};

window.addEventListener('swal:toast', (event) => {
    const detail = event.detail || {};
    const type = detail.type || 'info';
    const title = detail.title ?? ({
        success: 'Erfolg!',
        warning: 'Warnung!',
        error: 'Fehler!',
        info: 'Hinweis!',
    }[type] || 'Hinweis!');

    const showConfirm = detail.showConfirm ?? Boolean(detail.redirectTo);

    Swal.fire({
        toast: true,
        position: detail.position || 'top-end',
        icon: iconMap(type),
        title,
        text: detail.text ?? undefined,
        html: detail.html ?? undefined,
        timer: showConfirm ? undefined : (detail.timer ?? 4000),
        timerProgressBar: !showConfirm,
        showConfirmButton: showConfirm,
        confirmButtonText: detail.confirmText || 'OK',
    }).then((result) => {
        if ((result.isConfirmed || result.dismiss === Swal.DismissReason.timer) && detail.redirectTo) {
            window.location.assign(detail.redirectTo);
        }
    });
});

window.addEventListener('swal:alert', async (event) => {
    const detail = event.detail || {};
    const type = detail.type || 'info';

    const result = await Swal.fire({
        icon: iconMap(type),
        title: detail.title || 'Hinweis',
        text: detail.text ?? undefined,
        html: detail.html ?? undefined,
        confirmButtonText: detail.confirmText || 'OK',
        showCancelButton: Boolean(detail.showCancel),
        cancelButtonText: detail.cancelText || 'Abbrechen',
        allowOutsideClick: detail.allowOutsideClick ?? true,
    });

    if (detail.onConfirm && result.isConfirmed) {
        window.dispatchEvent(new CustomEvent(detail.onConfirm.name || 'swal:confirmed', {
            detail: detail.onConfirm.detail || {},
        }));
    }

    const redirectOn = detail.redirectOn || 'confirm';
    const shouldRedirect = detail.redirectTo
        && (
            (redirectOn === 'confirm' && result.isConfirmed)
            || (redirectOn === 'close' && (result.isDismissed || result.isDenied))
        );

    if (shouldRedirect) {
        window.location.assign(detail.redirectTo);
    }
});
