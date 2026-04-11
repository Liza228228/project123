const STORAGE_KEY = 'color-scheme';

export function bindThemeToggles() {
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const root = document.documentElement;
            const isDark = root.classList.toggle('dark');
            localStorage.setItem(STORAGE_KEY, isDark ? 'dark' : 'light');
        });
    });
}
