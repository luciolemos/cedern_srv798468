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

Observação:

- `database/schema/*.sql` continua servindo como bootstrap/base histórica;
- `database/patches/*.sql` passa a registrar apenas as mudanças incrementais daqui em diante.
