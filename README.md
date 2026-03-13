# NatalCode

[![Visual Regression](https://github.com/luciolemos/natalcode_srv798468/actions/workflows/visual-regression.yml/badge.svg)](https://github.com/luciolemos/natalcode_srv798468/actions/workflows/visual-regression.yml)
[![PHPUnit](https://github.com/luciolemos/natalcode_srv798468/actions/workflows/phpunit.yml/badge.svg)](https://github.com/luciolemos/natalcode_srv798468/actions/workflows/phpunit.yml)
[![PHPStan](https://github.com/luciolemos/natalcode_srv798468/actions/workflows/phpstan.yml/badge.svg)](https://github.com/luciolemos/natalcode_srv798468/actions/workflows/phpstan.yml)
[![PHPCS](https://github.com/luciolemos/natalcode_srv798468/actions/workflows/phpcs.yml/badge.svg)](https://github.com/luciolemos/natalcode_srv798468/actions/workflows/phpcs.yml)

Landing page institucional em **Slim 4 + Twig**, com foco em base reutilizavel para evolucao de produto (site, area administrativa e conteudo), com sistema de tema visual dinamico.

## Estado atual

O projeto esta rodando em servidor Linux com Apache, publicado no dominio:

- `https://srv798468.hstgr.cloud/`

Atualmente, a aplicacao entrega:

- landing page em Twig com secoes modulares;
- paleta de cores dinamica (blue/red/green/violet/amber);
- modo `light` / `dark`;
- intensidade do dark (`neutral` / `vivid`);
- animacoes de entrada com AOS e delays progressivos;
- tokenizacao de design em CSS (`app.css`).

## Stack

- PHP 8.x
- Slim Framework 4
- PHP-DI
- Twig (`slim/twig-view`)
- Dotenv (`vlucas/phpdotenv`)
- Monolog
- AOS (Animate On Scroll)
- CSS custom com design tokens
- JS vanilla para interacoes de tema
- Playwright (regressao visual)
- GitHub Actions (CI)

## Estrutura do projeto

```txt
/var/www/natalcode
├── .github/
│   └── workflows/
│       ├── visual-regression.yml
│       ├── phpunit.yml
│       ├── phpstan.yml
│       └── phpcs.yml
├── app/
│   ├── content/
│   │   ├── home.php            # Conteudo centralizado da home
│   │   └── README.md           # Guia de edicao do conteudo
│   ├── dependencies.php        # Container + Twig globals
│   ├── middleware.php
│   ├── repositories.php
│   ├── routes.php
│   └── settings.php
├── tests/
│   └── visual/
│       └── home.spec.js         # Regressao visual da home
├── public/
│   ├── assets/
│   │   ├── css/app.css         # Tokens + componentes + temas
│   │   └── js/
│   │       ├── aos-init.js
│   │       ├── button-states.js
│   │       ├── header-menu.js
│   │       └── theme-palette.js
│   └── index.php               # Bootstrap Slim + dotenv
├── templates/
│   ├── components/
│   │   ├── check-item.twig
│   │   ├── faq-item.twig
│   │   ├── feature-card.twig
│   │   ├── footer.twig
│   │   ├── header.twig
│   │   ├── section-header.twig
│   │   └── theme-palette.twig
│   ├── home/
│   │   ├── hero.twig
│   │   ├── features.twig
│   │   ├── social-proof.twig
│   │   ├── roadmap.twig
│   │   ├── faq.twig
│   │   └── final-cta.twig
│   ├── layouts/base.twig
│   └── home.twig               # Composicao da home via includes
├── package.json                # Scripts de regressao visual
├── playwright.config.js        # Config Playwright
├── .env
├── .env.example
└── README.md
```

## Estrutura da landing

A home eh composta por partials Twig:

1. `hero`
2. `features` (Solucoes)
3. `social-proof`
4. `roadmap`
5. `final-cta`

Cada secao tem animacao AOS e delays progressivos nos elementos internos.

## Edicao de conteudo da home

O conteudo textual da home (titulos, descricoes, cards, roadmap, FAQ e CTAs) esta centralizado em:

- `app/content/home.php`

Guia detalhado de edicao:

- `app/content/README.md`

### O que este guia cobre

- quais chaves controlam cada secao da home;
- como editar textos sem quebrar o layout;
- como usar `delay` nas animacoes;
- como configurar acoes de botoes (`loadingOnClick`, `ariaDisabled`, `disabledLabel`).

### Fluxo recomendado para alterar conteudo

1. editar `app/content/home.php`;
2. validar localmente em `http://localhost:8080`;
3. rodar checks (`npm run test:visual` e checks PHP no CI);
4. abrir PR com a mudanca de conteudo.

### Checklist rapido antes de publicar

- textos coerentes com a linha editorial;
- links validos (`href` funcionando);
- ordem dos itens correta (arrays);
- snapshots visuais atualizados quando houver mudanca intencional.

## Tema, modo e intensidade

A interface suporta:

- paleta: `blue | red | green | violet | amber`
- modo: `light | dark`
- dark intensity: `neutral | vivid`

### Prioridade de configuracao

1. Preferencia salva no navegador (`localStorage`)
2. Defaults vindos do servidor via `.env`

## Configuracao via .env

Arquivo: `.env`

Chaves disponiveis:

```env
APP_DEFAULT_THEME=amber
APP_DEFAULT_MODE=light
APP_DEFAULT_DARK_INTENSITY=neutral
```

Valores validos:

- `APP_DEFAULT_THEME`: `blue | red | green | violet | amber`
- `APP_DEFAULT_MODE`: `light | dark`
- `APP_DEFAULT_DARK_INTENSITY`: `neutral | vivid`

## Como executar localmente

```bash
cd /var/www/natalcode
composer install
composer start
```

Aplicacao local:

- `http://localhost:8080`

## Regressao visual (Playwright)

Setup inicial:

```bash
cd /var/www/natalcode
npm install
npx playwright install --with-deps chromium
```

Gerar baseline inicial (snapshots):

```bash
npm run test:visual:update
```

Executar regressao visual:

```bash
npm run test:visual
```

Cobertura atual:

- Home (top fold e full page)
- Breakpoints: mobile, tablet e desktop

### CI automatico

Workflow configurado em:

- `.github/workflows/visual-regression.yml`

Executa em `push` e `pull_request`:

- instala dependencias PHP/Node;
- instala Chromium do Playwright;
- executa `npm run test:visual`.

Em caso de falha, publica artefatos com relatorio e resultados dos testes.

### Quando a mudanca visual for intencional

Atualize snapshots localmente e versione os arquivos gerados:

```bash
npm run test:visual:update
```

## Publicacao (Apache)

A aplicacao deve ser servida com `DocumentRoot` apontando para:

- `.../natalcode/public`

## Observacao operacional

No momento, alteracoes estao sendo feitas diretamente no servidor. Para fluxo profissional, recomenda-se:

1. ambiente local de desenvolvimento;
2. ambiente de staging;
3. deploy para producao apos validacao.

## Branch protection (recomendado)

No GitHub, configure protecao da branch principal (`main`/`master`) com:

- Require a pull request before merging
- Require approvals: `1` ou mais
- Dismiss stale pull request approvals when new commits are pushed
- Require status checks to pass before merging
- Require conversation resolution before merging
- Restrict who can push to matching branches (opcional)

### Status checks sugeridos

- `Visual Regression / visual-tests`
- `PHPUnit / phpunit`
- `PHPStan / phpstan`
- `PHPCS / phpcs`

### Politica de merge

- Preferir `Squash and merge`
- Bloquear merge direto na branch principal
- Exigir PR mesmo para manutencao de conteudo
- Utilizar checklist do template de PR: `.github/pull_request_template.md`
