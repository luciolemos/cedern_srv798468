# Patches SQL e Deploy de Banco

Este projeto agora possui um fluxo simples de migrations baseado em patches SQL versionados.
Ele foi pensado para o cenário atual do CEDE:

- desenvolvimento em `https://srv798468.hstgr.cloud/cedern/`
- produção em `https://cedern.org/`
- sem homologação separada, por enquanto

## Objetivo

Parar de substituir o banco inteiro de produção por um dump do ambiente de desenvolvimento.

O fluxo correto passa a ser:

- código sobe por Git/Webhook
- banco evolui por patches SQL pequenos
- dados reais continuam nascendo e permanecendo em produção

Existe uma exceção controlada:

- antes do go-live, quando a produção ainda está vazia, pode existir uma `carga inicial`
  vinda do desenvolvimento;
- depois do go-live, esse padrão deve parar.

## Estrutura

- patches incrementais: `database/patches/*.sql`
- comando de status/aplicação: `php scripts/migrate.php`
- tabela de controle: `schema_migrations`

## Convenção de nome

Use nomes ordenáveis:

```text
2026-07-06-001-create-courses.sql
2026-07-06-002-create-course-enrollments.sql
2026-07-07-001-add-course-status-index.sql
```

## Regras importantes

1. Não edite um patch já aplicado em outro ambiente.
2. Se precisar corrigir algo, crie um novo patch.
3. Faça backup do banco de produção antes de aplicar qualquer patch.
4. Se a feature depende do novo schema, aplique o patch antes do deploy do código.

## Comandos

Verificar status:

```bash
php scripts/migrate.php
```

ou:

```bash
composer db:migrate
```

Aplicar patches pendentes:

```bash
php scripts/migrate.php --apply
```

ou:

```bash
composer db:migrate -- --apply
```

## Fluxo recomendado sem homologação

### 1. Desenvolvimento

1. Criar ou ajustar o código no ambiente de desenvolvimento.
2. Criar o patch SQL necessário em `database/patches/`.
3. Aplicar o patch no banco de desenvolvimento.
4. Validar a funcionalidade completa.

### 2. Publicação em produção

1. Fazer backup do banco de produção.
2. Aplicar em produção exatamente os mesmos patches testados em desenvolvimento.
3. Fazer deploy da `main`.
4. Rodar smoke check.
5. Validar as rotas e fluxos críticos.

## Primeira carga de produção

Quando a produção ainda não existe como banco em uso real, há dois caminhos válidos.

### Opção A: bootstrap pelo schema versionado

Use quando a produção deve nascer limpa, com estrutura base e dados mínimos institucionais.

1. Criar o banco vazio.
2. Importar, nesta ordem recomendada:
   - `database/schema/agenda.sql`
   - `database/schema/library.sql`
   - `database/schema/bookshop.sql`
   - `database/schema/patrimony.sql`
3. Aplicar os patches de `database/patches/`.
4. Criar o primeiro usuário admin.
5. Validar a aplicação completa.

### Opção B: baseline inicial exportada do desenvolvimento

Use apenas antes do go-live, quando você quer nascer com dados iniciais já montados no desenvolvimento.

Exemplos aceitáveis nessa baseline:

- acervo da biblioteca;
- catálogo da livraria;
- conteúdo institucional;
- usuários administrativos controlados;
- configurações de gestão já preparadas.

Regra:
essa importação é um evento de `bootstrap inicial`, não um fluxo recorrente de deploy.

## Depois que produção entra em uso

A partir do momento em que produção começa a receber:

- cadastros reais;
- alterações administrativas reais;
- cobranças;
- inscrições;
- uploads feitos pelos usuários;

o banco de produção passa a ser a fonte de verdade.

Nesse ponto, o fluxo correto é:

1. manter os dados reais em produção;
2. subir código por Git/webhook;
3. aplicar patches incrementais;
4. nunca sobrescrever o banco inteiro com dump do desenvolvimento.

## Aplicacao manual via phpMyAdmin

Quando a producao nao tiver SSH, o patch ainda pode ser aplicado manualmente:

1. Fazer backup do banco de producao.
2. Abrir o arquivo SQL do patch em `database/patches/`.
3. Copiar o conteudo e executar na aba `SQL` do phpMyAdmin da producao.
4. Se quiser manter o controle de `schema_migrations` consistente com o fluxo automatizado, criar a tabela de controle se necessario e registrar manualmente o patch aplicado com a `migration_key` e o `checksum_sha256` do arquivo versionado.

Estrutura da tabela de controle:

```sql
CREATE TABLE IF NOT EXISTS schema_migrations (
    migration_key VARCHAR(190) NOT NULL PRIMARY KEY,
    checksum_sha256 CHAR(64) NOT NULL,
    applied_at DATETIME NOT NULL,
    KEY idx_schema_migrations_applied_at (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Registro manual do patch:

```sql
INSERT INTO schema_migrations (migration_key, checksum_sha256, applied_at)
VALUES ('NOME_DO_PATCH', 'CHECKSUM_SHA256_DO_ARQUIVO', NOW())
ON DUPLICATE KEY UPDATE
    checksum_sha256 = VALUES(checksum_sha256),
    applied_at = applied_at;
```

## Ordem segura de deploy

Quando a mudança altera schema:

1. `backup produção`
2. `patch no banco de produção`
3. `deploy do código`
4. `smoke check`
5. `validação funcional`

Quando a mudança não altera schema:

1. `deploy do código`
2. `smoke check`
3. `validação funcional`

## Cenário Hostinger sem SSH em produção

No seu fluxo atual, o equivalente prático é:

1. manter o patch `.sql` versionado em `database/patches/`;
2. testar esse patch no desenvolvimento;
3. fazer backup do banco de produção no phpMyAdmin;
4. executar manualmente o mesmo patch no phpMyAdmin da produção;
5. publicar o código pela branch `main`;
6. validar o resultado.

Se a release não tiver mudança de banco, pule apenas a etapa do patch.

## Observações sobre baseline

Os arquivos em `database/schema/*.sql` continuam sendo a base histórica do projeto.
Os patches em `database/patches/*.sql` registram apenas mudanças incrementais a partir da adoção deste fluxo.

Ou seja:

- para um banco já existente, basta aplicar os patches novos;
- para um ambiente totalmente novo, pode ser necessário primeiro bootstrapar o schema base e depois aplicar os patches incrementais.
