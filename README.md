# NatalCode

[![Visual Regression](https://github.com/luciolemos/natalcode_srv798468/actions/workflows/visual-regression.yml/badge.svg)](https://github.com/luciolemos/natalcode_srv798468/actions/workflows/visual-regression.yml)
[![PHPUnit](https://github.com/luciolemos/natalcode_srv798468/actions/workflows/phpunit.yml/badge.svg)](https://github.com/luciolemos/natalcode_srv798468/actions/workflows/phpunit.yml)
[![PHPStan](https://github.com/luciolemos/natalcode_srv798468/actions/workflows/phpstan.yml/badge.svg)](https://github.com/luciolemos/natalcode_srv798468/actions/workflows/phpstan.yml)
[![PHPCS](https://github.com/luciolemos/natalcode_srv798468/actions/workflows/phpcs.yml/badge.svg)](https://github.com/luciolemos/natalcode_srv798468/actions/workflows/phpcs.yml)

Landing page institucional em **Slim 4 + Twig**, com foco em base reutilizável para evolução de produto (site, área administrativa e conteúdo), com sistema de tema visual dinâmico.

## Estado atual

O projeto está rodando em servidor Linux com Apache, publicado no domínio:

- `https://srv798468.hstgr.cloud/`

Atualmente, a aplicação entrega:

- landing page em Twig com seções modulares;
- paleta de cores dinâmica (blue/red/green/violet/amber);
- modo `light` / `dark`;
- intensidade do dark (`neutral` / `vivid`);
- animações de entrada com AOS e delays progressivos;
- tokenização de design em CSS (`app.css`).

## Stack

- PHP 8.x
- Slim Framework 4
- PHP-DI
- Twig (`slim/twig-view`)
- Dotenv (`vlucas/phpdotenv`)
- Monolog
- AOS (Animate On Scroll)
- CSS custom com design tokens
- JS vanilla para interações de tema
- Playwright (regressão visual)
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
│   │   ├── home.php            # Conteúdo centralizado da home
│   │   └── README.md           # Guia de edição do conteúdo
│   ├── dependencies.php        # Container + Twig globals
│   ├── middleware.php
│   ├── repositories.php
│   ├── routes.php
│   └── settings.php
├── tests/
│   └── visual/
│       └── home.spec.js         # Regressão visual da home
├── public/
│   ├── assets/
│   │   ├── css/cedern.css      # Tokens + componentes + temas
│   │   └── js/
│   │       ├── aos-init.js
│   │       ├── cedern-buttons.js
│   │       ├── cedern-nav.js
│   │       └── cedern-theme.js
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
│   └── home.twig               # Composição da home via includes
├── package.json                # Scripts de regressão visual
├── playwright.config.js        # Config Playwright
├── .env
├── .env.example
└── README.md
```

## Estrutura da landing

A home é composta por partials Twig:

1. `hero`
2. `features` (Estudos)
3. `social-proof`
4. `roadmap`
5. `final-cta`

Cada seção tem animação AOS e delays progressivos nos elementos internos.

## Edição de conteúdo da home

O conteúdo textual da home (títulos, descrições, cards, agenda, FAQ e CTAs) está centralizado em:

- `app/content/home.php`

Guia detalhado de edição:

- `app/content/README.md`

### O que este guia cobre

- quais chaves controlam cada seção da home;
- como editar textos sem quebrar o layout;
- como usar `delay` nas animações;
- como configurar ações de botões (`loadingOnClick`, `ariaDisabled`, `disabledLabel`).

### Fluxo recomendado para alterar conteúdo

1. editar `app/content/home.php`;
2. validar localmente em `http://localhost:8080`;
3. rodar checks (`npm run test:visual` e checks PHP no CI);
4. abrir PR com a mudança de conteúdo.

### Checklist rápido antes de publicar

- textos coerentes com a linha editorial;
- links válidos (`href` funcionando);
- ordem dos itens correta (arrays);
- snapshots visuais atualizados quando houver mudança intencional.

## Tema, modo e intensidade

A interface suporta:

- paleta: `blue | red | green | violet | amber`
- modo: `light | dark`
- dark intensity: `neutral | vivid`

### Prioridade de configuração

1. Preferência salva no navegador (`localStorage`)
2. Defaults vindos do servidor via `.env`

## Configuração via .env

Arquivo: `.env`

Chaves disponíveis:

```env
APP_DEFAULT_THEME=amber
APP_DEFAULT_MODE=light
APP_DEFAULT_DARK_INTENSITY=neutral
APP_AGENDA_PUBLIC_LIMIT=12
```

Valores válidos:

- `APP_DEFAULT_THEME`: `blue | red | green | violet | amber`
- `APP_DEFAULT_MODE`: `light | dark`
- `APP_DEFAULT_DARK_INTENSITY`: `neutral | vivid`
- `APP_AGENDA_PUBLIC_LIMIT`: quantidade de eventos futuros exibidos em `/agenda` (mínimo `1`, máximo `100`)

## Acesso administrativo via membros

O painel (`/painel`) utiliza papéis de `member_users`.

- `operator`: acesso a eventos;
- `manager`: acesso a eventos e categorias;
- `admin`: acesso total, incluindo usuários.

Não há mais autenticação administrativa por usuário/senha em `.env`.
Administradores devem ser membros aprovados com papel `admin`.

### Bootstrap do primeiro admin (SQL)

Promover um membro existente pelo e-mail:

```sql
UPDATE member_users mu
JOIN roles r ON r.role_key = 'admin'
SET
	mu.role_id = r.id,
	mu.status = 'active',
	mu.approved_at = COALESCE(mu.approved_at, NOW())
WHERE mu.email = 'seu-email@dominio.com';
```

Criar um membro admin direto (ajuste hash/senha conforme sua política):

```sql
INSERT INTO member_users (
	full_name,
	email,
	password_hash,
	role_id,
	status,
	profile_completed,
	approved_at
)
SELECT
	'Administrador CEDE',
	'admin@exemplo.com',
	'$2y$10$TroquePorHashBcryptValido',
	r.id,
	'active',
	1,
	NOW()
FROM roles r
WHERE r.role_key = 'admin';
```

## Como executar localmente

```bash
cd /var/www/natalcode
composer install
composer start
```

Aplicação local:

- `http://localhost:8080`

## Regressão visual (Playwright)

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

Executar regressão visual:

```bash
npm run test:visual
```

Cobertura atual:

- Home (top fold e full page)
- Breakpoints: mobile, tablet e desktop

### CI automático

Workflow configurado em:

- `.github/workflows/visual-regression.yml`

Executa em `push` e `pull_request`:

- instala dependências PHP/Node;
- instala Chromium do Playwright;
- executa `npm run test:visual`.

Em caso de falha, publica artefatos com relatório e resultados dos testes.

## Smoke check de deploy

Para validar rapidamente se a página publicada não está truncada e se os scripts críticos foram carregados:

```bash
chmod +x scripts/smoke-check.sh
./scripts/smoke-check.sh https://cedern.org/
```

O script falha se:

- status HTTP for >= 500;
- HTML vier truncado (sem `</html>`);
- houver JSON de erro injetado no HTML (`"statusCode": 500`);
- referência do `cedern-nav.js` não for encontrada.

Workflow dedicado:

- `.github/workflows/smoke-check.yml`

Ele roda manualmente (`workflow_dispatch`) e deve ser executado após o deploy.

## Checklist anti-quebra em hospedagem compartilhada

Use esta lista como critério de publicação para evitar regressão em produção:

1. **Paridade de ambiente**
	- Validar local/staging com a mesma versão de PHP e extensões do servidor.

2. **Erro correto por tipo de resposta**
	- Páginas HTML devem receber erro em HTML.
	- Rotas de API devem receber erro em JSON.

3. **Shutdown handler seguro**
	- Tratar apenas erros fatais no shutdown (warnings/notices não devem sobrescrever resposta válida).

4. **Versionamento de assets**
	- Sempre publicar CSS/JS com versão/hash para evitar cache antigo em deploy.

5. **Smoke check obrigatório pós-deploy**
	- Executar `scripts/smoke-check.sh` contra o domínio publicado.
	- Bloquear liberação se houver HTML truncado, `statusCode: 500` injetado ou falta de scripts críticos.

6. **CI alinhada ao runtime real**
	- Matriz de testes deve usar versões de PHP compatíveis com `composer.lock`.

7. **Logs e diagnóstico rápido**
	- Garantir logs acessíveis no servidor e endpoint de diagnóstico quando necessário.

8. **Deploy determinístico**
	- Confirmar que o provedor publicou exatamente o commit esperado (sem artefato antigo em cache).

### Fluxo recomendado de implantação

1. Rodar validações locais (`phpunit`, `phpstan`, `phpcs`, visual quando aplicável).
2. Publicar branch/PR e aguardar checks obrigatórios.
3. Fazer deploy no provedor.
4. Executar smoke check pós-deploy.
5. Validar interações críticas (menu mobile, seletor de tema, rota API principal).

### Quando a mudança visual for intencional

Atualize snapshots localmente e versione os arquivos gerados:

```bash
npm run test:visual:update
```

## Publicação (Apache)

A aplicação deve ser servida com `DocumentRoot` apontando para:

- `.../natalcode/public`

## Observação operacional

No momento, alterações estão sendo feitas diretamente no servidor. Para fluxo profissional, recomenda-se:

1. ambiente local de desenvolvimento;
2. ambiente de staging;
3. deploy para produção após validação.

## Branch protection (recomendado)

No GitHub, configure proteção da branch principal (`main`/`master`) com:

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

### Política de merge

- Preferir `Squash and merge`
- Bloquear merge direto na branch principal
- Exigir PR mesmo para manutenção de conteúdo
- Utilizar checklist do template de PR: `.github/pull_request_template.md`
