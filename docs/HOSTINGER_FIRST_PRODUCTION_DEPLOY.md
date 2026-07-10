# Primeira Publicacao em Producao na Hostinger

Este runbook define um unico procedimento para a primeira publicacao do CEDE
em producao, assumindo:

- codigo ainda nao publicado no dominio final;
- banco de producao ainda vazio;
- storage de producao ainda vazio;
- producao sem SSH;
- desenvolvimento com codigo, banco e uploads ja validados.

O objetivo e eliminar bifurcacoes. Se seguir esta ordem, a primeira publicacao
deve nascer com o mesmo contrato logico do desenvolvimento.

## Regra unica

Em producao, a fonte de verdade de uploads sera:

- `APP_MANAGED_STORAGE_ROOT="/home/u429418010/_cedern_storage"`

Os buckets canonicos sao:

- `/home/u429418010/_cedern_storage/bookshop/covers`
- `/home/u429418010/_cedern_storage/library/docs`
- `/home/u429418010/_cedern_storage/library/covers`
- `/home/u429418010/_cedern_storage/member-photos`
- `/home/u429418010/_cedern_storage/patrimony/docs`
- `/home/u429418010/_cedern_storage/patrimony/img`

Nao use como destino de producao:

- `public/assets/...`
- `public/assets/img/...`
- `public/assets/docs/...`
- `var/storage/...` dentro do projeto publicado

## Procedimento fechado

### 1. Publicar a release correta na branch de deploy

Se a Hostinger publica `main`, a branch `main` precisa conter a release atual.
Nao publique producao a partir de branch antiga.

Fluxo esperado no repositório:

```bash
git checkout cedern
git pull --ff-only
git checkout main
git merge --ff-only cedern
git push origin main
```

Se o merge fast-forward nao for possivel, pare e resolva isso antes do deploy.

### 2. Validar o estado do desenvolvimento

No desenvolvimento:

```bash
composer test
composer storage:audit
```

Resultado esperado:

- testes passando;
- `Arquivos ausentes: 0` para todos os buckets gerenciados.

### 3. Gerar os pacotes canonicos de storage

No desenvolvimento:

```bash
composer storage:package
```

Arquivos esperados em `var/exports/managed-storage-zips/`:

- `bookshop-covers.zip`
- `library-docs.zip`
- `library-covers.zip`
- `member-photos.zip`
- `patrimony-docs.zip`
- `patrimony-img.zip`

### 4. Exportar o banco baseline do desenvolvimento

Exporte o banco atual do desenvolvimento para um arquivo unico, por exemplo:

- `cedern-baseline.sql`

Esse dump baseline precisa sair do ambiente que ja passou no passo 2.

## 5. Publicar o codigo em producao

Na Hostinger, publique a branch `main` no diretorio:

- `/home/u429418010/domains/cedern.org/public_html`

Depois da publicacao, confirme que o projeto publicado contem:

- `public/index.php`
- `app/routes.php`
- `vendor/`
- `templates/`

## 6. Configurar o ambiente real de producao

Crie o arquivo real de ambiente fora do projeto e aponte `APP_ENV_FILE` para ele.

Bloco minimo obrigatorio:

```dotenv
APP_ENV=production
APP_BASE=""
APP_LOG_PATH="/home/u429418010/logs/cedern-app.log"
APP_ALLOW_REPOSITORY_FALLBACK="false"
APP_ASSET_VERSION="1"
APP_MANAGED_STORAGE_ROOT="/home/u429418010/_cedern_storage"
APP_DIAGNOSTIC_TOKEN="troque_este_token"

LIBRARY_UPLOAD_DIR="var/storage/library/docs"
LIBRARY_UPLOAD_PUBLIC_PREFIX="media/biblioteca/docs"
LIBRARY_COVER_UPLOAD_DIR="var/storage/library/covers"
LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX="media/biblioteca/capas"
BOOKSHOP_COVER_UPLOAD_DIR="var/storage/bookshop/covers"
BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX="media/livraria/capas"
MEMBER_PROFILE_PHOTO_UPLOAD_DIR="var/storage/member-photos"
MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX="media/membros/fotos"
PATRIMONY_DOCUMENT_UPLOAD_DIR="var/storage/patrimony/docs"
PATRIMONY_DOCUMENT_UPLOAD_PUBLIC_PREFIX="media/patrimonio/docs"
PATRIMONY_IMAGE_UPLOAD_DIR="var/storage/patrimony/img"
PATRIMONY_IMAGE_UPLOAD_PUBLIC_PREFIX="media/patrimonio/img"
```

