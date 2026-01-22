

        <script type="text/javascript">
                document.addEventListener("DOMContentLoaded", function() {
                        const urlF = document.getElementById('url');
			if (urlF) {urlF.focus();}
                });

                function toggleNavbar() {
                        const collapse = document.getElementById('navbarSupportedContent');
                        collapse.classList.toggle('show');
                }

                function toggleDropdown(element) {
                        const menu = element.nextElementSibling;
                        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                }

                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                        const dropdowns = document.querySelectorAll('.dropdown-menu');
                        dropdowns.forEach(menu => {
                                if (!menu.parentElement.contains(event.target)) {
                                        menu.style.display = 'none';
                                }
                        });
                });

                // Dark Mode Toggle
                const themeToggle = document.getElementById('theme-toggle');
                const sunIcon = document.querySelector('.sun-icon');
                const moonIcon = document.querySelector('.moon-icon');
                const html = document.documentElement;

                // Initialize theme from localStorage or system preference
                function initTheme() {
                        const savedTheme = localStorage.getItem('theme');
                        if (savedTheme === 'dark') {
                                html.classList.add('dark');
                                updateIcons(true);
                        } else if (savedTheme === 'light') {
                                html.classList.remove('dark');
                                updateIcons(false);
                        } else {
                                // Check system preference
                                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                                if (prefersDark) {
                                        html.classList.add('dark');
                                        updateIcons(true);
                                }
                        }
                }

                // Update icon visibility
                function updateIcons(isDark) {
                        if (isDark) {
                                sunIcon.style.display = 'none';
                                moonIcon.style.display = 'block';
                        } else {
                                sunIcon.style.display = 'block';
                                moonIcon.style.display = 'none';
                        }
                }

                // Toggle theme
                function toggleTheme() {
                        const isDark = html.classList.contains('dark');
                        if (isDark) {
                                html.classList.remove('dark');
                                localStorage.setItem('theme', 'light');
                                updateIcons(false);
                        } else {
                                html.classList.add('dark');
                                localStorage.setItem('theme', 'dark');
                                updateIcons(true);
                        }
                }

                // Initialize theme on page load
                initTheme();

                // Add click listener to toggle button
                if (themeToggle) {
                        themeToggle.addEventListener('click', toggleTheme);
                }
        </script>

</body></html>
