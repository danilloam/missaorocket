# 🚀 Missão Rocket

> Plataforma web de gamificação criada para transformar atividades, desafios e participação em uma experiência de competição saudável, com missões, pontuação, rankings, medalhas, clãs e acompanhamento de desempenho.

## 📋 Sobre o projeto

O **Missão Rocket** é uma aplicação web desenvolvida em PHP com foco em **gamificação de participantes**, permitindo organizar desafios e acompanhar a evolução individual e por grupos.

A plataforma possui uma interface mobile/PWA e um painel administrativo para gerenciamento dos participantes, grupos, provas, evidências, pontuação e notificações.

O projeto foi estruturado para funcionar como uma aplicação web responsiva, podendo ser instalada como **Progressive Web App (PWA)** em dispositivos compatíveis.

---

## ✨ Funcionalidades

### 🎯 Missões e provas

- Cadastro e gerenciamento de provas/missões.
- Definição de título, descrição, pontuação e período de validade.
- Provas globais ou direcionadas a grupos.
- Envio de evidências pelos participantes.
- Suporte a evidências em:
  - JPG
  - JPEG
  - PNG
  - PDF
- Validação das evidências pela administração.
- Estados de evidência:
  - `pendente`
  - `aprovado`
  - `rejeitado`

### 💎 Sistema de pontuação

A plataforma registra os pontos obtidos nas missões aprovadas por meio do histórico de pontuação.

A pontuação pode ser utilizada para:

- Ranking individual.
- Ranking por clã/grupo.
- Acompanhamento de desempenho.
- Conquistas e medalhas.

### 🔥 Ofensivas (Streak)

O sistema possui mecânica de **ofensivas**, permitindo acompanhar sequências de participação.

Também existe configuração de recompensas por quantidade de dias consecutivos, incluindo:

- quantidade de dias necessários;
- pontos de bônus;
- título da medalha;
- ícone da medalha.

### 🏅 Medalhas

Os participantes podem conquistar medalhas conforme as regras configuradas no sistema.

A área de medalhas apresenta:

- medalhas conquistadas;
- data da conquista;
- total de medalhas;
- configurações relacionadas às ofensivas.

### 👥 Clãs / Grupos

Os participantes podem ser associados a grupos, chamados na interface de **Clãs** ou **GCs**.

Cada grupo pode possuir:

- nome;
- logotipo;
- participantes;
- pontuação acumulada;
- ranking próprio.

A administração possui recursos para gerenciar grupos e seus participantes.

### 🏆 Rankings

O sistema possui diferentes visões de ranking:

- Ranking individual.
- Ranking dos participantes do grupo.
- Ranking geral dos grupos/clãs.
- Ranking com período configurável.

Os rankings utilizam os pontos aprovados registrados no histórico.

### 📱 Aplicação Mobile / PWA

O diretório `/mobile/` concentra a experiência mobile do sistema.

Entre os recursos disponíveis estão:

- Dashboard.
- Login.
- Perfil.
- Provas.
- Evidências.
- Rankings.
- Medalhas.
- Grupos.
- Check-in.
- Notificações.
- Instalação como PWA.

A aplicação possui:

- `manifest.json`
- Service Worker
- recursos específicos para instalação e funcionamento como aplicação web.

### 🔔 Notificações Push

O projeto utiliza **Web Push** para enviar notificações aos participantes.

As notificações podem ser utilizadas, entre outras situações, para informar:

- aprovação de evidências;
- rejeição de evidências;
- novas atividades;
- lembretes de provas;
- outras ações definidas pelo sistema.

A implementação utiliza a biblioteca `minishlink/web-push`.

### 📧 Notificações por e-mail

O sistema utiliza **PHPMailer** para envio de mensagens por SMTP.

Existem rotinas automatizadas para identificar provas pendentes e enviar lembretes aos participantes.

Entre os scripts de automação estão:

- `cron_tarefas.php`
- `cron_tempo_correndo.php`

Esses scripts podem ser executados por **Cron Job** no servidor.

### 📷 Check-in por QR Code

O sistema possui check-in utilizando QR Code.

O participante pode utilizar a câmera do dispositivo para ler o código exibido no telão.

