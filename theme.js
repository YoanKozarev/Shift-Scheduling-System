// ═══════════════════════════════════════
// Theme Toggle & Demo Badge — Shared JS
// ═══════════════════════════════════════

(function() {
    // Apply saved theme immediately (before paint)
    const saved = localStorage.getItem('grafik-theme');
    if (saved === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
})();

function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    
    if (isDark) {
        html.removeAttribute('data-theme');
        localStorage.setItem('grafik-theme', 'light');
    } else {
        html.setAttribute('data-theme', 'dark');
        localStorage.setItem('grafik-theme', 'dark');
    }
    
    // Update icon
    updateThemeIcon();
}

function updateThemeIcon() {
    const btn = document.getElementById('themeToggleBtn');
    if (!btn) return;
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    btn.innerHTML = isDark 
        ? '<i class="bi bi-sun"></i>' 
        : '<i class="bi bi-moon-stars"></i>';
    btn.title = isDark ? 'Светла тема' : 'Тъмна тема';
}

// Update icon on load
document.addEventListener('DOMContentLoaded', updateThemeIcon);
