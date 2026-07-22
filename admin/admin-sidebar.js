/**
 * Blue-Car Admin — sidebar: acordeon details + mobil.
 */
(function () {
    'use strict';

    const wrapper = document.querySelector('.page-wrapper');
    const menu = document.getElementById('blu-admin-sidebar-menu');

    if (menu) {
        menu.querySelectorAll('details.blu-nav-details').forEach(function (details) {
            details.addEventListener('toggle', function () {
                const list = details.closest('.sidebar-list');
                if (!details.open) {
                    if (list) list.classList.remove('active');
                    return;
                }
                menu.querySelectorAll('details.blu-nav-details').forEach(function (other) {
                    if (other === details) return;
                    other.open = false;
                    const otherList = other.closest('.sidebar-list');
                    if (otherList) otherList.classList.remove('active');
                });
                if (list) list.classList.add('active');
            });
        });
    }

    if (wrapper) {
        document.querySelectorAll('.toggle-sidebar').forEach(function (btn) {
            btn.addEventListener('click', function () {
                wrapper.classList.toggle('sidebar-open');
            });
        });
    }

    function syncMobileSidebar() {
        if (!wrapper) return;
        const mobileToggle = document.querySelector('.toggle-sidebar');
        if (!mobileToggle) return;
        if (window.innerWidth <= 1199) {
            wrapper.classList.add('sidebar-open');
            mobileToggle.classList.add('close');
        } else {
            wrapper.classList.remove('sidebar-open');
            mobileToggle.classList.remove('close');
        }
    }

    syncMobileSidebar();
    window.addEventListener('resize', syncMobileSidebar);

    if (!menu) return;

    const scrollHost = document.getElementById('blu-sidebar-nav-scroll') || menu.closest('.blu-sidebar-nav-scroll') || menu;
    const active = menu.querySelector('.sidebar-submenu a.active, .sidebar-link.active[href*="page="]');
    if (active && scrollHost) {
        requestAnimationFrame(function () {
            const top = active.getBoundingClientRect().top - scrollHost.getBoundingClientRect().top;
            if (top > scrollHost.clientHeight - 80 || top < 0) {
                scrollHost.scrollTop += top - 40;
            }
        });
    }
})();
