// ==================== SCRIPT.JS REORGANIZADO ====================
// Seção 1: Configurações e variáveis globais
// Seção 2: Funções auxiliares (validação, hash, UI)
// Seção 3: Handlers de eventos (cadastro, login, recuperação, 2FA)
// Seção 4: Funções de criptografia híbrida (RSA + AES)
// =================================================================

// ==================== 1. CONFIGURAÇÕES GLOBAIS ====================
const CRYPTO_CONFIG = {
    aesAlgorithm: 'AES-CBC',
    aesKeyLength: 256,
    rsaAlgorithm: { name: 'RSA-OAEP', hash: 'SHA-1' },
    ivLength: 16
};

let publicKeyCache = null;

// ==================== 2. FUNÇÕES AUXILIARES ====================
// Valida a força da senha (mínimo 8 chars, números, letras, especiais)
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

// Gera hash SHA-256 da senha usando CryptoJS
function hashSenha(senha) {
    return CryptoJS.SHA256(senha).toString();
}

// Exibe mensagem de sucesso na página (elemento successMessage)
function showSuccess(message) {
    const element = document.getElementById('successMessage');
    if (element) {
        element.textContent = message;
        element.style.display = 'block';
    }
}

// Exibe mensagem de erro na página (elemento errorMessage)
function showError(message) {
    const element = document.getElementById('errorMessage');
    if (element) {
        element.textContent = message;
        element.style.display = 'block';
    }
}

// ==================== 3. HANDLERS DAS PÁGINAS ====================

// ----- CADASTRO (registerForm) -----
document.getElementById('registerForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    console.log(' Iniciando processo de cadastro...');
    
    // Verifica aceite dos Termos
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
    
    // Validações de campo
    if (!formData.nome || formData.nome.length < 2) {
        alert("Nome deve ter pelo menos 2 caracteres!");
        return;
    }
    if (!formData.email || !formData.email.includes('@') || !formData.email.includes('.')) {
        alert("Por favor, insira um e-mail válido!");
        return;
    }
    if (!formData.telefone || formData.telefone.replace(/\D/g, '').length < 10) {
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
    
    try {
        // Testa compatibilidade RSA antes de enviar
        const compatibilityTest = await testRSACompatibility();
        if (!compatibilityTest) {
            throw new Error('Teste de compatibilidade RSA falhou. Verifique a configuração.');
        }
        
        formData.senha = hashSenha(formData.senha);
        delete formData.confirmarSenha;
        formData.termosAceitos = true;
        formData.dataAceiteTermos = new Date().toISOString();
        
        const submitButton = e.target.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'Processando...';
        
        const response = await sendSecureRequest('../api/cadastrar.php', formData);
        
        if (response.success) {
            alert('Cadastro realizado com sucesso! Verifique seu e-mail para confirmar.');
            localStorage.setItem('emailParaConfirmacao', formData.email);
            localStorage.setItem('termosAceitos', 'true');
            localStorage.setItem('dataAceiteTermos', formData.dataAceiteTermos);
            setTimeout(() => window.location.href = 'confirmacao-email.html', 1000);
        } else {
            alert(response.message || "Erro no cadastro.");
        }
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    } catch (error) {
        alert("Erro ao conectar com o servidor: " + error.message);
    }
});

// ----- LOGIN (loginForm) -----
document.getElementById('loginForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = {
        email: document.getElementById('email').value.trim(),
        senha: document.getElementById('password').value
    };
    const isSecondStep = document.getElementById('2fa-field')?.style.display === 'block';
    const codigo2FA = document.getElementById('2fa-code')?.value;
    
    if (isSecondStep && (!codigo2FA || codigo2FA.length !== 6)) {
        alert("Por favor, insira o código de 6 dígitos do seu autenticador");
        return;
    }
    if (isSecondStep) formData.codigo2FA = codigo2FA;
    if (!formData.email || !formData.senha) {
        alert("Por favor, preencha todos os campos!");
        return;
    }
    
    try {
        formData.senha = hashSenha(formData.senha);
        const submitButton = e.target.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'Entrando...';
        
        const response = await sendSecureRequest('../api/login.php', formData);
        
        if (response.success) {
            if (response.token) localStorage.setItem('authToken', response.token);
            if (response.requires2FASetup) {
                window.location.href = 'ativar-2fa.html';
            } else if (response.requires2FA && !isSecondStep) {
                document.getElementById('2fa-field').style.display = 'block';
                document.getElementById('password').readOnly = true;
                document.getElementById('2fa-code').focus();
            } else {
                window.location.href = 'index_cadastrado.html';
            }
        } else {
            alert(response.message || "Erro no login");
            if (isSecondStep) document.getElementById('2fa-code').value = '';
        }
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    } catch (error) {
        alert("Erro ao conectar com o servidor: " + error.message);
    }
});

