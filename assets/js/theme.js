/**
 * Maruf Traders - Dark / Light Theme Toggle
 * Requires the no-flash inline snippet in <head> (see header.php) to already
 * have set data-theme on <html> before this file runs.
 */
(function () {
    var STORAGE_KEY = 'mt_theme';

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(STORAGE_KEY, theme);
    }

    function currentTheme() {
        return document.documentElement.getAttribute('data-theme') || 'dark';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('themeToggleBtn');
        if (!btn) return;

        btn.addEventListener('click', function () {
            var next = currentTheme() === 'dark' ? 'light' : 'dark';
            applyTheme(next);
        });
    });
})();