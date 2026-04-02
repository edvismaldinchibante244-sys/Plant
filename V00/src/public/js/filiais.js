(function () {
    const config = window.FILIAIS_CONFIG || {};
    const csrfToken = config.csrfToken || '';
    const createForm = document.getElementById('formNovaFilial');
    const editForm = document.getElementById('formEditarFilial');
    const createAlert = document.getElementById('alertFilialCriacao');
    const editAlert = document.getElementById('alertFilialEdicao');
    const createBtn = document.getElementById('btnCriarFilial');
    const editBtn = document.getElementById('btnSalvarFilial');
    const createModalEl = document.getElementById('modalAdicionarFilial');
    const editModalEl = document.getElementById('modalEditarFilial');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;

    const setAlert = (element, message, type) => {
        if (!element) return;
        element.className = 'alert alert-' + type + ' mx-3 mt-3 mb-0';
        element.textContent = message;
        element.classList.remove('d-none');
    };

    const clearAlert = (element) => {
        if (!element) return;
        element.className = 'alert d-none mx-3 mt-3 mb-0';
        element.textContent = '';
    };

    const setBusy = (button, isBusy, busyHtml, idleHtml) => {
        if (!button) return;
        button.disabled = isBusy;
        button.innerHTML = isBusy ? busyHtml : idleHtml;
    };

    const postForm = async (url, formElement) => {
        const body = new URLSearchParams(new FormData(formElement));
        body.set('_csrf', csrfToken);

        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            body
        });

        let data = null;
        try {
            data = await response.json();
        } catch (error) {
            throw new Error('Resposta invalida do servidor.');
        }

        if (!response.ok && (!data || !data.message)) {
            throw new Error('Nao foi possivel concluir a operacao.');
        }

        return data;
    };

    if (createForm) {
        createForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearAlert(createAlert);
            setBusy(
                createBtn,
                true,
                '<i class="fas fa-spinner fa-spin me-1"></i>Criando...',
                '<i class="fas fa-plus me-1"></i> Criar Filial'
            );

            try {
                const data = await postForm(config.createUrl, createForm);
                if (!data.success) {
                    setAlert(createAlert, data.message || 'Nao foi possivel criar a filial.', 'danger');
                    return;
                }

                window.location.href = 'filiais.php?secao=inicio&tipo=success&msg=' + encodeURIComponent(data.message || 'Filial criada com sucesso.');
            } catch (error) {
                setAlert(createAlert, error.message || 'Erro ao criar filial.', 'danger');
            } finally {
                setBusy(
                    createBtn,
                    false,
                    '<i class="fas fa-spinner fa-spin me-1"></i>Criando...',
                    '<i class="fas fa-plus me-1"></i> Criar Filial'
                );
            }
        });
    }

    if (editForm) {
        editForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearAlert(editAlert);
            setBusy(
                editBtn,
                true,
                '<i class="fas fa-spinner fa-spin me-1"></i>Salvando...',
                '<i class="fas fa-save me-1"></i> Salvar Alteracoes'
            );

            try {
                const data = await postForm(config.updateUrl, editForm);
                if (!data.success) {
                    setAlert(editAlert, data.message || 'Nao foi possivel atualizar a filial.', 'danger');
                    return;
                }

                window.location.href = 'filiais.php?secao=inicio&tipo=success&msg=' + encodeURIComponent(data.message || 'Filial atualizada com sucesso.');
            } catch (error) {
                setAlert(editAlert, error.message || 'Erro ao atualizar filial.', 'danger');
            } finally {
                setBusy(
                    editBtn,
                    false,
                    '<i class="fas fa-spinner fa-spin me-1"></i>Salvando...',
                    '<i class="fas fa-save me-1"></i> Salvar Alteracoes'
                );
            }
        });
    }

    document.querySelectorAll('[data-edit-filial]').forEach((button) => {
        button.addEventListener('click', () => {
            clearAlert(editAlert);
            document.getElementById('edit_filial_id').value = button.dataset.filialId || '';
            document.getElementById('edit_nome_filial').value = button.dataset.filialNome || '';
            document.getElementById('edit_email_filial').value = button.dataset.filialEmail || '';
            document.getElementById('edit_telefone_filial').value = button.dataset.filialTelefone || '';
            document.getElementById('edit_endereco_filial').value = button.dataset.filialEndereco || '';
            document.getElementById('edit_cidade_filial').value = button.dataset.filialCidade || '';
            document.getElementById('edit_status_filial').value = button.dataset.filialStatus || 'ATIVO';

            if (editModal) {
                editModal.show();
            }
        });
    });

    if (createModalEl && createForm) {
        createModalEl.addEventListener('hidden.bs.modal', () => {
            clearAlert(createAlert);
            createForm.reset();
        });
    }

    if (editModalEl && editForm) {
        editModalEl.addEventListener('hidden.bs.modal', () => {
            clearAlert(editAlert);
            editForm.reset();
        });
    }
})();
