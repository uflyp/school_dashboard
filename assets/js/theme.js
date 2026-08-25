/**
 * theme.js - Dark/Light Mode Manager
 * Shared across all pages: index.php, dashboard.php, ppdb.php
 */

// Apply saved theme immediately (before DOM paint to prevent flash)
(function () {
    var saved = localStorage.getItem('theme');
    var useDark = saved ? saved === 'dark' : true; // default: dark
    document.documentElement.classList.toggle('dark', useDark);
})();

// Toggle function - called by the toggle button
function toggleTheme() {
    var isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    syncThemeIcons(isDark);
}

// Sync all theme icons and tooltips
function syncThemeIcons(isDark) {
    document.querySelectorAll('.theme-icon').forEach(function (icon) {
        icon.className = 'fa-solid theme-icon ' + (isDark ? 'fa-moon' : 'fa-sun');
    });
    document.querySelectorAll('.theme-toggle-btn').forEach(function (btn) {
        btn.title = isDark ? 'Ganti ke Mode Terang' : 'Ganti ke Mode Gelap';
    });
}

// Init icons after DOM ready
document.addEventListener('DOMContentLoaded', function () {
    syncThemeIcons(document.documentElement.classList.contains('dark'));
});