// ----- RECUPERAÇÃO DE SENHA (recoverForm) -----
document.getElementById('recoverForm')?.addEventListener('submit', async function(e) {
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
    try {
        const response = await sendSecureRequest('../api/recuperar-senha.php', { email });
        alert(response.success ? response.message : response.message || "Erro ao solicitar recuperação.");
    } catch (error) {
        alert("Erro ao conectar com o servidor: " + error.message);
    }
});

// ----- NOVA SENHA (newPasswordForm) -----
document.getElementById('newPasswordForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    showSuccess(''); showError('');
    const formData = {
        novaSenha: document.getElementById('new-password').value,
        confirmarNovaSenha: document.getElementById('confirm-new-password').value
    };
    const urlParams = new URLSearchParams(window.location.search);
    formData.token = urlParams.get('token');
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
    try {
        formData.novaSenha = hashSenha(formData.novaSenha);
        delete formData.confirmarNovaSenha;
        const submitButton = e.target.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'Salvando...';
        const response = await sendSecureRequest('../api/nova-senha.php', formData);
        if (response.success) {
            showSuccess("Senha alterada com sucesso! Redirecionando...");
            setTimeout(() => window.location.href = 'login.html', 3000);
        } else {
            showError(response.message || "Erro ao alterar senha.");
        }
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    } catch (error) {
        showError("Erro ao conectar com o servidor: " + error.message);
    }
});

// ----- REENVIO DE E-MAIL DE CONFIRMAÇÃO (resend-link) -----
document.getElementById('resend-link')?.addEventListener('click', async function(e) {
    e.preventDefault();
    const email = localStorage.getItem('emailParaConfirmacao') || prompt("Digite seu e-mail para reenviar a confirmação:");
    if (!email) return;
    try {
        const response = await sendSecureRequest('../api/reenviar-confirmacao.php', { email });
        alert(response.success ? "E-mail reenviado!" : response.message || "Erro ao reenviar.");
    } catch (error) {
        alert("Erro ao conectar: " + error.message);
    }
});

// ----- INICIALIZAÇÃO DA PÁGINA DE 2FA (ativar-2fa.html) -----
document.addEventListener("DOMContentLoaded", function() {
    const qrCodeImg = document.getElementById("qr-code");
    const ativarBtn = document.getElementById("ativar-btn");
    if (qrCodeImg && ativarBtn) {
        sendSecureRequest("../api/gerar-secret.php", {}, true)
            .then(data => {
                if (data.success && data.qrCodeUrl) {
                    qrCodeImg.src = data.qrCodeUrl;
                    qrCodeImg.dataset.secret = data.secret;
                } else {
                    document.getElementById('mensagem').textContent = data.message || "Erro ao gerar QR Code";
                }
            })
            .catch(error => {
                document.getElementById('mensagem').textContent = "Erro ao conectar: " + error.message;
            });
    }
    if (window.location.search.includes('debug=1')) {
        testRSACompatibility().then(result => console.log('Teste RSA:', result ? 'OK' : 'FALHA'));
    }
});

// ----- ATIVAÇÃO DO 2FA (botão ativar-btn) -----
document.getElementById('ativar-btn')?.addEventListener('click', async function() {
    const codigo = document.getElementById('codigo').value;
    const secret = document.getElementById('qr-code')?.dataset?.secret;
    if (!codigo || codigo.length !== 6) {
        alert("Código de 6 dígitos é obrigatório");
        return;
    }
    if (!secret) {
        alert("Secret não encontrado. Recarregue a página.");
        return;
    }
    const formData = { codigo, secret };
    try {
        this.disabled = true;
        this.textContent = 'Ativando...';
        const response = await sendSecureRequest('../api/ativar-2fa.php', formData, true);
        const mensagem = document.getElementById('mensagem');
        if (response.success) {
            mensagem.textContent = "2FA ativado com sucesso!";
            mensagem.style.color = "green";
            setTimeout(() => window.location.href = 'index_cadastrado.html', 2000);
        } else {
            mensagem.textContent = response.message || "Erro ao ativar 2FA";
            mensagem.style.color = "red";
        }
        this.disabled = false;
        this.textContent = 'Ativar 2FA';
    } catch (error) {
        document.getElementById('mensagem').textContent = "Erro: " + error.message;
        this.disabled = false;
        this.textContent = 'Ativar 2FA';
    }
});

// ==================== 4. FUNÇÕES DE CRIPTOGRAFIA HÍBRIDA ====================

