const CRYPTO_CONFIG = {
    aesAlgorithm: 'AES-CBC',
    aesKeyLength: 256,
    rsaAlgorithm: { name: 'RSA-OAEP', hash: 'SHA-1' },
    ivLength: 16
};

let publicKeyCache = null;

function base64ToArrayBuffer(base64) {
    const binaryString = atob(base64);
    const bytes = new Uint8Array(binaryString.length);
    for (let i = 0; i < binaryString.length; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }
    return bytes.buffer;
}

function arrayBufferToBase64(buffer) {
    return btoa(String.fromCharCode(...new Uint8Array(buffer)));
}

function pemToArrayBuffer(pem) {
    const b64 = pem.replace(/-{5}(BEGIN|END) PUBLIC KEY-{5}/g, '').replace(/\s/g, '');
    return base64ToArrayBuffer(b64);
}

async function loadPublicKey() {
    if (publicKeyCache) return publicKeyCache;
    
    try {
        console.log(' Carregando chave pública...');
        const response = await fetch('../public.key');
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const pem = await response.text();
        console.log(' Chave PEM carregada, tamanho:', pem.length);
        
        publicKeyCache = await crypto.subtle.importKey(
            'spki',
            pemToArrayBuffer(pem),
            CRYPTO_CONFIG.rsaAlgorithm,
            true,
            ['encrypt']
        );
        
        console.log(' Chave pública importada com sucesso');
        return publicKeyCache;
    } catch (error) {
        console.error(' Erro ao carregar chave pública:', error);
        throw new Error('Falha na configuração de segurança: ' + error.message);
    }
}

async function generateAESKey() {
    return await crypto.subtle.generateKey(
        {
            name: CRYPTO_CONFIG.aesAlgorithm,
            length: CRYPTO_CONFIG.aesKeyLength
        },
        true,
        ['encrypt', 'decrypt']
    );
}

async function encryptDataAES(data, key, iv) {
    const encoded = new TextEncoder().encode(JSON.stringify(data));
    const encrypted = await crypto.subtle.encrypt(
        { name: CRYPTO_CONFIG.aesAlgorithm, iv },
        key,
        encoded
    );
    return arrayBufferToBase64(encrypted);
}

async function decryptDataAES(encryptedData, key, iv) {
    try {
        const data = base64ToArrayBuffer(encryptedData);
        const decrypted = await crypto.subtle.decrypt(
            { name: CRYPTO_CONFIG.aesAlgorithm, iv },
            key,
            data
        );
        return JSON.parse(new TextDecoder().decode(decrypted));
    } catch (error) {
        console.error(' Erro ao descriptografar AES:', error);
        throw new Error('Falha ao processar resposta segura');
    }
}

async function encryptKeyRSA(key) {
    try {
        console.log(' Iniciando criptografia RSA da chave AES...');
        
        const publicKey = await loadPublicKey();
        const exported = await crypto.subtle.exportKey('raw', key);
        
        console.log(' Tamanho da chave AES a ser criptografada:', exported.byteLength, 'bytes');
        
        const encrypted = await crypto.subtle.encrypt(
            {
                name: "RSA-OAEP",
                hash: "SHA-1"
            },
            publicKey,
            exported
        );
        
        const result = arrayBufferToBase64(encrypted);
        console.log(' Chave RSA criptografada com sucesso, tamanho final:', result.length);
        
        return result;
    } catch (error) {
        console.error(' Erro na criptografia RSA:', error);
        throw new Error('Falha na criptografia RSA: ' + error.message);
    }
}

async function testRSACompatibility() {
    try {
        console.log(' Testando compatibilidade RSA...');
        
        const testKey = await generateAESKey();
        const testKeyRaw = await crypto.subtle.exportKey('raw', testKey);
        console.log(' Chave de teste gerada, tamanho:', testKeyRaw.byteLength);
        
        const encryptedKey = await encryptKeyRSA(testKey);
        console.log(' Chave criptografada, tamanho base64:', encryptedKey.length);
        
        console.log('Teste de criptografia RSA concluído com sucesso');
        return true;
        
    } catch (error) {
        console.error(' Teste de compatibilidade RSA falhou:', error);
        return false;
    }
}

