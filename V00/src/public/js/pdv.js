/**
 * ============================================
 * JAVASCRIPT - PDV (Ponto de Venda)
 * ============================================
 */

let carrinho = [];

function adicionarProdutoCarrinho(el) {
    // Ler dados do atributo data-
    const produto = {
        id: parseInt(el.closest('.produto-item').dataset.id),
        nome: el.closest('.produto-item').dataset.nome,
        preco: parseFloat(el.closest('.produto-item').dataset.preco),
        estoque: parseInt(el.closest('.produto-item').dataset.estoque)
    };
    
    adicionarAoCarrinho(produto);
}

function adicionarAoCarrinho(produto) {
    // Support both 'id' and 'Id' (case insensitive)
    const produtoId = produto.id || produto.Id;
    const produtoNome = produto.nome || produto.Nome;
    const produtoPreco = produto.preco || produto.Preco;
    const produtoEstoque = produto.estoque || produto.Estoque || 999;
    
    const index = carrinho.findIndex(item => item.id === produtoId);
    if(index !== -1) {
        carrinho[index].quantidade++;
    } else {
        carrinho.push({
            id: produtoId,
            nome: produtoNome,
            preco: parseFloat(produtoPreco),
            quantidade: 1
        });
    }
    atualizarCarrinho();
    showAlert('Produto adicionado ao carrinho!', 'success');
}

function atualizarCarrinho() {
    const container = document.getElementById('carrinhoItens');
    
    if(carrinho.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #999;">🛒<br>Carrinho vazio</div>';
        document.getElementById('subtotal').textContent = '0,00 MZN';
        document.getElementById('total').textContent = '0,00 MZN';
        return;
    }
    
    let html = '';
    carrinho.forEach((item, index) => {
        const subtotal = item.preco * item.quantidade;
        html += `
            <div class="carrinho-item">
                <div class="item-info">
                    <div class="item-nome">${item.nome}</div>
                    <div class="item-preco">${formatarMoeda(item.preco)} x ${item.quantidade}</div>
                </div>
                <div class="item-qtd">
                    <button class="btn-qtd" onclick="alterarQuantidade(${index}, -1)">-</button>
                    <span>${item.quantidade}</span>
                    <button class="btn-qtd" onclick="alterarQuantidade(${index}, 1)">+</button>
                </div>
                <div class="item-subtotal">${formatarMoeda(subtotal)}</div>
                <button class="btn-remover" onclick="removerItem(${index})">🗑️</button>
            </div>
        `;
    });
    
    container.innerHTML = html;
    calcularTotal();
}

function alterarQuantidade(index, delta) {
    carrinho[index].quantidade += delta;
    if(carrinho[index].quantidade <= 0) {
        carrinho.splice(index, 1);
    }
    atualizarCarrinho();
}

function removerItem(index) {
    carrinho.splice(index, 1);
    atualizarCarrinho();
}

function calcularTotal() {
    let subtotal = 0;
    carrinho.forEach(item => {
        subtotal += item.preco * item.quantidade;
    });
    const desconto = parseFloat(document.getElementById('desconto').value) || 0;
    const total = subtotal - desconto;
    document.getElementById('subtotal').textContent = formatarMoeda(subtotal);
    document.getElementById('total').textContent = formatarMoeda(total);
}

function limparCarrinho() {
    if(carrinho.length === 0) return;
    if(confirm('Deseja limpar o carrinho?')) {
        carrinho = [];
        document.getElementById('desconto').value = 0;
        atualizarCarrinho();
    }
}

