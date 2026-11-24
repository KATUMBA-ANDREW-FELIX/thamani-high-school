(function () {
    var publicPages = new Set([
        'home',
        'enrollment',
        'library',
        'gallery',
        'calendar',
        'alumni',
        'map',
        'timetables'
    ]);

    function pageKey(pathname) {
        var page = decodeURIComponent(pathname).split('/').filter(Boolean).pop() || 'home';
        page = page.replace(/\.(php|html)$/i, '').toLowerCase();
        return page === 'index' ? 'home' : page;
    }

    var currentPage = pageKey(window.location.pathname);

    document.querySelectorAll('nav a[href]').forEach(function (link) {
        var isNavigationLink = link.classList.contains('nav-link')
            || link.closest('.nav-links')
            || link.closest('#mobile-menu')
            || link.closest('.mobile-menu');
        if (!isNavigationLink) return;

        var target;
        try {
            target = new URL(link.getAttribute('href'), document.baseURI);
        } catch (error) {
            return;
        }
        if (target.origin !== window.location.origin) return;

        var targetPage = pageKey(target.pathname);
        if (!publicPages.has(targetPage)) return;

        link.classList.remove(
            'site-nav-current',
            'active',
            'text-white',
            'bg-brand-green',
            'text-gray-900',
            'bg-white',
            'font-extrabold',
            'border-gray-300',
            'shadow-sm'
        );
        link.removeAttribute('aria-current');

        if (targetPage === currentPage) {
            link.classList.add('site-nav-current');
            link.setAttribute('aria-current', 'page');
        }
    });
})();
