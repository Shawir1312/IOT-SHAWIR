/**
 * ShawirIOT - Global Theme Controller (Default: Light Mode)
 */
(function() {
  const THEME_KEY = 'shawir_theme';

  function getPreferredTheme() {
    const saved = localStorage.getItem(THEME_KEY);
    if (saved === 'dark' || saved === 'light') return saved;
    // Default to Light Mode!
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

    // Update all theme toggle icons
    document.querySelectorAll('.theme-toggle-icon').forEach(icon => {
      if (theme === 'light') {
        // When in light mode, show moon icon so user can switch to dark mode
        icon.className = 'fas fa-moon theme-toggle-icon';
      } else {
        // When in dark mode, show sun icon so user can switch to light mode
        icon.className = 'fas fa-sun theme-toggle-icon';
      }
    });
  }

  window.toggleTheme = function() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = current === 'light' ? 'dark' : 'light';
    applyTheme(next);
  };

  // Immediate init before DOM ready to prevent flash
  const initialTheme = getPreferredTheme();
  document.documentElement.setAttribute('data-theme', initialTheme);

  document.addEventListener('DOMContentLoaded', () => {
    applyTheme(initialTheme);
  });
})();
