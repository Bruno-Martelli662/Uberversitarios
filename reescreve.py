import base64
import json
from pathlib import Path


def esconder_credenciais_na_imagem(caminho_imagem, config_dict):
    caminho = Path(caminho_imagem)

    if not caminho.exists():
        print(f"❌ Imagem não encontrada: {caminho}")
        return False

    conteudo_png = caminho.read_bytes()

    pos_iend = conteudo_png.find(b"IEND")

    if pos_iend == -1:
        print("❌ Marcador IEND não encontrado. Isso não parece ser um PNG válido.")
        return False

    pos_dados = pos_iend + 8

    json_str = json.dumps(config_dict, ensure_ascii=False, separators=(",", ":"))
    dados_base64 = base64.b64encode(json_str.encode("utf-8"))

    nova_imagem = conteudo_png[:pos_dados] + dados_base64

    backup = caminho.with_suffix(caminho.suffix + ".backup")
    backup.write_bytes(conteudo_png)

    caminho.write_bytes(nova_imagem)

    print(f"✅ Credenciais atualizadas em: {caminho}")
    print(f"📦 Backup criado em: {backup}")
    print(f"🔐 Bytes escondidos: {len(dados_base64)}")

    return True


if __name__ == "__main__":
    credenciais = {
        "SITE_URL": "https://localhost/Uberversitarios",
        "SITE_NAME": "Uberversitarios",

        "DB_HOST": "localhost",
        "DB_NAME": "sistema_autenticacao",

        "DB_AUTH_USER": "uberv_auth",
        "DB_AUTH_PASS": "Auth@2026!segura",

        "DB_READ_USER": "uberv_read",
        "DB_READ_PASS": "Read@2026!segura",

        "DB_WRITE_USER": "uberv_write",
        "DB_WRITE_PASS": "Write@2026!segura",

        "DB_ADMIN_USER": "uberv_admin",
        "DB_ADMIN_PASS": "Admin@2026!segura",

        "SMTP_HOST": "smtp.gmail.com",
        "SMTP_PORT": 587,
        "SMTP_USERNAME": "uberversitarios@gmail.com",
        "SMTP_PASSWORD": "yhvc nuza eovp umvu",
        "SMTP_FROM_EMAIL": "uberversitarios@gmail.com",
        "SMTP_FROM_NAME": "Uberversitarios",
        "SMTP_SECURE": "tls",
        "SMTP_DEBUG": False
    }

    esconder_credenciais_na_imagem("logo_projeto.png", credenciais)
