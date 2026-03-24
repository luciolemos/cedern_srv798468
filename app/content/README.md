# Conteúdo da Home (NatalCode)

Este diretório centraliza os textos e listas da página inicial.

## Arquivo principal

- `app/content/home.php`

## Como editar

Edite apenas os valores de texto e links dentro dos arrays. Evite mudar os nomes das chaves.

### Blocos de seção (título/subtítulo)

Em `sections`:

- `hero`
- `features`
- `socialProof`
- `testimonials`
- `roadmap`
- `faq`
- `cta`

Cada bloco usa chaves como:

- `kicker`
- `title`
- `lead`

Algumas seções possuem campos extras:

- `hero`: `badge`, `actionsDelay`, `panelTitle`, `panelDelay`, `qrTitle`, `qrLead`, `qrUrl`, `qrImage`, `qrAlt`
- `cta`: `actionsDelay`

## Listas de itens

No mesmo arquivo `home.php`:

- `heroActions`: botões do Hero
- `heroMetrics`: indicadores do Hero
- `featuresItems`: cards de Estudos
- `socialProofTestimonials`: depoimentos
- `roadmapItems`: passos da Agenda
- `faqItems`: perguntas e respostas
- `ctaActions`: botões da chamada final

### Flags úteis para botões (actions)

Disponíveis em `heroActions` e `ctaActions`:

- `loadingOnClick` (boolean): adiciona estado visual de carregamento ao clicar.
- `ariaDisabled` (boolean): desabilita interação do botão/link.
- `disabledLabel` (string): texto alternativo quando `ariaDisabled=true` (ex.: `Em breve`).

Exemplo:

```php
[
	'label' => 'Ver demonstração',
	'href' => '/users',
	'class' => 'nc-btn nc-btn-secondary',
	'ariaDisabled' => true,
	'disabledLabel' => 'Em breve',
]
```

## Ordem de exibição

A ordem na tela segue a ordem dos itens no array.

## Delays de animação

Campos `delay` controlam o tempo do AOS. Mantenha valores progressivos para um efeito fluido.

Observação: em seções como `hero` e `cta`, os delays de container/ações são definidos no bloco `sections`.

## Boas práticas

- Mantenha textos curtos e objetivos.
- Preserve a consistência da linha editorial.
- Ao trocar links, valide se o destino existe.
- Não remova chaves usadas pelos templates.

## Erros comuns e como evitar

- **Remover chaves obrigatórias**
	- Sintoma: texto vazio ou componente não renderiza.
	- Evite: mantenha a estrutura dos arrays e edite apenas valores.

- **Trocar tipo de dado por engano**
	- Sintoma: erro de renderização no Twig (ex.: string no lugar de array).
	- Evite: itens de lista devem continuar como array de objetos (`[ {...}, {...} ]`).

- **Delays fora de ordem**
	- Sintoma: animação visual estranha (elementos aparecem “fora de ritmo”).
	- Evite: mantenha `delay` progressivo (ex.: 120, 180, 240...).

- **Links quebrados em ações**
	- Sintoma: botão leva para rota inexistente ou erro 404.
	- Evite: validar `href` localmente antes de publicar.

- **Uso incorreto de `ariaDisabled`**
	- Sintoma: botão parece ativo, mas não clica como esperado.
	- Evite: quando `ariaDisabled=true`, definir também `disabledLabel` para comunicar claramente (ex.: `Em breve`).

- **Esquecer atualizar snapshots após mudança visual intencional**
	- Sintoma: falha no check `Visual Regression / visual-tests`.
	- Evite: rodar `npm run test:visual:update` e versionar snapshots.

## Onde isso é renderizado

Templates que consomem esses dados:

- `templates/home/hero.twig`
- `templates/home/features.twig`
- `templates/home/social-proof.twig`
- `templates/home/testimonials.twig`
- `templates/home/roadmap.twig`
- `templates/home/faq.twig`
- `templates/home/final-cta.twig`

## Matriz campo -> impacto na tela

| Campo em `home.php` | Impacto na interface |
|---|---|
| `sections.hero.kicker` | Texto curto acima do título principal no Hero |
| `sections.hero.badge` | Badge de destaque no Hero |
| `sections.hero.title` | Título principal do Hero |
| `sections.hero.lead` | Descrição principal do Hero |
| `sections.hero.qr*` | Bloco de QR code exibido no painel lateral do Hero |
| `heroActions[]` | Botões do Hero (label, link, estilo, estado) |
| `heroMetrics[]` | Cards de indicadores no painel lateral do Hero |
| `sections.features.*` | Cabeçalho da seção Estudos |
| `featuresItems[]` | Cards da seção Estudos |
| `sections.socialProof.*` | Cabeçalho da seção Quem Somos |
| `sections.testimonials.*` | Cabeçalho da seção Testemunhos |
| `socialProofTestimonials[]` | Depoimentos (avatar, nome, cargo, frase) |
| `sections.roadmap.*` | Cabeçalho da seção Agenda |
| `roadmapItems[]` | Itens numerados da Agenda |
| `sections.faq.*` | Cabeçalho da seção FAQ |
| `faqItems[]` | Perguntas e respostas expansíveis |
| `sections.cta.*` | Cabeçalho da chamada final (Ação) |
| `ctaActions[]` | Botões da CTA final |

### Campos de comportamento (botões)

| Campo | Efeito |
|---|---|
| `loadingOnClick` | Exibe estado de carregamento ao clicar |
| `ariaDisabled` | Desabilita interação do botão/link |
| `disabledLabel` | Define texto alternativo quando desabilitado |

### Campos de ritmo visual (AOS)

| Campo | Efeito |
|---|---|
| `delay` (itens) | Ordem e timing de entrada da animação |
| `actionsDelay` / `panelDelay` | Timing de blocos do Hero/CTA |
