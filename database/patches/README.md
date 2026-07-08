# Patches SQL

Use esta pasta para mudanças incrementais de banco a partir da adoção do fluxo de migrations.

Regras:

- um arquivo `.sql` por mudança lógica;
- nomear com prefixo ordenável, por exemplo `2026-07-06-001-create-courses.sql`;
- nunca editar o conteúdo de um patch já aplicado em outro ambiente;
- criar um novo patch para corrigir ou complementar um patch anterior.

Fluxo:

1. criar o patch;
2. rodar `php scripts/migrate.php` para conferir o status;
3. rodar `php scripts/migrate.php --apply` no ambiente desejado;
4. publicar o código depois do banco, quando a mudança depender do novo schema.

Primeira publicação em produção:

- se a produção ainda estiver vazia, você pode primeiro bootstrapar o banco base por
  `database/schema/*.sql` ou importar uma baseline inicial controlada;
- depois que a produção entrar em uso real, pare de substituir o banco inteiro e
  trabalhe apenas com patches incrementais.

Observação:

- `database/schema/*.sql` continua servindo como bootstrap/base histórica;
- `database/patches/*.sql` passa a registrar apenas as mudanças incrementais daqui em diante.
- detalhes operacionais estão em `docs/DB_SQL_PATCHES.md`.
