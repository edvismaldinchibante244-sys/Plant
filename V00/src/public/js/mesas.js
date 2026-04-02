/**
 * ============================================
 * JAVASCRIPT - GESTÃO DE MESAS (Bootstrap 5)
 * ============================================
 */

let modalNovaMesaInstance = null;
let modalReservaInstance = null;
let reservaSubmitBusy = false;
let reservaPodeSubmeter = true;
let reservaDisponibilidadeSeq = 0;
let mesaModalModo = 'mesa';

document.addEventListener('DOMContentLoaded', function () {
    const modalMesaEl = document.getElementById('modalNovaMesa');
    if (modalMesaEl) {
        modalNovaMesaInstance = new bootstrap.Modal(modalMesaEl);
    }

    const modalReservaEl = document.getElementById('modalReservaMesa');
    if (modalReservaEl) {
        modalReservaInstance = new bootstrap.Modal(modalReservaEl);
    }

    const formMesa = document.getElementById('formNovaMesa');
    if (formMesa) {
        formMesa.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(formMesa);

            fetch('api/mesa_cadastrar.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showFeedback(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showFeedback(data.message, 'danger');
                    }
                })
                .catch(() => showFeedback('Erro ao cadastrar mesa.', 'danger'));
        });
    }

    const formReserva = document.getElementById('formReservaMesa');
    if (formReserva) {
        ['reserva_data', 'reserva_hora', 'reserva_quantidade', 'reserva_mesa_id'].forEach((fieldId) => {
            const field = document.getElementById(fieldId);
            if (!field) {
                return;
            }

            field.addEventListener('input', atualizarDisponibilidadeReserva);
            field.addEventListener('change', atualizarDisponibilidadeReserva);
        });

        refreshReservaSubmitButton();
        atualizarDisponibilidadeReserva();

        formReserva.addEventListener('submit', function (e) {
            e.preventDefault();

            const payload = {
                mesa_atribuida: document.getElementById('reserva_mesa_id').value || null,
                nome_cliente: document.getElementById('reserva_nome_cliente').value.trim(),
                telefone_cliente: document.getElementById('reserva_telefone_cliente').value.trim(),
                email_cliente: document.getElementById('reserva_email_cliente').value.trim(),
                data_reserva: document.getElementById('reserva_data').value,
                hora_reserva: document.getElementById('reserva_hora').value,
                quantidade_pessoas: Number(document.getElementById('reserva_quantidade').value || 0),
                observacoes: document.getElementById('reserva_observacoes').value.trim()
            };

            if (!payload.nome_cliente || !payload.data_reserva || !payload.hora_reserva || payload.quantidade_pessoas <= 0) {
                showFeedback('Preencha cliente, data, hora e quantidade de pessoas.', 'warning', 'alertReserva');
                return;
            }

            const capacidadeMaxima = Number(document.getElementById('reserva_quantidade').max || 0);
            if (capacidadeMaxima > 0 && payload.quantidade_pessoas > capacidadeMaxima) {
                showFeedback('A quantidade de pessoas excede a capacidade da mesa selecionada.', 'warning', 'alertReserva');
                return;
            }

            if (!reservaPodeSubmeter) {
                showFeedback('Nao ha disponibilidade para os dados informados.', 'warning', 'alertReserva');
                return;
            }

            setReservaSubmitBusy(true);

            fetch(buildReservaUrl(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': getReservaToken()
                },
                body: JSON.stringify(payload)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (typeof window.sidebarReservasMarcarVisto === 'function' && data.reserva_id) {
                            window.sidebarReservasMarcarVisto(data.reserva_id);
                        }
                        showFeedback(data.message || 'Reserva criada com sucesso.', 'success', 'alertReserva');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showFeedback(data.message || 'Erro ao criar reserva.', 'danger', 'alertReserva');
                    }
                })
                .catch(() => {
                    showFeedback('Erro ao comunicar com a API de reservas.', 'danger', 'alertReserva');
                })
                .finally(() => {
                    setReservaSubmitBusy(false);
                });
        });
    }
});