Observacao operacional:

- `APP_ENABLE_DIAGNOSTIC_ROUTES` pode ficar `false`;
- com `APP_DIAGNOSTIC_TOKEN` definido, `/health/...` fica acessivel com token.

## 7. Criar o storage fisico de producao

No File Manager da Hostinger, crie exatamente estes diretorios:

- `/home/u429418010/_cedern_storage/bookshop/covers`
- `/home/u429418010/_cedern_storage/library/docs`
- `/home/u429418010/_cedern_storage/library/covers`
- `/home/u429418010/_cedern_storage/member-photos`
- `/home/u429418010/_cedern_storage/patrimony/docs`
- `/home/u429418010/_cedern_storage/patrimony/img`

Permissao alvo:

- diretorios: `755`

## 8. Importar o banco baseline em producao

No phpMyAdmin da producao:

1. selecione o banco vazio;
2. importe `cedern-baseline.sql`;
3. confirme que as tabelas foram criadas;
4. confirme que os caminhos salvos continuam em `media/...`.

## 9. Enviar os zips para os buckets corretos e extrair no lugar final

Mapeamento obrigatorio:

- `bookshop-covers.zip` -> `/home/u429418010/_cedern_storage/bookshop/covers`
- `library-docs.zip` -> `/home/u429418010/_cedern_storage/library/docs`
- `library-covers.zip` -> `/home/u429418010/_cedern_storage/library/covers`
- `member-photos.zip` -> `/home/u429418010/_cedern_storage/member-photos`
- `patrimony-docs.zip` -> `/home/u429418010/_cedern_storage/patrimony/docs`
- `patrimony-img.zip` -> `/home/u429418010/_cedern_storage/patrimony/img`

Regra de extracao:

- os arquivos finais devem ficar diretamente dentro do bucket;
- nao crie subpastas extras como `library-covers/library-covers/...`;
- apague os `.zip` depois da extracao.

## 10. Validar a instalacao antes de abrir o site

Teste estas URLs em producao:

- `https://cedern.org/health/readiness?token=SEU_TOKEN`
- `https://cedern.org/health/storage?token=SEU_TOKEN&kind=member_photos&file=ARQUIVO_REAL.jpg`
- `https://cedern.org/health/storage?token=SEU_TOKEN&kind=library_covers&file=ARQUIVO_REAL.png`
- `https://cedern.org/media/membros/fotos/ARQUIVO_REAL.jpg`
- `https://cedern.org/media/biblioteca/capas/ARQUIVO_REAL.png`
- `https://cedern.org/media/livraria/capas/ARQUIVO_REAL.jpg`

Resultado esperado:

- `health/readiness`: `200` ou `206`, nunca `404`;
- `health/storage`: deve listar `existing_matches`;
- URLs `/media/...`: `200` para arquivos existentes.

Depois disso, confira no navegador:

- `https://cedern.org/`
- `https://cedern.org/quem-somos/base-de-conhecimento`
- `https://cedern.org/quem-somos/gestao-cede`
- `https://cedern.org/loja/livraria`

## Fechamento

Se a primeira publicacao seguir este runbook, o contrato fica:

- banco grava `media/...`;
- producao le a partir de `APP_MANAGED_STORAGE_ROOT`;
- `public/assets/...` nao participa da instalacao;
- `var/storage/...` dentro do projeto nao participa da instalacao;
- diagnostico de producao fica disponivel por token;
- deploy futuro mexe em codigo, banco e storage de forma separada.

## Referencias

- [HOSTINGER_SHARED_DEPLOY.md](/var/www/cedern/docs/HOSTINGER_SHARED_DEPLOY.md)
- [MANAGED_MEDIA_STANDARD.md](/var/www/cedern/docs/MANAGED_MEDIA_STANDARD.md)
- [.env.production.example](/var/www/cedern/.env.production.example)
