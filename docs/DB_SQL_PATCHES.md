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

## Observações sobre baseline

Os arquivos em `database/schema/*.sql` continuam sendo a base histórica do projeto.
Os patches em `database/patches/*.sql` registram apenas mudanças incrementais a partir da adoção deste fluxo.

Ou seja:

- para um banco já existente, basta aplicar os patches novos;
- para um ambiente totalmente novo, pode ser necessário primeiro bootstrapar o schema base e depois aplicar os patches incrementais.
