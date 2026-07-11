# Runbook de Homologacao

Este documento define o padrao operacional para um terceiro ambiente de
homologacao do CEDE, entre desenvolvimento e producao.

Objetivo:

- validar deploy, banco, storage, integracoes e cache antes da promocao final;
- reproduzir o comportamento de producao com isolamento real;
- reduzir regressao operacional em `https://cedern.org/`.

## Topologia recomendada

Ambientes atuais:

- desenvolvimento: `https://srv798468.hstgr.cloud/cedern/`
- producao: `https://cedern.org/`

Ambiente novo recomendado:

- homologacao: `https://homolog.cedern.org/`

Contrato do ambiente de homologacao:

- codigo separado da producao;
- banco separado da producao;
- storage separado da producao;
- `.env` separado da producao;
- tokens e chaves separados da producao;
- mesma logica de runtime da producao.

Regra principal:

- homologacao nunca deve apontar para o banco nem para o storage real de
  producao.

## Por que homologacao agora faz sentido

Desenvolvimento hoje roda em VPS com SSH e URL em subdiretorio:

- `https://srv798468.hstgr.cloud/cedern/`

Producao hoje roda em Hostinger shared, na raiz do dominio e sem SSH:

- `https://cedern.org/`

Isso cria diferencas importantes:

- `APP_BASE`;
- forma de publicacao;
- acesso a logs;
- acesso a shell;
- permissao sobre arquivos;
- forma de validar importacao de midia e patches.

Homologacao serve para testar exatamente esse segundo modelo antes de tocar a
producao.

## Regra de `APP_ENV`

Para homologacao que precisa espelhar producao, o recomendado e:

```dotenv
APP_ENV=production
```

Motivo tecnico:

- no runtime atual, `APP_ENV=homolog` e tratado como ambiente
  development-like;
- isso libera diagnosticos automaticamente;
- isso tambem libera fallback de repositorio por padrao;
- portanto `APP_ENV=homolog` nao reproduz fielmente a severidade operacional
  da producao.

Use `APP_ENV=homolog` apenas se a intencao for ter um ambiente mais permissivo
de apoio interno. Para homologacao pre-producao, use `APP_ENV=production`.

## Branches e promocao

Fluxo recomendado:

1. `cedern` ou branch de trabalho: desenvolvimento corrente.
2. `homolog`: branch dedicada ao ambiente de homologacao.
3. `main`: branch dedicada a producao.

Regra operacional:

- nada sobe para `main` sem antes passar por `homolog`;
- `homolog` recebe o mesmo codigo que se pretende publicar em producao;
- a promocao para `main` deve ser merge ou fast-forward do estado ja validado.

## Bootstrap inicial da branch `homolog`

Na criacao da homologacao, a branch `homolog` deve nascer uma unica vez a
partir do estado que hoje representa a producao.

Se o deploy de producao sai da branch `main`, a referencia correta para esse
bootstrap e `origin/main`, nao uma copia local antiga de `main`.

Procedimento recomendado:

```bash
git fetch origin
git switch -c homolog origin/main
git push -u origin homolog
```

Resultado esperado:

- `homolog` nasce igual ao codigo atualmente publicado ou prestes a ser
  publicado em producao;
- o subdominio `homolog.cedern.org` passa a ter uma branch propria de deploy;
- a partir daqui, homologacao deixa de depender diretamente de `main`.

Regra importante:

- esse bootstrap a partir de `main` acontece uma vez;
- depois disso, o deploy da homologacao deve sair da propria branch `homolog`.

## Mapeamento de deploy

Configuracao recomendada na Hostinger:

- `cedern.org` publica a branch `main`
- `homolog.cedern.org` publica a branch `homolog`

Mapeamento fisico esperado:

- producao: `/home/u429418010/domains/cedern.org/public_html`
- homologacao: `/home/u429418010/domains/cedern.org/public_html/homolog`

Regra:

