// ==================== SCRIPT.JS LIMPO ====================
// Cadastro, login, recuperação de senha e 2FA
// =========================================================

// ==================== FUNÇÕES AUXILIARES ====================

function validarSenha(senha) {
    if (senha.length < 8) {
        return { valido: false, mensagem: "A senha deve ter no mínimo 8 caracteres." };
    }

    if (!/\d/.test(senha)) {
        return { valido: false, mensagem: "A senha deve conter pelo menos 1 número." };
    }

    if (!/[a-zA-Z]/.test(senha)) {
        return { valido: false, mensagem: "A senha deve conter pelo menos 1 letra." };
    }

    if (!/[!@#$%^&*(),.?":{}|<>]/.test(senha)) {
        return { valido: false, mensagem: "A senha deve conter pelo menos 1 caractere especial." };
    }

    return { valido: true, mensagem: "Senha válida." };
}

function hashSenha(senha) {
    return CryptoJS.SHA256(senha).toString();
}

function showSuccess(message) {
    const element = document.getElementById('successMessage');

    if (element) {
        element.textContent = message;
        element.style.display = message ? 'block' : 'none';
    }
}

function showError(message) {
    const element = document.getElementById('errorMessage');

    if (element) {
        element.textContent = message;
        element.style.display = message ? 'block' : 'none';
    }
}

async function sendJsonRequest(url, data = {}, useAuth = false) {
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    };

    if (useAuth) {
        const token = localStorage.getItem('authToken');

        if (token) {
            headers['Authorization'] = token;
        }
    }

    const response = await fetch(url, {
        method: 'POST',
        headers,
        body: JSON.stringify(data)
    });

    const text = await response.text();

    try {
        return JSON.parse(text);
    } catch (error) {
        throw new Error("Resposta inválida do servidor: " + text.substring(0, 200));
    }
}

function setButtonLoading(button, loadingText) {
    if (!button) return null;

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = loadingText;

    return function resetButton() {
        button.disabled = false;
        button.textContent = originalText;
    };
}

// ==================== CADASTRO ====================

document.getElementById('registerForm')?.addEventListener('submit', async function (e) {
    e.preventDefault();

    const termsCheckbox = document.getElementById('termsCheckbox');

    if (!termsCheckbox || !termsCheckbox.checked) {
        alert("Você precisa aceitar os Termos e Serviços para continuar com o cadastro!");
        termsCheckbox?.focus();
        return;
    }

    const formData = {
        nome: document.getElementById('name').value.trim(),
        email: document.getElementById('email').value.trim(),
        telefone: document.getElementById('tel').value.trim(),
        senha: document.getElementById('password').value,
        confirmarSenha: document.getElementById('confirm-password').value
    };

    if (formData.nome.length < 2) {
        alert("Nome deve ter pelo menos 2 caracteres!");
        return;
    }

    if (!formData.email.includes('@') || !formData.email.includes('.')) {
        alert("Por favor, insira um e-mail válido!");
        return;
    }

    if (formData.telefone.replace(/\D/g, '').length < 10) {
        alert("Por favor, insira um telefone válido!");
        return;
    }

    if (formData.senha !== formData.confirmarSenha) {
        alert("As senhas não coincidem!");
        return;
    }

    const validacaoSenha = validarSenha(formData.senha);

    if (!validacaoSenha.valido) {
        alert(validacaoSenha.mensagem);
        return;
    }

    const submitButton = e.target.querySelector('button[type="submit"]');
    const resetButton = setButtonLoading(submitButton, 'Processando...');

    try {
        formData.senha = hashSenha(formData.senha);
        delete formData.confirmarSenha;

        formData.termosAceitos = true;
        formData.dataAceiteTermos = new Date().toISOString();

        const response = await sendJsonRequest('../api/cadastrar.php', formData);

        if (response.success) {
            alert('Cadastro realizado com sucesso! Verifique seu e-mail para confirmar.');

            localStorage.setItem('emailParaConfirmacao', formData.email);
            localStorage.setItem('termosAceitos', 'true');
            localStorage.setItem('dataAceiteTermos', formData.dataAceiteTermos);

            window.location.href = 'confirmacao-email.html';
        } else {
            alert(response.message || "Erro no cadastro.");
        }
    } catch (error) {
        alert("Erro ao conectar com o servidor: " + error.message);
    } finally {
        resetButton?.();
    }
});

// ==================== LOGIN ====================

document.getElementById('loginForm')?.addEventListener('submit', async function (e) {
    e.preventDefault();

    const isSecondStep = document.getElementById('2fa-field')?.style.display === 'block';
    const codigo2FA = document.getElementById('2fa-code')?.value.trim();

    const formData = {
        email: document.getElementById('email').value.trim(),
        senha: document.getElementById('password').value
    };

    if (!formData.email || !formData.senha) {
        alert("Por favor, preencha todos os campos!");
        return;
    }

    if (isSecondStep) {
        if (!codigo2FA || codigo2FA.length !== 6) {
            alert("Por favor, insira o código de 6 dígitos do seu autenticador");
            return;
        }

        formData.codigo2FA = codigo2FA;
    }

    const submitButton = e.target.querySelector('button[type="submit"]');
    const resetButton = setButtonLoading(submitButton, 'Entrando...');

    try {
        formData.senha = hashSenha(formData.senha);

        const response = await sendJsonRequest('../api/login.php', formData);

        if (response.success) {
            if (response.token) {
                localStorage.setItem('authToken', response.token);
            }

            if (response.requires2FASetup) {
                window.location.href = 'ativar-2fa.html';
                return;
            }

            if (response.requires2FA && !isSecondStep) {
                document.getElementById('2fa-field').style.display = 'block';
                document.getElementById('password').readOnly = true;
                document.getElementById('2fa-code').focus();
                return;
            }

            window.location.href = 'index_cadastrado.html';
        } else {
            alert(response.message || "Erro no login");

            if (isSecondStep) {
                document.getElementById('2fa-code').value = '';
            }
        }
    } catch (error) {
        alert("Erro ao conectar com o servidor: " + error.message);
    } finally {
        resetButton?.();
    }
});

