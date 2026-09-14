/* ═══════════════════════════════════════════════════════════════
   CLIENT PORTAL — dashboard.js
   Sidebar collapse + SPA navigation + nav-rail dot positioning
   ═══════════════════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', function () {
    /* Theme / sidebar side come from PHP body classes + data-* (single source).
       Collapse state is session-ephemeral (resets on navigate) — intentional. */

    /* ── Elements ───────────────────────────────────────────── */
    const sidebar = document.getElementById('sidebar');
    const btnCollapse = document.getElementById('sidebarToggle');
    const btnExpand = document.getElementById('sidebarToggleCollapsed');
    const stage = document.querySelector('.portal-stage');
    const topbarTitle = document.querySelector('.portal-topbar-title');

    /* Nav-rail is CSS-layout (no fixed positioning). Keep stub for settings live-preview. */
    window.positionRail = function () {};

    /* ── Sidebar collapse ───────────────────────────────────── */
    if (btnCollapse && sidebar) {
        btnCollapse.addEventListener('click', () => {
            sidebar.classList.add('is-collapsed');
        });
    }
    if (btnExpand && sidebar) {
        btnExpand.addEventListener('click', () => {
            sidebar.classList.remove('is-collapsed');
        });
    }

    /* ═══════════════════════════════════════════════════════════
       SPA NAVIGATION
       ═══════════════════════════════════════════════════════════ */
    function getPageContent(doc) {
        const el = doc.getElementById('pageContent');
        return el ? el.innerHTML : null;
    }

    function getPageTitle(doc) {
        return doc.title || document.title;
    }

    function setActiveLink(page) {
        document.querySelectorAll('.sidebar-link').forEach((a) => {
            a.classList.toggle('is-active', a.dataset.page === page);
        });
        /* Sync nav-rail dots */
        document.querySelectorAll('.nav-rail-dot[data-page]').forEach((dot) => {
            dot.classList.toggle('is-active', dot.dataset.page === page);
        });
    }

    function updateTopbarTitle(doc) {
        const el = doc.querySelector('.portal-topbar-title');
        if (el && topbarTitle) topbarTitle.textContent = el.textContent;
    }

    async function navigateTo(href, page, pushState = true) {
        const pageContent = document.getElementById('pageContent');
        if (!pageContent) return;

        if (stage) stage.classList.add('is-loading');

        pageContent.classList.remove('is-current');
        pageContent.classList.add('is-exiting');

        let html;
        try {
            const res = await fetch(href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            html = await res.text();
        } catch (e) {
            pageContent.classList.remove('is-exiting');
            pageContent.classList.add('is-current');
            if (stage) stage.classList.remove('is-loading');
            return;
        }

        await new Promise((resolve) => {
            const onEnd = () => {
                pageContent.removeEventListener('animationend', onEnd);
                resolve();
            };
            pageContent.addEventListener('animationend', onEnd);
            setTimeout(resolve, 450);
        });

        const parser = new DOMParser();
        const newDoc = parser.parseFromString(html, 'text/html');
        const newBody = getPageContent(newDoc);

        if (newBody !== null) {
            pageContent.innerHTML = newBody;
            pageContent.dataset.page = page;
            document.title = getPageTitle(newDoc);
            updateTopbarTitle(newDoc);
            setActiveLink(page);
        }

        if (pushState) {
            history.pushState({ page, href }, document.title, href);
        }

        pageContent.classList.remove('is-exiting');
        pageContent.classList.add('is-current');
        if (stage) {
            stage.classList.remove('is-loading');
            stage.scrollTop = 0;
        }

        pageContent.querySelectorAll('script').forEach((oldScript) => {
            const s = document.createElement('script');
            [...oldScript.attributes].forEach((a) =>
                s.setAttribute(a.name, a.value)
            );
            s.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(s, oldScript);
        });

        /* Move any Bootstrap modals out of the scroll container to <body>
           so that the backdrop (position:fixed, z-index 1040) and dialog
           are not clipped by the overflow:auto stacking context. */
        pageContent.querySelectorAll('.modal').forEach((modal) => {
            /* Remove any pre-existing modal with the same id to avoid dupes */
            const existing = document.getElementById(modal.id);
            if (existing && existing !== modal) existing.remove();
            document.body.appendChild(modal);
        });
    }

    /* Intercept sidebar link clicks */
    document.querySelectorAll('.sidebar-link[data-page]').forEach((link) => {
        if (link.classList.contains('sidebar-link--danger')) return;
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const page = this.dataset.page;
            const href = this.href;
            if (page === document.getElementById('pageContent')?.dataset.page)
                return;
            navigateTo(href, page);
        });
    });

    /* Dot clicks also trigger navigation */
    document.querySelectorAll('.nav-rail-dot[data-page]').forEach((dot) => {
        dot.addEventListener('click', function () {
            const page = this.dataset.page;
            const link = document.querySelector(
                `.sidebar-link[data-page="${page}"]`
            );
            if (link) link.click();
        });
    });

    /* Browser back / forward */
    window.addEventListener('popstate', function (e) {
        if (e.state && e.state.href) {
            navigateTo(e.state.href, e.state.page, false);
        }
    });

    /* Initial history state */
    const initPage = document.getElementById('pageContent')?.dataset.page;
    if (initPage) {
        history.replaceState(
            { page: initPage, href: location.href },
            document.title,
            location.href
        );
    }

    /* On initial (non-SPA) page load, move any modals from inside the
       scroll container to <body> so the backdrop is not clipped. */
    document.querySelectorAll('#pageContent .modal').forEach((modal) => {
        document.body.appendChild(modal);
    });
});
