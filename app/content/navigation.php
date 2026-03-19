<?php

declare(strict_types=1);

return [
    'labels' => [
        'inicio' => 'Início',
        'quem-somos' => 'Quem Somos',
        'missao' => 'Missão',
        'valores' => 'Valores',
        'historia' => 'História',
        'estatuto' => 'Estatuto',
        'nossa-marca' => 'Nossa Marca',
        'gestao-cede' => 'Gestão CEDE',
        'estudos' => 'Estudos',
        'esde' => 'ESDE',
        'palestras' => 'Palestras',
        'atendimento-fraterno' => 'Atendimento Fraterno',
        'agenda' => 'Agenda',
        'estudo-do-evangelho' => 'Estudo do Evangelho',
        'palestra-publica' => 'Palestra Pública',
        'juventude-espirita' => 'Juventude Espírita',
        'faq' => 'FAQ',
        'biblioteca' => 'BIBLIOTECA',
        'doutrina' => 'Doutrina Espírita',
        'participacao' => 'Participação',
        'praticas' => 'Práticas da Casa',
        'politica-de-privacidade' => 'Política de Privacidade',
        'termos-de-uso' => 'Termos de Uso',
        'contato' => 'Contato',
    ],
    'menu' => [
        [
            'key' => 'quem-somos',
            'base' => '/quem-somos',
            'items' => [
                ['path' => '/quem-somos', 'label' => 'Visão geral'],
                ['path' => '/quem-somos/missao', 'key' => 'missao'],
                ['path' => '/quem-somos/valores', 'key' => 'valores'],
                ['path' => '/quem-somos/historia', 'key' => 'historia'],
                ['path' => '/quem-somos/estatuto', 'key' => 'estatuto'],
                ['path' => '/quem-somos/gestao-cede', 'key' => 'gestao-cede'],
            ],
        ],
        [
            'key' => 'estudos',
            'base' => '/estudos',
            'items' => [
                ['path' => '/estudos', 'label' => 'Visão geral'],
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
            'key' => 'faq',
            'base' => '/faq',
            'items' => [
                ['path' => '/faq', 'label' => 'Visão geral'],
                ['path' => '/faq/doutrina', 'key' => 'doutrina'],
                ['path' => '/faq/participacao', 'key' => 'participacao'],
                ['path' => '/faq/praticas', 'key' => 'praticas'],
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
            'path' => '#',
            'key' => 'biblioteca',
        ],
        [
            'path' => '/contato',
            'key' => 'contato',
        ],
    ],
];
