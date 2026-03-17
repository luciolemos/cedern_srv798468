<?php

declare(strict_types=1);

return [
    'sections' => [
        'hero' => [
            'kicker' => 'Espiritualidade com Razão',
            'badge' => 'Sejam bem-vindos ao CEDE',
            'title' => 'Centro de Estudos da Doutrina Espírita',
            'lead' => 'Promovendo o estudo, a prática e a difusão do Espiritismo com base nas obras de Allan Kardec, buscando o progresso moral e intelectual do ser humano.',
            'actionsDelay' => 140,
            'panelTitle' => 'Nossas Atividades',
            'panelDelay' => 180,
        ],
        'features' => [
            'kicker' => 'Estudos',
            'title' => 'Iluminando consciências através do conhecimento',
            'lead' => 'Oferecemos grupos de estudo sistematizado, palestras públicas e atendimento fraterno para todos que buscam compreender a vida além da matéria.',
        ],
        'socialProof' => [
            'kicker' => 'Quem Somos',
            'title' => 'Centro de Estudos da Doutrina Espírita (CEDE)',
            'lead' => 'O Centro de Estudos da Doutrina Espírita (CEDE) é uma instituição filantrópica dedicada ao estudo sistemático, à prática e à divulgação da Doutrina Espírita, fundamentada nas obras de Allan Kardec.',
            'leadSecondary' => 'Por meio de atividades de estudo, reflexão, acolhimento e assistência fraterna, buscamos contribuir para o desenvolvimento espiritual do indivíduo, incentivar valores como caridade, fraternidade e responsabilidade moral, além de oferecer apoio e orientação a todos que buscam compreensão, consolo e crescimento interior.',
            'trustGridLabel' => 'Frentes de trabalho',
        ],
        'roadmap' => [
            'kicker' => 'Agenda',
            'title' => 'Cronograma Semanal de Atividades',
            'lead' => 'Confira nossos horários de reuniões públicas, estudos e assistência espiritual. Todas as atividades são gratuitas.',
        ],
        'faq' => [
            'kicker' => 'Dúvidas',
            'title' => 'Perguntas Frequentes',
            'lead' => 'Entenda melhor sobre o Espiritismo, nossas atividades e como você pode participar e contribuir.',
        ],
        'donation' => [
            'kicker' => 'Apoie o CEDE',
            'title' => 'Faça sua doação',
            'lead' => 'Sua contribuição ajuda a manter os estudos, atendimentos fraternos e ações de acolhimento realizados pelo CEDE.',
            'leadSecondary' => 'Escolha abaixo a forma de doação mais conveniente para você.',
        ],
        'cta' => [
            'kicker' => 'Participe',
            'title' => 'Junte-se a nós nesta jornada de luz',
            'lead' => 'Venha conhecer o CEDE e encontrar respostas para suas indagações espirituais em um ambiente acolhedor e fraterno.',
            'actionsDelay' => 160,
        ],
    ],
    'heroActions' => [
        [
            'label' => 'Ver Horários',
            'href' => '/agenda',
            'class' => 'nc-btn nc-btn-primary',
            'loadingOnClick' => false,
        ],
        [
            'label' => 'Falar Conosco',
            'href' => '/contato',
            'class' => 'nc-btn nc-btn-secondary',
            'loadingOnClick' => false,
        ],
    ],
    'heroMetrics' => [
        [
            'value' => '100%',
            'label' => 'Gratuito',
            'delay' => 220,
        ],
        [
            'value' => '5 Obras',
            'label' => 'Pentateuco Kardequiano',
            'delay' => 280,
        ],
        [
            'value' => '3 Grupos',
            'label' => 'Estudo Sistematizado',
            'delay' => 340,
        ],
        [
            'value' => 'Semanal',
            'label' => 'Palestras Públicas',
            'delay' => 400,
        ],
    ],
    'featuresItems' => [
        [
            'title' => 'Estudo Sistematizado (ESDE)',
            'description' => 'Grupos dedicados ao estudo aprofundado das obras básicas, promovendo o entendimento racional da fé.',
            'href' => '/estudos/esde',
            'linkLabel' => 'Ver detalhes do ESDE',
            'delay' => 120,
        ],
        [
            'title' => 'Atendimento Fraterno',
            'description' => 'Acolhimento individualizado para ouvir, orientar e consolar à luz da Doutrina Espírita com total sigilo.',
            'href' => '/estudos/atendimento-fraterno',
            'linkLabel' => 'Ver como funciona',
            'delay' => 180,
        ],
        [
            'title' => 'Palestras Públicas',
            'description' => 'Explanações semanais sobre temas atuais sob a ótica espírita, abertas ao público em geral.',
            'href' => '/estudos/palestras',
            'linkLabel' => 'Ver programação',
            'delay' => 240,
        ],
    ],
    'studiesPages' => [
        'esde' => [
            'kicker' => 'Estudos',
            'title' => 'Estudo Sistematizado da Doutrina Espírita (ESDE)',
            'lead' => 'Formação contínua baseada nas obras fundamentais de Allan Kardec, com encontros em grupo e acompanhamento fraterno.',
            'topics' => [
                [
                    'step' => '01',
                    'title' => 'Base doutrinária sólida',
                    'description' => 'Estudo metódico do Pentateuco Kardequiano com linguagem acessível e foco no entendimento racional.',
                    'delay' => 120,
                ],
                [
                    'step' => '02',
                    'title' => 'Vivência prática do Evangelho',
                    'description' => 'Aplicação dos princípios espíritas no cotidiano, fortalecendo valores morais e responsabilidade espiritual.',
                    'delay' => 180,
                ],
                [
                    'step' => '03',
                    'title' => 'Acompanhamento por etapas',
                    'description' => 'Turmas organizadas por níveis de aprofundamento para acolher iniciantes e estudantes experientes.',
                    'delay' => 240,
                ],
            ],
        ],
        'palestras' => [
            'kicker' => 'Estudos',
            'title' => 'Palestras Públicas',
            'lead' => 'Encontros semanais abertos à comunidade com temas atuais à luz do Evangelho e da Doutrina Espírita.',
            'topics' => [
                [
                    'step' => '01',
                    'title' => 'Temas atuais e edificantes',
                    'description' => 'Reflexões sobre família, saúde emocional, caridade, reencarnação e reforma íntima.',
                    'delay' => 120,
                ],
                [
                    'step' => '02',
                    'title' => 'Abertas para todos',
                    'description' => 'Não é necessário ser frequentador da casa; visitantes são sempre bem-vindos.',
                    'delay' => 180,
                ],
                [
                    'step' => '03',
                    'title' => 'Integração com passes e acolhimento',
                    'description' => 'Após a explanação, a programação pode incluir passes e encaminhamento para atendimento fraterno.',
                    'delay' => 240,
                ],
            ],
        ],
        'atendimento-fraterno' => [
            'kicker' => 'Estudos',
            'title' => 'Atendimento Fraterno',
            'lead' => 'Escuta acolhedora e orientação espiritual individual, com sigilo, respeito e amparo moral.',
            'topics' => [
                [
                    'step' => '01',
                    'title' => 'Escuta qualificada',
                    'description' => 'Atendentes preparados oferecem espaço de acolhimento para dúvidas, aflições e busca de equilíbrio.',
                    'delay' => 120,
                ],
                [
                    'step' => '02',
                    'title' => 'Orientação doutrinária',
                    'description' => 'As orientações são fundamentadas no Evangelho e na Doutrina Espírita, sem imposições e com respeito à individualidade.',
                    'delay' => 180,
                ],
                [
                    'step' => '03',
                    'title' => 'Encaminhamento para continuidade',
                    'description' => 'Quando necessário, o participante é orientado para estudos, palestras e demais atividades da casa.',
                    'delay' => 240,
                ],
            ],
        ],
    ],
    'aboutPages' => [
        'missao' => [
            'kicker' => 'Quem Somos',
            'title' => 'Nossa Missão',
            'lead' => 'Promover o estudo, a prática e a difusão da Doutrina Espírita, contribuindo para o progresso moral e espiritual do ser humano.',
            'topics' => [
                [
                    'step' => '01',
                    'title' => 'Estudo com responsabilidade',
                    'description' => 'Oferecemos formação doutrinária séria, com base nas obras de Allan Kardec e no Evangelho de Jesus.',
                    'delay' => 120,
                ],
                [
                    'step' => '02',
                    'title' => 'Acolhimento fraterno',
                    'description' => 'Recebemos todas as pessoas com respeito, escuta ativa e compromisso com o bem.',
                    'delay' => 180,
                ],
                [
                    'step' => '03',
                    'title' => 'Serviço ao próximo',
                    'description' => 'Incentivamos a caridade e a transformação íntima como caminhos de evolução espiritual.',
                    'delay' => 240,
                ],
            ],
        ],
        'valores' => [
            'kicker' => 'Quem Somos',
            'title' => 'Nossos Valores',
            'lead' => 'Nossa atuação é orientada por princípios evangélicos e pela ética espírita no convívio diário.',
            'topics' => [
                [
                    'step' => '01',
                    'title' => 'Caridade e fraternidade',
                    'description' => 'Praticamos a benevolência, o perdão e o auxílio mútuo em todas as atividades da casa.',
                    'delay' => 120,
                ],
                [
                    'step' => '02',
                    'title' => 'Respeito e diálogo',
                    'description' => 'Valorizamos a diversidade de experiências e crenças, promovendo ambiente de aprendizado e paz.',
                    'delay' => 180,
                ],
                [
                    'step' => '03',
                    'title' => 'Disciplina e humildade',
                    'description' => 'Buscamos coerência entre estudo e vivência, com responsabilidade moral e espírito de serviço.',
                    'delay' => 240,
                ],
            ],
        ],
        'historia' => [
            'kicker' => 'Quem Somos',
            'title' => 'Nossa História',
            'lead' => 'O CEDE nasceu do ideal de reunir pessoas para estudar o Espiritismo com seriedade e acolhimento fraterno.',
            'topics' => [
                [
                    'step' => '01',
                    'title' => 'Origem no estudo coletivo',
                    'description' => 'Iniciamos com pequenos grupos de leitura e reflexão doutrinária, fortalecendo laços de fraternidade.',
                    'delay' => 120,
                ],
                [
                    'step' => '02',
                    'title' => 'Ampliação das atividades',
                    'description' => 'Com o tempo, estruturamos palestras públicas, atendimento fraterno e evangelização.',
                    'delay' => 180,
                ],
                [
                    'step' => '03',
                    'title' => 'Compromisso permanente',
                    'description' => 'Seguimos dedicados ao estudo e à caridade, servindo à comunidade com simplicidade e amor.',
                    'delay' => 240,
                ],
            ],
        ],
    ],
    'socialProofTrustCards' => [
        [
            'image' => '/assets/img/cedern/cede1_1600_1000.png',
            'alt' => 'Participantes em estudo doutrinário no CEDE',
            'title' => 'Estudos Semanais',
            'text' => 'Aprofundamento na Doutrina e no Evangelho.',
        ],
        [
            'image' => '/assets/img/cedern/cede2_1600_1000.png',
            'alt' => 'Evangelização no CEDE com crianças e jovens',
            'title' => 'Evangelização',
            'text' => 'Ensinamentos morais para crianças e jovens.',
        ],
        [
            'image' => '/assets/img/cedern/cede3_1600_1000.png',
            'alt' => 'Biblioteca espírita do CEDE com acervo de estudos',
            'title' => 'Biblioteca',
            'text' => 'Acervo completo das obras básicas e complementares.',
        ],
    ],
    'socialProofTestimonials' => [
        [
            'avatar' => 'https://randomuser.me/api/portraits/women/44.jpg',
            'alt' => 'Maria Silva',
            'name' => 'Maria Silva',
            'role' => 'Frequentadora',
            'quote' => 'O CEDE mudou minha visão sobre a vida. Encontrei respostas lógicas e consoladoras para minhas dúvidas.',
        ],
         [
            'avatar' => 'https://randomuser.me/api/portraits/men/32.jpg',
            'alt' => 'João Souza',
            'name' => 'João Souza',
            'role' => 'Estudante do ESDE',
            'quote' => 'Os estudos são muito bem organizados e o ambiente é acolhedor. Recomendo a todos que buscam conhecimento.',
        ],
         [
            'avatar' => 'https://randomuser.me/api/portraits/women/68.jpg',
            'alt' => 'Ana Pereira',
            'name' => 'Ana Pereira',
            'role' => 'Voluntária',
            'quote' => 'Servir no CEDE é uma alegria. A caridade e o amor ao próximo são praticados aqui todos os dias.',
        ],
         [
            'avatar' => 'https://randomuser.me/api/portraits/women/90.jpg',
            'alt' => 'Solange',
            'name' => 'Solange',
            'role' => 'Frequentadora',
            'quote' => 'O acolhimento fraterno transformou minha vida. Sinto muita paz e gratidão por fazer parte desta casa.',
        ],
    ],
    'donationOptions' => [
        [
            'title' => 'Doação via PIX (Banco Bradesco)',
            'description' => 'Escaneie o QR Code do PIX para contribuir de forma rápida e segura.',
            'qrImage' => '/assets/img/cedern/qr_cede_bradesco.jpeg',
            'qrAlt' => 'QR Code para doação via PIX',
            'pixKey' => '04.242.556/0001-45',
            'hint' => 'Adicione a imagem do QR do PIX e atualize a chave PIX no arquivo de conteúdo da home.',
        ],
    ],
     'roadmapItems' => [
        [
            'phase' => 'Segunda-feira',
            'title' => '20:00 - Estudo do Evangelho',
            'description' => 'Reflexões sobre os ensinamentos morais de Jesus à luz do Espiritismo.',
            'href' => '/agenda/estudo-do-evangelho',
            'linkLabel' => 'Ver detalhes da atividade',
            'status' => 'done',
        ],
        [
            'phase' => 'Quarta-feira',
            'title' => '19:30 - Palestra Pública',
            'description' => 'Tema livre evangélico-doutrinário seguido de passes magnéticos.',
            'href' => '/agenda/palestra-publica',
            'linkLabel' => 'Ver detalhes da atividade',
            'status' => 'current',
        ],
        [
            'phase' => 'Sábado',
            'title' => '16:00 - Juventude Espírita',
            'description' => 'Encontros voltados para jovens de 12 a 21 anos com dinâmicas e estudos.',
            'href' => '/agenda/juventude-espirita',
            'linkLabel' => 'Ver detalhes da atividade',
            'status' => 'planned',
        ],
    ],
    'agendaPages' => [
        'estudo-do-evangelho' => [
            'kicker' => 'Agenda',
            'title' => 'Estudo do Evangelho',
            'lead' => 'Encontro semanal de reflexão sobre os ensinamentos de Jesus, com foco na vivência prática do bem.',
            'topics' => [
                [
                    'step' => '01',
                    'title' => 'Horário e dinâmica',
                    'description' => 'Segunda-feira, às 20h, com leitura orientada e diálogo fraterno entre os participantes.',
                    'delay' => 120,
                ],
                [
                    'step' => '02',
                    'title' => 'Conteúdo estudado',
                    'description' => 'Passagens do Evangelho à luz da Doutrina Espírita, com aplicação no cotidiano.',
                    'delay' => 180,
                ],
                [
                    'step' => '03',
                    'title' => 'Participação',
                    'description' => 'Aberto a iniciantes e frequentadores; não é necessário inscrição prévia.',
                    'delay' => 240,
                ],
            ],
        ],
        'palestra-publica' => [
            'kicker' => 'Agenda',
            'title' => 'Palestra Pública',
            'lead' => 'Exposição doutrinária aberta ao público, seguida de acolhimento espiritual da casa.',
            'topics' => [
                [
                    'step' => '01',
                    'title' => 'Horário e abertura',
                    'description' => 'Quarta-feira, às 19h30, com recepção fraterna para visitantes e frequentadores.',
                    'delay' => 120,
                ],
                [
                    'step' => '02',
                    'title' => 'Temática da palestra',
                    'description' => 'Abordagem evangélico-doutrinária sobre desafios da vida, família e crescimento espiritual.',
                    'delay' => 180,
                ],
                [
                    'step' => '03',
                    'title' => 'Encaminhamentos',
                    'description' => 'Ao final, os participantes podem receber passes e orientação para próximas atividades.',
                    'delay' => 240,
                ],
            ],
        ],
        'juventude-espirita' => [
            'kicker' => 'Agenda',
            'title' => 'Juventude Espírita',
            'lead' => 'Espaço de estudo e convivência para jovens, unindo conhecimento doutrinário e formação moral.',
            'topics' => [
                [
                    'step' => '01',
                    'title' => 'Horário e faixa etária',
                    'description' => 'Sábado, às 16h, para jovens de 12 a 21 anos em ambiente acolhedor e participativo.',
                    'delay' => 120,
                ],
                [
                    'step' => '02',
                    'title' => 'Metodologia',
                    'description' => 'Estudos temáticos, rodas de conversa e dinâmicas educativas baseadas no Evangelho.',
                    'delay' => 180,
                ],
                [
                    'step' => '03',
                    'title' => 'Objetivo formativo',
                    'description' => 'Fortalecer valores, autoconhecimento e compromisso com o bem desde a juventude.',
                    'delay' => 240,
                ],
            ],
        ],
    ],
    'faqCategories' => [
        [
            'slug' => 'doutrina',
            'title' => 'Doutrina Espírita',
            'lead' => 'Conceitos fundamentais e princípios do Espiritismo.',
        ],
        [
            'slug' => 'participacao',
            'title' => 'Participação no CEDE',
            'lead' => 'Como participar das atividades e se integrar à casa.',
        ],
        [
            'slug' => 'praticas',
            'title' => 'Práticas da Casa',
            'lead' => 'Atendimento fraterno, passes e demais vivências da casa espírita.',
        ],
    ],
    'faqItems' => [
        [
            'question' => 'O que é o Espiritismo?',
            'answer' => 'É a doutrina fundada sobre a existência, as manifestações e o ensino dos Espíritos. Possui caráter científico, filosófico e religioso.',
            'category' => 'doutrina',
        ],
        [
            'question' => 'Preciso pagar para participar?',
            'answer' => 'Não. Todas as atividades da casa espírita são gratuitas, seguindo o princípio "Dai de graça o que de graça recebestes".',
            'category' => 'participacao',
        ],
        [
            'question' => 'Como funciona o atendimento fraterno?',
            'answer' => 'É um diálogo reservado com um atendente preparado, que oferece escuta e orientação baseada no Evangelho. Basta chegar 30min antes da reunião pública.',
            'category' => 'praticas',
        ],
        [
            'question' => 'O que são os passes magnéticos?',
            'answer' => 'São transmissões de energias psíquicas e espirituais que visam o reequilíbrio físico e perispiritual, auxiliando na harmonia interior.',
            'category' => 'praticas',
        ],
        [
            'question' => 'Crianças podem frequentar o centro?',
            'answer' => 'Sim! Temos a Evangelização Infantil, que adapta os ensinamentos morais de Jesus e do Espiritismo para a linguagem das crianças.',
            'category' => 'participacao',
        ],
        [
            'question' => 'Como posso começar a estudar?',
            'answer' => 'Recomendamos iniciar pela leitura de "O Livro dos Espíritos" e participar do ESDE (Estudo Sistematizado da Doutrina Espírita) ou das palestras públicas.',
            'category' => 'participacao',
        ],
        [
            'question' => 'Qual a visão espírita sobre outras religiões?',
            'answer' => 'O Espiritismo respeita todas as religiões e crenças que promovam o bem, entendendo que todas são caminhos para a evolução moral do ser humano.',
            'category' => 'doutrina',
        ],
        [
            'question' => 'O que é a reencarnação?',
            'answer' => 'É a volta do Espírito à vida corporal. É uma oportunidade de aprendizado e reparação, necessária para o progresso intelectual e moral.',
            'category' => 'doutrina',
        ],
        [
            'question' => 'O que é um médium?',
            'answer' => 'É toda pessoa que sente, num grau qualquer, a influência dos Espíritos. É uma faculdade inerente ao ser humano e não um privilégio.',
            'category' => 'doutrina',
        ],
        [
            'question' => 'Como posso ajudar o CEDE?',
            'answer' => 'Você pode ajudar participando das atividades, divulgando a doutrina e, se desejar, colaborando com doações ou trabalho voluntário nas frentes assistenciais.',
            'category' => 'participacao',
        ],
    ],
    'ctaActions' => [
        [
            'label' => 'Conheça nossa Biblioteca',
            'href' => '/estudos',
            'class' => 'nc-btn nc-btn-primary',
            'loadingOnClick' => false,
        ],
    ],
];
