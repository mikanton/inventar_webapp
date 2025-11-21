// Common JS utilities
const $ = sel => document.querySelector(sel);
const $$ = sel => Array.from(document.querySelectorAll(sel));
const create = (t, c) => { const e = document.createElement(t); if (c) e.className = c; return e; };

// CSRF Wrapper for fetch
const originalFetch = window.fetch;
window.fetch = async (url, options = {}) => {
    if (options.method && ['POST', 'PUT', 'DELETE'].includes(options.method.toUpperCase())) {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        if (token) {
            options.headers = { ...options.headers, 'X-CSRF-Token': token };
        }
    }
    return originalFetch(url, options);
};

// Simple toast notification
function toast(msg, type = 'info') {
    const t = create('div', `toast ${type}`);
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.classList.add('show'), 10);
    setTimeout(() => {
        t.classList.remove('show');
        setTimeout(() => t.remove(), 300);
    }, 3000);
}

document.addEventListener('DOMContentLoaded', () => {
    const menuBtn = document.getElementById('menuBtn');
    const drawer = document.getElementById('drawer');
    const backdrop = document.getElementById('drawerBackdrop');
    const closeBtn = document.getElementById('drawerClose');

    function toggle() {
        drawer.classList.toggle('open');
        backdrop.classList.toggle('open');
    }

    if (menuBtn) menuBtn.addEventListener('click', toggle);
    if (closeBtn) closeBtn.addEventListener('click', toggle);
    if (backdrop) backdrop.addEventListener('click', toggle);

    // Handle data-trigger links in drawer
    document.querySelectorAll('.drawer-nav a[data-trigger]').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            toggle(); // Close drawer
            const modalId = link.dataset.trigger;
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
                // Special handling for specific modals if needed
                if (modalId === 'modalSupplier' && window.loadRequests) window.loadRequests();
            }
        });
    });
});
