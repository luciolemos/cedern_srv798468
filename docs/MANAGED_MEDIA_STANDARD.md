# Padrão de Mídia Gerenciada do CEDE

Documento principal de operacao:

- [PRODUCTION_OPERATIONS_RUNBOOK.md](/var/www/cedern/docs/PRODUCTION_OPERATIONS_RUNBOOK.md)

Este documento fixa o padrão para qualquer implementação nova que envolva banco,
upload, URL pública e storage físico de arquivos no projeto.

O objetivo é manter desenvolvimento e produção com a mesma regra lógica:

- banco grava caminhos lógicos públicos;
- a aplicação resolve a URL pública a partir desses caminhos;
- o storage físico fica fora do Git;
- a diferença entre ambientes fica concentrada na configuração do storage.

## Regra principal

Uploads reais não devem depender de `public/assets/...` como fonte de verdade.

O estado correto do sistema é:

- banco com paths `media/...`;
- arquivos no storage gerenciado;
- código resolvendo leitura e escrita por configuração.

Fallback legado pode existir como contingência, mas não deve ser a base de uma
implementação nova.

Quando um ambiente antigo ainda precisar ler `public/assets/...` durante uma
janela de migração, isso deve ser ativado explicitamente por
`APP_ENABLE_LEGACY_MEDIA_FALLBACK=true`. O padrão profissional para ambientes
novos e estáveis é manter essa flag desligada.

Essa flag vale para todos os buckets gerenciados do projeto. Sem ela, a
aplicacao nao deve depender de `public/assets/...` para membros, livraria,
biblioteca ou patrimonio.

## Contrato de banco

Para cada arquivo gerenciado, use este trio de colunas:

- `*_path`
- `*_mime_type`
- `*_size_bytes`

Exemplos já existentes:

- `member_users.profile_photo_path`
- `bookshop_books.cover_image_path`
- `library_books.pdf_path`
- `library_books.cover_image_path`
- `patrimony_assets.main_photo_path`
- `patrimony_assets.purchase_document_path`

O valor salvo em `*_path` deve ser um caminho lógico público, nunca um caminho
absoluto do servidor.

Exemplos corretos:

- `media/membros/fotos/member_20260709191607_15dd55e4.png`
- `media/livraria/capas/cover_20260326214748_aab9ede7.jpg`
- `media/biblioteca/docs/book_20260323120156_demo.pdf`
- `media/patrimonio/img/asset-photo_20260627182832_35f7845e.webp`

Exemplos incorretos:

- `/home/usuario/_cedern_storage/member-photos/member_demo.png`
- `/var/www/cedern/public/assets/img/member-photos/member_demo.png`

## Contrato de URL pública

Toda mídia gerenciada deve ser publicada sob `/media/...`.

Padrão:

- prefixo público: `media/<modulo>/<bucket>`
- rota pública: `/media/<modulo>/<bucket>/{file}`

Buckets já padronizados no projeto:

- `media/membros/fotos`
- `media/livraria/capas`
- `media/biblioteca/docs`
- `media/biblioteca/capas`
- `media/patrimonio/docs`
- `media/patrimonio/img`

## Contrato de storage físico

O diretório padrão do bucket deve seguir:

- `var/storage/<modulo>/<bucket>`

Quando `APP_MANAGED_STORAGE_ROOT` estiver definido, esse caminho relativo é
rebaseado para a raiz compartilhada do ambiente.

Exemplo:

- bucket lógico: `var/storage/member-photos`
- desenvolvimento: `/var/www/_cedern_storage/member-photos`
- produção: `/home/u429418010/_cedern_storage/member-photos`

Se o ambiente exigir mais previsibilidade, o bucket pode ser configurado com
caminho absoluto direto no `.env`.

## Importação baseline por ZIP

Quando a producao nascer vazia e a administracao do servidor nao tiver SSH, o
baseline de arquivos deve entrar pelo proprio PHP da aplicacao.

Fluxo padrao:

- gerar os pacotes com `composer storage:package` no ambiente de origem;
- definir `APP_MANAGED_STORAGE_IMPORT_ARCHIVE_DIR` no ambiente de destino quando
  a operacao precisar ter uma origem unica e explicita dos `.zip`;
- enviar os `.zip` para `<APP_MANAGED_STORAGE_ROOT>/imports/managed-storage-zips`;
- validar a descoberta em `/health/storage/import?token=...`;
- executar a importacao real em `/health/storage/import?token=...&execute=1&kind=all`.

Resolucao de origem dos `.zip`:

- se `APP_MANAGED_STORAGE_IMPORT_ARCHIVE_DIR` estiver definido, ele vira a
  origem exclusiva dos `.zip`;
- o importador nao depende de uma unica pasta fixa;
- ele procura os arquivos nesta ordem:
  `1.` `<APP_MANAGED_STORAGE_ROOT>/imports/managed-storage-zips`
  `2.` `<APP_MANAGED_STORAGE_ROOT>/imports`
  `3.` `<project_root>/var/imports/managed-storage-zips`
  `4.` `<project_root>/var/imports`
  `5.` `<project_root>/var/exports/managed-storage-zips`
- a fonte real usada em cada execucao e a que aparece em `selected_archive` no
  JSON de `/health/storage/import`.

Regra operacional:

- antes de executar a importacao, sempre confira `selected_archive`;
- se o runtime do PHP nao enxergar os `.zip` em `<APP_MANAGED_STORAGE_ROOT>/imports/...`,
  ele pode cair para `var/exports/managed-storage-zips` dentro do projeto;
