# Deploy na Hostinger Shared

Este projeto pode ser publicado em hospedagem compartilhada da Hostinger sem Docker.
O fluxo abaixo assume Git deploy ou upload do repositório para o diretório publicado.

## Checklist de compatibilidade

- PHP configurado na Hostinger: `8.4`
- Composer alvo do projeto: `8.3` via `config.platform.php`
- Branch de deploy: preferencialmente `main`
- `APP_BASE=""` quando o site roda na raiz do domínio
- `APP_BASE="/cedern"` quando o site roda em subdiretório

Observação:
Se `APP_BASE` ficar vazio, o bootstrap tenta autodetectar o prefixo a partir de `SCRIPT_NAME`
e ignora o sufixo `/public` gerado por reescrita Apache. Isso serve como fallback, nao como
substituto da configuracao final de producao.

## Estrutura publicada

O repositório ja possui um `.htaccess` na raiz que reescreve as requisicoes para `public/`.
Com isso, a aplicacao pode ser publicada mantendo a estrutura inteira do projeto.

## Ambiente de producao

Mantenha o arquivo real de ambiente fora do repositório e aponte para ele com `APP_ENV_FILE`.

Template completo versionado:

- [.env.production.example](/var/www/cedern/.env.production.example)

Exemplo:

```apache
SetEnv APP_ENV_FILE "/home/usuario/.secrets/cedern.prod.env"
```

Exemplo minimo para dominio raiz:

```dotenv
APP_ENV=production
APP_BASE=""
APP_ENV_FILE="/home/usuario/.secrets/cedern.prod.env"
APP_LOG_PATH="/home/usuario/logs/cedern-app.log"
APP_ASSET_VERSION="1"
APP_ENABLE_THEME_PALETTE="false"
APP_ENABLE_DASHBOARD_THEME_PALETTE="true"
RECAPTCHA_ENABLED="true"
RECAPTCHA_ALLOWED_HOSTNAME="cedern.org"
```

Exemplo minimo para subdiretório:

```dotenv
APP_ENV=production
APP_BASE="/cedern"
APP_LOG_PATH="/home/usuario/logs/cedern-app.log"
APP_ASSET_VERSION="1"
APP_ENABLE_THEME_PALETTE="false"
APP_ENABLE_DASHBOARD_THEME_PALETTE="true"
RECAPTCHA_ENABLED="false"
```

## Diretorios gravaveis

Garanta permissao de escrita para:

- `var/cache`
- `var/storage/bookshop/covers`
- `var/storage/library/covers`
- `var/storage/library/docs`
- `var/storage/member-photos`
- `var/storage/patrimony/docs`
- `var/storage/patrimony/img`
- o caminho configurado em `APP_LOG_PATH`, se estiver fora do projeto

Importante:
Os arquivos dentro de `var/storage/**` sao ignorados pelo Git neste projeto. Entao o deploy por branch/webhook publica codigo e banco, mas nao leva automaticamente fotos de membros, capas, PDFs e outros uploads gerenciados. Ao promover dados entre desenvolvimento e producao, sincronize tambem esses diretorios ou mantenha um storage compartilhado entre os ambientes.

## Composer

Validacoes locais antes do deploy:

```bash
composer validate --strict
composer install --dry-run --no-interaction
composer prohibits php 8.3.0 --locked
```

Se a Hostinger oferecer `composer2`, prefira:

```bash
composer2 install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

## Publicacao segura

1. Ajuste o `.env` real no servidor.
2. Incremente `APP_ASSET_VERSION` quando houver mudanca visual relevante.
3. Se houver mudanca de schema, rode `php scripts/migrate.php` e depois `php scripts/migrate.php --apply`.
4. Envie a branch de deploy.
5. Execute o install de dependencias no servidor, se o fluxo nao fizer isso automaticamente.
6. Verifique home publica, login, painel e formularios com e-mail/recaptcha.

Observacao:
Antes de aplicar patches de banco em producao, faca backup do banco real. O fluxo recomendado
e aplicar apenas patches incrementais, nunca substituir o banco inteiro por um dump do ambiente
de desenvolvimento.

## Smoke checks

```bash
curl -I https://cedern.org/
curl -fsS https://cedern.org/ | rg 'assets|CEDE|Centro de Estudos'
```

Para subdiretório:

```bash
curl -I https://host/cedern/
curl -fsS https://host/cedern/ | rg '/cedern/assets|CEDE'
```
