# CloudPress

Este projeto consiste na implantação de uma aplicação WordPress na AWS utilizando Docker, EFS e Load Balancer.

## Estrutura do Projeto

- **infra/**: Scripts de infraestrutura.
- **nginx/**: Configuração do proxy reverso e SSL.
- **wordpress/**: Configuração do WordPress.
- **db/**: Configuração do banco de dados.
- **portainer/**: Configuração do Portainer.
- **scripts/**: Scripts úteis para backup e restore.
- **docs/**: Documentação técnica.

## Como Usar

1. Clone o repositório.
2. Configure as variáveis de ambiente no arquivo `.env`.
3. Execute `docker-compose up -d` para iniciar os containers.