- nao apontar `homolog.cedern.org` para a branch `main`;
- nao usar `cedern.org/homolog` como URL da homologacao.

## Fluxo normal depois do bootstrap

Depois que a branch `homolog` existir, o ciclo recomendado passa a ser:

1. desenvolvimento em `cedern` ou branch de release;
2. promocao de `cedern` para `homolog`;
3. deploy automatico da branch `homolog` em `homolog.cedern.org`;
4. validacao funcional completa na homologacao;
5. promocao de `homolog` para `main`;
6. deploy automatico de `main` em `cedern.org`.

Modelo recomendado de promocao:

- PR `cedern -> homolog`
- PR `homolog -> main`

Vantagem:

- o mesmo estado testado em homologacao e o que segue para producao.

## Isolamento minimo obrigatorio

Homologacao precisa ter:

- dominio ou subdominio proprio;
- diretorio publicado proprio;
- banco proprio;
- storage gerenciado proprio;
- log proprio;
- credenciais SMTP proprias ou controladas;
- reCAPTCHA configurado para o hostname de homologacao;
- Asaas em `sandbox`.

Exemplo de storage dedicado:

```dotenv
APP_MANAGED_STORAGE_ROOT="/home/usuario/_cedern_storage_homolog"
APP_MANAGED_STORAGE_IMPORT_ARCHIVE_DIR="/home/usuario/_cedern_storage_homolog/imports/managed-storage-zips"
```

Buckets canonicos:

- `/home/usuario/_cedern_storage_homolog/bookshop/covers`
- `/home/usuario/_cedern_storage_homolog/library/docs`
- `/home/usuario/_cedern_storage_homolog/library/covers`
- `/home/usuario/_cedern_storage_homolog/member-photos`
- `/home/usuario/_cedern_storage_homolog/patrimony/docs`
- `/home/usuario/_cedern_storage_homolog/patrimony/img`

## Template minimo do `.env` de homologacao

Se homologacao roda na raiz do subdominio:

```dotenv
APP_ENV=production
APP_BASE=""
APP_LOG_PATH="/home/usuario/logs/cedern-homolog.log"
APP_ALLOW_REPOSITORY_FALLBACK="false"
APP_ENABLE_DIAGNOSTIC_ROUTES="false"
APP_DIAGNOSTIC_TOKEN="troque_este_token"
APP_MANAGED_STORAGE_ROOT="/home/usuario/_cedern_storage_homolog"
APP_MANAGED_STORAGE_IMPORT_ARCHIVE_DIR="/home/usuario/_cedern_storage_homolog/imports/managed-storage-zips"
APP_ENABLE_LEGACY_MEDIA_FALLBACK="false"

APP_DEFAULT_PAGE_URL="https://homolog.cedern.org/"

RECAPTCHA_ENABLED="true"
RECAPTCHA_ALLOWED_HOSTNAME="homolog.cedern.org"

ASAAS_ENVIRONMENT="sandbox"
ASAAS_ALLOW_PRODUCTION_IN_NON_PRODUCTION="false"
```

Se homologacao rodar em subdiretorio, ajuste apenas:

```dotenv
APP_BASE="/cedern-homolog"
```

## Fluxo de criacao da homologacao

### 1. Publicar o codigo

Criar um novo alvo de deploy para a branch `homolog`.

Requisitos:

- diretorio publicado isolado;
- mesmo codigo da release candidata;
- `vendor/` presente;
- `.env` proprio do ambiente.

### 2. Criar banco proprio

Criar um banco de homologacao separado do banco de producao.

Regra:

- nunca reaproveitar o banco real de producao como banco de homologacao.

Fonte recomendada para bootstrap:

- dump controlado do desenvolvimento;
- ou schema base mais patches, quando quiser um ambiente mais limpo.

### 3. Criar storage proprio

Criar os buckets dentro do root de homologacao e garantir permissao de leitura e
escrita pelo PHP.

### 4. Importar baseline de midia

