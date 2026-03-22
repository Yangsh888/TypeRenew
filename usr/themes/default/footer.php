<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
</main>

<footer class="site-footer" role="contentinfo">
    <div class="container">
        <p>&copy; <?php echo date('Y'); ?> <a href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title(); ?></a>.</p>
        <p>Powered by <a href="https://www.typerenew.com">TypeRenew</a>.</p>
    </div>
</footer>

<?php $this->footer(); ?>

<script>
    (function() {
        var btn = document.getElementById('theme-switch');
        if (!btn) return;
        
        var html = document.documentElement;
        var iconPath = btn.querySelector('path');
        var transitionTimeout;
        
        function updateIcon() {
            var isDark = html.getAttribute('data-theme') === 'dark';
            if (isDark) {
                iconPath.setAttribute('d', 'M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42M17 12a5 5 0 1 1-10 0 5 5 0 0 1 10 0z');
            } else {
                iconPath.setAttribute('d', 'M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z');
            }
        }
        
        updateIcon();
        
        btn.addEventListener('click', function() {
            var currentDark = html.getAttribute('data-theme') === 'dark';
            var newTheme = currentDark ? 'light' : 'dark';
            
            html.classList.add('theme-transition');
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateIcon();
            
            clearTimeout(transitionTimeout);
            transitionTimeout = setTimeout(function() {
                html.classList.remove('theme-transition');
            }, 300);
        });
        
        var prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
        prefersDark.addEventListener('change', function(e) {
            if (!localStorage.getItem('theme')) {
                html.classList.add('theme-transition');
                html.setAttribute('data-theme', e.matches ? 'dark' : 'light');
                updateIcon();
                clearTimeout(transitionTimeout);
                transitionTimeout = setTimeout(function() {
                    html.classList.remove('theme-transition');
                }, 300);
            }
        });
    })();
</script>

</body>
</html>
