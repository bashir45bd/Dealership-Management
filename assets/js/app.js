/**
 * Maruf Traders - Application Layout & Interactive Navigation
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Sidebar Collapse State (LocalStorage)
    const body = document.body;
    const sidebarToggle = document.getElementById('sidebarToggle');
    const savedState = localStorage.getItem('maruf_sidebar_collapsed');

    if (savedState === 'true' && window.innerWidth >= 1200) {
        body.classList.add('sidebar-collapsed');
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            if (window.innerWidth < 768) {
                // Mobile slide drawer
                body.classList.toggle('sidebar-mobile-open');
            } else {
                // Desktop collapse
                body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('maruf_sidebar_collapsed', body.classList.contains('sidebar-collapsed'));
            }
        });
    }

    // Close mobile sidebar on backdrop click
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (backdrop) {
        backdrop.addEventListener('click', () => {
            body.classList.remove('sidebar-mobile-open');
        });
    }

    // 2. Dynamic Live Menu Search Filtering
    const menuSearch = document.getElementById('sidebarMenuSearch');
    if (menuSearch) {
        menuSearch.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const navItems = document.querySelectorAll('.sidebar-nav-item');
            const categoryTitles = document.querySelectorAll('.menu-category-title');

            if (!query) {
                navItems.forEach(item => item.style.display = '');
                categoryTitles.forEach(cat => cat.style.display = '');
                return;
            }

            let matchCountsByCategory = {};

            navItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                const matches = text.includes(query);
                item.style.display = matches ? '' : 'none';
            });

            // Hide categories if all items under them are hidden
            categoryTitles.forEach(cat => {
                let nextElem = cat.nextElementSibling;
                let hasVisible = false;
                while (nextElem && !nextElem.classList.contains('menu-category-title')) {
                    if (nextElem.classList.contains('sidebar-nav-item') && nextElem.style.display !== 'none') {
                        hasVisible = true;
                        break;
                    }
                    nextElem = nextElem.nextElementSibling;
                }
                cat.style.display = hasVisible ? '' : 'none';
            });
        });
    }

    // 3. Highlight Current Active Route in Sidebar
    const currentPath = window.location.pathname.toLowerCase();
    const navLinks = document.querySelectorAll('.sidebar-nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href !== '#' && currentPath.includes(href.toLowerCase().replace(/index\.php$/, ''))) {
            navLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');
        }
    });
});
