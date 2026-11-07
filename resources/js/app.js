//
import QRCode from 'qrcode';

function applyTheme(theme, save = false) {
    const activeTheme = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.dataset.theme = activeTheme;

    document.querySelectorAll('[data-theme-logo]').forEach((logo) => {
        logo.src = activeTheme === 'dark' ? logo.dataset.darkLogo : logo.dataset.lightLogo;
    });

    const favicon = document.querySelector('#app-favicon');
    if (favicon) {
        favicon.href = activeTheme === 'dark' ? favicon.dataset.darkIcon : favicon.dataset.lightIcon;
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        const nextTheme = activeTheme === 'dark' ? 'light' : 'dark';
        toggle.setAttribute('aria-label', `Switch to ${nextTheme} mode`);
        toggle.setAttribute('aria-pressed', String(activeTheme === 'dark'));

        const label = toggle.querySelector('[data-theme-label]');
        if (label) {
            label.textContent = `${nextTheme === 'dark' ? 'Dark' : 'Light'} mode`;
        }
    });

    if (save) {
        try {
            localStorage.setItem('agriqr-theme', activeTheme);
        } catch {
            return;
        }
    }
}

applyTheme(document.documentElement.dataset.theme || 'light');

document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        applyTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark', true);
    });
});

document.querySelectorAll('[data-qr-value]').forEach((canvas) => {
    QRCode.toCanvas(canvas, canvas.dataset.qrValue, {
        width: 160,
        margin: 1,
        color: { dark: '#173c24', light: '#ffffff' },
        errorCorrectionLevel: 'M',
    });
});

const programRateValues = new Map();

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
        refreshProgramSeasonFields();
        refreshProgramRates();
    });
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-row]');
    if (!button) return;

    const list = button.closest('[data-repeatable-list]');
    if (list.children.length > 1) button.closest('.repeatable-row').remove();
    refreshProgramRates();
});

function updateSettingsOptionsVisibility(row) {
    const type = row.querySelector('[data-settings-field-type]');
    const options = row.querySelector('[data-settings-field-options]');
    if (type && options) options.hidden = type.value !== 'select';
}

document.querySelectorAll('[data-settings-field]').forEach((row) => {
    const type = row.querySelector('[data-settings-field-type]');
    type?.addEventListener('change', () => updateSettingsOptionsVisibility(row));
    updateSettingsOptionsVisibility(row);
});

document.querySelectorAll('[data-add-settings-field]').forEach((button) => {
    button.addEventListener('click', () => {
        const form = button.closest('form');
        const list = form?.querySelector('[data-settings-fields]');
        const template = form?.querySelector('[data-settings-field-template]');
        if (!form || !list || !(template instanceof HTMLTemplateElement)) return;

        const indexes = [...list.querySelectorAll('[name^="farmer_fields["]')]
            .map((field) => Number(field.name.match(/^farmer_fields\[(\d+)\]/)?.[1]))
            .filter(Number.isInteger);
        const index = indexes.length > 0 ? Math.max(...indexes) + 1 : 0;
        const row = template.content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[data-name]').forEach((field) => {
            field.name = field.dataset.name.replaceAll('__INDEX__', String(index));
            delete field.dataset.name;
        });
        list.append(row);
        row.querySelector('[data-settings-field-type]')?.addEventListener('change', () => updateSettingsOptionsVisibility(row));
        updateSettingsOptionsVisibility(row);
    });
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-settings-field]');
    if (button) button.closest('[data-settings-field]')?.remove();
});

document.querySelectorAll('[data-sensitive-settings-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm('Save these system-wide settings? This may change supply categories, farmer data fields, and distribution eligibility rules.')) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('[data-user-security-form]').forEach((form) => {
    const roleSelect = form.querySelector('[name="role"]');

    const updateModuleOptions = () => {
        const role = roleSelect?.value;

        form.querySelectorAll('[data-module-access-option]').forEach((option) => {
            const checkbox = option.querySelector('input[name="module_access[]"]');
            const allowedRoles = (option.dataset.roles ?? '').split(',');
            const allowed = allowedRoles.includes(role);

            option.hidden = !allowed;
            checkbox.disabled = !allowed;
        });
    };

    roleSelect?.addEventListener('change', updateModuleOptions);
    updateModuleOptions();

    form.addEventListener('submit', (event) => {
        const roleChanged = form.querySelector('[name="role"]')?.value !== form.dataset.originalRole;
        const deactivated = form.querySelector('[name="is_active"]')?.value === '0';
        const assigningAdmin = form.querySelector('[name="role"]')?.value === 'admin'
            && form.dataset.originalRole !== 'admin';
        const selectedModules = [...form.querySelectorAll('input[name="module_access[]"]:checked')]
            .map((checkbox) => checkbox.value)
            .join(',');
        const moduleAccessChanged = form.querySelector('[name="module_access[]"]')
            && selectedModules !== form.dataset.originalModules;
        const changingAccess = roleChanged || deactivated || moduleAccessChanged;

        if (changingAccess && !window.confirm(assigningAdmin
            ? 'Grant this account Admin access? This role has full system control.'
            : 'Apply this role, module access, or account status change?')) {
            event.preventDefault();
        }
    });
});