async function sendSecureRequest(url, data, useAuth = false) {
    try {
        console.log(' Iniciando requisição segura para:', url);
        console.log(' Dados a serem enviados (sem senhas):', 
            Object.fromEntries(Object.entries(data).filter(([key]) => !key.toLowerCase().includes('senha')))
        );
        
        const aesKey = await generateAESKey();
        const iv = crypto.getRandomValues(new Uint8Array(CRYPTO_CONFIG.ivLength));
        
        console.log('Chave AES e IV gerados');
        
        console.log(' Criptografando dados com AES...');
        const encryptedData = await encryptDataAES(data, aesKey, iv);
        console.log(' Dados AES criptografados, tamanho:', encryptedData.length);
        
        console.log(' Criptografando chave AES com RSA...');
        const encryptedKey = await encryptKeyRSA(aesKey);
        console.log(' Chave RSA criptografada');
        
        const payload = {
            encryptedKey,
            iv: arrayBufferToBase64(iv),
            encryptedData
        };
        
        console.log(' Payload preparado:');
        console.log('  - encryptedKey length:', payload.encryptedKey.length);
        console.log('  - iv length:', payload.iv.length);
        console.log('  - encryptedData length:', payload.encryptedData.length);
        
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        
        if (useAuth) {
            const authToken = localStorage.getItem('authToken');
            if (authToken) {
                headers['Authorization'] = authToken;
            }
        }
        
        console.log(' Enviando para servidor...');
        const response = await fetch(url, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(payload)
        }); 
        
        console.log(' Resposta recebida, status:', response.status);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error(' Erro HTTP:', response.status, errorText);
            throw new Error(`HTTP error! status: ${response.status} - ${errorText}`);
        }
        
        const responseText = await response.text();
        console.log(' Resposta bruta (primeiros 200 chars):', responseText.substring(0, 200));
        
        if (!responseText.trim()) {
            throw new Error('Resposta vazia do servidor');
        }
        
        let responseData;
        try {
            responseData = JSON.parse(responseText);
        } catch (parseError) {
            console.error(' Erro ao fazer parse do JSON:', parseError);
            console.error(' Conteúdo completo da resposta:', responseText);
            
            if (responseText.toLowerCase().includes('<html') || responseText.toLowerCase().includes('<!doctype')) {
                throw new Error('Servidor retornou página HTML em vez de JSON. Verifique a configuração do servidor.');
            }
            
            if (responseText.includes('Fatal error') || responseText.includes('Parse error') || responseText.includes('Warning:')) {
                throw new Error('Erro PHP no servidor: ' + responseText.substring(0, 200));
            }
            
            throw new Error('Resposta do servidor não é um JSON válido');
        }
        
        console.log(' JSON parseado com sucesso:', responseData);
        
        if (responseData.encryptedKey && responseData.iv && responseData.encryptedData) {
            console.log('🔓 Descriptografando resposta criptografada...');
            try {
                const decryptedResponse = await decryptDataAES(
                    responseData.encryptedData,
                    aesKey,
                    base64ToArrayBuffer(responseData.iv)
                );
                console.log(' Resposta descriptografada:', decryptedResponse);
                return decryptedResponse;
            } catch (decryptError) {
                console.error(' Erro ao descriptografar resposta:', decryptError);
                throw new Error('Falha ao descriptografar resposta do servidor');
            }
        }
        
        return responseData;
        
    } catch (error) {
        console.error(' Erro na requisição segura:', error);
        throw error;
    }
}

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
        element.style.display = 'block';
    }
}

function showError(message) {
    const element = document.getElementById('errorMessage');
    if (element) {
        element.textContent = message;
        element.style.display = 'block';
    }
}

