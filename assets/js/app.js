// assets/js/app.js - Application Interactive Helpers
document.addEventListener('DOMContentLoaded', () => {
    // Sidebar Mobile Toggle & Desktop Collapse
    const sidebarToggleBtn = document.getElementById('sidebar-toggle');
    const desktopCollapseBtn = document.getElementById('sidebar-collapse-btn');
    const sidebar = document.getElementById('app-sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');

    if (sidebarToggleBtn && sidebar) {
        sidebarToggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle('hidden');
            }
        });
    }

    if (desktopCollapseBtn && sidebar) {
        desktopCollapseBtn.addEventListener('click', () => {
            sidebar.classList.toggle('is-collapsed');
            const isCollapsed = sidebar.classList.contains('is-collapsed');
            localStorage.setItem('sidebar_collapsed', isCollapsed ? 'true' : 'false');
        });
    }

    // Restore desktop sidebar collapse state
    if (localStorage.getItem('sidebar_collapsed') === 'true' && sidebar) {
        sidebar.classList.add('is-collapsed');
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        });
    }

    // Modal Helper functions
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    };
});
