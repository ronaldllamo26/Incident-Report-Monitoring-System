(function() {
    const storageKey = 'qc-alerto-theme';
    
    // Apply theme immediately to prevent flicker
    const savedTheme = localStorage.getItem(storageKey) || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);

    window.toggleTheme = function() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem(storageKey, newTheme);
        
        // Dispatch event for other components (like maps)
        window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: newTheme } }));
    };
})();
