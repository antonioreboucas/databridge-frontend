document.addEventListener('DOMContentLoaded', function () {
    var btnToggleSenha = document.getElementById('btn-toggle-senha');
    var inputSenha = document.getElementById('senha');

    if (btnToggleSenha && inputSenha) {
        btnToggleSenha.addEventListener('click', function () {
            var vaiMostrar = inputSenha.type === 'password';
            inputSenha.type = vaiMostrar ? 'text' : 'password';
            btnToggleSenha.querySelector('.icon-toggle-mostrar').style.display = vaiMostrar ? 'none' : '';
            btnToggleSenha.querySelector('.icon-toggle-ocultar').style.display = vaiMostrar ? '' : 'none';
            btnToggleSenha.setAttribute('aria-label', vaiMostrar ? 'Ocultar senha' : 'Mostrar senha');
        });
    }

    var formLogin = document.getElementById('form-login');
    var btnEntrar = document.getElementById('btn-entrar');

    if (formLogin && btnEntrar) {
        formLogin.addEventListener('submit', function () {
            btnEntrar.disabled = true;
            btnEntrar.classList.add('is-loading');
            btnEntrar.querySelector('.btn-label').textContent = 'Entrando…';
        });
    }
});
