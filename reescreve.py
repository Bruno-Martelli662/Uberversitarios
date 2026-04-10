import base64
import json
from pathlib import Path

def esconder_credenciais_na_imagem(caminho_imagem, config_dict):
    """
    Esconde as credenciais de volta no logo_projeto.png depois do IEND
    Modo 4chan: simples, sujo e eficaz
    """
    
    caminho = Path(caminho_imagem)
    
    if not caminho.exists():
        print("❌ Imagem não encontrada, seu animal")
        return False
    
    # Lê a imagem original
    with open(caminho, 'rb') as f:
        conteudo_png = f.read()
    
    # Encontra o fim do PNG real (IEND chunk)
    pos_iend = conteudo_png.find(b'IEND')
    if pos_iend == -1:
        print("❌ Isso nem é um PNG válido, wtf")
        return False
    
    # +8 porque IEND + CRC (4 bytes)
    pos_dados = pos_iend + 8
    
    # Converte o dict pra json -> base64
    json_str = json.dumps(config_dict, ensure_ascii=False, separators=(',', ':'))
    dados_base64 = base64.b64encode(json_str.encode('utf-8')).decode('utf-8')
    
    # Cria a nova imagem: PNG original + base64 dos dados
    nova_imagem = conteudo_png[:pos_dados] + dados_base64.encode('utf-8')
    
    # Salva por cima (ou com nome diferente se quiser ser safe)
    with open(caminho, 'wb') as f:
        f.write(nova_imagem)
    
    print(f"✅ Credenciais escondidas com sucesso no {caminho.name}")
    print(f"   Tamanho adicionado: {len(dados_base64)} bytes")
    print("   Agora seu config.php vai ler tudo bonitinho de novo")
    
    return True


# ==================== USE ASSIM ====================

if __name__ == "__main__":
    # <<< COLOQUE SUAS CREDENCIAIS AQUI >>>
    minhas_credenciais = {
        "SITE_URL": "https://localhost/Uberversitarios",
        "SITE_NAME": "Uberversitarios",
        
        "DB_HOST": "localhost",
        "DB_USER": "Uberversitarios",
        "DB_PASS": "Pucpr@1234",
        "DB_NAME": "sistema_autenticacao",
        
        "SMTP_HOST": "smtp.gmail.com",
        "SMTP_PORT": 587,
        "SMTP_USERNAME": "uberversitarios@gmail.com",
        "SMTP_PASSWORD": "yhvc nuza eovp umvu",
        "SMTP_FROM_EMAIL": "uberversitarios@gmail.com",
        "SMTP_FROM_NAME": "Uberversitarios",
        "SMTP_SECURE": "tls",
        "SMTP_DEBUG": False
    }
    
    esconder_credenciais_na_imagem("logo_projeto.png", minhas_credenciais)
    
    print("\n>be me")
    print(">hiding creds in png like a 2012 /g/ autist")
    print(">still more secure than most corporate devs")
