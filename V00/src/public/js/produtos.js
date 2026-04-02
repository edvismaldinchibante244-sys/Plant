/**
 * JAVASCRIPT - GESTÃO DE PRODUTOS (Bootstrap 5)
 * Fixed: Added BASE_URL to all API fetch calls
 * Patch: Adiciona checagem e logs para BASE_URL e RESTAURANTE_ID
 */

if (typeof BASE_URL === 'undefined' || !BASE_URL) {
    console.error('BASE_URL não está definido!');
    alert('Erro: BASE_URL não está definido!');
}
if (typeof RESTAURANTE_ID === 'undefined' || !RESTAURANTE_ID) {
    console.error('RESTAURANTE_ID não está definido!');
    alert('Erro: RESTAURANTE_ID não está definido!');
}
let modalProdutoInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('modalProduto');
    if (modalEl) modalProdutoInstance = new bootstrap.Modal(modalEl);
    
    const novaCategoria = document.getElementById('novaCategoria');
    if(novaCategoria) novaCategoria.addEventListener('keypress', function(e) { 
        if(e.key === 'Enter') { e.preventDefault(); adicionarNovaCategoria(); } 
    });
    
    const form = document.getElementById('formProduto');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            const produto_id = document.getElementById('produto_id').value;
            const url = BASE_URL + (produto_id ? 'api/produto_editar.php' : 'api/produto_cadastrar.php');
            fetch(url, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => { 
                if(data.success) { 
                    showAlertModal(data.message, 'success'); 
                    setTimeout(() => location.reload(), 1500); 
                } else { 
                    showAlertModal(data.message, 'danger'); 
                } 
            })
            .catch(error => { showAlertModal('Erro ao processar', 'danger'); });
        });
    }
    
    const inputBuscar = document.getElementById('buscar');
    const filtroCategoria = document.getElementById('filtroCategoria');
    if(inputBuscar) inputBuscar.addEventListener('keyup', filtrarProdutos);
    if(filtroCategoria) filtroCategoria.addEventListener('change', filtrarProdutos);
    
    const inputImagem = document.getElementById('imagem');
    if (inputImagem) {
        inputImagem.addEventListener('change', function() {
            previewImagem(inputImagem);
        });
    }
});

function previewImagem(input) {
    const file = input?.files?.[0];
    const preview = document.getElementById('imagemPreview');
    const placeholder = document.getElementById('imagemPlaceholder');
    const imgField = document.getElementById('imagem_existing');

    if (!preview) return;

    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            preview.src = ev.target.result;
            preview.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
        if (imgField) imgField.value = '';
        return;
    }

    preview.src = '';
    preview.style.display = 'none';
    if (placeholder) placeholder.style.display = 'flex';
}

function abrirModal() {
    document.getElementById('tituloModal').textContent = 'Novo Produto';
    document.getElementById('formProduto').reset();
    document.getElementById('produto_id').value = '';
    document.getElementById('ativo').checked = true;
    const imgPrev = document.getElementById('imagemPreview');
    const imgField = document.getElementById('imagem_existing');
    const imgPlaceholder = document.getElementById('imagemPlaceholder');
    if (imgPrev) { imgPrev.src = ''; imgPrev.style.display = 'none'; }
    if (imgPlaceholder) imgPlaceholder.style.display = 'flex';
    if (imgField) imgField.value = '';
    if (modalProdutoInstance) modalProdutoInstance.show();
    else document.getElementById('modalProduto').style.display = 'block';
}

function fecharModal() {
    if (modalProdutoInstance) modalProdutoInstance.hide();
    else document.getElementById('modalProduto').style.display = 'none';
    document.getElementById('alertModal').style.display = 'none';
}

function editarProduto(id) {
    console.log('Editar produto ID:', id);
    console.log('BASE_URL:', BASE_URL);
    
    document.getElementById('tituloModal').textContent = 'Carregando...';
    document.getElementById('formProduto').reset();
    document.getElementById('produto_id').value = id;
    document.getElementById('alertModal').style.display = 'none';
    if (modalProdutoInstance) modalProdutoInstance.show();
    else document.getElementById('modalProduto').style.display = 'block';
    
    const apiUrl = BASE_URL + 'api/produto_buscar.php?id=' + id;
    console.log('Fetching URL:', apiUrl);
    
    fetch(apiUrl)
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if(data.success) {
            const produto = data.data;
            document.getElementById('tituloModal').textContent = 'Editar Produto';
            document.getElementById('produto_id').value = produto.id;
            document.getElementById('nome').value = produto.nome;
            const selectCategoria = document.getElementById('categoria_id');
            if (selectCategoria) {
                // Garante que o valor seja string e exista no select
                const categoriaIdStr = produto.categoria_id ? String(produto.categoria_id) : '';
                let found = false;
                for (let i = 0; i < selectCategoria.options.length; i++) {
                    if (selectCategoria.options[i].value === categoriaIdStr) {
                        selectCategoria.selectedIndex = i;
                        found = true;
                        break;
                    }
                }
                if (!found) selectCategoria.selectedIndex = 0;
            }
            document.getElementById('descricao').value = produto.descricao || '';
            document.getElementById('preco').value = produto.preco;
            document.getElementById('custo').value = produto.custo || '';
            document.getElementById('estoque').value = produto.estoque;
            document.getElementById('estoque_minimo').value = produto.estoque_minimo;
            document.getElementById('ativo').checked = produto.ativo == 1;
            const imgPrev = document.getElementById('imagemPreview');
            const imgField = document.getElementById('imagem_existing');
            const imgPlaceholder = document.getElementById('imagemPlaceholder');
            if (produto.imagem) {
                // sanitize possible old prefix
                let imgPath = produto.imagem.replace(/src\/public\//g, '');
                imgPrev.src = BASE_URL + imgPath;
                imgPrev.style.display = 'block';
                if (imgPlaceholder) imgPlaceholder.style.display = 'none';
                imgField.value = imgPath;
            }
            else {
                imgPrev.src = '';
                imgPrev.style.display = 'none';
                if (imgPlaceholder) imgPlaceholder.style.display = 'flex';
                imgField.value = '';
            }
        } else { 
            document.getElementById('tituloModal').textContent = 'Erro'; 
            showAlertModal(data.message || 'Erro ao carregar', 'danger'); 
        }
    })
    .catch(error => { 
        console.error('Fetch error:', error);
        document.getElementById('tituloModal').textContent = 'Erro'; 
        showAlertModal('Erro ao carregar dados: ' + error.message, 'danger'); 
    });
}