Arquivos relacionados incluem:

- `mobile/checkin.php`
- `mobile/processar_checkin.php`
- `checkin.php`
- `totem_checkin.php`

### 🧬 Check-in por reconhecimento facial

O projeto também possui um fluxo de identificação por biometria facial.

A administração pode cadastrar a biometria de um participante e o sistema compara o vetor facial capturado no totem com os vetores armazenados.

Principais arquivos:

- `cadastrar_biometria.php`
- `salvar_biometria_action.php`
- `totem_checkin.php`
- `processar_totem_action.php`

> **Atenção:** recursos biométricos envolvem dados pessoais sensíveis e devem ser tratados de acordo com os requisitos de segurança, privacidade e legislação aplicáveis.

---

## 🖥️ Painel administrativo

A administração possui recursos para gerenciar a plataforma.

Entre eles:

- participantes;
- grupos/clãs;
- provas;
- evidências;
- medalhas;
- penalizações;
- configurações de ofensivas;
- notificações;
- biometria;
- acompanhamento dos registros.

Principais arquivos administrativos:

```text
gerenciar.php
mobile/gerenciar.php
mobile/gerenciar_participantes.php
mobile/gerenciar_grupos.php
mobile/gerenciar_provas.php
mobile/penalizacoes.php
```

O acesso administrativo é protegido pelo perfil `admin`.

---

## 🔐 Autenticação

A plataforma possui autenticação baseada em sessão para a aplicação web.

Também existe uma camada de autenticação para a API utilizando token.

O login da API é realizado através de:

```text
POST /api/login.php
```

Após autenticação, a API retorna um token associado ao usuário.

Os arquivos relacionados à autenticação da API incluem:

```text
api/auth.php
api/login.php
```

As senhas dos usuários são verificadas utilizando `password_verify()`.

---

## 🔌 API

O projeto possui endpoints PHP para integração com aplicações externas ou futuras interfaces.

Estrutura:

```text
api/
├── auth.php
├── dashboard.php
├── enviar_prova.php
├── login.php
├── minhas_medalhas.php
├── perfil.php
├── prova.php
├── provas.php
└── ranking.php
```

Existe também um endpoint geral em:

```text
/api.php
```

que disponibiliza dados consolidados de rankings.

---

## 🗄️ Banco de dados

A aplicação utiliza **PDO** para comunicação com o banco de dados.

Entre as entidades identificadas no código estão:

```text
usuarios
grupos
provas
historico_pontos
medalhas
usuarios_ofensivas
config_ofensivas
usuarios_notificacoes
usuarios_sessoes
prova_destinatarios
penalizacoes_grupos
locais
```

### Principais relacionamentos conceituais

```text
USUÁRIO
   │
   ├── pertence a ──> GRUPO
   │
   ├── participa de ──> PROVA
   │                       │
   │                       └── gera ──> HISTÓRICO DE PONTOS
   │
   ├── conquista ──> MEDALHAS
   │
   ├── possui ──> OFENSIVA
   │
   └── recebe ──> NOTIFICAÇÕES
```

O ZIP analisado não contém um dump/schema SQL completo. Para disponibilizar o projeto publicamente, recomenda-se criar posteriormente um arquivo como:

```text
database/schema.sql
```

com a estrutura necessária para instalação.

---

## 📁 Estrutura do projeto

Uma visão simplificada da estrutura:

```text
/
├── api/
│   ├── auth.php
│   ├── dashboard.php
│   ├── enviar_prova.php
│   ├── login.php
│   ├── minhas_medalhas.php
│   ├── perfil.php
│   ├── prova.php
│   ├── provas.php
│   └── ranking.php
│
├── mobile/
│   ├── assets/
│   │   ├── css/
│   │   ├── img/
│   │   └── js/
│   │
│   ├── components/
│   ├── mock/
│   ├── pwa/
│   ├── uploads/
│   ├── dashboard.php
│   ├── provas.php
│   ├── checkin.php
│   ├── ranking.php
│   ├── medalhas.php
│   ├── gerenciar.php
│   ├── gerenciar_grupos.php
│   ├── gerenciar_participantes.php
│   ├── gerenciar_provas.php
│   ├── manifest.json
│   └── sw.js
│
├── uploads/
├── PHPMailer-master/
├── config.php
├── index.php
├── dashboard.php
├── gerenciar.php
├── checkin.php
├── totem_checkin.php
├── cadastrar_biometria.php
├── processar_totem_action.php
├── cron_tarefas.php
├── cron_tempo_correndo.php
├── manifest.json
├── sw.js
└── .htaccess
```