Gerar os pacotes no desenvolvimento:

```bash
composer storage:package
```

Enviar os `.zip` para:

- `/home/usuario/_cedern_storage_homolog/imports/managed-storage-zips`

Depois executar em homologacao:

- `/health/storage/import?token=SEU_TOKEN`
- `/health/storage/import?token=SEU_TOKEN&execute=1&kind=all`

Validacao obrigatoria:

- `post_import_snapshot.missing_expected_file_count=0`

### 5. Aplicar patches SQL

Executar em homologacao:

- `/health/migrations?token=SEU_TOKEN`
- `/health/migrations?token=SEU_TOKEN&execute=1`

Homologacao so avanca quando:

- `pending_count=0`
- `checksum_mismatch_count=0`

### 6. Validar prontidao

Executar:

- `/health/readiness?token=SEU_TOKEN`

Esperado:

- storage pronto;
- banco pronto;
- logger gravavel;
- patches em dia;
- fallback de repositorio desligado.

### 7. Smoke check funcional

Validar no navegador:

- home publica;
- login publico;
- painel administrativo;
- listagem de membros com foto;
- livraria com capas;
- base de conhecimento com capas e PDFs;
- patrimonio com imagem e documento;
- formularios com reCAPTCHA;
- fluxos que enviam e-mail;
- fluxos que tocam Asaas em sandbox.

## Politica de dados

Homologacao nao precisa ser copia permanente da producao.

Padrao recomendado:

- desenvolvimento monta a release;
- homologacao recebe um baseline coerente com essa release;
- homologacao valida estrutura, rotas, storage, banco e integracoes;
- producao recebe apenas o estado aprovado.

Se for preciso levar dados reais para homologacao:

- anonimizar quando houver dado pessoal sensivel;
- limitar acesso ao ambiente;
- evitar espelhamento automatico sem controle.

## Protecao do ambiente

Homologacao deve ser protegida.

Recomendacoes:

- `robots` bloqueado ou `noindex`;
- autenticacao HTTP basica, se possivel;
- token forte para rotas de diagnostico;
- sem indexacao publica por mecanismos de busca.

## Checklist por release

Antes de subir para homologacao:

- `composer test`
- `composer db:migrate`
- `composer storage:audit`
- sem arquivos ausentes no storage de origem

Depois do deploy em homologacao:

- `/health/storage/import` coerente
- `/health/storage/import?token=SEU_TOKEN&execute=1&kind=all` concluido
- `/health/migrations` sem pendencias
- `/health/readiness` sem erro
- smoke check funcional completo

Antes de promover para producao:

- codigo em `homolog` igual ao que vai para `main`
- banco validado
- storage validado
- reCAPTCHA validado no hostname correto
- Asaas mantido em `sandbox` na homologacao

## Quando homologacao vai resolver de fato

Homologacao passa a resolver um problema real quando ela pega, antes da
producao, erros como:

- `APP_BASE` incorreto;
- storage apontando para root errado;
- `.zip` em diretorio nao lido pelo PHP;
- patches SQL pendentes;
- reCAPTCHA travando por hostname;
- cache antigo servindo assets antigos;
- diferencas de permissao entre VPS e Hostinger shared.

Sem esse terceiro ambiente, esses problemas continuam sendo descobertos apenas
em `https://cedern.org/`.

## Documentos relacionados

- [PRODUCTION_OPERATIONS_RUNBOOK.md](/var/www/cedern/docs/PRODUCTION_OPERATIONS_RUNBOOK.md)
- [HOSTINGER_SHARED_DEPLOY.md](/var/www/cedern/docs/HOSTINGER_SHARED_DEPLOY.md)
- [ENVIRONMENT_CONFIGURATION.md](/var/www/cedern/docs/ENVIRONMENT_CONFIGURATION.md)
- [MANAGED_MEDIA_STANDARD.md](/var/www/cedern/docs/MANAGED_MEDIA_STANDARD.md)