document.getElementById('registerForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    console.log(' Iniciando processo de cadastro...');
    
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
    
    console.log(' Dados do formulário (sem senhas):', {
        nome: formData.nome,
        email: formData.email,
        telefone: formData.telefone,
        termosAceitos: true
    });
    
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
        console.log(' Testando compatibilidade RSA...');
        const compatibilityTest = await testRSACompatibility();
        if (!compatibilityTest) {
            throw new Error('Teste de compatibilidade RSA falhou. Verifique a configuração.');
        }
        
        formData.senha = hashSenha(formData.senha);
        delete formData.confirmarSenha;
        
        formData.termosAceitos = true;
        formData.dataAceiteTermos = new Date().toISOString();
        
        console.log(' Enviando dados criptografados para o servidor...');
        
        const submitButton = e.target.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'Processando...';
        
        try {
            const response = await sendSecureRequest('../api/cadastrar.php', formData);
        
            console.log(' Resposta do servidor:', response);
            
            if (response.success) {
                console.log(' Cadastro realizado com sucesso!');
                alert('Cadastro realizado com sucesso! Verifique seu e-mail para confirmar.');
                
                localStorage.setItem('emailParaConfirmacao', formData.email);
                localStorage.setItem('termosAceitos', 'true');
                localStorage.setItem('dataAceiteTermos', formData.dataAceiteTermos);
                
                setTimeout(() => {
                    window.location.href = 'confirmacao-email.html';
                }, 1000);
            } else {
                alert(response.message || "Erro no cadastro.");
            }
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
        
    } catch (error) {
        console.error(' Erro no cadastro:', error);
        alert("Erro ao conectar com o servidor: " + error.message);
    }
});

document.getElementById('loginForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    console.log(' Iniciando processo de login...');
    
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
    
    if (isSecondStep) {
        formData.codigo2FA = codigo2FA;
    }
    
    if (!formData.email || !formData.senha) {
        alert("Por favor, preencha todos os campos!");
        return;
    }
    
    try {
        formData.senha = hashSenha(formData.senha);
        
        console.log(' Enviando credenciais criptografadas...');
        
        const submitButton = e.target.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'Entrando...';
        
        try {
            const response = await sendSecureRequest('../api/login.php', formData);
            
            console.log(' Resposta do login:', response);
            
            if (response.success) {
                if (response.token) {
                    localStorage.setItem('authToken', response.token);
                }
                
                if (response.requires2FASetup) {
                    console.log(' Redirecionando para configuração do 2FA...');
                    window.location.href = 'ativar-2fa.html';
                } 
                else if (response.requires2FA && !isSecondStep) {
                    console.log(' Solicitando código 2FA...');
                    document.getElementById('2fa-field').style.display = 'block';
                    document.getElementById('password').readOnly = true;
                    document.getElementById('2fa-code').focus();
                }
                else {
                    console.log(' Login realizado com sucesso!');
                    window.location.href = 'index_cadastrado.html';
                }
            } 
            else {
                alert(response.message || "Erro no login");
                if (isSecondStep) {
                    document.getElementById('2fa-code').value = '';
                    document.getElementById('2fa-code').focus();
                }
            }
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
        
    } catch (error) {
        console.error(" Erro no login:", error);
        alert("Erro ao conectar com o servidor: " + error.message);
    }
});

document.getElementById('newPasswordForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    console.log(' Iniciando processo de nova senha...');
    
    const successMsg = document.getElementById('successMessage');
    const errorMsg = document.getElementById('errorMessage');
    if (successMsg) successMsg.style.display = 'none';
    if (errorMsg) errorMsg.style.display = 'none';
    
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
    
    const validacaoSenha = validarSenha(formData.novaSenha);
    if (!validacaoSenha.valido) {
        showError(validacaoSenha.mensagem);
        return;
    }
    
    try {
        formData.novaSenha = hashSenha(formData.novaSenha);
        delete formData.confirmarNovaSenha;
        
        console.log(' Enviando nova senha criptografada...');
        
        const submitButton = e.target.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'Salvando...';
        
        try {
            const response = await sendSecureRequest('../api/nova-senha.php', formData);
            
            console.log(' Resposta do servidor:', response);
            
            if (response.success) {
                showSuccess(response.message || "Senha alterada com sucesso!");
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 3000);
            } else {
                showError(response.message || "Erro ao alterar senha.");
            }
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
        
    } catch (error) {
        console.error(' Erro ao alterar senha:', error);
        showError("Erro ao conectar com o servidor: " + error.message);
    }
});

document.getElementById('recoverForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = {
        email: document.getElementById('email').value.trim()
    };
    
    if (!formData.email) {
        alert("Por favor, insira seu e-mail");
        return;
    }

    if (!formData.email.includes('@') || !formData.email.includes('.')) {
        alert("Por favor, insira um e-mail válido");
        return;
    }
    
    try {
        const response = await sendSecureRequest('../api/recuperar-senha.php', formData);
        
        if (response.success) {
            alert(response.message || "E-mail de recuperação enviado com sucesso!");
        } else {
            alert(response.message || "Erro ao solicitar recuperação.");
        }
    } catch (error) {
        console.error(' Erro:', error);
        alert("Erro ao conectar com o servidor: " + error.message);
    }
});

