<?php

declare(strict_types=1);

return [
    'name' => 'CEDE',
    'legalName' => 'Centro de Estudos da Doutrina Espírita (CEDE)',
    'tagline' => 'Estudo, acolhimento fraterno e prática do bem à luz do Espiritismo.',
    'brand' => [
        'homeHref' => '/',
        'logoSrc' => '/assets/img/brands/cede4_logo.png',
        'logoAlt' => 'CEDE',
    ],
    'contact' => [
        'email' => 'cede@cedern.org',
        'address' => 'R. Frejó, 44 - Nova Parnamirim, Parnamirim - RN, 59150-663.',
        'mapUrl' => 'https://www.google.com/maps/search/?api=1&query=R.%20Frej%C3%B3%2C%2044%20-%20Nova%20Parnamirim%2C%20Parnamirim%20-%20RN%2C%2059150-663',
    ],
    'social' => [
        'instagram' => [
            'url' => 'https://www.instagram.com/cedeoficialrn/',
            'label' => 'Instagram oficial: @cedeoficialrn',
        ],
    ],
    'institutional' => [
        'statuteExcerpt' => 'O Centro de Estudos da Doutrina Espírita - CEDE, fundado em 09 de janeiro de 2001, '
            . 'é uma associação civil de caráter religioso, '
            . 'filosófico, científico, cultural e filantrópico, sem fins lucrativos, com sede e foro na cidade de '
            . 'Parnamirim/RN, localizada à Rua Frejó, nº 44, bairro Nova Parnamirim.',
        'contactSummary' => 'Fundado em 09 de janeiro de 2001, o CEDE é uma associação civil sem fins lucrativos '
            . 'com sede em Parnamirim/RN, à Rua Frejó, nº 44, bairro Nova Parnamirim.',
        'footerSummary' => 'Fundado em 09 de janeiro de 2001 • Associação civil sem fins lucrativos • '
            . 'Parnamirim/RN',
    ],
    'footer' => [
        'kicker' => 'Presença e acolhimento',
        'description' => 'O CEDE mantém suas portas abertas à comunidade, oferecendo estudo doutrinário, atendimento fraterno e convivência cristã, com seriedade, organização e acolhimento.',
        'navGroups' => [
            [
                'title' => 'Institucional',
                'links' => [
                    ['path' => '/', 'key' => 'inicio'],
                    ['path' => '/quem-somos', 'key' => 'quem-somos'],
                    ['path' => '/quem-somos/estatuto', 'key' => 'estatuto'],
                    ['path' => '/quem-somos/gestao-cede', 'key' => 'gestao-cede'],
                    ['path' => '/contato', 'key' => 'contato'],
                    ['path' => '/politica-de-privacidade', 'key' => 'politica-de-privacidade'],
                    ['path' => '/termos-de-uso', 'key' => 'termos-de-uso'],
                ],
            ],
            [
                'title' => 'Atividades',
                'links' => [
                    ['path' => '/estudos', 'key' => 'estudos'],
                    ['path' => '/agenda', 'key' => 'agenda'],
                    ['path' => '/faq', 'key' => 'faq'],
                ],
            ],
            [
                'title' => 'Loja',
                'links' => [
                    ['path' => '/loja', 'key' => 'loja'],
                    ['path' => '/loja/bazar', 'key' => 'bazar'],
                    ['path' => '/loja/livraria', 'key' => 'livraria'],
                ],
            ],
        ],
        'contactKicker' => 'Visite o CEDE',
        'contactTitle' => 'Fale conosco ou venha nos conhecer',
        'contactLead' => 'Acompanhe a agenda pública, envie sua mensagem ou trace sua rota até a casa.',
        'bottomNote' => 'Parnamirim/RN',
        'cnpj' => '04.242.556/0001-45',
    ],
];
