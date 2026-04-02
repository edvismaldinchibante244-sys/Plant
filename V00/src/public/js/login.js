/**
 * ============================================
 * JAVASCRIPT DO LOGIN (Bootstrap 5)
 * ============================================
 */

document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('formLogin');
    const alertDiv = document.getElementById('loginAlert');

    if (!loginForm) {
        console.error('Formulário de login não encontrado!');
        return;
    }

    loginForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const email = document.querySelector('input[name="email"]').value;
        const senha = document.querySelector('input[name="senha"]').value;

        if (!email || !senha) {
            showAlert('Preencha todos os campos!', 'danger');
            return;
        }

        const formData = new FormData();
        formData.append('email', email);
        formData.append('senha', senha);

        fetch('api/login_simples.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1000);
                    } else {
                        showAlert(data.message, 'danger');
                    }
                } catch (e) {
                    showAlert('Não foi possível processar o login. Tente novamente.', 'danger');
                }
            })
            .catch(error => {
                showAlert('Não foi possível processar o login. Tente novamente.', 'danger');
            });
    });

    function showAlert(message, type) {
        alertDiv.textContent = message;
        alertDiv.className = 'alert alert-' + type;
        alertDiv.style.display = 'block';
        setTimeout(() => {
            alertDiv.style.display = 'none';
        }, 5000);
    }
});