> A pasta `vendor/` contém dependências de terceiros utilizadas pela aplicação e não deve ser descrita como código próprio do projeto.

---

## 🛠️ Tecnologias

### Backend

- PHP
- PDO
- MySQL/MariaDB
- PHP Sessions
- REST-like API em PHP

### Frontend

- HTML5
- CSS3
- JavaScript
- Bootstrap
- Font Awesome
- PWA / Service Worker

### Bibliotecas

- PHPMailer
- Minishlink Web Push
- Guzzle
- Brick Math
- outras dependências Composer presentes em `mobile/vendor/`

### Recursos do navegador

- Camera API
- QR Code
- Web Push
- Service Worker
- PWA
- recursos de reconhecimento facial no fluxo de biometria

---

## ⚙️ Requisitos

Para executar o projeto, recomenda-se um ambiente com:

- PHP 8.x
- MySQL ou MariaDB
- Apache ou servidor web compatível
- HTTPS para recursos que dependem de câmera, Push e APIs do navegador
- Extensão PDO para o banco utilizado
- Extensões PHP necessárias pelas dependências instaladas

Para as funcionalidades de envio de e-mail:

- acesso SMTP;
- conta de e-mail;
- configuração das credenciais SMTP.

Para Web Push:

- chaves VAPID;
- configuração adequada do Service Worker;
- HTTPS.

---

## 🚀 Instalação

### 1. Clone o projeto

```bash
git clone https://github.com/SEU_USUARIO/missao-rocket.git
cd missao-rocket
```

### 2. Configure o banco de dados

Crie o banco de dados:

```sql
CREATE DATABASE missao_rocket
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Depois importe o schema do projeto, quando disponibilizado:

```bash
mysql -u usuario -p missao_rocket < database/schema.sql
```

### 3. Configure a conexão

A conexão atualmente é centralizada em:

```text
config.php
```

Para uma versão pública do projeto, recomenda-se migrar credenciais para variáveis de ambiente ou arquivo de configuração fora do diretório público.

Exemplo conceitual:

```env
DB_HOST=localhost
DB_NAME=missao_rocket
DB_USER=usuario
DB_PASSWORD=senha
```

### 4. Configure as permissões

Garanta que o servidor possua permissão de escrita nos diretórios destinados aos uploads:

```text
/uploads
/mobile/uploads
```

### 5. Configure o Web Push

Cadastre as chaves VAPID no servidor de forma segura.

Não publique as chaves privadas no GitHub.

### 6. Configure o SMTP

Configure as credenciais do servidor de e-mail.

Também não publique senhas SMTP no repositório.

### 7. Configure os Cron Jobs

As rotinas de lembrete podem ser executadas através do Cron:

```bash
php /caminho/do/projeto/cron_tarefas.php
```

e:

```bash
php /caminho/do/projeto/cron_tempo_correndo.php
```

A frequência deve ser definida de acordo com a estratégia de notificações do projeto.

---

## 📱 PWA

A aplicação mobile possui manifesto e Service Worker.

Arquivos principais:

```text
mobile/manifest.json
mobile/sw.js
mobile/pwa/manifest.json
mobile/pwa/service-worker.js
```

Em navegadores compatíveis, o usuário poderá adicionar a aplicação à tela inicial.

Para que os recursos PWA funcionem corretamente, recomenda-se utilizar HTTPS.

---

## 🔔 Fluxo de uma missão

O fluxo principal pode ser representado da seguinte forma:

```text
ADMIN
  │
  ├── cria PROVA
  │
  ├── define período
  │
  ├── define pontuação
  │
  └── disponibiliza para participantes
              │
              ▼
        PARTICIPANTE
              │
              ├── visualiza missão
              │
              ├── executa atividade
              │
              └── envia evidência
                         │
                         ▼
                   ADMINISTRAÇÃO
                         │
                 ┌───────┴───────┐
                 ▼               ▼
             APROVADA         REJEITADA
                 │
                 ▼
             PONTOS
                 │
        ┌────────┼────────┐
        ▼        ▼        ▼
     RANKING  MEDALHA  OFENSIVA