function adicionarNovaCategoria() {
    const nomeCategoria = document.getElementById('novaCategoria').value.trim();
    const botao = event?.target || document.querySelector('button[onclick="adicionarNovaCategoria()"]');
    if(!nomeCategoria) { showAlertModal('Digite o nome da categoria', 'warning'); document.getElementById('novaCategoria').focus(); return; }
    if(nomeCategoria.length < 2) { showAlertModal('O nome deve ter pelo menos 2 caracteres', 'warning'); return; }
    if(botao) botao.disabled = true;
    const formData = new FormData();
    formData.append('nome', nomeCategoria);
    fetch(BASE_URL + 'api/categoria_cadastrar.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            const select = document.getElementById('categoria_id');
            const jaExiste = Array.from(select.options).some(opt => opt.value === String(data.categoria.id));
            if(!jaExiste) { const option = document.createElement('option'); option.value = data.categoria.id; option.textContent = data.categoria.nome; select.appendChild(option); }
            select.value = data.categoria.id;
            document.getElementById('novaCategoria').value = '';
            showAlertModal('✓ ' + data.message, 'success');
        } else { showAlertModal('❌ ' + data.message, 'danger'); }
    })
    .catch(error => { showAlertModal('Erro ao criar categoria', 'danger'); })
    .finally(() => {
        if(botao) botao.disabled = false;
    });
}

function deletarProduto(id) {
    if(!confirm('Tem certeza que deseja inativar este produto?')) return;
    
    const formData = new FormData();
    formData.append('id', id);
    
    fetch(BASE_URL + 'api/produto_deletar.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.success) { location.reload(); }
        else { alert(data.message); }
    });
}

function atualizarEstoque(id) {
    const quantidade = prompt('Digite a quantidade (use - para saída):');
    if(quantidade === null) return;
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('quantidade', quantidade);
    
    fetch(BASE_URL + 'api/produto_estoque.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.success) { location.reload(); }
        else { alert(data.message); }
    });
}

function showAlertModal(message, type) {
    const alertDiv = document.getElementById('alertModal');
    alertDiv.textContent = message;
    alertDiv.className = 'alert alert-' + type;
    alertDiv.style.display = 'block';
    setTimeout(() => alertDiv.style.display = 'none', 5000);
}

function filtrarProdutos() {
    const busca = document.getElementById('buscar').value.toLowerCase().trim();
    const categoriaFiltro = document.getElementById('filtroCategoria').value.trim();
    carregarProdutos(categoriaFiltro, busca);
}

