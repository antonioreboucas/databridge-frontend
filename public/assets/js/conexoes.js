document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('btn-testar-conexao');
    if (!btn) {
        return;
    }

    var resultado = document.getElementById('resultado-teste-conexao');
    var textoOriginal = btn.textContent;

    btn.addEventListener('click', function () {
        var payload = {
            host: document.getElementById('host').value,
            porta: document.getElementById('porta').value,
            banco: document.getElementById('banco').value,
            usuario: document.getElementById('usuario').value,
            senha: document.getElementById('senha').value,
        };

        btn.disabled = true;
        btn.textContent = 'Testando…';
        resultado.innerHTML = '';

        fetch((window.DATABRIDGE_BASE_URL || '') + '/conexoes?ajax=testar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var tipo = data.sucesso ? 'success' : 'error';
                var div = document.createElement('div');
                div.className = 'alert alert-' + tipo;
                div.textContent = data.mensagem || '';
                resultado.appendChild(div);
            })
            .catch(function () {
                var div = document.createElement('div');
                div.className = 'alert alert-error';
                div.textContent = 'Não foi possível testar a conexão (erro de rede).';
                resultado.appendChild(div);
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = textoOriginal;
            });
    });
});
