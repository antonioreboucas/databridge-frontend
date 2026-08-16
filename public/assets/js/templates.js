document.addEventListener('DOMContentLoaded', function () {
    var selConexao = document.getElementById('sel-conexao');
    var selSchema = document.getElementById('sel-schema');
    var selTabela = document.getElementById('sel-tabela');
    var inputTabelaNova = document.getElementById('input-tabela-nova');
    var linkAlternarModo = document.getElementById('link-alternar-modo-tabela');
    var painelNovaTabela = document.getElementById('painel-nova-tabela');
    var btnGerarSql = document.getElementById('btn-gerar-sql');
    var btnCriarTabela = document.getElementById('btn-criar-tabela');
    var statusCriacaoTabela = document.getElementById('status-criacao-tabela');
    var sqlPreview = document.getElementById('sql-preview');
    var lista = document.getElementById('lista-colunas');
    var btnAdd = document.getElementById('btn-add-coluna');

    if (!selConexao || !selSchema || !selTabela || !lista) {
        return;
    }

    var permiteNovaTabela = !(window.DATABRIDGE_TEMPLATE && window.DATABRIDGE_TEMPLATE.edicao);
    var TABELA_NOVA_VALOR = '__nova__';
    var TIPOS_COLUNA = [
        ['texto_curto', 'Texto curto'],
        ['texto_longo', 'Texto longo'],
        ['inteiro', 'Número inteiro'],
        ['numero_grande', 'Número grande'],
        ['decimal', 'Decimal'],
        ['booleano', 'Verdadeiro/Falso'],
        ['data', 'Data'],
        ['data_hora', 'Data e hora'],
    ];

    var colunasDisponiveis = [];
    var modoNovaTabela = false;

    function definirOpcoes(select, opcoes, placeholder) {
        select.innerHTML = '';
        var optPlaceholder = document.createElement('option');
        optPlaceholder.value = '';
        optPlaceholder.textContent = placeholder;
        select.appendChild(optPlaceholder);
        opcoes.forEach(function (valor) {
            var opt = document.createElement('option');
            opt.value = valor;
            opt.textContent = valor;
            select.appendChild(opt);
        });
    }

    function buscarJson(url, opcoes) {
        return fetch(url, opcoes).then(function (r) {
            return r.json().then(function (body) {
                if (!r.ok) {
                    throw new Error(body.detail || 'Falha ao buscar dados.');
                }
                return body;
            });
        });
    }

    function urlFrontend(caminho) {
        return (window.DATABRIDGE_BASE_URL || '') + '/templates-mapeamento' + caminho;
    }

    // --- Modo "coluna de tabela existente" (select) vs "nova coluna" (nome + tipo) ---

    function celulaDestinoExistenteHtml() {
        return '<label class="text-muted" style="text-transform:none; font-weight:400;">Coluna Tabela</label>' +
            '<select name="coluna_destino[]" class="sel-coluna-destino" required></select>';
    }

    function celulaDestinoNovaHtml() {
        var optionsHtml = TIPOS_COLUNA.map(function (t) {
            return '<option value="' + t[0] + '">' + t[1] + '</option>';
        }).join('');
        return '<label class="text-muted" style="text-transform:none; font-weight:400;">Coluna Tabela</label>' +
            '<div style="display:flex; gap:6px;">' +
            '<input type="text" name="coluna_destino[]" class="input-coluna-destino-nova" placeholder="nome_coluna" style="flex:1;" required>' +
            '<select name="tipo_dado[]" class="sel-tipo-dado" style="width:auto;">' + optionsHtml + '</select>' +
            '</div>';
    }

    function aplicarModoNaCelula(celula) {
        celula.innerHTML = modoNovaTabela ? celulaDestinoNovaHtml() : celulaDestinoExistenteHtml();
        if (!modoNovaTabela) {
            atualizarSelectUnico(celula.querySelector('.sel-coluna-destino'));
        }
    }

    function aplicarModoATodasLinhas() {
        lista.querySelectorAll('.celula-coluna-destino').forEach(aplicarModoNaCelula);
    }

    function atualizarSelectUnico(select) {
        if (!select) {
            return;
        }
        var atual = select.value;
        select.innerHTML = '';
        var optVazio = document.createElement('option');
        optVazio.value = '';
        optVazio.textContent = colunasDisponiveis.length ? 'Selecione…' : 'Selecione uma tabela primeiro';
        select.appendChild(optVazio);
        colunasDisponiveis.forEach(function (nomeColuna) {
            var opt = document.createElement('option');
            opt.value = nomeColuna;
            opt.textContent = nomeColuna;
            if (nomeColuna === atual) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
        select.disabled = colunasDisponiveis.length === 0;
    }

    function atualizarSelectsColunaDestino() {
        if (modoNovaTabela) {
            return;
        }
        lista.querySelectorAll('.sel-coluna-destino').forEach(atualizarSelectUnico);
    }

    function limparStatusCriacao() {
        if (statusCriacaoTabela) {
            statusCriacaoTabela.innerHTML = '';
        }
        if (sqlPreview) {
            sqlPreview.style.display = 'none';
            sqlPreview.textContent = '';
        }
    }

    function mostrarStatusCriacao(tipo, mensagem) {
        if (!statusCriacaoTabela) {
            return;
        }
        var div = document.createElement('div');
        div.className = 'alert alert-' + (tipo === 'success' ? 'success' : 'error');
        div.textContent = mensagem;
        statusCriacaoTabela.innerHTML = '';
        statusCriacaoTabela.appendChild(div);
    }

    function definirModoNovaTabela(ativo) {
        modoNovaTabela = ativo;
        limparStatusCriacao();

        if (ativo) {
            selTabela.style.display = 'none';
            selTabela.disabled = true;
            inputTabelaNova.style.display = '';
            inputTabelaNova.disabled = false;
            inputTabelaNova.value = '';
            inputTabelaNova.focus();
            if (painelNovaTabela) {
                painelNovaTabela.style.display = '';
            }
            if (linkAlternarModo) {
                linkAlternarModo.textContent = 'A tabela já existe? Selecionar em vez de criar »';
            }
        } else {
            selTabela.style.display = '';
            selTabela.disabled = false;
            if (selTabela.value === TABELA_NOVA_VALOR) {
                selTabela.value = '';
            }
            inputTabelaNova.style.display = 'none';
            inputTabelaNova.disabled = true;
            if (painelNovaTabela) {
                painelNovaTabela.style.display = 'none';
            }
            if (linkAlternarModo) {
                linkAlternarModo.textContent = 'A tabela ainda não existe? Crie a partir deste template »';
            }
        }

        colunasDisponiveis = [];
        aplicarModoATodasLinhas();
    }

    if (linkAlternarModo && inputTabelaNova) {
        linkAlternarModo.addEventListener('click', function (e) {
            e.preventDefault();
            definirModoNovaTabela(!modoNovaTabela);
        });
    }

    selConexao.addEventListener('change', function () {
        selSchema.disabled = true;
        selTabela.disabled = true;
        definirOpcoes(selSchema, [], 'Selecione o banco primeiro…');
        definirOpcoes(selTabela, [], 'Selecione o schema primeiro…');
        colunasDisponiveis = [];
        atualizarSelectsColunaDestino();

        if (!selConexao.value) {
            return;
        }

        buscarJson(urlFrontend('?ajax=schemas&conexao_id=' + selConexao.value))
            .then(function (body) {
                definirOpcoes(selSchema, body.schemas || [], 'Selecione…');
                selSchema.disabled = false;
            })
            .catch(function (e) {
                alert(e.message);
            });
    });

    function carregarTabelas() {
        selTabela.disabled = true;
        definirOpcoes(selTabela, [], 'Selecione o schema primeiro…');
        colunasDisponiveis = [];
        atualizarSelectsColunaDestino();

        if (!selSchema.value || !selConexao.value) {
            return Promise.resolve();
        }

        return buscarJson(urlFrontend('?ajax=tabelas&conexao_id=' + selConexao.value + '&schema=' + encodeURIComponent(selSchema.value)))
            .then(function (body) {
                definirOpcoes(selTabela, body.tabelas || [], 'Selecione…');
                if (permiteNovaTabela) {
                    var optNova = document.createElement('option');
                    optNova.value = TABELA_NOVA_VALOR;
                    optNova.textContent = '+ Criar nova tabela…';
                    selTabela.appendChild(optNova);
                }
                selTabela.disabled = false;
            })
            .catch(function (e) {
                alert(e.message);
            });
    }

    selSchema.addEventListener('change', function () {
        if (modoNovaTabela) {
            definirModoNovaTabela(false);
        }
        carregarTabelas();
    });

    function carregarColunasDaTabela(nomeTabela) {
        colunasDisponiveis = [];
        atualizarSelectsColunaDestino();

        if (!nomeTabela || !selSchema.value || !selConexao.value) {
            return Promise.resolve();
        }

        return buscarJson(
            urlFrontend('?ajax=colunas&conexao_id=' + selConexao.value +
                '&schema=' + encodeURIComponent(selSchema.value) +
                '&tabela=' + encodeURIComponent(nomeTabela))
        )
            .then(function (body) {
                colunasDisponiveis = (body.colunas || []).map(function (c) { return c.nome; });
                atualizarSelectsColunaDestino();
            })
            .catch(function (e) {
                alert(e.message);
            });
    }

    selTabela.addEventListener('change', function () {
        if (selTabela.value === TABELA_NOVA_VALOR) {
            definirModoNovaTabela(true);
            return;
        }
        carregarColunasDaTabela(selTabela.value);
    });

    function criarLinha() {
        var wrapper = document.createElement('div');
        wrapper.style.display = 'flex';
        wrapper.style.gap = '8px';
        wrapper.style.alignItems = 'flex-start';
        wrapper.style.marginBottom = '8px';

        var linha = document.createElement('div');
        linha.className = 'form-row coluna-mapeamento-row';
        linha.style.flex = '1';
        linha.style.marginBottom = '0';

        var campoCsv = document.createElement('div');
        campoCsv.innerHTML = '<label class="text-muted" style="text-transform:none; font-weight:400;">Coluna CSV</label>' +
            '<input type="text" name="coluna_csv[]" placeholder="Ex: ID_Venda" required>';

        var campoDestino = document.createElement('div');
        campoDestino.className = 'celula-coluna-destino';

        linha.appendChild(campoCsv);
        linha.appendChild(campoDestino);

        var btnRemove = document.createElement('button');
        btnRemove.type = 'button';
        btnRemove.className = 'icon-btn danger';
        btnRemove.title = 'Remover coluna';
        btnRemove.style.marginTop = '20px';
        btnRemove.innerHTML = '&minus;';
        btnRemove.addEventListener('click', function () {
            wrapper.remove();
        });

        wrapper.appendChild(linha);
        wrapper.appendChild(btnRemove);
        lista.appendChild(wrapper);

        aplicarModoNaCelula(campoDestino);
    }

    if (btnAdd) {
        btnAdd.addEventListener('click', criarLinha);
    }

    // --- Gerar SQL / criar tabela a partir do template ---

    function coletarMapeamentoNovaTabela() {
        var linhas = [];
        lista.querySelectorAll('.coluna-mapeamento-row').forEach(function (linha) {
            var csv = linha.querySelector('input[name="coluna_csv[]"]');
            var destino = linha.querySelector('.input-coluna-destino-nova');
            var tipo = linha.querySelector('.sel-tipo-dado');
            var csvValor = csv ? csv.value.trim() : '';
            var destinoValor = destino ? destino.value.trim() : '';
            if (csvValor && destinoValor) {
                linhas.push({ coluna_csv: csvValor, coluna_destino: destinoValor, tipo_dado: tipo ? tipo.value : null });
            }
        });
        return linhas;
    }

    function payloadNovaTabela() {
        var mapeamento = coletarMapeamentoNovaTabela();
        if (!selSchema.value) {
            throw new Error('Selecione o schema de destino.');
        }
        if (!inputTabelaNova.value.trim()) {
            throw new Error('Informe o nome da nova tabela.');
        }
        if (mapeamento.length === 0) {
            throw new Error('Mapeie pelo menos uma coluna (com nome de destino preenchido) antes de gerar o SQL.');
        }
        return {
            schema_destino: selSchema.value,
            tabela_destino: inputTabelaNova.value.trim(),
            mapeamento_colunas: mapeamento,
        };
    }

    if (btnGerarSql) {
        btnGerarSql.addEventListener('click', function () {
            limparStatusCriacao();
            var payload;
            try {
                payload = payloadNovaTabela();
            } catch (e) {
                mostrarStatusCriacao('error', e.message);
                return;
            }

            buscarJson(urlFrontend('?ajax=gerar_sql'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
                .then(function (body) {
                    if (sqlPreview) {
                        sqlPreview.textContent = body.sql;
                        sqlPreview.style.display = '';
                    }
                })
                .catch(function (e) {
                    mostrarStatusCriacao('error', e.message);
                });
        });
    }

    if (btnCriarTabela) {
        btnCriarTabela.addEventListener('click', function () {
            limparStatusCriacao();
            var payload;
            try {
                payload = payloadNovaTabela();
            } catch (e) {
                mostrarStatusCriacao('error', e.message);
                return;
            }
            if (!selConexao.value) {
                mostrarStatusCriacao('error', 'Selecione o banco de dados onde a tabela será criada.');
                return;
            }
            payload.conexao_id = parseInt(selConexao.value, 10);

            btnCriarTabela.disabled = true;
            btnCriarTabela.textContent = 'Criando…';

            buscarJson(urlFrontend('?ajax=criar_tabela'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
                .then(function (body) {
                    // O painel de criação (onde ficam a prévia do SQL e as mensagens de status)
                    // é escondido pela troca de modo abaixo, então a confirmação de sucesso
                    // usa alert() — do contrário desapareceria antes do usuário ler.
                    var tabelaCriada = payload.tabela_destino;
                    var sqlExecutado = body.sql;
                    var nomesDigitadosPorLinha = Array.prototype.map.call(
                        lista.querySelectorAll('.coluna-mapeamento-row'),
                        function (linha) {
                            var destino = linha.querySelector('.input-coluna-destino-nova');
                            return destino ? destino.value.trim() : '';
                        }
                    );

                    definirModoNovaTabela(false);
                    carregarTabelas().then(function () {
                        selTabela.value = tabelaCriada;
                        return carregarColunasDaTabela(tabelaCriada);
                    }).then(function () {
                        var linhas = lista.querySelectorAll('.coluna-mapeamento-row');
                        linhas.forEach(function (linha, i) {
                            var select = linha.querySelector('.sel-coluna-destino');
                            var nomeOriginal = nomesDigitadosPorLinha[i];
                            if (select && nomeOriginal && colunasDisponiveis.indexOf(nomeOriginal) !== -1) {
                                select.value = nomeOriginal;
                            }
                        });
                        alert('Tabela "' + tabelaCriada + '" criada com sucesso:\n\n' + sqlExecutado);
                    });
                })
                .catch(function (e) {
                    mostrarStatusCriacao('error', e.message);
                })
                .finally(function () {
                    btnCriarTabela.disabled = false;
                    btnCriarTabela.textContent = 'Criar tabela agora';
                });
        });
    }
});