// Função para carregar produtos via AJAX
function carregarProdutos(categoriaId = '', busca = '') {
    const tabela = document.getElementById('tabelaProdutos');
    tabela.innerHTML = '<tr><td colspan="7" class="text-center py-5"><span class="spinner-border text-primary"></span> Carregando...</td></tr>';
    const params = new URLSearchParams();
    if (categoriaId) params.append('categoria_id', categoriaId);
    if (busca) params.append('busca', busca);
    params.append('restaurante_id', RESTAURANTE_ID);
    const url = BASE_URL + 'api/produto_listar.php?' + params.toString();
    console.log('🔍 carregarProdutos URL:', url);
    console.log('📊 RESTAURANTE_ID:', RESTAURANTE_ID);
    console.log('🌐 BASE_URL:', BASE_URL);
    
    fetch(url)
        .then(res => {
            console.log('📡 Fetch status:', res.status, res.statusText);
            console.log('📡 Response headers:', [...res.headers.entries()]);
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            }
            return res.text().then(text => {
                console.log('📄 Raw response (primeiros 500 chars):', text.substring(0, 500));
                try {
                    return JSON.parse(text);
                } catch (jsonErr) {
                    console.error('JSON Parse error:', jsonErr);
                    throw new Error('Resposta inválida JSON: ' + jsonErr.message);
                }
            });
        })
        .then(data => {
            console.log('✅ API data:', data);
            if (!data || typeof data.success === 'undefined' || !data.success) {
                console.error('❌ API success=false ou indefinido:', data);
                tabela.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-5">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3 d-block"></i>
                    <strong>Erro na API:</strong><br>
                    <small>${data?.message || 'Resposta inválida (success=false)'}</small>
                </td></tr>`;
                return;
            }
            const produtos = data.data || [];
            console.log('📦 Produtos encontrados:', produtos.length);
            if (!produtos.length) {
                tabela.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-3 d-block"></i><strong>Nenhum produto encontrado</strong><br><small>para os filtros atuais</small></td></tr>';
                return;
            }
            tabela.innerHTML = produtos.map(p => `
                <tr>
                    <td>
                        <img src="${p.imagem ? (BASE_URL + p.imagem.replace(/src\/public\//g, '')) : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(p.nome) + '&background=FF6B35&color=ffffff&size=80'}"
                             class="product-img"
                             style="width:80px;height:80px;object-fit:cover;border-radius:8px;"
                             onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(p.nome)}&background=FF6B35&color=ffffff&size=80'" />
                    </td>
                    <td>
                        <div class="product-info">
                            <span class="product-name">${p.nome || '—'}</span>
                            ${p.descricao ? `<span class="product-desc">${p.descricao}</span>` : ''}
                        </div>
                    </td>
                    <td style="cursor:pointer;color:#FF6B35;text-decoration:underline;"
                        data-categoria-id="${p.categoria_id || ''}">${p.categoria_nome || '—'}</td>
                    <td><strong>${Number(p.preco || 0).toLocaleString('pt-MZ', {minimumFractionDigits:2, maximumFractionDigits:2})} MZN</strong></td>
                    <td>${(p.estoque || 0) <= (p.estoque_minimo || 0) ? `<span class="badge-custom badge-warning">⚠️ ${p.estoque || 0}</span>` : `<span class="badge-custom badge-success">${p.estoque || 0}</span>`}</td>
                    <td>${p.ativo == 1 ? '<span class="badge-custom badge-success">Ativo</span>' : '<span class="badge-custom badge-danger">Inativo</span>'}</td>
                    <td class="text-center">
                        <button class="btn btn-info btn-action btn-sm me-1" onclick="editarProduto(${p.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-warning btn-action btn-sm me-1" onclick="atualizarEstoque(${p.id})" title="Estoque">
                            <i class="fas fa-box"></i>
                        </button>
                        <button class="btn btn-danger btn-action btn-sm" onclick="deletarProduto(${p.id})" title="Excluir">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        })
        .catch((err) => {
            console.error('💥 Fetch ERROR completo:', err);
            console.error('💥 Stack trace:', err.stack);
            tabela.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-5">
                <i class="fas fa-network-wired fa-2x mb-3 d-block text-danger"></i>
                <strong>Erro de conexão:</strong><br>
                <code>${err.message}</code><br>
                <small>Abrir F12 → Console para detalhes técnicos</small>
            </td></tr>`;
        });
}

// Chamar AJAX ao carregar página
window.addEventListener('DOMContentLoaded', function() {
    carregarProdutos();

    // Ajustar botões de categoria para AJAX
    const botoes = document.querySelectorAll('.categoria-btn');
    const filtro = document.getElementById('filtroCategoria');
    if (botoes && filtro) {
        botoes.forEach(btn => {
            btn.addEventListener('click', function() {
                botoes.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const catId = btn.getAttribute('data-categoria-id') || '';
                filtro.value = catId;
                carregarProdutos(catId, document.getElementById('buscar').value.toLowerCase().trim());
            });
        });
    }

    // Permitir clicar na célula da categoria na tabela para filtrar
    document.addEventListener('click', function(e) {
        if (e.target && e.target.matches('td[data-categoria-id]')) {
            const categoriaId = String(e.target.getAttribute('data-categoria-id') || '');
            if (filtro) filtro.value = categoriaId;
            // Ativar botão correspondente
            botoes.forEach(b => {
                if (String(b.getAttribute('data-categoria-id') || '') === categoriaId) b.classList.add('active');
                else b.classList.remove('active');
            });
            carregarProdutos(categoriaId, document.getElementById('buscar').value.toLowerCase().trim());
        }
    });
});

// Ajustar select de categoria para AJAX
window.addEventListener('DOMContentLoaded', function() {
    const filtro = document.getElementById('filtroCategoria');
    if (filtro) {
        filtro.addEventListener('change', function() {
            carregarProdutos(filtro.value, document.getElementById('buscar').value.toLowerCase().trim());
        });
    }
});

// Ajustar busca para AJAX
window.addEventListener('DOMContentLoaded', function() {
    const inputBuscar = document.getElementById('buscar');
    if (inputBuscar) {
        inputBuscar.addEventListener('keyup', function() {
            carregarProdutos(document.getElementById('filtroCategoria').value, inputBuscar.value.toLowerCase().trim());
        });
    }
});