- limpeza posterior deve remover os `.zip` do local que apareceu em `selected_archive`,
  e nao apenas do staging presumido.
- depois da importacao, o JSON de execucao devolve `post_import_snapshot` para
  confirmar que o mesmo runtime do PHP enxerga o bucket e os arquivos esperados.

Regra importante:

- o `.zip` deve conter os arquivos diretamente na raiz do bucket;
- o importador rejeita entradas com subpastas extras para evitar layouts como `bookshop-covers/bookshop-covers/...`.

## Convenção de variáveis de ambiente

Cada bucket novo deve expor:

- `<MODULO>_UPLOAD_DIR`
- `<MODULO>_UPLOAD_PUBLIC_PREFIX`

Exemplos já existentes:

- `MEMBER_PROFILE_PHOTO_UPLOAD_DIR`
- `MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX`
- `BOOKSHOP_COVER_UPLOAD_DIR`
- `BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX`
- `LIBRARY_UPLOAD_DIR`
- `LIBRARY_UPLOAD_PUBLIC_PREFIX`
- `LIBRARY_COVER_UPLOAD_DIR`
- `LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX`
- `PATRIMONY_DOCUMENT_UPLOAD_DIR`
- `PATRIMONY_DOCUMENT_UPLOAD_PUBLIC_PREFIX`
- `PATRIMONY_IMAGE_UPLOAD_DIR`
- `PATRIMONY_IMAGE_UPLOAD_PUBLIC_PREFIX`

## Componentes de código a reutilizar

Não implemente resolução de path e URL “na mão” em cada módulo.

Use o que já existe:

- [ManagedUploadStorage.php](/var/www/cedern/src/Support/ManagedUploadStorage.php): resolve diretórios físicos e prepara escrita.
- [ManagedPublicMediaPath.php](/var/www/cedern/src/Support/ManagedPublicMediaPath.php): normaliza path lógico e monta URL pública.
- [ManagedMediaLocator.php](/var/www/cedern/src/Support/ManagedMediaLocator.php): localiza o arquivo físico a partir do path salvo.

Fluxo esperado:

1. upload chega na action;
2. a action resolve o diretório com `ManagedUploadStorage`;
3. grava o arquivo;
4. salva no banco o path lógico `media/...`;
5. o repositório ou helper converte esse path em URL pública;
6. a rota `/media/...` lê o arquivo usando `ManagedMediaLocator`.

## Checklist para módulo novo com arquivos

1. Criar colunas `*_path`, `*_mime_type`, `*_size_bytes`.
2. Definir prefixo público `media/<modulo>/<bucket>`.
3. Definir diretório padrão `var/storage/<modulo>/<bucket>`.
4. Criar chaves de ambiente `*_UPLOAD_DIR` e `*_UPLOAD_PUBLIC_PREFIX`.
5. Gravar o arquivo com `ManagedUploadStorage`.
6. Salvar no banco apenas o path lógico.
7. Expor a rota pública `/media/...`.
8. Ler o arquivo com `ManagedMediaLocator`.
9. Montar a URL com `ManagedPublicMediaPath::toUrl()`.
10. Cobrir o fluxo com teste.
11. Validar o bucket com `composer storage:audit`.

## Regra operacional de deploy

No primeiro deploy de um ambiente novo, publique juntos:

- código;
- banco;
- storage gerenciado correspondente.

Depois do go-live:

- código sobe por Git/webhook;
- banco evolui por patch SQL;
- uploads são sincronizados apenas quando a release depender de novos arquivos.

## Diagnóstico operacional

Quando houver diferença entre desenvolvimento e produção, valide primeiro qual
root físico está realmente ativo.

- `composer storage:audit`
- `composer storage:probe -- --kind member_photos --file member_arquivo.jpg`
- `composer storage:probe -- --kind patrimony_images --file asset_arquivo.webp`

Regra prática:

- `APP_MANAGED_STORAGE_ROOT` definido: `var/storage/...` é rebaseado para o root compartilhado.
- `APP_MANAGED_STORAGE_ROOT` definido: a leitura canônica também passa a ocorrer nesse root compartilhado; `var/storage/...` dentro do release deixa de ser fallback implícito.
- `APP_MANAGED_STORAGE_ROOT` vazio ou comentado: a aplicação lê de `var/storage/...` dentro do projeto.

Se um ambiente funcionar com a variável comentada e falhar com ela ativa, isso
significa que existem dois storages físicos competindo entre si, com conteúdo
diferente.

## O que não fazer

- não salvar caminho absoluto do servidor no banco;
- não criar implementação nova dependente de `assets/...`;
- não tratar storage de um módulo diferente dos outros sem justificativa técnica;
- não subir código sem levar junto o storage correspondente quando o ambiente nasce vazio;
- não deixar o comportamento de produção depender de fallback legado como regra normal.

## Referências

- Primeira publicacao em producao: [HOSTINGER_FIRST_PRODUCTION_DEPLOY.md](/var/www/cedern/docs/HOSTINGER_FIRST_PRODUCTION_DEPLOY.md)
- Deploy e storage compartilhado: [HOSTINGER_SHARED_DEPLOY.md](/var/www/cedern/docs/HOSTINGER_SHARED_DEPLOY.md)
- Configuração de ambiente: [ENVIRONMENT_CONFIGURATION.md](/var/www/cedern/docs/ENVIRONMENT_CONFIGURATION.md)
- Bootstrap e resolução de env: [public/index.php](/var/www/cedern/public/index.php)