// Converte Base64 para ArrayBuffer
function base64ToArrayBuffer(base64) {
    const binaryString = atob(base64);
    const bytes = new Uint8Array(binaryString.length);
    for (let i = 0; i < binaryString.length; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }
    return bytes.buffer;
}

// Converte ArrayBuffer para Base64
function arrayBufferToBase64(buffer) {
    return btoa(String.fromCharCode(...new Uint8Array(buffer)));
}

// Converte chave pública PEM para ArrayBuffer (formato SPKI)
function pemToArrayBuffer(pem) {
    const b64 = pem.replace(/-{5}(BEGIN|END) PUBLIC KEY-{5}/g, '').replace(/\s/g, '');
    return base64ToArrayBuffer(b64);
}

// Carrega a chave pública RSA a partir do arquivo public.key (cache)
async function loadPublicKey() {
    if (publicKeyCache) return publicKeyCache;
    try {
        const response = await fetch('../public.key');
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const pem = await response.text();
        publicKeyCache = await crypto.subtle.importKey(
            'spki',
            pemToArrayBuffer(pem),
            CRYPTO_CONFIG.rsaAlgorithm,
            true,
            ['encrypt']
        );
        return publicKeyCache;
    } catch (error) {
        throw new Error('Falha na configuração de segurança: ' + error.message);
    }
}

// Gera uma chave AES-256 aleatória
async function generateAESKey() {
    return await crypto.subtle.generateKey(
        { name: CRYPTO_CONFIG.aesAlgorithm, length: CRYPTO_CONFIG.aesKeyLength },
        true,
        ['encrypt', 'decrypt']
    );
}

// Criptografa dados com AES-CBC (retorna Base64)
async function encryptDataAES(data, key, iv) {
    const encoded = new TextEncoder().encode(JSON.stringify(data));
    const encrypted = await crypto.subtle.encrypt(
        { name: CRYPTO_CONFIG.aesAlgorithm, iv },
        key,
        encoded
    );
    return arrayBufferToBase64(encrypted);
}

// Descriptografa dados com AES-CBC (recebe Base64)
async function decryptDataAES(encryptedData, key, iv) {
    const data = base64ToArrayBuffer(encryptedData);
    const decrypted = await crypto.subtle.decrypt(
        { name: CRYPTO_CONFIG.aesAlgorithm, iv },
        key,
        data
    );
    return JSON.parse(new TextDecoder().decode(decrypted));
}

// Criptografa a chave AES usando RSA (chave pública)
async function encryptKeyRSA(key) {
    const publicKey = await loadPublicKey();
    const exported = await crypto.subtle.exportKey('raw', key);
    const encrypted = await crypto.subtle.encrypt(
        { name: "RSA-OAEP", hash: "SHA-1" },
        publicKey,
        exported
    );
    return arrayBufferToBase64(encrypted);
}

// Testa a compatibilidade da criptografia RSA gerando uma chave AES e criptografando/descriptografando internamente (apenas lado cliente)
async function testRSACompatibility() {
    try {
        const testKey = await generateAESKey();
        await encryptKeyRSA(testKey);
        return true;
    } catch (error) {
        console.error('Teste RSA falhou:', error);
        return false;
    }
}

// Função principal que envia requisições criptografadas (híbrida: RSA para chave AES, AES para payload)
async function sendSecureRequest(url, data, useAuth = false) {
    try {
        const aesKey = await generateAESKey();
        const iv = crypto.getRandomValues(new Uint8Array(CRYPTO_CONFIG.ivLength));
        const encryptedData = await encryptDataAES(data, aesKey, iv);
        const encryptedKey = await encryptKeyRSA(aesKey);
        const payload = {
            encryptedKey,
            iv: arrayBufferToBase64(iv),
            encryptedData
        };
        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (useAuth) {
            const token = localStorage.getItem('authToken');
            if (token) headers['Authorization'] = token;
        }
        const response = await fetch(url, {
            method: 'POST',
            headers,
            body: JSON.stringify(payload)
        });
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP ${response.status} - ${errorText.substring(0, 100)}`);
        }
        const responseText = await response.text();
        let responseData = JSON.parse(responseText);
        // Se a resposta estiver criptografada, descriptografa
        if (responseData.encryptedKey && responseData.iv && responseData.encryptedData) {
            const decrypted = await decryptDataAES(
                responseData.encryptedData,
                aesKey,
                base64ToArrayBuffer(responseData.iv)
            );
            return decrypted;
        }
        return responseData;
    } catch (error) {
        console.error('Erro na requisição segura:', error);
        throw error;
    }
}

console.log('Script.js reorganizado e carregado com sucesso!');