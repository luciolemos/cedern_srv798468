# Runbook Operacional de Producao

Este e o ponto de entrada oficial para operar o CEDE em producao.

Use este documento para quatro cenarios:

- primeira publicacao totalmente limpa;
- release normal apos o go-live;
- importacao baseline de midia gerenciada;
- aplicacao de patches SQL sem SSH.

## Contrato operacional

O ambiente de producao deve obedecer sempre a este contrato:

- codigo publica por Git/webhook;
- banco evolui por schema base inicial mais patches SQL incrementais;
- uploads vivem fora do repositorio;
- banco grava caminhos logicos `media/...`;
- leitura e escrita de arquivos usam o storage gerenciado;
- `public/assets/...` nao e fonte de verdade;
- `var/storage/...` dentro do release nao e fonte de verdade quando `APP_MANAGED_STORAGE_ROOT` estiver ativo.

Raiz canonica atual de producao:

- `APP_MANAGED_STORAGE_ROOT="/home/u429418010/_cedern_storage"`

## Mapa canonico dos buckets

| Kind | Prefixo publico | Diretorio canonico |
| --- | --- | --- |
| `bookshop_covers` | `media/livraria/capas` | `/home/u429418010/_cedern_storage/bookshop/covers` |
| `library_docs` | `media/biblioteca/docs` | `/home/u429418010/_cedern_storage/library/docs` |
| `library_covers` | `media/biblioteca/capas` | `/home/u429418010/_cedern_storage/library/covers` |
| `member_photos` | `media/membros/fotos` | `/home/u429418010/_cedern_storage/member-photos` |
| `patrimony_docs` | `media/patrimonio/docs` | `/home/u429418010/_cedern_storage/patrimony/docs` |
| `patrimony_images` | `media/patrimonio/img` | `/home/u429418010/_cedern_storage/patrimony/img` |

## Fluxo A: primeira publicacao limpa

Use este fluxo quando o dominio final ainda nao tem banco real em uso e o
storage de producao ainda vai nascer.

### 1. Validar a release de origem no desenvolvimento

No ambiente de desenvolvimento:

```bash
composer test
composer storage:audit
composer db:migrate
```

Resultado esperado:

- testes passando;
- `Arquivos ausentes: 0` em todos os buckets;
- nenhum patch SQL pendente antes de gerar o baseline.

### 2. Gerar baseline de midia

No desenvolvimento:

```bash
composer storage:package
```

Arquivos esperados:

- `bookshop-covers.zip`
- `library-docs.zip`
- `library-covers.zip`
- `member-photos.zip`
- `patrimony-docs.zip`
- `patrimony-img.zip`

### 3. Gerar baseline do banco

Exporte do desenvolvimento o banco que acabou de passar pela validacao.

Regra:

- o dump baseline deve sair do mesmo estado do codigo e da midia empacotada.

### 4. Publicar o codigo da release

Publique a branch correta em:

- `/home/u429418010/domains/cedern.org/public_html`

Confirme no release publicado:

- `public/index.php`
- `app/routes.php`
- `vendor/`
- `templates/`

### 5. Configurar o ambiente real

Configure o arquivo real apontado por `APP_ENV_FILE` com pelo menos:

```dotenv
APP_ENV=production
APP_BASE=""
APP_LOG_PATH="/home/u429418010/logs/cedern-app.log"
APP_ALLOW_REPOSITORY_FALLBACK="false"
APP_MANAGED_STORAGE_ROOT="/home/u429418010/_cedern_storage"
APP_DIAGNOSTIC_TOKEN="troque_este_token"
```

Mantenha os buckets com prefixos publicos `media/...` e paths relativos
`var/storage/...` no `.env`. Com `APP_MANAGED_STORAGE_ROOT` ativo, o runtime
rebate esses caminhos para `/_cedern_storage/...`.

### 6. Criar o storage fisico

Crie com permissao `755`:

- `/home/u429418010/_cedern_storage/bookshop/covers`
- `/home/u429418010/_cedern_storage/library/docs`
- `/home/u429418010/_cedern_storage/library/covers`
- `/home/u429418010/_cedern_storage/member-photos`
- `/home/u429418010/_cedern_storage/patrimony/docs`
- `/home/u429418010/_cedern_storage/patrimony/img`

### 7. Importar o banco baseline

No phpMyAdmin da producao:

1. importe o dump baseline;
2. confirme que os registros continuam apontando para `media/...`;
3. confirme que a tabela `schema_migrations` existe ou sera criada pelo fluxo de patches.

### 8. Enviar os `.zip` de midia

Envie os `.zip` preferencialmente para:

- `/home/u429418010/_cedern_storage/imports/managed-storage-zips`

### 9. Fazer o PHP descobrir a origem real dos `.zip`

Abra:

- `https://cedern.org/health/storage/import?token=SEU_TOKEN`

Confira no JSON:

- `selected_archive`
- `target_directory`
- `missing_archives`

Regra operacional:

- a verdade nao e a pasta vista no File Manager;
- a verdade e o `selected_archive` que o PHP informa.

Ordem atual de busca:

