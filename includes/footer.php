</main>
<footer class="lg:hidden text-center text-[11px] text-slate-400 pb-6 -mt-4">
    <?= APP_NAME ?> · v<?= APP_VERSION ?>
</footer>
<!-- Búsqueda y filtros en vivo -->
<script>
(function () {
    const DEBOUNCE = 400;
    const KEY = 'live-search-focus';

    function marcarFoco(input) {
        try {
            sessionStorage.setItem(KEY, JSON.stringify({
                path: location.pathname,
                name: input.name,
                pos:  input.selectionStart
            }));
        } catch (e) {}
    }

    function enviar(form, input) {
        if (input) marcarFoco(input);
        form.querySelectorAll('input[name="page"]').forEach(el => el.remove());
        form.classList.add('is-searching');
        form.submit();
    }

    document.querySelectorAll('form[method="GET"], form[method="get"]').forEach(form => {
        const texto   = form.querySelector('input[name="q"], input[type="search"]');
        const selects = form.querySelectorAll('select');
        const fechas  = form.querySelectorAll('input[type="date"]');
        if (!texto && !selects.length && !fechas.length) return;

        // Texto: espera a que el usuario deje de escribir
        if (texto) {
            let t = null;
            texto.setAttribute('autocomplete', 'off');
            texto.addEventListener('input', () => {
                clearTimeout(t);
                t = setTimeout(() => enviar(form, texto), DEBOUNCE);
            });
            // Enter envía de una vez
            texto.addEventListener('keydown', e => {
                if (e.key === 'Enter') { clearTimeout(t); enviar(form, texto); }
                if (e.key === 'Escape' && texto.value) {
                    texto.value = '';
                    clearTimeout(t);
                    enviar(form, texto);
                }
            });
        }

        // Selects y fechas: al cambiar
        selects.forEach(sel => {
            if (sel.hasAttribute('onchange')) return;
            sel.addEventListener('change', () => enviar(form, texto));
        });
        fechas.forEach(f => f.addEventListener('change', () => enviar(form, texto)));

        // El botón de lupa pasa a ser indicador
        const btn = form.querySelector('button:not([type="button"])');
        if (btn && btn.querySelector('.fa-search, .fa-magnifying-glass')) {
            btn.classList.add('search-indicator');
            btn.setAttribute('tabindex', '-1');
            btn.setAttribute('aria-hidden', 'true');
        }
    });

    // Devolver el cursor donde estaba
    try {
        const raw = sessionStorage.getItem(KEY);
        if (raw) {
            const d = JSON.parse(raw);
            sessionStorage.removeItem(KEY);
            if (d.path === location.pathname && d.name) {
                const el = document.querySelector('[name="' + d.name + '"]');
                if (el) {
                    el.focus();
                    const p = d.pos != null ? d.pos : el.value.length;
                    try { el.setSelectionRange(p, p); } catch (e) {}
                }
            }
        }
    } catch (e) {}
})();
</script>

</body>
</html>
