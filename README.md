# CloudPress

**CloudPress** é uma infraestrutura automatizada para implantar o WordPress na AWS utilizando Docker, RDS, EFS e Load Balancer, garantindo alta disponibilidade e escalabilidade.

---

## 📌 Sumário

1. [Tecnologias Utilizadas](#-tecnologias-utilizadas)
2. [Passos para Configuração](#-passos-para-configuração)
   - [Clonar o Repositório](#-clonar-o-repositório)
   - [Configurar Variáveis de Ambiente](#-configurar-variáveis-de-ambiente)
   - [Configurar Infraestrutura AWS](#-configurar-infraestrutura-aws)
   - [Iniciar os Containers](#-iniciar-os-containers)
   - [Configuração HTTPS](#-configuração-https)
3. [Backup e Manutenção](#-backup-e-manutenção)
4. [Licença](#-licença)

---

## 📌 Tecnologias Utilizadas

- **AWS EC2**: Hospedagem da aplicação WordPress.
- **AWS RDS**: Banco de dados MySQL gerenciado.
- **AWS EFS**: Armazenamento compartilhado de arquivos.
- **Docker & Docker Compose**: Containerização e orquestração.
- **Nginx & Certbot**: Proxy reverso e configuração de HTTPS.
- **Portainer**: Interface gráfica para gerenciamento de containers.
- **No-IP**: Serviço de DNS dinâmico.

---

## 🔧 Passos para Configuração

### 1. Clonar o Repositório

Clone o repositório do projeto para seu ambiente local:

```bash
git clone https://github.com/maverick/CloudPress.git
cd CloudPress
```

### 2. Configurar Variáveis de Ambiente

Crie um arquivo `.env` e adicione as variáveis de ambiente para conectar a aplicação ao banco de dados e ao serviço de DNS dinâmico:

```bash
DB_NAME=wordpress
DB_USER=admin
DB_PASSWORD=sua_senha
DB_HOST=seu_endpoint_do_rds
NOIP_USER=seu_usuario_noip
NOIP_PASS=sua_senha_noip
DOMAIN=seudominio.no-ip.com
```

### 3. Configurar Infraestrutura AWS

Para configurar a infraestrutura na AWS, execute os seguintes scripts:

#### **Configurar EFS**

```bash
#!/bin/bash

aws efs create-file-system --creation-token "cloudpress-efs" --performance-mode generalPurpose --region us-east-1
aws efs describe-file-systems --region us-east-1
```

#### **Configurar Load Balancer**

```bash
#!/bin/bash

aws elb create-load-balancer --load-balancer-name cloudpress-lb --listeners "Protocol=HTTP,LoadBalancerPort=80,InstanceProtocol=HTTP,InstancePort=80" --subnets subnet-xyz --security-groups sg-xyz --region us-east-1
aws elb create-lb-listeners --load-balancer-name cloudpress-lb --listeners "Protocol=HTTP,LoadBalancerPort=80,InstanceProtocol=HTTP,InstancePort=80" --region us-east-1
```

#### **Configurar EC2 e Banco de Dados**

```bash
#!/bin/bash

aws ec2 run-instances --image-id ami-xyz --instance-type t2.micro --key-name seu_nome_da_chave --security-group-ids sg-xyz --subnet-id subnet-xyz --region us-east-1
sudo apt update && sudo apt install -y docker.io
sudo systemctl start docker
sudo systemctl enable docker
aws rds create-db-instance --db-name wordpress --db-instance-identifier wordpress-db --allocated-storage 20 --db-instance-class db.t2.micro --engine mysql --master-username admin --master-user-password sua_senha --vpc-security-group-ids sg-xyz --region us-east-1
```

### 4. Iniciar os Containers

Após configurar a infraestrutura, inicialize os containers usando Docker Compose. Crie o arquivo `docker-compose.yml` com a seguinte configuração:

```yaml
version: '3.1'

services:
  db:
    image: mysql:5.7
    restart: always
    environment:
      MYSQL_DATABASE: wordpress
      MYSQL_USER: admin
      MYSQL_PASSWORD: sua_senha
      MYSQL_ROOT_PASSWORD: sua_senha
    volumes:
      - db_data:/var/lib/mysql

  wordpress:
    image: wordpress
    restart: always
    ports:
      - "80:80"
    environment:
      WORDPRESS_DB_HOST: db:3306
      WORDPRESS_DB_USER: admin
      WORDPRESS_DB_PASSWORD: sua_senha
      WORDPRESS_DB_NAME: wordpress
    volumes:
      - wp_data:/var/www/html

volumes:
  db_data:
  wp_data:
    driver: local
    driver_opts:
      type: "nfs"
      o: "addr=fs-xyz.efs.us-east-1.amazonaws.com,rw"
      device: ":/"
```

Execute o comando para iniciar os containers:

```bash
docker-compose up -d
```

### 5. Configuração HTTPS

Para configurar HTTPS, utilize o Certbot com o Nginx para garantir uma conexão segura:

```bash
docker exec -it nginx certbot --nginx -d seudominio.no-ip.com
```

---

## 🛠 Backup e Manutenção

### **Backup do Banco de Dados**

```bash
#!/bin/bash

docker exec -it <mysql_container_id> mysqldump -u admin -p sua_senha wordpress > backup.sql
```

### **Restauração do Banco de Dados**

```bash
#!/bin/bash

docker exec -i <mysql_container_id> mysql -u admin -p sua_senha wordpress < backup.sql
```

---

## 📜 Licença

Este projeto é distribuído sob a licença MIT. Veja o arquivo `LICENSE` para mais detalhes.
```

---

Agora, o README está em formato Markdown, com todos os scripts necessários sem comentários. O diagrama pode ser incluído separadamente como uma imagem, se necessário. Se precisar de mais ajustes, estou à disposição!
