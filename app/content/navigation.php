<?php

declare(strict_types=1);

return [
    'labels' => [
        'inicio' => 'Início',
        'quem-somos' => 'Quem Somos',
        'missao' => 'Missão',
        'valores' => 'Valores',
        'historia' => 'História',
        'fundador' => 'Fundador',
        'estatuto' => 'Estatuto',
        'nossa-marca' => 'Nossa Marca',
        'gestao-cede' => 'Gestão CEDE',
        'estudos' => 'Estudos',
        'esde' => 'ESDE',
        'eade' => 'EADE',
        'palestras' => 'Palestras',
        'atendimento-fraterno' => 'Atendimento Fraterno',
        'agenda' => 'Agenda',
        'estudo-do-evangelho' => 'Estudo do Evangelho',
        'palestra-publica' => 'Palestra Pública',
        'juventude-espirita' => 'Juventude Espírita',
        'loja' => 'LOJA',
        'bazar' => 'BAZAR',
        'livraria' => 'Livraria Auta de Sousa',
        'livraria-ii' => 'Livraria Auta de Sousa',
        'livraria-auta-de-sousa' => 'Livraria Auta de Sousa',
        'faq' => 'FAQ',
        'biblioteca' => 'Base de conhecimento',
        'base-de-conhecimento' => 'Base de conhecimento',
        'doutrina' => 'Doutrina Espírita',
        'participacao' => 'Participação',
        'praticas' => 'Práticas da Casa',
        'politica-de-privacidade' => 'Política de Privacidade',
        'dados-de-acesso' => 'Dados de acesso',
        'termos-de-uso' => 'Termos de Uso',
        'contato' => 'Contato',
    ],
    'menu' => [
        [
            'key' => 'quem-somos',
            'base' => '/quem-somos',
            'items' => [
                ['path' => '/quem-somos', 'label' => 'Visão geral'],
                ['path' => '/quem-somos/historia', 'key' => 'historia'],
                ['path' => '/quem-somos/missao', 'key' => 'missao'],
                ['path' => '/quem-somos/valores', 'key' => 'valores'],
                ['path' => '/quem-somos/fundador', 'key' => 'fundador'],
                ['path' => '/quem-somos/nossa-marca', 'key' => 'nossa-marca'],
                ['path' => '/quem-somos/gestao-cede', 'key' => 'gestao-cede'],
                ['path' => '/quem-somos/estatuto', 'key' => 'estatuto'],
                ['path' => '/quem-somos/base-de-conhecimento', 'key' => 'biblioteca'],
            ],
        ],
        [
            'key' => 'estudos',
            'base' => '/estudos',
            'items' => [
                ['path' => '/estudos', 'label' => 'Visão geral'],
                ['path' => '/estudos/eade', 'key' => 'eade'],
                ['path' => '/estudos/esde', 'key' => 'esde'],
                ['path' => '/estudos/palestras', 'key' => 'palestras'],
                ['path' => '/estudos/atendimento-fraterno', 'key' => 'atendimento-fraterno'],
            ],
        ],
        [
            'key' => 'agenda',
            'base' => '/agenda',
            'items' => [
                ['path' => '/agenda', 'label' => 'Visão geral'],
                ['path' => '/agenda/estudo-do-evangelho', 'key' => 'estudo-do-evangelho'],
                ['path' => '/agenda/palestra-publica', 'key' => 'palestra-publica'],
                ['path' => '/agenda/juventude-espirita', 'key' => 'juventude-espirita'],
            ],
        ],
        [
            'key' => 'loja',
            'base' => '/loja',
            'items' => [
                ['path' => '/loja', 'label' => 'Visão geral'],
                ['path' => '/loja/livraria', 'label' => 'Livraria Auta de Sousa'],
                ['path' => '/loja/bazar', 'key' => 'bazar'],
            ],
        ],
    ],
    'links_before_groups' => [
        [
            'path' => '/',
            'key' => 'inicio',
        ],
    ],
    'links_after_groups' => [
        [
            'path' => '/contato',
            'key' => 'contato',
        ],
    ],
];
