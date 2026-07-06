<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Database;

use App\Infrastructure\Database\SqlStatementSplitter;
use PHPUnit\Framework\TestCase;

final class SqlStatementSplitterTest extends TestCase
{
    public function testSplitIgnoresCommentsAndKeepsSemicolonsInsideStrings(): void
    {
        $splitter = new SqlStatementSplitter();

        $statements = $splitter->split(<<<SQL
            -- comentario de linha
            CREATE TABLE sample (
                id INT PRIMARY KEY,
                name VARCHAR(80)
            );

            INSERT INTO sample (name) VALUES ('Maria; de Souza');
            # outro comentario
            /* comentario
               em bloco; */
            UPDATE sample
            SET name = 'Joao ''Jota'''
            WHERE id = 1;
        SQL);

        $this->assertCount(3, $statements);
        $this->assertStringStartsWith('CREATE TABLE sample', $statements[0]);
        $this->assertSame("INSERT INTO sample (name) VALUES ('Maria; de Souza')", $statements[1]);
        $this->assertStringContainsString("SET name = 'Joao ''Jota'''", $statements[2]);
    }

    public function testSplitReturnsEmptyArrayForCommentOnlySql(): void
    {
        $splitter = new SqlStatementSplitter();

        $statements = $splitter->split(<<<SQL
            -- apenas comentario
            # outro comentario
            /* bloco */
        SQL);

        $this->assertSame([], $statements);
    }
}
