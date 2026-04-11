<script>
(function () {
    try {
        var k = 'color-scheme';
        var s = localStorage.getItem(k);
        if (s === 'dark') {
            document.documentElement.classList.add('dark');
        } else if (s === 'light') {
            document.documentElement.classList.remove('dark');
        } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
    } catch (e) {}
})();
</script>
