/**
 * theme-toggle.js
 * Handles the dark mode toggle logic, persisting user preference in localStorage.
 */

document.addEventListener('DOMContentLoaded', () => {
    const htmlEl = document.documentElement;
    const themeToggleBtn = document.getElementById('theme-toggle');
    const THEME_KEY = 'hearing_store_theme';

    // Check for saved theme or system preference
    const savedTheme = localStorage.getItem(THEME_KEY);
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    // Determine initial theme
    let currentTheme = 'light';
    if (savedTheme) {
        currentTheme = savedTheme;
    } else if (systemPrefersDark) {
        currentTheme = 'dark';
    }

    // Apply initial theme
    if (currentTheme === 'dark') {
        htmlEl.setAttribute('data-theme', 'dark');
    } else {
        htmlEl.removeAttribute('data-theme');
    }

    // Toggle theme on button click (if button exists)
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            if (htmlEl.getAttribute('data-theme') === 'dark') {
                htmlEl.removeAttribute('data-theme');
                localStorage.setItem(THEME_KEY, 'light');
            } else {
                htmlEl.setAttribute('data-theme', 'dark');
                localStorage.setItem(THEME_KEY, 'dark');
            }
        });
    }
});
