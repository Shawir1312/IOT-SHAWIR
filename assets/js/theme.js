/**
 * ShawirIOT - Global Theme Controller (Default: Light Mode / Mode Biasa & Cerah)
 */
(function() {
  const THEME_KEY = 'shawir_theme';

  function getPreferredTheme() {
    const saved = localStorage.getItem(THEME_KEY);
    if (saved === 'dark' || saved === 'light') return saved;
    // Default: Light Mode (Mode Biasa / Cerah)
    return 'light';
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    if (document.body) {
      document.body.setAttribute('data-theme', theme);
      if (theme === 'dark') {
        document.body.classList.add('theme-dark');
        document.body.classList.remove('theme-light');
      } else {
        document.body.classList.add('theme-light');
        document.body.classList.remove('theme-dark');
      }
    }
    localStorage.setItem(THEME_KEY, theme);

    // Update all theme toggle icons & tooltips
    document.querySelectorAll('.theme-toggle-icon').forEach(icon => {
      if (theme === 'light') {
        icon.className = 'fas fa-moon theme-toggle-icon';
      } else {
        icon.className = 'fas fa-sun theme-toggle-icon';
      }
    });

    document.querySelectorAll('.theme-toggle-btn').forEach(btn => {
      btn.setAttribute('title', theme === 'light' ? 'Ubah ke Mode Gelap (Dark Mode)' : 'Ubah ke Mode Terang (Light Mode)');
      btn.setAttribute('aria-label', theme === 'light' ? 'Ubah ke Mode Gelap' : 'Ubah ke Mode Terang');
    });

    // Notify listeners (e.g. Chart.js redraws)
    window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
  }

  window.toggleTheme = function() {
    const current = document.documentElement.getAttribute('data-theme') || getPreferredTheme();
    const next = current === 'light' ? 'dark' : 'light';
    applyTheme(next);
  };

  // Immediate init before DOM ready to prevent flash of unstyled theme
  const initialTheme = getPreferredTheme();
  document.documentElement.setAttribute('data-theme', initialTheme);

  document.addEventListener('DOMContentLoaded', () => {
    applyTheme(initialTheme);
  });
})();
