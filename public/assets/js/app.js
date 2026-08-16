// Barra de carregamento no topo da página: aparece assim que o usuário navega
// (clique em link ou envio de formulário) e some quando a próxima página termina
// de carregar. Como a maior parte do app é renderizada no servidor (PHP -> API ->
// banco remoto, às vezes lento), isso dá um feedback imediato em vez de tela parada.
(function () {
    var loader = document.getElementById('page-loader');
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'page-loader';
        loader.className = 'page-loader';
        document.body.appendChild(loader);
    }

    function iniciar() {
        loader.classList.remove('is-done');
        // força reflow pra reiniciar a transição do zero
        void loader.offsetWidth;
        loader.classList.add('is-active');
    }

    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[href]');
        if (!link) return;
        if (link.target === '_blank' || link.hasAttribute('download')) return;
        if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;

        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) return;

        iniciar();
    });

    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        iniciar();
    });

    // form.submit() chamado via JS (usado nos selects em cascata de
    // conexão/schema/tabela) NÃO dispara o evento 'submit' nativo — só clique
    // em botão ou Enter disparam. Interceptar o método garante que a barra
    // apareça nesses casos também.
    var submitOriginal = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        iniciar();
        return submitOriginal.apply(this, arguments);
    };

    // Ao voltar via cache do navegador (bfcache), garante que a barra não fique presa
    window.addEventListener('pageshow', function () {
        loader.classList.remove('is-active');
        loader.classList.remove('is-done');
    });
})();

document.addEventListener('DOMContentLoaded', function () {
    var btnOpen = document.getElementById('btn-toggle-sidebar');
    var btnClose = document.getElementById('btn-close-sidebar');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebar-overlay');

    if (!btnOpen || !sidebar || !overlay) {
        return;
    }

    function abrirMenu() {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-open');
    }

    function fecharMenu() {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-open');
    }

    btnOpen.addEventListener('click', abrirMenu);
    overlay.addEventListener('click', fecharMenu);
    if (btnClose) {
        btnClose.addEventListener('click', fecharMenu);
    }
});
