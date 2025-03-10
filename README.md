# CloudPress

CloudPress é uma infraestrutura automatizada para implantar o WordPress na AWS utilizando Docker, RDS, EFS e Load Balancer, garantindo alta disponibilidade e escalabilidade.

## 📌 Sumário

1. [Tecnologias Utilizadas](#-tecnologias-utilizadas)
2. [Configuração e Instalação](#-configuração-e-instalação)
   - [Clonar o Repositório](#1-clonar-o-repositório)
   - [Configurar Variáveis de Ambiente](#2-configurar-variáveis-de-ambiente)
   - [Configurar a Infraestrutura](#3-configurar-a-infraestrutura)
   - [Iniciar os Containers](#4-iniciar-os-containers)
   - [Configurar HTTPS com Certbot](#5-configurar-https-com-certbot)
3. [Estrutura do Projeto](#-estrutura-do-projeto)
4. [Manutenção e Backup](#-manutenção-e-backup)
5. [Diagrama da Infraestrutura](#-diagrama-da-infraestrutura)
6. [Licença](#-licença)

## 📌 Tecnologias Utilizadas

- **AWS EC2**: Hospedagem da aplicação WordPress.
- **AWS RDS (MySQL)**: Banco de dados gerenciado.
- **AWS EFS**: Armazenamento compartilhado para persistência de dados.
- **Docker & Docker Compose**: Containerização da aplicação.
- **Nginx & Certbot**: Proxy reverso e HTTPS com Let's Encrypt.
- **Portainer**: Gerenciamento de containers via interface gráfica.
- **No-IP**: DNS dinâmico para mapeamento do domínio.

## 🔧 Configuração e Instalação

### **1. Clonar o Repositório**
```sh
git clone https://github.com/maverick/CloudPress.git
cd CloudPress
```

### **2. Configurar Variáveis de Ambiente**
Crie um arquivo `.env` e defina as variáveis necessárias:
```sh
DB_NAME=wordpress
DB_USER=admin
DB_PASSWORD=senha_segura
DB_HOST=rds-endpoint.amazonaws.com
NOIP_USER=seu_usuario
NOIP_PASS=sua_senha
DOMAIN=seudominio.no-ip.com
```

### **3. Configurar a Infraestrutura**
Execute os scripts de configuração:
```sh
bash infra/setup-efs.sh  # Configura o EFS
bash infra/setup-lb.sh   # Configura o Load Balancer
bash infra/user_data.sh  # Inicializa a EC2
```

### **4. Iniciar os Containers**
```sh
docker-compose up -d
```

### **5. Configurar HTTPS com Certbot**
```sh
docker exec -it nginx certbot --nginx -d seudominio.no-ip.com
```

## 📂 Estrutura do Projeto
```
CloudPress/
├── README.md
├── db/
│   └── init.sql
├── docker-compose.yml
├── docs/
│   ├── CONFIG.md
│   └── INSTALL.md
├── infra/
│   ├── setup-efs.sh
│   ├── setup-lb.sh
│   └── user_data.sh
├── nginx/
│   ├── certbot/
│   │   ├── conf/
│   │   ├── logs/
│   │   └── www/
│   └── conf.d/
│       └── default.conf
├── portainer/
│   └── docker-compose.yml
├── scripts/
│   ├── backup.sh
│   └── restore.sh
└── wordpress/
    ├── Dockerfile
    └── wp-config.php
```

## 🛠 Manutenção e Backup

### **Backup do Banco de Dados**
```sh
bash scripts/backup.sh
```

### **Restauração do Banco de Dados**
```sh
bash scripts/restore.sh
```

## 📊 Diagrama da Infraestrutura
Para melhor visualização da arquitetura do projeto, utilize o [Draw.io](https://app.diagrams.net/) e importe o arquivo `docs/diagrama.xml`, onde está o modelo da infraestrutura implementada.

## 📜 Licença
Este projeto é distribuído sob a licença MIT. Veja `LICENSE` para mais detalhes.
