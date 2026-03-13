# Conteudo da Home (NatalCode)

Este diretório centraliza os textos e listas da página inicial.

## Arquivo principal

- `app/content/home.php`

## Como editar

Edite apenas os valores de texto e links dentro dos arrays. Evite mudar os nomes das chaves.

### Blocos de seção (titulo/subtitulo)

Em `sections`:

- `hero`
- `features`
- `socialProof`
- `roadmap`
- `faq`
- `cta`

Cada bloco usa chaves como:

- `kicker`
- `title`
- `lead`

Algumas seções possuem campos extras:

- `hero`: `badge`, `actionsDelay`, `panelTitle`, `panelDelay`
- `socialProof`: `trustGridLabel`
- `cta`: `actionsDelay`

## Listas de itens

No mesmo arquivo `home.php`:

- `heroActions`: botoes do Hero
- `heroMetrics`: indicadores do Hero
- `featuresItems`: cards de Solucoes
- `socialProofTrustCards`: cards de confianca
- `socialProofTestimonials`: depoimentos
- `roadmapItems`: passos do Roadmap
- `faqItems`: perguntas e respostas
- `ctaActions`: botoes da chamada final

### Flags uteis para botoes (actions)

Disponiveis em `heroActions` e `ctaActions`:

- `loadingOnClick` (boolean): adiciona estado visual de carregamento ao clicar.
- `ariaDisabled` (boolean): desabilita interacao do botao/link.
- `disabledLabel` (string): texto alternativo quando `ariaDisabled=true` (ex.: `Em breve`).

Exemplo:

```php
[
	'label' => 'Ver demonstracao',
	'href' => '/users',
	'class' => 'nc-btn nc-btn-secondary',
	'ariaDisabled' => true,
	'disabledLabel' => 'Em breve',
]
```

## Ordem de exibicao

A ordem na tela segue a ordem dos itens no array.

## Delays de animacao

Campos `delay` controlam o tempo do AOS. Mantenha valores progressivos para um efeito fluido.

Observacao: em secoes como `hero` e `cta`, os delays de container/acoes sao definidos no bloco `sections`.

## Boas praticas

- Mantenha textos curtos e objetivos.
- Preserve a consistencia da linha editorial.
- Ao trocar links, valide se o destino existe.
- Nao remova chaves usadas pelos templates.

## Erros comuns e como evitar

- **Remover chaves obrigatorias**
	- Sintoma: texto vazio ou componente nao renderiza.
	- Evite: mantenha a estrutura dos arrays e edite apenas valores.

- **Trocar tipo de dado por engano**
	- Sintoma: erro de renderizacao no Twig (ex.: string no lugar de array).
	- Evite: itens de lista devem continuar como array de objetos (`[ {...}, {...} ]`).

- **Delays fora de ordem**
	- Sintoma: animacao visual estranha (elementos aparecem “fora de ritmo”).
	- Evite: mantenha `delay` progressivo (ex.: 120, 180, 240...).

- **Links quebrados em acoes**
	- Sintoma: botao leva para rota inexistente ou erro 404.
	- Evite: validar `href` localmente antes de publicar.

- **Uso incorreto de `ariaDisabled`**
	- Sintoma: botao parece ativo, mas nao clica como esperado.
	- Evite: quando `ariaDisabled=true`, definir tambem `disabledLabel` para comunicar claramente (ex.: `Em breve`).

- **Esquecer atualizar snapshots apos mudanca visual intencional**
	- Sintoma: falha no check `Visual Regression / visual-tests`.
	- Evite: rodar `npm run test:visual:update` e versionar snapshots.

## Onde isso e renderizado

Templates que consomem esses dados:

- `templates/home/hero.twig`
- `templates/home/features.twig`
- `templates/home/social-proof.twig`
- `templates/home/roadmap.twig`
- `templates/home/faq.twig`
- `templates/home/final-cta.twig`

## Matriz campo -> impacto na tela

| Campo em `home.php` | Impacto na interface |
|---|---|
| `sections.hero.kicker` | Texto curto acima do titulo principal no Hero |
| `sections.hero.badge` | Badge de destaque no Hero |
| `sections.hero.title` | Titulo principal do Hero |
| `sections.hero.lead` | Descricao principal do Hero |
| `heroActions[]` | Botoes do Hero (label, link, estilo, estado) |
| `heroMetrics[]` | Cards de indicadores no painel lateral do Hero |
| `sections.features.*` | Cabecalho da secao Solucoes |
| `featuresItems[]` | Cards da secao Solucoes |
| `sections.socialProof.*` | Cabecalho da secao Prova Social |
| `socialProofTrustCards[]` | Cards com imagem e texto de confianca |
| `socialProofTestimonials[]` | Depoimentos (avatar, nome, cargo, frase) |
| `sections.roadmap.*` | Cabecalho da secao Roadmap |
| `roadmapItems[]` | Itens numerados do Roadmap |
| `sections.faq.*` | Cabecalho da secao FAQ |
| `faqItems[]` | Perguntas e respostas expansiveis |
| `sections.cta.*` | Cabecalho da chamada final (Acao) |
| `ctaActions[]` | Botoes da CTA final |

### Campos de comportamento (botoes)

| Campo | Efeito |
|---|---|
| `loadingOnClick` | Exibe estado de carregamento ao clicar |
| `ariaDisabled` | Desabilita interacao do botao/link |
| `disabledLabel` | Define texto alternativo quando desabilitado |

### Campos de ritmo visual (AOS)

| Campo | Efeito |
|---|---|
| `delay` (itens) | Ordem e timing de entrada da animacao |
| `actionsDelay` / `panelDelay` | Timing de blocos do Hero/CTA |
