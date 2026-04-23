import express from "express";
import crypto from "crypto";

const app = express();

// Simulação de banco
const users = [];

// Cadastro
app.post("/register", (req, res) => {
  const { email } = req.body;

  const token = crypto.randomBytes(32).toString("hex");

  users.push({
    email,
    token,
    verified: false
  });

  // Aqui você enviaria o email real
  console.log(`Link: http://localhost:3000/confirm?token=${token}`);

  res.json({ message: "Usuário criado. Verifique o e-mail." });
});

// Confirmação
app.get("/api/confirm-email", (req, res) => {
  const { token } = req.query;

  const user = users.find(u => u.token === token);

  if (!user) {
    return res.json({ success: false });
  }

  user.verified = true;

  res.json({ success: true });
});