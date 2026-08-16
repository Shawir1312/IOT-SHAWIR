/**
 * ShawirIOT - Global Theme Controller (Light & Dark Mode)
 */
(function() {
  const THEME_KEY = 'shawir_theme';

  function getPreferredTheme() {
    const saved = localStorage.getItem(THEME_KEY);
    if (saved === 'light' || saved === 'dark') return saved;
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
      return 'light';
    }
    return 'dark';
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    if (document.body) {
      document.body.setAttribute('data-theme', theme);
      if (theme === 'light') {
        document.body.classList.add('theme-light');
      } else {
        document.body.classList.remove('theme-light');
      }
    }
    localStorage.setItem(THEME_KEY, theme);

    // Update all theme toggle icons
    document.querySelectorAll('.theme-toggle-icon').forEach(icon => {
      if (theme === 'light') {
        icon.className = 'fas fa-sun theme-toggle-icon';
      } else {
        icon.className = 'fas fa-moon theme-toggle-icon';
      }
    });
  }

  window.toggleTheme = function() {
    const current = document.documentElement.getAttribute('data-theme') || 'dark';
    const next = current === 'dark' ? 'light' : 'dark';
    applyTheme(next);
  };

  // Immediate init before DOM ready to prevent flash
  const initialTheme = getPreferredTheme();
  document.documentElement.setAttribute('data-theme', initialTheme);

  document.addEventListener('DOMContentLoaded', () => {
    applyTheme(initialTheme);
  });
})();