1. `<APP_MANAGED_STORAGE_ROOT>/imports/managed-storage-zips`
2. `<APP_MANAGED_STORAGE_ROOT>/imports`
3. `<project_root>/var/imports/managed-storage-zips`
4. `<project_root>/var/imports`
5. `<project_root>/var/exports/managed-storage-zips`

### 10. Executar a importacao de midia

Quando o relatorio estiver coerente, execute:

- `https://cedern.org/health/storage/import?token=SEU_TOKEN&execute=1&kind=all`

Se quiser limpar os `.zip` usados ao final:

- `https://cedern.org/health/storage/import?token=SEU_TOKEN&execute=1&kind=all&delete_after=1`

Depois da execucao, confira em cada bucket importado:

- `post_import_snapshot.directory`
- `post_import_snapshot.file_count`
- `post_import_snapshot.visible_expected_file_count`
- `post_import_snapshot.missing_expected_file_count`

Regra pratica:

- `missing_expected_file_count=0` confirma que o mesmo PHP que importou tambem
  enxerga os arquivos no bucket canonico imediatamente apos a operacao.

### 11. Aplicar patches SQL pendentes

Valide:

- `https://cedern.org/health/migrations?token=SEU_TOKEN`

Se houver `pending_count > 0`, execute:

- `https://cedern.org/health/migrations?token=SEU_TOKEN&execute=1`

### 12. Validar prontidao e smoke check

Valide:

- `https://cedern.org/health/readiness?token=SEU_TOKEN`
- `https://cedern.org/media/livraria/capas/ARQUIVO_REAL.jpg`
- `https://cedern.org/media/biblioteca/capas/ARQUIVO_REAL.png`
- `https://cedern.org/media/membros/fotos/ARQUIVO_REAL.jpg`
- `https://cedern.org/media/patrimonio/img/ARQUIVO_REAL.webp`
- `https://cedern.org/media/patrimonio/docs/ARQUIVO_REAL.pdf`

Depois valide no navegador:

- `https://cedern.org/`
- `https://cedern.org/loja/livraria`
- `https://cedern.org/quem-somos/base-de-conhecimento`
- `https://cedern.org/quem-somos/gestao-cede`

### 13. Limpeza

Depois da importacao bem-sucedida:

- remova os `.zip` do local indicado em `selected_archive`;
- remova copias redundantes em outros stagings, se existirem;
- mantenha apenas as pastas `imports/` e `imports/managed-storage-zips`.

## Fluxo B: release normal apos o go-live

Depois que producao passa a receber dados reais, o fluxo muda.

### Ordem segura

1. fazer backup do banco de producao;
2. publicar o codigo da release;
3. validar `health/migrations`;
4. aplicar apenas os patches novos;
5. importar midia apenas se a release depender de novos arquivos fisicos;
6. rodar `health/readiness`;
7. validar os fluxos criticos no navegador.

Regra:

- nunca substituir o banco inteiro de producao por dump do desenvolvimento depois do go-live.

## Quando File Manager e PHP discordam

Se o File Manager mostrar o arquivo, mas o site ou `/health/storage` retornar
que ele nao existe, o diagnostico deve seguir esta ordem:

1. confirmar o nome exato do arquivo salvo no banco;
2. consultar `/health/storage?token=SEU_TOKEN&kind=...&file=...`;
3. verificar `resolved_directory`, `resolved_file_path` e `existing_matches`;
4. validar `selected_archive` em `/health/storage/import`;
5. confirmar se o PHP esta olhando para `/_cedern_storage/...` ou para outro staging.

Regra profissional:

- o runtime do PHP e a fonte de verdade para diagnostico;
- o File Manager sozinho nao prova que o arquivo esta no path canonicamente visivel pela aplicacao.

## O que nao fazer

- nao usar `public/assets/...` como storage ativo;
- nao depender de `var/storage/...` dentro do release quando `APP_MANAGED_STORAGE_ROOT` estiver ativo;
- nao extrair `.zip` manualmente como fluxo padrao se `/health/storage/import` estiver disponivel;
- nao aplicar patch SQL em producao sem backup;
- nao editar patch SQL ja aplicado em outro ambiente.

## Documentos especializados

- primeira publicacao detalhada: [HOSTINGER_FIRST_PRODUCTION_DEPLOY.md](/var/www/cedern/docs/HOSTINGER_FIRST_PRODUCTION_DEPLOY.md)
- deploy shared e operacao recorrente: [HOSTINGER_SHARED_DEPLOY.md](/var/www/cedern/docs/HOSTINGER_SHARED_DEPLOY.md)
- padrao tecnico de midia: [MANAGED_MEDIA_STANDARD.md](/var/www/cedern/docs/MANAGED_MEDIA_STANDARD.md)
- patches SQL: [DB_SQL_PATCHES.md](/var/www/cedern/docs/DB_SQL_PATCHES.md)
- configuracao de ambiente: [ENVIRONMENT_CONFIGURATION.md](/var/www/cedern/docs/ENVIRONMENT_CONFIGURATION.md)
- template de ambiente: [.env.production.example](/var/www/cedern/.env.production.example)