function atualizarMesa(id, novoStatus) {
    const labels = { LIVRE: 'liberar', OCUPADA: 'ocupar', RESERVADA: 'reservar' };
    if (!confirm('Deseja ' + (labels[novoStatus] || novoStatus) + ' esta mesa?')) return;

    const formData = new FormData();
    formData.append('id', id);
    formData.append('status', novoStatus);

    fetch('api/mesa_atualizar.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showFeedback(data.message, 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showFeedback(data.message, 'danger');
            }
        })
        .catch(() => showFeedback('Erro ao atualizar a mesa.', 'danger'));
}

function abrirModalNovaMesa(tipo = 'mesa') {
    const form = document.getElementById('formNovaMesa');
    if (form) form.reset();
    hideFeedback('alertMesa');

    configurarModalNovaMesa(tipo);

    const tipoField = document.getElementById('mesa_tipo');
    const numeroField = document.getElementById('mesa_numero');
    const capacidadeField = document.getElementById('mesa_capacidade');

    if (tipoField) tipoField.value = mesaModalModo;

    if (mesaModalModo === 'balcao') {
        if (numeroField) {
            numeroField.value = 'Balcão';
            numeroField.readOnly = true;
        }
        if (capacidadeField) {
            capacidadeField.value = 1;
        }
    } else {
        if (numeroField) {
            numeroField.value = '';
            numeroField.readOnly = false;
        }
        if (capacidadeField) {
            capacidadeField.value = 4;
        }
    }

    if (modalNovaMesaInstance) {
        modalNovaMesaInstance.show();
    } else {
        const modal = document.getElementById('modalNovaMesa');
        if (modal) modal.style.display = 'block';
    }
}

function configurarModalNovaMesa(tipo = 'mesa') {
    mesaModalModo = tipo === 'balcao' ? 'balcao' : 'mesa';

    const titulo = document.getElementById('modalNovaMesaTitulo');
    const numeroLabel = document.getElementById('mesa_numero_label');
    const numeroField = document.getElementById('mesa_numero');
    const capacidadeLabel = document.getElementById('mesa_capacidade_label');

    if (mesaModalModo === 'balcao') {
        if (titulo) titulo.innerHTML = '<i class="fas fa-store me-2"></i>Novo Balcão';
        if (numeroLabel) numeroLabel.textContent = 'Nome do Balcão *';
        if (numeroField) numeroField.placeholder = 'Balcão';
        if (capacidadeLabel) capacidadeLabel.textContent = 'Capacidade padrão *';
        return;
    }

    if (titulo) titulo.innerHTML = '<i class="fas fa-plus me-2"></i>Nova Mesa';
    if (numeroLabel) numeroLabel.textContent = 'Número da Mesa *';
    if (numeroField) numeroField.placeholder = 'Ex: 9';
    if (capacidadeLabel) capacidadeLabel.textContent = 'Capacidade (pessoas) *';
}

function abrirModalReserva(mesaId = '', mesaNumero = '', capacidade = 0) {
    const form = document.getElementById('formReservaMesa');
    if (form) form.reset();

    hideFeedback('alertReserva');

    const mesaIdField = document.getElementById('reserva_mesa_id');
    const mesaLabelField = document.getElementById('reserva_mesa_label');
    const qtdField = document.getElementById('reserva_quantidade');
    const dataField = document.getElementById('reserva_data');
    const horaField = document.getElementById('reserva_hora');

    if (mesaIdField) mesaIdField.value = mesaId || '';
    if (mesaLabelField) {
        mesaLabelField.value = mesaNumero
            ? 'Mesa ' + mesaNumero + ' • capacidade ' + (capacidade || '?')
            : 'Atribuição automática pela melhor disponibilidade';
    }

    if (qtdField) {
        qtdField.value = capacidade > 0 ? Math.min(2, capacidade) : 2;
        if (capacidade > 0) {
            qtdField.max = String(capacidade);
        } else {
            qtdField.removeAttribute('max');
        }
    }

    if (dataField && !dataField.value && window.RESERVAS_CONFIG?.hoje) {
        dataField.value = window.RESERVAS_CONFIG.hoje;
    }

    if (horaField && !horaField.value) {
        horaField.value = '19:00';
    }

    setReservaPodeSubmeter(true);
    updateReservaAvailabilityHint('Selecione data, hora e quantidade para ver a disponibilidade.', 'muted');
    refreshReservaSubmitButton();
    setTimeout(atualizarDisponibilidadeReserva, 0);

    if (modalReservaInstance) {
        modalReservaInstance.show();
    } else {
        const modal = document.getElementById('modalReservaMesa');
        if (modal) modal.style.display = 'block';
    }
}