function finalizarVenda() {
    if(carrinho.length === 0) {
        showAlert('Adicione produtos ao carrinho!', 'warning');
        return;
    }
    
    // Se nenhuma mesa for selecionada, define automaticamente a mesa 'Balcão' (primeira opção com valor > 0)
    let mesa_id = document.getElementById('mesa_id').value;
    if (!mesa_id || mesa_id === '' || mesa_id === '0') {
        // Busca a opção do select que contenha 'Balcão' (ignora qualquer outra)
        const select = document.getElementById('mesa_id');
        let balcaoId = null;
        for (let i = 0; i < select.options.length; i++) {
            const opt = select.options[i];
            if (opt.textContent.toLowerCase().includes('balcão') && opt.value > 0) {
                balcaoId = opt.value;
                break;
            }
        }
        if (!balcaoId) {
            showAlert('⚠️ Cadastre uma mesa chamada "Balcão" para lançar pedidos sem mesa.', 'danger');
            return;
        }
        mesa_id = parseInt(balcaoId);
        select.value = mesa_id;
    } else {
        mesa_id = parseInt(mesa_id);
    }
    const desconto = parseFloat(document.getElementById('desconto').value) || 0;
    // Não envia forma_pagamento na criação do pedido, só no pagamento
    let subtotal = 0;
    carrinho.forEach(item => { subtotal += item.preco * item.quantidade; });
    const total = subtotal - desconto;
    // Envia dados do pedido para produção (bar/cozinha) usando api/pedido_novo.php
    const dados = {
        mesa_id: mesa_id,
        itens: carrinho
    };
    console.log('Dados enviados:', JSON.stringify(dados));
    fetch('api/pedido_novo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados)
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            showAlert('Pedido lançado para produção! Aguarde preparo para liberar pagamento.', 'success');
            carrinho = [];
            document.getElementById('desconto').value = 0;
            document.getElementById('mesa_id').value = '';
            atualizarCarrinho();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Erro ao processar pedido: ' + error.message, 'danger');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const inputBuscar = document.getElementById('buscarProduto');
    const filtroCategoria = document.getElementById('filtroCategoria');
    
    if(inputBuscar) {
        inputBuscar.addEventListener('keyup', function() {
            filtrarProdutos();
        });
    }
    
    if(filtroCategoria) {
        filtroCategoria.addEventListener('change', function() {
            filtrarProdutos();
        });
    }
});

function filtrarProdutos() {
    const busca = document.getElementById('buscarProduto')?.value.toLowerCase() || '';
    const categoria = document.getElementById('filtroCategoria')?.value || '';
    const items = document.querySelectorAll('.produto-item');
    
    items.forEach(item => {
        const nome = item.querySelector('.nome')?.textContent.toLowerCase() || '';
        const itemCategoria = item.getAttribute('data-categoria') || '';
        
        let mostrar = true;
        
        // Filtro por busca
        if(busca && !nome.includes(busca)) {
            mostrar = false;
        }
        
        // Filtro por categoria
        if(categoria && itemCategoria !== categoria) {
            mostrar = false;
        }
        
        item.style.display = mostrar ? 'block' : 'none';
    });
}

function formatarMoeda(valor) {
    return valor.toFixed(2).replace('.', ',') + ' MZN';
}

function showAlert(message, type) {
    const alertDiv = document.getElementById('alertVenda');
    if (!alertDiv) return;

    alertDiv.textContent = message;
    alertDiv.className = 'alert alert-' + (type === 'warning' ? 'warning' : (type === 'success' ? 'success' : 'danger'));
    alertDiv.style.display = 'block';
    setTimeout(() => {
        alertDiv.style.display = 'none';
    }, 5000);
}

// =============================================
// INTEGRAÇÃO: receber pagamento de pedido
// chamado pelos botões na seção "Pedidos Aguardando"
// =============================================
function receberPagamentoPedido(pedidoId) {
    const sel = document.getElementById('forma_pag_' + pedidoId);
    const btn = document.getElementById('btn_pagar_' + pedidoId);

    if (!sel || !btn) return;

    const formaPag = sel.value;
    const originalHtml = btn.innerHTML;

    if (!confirm('Confirmar recebimento do pedido #' + pedidoId + ' via ' + formaPag + '?')) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processando...';

    const formData = new FormData();
    formData.append('id', pedidoId);
    formData.append('forma_pagamento', formaPag);
    formData.append('desconto', '0');

    fetch('api/pedido_pagar.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.text())
    .then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Resposta inválida do servidor');
        }
    })
    .then(data => {
        if (data.success) {
            // Remove row visualmente
            const row = document.getElementById('pedido_row_' + pedidoId);
            if (row) {
                row.style.transition = 'opacity 0.4s';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 400);
            }
            showAlert(
                '✅ Pedido #' + pedidoId + ' pago! Fatura: ' + (data.numero_fatura || ''),
                'success'
            );
            // Atualiza mesas automaticamente após pagamento
            if (typeof atualizarMesas === 'function') {
                setTimeout(() => atualizarMesas(), 1200); // Aguarda animação e backend
            } else {
                // fallback: recarrega página se função não existir
                setTimeout(() => location.reload(), 2500);
            }
        } else {
            showAlert(data.message || 'Erro ao receber pagamento', 'danger');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    })
    .catch(e => {
        showAlert('Erro de conexão: ' + e.message, 'danger');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
}
