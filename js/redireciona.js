const token = localStorage.getItem('authToken');
if (token) {
    fetch('../api/verificar-sessao.php?token=' + encodeURIComponent(token))
        .then(res => res.json())
        .then(data => {
            if (data.autenticado) {
                window.location.href = '../html/index_cadastrado.html';
            }
        })
        .catch(() => {});
}