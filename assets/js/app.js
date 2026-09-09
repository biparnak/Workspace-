// FreeDNS Panel — front-end helpers

document.addEventListener('DOMContentLoaded', function () {
    // Mobile nav toggle
    const toggle = document.getElementById('navToggle');
    const nav = document.getElementById('mainNav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            nav.classList.toggle('open');
        });
    }

    // Live subdomain preview
    const subInput = document.getElementById('subdomainInput');
    const preview = document.getElementById('subdomainPreview');
    if (subInput && preview) {
        const render = function () {
            const val = (subInput.value || '').toLowerCase().replace(/[^a-z0-9-]/g, '');
            const parent = preview.dataset.parent || '';
            preview.textContent = (val ? val + '.' : '') + parent;
        };
        subInput.addEventListener('input', render);
        render();
    }

    // Tabs
    document.querySelectorAll('.tabs').forEach(function (tabs) {
        const panes = tabs.closest('div').querySelectorAll('.tab-pane');
        tabs.querySelectorAll('.tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                panes.forEach(p => {
                    p.style.display = (p.id === tab.dataset.pane) ? 'block' : 'none';
                });
            });
        });
    });

    // Confirmation dialogs
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (ev) {
            if (!window.confirm(el.dataset.confirm)) {
                ev.preventDefault();
            }
        });
    });

    // Auto-submit on filter select change
    document.querySelectorAll('[data-auto-submit]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            if (sel.form) sel.form.submit();
        });
    });

    // Admin domain import textarea helper
    const importTa = document.getElementById('importDomains');
    if (importTa) {
        const count = document.getElementById('importCount');
        const update = function () {
            const lines = importTa.value.split('\n').map(l => l.trim()).filter(Boolean);
            if (count) count.textContent = lines.length + ' domain' + (lines.length === 1 ? '' : 's');
        };
        importTa.addEventListener('input', update);
        update();
    }
});