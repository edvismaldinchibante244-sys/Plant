/**
 * ============================================
 * JAVASCRIPT - GESTÃO DE USUÁRIOS (Bootstrap 5)
 * ============================================
 */

// Opções padrão para fetch com sessão
var fetchOptions = {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    }
};

document.addEventListener('DOMContentLoaded', function () {
    // Inicializar modais Bootstrap
    var modalUsuarioEl = document.getElementById('modalUsuario');
    var modalSenhaEl = document.getElementById('modalSenha');

    var modalUsuario = modalUsuarioEl ? new bootstrap.Modal(modalUsuarioEl) : null;
    var modalSenha = modalSenhaEl ? new bootstrap.Modal(modalSenhaEl) : null;

    // Form usuário
    var form = document.getElementById('formUsuario');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var usuario_id = document.getElementById('usuario_id').value;
            var url = usuario_id ? 'api/usuario_editar.php' : 'api/usuario_cadastrar.php';
            var formData = new FormData(form);

            fetch(url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        showAlert('alertModal', data.message, 'success');
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        showAlert('alertModal', data.message, 'danger');
                    }
                })
                .catch(function (err) {
                    showAlert('alertModal', 'Erro ao processar: ' + err.message, 'danger');
                });
        });
    }

    // Form senha
    var formSenha = document.getElementById('formSenha');
    if (formSenha) {
        formSenha.addEventListener('submit', function (e) {
            e.preventDefault();
            var nova = document.getElementById('nova_senha').value;
            var confirma = document.getElementById('confirmar_senha').value;

            if (nova !== confirma) {
                showAlert('alertSenha', 'As senhas não coincidem!', 'danger');
                return;
            }
            if (nova.length < 6) {
                showAlert('alertSenha', 'Mínimo 6 caracteres', 'danger');
                return;
            }

            var formData = new FormData(formSenha);
            fetch('api/usuario_senha.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        showAlert('alertSenha', data.message, 'success');
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        showAlert('alertSenha', data.message, 'danger');
                    }
                })
                .catch(function (err) { showAlert('alertSenha', 'Erro ao alterar: ' + err.message, 'danger'); });
        });
    }

    // Filtro por perfil na tabela de usuarios
    var filtroPerfil = document.getElementById('filtroPerfil');
    if (filtroPerfil) {
        filtroPerfil.addEventListener('change', aplicarFiltroPerfil);
    }
});

function aplicarFiltroPerfil() {
    var filtroEl = document.getElementById('filtroPerfil');
    if (!filtroEl) return;

    var perfil = (filtroEl.value || '').toUpperCase();
    var rows = document.querySelectorAll('tr.usuario-row');

    rows.forEach(function (row) {
        var rowPerfil = (row.getAttribute('data-perfil') || '').toUpperCase();
        var visible = !perfil || rowPerfil === perfil;
        row.style.display = visible ? '' : 'none';
    });
}

function abrirModal() {
    document.getElementById('tituloModal').textContent = 'Novo Usuário';
    document.getElementById('formUsuario').reset();
    document.getElementById('usuario_id').value = '';
    document.getElementById('ativo').checked = true;
    document.getElementById('senhaHint').style.display = 'none';
    document.getElementById('senha').required = true;
    document.getElementById('alertModal').style.display = 'none';

    // Reset foto
    document.getElementById('fotoPreview').src = 'https://ui-avatars.com/api/?name=?&background=FF6B35&color=fff&size=100';

    var modalEl = document.getElementById('modalUsuario');
    if (modalEl) {
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

function editarUsuario(id) {
    // Proteção extra: não permitir chamada sem ID válido
    if (!id || isNaN(id) || id <= 0) {
        alert('ID do usuário inválido ou não fornecido. Não é possível editar este usuário.');
        return;
    }
    // ...comentário: fetch e manipulação normal...
    fetch('api/usuario_buscar.php?id=' + id, {
        credentials: 'same-origin'
    })
        .then(function (r) {
            return r.json();
        })
        .then(function (data) {
            if (data.success) {
                var u = data.data;
                document.getElementById('tituloModal').textContent = 'Editar Usuário';
                document.getElementById('usuario_id').value = u.id;
                document.getElementById('nome').value = u.nome;
                document.getElementById('email').value = u.email;
                document.getElementById('perfil').value = u.perfil;
                document.getElementById('ativo').checked = u.ativo == 1;
                document.getElementById('senha').required = false;
                document.getElementById('senhaHint').style.display = 'block';
                document.getElementById('alertModal').style.display = 'none';

                // Mostrar foto do usuário
                if (u.foto && u.foto.trim() !== '') {
                    if (u.foto.startsWith('http') || u.foto.startsWith('/')) {
                        document.getElementById('fotoPreview').src = u.foto;
                    } else {
                        document.getElementById('fotoPreview').src = BASE_URL + '/' + u.foto;
                    }
                } else {
                    var iniciais = u.nome ? u.nome.substring(0, 1).toUpperCase() : '?';
                    document.getElementById('fotoPreview').src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(iniciais) + '&background=FF6B35&color=fff&size=100';
                }

                var modalEl = document.getElementById('modalUsuario');
                if (modalEl) {
                    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                }
            } else {
                alert(data.message || 'Erro ao buscar usuário');
            }
        })
        .catch(function (err) {
            alert('Erro ao buscar usuário: ' + err.message);
        });
}

function deletarUsuario(id) {
    if (!confirm('Inativar este usuário?')) return;
    var formData = new FormData();
    formData.append('id', id);
    fetch('api/usuario_deletar.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            alert(data.message);
            if (data.success) location.reload();
        });
}

function alterarSenha(id) {
    document.getElementById('formSenha').reset();
    document.getElementById('senha_usuario_id').value = id;
    document.getElementById('alertSenha').style.display = 'none';

    var modalEl = document.getElementById('modalSenha');
    if (modalEl) {
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

function showAlert(id, message, type) {
    var alertDiv = document.getElementById(id);
    if (alertDiv) {
        alertDiv.textContent = message;
        alertDiv.className = 'alert alert-' + type;
        alertDiv.style.display = 'block';
        setTimeout(function () { alertDiv.style.display = 'none'; }, 5000);
    }
}
