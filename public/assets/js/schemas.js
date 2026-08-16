document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('filtro-tabelas');
    var grid = document.getElementById('grid-tabelas');
    if (!input || !grid) {
        return;
    }

    var cards = grid.querySelectorAll('[data-nome-tabela]');

    input.addEventListener('input', function () {
        var termo = input.value.trim().toLowerCase();
        cards.forEach(function (card) {
            var nome = card.getAttribute('data-nome-tabela');
            card.style.display = nome.indexOf(termo) === -1 ? 'none' : '';
        });
    });
});
