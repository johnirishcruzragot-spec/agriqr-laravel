//
import QRCode from 'qrcode';

document.querySelectorAll('[data-qr-value]').forEach((canvas) => {
    QRCode.toCanvas(canvas, canvas.dataset.qrValue, {
        width: 160,
        margin: 1,
        color: { dark: '#173c24', light: '#ffffff' },
        errorCorrectionLevel: 'M',
    });
});

document.querySelectorAll('[data-add-row]').forEach((button) => {
    button.addEventListener('click', () => {
        const template = document.querySelector(button.dataset.addRow);
        const list = button.closest('section')?.querySelector('[data-repeatable-list]');
        if (!template || !list) return;

        const index = list.children.length;
        const row = template.content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[data-name]').forEach((field) => {
            field.name = field.dataset.name.replace('__INDEX__', String(index));
            delete field.dataset.name;
        });
        list.append(row);
        refreshDistributionSupplies();
    });
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-row]');
    if (!button) return;

    const list = button.closest('[data-repeatable-list]');
    if (list.children.length > 1) button.closest('.repeatable-row').remove();
});

function refreshDistributionSupplies() {
    const selectedProgram = document.querySelector('[data-program-select]')?.value;

    document.querySelectorAll('[data-distribution-supply]').forEach((select) => {
        const currentValue = select.value;
        let currentValueAvailable = false;

        [...select.options].forEach((option) => {
            if (!option.value) return;
            const available = Boolean(selectedProgram) && option.dataset.programId === selectedProgram;
            option.hidden = !available;
            if (option.value === currentValue && available) currentValueAvailable = true;
        });

        if (!currentValueAvailable) select.value = '';
        updateDistributionLimit(select);
    });
}

function updateDistributionLimit(select) {
    const option = select.selectedOptions[0];
    const row = select.closest('.repeatable-row');
    const input = row?.querySelector('input[type="number"]');
    const note = row?.querySelector('[data-distribution-limit]');
    if (!option || !option.value || !input || !note) {
        if (note) note.textContent = '';
        if (input) input.removeAttribute('max');
        return;
    }

    const available = Number(option.dataset.available);
    const programLimit = option.dataset.max ? Number(option.dataset.max) : null;
    const max = programLimit === null ? available : Math.min(available, programLimit);
    input.max = String(max);
    note.textContent = `${available} available${programLimit === null ? '' : ` · program limit ${programLimit}`}`;
}

document.querySelectorAll('[data-program-select]').forEach((select) => {
    select.addEventListener('change', refreshDistributionSupplies);
});
document.querySelectorAll('[data-distribution-supply]').forEach((select) => {
    select.addEventListener('change', () => updateDistributionLimit(select));
});
refreshDistributionSupplies();

document.querySelectorAll('[data-qr-scan]').forEach((button) => {
    const wrap = document.querySelector('[data-scanner-wrap]');
    const video = document.querySelector('[data-scanner-video]');
    const message = document.querySelector('[data-scanner-message]');
    const lookupForm = button.closest('[data-lookup-form]');
    let controls;

    button.addEventListener('click', async () => {
        if (!wrap || !video || !message || !lookupForm) return;
        wrap.hidden = false;
        message.textContent = 'Requesting camera access…';

        try {
            const { BrowserMultiFormatReader } = await import('@zxing/browser');
            const reader = new BrowserMultiFormatReader();
            controls = await reader.decodeFromVideoDevice(undefined, video, (result) => {
                if (!result) return;
                lookupForm.querySelector('[name="farmer_code"]').value = result.getText();
                controls?.stop();
                lookupForm.requestSubmit();
            });
            message.textContent = 'Point the camera at the farmer’s AgriQR code.';
        } catch (error) {
            message.textContent = error instanceof Error
                ? `Camera unavailable: ${error.message}`
                : 'Camera unavailable. Enter the farmer ID manually.';
        }
    });

    document.querySelector('[data-qr-stop]')?.addEventListener('click', () => {
        controls?.stop();
        wrap.hidden = true;
    });
});

document.querySelector('[data-menu-toggle]')?.addEventListener('click', () => {
    document.querySelector('[data-menu-toggle]')?.closest('.main-area')
        ?.previousElementSibling?.classList.toggle('is-open');
});
