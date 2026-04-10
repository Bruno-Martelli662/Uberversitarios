const token = localStorage.getItem('authToken');

if (!token) {
    window.location.href = 'login.html';
}

fetch('verificar-sessao.php?token=' + encodeURIComponent(token))
    .then(response => response.json())
    .then(data => {
        if (!data.autenticado) {
            window.location.href = 'login.html';
        }
    })
    .catch(() => {
        window.location.href = 'login.html';
    });

function logout() {
    localStorage.removeItem('authToken');
    window.location.href = 'login.html';
}

// Função para alterar senha quando logado
function alterarSenha() {
    const novaSenha = prompt("Digite sua nova senha:");
    const confirmarSenha = prompt("Confirme sua nova senha:");
    
    if (!novaSenha || !confirmarSenha) {
        alert("Operação cancelada");
        return;
    }
    
    if (novaSenha !== confirmarSenha) {
        alert("As senhas não coincidem!");
        return;
    }
    
    // Validação básica da senha
    if (novaSenha.length < 8 || !/\d/.test(novaSenha) || !/[a-zA-Z]/.test(novaSenha)) {
        alert("A senha deve ter no mínimo 8 caracteres, incluindo letras e números");
        return;
    }
    
    const senhaHash = CryptoJS.SHA256(novaSenha).toString();
    const token = localStorage.getItem('authToken');
    
    fetch('alterar-senha.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + token
        },
        body: JSON.stringify({
            senha: senhaHash
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("Senha alterada com sucesso!");

        } else {
            alert(data.message || "Erro ao alterar senha");
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert("Erro ao conectar com o servidor");
    });
}