```

---

## 🔄 Fluxo de check-in

### QR Code

```text
TELÃO
  │
  └── exibe QR Code
          │
          ▼
PARTICIPANTE
          │
          └── escaneia QR Code
                    │
                    ▼
              validação
                    │
                    ▼
               CHECK-IN
```

### Biometria

```text
ADMIN
  │
  └── cadastra biometria
            │
            ▼
       vetor facial
            │
            ▼
          BANCO
            │
            ▼
          TOTEM
            │
            └── captura rosto
                    │
                    ▼
             comparação facial
                    │
                    ▼
             identificação
                    │
                    ▼
                 CHECK-IN
```

---

## 🔒 Segurança

O projeto possui mecanismos como:

- sessões PHP;
- controle de perfil administrativo;
- `password_verify()` para validação de senhas;
- consultas preparadas com PDO;
- token para autenticação da API;
- validação de extensões de arquivos;
- validações de upload;
- proteção CSRF em partes da aplicação;
- controle de acesso a funcionalidades administrativas.

### Recomendações antes de publicar

Antes de colocar o projeto em um repositório público:

1. Remova todas as senhas do código.
2. Remova chaves privadas VAPID.
3. Remova credenciais SMTP.
4. Remova tokens e segredos de produção.
5. Remova uploads reais de usuários.
6. Remova logs contendo informações privadas.
7. Crie um `.env.example`.
8. Adicione arquivos sensíveis ao `.gitignore`.
9. Gere novas credenciais para o ambiente de produção caso alguma tenha sido exposta.
10. Não publique dados biométricos reais.

---

## 🧹 Arquivos que não devem ir para o GitHub

Recomenda-se adicionar ao `.gitignore`:

```gitignore
.env
.env.*
*.log
error_log

/uploads/*
/mobile/uploads/*
!/uploads/.gitkeep
!/mobile/uploads/.gitkeep

vendor/
mobile/vendor/

.DS_Store
Thumbs.db
```

Caso as dependências sejam versionadas junto com o projeto, ajuste as regras de `vendor/` de acordo com a estratégia escolhida.

---

## 🧪 Ambiente de desenvolvimento

Para desenvolvimento local, pode-se utilizar:

- XAMPP
- WAMP
- Laragon
- Apache + PHP + MySQL/MariaDB
- Docker

O projeto utiliza caminhos relativos e deve ser configurado de acordo com o diretório publicado pelo servidor web.

---

## 📌 Próximos passos sugeridos

Algumas melhorias que podem ser consideradas para futuras versões:

- [ ] Criar schema SQL oficial de instalação.
- [ ] Centralizar configurações em `.env`.
- [ ] Separar configuração de produção e desenvolvimento.
- [ ] Criar documentação completa da API.
- [ ] Criar testes automatizados.
- [ ] Adicionar controle de logs estruturado.
- [ ] Melhorar documentação de permissões e perfis.
- [ ] Criar processo automatizado de deploy.
- [ ] Revisar políticas de retenção de uploads.
- [ ] Revisar armazenamento e proteção de dados biométricos.
- [ ] Adicionar screenshots oficiais ao README.
- [ ] Criar ambiente Docker para facilitar a instalação.

---

## 📄 Licença

A licença do projeto deve ser definida pelo autor antes da publicação.

Se nenhuma licença for adicionada ao repositório, os direitos autorais permanecem com o autor e terceiros não recebem automaticamente autorização para reutilizar, modificar ou distribuir o código.

---

## 👨‍💻 Autor

**Danillo Almeida Marques**

Projeto desenvolvido como uma plataforma de gamificação e gestão de desafios, com foco em experiência mobile, competição entre grupos e acompanhamento de desempenho.

---

## 🚀 Missão Rocket

**Transforme participação em missão.  
Missão em pontos.  
Pontos em conquistas.**