// ==================== RECUPERAÇÃO DE SENHA ====================

document.getElementById('recoverForm')?.addEventListener('submit', async function (e) {
    e.preventDefault();

    const email = document.getElementById('email').value.trim();

    if (!email) {
        alert("Por favor, insira seu e-mail");
        return;
    }

    if (!email.includes('@') || !email.includes('.')) {
        alert("Por favor, insira um e-mail válido");
        return;
    }

    const submitButton = e.target.querySelector('button[type="submit"]');
    const resetButton = setButtonLoading(submitButton, 'Enviando...');

    try {
        const response = await sendJsonRequest('../api/recuperar-senha.php', { email });

        alert(response.success ? response.message : response.message || "Erro ao solicitar recuperação.");
    } catch (error) {
        alert("Erro ao conectar com o servidor: " + error.message);
    } finally {
        resetButton?.();
    }
});

// ==================== NOVA SENHA ====================

document.getElementById('newPasswordForm')?.addEventListener('submit', async function (e) {
    e.preventDefault();

    showSuccess('');
    showError('');

    const urlParams = new URLSearchParams(window.location.search);

    const formData = {
        token: urlParams.get('token'),
        novaSenha: document.getElementById('new-password').value,
        confirmarNovaSenha: document.getElementById('confirm-new-password').value
    };

    if (!formData.token) {
        showError("Token de recuperação inválido ou expirado.");
        return;
    }

    if (formData.novaSenha !== formData.confirmarNovaSenha) {
        showError("As senhas não coincidem!");
        return;
    }

    const validacao = validarSenha(formData.novaSenha);

    if (!validacao.valido) {
        showError(validacao.mensagem);
        return;
    }

    const submitButton = e.target.querySelector('button[type="submit"]');
    const resetButton = setButtonLoading(submitButton, 'Salvando...');

    try {
        formData.novaSenha = hashSenha(formData.novaSenha);
        delete formData.confirmarNovaSenha;

        const response = await sendJsonRequest('../api/nova-senha.php', formData);

        if (response.success) {
            showSuccess("Senha alterada com sucesso! Redirecionando...");

            setTimeout(() => {
                window.location.href = 'login.html';
            }, 3000);
        } else {
            showError(response.message || "Erro ao alterar senha.");
        }
    } catch (error) {
        showError("Erro ao conectar com o servidor: " + error.message);
    } finally {
        resetButton?.();
    }
});

// ==================== REENVIAR CONFIRMAÇÃO ====================

document.getElementById('resend-link')?.addEventListener('click', async function (e) {
    e.preventDefault();

    const email = localStorage.getItem('emailParaConfirmacao') || prompt("Digite seu e-mail para reenviar a confirmação:");

    if (!email) return;

    try {
        const response = await sendJsonRequest('../api/reenviar-confirmacao.php', { email });

        alert(response.success ? "E-mail reenviado!" : response.message || "Erro ao reenviar.");
    } catch (error) {
        alert("Erro ao conectar: " + error.message);
    }
});

// ==================== INICIALIZAÇÃO 2FA ====================

document.addEventListener("DOMContentLoaded", function () {
    const qrCodeImg = document.getElementById("qr-code");
    const ativarBtn = document.getElementById("ativar-btn");
    const mensagem = document.getElementById('mensagem');

    if (!qrCodeImg || !ativarBtn) return;

    sendJsonRequest("../api/gerar-secret.php", {}, true)
        .then(data => {
            if (data.success && data.qrCodeUrl) {
                qrCodeImg.src = data.qrCodeUrl;
                qrCodeImg.dataset.secret = data.secret;
            } else if (mensagem) {
                mensagem.textContent = data.message || "Erro ao gerar QR Code";
            }
        })
        .catch(error => {
            if (mensagem) {
                mensagem.textContent = "Erro ao conectar: " + error.message;
            }
        });
});

// ==================== ATIVAR 2FA ====================

document.getElementById('ativar-btn')?.addEventListener('click', async function () {
    const codigo = document.getElementById('codigo').value.trim();
    const secret = document.getElementById('qr-code')?.dataset?.secret;
    const mensagem = document.getElementById('mensagem');

    if (!codigo || codigo.length !== 6) {
        alert("Código de 6 dígitos é obrigatório");
        return;
    }

    if (!secret) {
        alert("Secret não encontrado. Recarregue a página.");
        return;
    }

    const resetButton = setButtonLoading(this, 'Ativando...');

    try {
        const response = await sendJsonRequest('../api/ativar-2fa.php', { codigo, secret }, true);

        if (response.success) {
            if (mensagem) {
                mensagem.textContent = "2FA ativado com sucesso!";
                mensagem.style.color = "green";
            }

            setTimeout(() => {
                window.location.href = 'index_cadastrado.html';
            }, 2000);
        } else if (mensagem) {
            mensagem.textContent = response.message || "Erro ao ativar 2FA";
            mensagem.style.color = "red";
        }
    } catch (error) {
        if (mensagem) {
            mensagem.textContent = "Erro: " + error.message;
            mensagem.style.color = "red";
        }
    } finally {
        resetButton?.();
    }
});

console.log('script.js limpo carregado com sucesso!');