function confirmarReserva(reservaId) {
    if (!confirm('Confirmar esta reserva?')) return;
    executarAcaoReserva(reservaId + '/confirmar', {}, 'Reserva confirmada com sucesso.');
}

function cancelarReserva(reservaId) {
    if (!confirm('Cancelar esta reserva?')) return;
    executarAcaoReserva(String(reservaId), {}, 'Reserva cancelada com sucesso.');
}

function fazerCheckinReserva(reservaId, mesaId) {
    if (!mesaId || Number(mesaId) <= 0) {
        showFloatingNotice('Esta reserva ainda nao possui mesa atribuida.', 'warning');
        return;
    }

    if (!confirm('Realizar check-in desta reserva na mesa atribuida?')) return;
    executarAcaoReserva(reservaId + '/checkin', { mesa_id: Number(mesaId) }, 'Check-in realizado com sucesso.');
}

function executarAcaoReserva(route, payload, successMessage) {
    fetch(buildReservaUrl(route), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': getReservaToken()
        },
        body: JSON.stringify(payload || {})
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showFloatingNotice(data.message || successMessage, 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showFloatingNotice(data.message || 'Nao foi possivel concluir a acao.', 'danger');
            }
        })
        .catch(() => showFloatingNotice('Erro ao comunicar com a API de reservas.', 'danger'));
}

function buildReservaUrl(route = '', query = {}) {
    const endpoint = window.RESERVAS_CONFIG?.endpoint || 'api/reservas.php';
    const params = new URLSearchParams();
    params.set('route', route);

    Object.entries(query || {}).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') {
            return;
        }

        params.set(key, String(value));
    });

    return endpoint + '?' + params.toString();
}

function getReservaToken() {
    return window.RESERVAS_CONFIG?.token || '';
}

function showFeedback(message, type, targetId = 'alertMesa') {
    const alertDiv = document.getElementById(targetId);
    if (!alertDiv) {
        showFloatingNotice(message, type);
        return;
    }

    alertDiv.textContent = message;
    alertDiv.className = 'alert alert-' + type;
    alertDiv.style.display = 'block';
}

function hideFeedback(targetId) {
    const alertDiv = document.getElementById(targetId);
    if (!alertDiv) return;
    alertDiv.style.display = 'none';
    alertDiv.textContent = '';
}

function showFloatingNotice(message, type) {
    let toast = document.getElementById('mesasFloatingNotice');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'mesasFloatingNotice';
        toast.style.position = 'fixed';
        toast.style.top = '24px';
        toast.style.right = '24px';
        toast.style.zIndex = '2000';
        toast.style.minWidth = '260px';
        document.body.appendChild(toast);
    }

    toast.className = 'alert alert-' + type;
    toast.textContent = message;
    toast.style.display = 'block';

    clearTimeout(toast._hideTimer);
    toast._hideTimer = setTimeout(() => {
        toast.style.display = 'none';
    }, 4000);
}