function refreshProgramSeasonFields() {
    const selectedSeason = document.querySelector('[data-program-season-select]')?.value ?? '';

    document.querySelectorAll('[data-season-group-option]').forEach((label) => {
        const checkbox = label.querySelector('input[type="checkbox"]');
        const available = Boolean(selectedSeason) && label.dataset.seasonId === selectedSeason;
        label.hidden = !available;
        checkbox.disabled = !available;
    });

    refreshProgramRates();
}

function refreshProgramRates() {
    const list = document.querySelector('[data-program-rates-list]');
    const template = document.querySelector('#program-rate-row-template');
    if (!list || !(template instanceof HTMLTemplateElement)) return;

    [...list.querySelectorAll('[data-program-rate-row]')].forEach((row) => {
        const key = `${row.dataset.supplyId}:${row.dataset.groupId}`;

        programRateValues.set(key, {
            quantity: row.querySelector('[data-rate-quantity]')?.value ?? row.dataset.rateQuantity ?? '',
            maximum: row.querySelector('[data-rate-maximum]')?.value ?? row.dataset.rateMaximum ?? '',
        });
    });
    const supplySelects = document.querySelectorAll('[data-program-items] select[name$="[supply_id]"]');
    const groupCheckboxes = document.querySelectorAll(
        '[data-season-group-option] input[name="season_group_ids[]"]:checked:not(:disabled)',
    );
    const rows = [];

    supplySelects.forEach((select) => {
        if (!select.value) return;

        const supplyName = select.selectedOptions[0]?.textContent.trim() ?? '';

        groupCheckboxes.forEach((checkbox) => {
            const groupId = checkbox.value;
            const key = `${select.value}:${groupId}`;
            const row = template.content.firstElementChild.cloneNode(true);
            const rate = programRateValues.get(key);

            row.dataset.supplyId = select.value;
            row.dataset.groupId = groupId;
            row.querySelector('[data-rate-supply-label]').textContent = supplyName;
            row.querySelector('[data-rate-group-label]').textContent = checkbox.closest('label').textContent.trim();
            row.querySelector('[data-name="rates[__INDEX__][supply_id]"]').value = select.value;
            row.querySelector('[data-name="rates[__INDEX__][season_group_id]"]').value = groupId;
            row.querySelectorAll('[data-name]').forEach((field) => {
                field.name = field.dataset.name.replaceAll('__INDEX__', String(rows.length));
                delete field.dataset.name;
            });
            row.querySelector('[data-rate-quantity]').value = rate?.quantity ?? '';
            row.querySelector('[data-rate-maximum]').value = rate?.maximum ?? '';
            rows.push(row);
        });
    });

    list.replaceChildren(...rows);

    const emptyMessage = document.querySelector('[data-program-rates-empty]');
    if (emptyMessage) emptyMessage.hidden = rows.length > 0;
}

function refreshDistributionSupplies() {
    const selectedProgram = document.querySelector('[data-program-select]')?.value;

    document.querySelectorAll('[data-season-group-select]').forEach((select) => {
        const currentValue = select.value;
        let currentValueAvailable = false;

        [...select.options].forEach((option) => {
            if (!option.value) return;
            const available = Boolean(selectedProgram) && option.dataset.programId === selectedProgram;
            option.hidden = !available;
            if (option.value === currentValue && available) currentValueAvailable = true;
        });

        if (!currentValueAvailable) select.value = '';
        updateBenefitCapNote(select);
    });

    const selectedAssignment = document.querySelector('[data-season-group-select]')?.value;
    document.querySelectorAll('[data-distribution-supply]').forEach((select) => {
        const currentValue = select.value;
        let currentValueAvailable = false;

        [...select.options].forEach((option) => {
            if (!option.value) return;
            const available = Boolean(selectedProgram)
                && Boolean(selectedAssignment)
                && option.dataset.programId === selectedProgram
                && option.dataset.assignmentId === selectedAssignment
                && option.dataset.alreadyIssued !== 'true';
            option.hidden = !available;
            if (option.value === currentValue && available) currentValueAvailable = true;
        });

        if (!currentValueAvailable) select.value = '';
        updateDistributionLimit(select);
    });
}

