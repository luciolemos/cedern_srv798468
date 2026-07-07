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
APP_MANAGED_STORAGE_ROOT="/home/usuario/cedern-storage"
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
APP_MANAGED_STORAGE_ROOT="/home/usuario/cedern-storage"
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

Se `APP_MANAGED_STORAGE_ROOT` estiver ativo, a exigencia de escrita passa a valer para os subdiretorios correspondentes dentro desse root compartilhado.

Importante:
Os arquivos dentro de `var/storage/**` sao ignorados pelo Git neste projeto. Entao o deploy por branch/webhook publica codigo e banco, mas nao leva automaticamente fotos de membros, capas, PDFs e outros uploads gerenciados. Ao promover dados entre desenvolvimento e producao, sincronize tambem esses diretorios ou mantenha um storage compartilhado entre os ambientes.

Recomendacao profissional:
defina `APP_MANAGED_STORAGE_ROOT` em desenvolvimento e producao apontando para uma pasta compartilhada fora da arvore publicada. Assim, os uploads deixam de depender do diretório `var/storage` dentro do release atual. Quando esse root estiver ativo, caminhos relativos como `var/storage/library/docs` e `var/storage/bookshop/covers` passam automaticamente a ser resolvidos dentro dele.

## Sincronizacao de uploads

Diretorios que precisam acompanhar a publicacao quando houver dados reais:

- `var/storage/member-photos`
- `var/storage/bookshop/covers`
- `var/storage/library/docs`
- `var/storage/library/covers`
- `var/storage/patrimony/docs`
- `var/storage/patrimony/img`

### Procedimento recomendado com SSH

Use quando desenvolvimento e producao estiverem acessiveis por terminal no mesmo servidor ou em servidores com SSH liberado.

Defina os caminhos reais:

```bash
DEV_ROOT="/caminho/do/ambiente-de-desenvolvimento"
PROD_ROOT="/caminho/do/ambiente-de-producao"
```

Exemplo de sincronizacao so das fotos de membros:

```bash
rsync -av --progress "$DEV_ROOT/var/storage/member-photos/" "$PROD_ROOT/var/storage/member-photos/"
```

Exemplo completo:

```bash
rsync -av --progress "$DEV_ROOT/var/storage/member-photos/" "$PROD_ROOT/var/storage/member-photos/"
rsync -av --progress "$DEV_ROOT/var/storage/bookshop/covers/" "$PROD_ROOT/var/storage/bookshop/covers/"
rsync -av --progress "$DEV_ROOT/var/storage/library/docs/" "$PROD_ROOT/var/storage/library/docs/"
rsync -av --progress "$DEV_ROOT/var/storage/library/covers/" "$PROD_ROOT/var/storage/library/covers/"
rsync -av --progress "$DEV_ROOT/var/storage/patrimony/docs/" "$PROD_ROOT/var/storage/patrimony/docs/"
rsync -av --progress "$DEV_ROOT/var/storage/patrimony/img/" "$PROD_ROOT/var/storage/patrimony/img/"
```

Se `rsync` nao estiver disponivel, use `cp` no mesmo servidor:

```bash
cp -a "$DEV_ROOT/var/storage/member-photos/." "$PROD_ROOT/var/storage/member-photos/"
```

### Procedimento sem SSH

Use quando o acesso for apenas pelo painel da Hostinger.

1. No ambiente de desenvolvimento, compacte o conteudo do diretorio desejado.
2. Baixe o `.zip` para sua maquina.
3. No ambiente de producao, envie esse `.zip` para o diretorio correspondente.
4. Extraia o conteudo mantendo os nomes originais dos arquivos.
5. Apague o `.zip` depois da extracao.

Para o problema atual das fotos de membros, o diretorio a sincronizar e:

```text
var/storage/member-photos
```

### Ordem segura no seu fluxo atual

1. Atualize o codigo em producao pelo Git/webhook.
2. Aplique eventual patch SQL de banco.
3. Se ainda houver registros antigos apontando para `assets/...`, rode a migracao correspondente no ambiente de desenvolvimento e publique o banco ja corrigido.
4. Se ainda nao usa `APP_MANAGED_STORAGE_ROOT`, sincronize os diretorios de `var/storage` afetados pela release.
5. Confirme permissoes de escrita.
6. Rode `composer storage:audit`.
7. Rode validacao manual no navegador.

### Migracao da biblioteca para storage gerenciado

Quando a tabela `library_books` ainda estiver com `pdf_path` em `assets/docs/library/...` e `cover_image_path` em `assets/img/library-covers/...`, use:

```bash
composer migrate:library:storage
composer migrate:library:storage -- --apply
```

O comando copia PDFs e capas legados para os diretorios resolvidos por `LIBRARY_UPLOAD_DIR` e `LIBRARY_COVER_UPLOAD_DIR`, depois regrava `pdf_path` para `media/biblioteca/docs/...` e `cover_image_path` para `media/biblioteca/capas/...`.

No seu fluxo atual, como a producao recebe um banco exportado do ambiente de desenvolvimento, rode esse comando primeiro no desenvolvimento. Depois publique o banco corrigido e sincronize os arquivos fisicos para o storage da producao.

### Migracao da livraria para storage gerenciado

Quando a tabela `bookshop_books` ainda estiver com `cover_image_path` em `assets/img/bookshop-covers/...`, use:

```bash
composer migrate:bookshop:covers
composer migrate:bookshop:covers -- --apply
```

O comando copia as capas legadas para o diretorio resolvido por `BOOKSHOP_COVER_UPLOAD_DIR` e regrava `bookshop_books.cover_image_path` para `media/livraria/capas/...`.

No seu fluxo atual, como a producao recebe um banco exportado do ambiente de desenvolvimento, rode esse comando primeiro no desenvolvimento. Depois publique o banco corrigido e sincronize os arquivos fisicos para o storage da producao.

### Migracao das fotos de membros para storage gerenciado

Quando a tabela `member_users` ainda estiver com `profile_photo_path` em `assets/img/member-photos/...` ou `assets/img/avatar/...`, a correcao tem duas partes:

1. copiar os arquivos referenciados para o diretorio resolvido por `MEMBER_PROFILE_PHOTO_UPLOAD_DIR`
2. aplicar o patch SQL `2026-07-07-002-migrate-managed-member-photo-paths.sql`

O patch regrava `member_users.profile_photo_path` para `media/membros/fotos/...`.

Como a producao nao tem SSH no seu fluxo atual, a copia fisica dos arquivos legados deve ser feita pelo File Manager antes de executar o SQL no phpMyAdmin.

### Validacao rapida

Teste pelo menos uma URL de cada tipo de arquivo gerenciado:

```bash
curl -I https://cedern.org/media/membros/fotos/ARQUIVO_REAL.png
curl -I https://cedern.org/media/livraria/capas/ARQUIVO_REAL.jpg
curl -I https://cedern.org/media/biblioteca/docs/ARQUIVO_REAL.pdf
```

Resposta esperada:

- `200 OK` para arquivos existentes
- `404` apenas para arquivos realmente inexistentes

### Contorno temporario para fotos de membros

Se a producao estiver com URLs `media/membros/fotos/...` no banco, mas voce so tiver os arquivos em `public/assets/img/member-photos`, a aplicacao agora tenta localizar o mesmo nome de arquivo no storage legado. Isso ajuda como contingencia, mas o correto continua sendo manter a producao com os arquivos no diretorio gerenciado atual do ambiente.

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
