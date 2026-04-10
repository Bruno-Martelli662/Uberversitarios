// main.js

document.getElementById("formLogin").addEventListener("submit", function (e) {

  // Limpa mensagens anteriores
  document.getElementById("email-mensagem").textContent = "";
  document.getElementById("password-mensagem").textContent = "";

  const email = document.getElementById("email").value.trim();
  const senha = document.getElementById("password").value.trim();
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  let hasErro = false;

  // Valida email
  if (!email) {
    document.getElementById("email-mensagem").textContent = "Informe o email.";
    hasErro = true;
  } else if (!emailRegex.test(email)) {
    document.getElementById("email-mensagem").textContent = "Email inválido.";
    hasErro = true;
  }

  // Valida senha
  if (!senha) {
    document.getElementById("password-mensagem").textContent = "Informe a senha.";
    hasErro = true;
  } else if (senha.length < 6) {
    document.getElementById("password-mensagem").textContent = "Senha deve ter no mínimo 6 caracteres.";
    hasErro = true;
  }

  // Se tiver erro, bloqueia o envio
  if (hasErro) {
    e.preventDefault();
  }

  // Se não tiver erro, o form faz submit normalmente para o PHP
});