function updateBenefitCapNote(select) {
    const option = select.selectedOptions[0];
    const note = document.querySelector('[data-benefit-cap-note]');

    if (!note || !option || !option.value) {
        if (note) note.textContent = 'Select a program and season group to view supply quantity limits.';
        return;
    }

    if (!option.dataset.hectares) {
        note.textContent = 'A valid RSBSA farm size is required to calculate supply quantity limits.';
        return;
    }

    note.textContent = `${Number(option.dataset.hectares).toFixed(4)} ha. Each supply is limited by its group rate and per-farmer maximum.`;
}

function updateDistributionLimit(select) {
    const option = select.selectedOptions[0];
    const row = select.closest('.repeatable-row');
    const input = row?.querySelector('input[type="number"]');
    const note = row?.querySelector('[data-distribution-limit]');
    const reasonField = row?.querySelector('.distribution-reduction-reason');
    const reasonInput = reasonField?.querySelector('[data-reduction-reason]');
    if (!option || !option.value || !input || !note) {
        if (note) note.textContent = '';
        if (input) {
            input.disabled = false;
            input.removeAttribute('max');
        }
        if (reasonInput) reasonInput.required = false;
        if (reasonField) reasonField.hidden = true;
        return;
    }

    const available = Number(option.dataset.available);
    const quantityCap = Number(option.dataset.cap);
    const allowedQuantity = Number(option.dataset.allowed);
    const max = Math.min(available, allowedQuantity);
    input.max = String(max);
    const quantity = Number(input.value);
    const reduced = input.value !== '' && quantity < quantityCap;
    if (reasonInput) {
        reasonInput.required = reduced;
        if (!reduced) reasonInput.value = '';
    }
    if (reasonField) reasonField.hidden = !reduced;

    if (option.dataset.alreadyIssued === 'true') {
        note.textContent = 'This supply has already been issued to this farmer for this season.';
        input.disabled = true;
        return;
    }

    input.disabled = false;
    const calculated = Number(option.dataset.calculated);
    const configuredMaximum = Number(option.dataset.maximum);
    const shortage = Number(option.dataset.shortage);
    note.textContent = `${calculated.toFixed(2)} calculated · ${configuredMaximum.toFixed(2)} configured maximum · `
        + `${allowedQuantity.toFixed(2)} allowed · `
        + `${available.toFixed(2)} available${shortage > 0 ? ` · ${shortage.toFixed(2)} shortage at full allowance` : ''}. `
        + (reduced ? 'Enter a reduction reason.' : 'You may reduce the quantity with a reason.');
}

function updateDistributionEligibility() {
    const form = document.querySelector('[data-distribution-form]');
    if (!form) return;

    const program = form.querySelector('[data-program-select]')?.selectedOptions[0];
    const rsbsaRequired = form.dataset.requiresVerifiedRsbsa === 'true'
        || program?.dataset.requiresRsbsa === 'true';
    const eligible = form.dataset.farmerDeceased !== 'true'
        && (form.dataset.requiresActive !== 'true' || form.dataset.farmerActive === 'true')
        && form.dataset.hasPhoto === 'true'
        && form.dataset.farmSizeValid === 'true'
        && (!rsbsaRequired || form.dataset.rsbsaVerified === 'true');
    const submit = form.querySelector('[data-distribution-submit]');
    const note = form.querySelector('[data-distribution-rsbsa-note]');

    if (submit) submit.disabled = !eligible;
    if (note) {
        note.hidden = !program || (form.dataset.rsbsaVerified === 'true' || !rsbsaRequired);
        note.textContent = 'This program requires a registered and verified RSBSA record. Distribution is blocked until the farmer record is verified.';
    }
}

document.querySelectorAll('[data-distribution-form]').forEach((form) => {
    form.addEventListener('input', (event) => {
        if (event.target.matches('input[type="number"]')) {
            const select = event.target.closest('.repeatable-row')?.querySelector('[data-distribution-supply]');
            if (select) updateDistributionLimit(select);
        }
    });
});

