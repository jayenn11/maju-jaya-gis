/**
 * Global Reusable UI Components & Helpers
 * SIG Distribusi Makanan - PT Maju Jaya Food (Bandung)
 * - initCustomDropdown() / setCustomDropdownValue()
 * - showConfirmModal() (Pengganti window.confirm)
 * - showToast() (Notifikasi CRUD)
 * - debounce() (300ms Live Search)
 * - bindNumericPhoneInput() (Validasi angka saja untuk nomor telepon)
 */

(function (window) {
    'use strict';

    /**
     * Helper Debounce (default 300ms sesuai AGENTS.md)
     */
    function debounce(fn, delay) {
        let timer = null;
        const wait = typeof delay === 'number' ? delay : 300;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    /**
     * Escape HTML di sisi klien
     */
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Global Toast Notification (Bootstrap 5)
     */
    function showToast({ title = 'Informasi', message = '', type = 'success' } = {}) {
        const container = document.getElementById('globalToastContainer');
        if (!container) return;

        const iconMap = {
            success: 'bi-check-circle-fill text-success',
            danger: 'bi-x-circle-fill text-danger',
            error: 'bi-x-circle-fill text-danger',
            warning: 'bi-exclamation-triangle-fill text-warning',
            info: 'bi-info-circle-fill text-primary'
        };
        const borderMap = {
            success: 'border-success',
            danger: 'border-danger',
            error: 'border-danger',
            warning: 'border-warning',
            info: 'border-primary'
        };

        const iconClass = iconMap[type] || iconMap.info;
        const borderClass = borderMap[type] || borderMap.info;

        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center bg-white border-0 border-start border-4 ${borderClass} shadow rounded-3 mb-2`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');

        toastEl.innerHTML = `
            <div class="d-flex p-3 align-items-start gap-2">
                <i class="bi ${iconClass} fs-5 lh-1 mt-1"></i>
                <div class="flex-grow-1 pe-2">
                    <div class="fw-bold text-dark small mb-1">${escapeHtml(title)}</div>
                    <div class="text-secondary small">${escapeHtml(message)}</div>
                </div>
                <button type="button" class="btn-close btn-sm mt-1" data-bs-dismiss="toast" aria-label="Tutup"></button>
            </div>
        `;

        container.appendChild(toastEl);
        const bsToast = new bootstrap.Toast(toastEl, { delay: 4000 });
        bsToast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }

    /**
     * Global Custom Confirmation Modal (Pengganti window.confirm)
     */
    let activeConfirmCallback = null;
    function showConfirmModal({
        title = 'Konfirmasi Tindakan',
        message = 'Apakah Anda yakin ingin melanjutkan tindakan ini?',
        confirmText = 'Ya, Lanjutkan',
        cancelText = 'Batal',
        variant = 'danger',
        onConfirm = null
    } = {}) {
        const modalEl = document.getElementById('globalConfirmModal');
        if (!modalEl) return;

        const titleEl = document.getElementById('globalConfirmTitle');
        const msgEl = document.getElementById('globalConfirmMessage');
        const confirmBtn = document.getElementById('globalConfirmBtn');
        const cancelBtn = document.getElementById('globalCancelBtn');
        const iconWrap = document.getElementById('globalConfirmIconWrap');
        const iconEl = document.getElementById('globalConfirmIcon');

        titleEl.textContent = title;
        msgEl.textContent = message;
        confirmBtn.textContent = confirmText;
        cancelBtn.textContent = cancelText;

        confirmBtn.className = `btn btn-${variant} px-4 fw-semibold`;
        if (variant === 'danger') {
            iconWrap.className = 'mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10 text-danger';
            iconEl.className = 'bi bi-trash3-fill fs-3';
        } else if (variant === 'warning') {
            iconWrap.className = 'mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-10 text-warning';
            iconEl.className = 'bi bi-exclamation-triangle-fill fs-3';
        } else {
            iconWrap.className = 'mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 text-primary';
            iconEl.className = 'bi bi-question-circle-fill fs-3';
        }

        activeConfirmCallback = onConfirm;
        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    }

    /**
     * Custom Searchable Dropdown Component (Tanpa <select> bawaan browser)
     * Mendukung:
     * <div class="custom-dropdown" data-custom-dropdown data-name="category" data-placeholder="Pilih Kategori">
     *   <input type="hidden" name="category" value="">
     *   <script type="application/json" class="dropdown-options">[{"value":"Toko","label":"Toko"}]</script>
     * </div>
     */
    function buildDropdownMarkup(container) {
        const hiddenInput = container.querySelector('input[type="hidden"]');
        const nameAttr = container.dataset.name || (hiddenInput ? hiddenInput.name : '');
        const inputId = container.dataset.inputId || (hiddenInput ? hiddenInput.id : '');
        const placeholder = container.dataset.placeholder || 'Pilih opsi...';
        const searchPlaceholder = container.dataset.searchPlaceholder || 'Ketik untuk mencari...';

        let options = [];
        const jsonScript = container.querySelector('.dropdown-options');
        if (jsonScript) {
            try {
                options = JSON.parse(jsonScript.textContent || '[]');
            } catch (e) {
                options = [];
            }
        } else if (container.dataset.options) {
            try {
                options = JSON.parse(container.dataset.options);
            } catch (e) {
                options = [];
            }
        }

        const initialValue = hiddenInput ? hiddenInput.value : (container.dataset.value || '');
        container._dropdownOptions = options;
        container._placeholder = placeholder;

        container.innerHTML = `
            <input type="hidden" name="${escapeHtml(nameAttr)}" ${inputId ? `id="${escapeHtml(inputId)}"` : ''} value="${escapeHtml(initialValue)}">
            <button type="button" class="custom-dropdown-toggle" aria-haspopup="listbox" aria-expanded="false">
                <span class="custom-dropdown-label text-truncate placeholder-active">${escapeHtml(placeholder)}</span>
                <i class="bi bi-chevron-down custom-dropdown-caret"></i>
            </button>
            <div class="custom-dropdown-menu" role="listbox">
                <div class="custom-dropdown-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" class="custom-dropdown-search" placeholder="${escapeHtml(searchPlaceholder)}" autocomplete="off">
                </div>
                <ul class="custom-dropdown-list"></ul>
            </div>
        `;

        const toggleBtn = container.querySelector('.custom-dropdown-toggle');
        const searchInput = container.querySelector('.custom-dropdown-search');
        const listEl = container.querySelector('.custom-dropdown-list');

        function renderOptions(filterText = '') {
            const q = filterText.trim().toLowerCase();
            const currentVal = container.querySelector('input[type="hidden"]').value;
            const filtered = (container._dropdownOptions || []).filter(opt => {
                const label = String(opt.label ?? opt.value ?? '').toLowerCase();
                const sub = String(opt.sub ?? '').toLowerCase();
                return q === '' || label.includes(q) || sub.includes(q);
            });

            if (filtered.length === 0) {
                listEl.innerHTML = `<li class="custom-dropdown-empty">Tidak ada opsi yang cocok</li>`;
                return;
            }

            listEl.innerHTML = filtered.map(opt => {
                const val = String(opt.value ?? '');
                const label = String(opt.label ?? opt.value ?? '');
                const badge = opt.badge ? `<span class="badge bg-light text-secondary border">${escapeHtml(opt.badge)}</span>` : '';
                const isActive = val === String(currentVal);
                return `
                    <li class="custom-dropdown-item ${isActive ? 'active' : ''}" role="option" data-value="${escapeHtml(val)}" data-label="${escapeHtml(label)}">
                        <span class="text-truncate">${escapeHtml(label)}</span>
                        ${badge || (isActive ? '<i class="bi bi-check2 fw-bold"></i>' : '')}
                    </li>
                `;
            }).join('');
        }

        function updateLabelFromValue(val) {
            const labelEl = container.querySelector('.custom-dropdown-label');
            const found = (container._dropdownOptions || []).find(o => String(o.value ?? '') === String(val));
            if (found) {
                labelEl.textContent = found.label ?? found.value;
                labelEl.classList.remove('placeholder-active');
            } else {
                labelEl.textContent = container._placeholder;
                labelEl.classList.add('placeholder-active');
            }
        }

        container._renderOptions = renderOptions;
        container._updateLabelFromValue = updateLabelFromValue;

        updateLabelFromValue(initialValue);
        renderOptions('');

        toggleBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = container.classList.contains('open');
            closeAllCustomDropdowns(container);
            if (!isOpen) {
                container.classList.add('open');
                toggleBtn.setAttribute('aria-expanded', 'true');
                searchInput.value = '';
                renderOptions('');
                setTimeout(() => searchInput.focus(), 20);
            } else {
                container.classList.remove('open');
                toggleBtn.setAttribute('aria-expanded', 'false');
            }
        });

        searchInput.addEventListener('click', e => e.stopPropagation());
        searchInput.addEventListener('input', function () {
            renderOptions(this.value);
        });

        listEl.addEventListener('click', function (e) {
            const item = e.target.closest('.custom-dropdown-item');
            if (!item) return;
            e.stopPropagation();
            const newVal = item.getAttribute('data-value') ?? '';
            setCustomDropdownValue(container, newVal, true);
            container.classList.remove('open');
            toggleBtn.setAttribute('aria-expanded', 'false');
        });

        container.dataset.initialized = 'true';
    }

    function closeAllCustomDropdowns(exceptContainer = null) {
        document.querySelectorAll('.custom-dropdown.open').forEach(el => {
            if (el !== exceptContainer) {
                el.classList.remove('open');
                const btn = el.querySelector('.custom-dropdown-toggle');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function initCustomDropdown(root = document) {
        const containers = root.querySelectorAll('[data-custom-dropdown]');
        containers.forEach(container => {
            if (container.dataset.initialized !== 'true') {
                buildDropdownMarkup(container);
            }
        });
    }

    function setCustomDropdownValue(target, value, triggerChange = false) {
        const container = typeof target === 'string'
            ? (document.getElementById(target) || document.querySelector(target))
            : target;
        if (!container) return;

        const dropdownEl = container.classList.contains('custom-dropdown')
            ? container
            : container.closest('.custom-dropdown');
        if (!dropdownEl) return;

        const hiddenInput = dropdownEl.querySelector('input[type="hidden"]');
        if (hiddenInput) {
            hiddenInput.value = value ?? '';
        }
        if (typeof dropdownEl._updateLabelFromValue === 'function') {
            dropdownEl._updateLabelFromValue(value ?? '');
        }
        if (typeof dropdownEl._renderOptions === 'function') {
            dropdownEl._renderOptions('');
        }
        if (triggerChange && hiddenInput) {
            hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            dropdownEl.dispatchEvent(new CustomEvent('dropdown:change', {
                bubbles: true,
                detail: { value: value ?? '' }
            }));
        }
    }

    function updateCustomDropdownOptions(target, options = [], selectedValue = '') {
        const container = typeof target === 'string'
            ? (document.getElementById(target) || document.querySelector(target))
            : target;
        if (!container) return;
        container._dropdownOptions = options;
        setCustomDropdownValue(container, selectedValue, false);
    }

    /**
     * Validasi Input Nomor Telepon: Hanya Angka (AGENTS.md Bagian 4)
     */
    function bindNumericPhoneInput(root = document) {
        root.querySelectorAll('[data-numeric-only]').forEach(input => {
            if (input.dataset.numericBound === 'true') return;
            input.addEventListener('input', function () {
                const cleaned = this.value.replace(/[^0-9]/g, '');
                if (this.value !== cleaned) {
                    this.value = cleaned;
                }
            });
            input.addEventListener('keypress', function (e) {
                if (e.ctrlKey || e.metaKey || e.altKey) return;
                if (e.key && e.key.length === 1 && !/[0-9]/.test(e.key)) {
                    e.preventDefault();
                }
            });
            input.dataset.numericBound = 'true';
        });
    }

    /**
     * Buat Custom Marker DivIcon untuk Leaflet
     */
    function createMapPinIcon(bgColor = '#2563EB', iconClass = 'bi-geo-alt-fill') {
        if (typeof L === 'undefined') return null;
        return L.divIcon({
            className: 'bg-transparent border-0',
            html: `<div class="custom-pin-marker" style="background-color: ${bgColor};"><i class="bi ${iconClass}"></i></div>`,
            iconSize: [34, 34],
            iconAnchor: [17, 34],
            popupAnchor: [0, -32]
        });
    }

    document.addEventListener('click', () => closeAllCustomDropdowns());
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeAllCustomDropdowns();
    });

    document.addEventListener('DOMContentLoaded', function () {
        initCustomDropdown(document);
        bindNumericPhoneInput(document);

        // Sidebar responsive toggle
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        const sidebar = document.getElementById('appSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');

        if (toggleBtn && sidebar && backdrop) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('show');
                backdrop.classList.toggle('show');
            });
            backdrop.addEventListener('click', () => {
                sidebar.classList.remove('show');
                backdrop.classList.remove('show');
            });
        }

        // Global Confirm Modal button handler
        const confirmBtn = document.getElementById('globalConfirmBtn');
        const confirmModalEl = document.getElementById('globalConfirmModal');
        if (confirmBtn && confirmModalEl) {
            confirmBtn.addEventListener('click', async function () {
                if (typeof activeConfirmCallback === 'function') {
                    const cb = activeConfirmCallback;
                    activeConfirmCallback = null;
                    const bsModal = bootstrap.Modal.getInstance(confirmModalEl);
                    if (bsModal) bsModal.hide();
                    await cb();
                }
            });
        }

        // Tombol Logout dengan Custom Confirmation Modal
        const logoutBtns = document.querySelectorAll('[data-logout-trigger]');
        logoutBtns.forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const logoutUrl = this.getAttribute('href') || this.dataset.logoutUrl;
                showConfirmModal({
                    title: 'Keluar dari Sistem?',
                    message: 'Anda akan mengakhiri sesi Administrator Logistik PT Maju Jaya Food.',
                    confirmText: 'Ya, Keluar',
                    cancelText: 'Batal',
                    variant: 'danger',
                    onConfirm: () => {
                        window.location.href = logoutUrl;
                    }
                });
            });
        });
    });

    // Ekspor helper ke global window
    window.debounce = debounce;
    window.escapeHtml = escapeHtml;
    window.showToast = showToast;
    window.showConfirmModal = showConfirmModal;
    window.initCustomDropdown = initCustomDropdown;
    window.setCustomDropdownValue = setCustomDropdownValue;
    window.updateCustomDropdownOptions = updateCustomDropdownOptions;
    window.bindNumericPhoneInput = bindNumericPhoneInput;
    window.createMapPinIcon = createMapPinIcon;
})(window);
