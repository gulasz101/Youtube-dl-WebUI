

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
        </script>

</body></html>