document.querySelectorAll('[data-program-season-select]').forEach((select) => {
    select.addEventListener('change', refreshProgramSeasonFields);
});
document.querySelector('[data-program-form]')?.addEventListener('change', (event) => {
    if (event.target.matches(
        '[data-program-items] select[name$="[supply_id]"], [data-season-group-option] input[name="season_group_ids[]"]',
    )) {
        refreshProgramRates();
    }
});
document.querySelectorAll('[data-program-select]').forEach((select) => {
    select.addEventListener('change', () => {
        refreshDistributionSupplies();
        updateDistributionEligibility();
    });
});
document.querySelectorAll('[data-distribution-supply]').forEach((select) => {
    select.addEventListener('change', () => updateDistributionLimit(select));
});
document.querySelectorAll('[data-season-group-select]').forEach((select) => {
    select.addEventListener('change', () => {
        updateBenefitCapNote(select);
        refreshDistributionSupplies();
    });
});
refreshProgramSeasonFields();
refreshDistributionSupplies();
updateDistributionEligibility();

document.querySelectorAll('[data-supply-category]').forEach((select) => {
    const updateUnitRequirement = () => {
        const unitInput = document.querySelector('[data-supply-unit]');
        const requiredMark = document.querySelector('[data-unit-required]');
        if (!unitInput || !requiredMark) return;

        const unitOptionalCategories = (select.dataset.unitOptionalCategories ?? '').split('|');
        const unitOptional = unitOptionalCategories.includes(select.value);
        unitInput.required = !unitOptional;
        requiredMark.hidden = unitOptional;
    };

    select.addEventListener('change', updateUnitRequirement);
    updateUnitRequirement();
});

document.querySelectorAll('[data-farm-size-unit]').forEach((unitSelect) => {
    const sizeInput = unitSelect.closest('.input-with-suffix')?.querySelector('[data-farm-size]');
    if (!sizeInput) return;

    const updateMaximum = () => {
        const maximum = unitSelect.selectedOptions[0]?.dataset.maximum;
        if (maximum) sizeInput.max = maximum;
    };

    unitSelect.addEventListener('change', updateMaximum);
    updateMaximum();
});

function setRegistrationStep(dialog, stepNumber) {
    const form = dialog.querySelector('[data-registration-wizard]');
    if (!form) return;

    form.querySelectorAll('[data-registration-step]').forEach((step) => {
        step.hidden = Number(step.dataset.registrationStep) !== stepNumber;
    });

    const isFirstStep = stepNumber === 1;
    const status = dialog.querySelector('[data-registration-status]');
    const nextButton = form.querySelector('[data-registration-next]');
    const backButton = form.querySelector('[data-registration-back]');
    const submitButton = form.querySelector('[data-registration-submit]');

    if (status) {
        status.textContent = isFirstStep
            ? 'Step 1 of 2: Personal information'
            : 'Step 2 of 2: Farm and RSBSA registration';
    }

    if (nextButton) nextButton.hidden = !isFirstStep;
    if (backButton) backButton.hidden = isFirstStep;
    if (submitButton) submitButton.hidden = isFirstStep;
}

function isStepValid(step) {
    const fields = [...step.querySelectorAll('input, select, textarea')];
    const firstInvalidField = fields.find((field) => !field.checkValidity());

    if (firstInvalidField) {
        firstInvalidField.reportValidity();
        return false;
    }

    return true;
}

document.querySelectorAll('[data-registration-dialog]').forEach((dialog) => {
    const form = dialog.querySelector('[data-registration-wizard]');
    const firstStep = form?.querySelector('[data-registration-step="1"]');
    const secondStep = form?.querySelector('[data-registration-step="2"]');
    const nextButton = form?.querySelector('[data-registration-next]');
    const backButton = form?.querySelector('[data-registration-back]');

    if (!form || !firstStep || !secondStep) return;

    setRegistrationStep(dialog, Number(dialog.dataset.openStep) === 2 ? 2 : 1);

    nextButton?.addEventListener('click', () => {
        if (isStepValid(firstStep)) setRegistrationStep(dialog, 2);
    });

    backButton?.addEventListener('click', () => {
        setRegistrationStep(dialog, 1);
    });

    form.addEventListener('submit', (event) => {
        if (!isStepValid(firstStep)) {
            event.preventDefault();
            setRegistrationStep(dialog, 1);
            return;
        }

        if (!isStepValid(secondStep)) {
            event.preventDefault();
            setRegistrationStep(dialog, 2);
        }
    });

    dialog.addEventListener('close', () => {
        setRegistrationStep(dialog, 1);
    });

    if (dialog.hasAttribute('data-open-on-load')) dialog.showModal();
});

document.querySelectorAll('[data-open-dialog]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = document.getElementById(button.dataset.openDialog);
        if (dialog instanceof HTMLDialogElement) {
            if (dialog.hasAttribute('data-registration-dialog')) setRegistrationStep(dialog, 1);
            dialog.showModal();
        }
    });
});

document.querySelectorAll('[data-close-dialog]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = button.closest('dialog');
        if (dialog instanceof HTMLDialogElement) dialog.close();
    });
});

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
