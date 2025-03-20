**CloudPress: WordPress Escalável na AWS com Docker**  
![CloudPress Compass](https://vetores.org/d/compass-uol.svg)  

---

## Índice
- [1. Visão Geral](#1-visao-geral)  
- [2. Requisitos Básicos](#2-requisitos-basicos)  
- [3. Configuração da Rede](#3-configuracao-da-rede)  
- [4. Grupos de Segurança](#4-grupos-de-seguranca)  
- [5. Banco de Dados (RDS)](#5-banco-de-dados-rds)  
- [6. Sistema de Arquivos (EFS)](#6-sistema-de-arquivos-efs)  
- [7. Auto Scaling Group](#7-auto-scaling-group)  
- [8. Load Balancer](#8-load-balancer)  
- [9. Validação](#9-validacao)  
- [10. Implementação Avançada](#10-implementacao-avancada)  
- [11. Conclusão](#11-conclusao)  

---

## 1. Visão Geral <a id="1-visao-geral"></a>  
**Objetivo**: Implantar WordPress resiliente e escalável usando:  
- **Docker** para containerização  
- **RDS** para banco de dados MySQL  
- **EFS** para armazenamento compartilhado  
- **Auto Scaling** para balanceamento de carga  

---

## 2. Requisitos Básicos <a id="2-requisitos-basicos"></a>  
- Conta AWS com permissões EC2, RDS, EFS  
- Chave SSH para acesso às instâncias  
- Conhecimento em:  
  - Docker  
  - AWS   

---

## 3. Configuração da Rede <a id="3-configuracao-da-rede"></a>  
**VPC**: `10.0.0.0/16` com:  
- **Sub-redes Públicas**:  
  - `10.0.1.0/24` (us-east-1a)  
  - `10.0.3.0/24` (us-east-1b)  
- **Sub-redes Privadas**:  
  - `10.0.2.0/24` (us-east-1a)  
  - `10.0.4.0/24` (us-east-1b)  
- **NAT Gateway** em cada AZ pública para atualizações seguras 

---

## 4. Grupos de Segurança <a id="4-grupos-de-seguranca"></a>  
| Componente     | Portas         | Fonte                     |  
|----------------|----------------|---------------------------|  
| **Load Balancer** | 80 (HTTP)     | 0.0.0.0/0                |  
| **EC2**        | 80 (HTTP), 22  | SG do Load Balancer + IP fixo |  
| **RDS**        | 3306 (MySQL)   | SG das instâncias EC2     |  
| **EFS**        | 2049 (NFS)     | SG das instâncias EC2     |  

---

## 5. Banco de Dados (RDS) <a id="5-banco-de-dados-rds"></a>  
**Configuração**:  
- **Engine**: MySQL 8.0  
- **Classe**: `db.t3.micro` (para testes)  


---

## 6. Sistema de Arquivos (EFS) <a id="6-sistema-de-arquivos-efs"></a>  
**Montagem Automática por AZ**:  
```bash
# Exemplo para us-east-1a:
mount -t nfs4 -o nfsvers=4.1 10.0.2.100:/ /mnt/efs  
```  
**Otimizações**:  
- Tamanho de bloco: `rsize=1048576` e `wsize=1048576` 
- Timeout: `timeo=600` para tolerância a falhas  

---

## 7. Auto Scaling Group <a id="7-auto-scaling-group"></a>  
**Template de Lançamento**:  
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
WORDPRESS_DB_HOST: [endpoint_rds] # Ex: db-1.tim123.region.rds.amazonaws.com
WORDPRESS_DB_USER: [usuario_cloud] # Ex: admin
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
**Política de Escalonamento**:  
- **Mínimo**: 2 instâncias  
- **Máximo**: 3 instâncias  
- **Métrica**: CPU > 70% por 5 minutos 

---

## 8. Load Balancer <a id="8-load-balancer"></a>  
**Health Check**:  
- **Caminho**: `/wp-login.php`  
- **Intervalo**: 30 segundos  
- **Threshold**: 2 falhas consecutivas 

---

## 9. Validação <a id="9-validacao"></a>  
**Passos**:  
1. **Verificação Docker**:  
   ```bash
   docker ps | grep wordpress
   ```  
2. **Verificação EFS**:  
   ```bash
   df -h | grep efs
   ```  
3. **Acesso via Browser**:  
   ```text
   http://<DNS-do-Load-Balancer>
   ```  

---

## 10. Implementação Avançada <a id="10-implementacao-avancada"></a>  
**Sugestões**:  
- **CloudWatch Alarms**:  
  ```bash
  aws cloudwatch put-metric-alarm --alarm-name High-CPU --threshold 70 --metric-name CPUUtilization [[3]]
  ```  
- **Spot Instances**: Redução de custos em até 70% 
- **Certificado SSL**: Use AWS Certificate Manager para HTTPS   

---

## 11. Conclusão <a id="11-conclusao"></a>  
O CloudPress oferece alta disponibilidade e escalabilidade para WordPress na AWS. Para produção:  
1. Use **Multi-AZ** no RDS  
2. Habilite **Encryption at Rest** no EFS 
3. Implemente **WAF** no Load Balancer  

**[⬆ Voltar ao Índice](#índice)**  

