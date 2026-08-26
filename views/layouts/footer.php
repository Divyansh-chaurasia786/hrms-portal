<!-- views/layouts/footer.php -->
    </main>
</div>

<!-- SPA Top Loading Bar -->
<div id="spa-loading-bar" class="fixed top-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 z-[9999] opacity-0 transition-all duration-300 pointer-events-none" style="transform: scaleX(0); transform-origin: 0% 50%;"></div>

<script>
    lucide.createIcons();

    // High-Performance AJAX Dynamic Router with Prefetching & Caching
    (function initFastAJAXNavigation() {
        const loadingBar = document.getElementById('spa-loading-bar');
        const mainContent = document.getElementById('main-content');
        const pageCache = new Map();

        function startLoading() {
            if (loadingBar) {
                loadingBar.style.opacity = '1';
                loadingBar.style.transform = 'scaleX(0.7)';
            }
        }

        function stopLoading() {
            if (loadingBar) {
                loadingBar.style.transform = 'scaleX(1)';
                setTimeout(() => {
                    loadingBar.style.opacity = '0';
                    setTimeout(() => {
                        loadingBar.style.transform = 'scaleX(0)';
                    }, 200);
                }, 100);
            }
        }

        function updateActiveNav(targetUrl) {
            try {
                const urlObj = new URL(targetUrl, window.location.origin);
                const pageParam = urlObj.searchParams.get('page') || 'dashboard';

                document.querySelectorAll('aside nav a').forEach(link => {
                    const linkHref = link.getAttribute('href') || '';
                    const linkUrl = new URL(linkHref, window.location.origin);
                    const linkPage = linkUrl.searchParams.get('page');

                    if (linkPage === pageParam) {
                        link.classList.remove('text-slate-400', 'hover:bg-slate-800', 'hover:text-slate-200');
                        link.classList.add('bg-indigo-600', 'text-white', 'shadow-sm', 'font-semibold');
                    } else {
                        link.classList.remove('bg-indigo-600', 'text-white', 'shadow-sm', 'font-semibold');
                        link.classList.add('text-slate-400', 'hover:bg-slate-800', 'hover:text-slate-200');
                    }
                });
            } catch(e) {}
        }

        function applyHtmlToMain(html, url, push = true) {
            if (!mainContent) return;
            mainContent.innerHTML = html;

            if (push) {
                window.history.pushState({ url }, '', url);
            }
            updateActiveNav(url);

            // Re-execute embedded scripts in new content
            const scripts = mainContent.querySelectorAll('script');
            scripts.forEach(oldScript => {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });

            // Re-init Lucide Icons
            if (window.lucide) {
                window.lucide.createIcons();
            }

            // Re-init Alpine components
            if (window.Alpine && window.Alpine.initTree) {
                window.Alpine.initTree(mainContent);
            }

            window.scrollTo({ top: 0, behavior: 'instant' });
        }

        async function prefetch(url) {
            if (pageCache.has(url)) return;
            try {
                const ajaxUrl = url + (url.includes('?') ? '&' : '?') + 'ajax=1';
                const response = await fetch(ajaxUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (response.ok) {
                    const html = await response.text();
                    pageCache.set(url, html);
                }
            } catch (e) {}
        }

        async function navigateTo(url, push = true) {
            // 1. Instant Render from Cache if available
            if (pageCache.has(url)) {
                applyHtmlToMain(pageCache.get(url), url, push);
                // Background refresh cache
                prefetch(url);
                return;
            }

            startLoading();
            try {
                const ajaxUrl = url + (url.includes('?') ? '&' : '?') + 'ajax=1';
                const response = await fetch(ajaxUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!response.ok) {
                    window.location.href = url;
                    return;
                }

                const html = await response.text();
                pageCache.set(url, html);
                applyHtmlToMain(html, url, push);
            } catch (err) {
                console.error('AJAX Navigation Error:', err);
                window.location.href = url;
            } finally {
                stopLoading();
            }
        }

        window.spaNavigate = function(url, push = true) {
            navigateTo(url, push);
        };

        // Instant Prefetch on Hover (Mouseover / Touchstart)
        document.addEventListener('mouseover', function (e) {
            const link = e.target.closest('a');
            if (!link) return;
            const href = link.getAttribute('href');
            if (href && (href.startsWith('?page=') || href.startsWith('./?page=') || href.startsWith('/?page='))) {
                prefetch(href);
            }
        });

        // Intercept internal navigation clicks for 0-second AJAX transitions
        document.addEventListener('click', function (e) {
            const link = e.target.closest('a');
            if (!link) return;

            const href = link.getAttribute('href');
            if (!href) return;

            if (
                link.target === '_blank' ||
                link.hasAttribute('download') ||
                href.startsWith('mailto:') ||
                href.startsWith('tel:') ||
                href.startsWith('javascript:') ||
                href.startsWith('#') ||
                href.includes('?action=') ||
                href.includes('&action=') ||
                href.includes('action=logout') ||
                href.includes('action=switch-role') ||
                link.classList.contains('no-spa')
            ) {
                return;
            }

            if (href.startsWith('?page=') || href.startsWith('./?page=') || href.startsWith('/?page=') || (href.startsWith('http') && href.includes('?page=') && href.startsWith(window.location.origin))) {
                e.preventDefault();
                navigateTo(href, true);
            }
        });

        // Intercept GET Form submissions (date/search filters)
        document.addEventListener('submit', function (e) {
            const form = e.target;
            const method = (form.getAttribute('method') || 'GET').toUpperCase();
            const action = form.getAttribute('action') || window.location.search || '?page=dashboard';

            if (method === 'GET' && !action.includes('?action=') && !form.classList.contains('no-spa')) {
                e.preventDefault();
                const formData = new FormData(form);
                const params = new URLSearchParams(formData);
                const baseUrl = action.split('?')[0] || window.location.pathname;
                const targetUrl = baseUrl + '?' + params.toString();
                navigateTo(targetUrl, true);
            } else if (method === 'POST') {
                // Clear cache on mutations/posts
                pageCache.clear();
            }
        });

        // Handle Browser Back / Forward buttons
        window.addEventListener('popstate', function () {
            navigateTo(window.location.href, false);
        });

        // ========================================================
        // 🔄 Real-Time Live Presence & Background Sync
        // ========================================================
        let isSyncing = false;

        async function checkLiveUpdates() {
            if (isSyncing) return;
            isSyncing = true;
            try {
                const res = await fetch('?action=live-heartbeat', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) {
                    const data = await res.json();
                    if (!data.authenticated && data.force_logout) {
                        window.location.href = '?action=logout';
                    }
                }
            } catch (e) {
                // Ignore transient network hiccups
            } finally {
                isSyncing = false;
            }
        }

        // Lightweight periodic check (every 60 seconds) & on window focus
        setInterval(checkLiveUpdates, 60000);
        window.addEventListener('focus', checkLiveUpdates);
    })();
</script>
</body>
</html>
