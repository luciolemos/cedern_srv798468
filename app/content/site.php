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
    'footer' => [
        'kicker' => 'Presença e acolhimento',
        'description' => 'Estamos de portas abertas para estudo, atendimento fraterno e convivência cristã.',
        'navGroups' => [
            [
                'title' => 'Institucional',
                'links' => [
                    ['path' => '/', 'key' => 'inicio'],
                    ['path' => '/quem-somos', 'key' => 'quem-somos'],
                    ['path' => '/quem-somos/gestao-cede', 'key' => 'gestao-cede'],
                    ['path' => '/contato', 'key' => 'contato'],
                ],
            ],
            [
                'title' => 'Atividades',
                'links' => [
                    ['path' => '/estudos', 'key' => 'estudos'],
                    ['path' => '/agenda', 'key' => 'agenda'],
                    ['path' => '/faq', 'key' => 'faq'],
                    ['path' => '/quem-somos/nossa-marca', 'key' => 'nossa-marca'],
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
