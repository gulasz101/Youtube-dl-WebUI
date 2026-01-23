  </main>
  <script>
    // Auto-focus URL input field
    document.addEventListener("DOMContentLoaded", function() {
      const urlField = document.getElementById('url');
      if (urlField) urlField.focus();
    });

    // Dark Mode Toggle with PicoCSS
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('theme');

    // Initialize theme from localStorage or system preference
    if (savedTheme) {
      html.setAttribute('data-theme', savedTheme);
    } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
      html.setAttribute('data-theme', 'dark');
    }

    // Toggle theme function
    function toggleTheme() {
      const currentTheme = html.getAttribute('data-theme');
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', newTheme);
      localStorage.setItem('theme', newTheme);
    }
  </script>
</body>
</html>
