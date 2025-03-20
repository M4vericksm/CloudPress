
# CloudPress: WordPress na AWS com Docker
![CloudPress Compass](https://vetores.org/d/compass-uol.svg)

## Índice

- [1. Visão Geral](#1-visao-geral)
- [2. Requisitos Básicos](#2-requisitos-basicos)
- [3. Configuração da Rede CloudPress](#3-configuracao-da-rede-cloudpress)
- [4. Ajuste dos Grupos de Segurança](#4-ajuste-dos-grupos-de-seguranca)
- [5. Configuração do Banco de Dados MySQL (RDS)](#5-configuracao-do-banco-de-dados-mysql-rds)
- [6. Configuração do Sistema de Arquivos EFS](#6-configuracao-do-sistema-de-arquivos-efs)
- [7. Configuração do Auto Scaling Group](#7-configuracao-do-auto-scaling-group)
- [8. Configuração do Load Balancer](#8-configuracao-do-load-balancer)
- [9. Validação no Host EC2](#9-validacao-no-host-ec2)
- [10. Conclusão](#10-conclusao)

---

## 1. Visão Geral <a id="1-visao-geral"></a>

### 1.1 Objetivo do Projeto

Este tutorial demonstra como implantar uma aplicação **WordPress** utilizando **Docker** em uma infraestrutura AWS, agora denominada **CloudPress**. A solução integra um banco de dados **MySQL** gerenciado pelo **Amazon RDS**, armazenamento escalável via **Amazon EFS** para os arquivos estáticos e um **Load Balancer** que distribui o tráfego entre múltiplas instâncias **EC2**.

O foco é construir uma arquitetura que seja resiliente e escalável, preparada para aumentar a capacidade automaticamente conforme o volume de acessos. Todo o ambiente é orquestrado dentro de uma **VPC CloudPress**, permitindo a criação de sub-redes públicas e privadas para maior segurança e desempenho.

### 1.2 Arquitetura

**Componentes Principais:**

- **Instâncias EC2:** Hospedam os containers Docker executando o WordPress.
- **Amazon RDS (MySQL):** Serviço gerenciado de banco de dados.
- **Amazon EFS:** Sistema de arquivos compartilhado para os dados estáticos.
- **Load Balancer:** Distribui as requisições entre instâncias em diferentes zonas de disponibilidade.
- **Auto Scaling Group:** Ajusta automaticamente o número de instâncias conforme a demanda.

---

## 2. Requisitos Básicos <a id="2-requisitos-basicos"></a>

Antes de iniciar, verifique se você possui:

- Conta ativa na **AWS**.
- Conhecimentos básicos em:
  - **Docker** para containerização.
  - **AWS** (foco em EC2, RDS, EFS e Load Balancer).
  - **WordPress**.
- Acesso ao **AWS Management Console**.
- Par de chaves **SSH** para acesso às instâncias EC2.  
  *Caso ainda não possua, acesse:*  
  **EC2 > Network and Security > Key Pairs > Create New**  
  - Nome sugerido: `CloudPress`
  - Tipo: RSA  
  - Formato: .pem  
  *A chave será automaticamente baixada; utilize a chave criada para acesso posterior.*

---

## 3. Configuração da Rede CloudPress <a id="3-configuracao-da-rede-cloudpress"></a>

Nesta seção, criaremos a infraestrutura de rede que suporta o ambiente CloudPress, configurando VPC, sub-redes, gateways e tabelas de rotas.

### 3.1 Criação da VPC

- Crie uma VPC com o bloco CIDR, por exemplo, `10.0.0.0/16`, permitindo até 65.536 endereços IP privados.
- Nome sugerido: `CloudPress-vpc`

### 3.2 Configuração do Internet Gateway

O Internet Gateway (IGW) conecta a sub-rede pública – onde residirão o Load Balancer e o NAT Gateway – à internet:

1. Dentro da VPC, navegue até **Internet Gateway**.
2. Crie e associe o IGW à VPC `CloudPress-vpc`.

### 3.3 Criação das Sub-redes

#### 3.3.1 Sub-redes Públicas (para o Load Balancer)

1. Crie duas sub-redes públicas, distribuídas entre duas AZs para alta disponibilidade:
   - **AZ 1:** `us-east-1a` com CIDR `10.0.1.0/24`
   - **AZ 2:** `us-east-1b` com CIDR `10.0.3.0/24`
2. Configure a tabela de rotas para direcionar `0.0.0.0/0` ao IGW e associe-a a essas sub-redes.

#### 3.3.2 Sub-redes Privadas (para Instâncias EC2)

1. Crie duas sub-redes privadas em zonas distintas:
   - **Sub-rede Privada 1:** `us-east-1a` com CIDR `10.0.2.0/24`
   - **Sub-rede Privada 2:** `us-east-1b` com CIDR `10.0.4.0/24`
2. Essas sub-redes serão destinadas às instâncias EC2 que executam o WordPress.
3. Para permitir que essas instâncias acessem a internet sem exposição direta, utilize um **NAT Gateway** (veja o próximo item).

### 3.4 Criação dos NAT Gateways

Crie um NAT Gateway em cada sub-rede pública para que as instâncias nas sub-redes privadas possam acessar a internet de forma segura:

- Utilize as sub-redes com CIDRs `10.0.1.0/24` e `10.0.3.0/24`.
- Configure cada NAT com conectividade pública e associe um novo Elastic IP (EIP).

### 3.5 Configuração das Tabelas de Rotas

#### 3.5.1 Tabela para Sub-redes Públicas

- Adicione uma rota para `0.0.0.0/0` direcionada ao IGW.
- Associe essa tabela às duas sub-redes públicas.

#### 3.5.2 Tabela para Sub-redes Privadas

- Configure uma rota para `0.0.0.0/0` que aponte para o NAT Gateway (cada sub-rede privada utilizará o NAT correspondente).
- Associe essa tabela às sub-redes privadas.

### 3.6 Associação de Sub-redes

Após configurar as tabelas, verifique as associações:
- Associe a tabela pública `CP-rtb-public-1a` à sub-rede `CP-public-1a`.
- Associe a tabela pública `CP-rtb-public-1b` à sub-rede `CP-public-1b`.
- Associe a tabela privada `CP-rtb-private-1a` à sub-rede `CP-private-1a`.
- Associe a tabela privada `CP-rtb-private-1b` à sub-rede `CP-private-1b`.

### 3.7 Mapa Resumido da Rede

| Tipo de Sub-rede | AZ          | CIDR          | Finalidade                      | Rota Principal                       |
| ---------------- | ----------- | ------------- | ------------------------------- | ------------------------------------ |
| **Pública**      | us-east-1a  | `10.0.1.0/24` | Load Balancer e NAT Gateway     | `0.0.0.0/0` via IGW                  |
| **Pública**      | us-east-1b  | `10.0.3.0/24` | Load Balancer e NAT Gateway     | `0.0.0.0/0` via IGW                  |
| **Privada**      | us-east-1a  | `10.0.2.0/24` | Instâncias EC2 (WordPress)        | `0.0.0.0/0` via NAT Gateway          |
| **Privada**      | us-east-1b  | `10.0.4.0/24` | Instâncias EC2 (WordPress)        | `0.0.0.0/0` via NAT Gateway          |

---

## 4. Ajuste dos Grupos de Segurança <a id="4-ajuste-dos-grupos-de-seguranca"></a>

### 4.1 Grupo de Segurança para o Load Balancer

- **Nome:** `CloudPress-CLB-SG`
- **Descrição:** Grupo de segurança para o Load Balancer.

**Regras de Entrada:**

- **HTTP (Porta 80):**
  - Protocolo: TCP  
  - Origem: `0.0.0.0/0` (ajustável conforme necessário)
- **HTTPS (Porta 443):**
  - Protocolo: TCP  
  - Origem: `0.0.0.0/0`

**Regras de Saída:**

- Permitir tráfego destinado às instâncias EC2 nas portas 80 e 443 (essas regras serão refinadas posteriormente).

### 4.2 Grupo de Segurança para as Instâncias EC2

- **Nome:** `CloudPress-EC2-SG`
- **Descrição:** Grupo de segurança para as instâncias que operam o WordPress.

**Regras de Entrada:**

- **HTTP (Porta 80):**
  - Permitir tráfego oriundo do grupo do Load Balancer (`CloudPress-CLB-SG`).
- **SSH (Porta 22):**
  - Permitir conexões originadas do próprio grupo (`CloudPress-EC2-SG`).  
    *Observação: Esta regra deve ser adicionada após a criação do grupo, via edição das regras de entrada.*

**Regras de Saída:**

- Liberar todo o tráfego para acesso à internet.

### 4.3 Grupo de Segurança para RDS e EFS

- **Nome:** `CloudPress-RDS&EFS-SG`
- **Descrição:** Protege o acesso ao banco de dados RDS e ao sistema de arquivos EFS.

**Regras de Entrada:**

- **MySQL (Porta 3306):**
  - Permitir conexões do grupo de segurança das instâncias EC2.
- **NFS (Porta 2049):**
  - Permitir conexões do mesmo grupo.

**Regras de Saída:**

- Normalmente, não é necessário configurar saídas para esses serviços, mas é prudente permitir NFS na porta 2049 para comunicação.

---

## 5. Configuração do Banco de Dados MySQL (RDS) <a id="5-configuracao-do-banco-de-dados-mysql-rds"></a>

### 5.1 Criação da Instância RDS MySQL

#### 5.1.1 Parâmetros Iniciais

- Método: **Standard Create**
- Versão: **MySQL 8.0.40**
- Template: **Free Tier**
- Identificador: `cloudpress-db`  
  *(Atualize o endpoint conforme a conexão gerada)*

#### 5.1.2 Configurações de Credenciais

- Usuário master: `[usuario_cloud]`
- Gerenciamento de credenciais: Self managed
- Senha master: `[senha_cloud]`

#### 5.1.3 Outras Configurações

- Tipo de instância: **db.t3.micro** (classe burstable)
- Armazenamento: 5 GiB – gp2 (SSD)  
  *Desative o auto scaling do storage.*
- Conectividade: Sem ligação direta com instâncias EC2
- VPC: `CloudPress-vpc`
- Grupo de sub-redes: Criar um novo
- Acesso público: **NÃO** (para segurança)
- Grupo de Segurança: `CloudPress-RDS&EFS-SG`
- Zona: Sem preferência

#### 5.1.4 Configurações Adicionais

- Nome do banco inicial: `wordpress`
- Backup, manutenção e proteção contra exclusão: Desabilitados

### 5.2 Importante: Anote o Endpoint do RDS

Esse endpoint será usado para configurar as credenciais no arquivo **docker-compose**. Aguarde alguns minutos para que a AWS finalize a inicialização.

---

## 6. Configuração do Sistema de Arquivos EFS <a id="6-configuracao-do-sistema-de-arquivos-efs"></a>

### 6.1 Criação do EFS

1. No console da AWS, acesse o serviço **EFS**.
2. Clique em **Criar sistema de arquivos**.
3. Selecione a VPC `CloudPress-vpc`.
4. Nome sugerido: `CloudPress-efs`
5. Clique em **Customize**.

### 6.2 Ajustes de Configuração

#### 6.2.1 Parâmetros do Sistema

- Nome: `CloudPress-efs`
- Tipo: Regional
- Gerenciamento de ciclo de vida: Configure conforme a necessidade (sem gerenciamento automático, se preferir)
- Criptografia: Desabilitada
- Modo de throughput: Bursting
- Performance: General Purpose

#### 6.2.2 Configurações de Rede

- VPC: `CloudPress-vpc`
- Mount Targets:
  - **us-east-1a:** Sub-rede privada com IP exemplo `10.0.2.18` associada ao grupo `CloudPress-RDS&EFS-SG`
  - **us-east-1b:** Sub-rede privada com IP exemplo `10.0.4.36` associado ao mesmo grupo  
    *(Os IPs podem ser alterados, desde que estejam na mesma faixa das sub-redes)*

#### 6.2.3 Outras Configurações

- Utilize as políticas padrão e prossiga para a revisão.

#### 6.2.4 Revisão

> **Atenção:** Registre os IPs configurados para o EFS.  
> *(Caminho: EFS > File Systems > Selecione o EFS criado > Network)*

---

## 7. Configuração do Auto Scaling Group <a id="7-configuracao-do-auto-scaling-group"></a>

Crie um Auto Scaling Group para gerenciar dinamicamente as instâncias CloudPress.

### 7.1 Criação do Template de Lançamento

1. Nome sugerido: `CloudPress-template`
2. Descrição: "Servidores web para o WordPress"
3. Imagem: **Amazon Linux 2023 AMI**
4. Tipo: **t2.micro**
5. Selecione a chave SSH apropriada.
6. **Não defina sub-redes** no template (isso será configurado no ASG).
7. Grupo de Segurança: `CloudPress-EC2-SG`
8. Armazenamento: Utilize o padrão da imagem.
9. Adicione as tags necessárias.

#### Advanced (User Data):

Edite o script de inicialização substituindo os seguintes placeholders:

- `[endpoint_rds]` – conforme obtido na seção 5.2.
- `[usuario_cloud]` – conforme definido em 5.1.2.
- `[senha_cloud]` – conforme definido em 5.1.2.
- IPs do EFS: `[ip_efs1]` e `[ip_efs2]` – conforme registrados na seção 6.2.2.

```bash
#!/bin/bash

# Atualiza o sistema e instala o Docker e utilitários NFS
yum update -y
yum install docker -y

# Inicia e habilita o Docker
systemctl start docker
systemctl enable docker

# Adiciona o usuário ec2-user ao grupo docker
usermod -a -G docker ec2-user

# Instala o Docker Compose
curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose

# Obtém a zona de disponibilidade (AZ) da instância
TOKEN=$(curl -X PUT "http://169.254.169.254/latest/api/token" -H "X-aws-ec2-metadata-token-ttl-seconds: 21600")
AZ=$(curl -H "X-aws-ec2-metadata-token: $TOKEN" -s http://169.254.169.254/latest/meta-data/placement/availability-zone)

# Define o IP do EFS conforme a AZ
case $AZ in
  "us-east-1a") EFS_IP="[ip_efs1]" ;;
  "us-east-1b") EFS_IP="[ip_efs2]" ;;
  *) echo "AZ desconhecida"; exit 1 ;;
esac

# Cria e monta o diretório do EFS
mkdir -p /mnt/efs
mount -t nfs4 -o nfsvers=4.1,rsize=1048576,wsize=1048576,hard,timeo=600,retrans=2 $EFS_IP:/ /mnt/efs
mkdir -p /mnt/efs/wordpress_data

# Cria o arquivo docker-compose.yml
cat <<EOF > /home/ec2-user/docker-compose.yml
services:
  wordpress:
    image: wordpress:latest
    ports:
      - "80:80"
    environment:
      WORDPRESS_DB_HOST: [endpoint_rds]  # Ex: db-1.tim123.region.rds.amazonaws.com
      WORDPRESS_DB_USER: [usuario_cloud]   # Ex: admin
      WORDPRESS_DB_PASSWORD: [senha_cloud] # Ex: P0t4t0FR1es@987
      WORDPRESS_DB_NAME: wordpress
    volumes:
      - wordpress_data:/var/www/html

volumes:
  wordpress_data:
    driver_opts:
      type: "nfs"
      o: "addr=\$EFS_IP,nfsvers=4.1,rsize=1048576,wsize=1048576,hard,timeo=600,retrans=2"
      device: ":/"
EOF

# Ajusta as permissões do arquivo e do volume
chown ec2-user:ec2-user /home/ec2-user/docker-compose.yml
chown ec2-user:ec2-user /mnt/efs/wordpress_data

# Executa o Docker Compose para iniciar o serviço
sudo -u ec2-user /usr/local/bin/docker-compose -f /home/ec2-user/docker-compose.yml up -d
```

Após salvar o template, retorne ao Auto Scaling Group e associe este template.

### 7.2 Configuração do Auto Scaling Group

- Selecione a VPC: `CloudPress-vpc`
- Escolha as sub-redes privadas: `CloudPress-private-1a` e `CloudPress-private-1b`
- Distribuição entre AZs: "Balanced best effort"

### 7.3 Ajuste de Capacidade e Escalabilidade

- Capacidade desejada: **2 instâncias**
- Mínimo: **2 instâncias**
- Máximo: **3 instâncias**
- Política de escalonamento:
  - Nome: `CloudPress-CPU-scaling-policy`
  - Baseada na utilização média da CPU (meta de 70%)
  - Tempo de aquecimento: 300 segundos
- Política de manutenção: "Terminate and Launch"

Finalize as configurações e crie o grupo.

### 7.4 Monitoramento

Utilize o console da AWS para verificar se as instâncias estão sendo lançadas conforme esperado e acompanhe a utilização da CPU.

---

## 8. Configuração do Load Balancer <a id="8-configuracao-do-load-balancer"></a>

### 8.1 Criação do Load Balancer Clássico

1. No console de EC2, acesse **Load Balancers** e clique em **Create Load Balancer**.
2. Selecione **Classic Load Balancer (previous generation)**.
3. Configure os seguintes parâmetros:
   - Nome sugerido: `CloudPress-clb`
   - Esquema: `Internet-facing`
   - VPC: `CloudPress-vpc`
   - Sub-redes: Selecione as duas sub-redes públicas
   - Grupo de Segurança: `CloudPress-CLB-SG`
4. Configure os listeners para HTTP na porta 80.
5. Configure o health check com o caminho `/wp-admin/install.php`, com intervalo de 30 segundos e 2 tentativas.
6. Deixe que as instâncias sejam registradas automaticamente pelo Auto Scaling Group.
7. Ative recursos adicionais:
   - Balanceamento cross-zone.
   - Connection draining (timeout de 300 segundos).

### 8.2 Associação do Load Balancer ao Auto Scaling Group

- No console de EC2, acesse **Auto Scaling Groups**.
- Selecione `CloudPress-asg` e clique em **Actions > Edit**.
- Na seção de Load Balancing, escolha **Classic Load Balancers** e selecione `CloudPress-clb`.
- Salve as alterações.

### 8.3 Teste de Funcionamento

- Acesse o DNS fornecido pelo Load Balancer para confirmar se o WordPress está operante.
- Crie uma conta no WordPress e compartilhe o link para validação.
- Verifique no console do Load Balancer se as instâncias aparecem como “2 of 2 instances in service”.

---

### 9. Validação no Host EC2

**9.1 Criação do Endpoint para Acesso às Instâncias**  
No serviço VPC, vá para **PrivateLink and Lattice > Endpoints > Create Endpoint** e configure:  
- **Nome:** CloudPress-EC2-AcessoEndpoint  
- **Tipo:** EC2 Instance Connect Endpoint  
- **VPC:** CloudPress-vpc  
- **Grupo de Segurança:** CloudPress-EC2-SG  
- **Sub-rede:** Selecione uma sub-rede privada (por exemplo, CloudPress-private-1a)  

> **Nota:** Mesmo que o endpoint seja criado em uma AZ específica, ele pode acessar instâncias em outras AZs, desde que as configurações de rede e segurança estejam corretas.

**9.2 Conectando-se via Endpoint**  
No console de EC2, selecione a instância desejada, clique em **Actions > Connect**, escolha o método **Connect using EC2 Instance Connect Endpoint**, utilize o usuário `ec2-user` e configure o túnel para durar até 3600 segundos.

**9.3 Comandos de Verificação**  
Após conectar, execute os comandos abaixo para confirmar a instalação e configuração:
  
```bash
docker --version
docker-compose --version
sudo cat /home/ec2-user/docker-compose.yml
docker ps        # Lista os containers em execução
docker ps -a     # Lista todos os containers
ls -lha /mnt/efs/wordpress_data/  # Verifica o conteúdo do EFS
```

---

### 9.4 Sugestões de Métricas do ASG e do CloudWatch

Para garantir que a infraestrutura CloudPress esteja operando com eficiência, é fundamental monitorar métricas importantes utilizando o CloudWatch. Abaixo, algumas recomendações:

- **Métricas do Auto Scaling Group (ASG):**
  - **GroupDesiredCapacity:** Número desejado de instâncias configurado no ASG.
  - **GroupInServiceInstances:** Quantidade de instâncias atualmente em serviço.
  - **GroupPendingInstances:** Instâncias em processo de inicialização.
  - **GroupTerminatingInstances:** Instâncias que estão sendo finalizadas.
  - **Scaling Activities:** Eventos de escalonamento (adição ou remoção de instâncias), que ajudam a identificar picos de demanda e atividades anormais.

- **Métricas do CloudWatch para as Instâncias EC2:**
  - **CPUUtilization:** Percentual de uso da CPU de cada instância.
  - **NetworkIn / NetworkOut:** Quantidade de dados que entram e saem das instâncias.
  - **DiskReadOps / DiskWriteOps:** Operações de leitura e escrita no disco.
  - **StatusCheckFailed:** Falhas nos testes de status que podem indicar problemas de saúde da instância.
  
- **Dashboard e Alarmes:**
  - **Criação de Dashboards:** Utilize o CloudWatch Dashboards para visualizar todas as métricas em um único painel.
  - **Configuração de Alarmes:** Configure alarmes para alertar em caso de anomalias, como aumento súbito de CPU, queda no número de instâncias em serviço ou falhas repetidas nas verificações de status.

Essas métricas e práticas de monitoramento ajudam na identificação proativa de problemas e garantem que o ambiente CloudPress mantenha alta disponibilidade e desempenho.

---

### 10. Conclusão

#### 10.1 Resumo do Projeto  
Este guia ilustra a criação de uma infraestrutura robusta para hospedar o WordPress utilizando Docker na AWS sob a marca CloudPress. Com a integração do RDS (MySQL), do EFS para armazenamento compartilhado e do Load Balancer, a solução garante alta disponibilidade e escalabilidade. O Auto Scaling Group possibilita a adaptação automática conforme o aumento do tráfego, otimizando recursos e custos.

#### 10.2 Considerações Finais  
A adoção de métricas monitoradas via CloudWatch, juntamente com os relatórios do ASG, assegura uma visão clara do desempenho e da saúde do ambiente. Essas práticas permitem ajustes rápidos e eficientes, contribuindo para a confiabilidade e a resiliência da aplicação.
