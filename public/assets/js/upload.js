document.addEventListener('DOMContentLoaded', function () {
    var cfg = window.DATABRIDGE_UPLOAD;
    var dropzone = document.getElementById('dropzone');
    var inputArquivo = document.getElementById('input-arquivo');
    var btnSelecionar = document.getElementById('btn-selecionar-arquivo');
    var nomeArquivoEl = document.getElementById('nome-arquivo-selecionado');
    var cardPreview = document.getElementById('card-preview');
    var badgeLinhas = document.getElementById('badge-linhas');
    var areaMapeamento = document.getElementById('area-mapeamento');
    var tabelaPreview = document.getElementById('tabela-preview');
    var areaResultado = document.getElementById('area-resultado');
    var btnConfirmar = document.getElementById('btn-confirmar-importar');
    var selTemplate = document.getElementById('sel-template');
    var uploadProgress = document.getElementById('upload-progress');
    var progressFill = document.getElementById('progress-fill');
    var progressText = document.getElementById('progress-text');

    if (!cfg || !dropzone) {
        return;
    }

    var arquivoAtual = null;
    var colunasCsvAtuais = [];

    btnSelecionar.addEventListener('click', function () {
        inputArquivo.click();
    });

    dropzone.addEventListener('click', function (e) {
        if (e.target === btnSelecionar) return;
        inputArquivo.click();
    });

    inputArquivo.addEventListener('change', function () {
        if (inputArquivo.files.length > 0) {
            processarArquivo(inputArquivo.files[0]);
        }
    });

    ['dragover', 'dragenter'].forEach(function (evt) {
        dropzone.addEventListener(evt, function (e) {
            e.preventDefault();
            dropzone.classList.add('is-dragover');
        });
    });
    ['dragleave', 'dragend'].forEach(function (evt) {
        dropzone.addEventListener(evt, function () {
            dropzone.classList.remove('is-dragover');
        });
    });
    dropzone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropzone.classList.remove('is-dragover');
        if (e.dataTransfer.files.length > 0) {
            processarArquivo(e.dataTransfer.files[0]);
        }
    });

    function processarArquivo(arquivo) {
        arquivoAtual = arquivo;
        nomeArquivoEl.textContent = arquivo.name;
        nomeArquivoEl.style.display = 'block';
        areaResultado.innerHTML = '';
        btnConfirmar.disabled = true;

        var formData = new FormData();
        formData.append('arquivo', arquivo);

        fetch((window.DATABRIDGE_BASE_URL || '') + '/upload?ajax=preview', { method: 'POST', body: formData })
            .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
            .then(function (res) {
                if (!res.ok) {
                    mostrarResultado('error', res.body.detail || 'Falha ao ler o CSV.');
                    return;
                }
                renderizarPreview(res.body.colunas, res.body.linhas);
            })
            .catch(function () {
                mostrarResultado('error', 'Não foi possível enviar o arquivo (erro de rede).');
            });
    }

    function renderizarPreview(colunas, linhas) {
        colunasCsvAtuais = colunas;
        cardPreview.style.display = 'block';
        badgeLinhas.textContent = linhas.length + ' linha(s) de exemplo';

        // Tabela de prévia
        var thead = '<thead><tr>' + colunas.map(function (c) { return '<th>' + escapeHtml(c) + '</th>'; }).join('') + '</tr></thead>';
        var tbody = '<tbody>' + linhas.map(function (linha) {
            return '<tr>' + linha.map(function (v) { return '<td>' + (v === null ? '<span class="text-muted">—</span>' : escapeHtml(String(v))) + '</td>'; }).join('') + '</tr>';
        }).join('') + '</tbody>';
        tabelaPreview.innerHTML = thead + tbody;

        // UI de mapeamento
        var templateMapeamento = null;
        if (selTemplate && selTemplate.value) {
            try { templateMapeamento = JSON.parse(selTemplate.value); } catch (e) { templateMapeamento = null; }
        }

        var html = '<table><thead><tr><th>Coluna do CSV</th><th>Coluna de destino</th></tr></thead><tbody>';
        colunas.forEach(function (colCsv, i) {
            var selecionado = escolherDestinoPadrao(colCsv, templateMapeamento);
            html += '<tr><td>' + escapeHtml(colCsv) + '</td><td>' +
                '<select data-coluna-csv="' + escapeHtml(colCsv) + '" class="sel-destino">' +
                '<option value="">— não importar —</option>' +
                cfg.colunasDestino.map(function (cd) {
                    return '<option value="' + escapeHtml(cd) + '" ' + (cd === selecionado ? 'selected' : '') + '>' + escapeHtml(cd) + '</option>';
                }).join('') +
                '</select></td></tr>';
        });
        html += '</tbody></table>';
        areaMapeamento.innerHTML = html;

        btnConfirmar.disabled = false;
    }

    function escolherDestinoPadrao(colCsv, templateMapeamento) {
        if (templateMapeamento) {
            var doTemplate = templateMapeamento.find(function (m) { return m.coluna_csv.toLowerCase() === colCsv.toLowerCase(); });
            if (doTemplate) return doTemplate.coluna_destino;
        }
        var match = cfg.colunasDestino.find(function (cd) { return cd.toLowerCase() === colCsv.toLowerCase(); });
        return match || '';
    }

    if (selTemplate) {
        selTemplate.addEventListener('change', function () {
            if (colunasCsvAtuais.length > 0) {
                // reaplica a prévia já carregada com o novo template
                var linhasAtuais = Array.from(tabelaPreview.querySelectorAll('tbody tr')).map(function (tr) {
                    return Array.from(tr.children).map(function (td) { return td.textContent === '—' ? null : td.textContent; });
                });
                renderizarPreview(colunasCsvAtuais, linhasAtuais);
            }
        });
    }

    btnConfirmar.addEventListener('click', function () {
        if (!arquivoAtual) return;

        var mapeamento = Array.from(areaMapeamento.querySelectorAll('.sel-destino'))
            .map(function (sel) { return { coluna_csv: sel.getAttribute('data-coluna-csv'), coluna_destino: sel.value }; })
            .filter(function (m) { return m.coluna_destino !== ''; });

        if (mapeamento.length === 0) {
            mostrarResultado('error', 'Mapeie pelo menos uma coluna antes de importar.');
            return;
        }

        btnConfirmar.disabled = true;
        btnConfirmar.textContent = 'Importando…';
        resetProgress();

        var formData = new FormData();
        formData.append('arquivo', arquivoAtual);
        formData.append('conexao_id', cfg.conexaoId);
        formData.append('schema_destino', cfg.schema);
        formData.append('tabela_destino', cfg.tabela);
        formData.append('mapeamento_colunas', JSON.stringify(mapeamento));

        var xhr = new XMLHttpRequest();
        xhr.open('POST', (window.DATABRIDGE_BASE_URL || '') + '/upload?ajax=importar', true);

        // Progresso real só existe pro envio do arquivo (bytes indo pro servidor).
        // O processamento em si (ler o CSV, validar, gravar no banco) acontece
        // DEPOIS que o upload termina e não tem como medir progresso de verdade —
        // por isso reserva-se 0-85% pro envio e mostra um estado "processando"
        // (indeterminado) até a resposta final chegar, em vez de travar em 100%
        // enquanto ainda tem trabalho rolando no servidor.
        xhr.upload.addEventListener('progress', function (event) {
            if (event.lengthComputable) {
                var percentual = Math.round((event.loaded / event.total) * 85);
                atualizarProgresso(percentual, 'Enviando arquivo… ' + Math.round((event.loaded / event.total) * 100) + '%');
            }
        });

        xhr.upload.addEventListener('load', function () {
            atualizarProgresso(90, 'Processando os dados no servidor…', true);
        });

        xhr.addEventListener('load', function () {
            var responseBody = null;
            try { responseBody = JSON.parse(xhr.responseText); } catch (e) { responseBody = null; }
            var ok = xhr.status >= 200 && xhr.status < 300;
            if (!ok) {
                mostrarResultado('error', (responseBody && responseBody.detail) ? responseBody.detail : 'Falha ao importar.');
                return;
            }
            if (responseBody && responseBody.status === 'sucesso') {
                atualizarProgresso(100, 'Concluído.');
                mostrarResultado('success', responseBody.linhas_processadas + ' linha(s) importada(s) com sucesso para ' + cfg.schema + '.' + cfg.tabela + '.');
            } else {
                atualizarProgresso(100, 'Concluído.');
                mostrarResultado('error', (responseBody && responseBody.mensagem_erro) ? responseBody.mensagem_erro : 'A importação falhou.');
            }
        });

        xhr.addEventListener('error', function () {
            mostrarResultado('error', 'Não foi possível enviar o arquivo (erro de rede).');
        });

        xhr.addEventListener('abort', function () {
            mostrarResultado('error', 'Upload cancelado.');
        });

        xhr.addEventListener('loadend', function () {
            btnConfirmar.disabled = false;
            btnConfirmar.textContent = 'Confirmar e importar';
        });

        xhr.send(formData);
    });

    function mostrarResultado(tipo, mensagem) {
        var div = document.createElement('div');
        div.className = 'alert alert-' + tipo;
        div.textContent = mensagem;
        areaResultado.innerHTML = '';
        areaResultado.appendChild(div);
        if (tipo === 'success') {
            var link = document.createElement('a');
            link.href = (window.DATABRIDGE_BASE_URL || '') + '/historico-importacoes';
            link.className = 'btn btn-secondary btn-sm';
            link.style.marginTop = '8px';
            link.textContent = 'Ver histórico de importações';
            areaResultado.appendChild(link);
        }
    }

    function atualizarProgresso(percentual, texto, indeterminado) {
        if (!uploadProgress || !progressFill || !progressText) {
            return;
        }
        uploadProgress.style.display = 'block';
        progressFill.classList.toggle('is-indeterminate', !!indeterminado);
        progressFill.style.width = indeterminado ? '' : percentual + '%';
        progressText.textContent = texto || (percentual + '%');
    }

    function resetProgress() {
        if (!uploadProgress || !progressFill || !progressText) {
            return;
        }
        uploadProgress.style.display = 'none';
        progressFill.classList.remove('is-indeterminate');
        progressFill.style.width = '0%';
        progressText.textContent = '0%';
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
