/**
 * ============================================
 * JAVASCRIPT - CAIXA (Bootstrap 5)
 * ============================================
 */

let modalAbrirInstance = null;
let modalFecharInstance = null;

// Inicializar modais quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    // Criar instâncias dos modais Bootstrap
    const modalAbrirEl = document.getElementById('modalAbrir');
    const modalFecharEl = document.getElementById('modalFechar');
    
    if (modalAbrirEl) {
        modalAbrirInstance = new bootstrap.Modal(modalAbrirEl);
    }
    if (modalFecharEl) {
        modalFecharInstance = new bootstrap.Modal(modalFecharEl);
    }
    
    // Form de abertura
    const formAbrir = document.getElementById('formAbrir');
    
    if(formAbrir) {
        formAbrir.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const valor = document.getElementById('valor_abertura').value;
            
            if(!valor || parseFloat(valor) < 0) {
                showAlertModal('alertAbrir', 'Digite um valor válido', 'danger');
                return;
            }
            
            const formData = new FormData();
            formData.append('abertura', valor);
            
            fetch('api/caixa_abrir.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    showAlertModal('alertAbrir', data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlertModal('alertAbrir', data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showAlertModal('alertAbrir', 'Erro ao abrir caixa', 'danger');
            });
        });
    }
    
    // Form de fechamento
    const formFechar = document.getElementById('formFechar');
    
    if(formFechar) {
        formFechar.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const valor = document.getElementById('valor_fechamento').value;
            
            if(!valor || parseFloat(valor) < 0) {
                showAlertModal('alertFechar', 'Digite um valor válido', 'danger');
                return;
            }
            
            if(!confirm('Tem certeza que deseja fechar o caixa?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('fechamento', valor);
            
            fetch('api/caixa_fechar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    showAlertModal('alertFechar', data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlertModal('alertFechar', data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showAlertModal('alertFechar', 'Erro ao fechar caixa', 'danger');
            });
        });
    }
});

// Funções para abrir modais (compatível com Bootstrap 5)
function abrirModalAbrir() {
    if (modalAbrirInstance) {
        modalAbrirInstance.show();
    } else {
        // Fallback para Bootstrap 4 ou sem Bootstrap
        const modal = document.getElementById('modalAbrir');
        if (modal) modal.style.display = 'block';
    }
}

function abrirModalFechar() {
    if (modalFecharInstance) {
        modalFecharInstance.show();
    } else {
        // Fallback para Bootstrap 4 ou sem Bootstrap
        const modal = document.getElementById('modalFechar');
        if (modal) modal.style.display = 'block';
    }
}

// Mostrar alerta no modal
function showAlertModal(id, message, type) {
    const alertDiv = document.getElementById(id);
    if (alertDiv) {
        alertDiv.textContent = message;
        alertDiv.className = 'alert alert-' + type;
        alertDiv.style.display = 'block';
        
        // Ocultar após 5 segundos
        setTimeout(() => {
            alertDiv.style.display = 'none';
        }, 5000);
    }
}

function showTurnoAlert(message, type) {
    const alertDiv = document.getElementById('turnoOperadorAlert');
    if (!alertDiv) {
        alert(message);
        return;
    }

    alertDiv.textContent = message;
    alertDiv.className = 'alert alert-' + type;
    alertDiv.style.display = 'block';

    setTimeout(() => {
        alertDiv.style.display = 'none';
    }, 5000);
}

function operarTurno(acao) {
    fetch('api/turno_operacao.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ acao })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showTurnoAlert(data.message || 'Operação concluída com sucesso.', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showTurnoAlert(data.message || 'Não foi possível concluir a operação do turno.', 'danger');
            }
        })
        .catch(error => {
            console.error('Erro turno:', error);
            showTurnoAlert('Erro ao comunicar com a API de turnos.', 'danger');
        });
}