function atualizarDisponibilidadeReserva() {
    const dataField = document.getElementById('reserva_data');
    const horaField = document.getElementById('reserva_hora');
    const qtdField = document.getElementById('reserva_quantidade');
    const mesaField = document.getElementById('reserva_mesa_id');

    if (!dataField || !horaField || !qtdField || !mesaField) {
        return;
    }

    const data = dataField.value;
    const hora = horaField.value;
    const quantidade = Number(qtdField.value || 0);
    const mesaId = Number(mesaField.value || 0);
    const capacidadeMaxima = Number(qtdField.max || 0);

    if (capacidadeMaxima > 0 && quantidade > capacidadeMaxima) {
        setReservaPodeSubmeter(false);
        updateReservaAvailabilityHint('A quantidade de pessoas excede a capacidade da mesa selecionada.', 'warning');
        return;
    }

    if (!data || !hora || quantidade <= 0) {
        setReservaPodeSubmeter(true);
        updateReservaAvailabilityHint('Selecione data, hora e quantidade para ver a disponibilidade.', 'muted');
        return;
    }

    const requestId = ++reservaDisponibilidadeSeq;
    updateReservaAvailabilityHint('A verificar disponibilidade...', 'info');

    fetch(buildReservaUrl('disponibilidade', {
        data,
        hora,
        quantidade
    }), {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (requestId !== reservaDisponibilidadeSeq) {
                return;
            }

            const mesas = Array.isArray(data.mesas_disponiveis) ? data.mesas_disponiveis : [];
            if (!data.success) {
                setReservaPodeSubmeter(true);
                updateReservaAvailabilityHint('Nao foi possivel validar a disponibilidade agora.', 'warning');
                return;
            }

            if (mesas.length === 0) {
                setReservaPodeSubmeter(false);
                updateReservaAvailabilityHint('Nenhuma mesa disponivel para este horario e quantidade.', 'danger');
                return;
            }

            if (mesaId > 0) {
                const mesaSelecionada = mesas.find((mesa) => Number(mesa.id) === mesaId);
                if (!mesaSelecionada) {
                    setReservaPodeSubmeter(false);
                    updateReservaAvailabilityHint('A mesa escolhida nao esta disponivel neste horario. Escolha outra ou use atribuicao automatica.', 'warning');
                    return;
                }

                setReservaPodeSubmeter(true);
                updateReservaAvailabilityHint(
                    'Mesa ' + mesaSelecionada.numero + ' disponivel para ' + quantidade + ' pessoa(s).',
                    'success'
                );
                return;
            }

            const melhorMesa = mesas[0];
            setReservaPodeSubmeter(true);
            updateReservaAvailabilityHint(
                mesas.length + ' mesa(s) disponiveis. Melhor encaixe: Mesa ' + melhorMesa.numero + '.',
                'success'
            );
        })
        .catch(() => {
            if (requestId !== reservaDisponibilidadeSeq) {
                return;
            }

            setReservaPodeSubmeter(true);
            updateReservaAvailabilityHint('Nao foi possivel verificar a disponibilidade agora.', 'warning');
        });
}

function getReservaSubmitButton() {
    return document.getElementById('btnSalvarReserva');
}

function setReservaSubmitBusy(flag) {
    reservaSubmitBusy = !!flag;
    refreshReservaSubmitButton();
}

function setReservaPodeSubmeter(flag) {
    reservaPodeSubmeter = !!flag;
    refreshReservaSubmitButton();
}

function refreshReservaSubmitButton() {
    const button = getReservaSubmitButton();
    if (!button) {
        return;
    }

    button.disabled = reservaSubmitBusy || !reservaPodeSubmeter;
    button.innerHTML = reservaSubmitBusy
        ? '<i class="fas fa-spinner fa-spin me-2"></i>A guardar...'
        : '<i class="fas fa-save me-2"></i>Salvar Reserva';
}

function updateReservaAvailabilityHint(message, tone = 'muted') {
    const hint = document.getElementById('reserva_disponibilidade');
    if (!hint) {
        return;
    }

    const toneClass = {
        success: 'text-success',
        warning: 'text-warning',
        danger: 'text-danger',
        info: 'text-info',
        muted: 'text-muted'
    };

    hint.textContent = message;
    hint.className = 'form-text ' + (toneClass[tone] || 'text-muted');
}
