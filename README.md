
# **CloudPress: WordPress na AWS com Docker** 🚀

![CloudPress Logo](https://vetores.org/d/compass-uol.svg)

## 📖 Índice

- [Introdução](#introdução)
- [Pré-requisitos](#pré-requisitos)
- [Configuração da Rede AWS](#configuração-da-rede-aws)
- [Grupos de Segurança](#grupos-de-segurança)
- [Banco de Dados MySQL (RDS)](#banco-de-dados-mysql-rds)
- [Armazenamento Compartilhado com Amazon EFS](#armazenamento-compartilhado-com-amazon-efs)
- [Auto Scaling Group](#auto-scaling-group)
- [Load Balancer](#load-balancer)
- [Monitoramento com AWS CloudWatch](#monitoramento-com-aws-cloudwatch)
- [Gerenciamento de Credenciais com AWS Secrets Manager](#gerenciamento-de-credenciais-com-aws-secrets-manager)
- [Verificação das Instâncias](#verificação-das-instâncias)
- [Conclusão](#conclusão)

---

## Introdução

### Objetivo

Implementar o **CloudPress**, uma versão customizada do WordPress, de forma escalável e segura na AWS. Esse projeto utiliza Docker para orquestração de containers, RDS para o banco de dados, EFS para armazenamento compartilhado e serviços nativos da AWS para monitoramento e gerenciamento de segredos.

### Visão Geral da Arquitetura

- **Instâncias EC2:** Rodam os containers Docker com a aplicação CloudPress.  
- **RDS:** Banco de dados MySQL gerenciado que armazena os dados do WordPress.  
- **EFS:** Sistema de arquivos distribuído que guarda os uploads, plugins e temas.  
- **Load Balancer:** Distribui as requisições entre as instâncias para garantir disponibilidade.  
- **Auto Scaling:** Ajusta automaticamente o número de instâncias conforme a demanda.  
- **CloudWatch:** Coleta logs e métricas para monitoramento em tempo real.  
- **Secrets Manager:** Armazena e gerencia as credenciais do banco de dados com segurança.

---

## Pré-requisitos

- Conta AWS ativa e devidamente configurada.  
- Noções básicas de Docker, EC2, RDS, EFS, CloudWatch e Secrets Manager.  
- Par de chaves SSH configurado na AWS.  
- AWS CLI instalada (opcional, mas recomendado para automação).

---

## Configuração da Rede AWS

### 1. Criação da VPC e Sub-redes

- **VPC:**  
  - CIDR: `10.0.0.0/16`  
  - Nome: `CloudPress-VPC`  
  - Essa rede isolada é a base de todo o ambiente.

- **Sub-redes:**  
  - **Privadas:**  
    - `10.0.2.0/24` na zona `us-east-1a`  
    - `10.0.4.0/24` na zona `us-east-1b`  
    - Usadas para instâncias EC2, EFS e RDS, garantindo que recursos críticos não sejam expostos diretamente à internet.
  - **Públicas:**  
    - `10.0.1.0/24` na zona `us-east-1a`  
    - `10.0.3.0/24` na zona `us-east-1b`  
    - Utilizadas para o Load Balancer e NAT Gateway, possibilitando acesso externo controlado.

> **Detalhe:** Se uma zona de disponibilidade tiver problemas, a configuração multi-AZ garante que a aplicação continue operando sem impacto.

### 2. Internet Gateway e NAT Gateway

- **Internet Gateway:**  
  - Anexa à VPC para fornecer acesso direto à internet para as sub-redes públicas.
- **NAT Gateway:**  
  - Colocado em uma sub-rede pública, possibilita que instâncias em sub-redes privadas acessem a internet para atualizações e downloads sem exposição direta.

### 3. Tabelas de Rotas

- **Tabela Pública:**  
  - Associa as sub-redes públicas e define a rota `0.0.0.0/0` para o Internet Gateway.
- **Tabela Privada:**  
  - Associa as sub-redes privadas e define a rota `0.0.0.0/0` para o NAT Gateway.

> **Dica:** Verifique cada associação de rota para evitar falhas na comunicação entre os recursos.

---

## Grupos de Segurança

### SG-CLB (Load Balancer)

- **Regras de Entrada:**  
  - HTTP (porta 80) e HTTPS (porta 443) para `0.0.0.0/0`.
- **Regras de Saída:**  
  - Geralmente permite todo tráfego; ajuste conforme necessário após a criação do SG das instâncias.

### SG-EC2 (Instâncias CloudPress)

- **Regras de Entrada:**  
  - HTTP (porta 80) somente proveniente do SG-CLB.  
  - SSH (porta 22) limitado a IPs confiáveis ou outro SG específico para administração.
- **Regras de Saída:**  
  - Livre para todos os destinos (necessário para atualizações e comunicação com outros serviços).

### SG-RDS (Banco de Dados)

- **Regras de Entrada:**  
  - MySQL (porta 3306) apenas para tráfego vindo do SG-EC2.

### SG-EFS (Armazenamento)

- **Regras de Entrada:**  
  - NFS (porta 2049) somente para instâncias no SG-EC2.
- **Regras de Saída:**  
  - Geralmente livre para permitir o tráfego de dados.

---

## Banco de Dados MySQL (RDS)

### Configuração

- **Identificador:** `cloudpress-db`  
- **Versão do MySQL:** 8.0.x  
- **Classe de Instância:** `db.t3.micro`  
- **Armazenamento:** 20 GB em SSD (gp2)  
- **Backups:** Ativados com retenção de 7 dias  
- **Acesso:** Exclusivamente a partir das sub-redes privadas com o grupo SG-RDS

### Integração com Secrets Manager

- **Objetivo:**  
  - Gerenciar credenciais (usuário e senha) de forma segura.
- **Benefício:**  
  - Reduz a exposição de dados sensíveis no código e facilita a rotação automática das senhas.

> **Nota:** Configure o Secrets Manager para que o RDS possa utilizar as credenciais armazenadas e as instâncias EC2 possam recuperá-las via IAM Role.

---

## Armazenamento Compartilhado com Amazon EFS

### Configuração

- **Nome:** `CloudPress-EFS`  
- **VPC:** `CloudPress-VPC`  
- **Montagem:**  
  - Crie pontos de montagem em cada sub-rede privada para garantir alta disponibilidade.
  - Utilize o grupo SG-EFS para restringir o acesso.

### Procedimento

1. No console AWS, acesse **EFS** e clique em **Criar Sistema de Arquivos**.  
2. Selecione a VPC `CloudPress-VPC` e personalize as configurações (desative backups automáticos se não necessário, defina performance como "General Purpose").  
3. Configure os pontos de montagem nas sub-redes privadas (ex.: `us-east-1a` e `us-east-1b`).

> **Dica:** Garanta que o `/etc/fstab` seja atualizado para montar o EFS automaticamente em caso de reinício da instância.

---

## Auto Scaling Group

### Template de Instância (CloudPressTemplate)

- **AMI:** Amazon Linux Server LTS  
- **Tipo de Instância:** t3.micro  
- **Chave SSH:** aws-key  
- **Grupo de Segurança:** SG-EC2  
- **User Data:** Utilize o script detalhado (mais abaixo) para configurar Docker, montar EFS e iniciar os containers.

### Configuração do ASG

- **Nome:** `CloudPress-ASG`  
- **Capacidade Mínima:** 2 instâncias  
- **Capacidade Máxima:** 4 instâncias  
- **Política de Escala:**  
  - Aumenta instâncias se a utilização de CPU ultrapassar 70% por mais de 5 minutos.
- **Rede:**  
  - VPC: `CloudPress-VPC`  
  - Sub-redes: `private-1a` e `private-1b` (as sub-redes privadas definidas anteriormente)

> **Observação:** Configure os health checks para que o ASG remova instâncias problemáticas automaticamente.

---

## Load Balancer

### Configuração do Classic Load Balancer (CLB)

1. **Criação:**  
   - No Console EC2, vá em **Load Balancers > Criar Load Balancer** e selecione Classic Load Balancer.
2. **Parâmetros Básicos:**  
   - Nome: `CloudPress-CLB`  
   - Esquema: Internet-facing  
   - VPC: `CloudPress-VPC`  
   - Sub-redes: Escolha duas sub-redes públicas (ex.: `10.0.1.0/24` e `10.0.3.0/24`)
3. **Listeners:**  
   - Adicione HTTP (80) e HTTPS (443) com SSL/TLS habilitado.
4. **Health Checks:**  
   - Defina o caminho como `/wp-admin/install.php`, com intervalos de 30 segundos e limite de 2 falhas.
5. **Atributos Avançados:**  
   - Habilite balanceamento entre zonas e configure a drenagem de conexão (timeout de 300 segundos).

### Associação com o ASG

- No ASG `CloudPress-ASG`, associe o CLB `CloudPress-CLB` na seção de balanceamento de carga para distribuir o tráfego entre as instâncias.

---

## Monitoramento com AWS CloudWatch

### Configurações

- **CloudWatch Logs:**  
  - Capture os logs do sistema, do Docker e dos containers (CloudPress, Apache/nginx).
- **CloudWatch Metrics:**  
  - Monitore métricas como uso de CPU, memória, latência e número de erros 5XX.
- **Alarms:**  
  - Crie alarmes que disparem notificações via SNS se a CPU atingir 85% por mais de 5 minutos ou se ocorrer um número elevado de erros.
- **Dashboards:**  
  - Construa dashboards customizados para visualizar a performance geral do ambiente.

> **Dica:** Configure a coleta de logs dos containers e utilize o agente CloudWatch para obter métricas detalhadas.

---

## Gerenciamento de Credenciais com AWS Secrets Manager

### Implementação

- **Armazenamento de Segredos:**  
  - Armazene as credenciais do RDS (usuário e senha) no Secrets Manager.
- **Integração:**  
  - Utilize IAM Roles para permitir que as instâncias EC2 acessem os segredos com segurança.
- **Rotação Automática:**  
  - Configure a rotação automática das credenciais para aumentar a segurança e diminuir o risco de exposições.

> **Benefício:**  
> Com o Secrets Manager, não há necessidade de hardcode das senhas no script ou no código, deixando o ambiente muito mais seguro.

---

## Verificação das Instâncias

### Testando Localmente com Docker

Antes de colocar o script no `userdata.sh` da AWS, teste o ambiente localmente:

1. **Crie uma rede Docker:**
   ```bash
   docker network create cloudpress-network
   ```

2. **Rode um container de MySQL para simulação:**
   ```bash
   docker run -d --name cloudpress-db \
     --network cloudpress-network \
     -e MYSQL_ROOT_PASSWORD=root \
     -e MYSQL_DATABASE=wordpress \
     mysql:8.0
   ```

3. **Inicie o container do CloudPress:**
   ```bash
   docker run -d --name cloudpress-app \
     --network cloudpress-network \
     -e WORDPRESS_DB_HOST=cloudpress-db \
     -e WORDPRESS_DB_USER=root \
     -e WORDPRESS_DB_PASSWORD=root \
     -e WORDPRESS_DB_NAME=wordpress \
     wordpress:latest
   ```

4. **Verifique o acesso:**
   Acesse `http://localhost:8080` no seu navegador para confirmar que a aplicação está rodando corretamente.

### Verificação nas Instâncias EC2

Após a implantação na AWS, utilize o **Endpoint de Conexão** (via EC2 Instance Connect ou similar) para acessar a instância e rodar:
```bash
docker --version
docker-compose --version
```
Confirme se os containers estão ativos:
```bash
docker ps
```

---

## Script de Inicialização (User Data)

Segue o script aprimorado para ser utilizado no campo `userdata.sh`. Ele instala e configura Docker, Docker Compose, monta o EFS e cria um serviço systemd para garantir que os containers subam automaticamente mesmo após um reboot:

```bash
#!/bin/bash
set -e

echo "Atualizando o sistema..."
yum update -y

echo "Instalando Docker..."
yum install -y docker
systemctl start docker
systemctl enable docker
usermod -aG docker ec2-user

echo "Instalando Docker Compose..."
curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose
ln -sf /usr/local/bin/docker-compose /usr/bin/docker-compose

echo "Instalando amazon-efs-utils..."
yum install -y amazon-efs-utils

echo "Criando diretório de montagem do EFS..."
mkdir -p /mnt/efs

echo "Montando o EFS..."
mount -t nfs4 -o nfsvers=4.1,rsize=1048576,wsize=1048576,hard,timeo=600,retrans=2,noresvport fs-XXXXXXXXXXXXXXX.efs.us-east-1.amazonaws.com:/ /mnt/efs
if [ $? -ne 0 ]; then
  echo "Erro ao montar o EFS. Verifique conectividade e permissões."
  exit 1
fi

echo "Adicionando montagem automática no /etc/fstab..."
grep -q "fs-XXXXXXXXXXXXXXX.efs.us-east-1.amazonaws.com:/" /etc/fstab || \
echo "fs-XXXXXXXXXXXXXXX.efs.us-east-1.amazonaws.com:/ /mnt/efs nfs4 defaults,_netdev 0 0" >> /etc/fstab

echo "Configurando Docker para expor métricas..."
cat <<EOF > /etc/docker/daemon.json
{
  "metrics-addr": "0.0.0.0:9323",
  "experimental": false
}
EOF
systemctl restart docker || { echo "Erro ao reiniciar o Docker."; exit 1; }

echo "Aguardando Docker expor métricas na porta 9323..."
until curl -s http://localhost:9323/metrics >/dev/null; do
  echo "Porta 9323 não disponível, aguardando..."
  sleep 2
done
echo "Métricas disponíveis na porta 9323!"

echo "Criando docker-compose.yml no diretório EFS..."
if [ ! -f /mnt/efs/docker-compose.yml ]; then
  cat <<EOF > /mnt/efs/docker-compose.yml
version: '3.8'

services:
  wordpress:
    image: wordpress:latest
    ports:
      - "80:80"
    environment:
      WORDPRESS_DB_HOST: <RDS_ENDPOINT>
      WORDPRESS_DB_USER: <SECRET_USER>  # Valores devem ser obtidos via Secrets Manager
      WORDPRESS_DB_PASSWORD: <SECRET_PASSWORD>
      WORDPRESS_DB_NAME: wordpress
    volumes:
      - /mnt/efs:/var/www/html/wp-content
    restart: always

  portainer:
    image: portainer/portainer-ce
    ports:
      - "9000:9000"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - portainer_data:/data
    restart: always

volumes:
  portainer_data:
EOF
fi

echo "Ajustando permissões do diretório EFS..."
chown -R ec2-user:ec2-user /mnt/efs

echo "Criando serviço systemd para gerenciar o docker-compose..."
if [ ! -f /etc/systemd/system/docker-compose-app.service ]; then
  cat <<EOF > /etc/systemd/system/docker-compose-app.service
[Unit]
Description=Serviço CloudPress com Docker Compose
Requires=docker.service
After=docker.service

[Service]
WorkingDirectory=/mnt/efs
ExecStart=/usr/bin/docker-compose up -d
ExecStop=/usr/bin/docker-compose down
Restart=always
TimeoutStartSec=0

[Install]
WantedBy=multi-user.target
EOF
  systemctl daemon-reload
  systemctl enable docker-compose-app.service
  systemctl start docker-compose-app.service
fi

echo "Script de inicialização concluído com sucesso!"
```

> **Atenção:** Substitua os valores `fs-XXXXXXXXXXXXXXX` e `<RDS_ENDPOINT>`, `<SECRET_USER>`, `<SECRET_PASSWORD>` pelos dados reais do seu ambiente. Os segredos podem ser recuperados dinamicamente via AWS SDK ou CLI se configurado com a IAM Role adequada.

---

## Conclusão

### Resultados Alcançados

- **Escalabilidade:** Ambiente preparado para escalonar automaticamente as instâncias conforme a demanda.  
- **Alta Disponibilidade:** Deploy distribuído em múltiplas zonas de disponibilidade.  
- **Segurança:** Controle de acesso com Grupos de Segurança, integração com Secrets Manager para credenciais e monitoramento constante via CloudWatch.  
- **Monitoramento:** CloudWatch coleta logs, métricas e aciona alarmes em caso de anomalias, permitindo respostas rápidas a incidentes.

### Melhorias Futuras

- **Implementar AWS WAF:** Proteção adicional para o Load Balancer.  
- **Migrar para Application Load Balancer (ALB):** Melhor gerenciamento de tráfego e funcionalidades avançadas.  
- **Automatizar CI/CD:** Utilizar CodePipeline para deploy contínuo das atualizações.  
- **Backup Automático:** Configurar snapshots regulares do RDS e estratégias de backup para o EFS.