document.getElementById('resend-link')?.addEventListener('click', async function(e) {
    e.preventDefault();
    
    const email = localStorage.getItem('emailParaConfirmacao') || 
                 prompt("Digite seu e-mail para reenviar a confirmação:");
    
    if (!email) {
        alert("E-mail é necessário para reenviar a confirmação.");
        return;
    }
    
    try {
        const response = await sendSecureRequest('../api/reenviar-confirmacao.php', { email });
        
        if (response.success) {
            alert("E-mail de confirmação reenviado com sucesso!");
        } else {
            alert(response.message || "Erro ao reenviar e-mail.");
        }
    } catch (error) {
        console.error(' Erro:', error);
        alert("Erro ao conectar com o servidor: " + error.message);
    }
});

document.addEventListener("DOMContentLoaded", function() {
    console.log('📱 Inicializando página...');
    
    const qrCodeImg = document.getElementById("qr-code");
    const ativarBtn = document.getElementById("ativar-btn");
    
    if (qrCodeImg && ativarBtn) {
        console.log('📱 Carregando configuração do 2FA...');
        
        sendSecureRequest("../api/gerar-secret.php", {}, true)
            .then(data => {
                console.log('📱 Dados do 2FA recebidos:', data);
                
                if (data.success && data.qrCodeUrl) {
                    qrCodeImg.src = data.qrCodeUrl;
                    qrCodeImg.dataset.secret = data.secret;
                    console.log(' QR Code carregado com sucesso');
                } else {
                    console.error(' Erro nos dados do 2FA:', data);
                    document.getElementById('mensagem').textContent = data.message || "Erro ao gerar QR Code";
                }
            })
            .catch(error => {
                console.error(" Erro ao buscar QR Code:", error);
                document.getElementById('mensagem').textContent = "Erro ao conectar com o servidor: " + error.message;
            });
    }
    
    if (window.location.search.includes('debug=1')) {
        console.log(' Executando teste de compatibilidade (modo debug)...');
        testRSACompatibility().then(result => {
            console.log(' Resultado do teste:', result ? 'Sucesso' : 'Falha');
        });
    }
});

document.getElementById('ativar-btn')?.addEventListener('click', async function() {
    console.log('📱 Ativando 2FA...');
    
    const codigo = document.getElementById('codigo').value;
    const qrCodeImg = document.getElementById('qr-code');
    const secret = qrCodeImg?.dataset?.secret;
    
    if (!codigo || codigo.length !== 6) {
        alert("Por favor, insira um código válido de 6 dígitos");
        return;
    }
    
    if (!secret) {
        alert("Erro: Secret do 2FA não encontrado. Recarregue a página.");
        return;
    }
    
    const formData = {
        codigo: codigo,
        secret: secret
    };
    
    try {
        console.log(' Enviando código 2FA criptografado...');
        
        this.disabled = true;
        this.textContent = 'Ativando...';
        
        try {
            const response = await sendSecureRequest('../api/ativar-2fa.php', formData, true);
            
            console.log(' Resposta da ativação 2FA:', response);
            
            const mensagem = document.getElementById('mensagem');
            if (response.success) {
                mensagem.textContent = "Autenticação em dois fatores ativada com sucesso!";
                mensagem.style.color = "green";
                setTimeout(() => window.location.href = 'index_cadastrado.html', 2000);
            } else {
                mensagem.textContent = response.message || "Erro ao ativar 2FA";
                mensagem.style.color = "red";
            }
        } finally {
            this.disabled = false;
            this.textContent = 'Ativar 2FA';
        }
        
    } catch (error) {
        console.error(' Erro ao ativar 2FA:', error);
        const mensagem = document.getElementById('mensagem');
        mensagem.textContent = "Erro ao conectar com o servidor: " + error.message;
        mensagem.style.color = "red";
        
        this.disabled = false;
        this.textContent = 'Ativar 2FA';
    }
});

console.log('Script.js carregado com criptografia híbrida completa!');