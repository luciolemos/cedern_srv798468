# PR Title
Refatora site CEDE para arquitetura multipágina e consolida navegação institucional

## Resumo
Este PR transforma a estrutura atual em uma navegação multipágina com rotas dedicadas para Quem Somos, Estudos, Agenda, FAQ e Contato, além de subpáginas de detalhamento por tema.
Também centraliza a navegação, adiciona breadcrumbs, ajusta layout/header/footer e reduz duplicação de conteúdo na home.

## O que mudou

### 1) Arquitetura de páginas e rotas
- Criação de actions dedicadas em `src/Application/Actions/Page/` para páginas principais e subpáginas.
- Atualização de rotas em `app/routes.php` para suportar:
  - `/quem-somos`, `/estudos`, `/agenda`, `/faq`, `/contato`
  - subrotas de detalhes (ex.: missão, valores, história, estudos, agenda e categorias de FAQ)
  - nova página `/quem-somos/nossa-marca`

### 2) Conteúdo e navegação centralizados
- Estruturação de labels e menus em `app/content/navigation.php`.
- Expansão/organização de dados em `app/content/home.php` para suportar páginas de detalhe.
- Inclusão de breadcrumbs reutilizáveis com fallback de labels.

### 3) Templates Twig
- Novos templates em `templates/pages/` para páginas principais e páginas de detalhe.
- Inclusão de `templates/components/breadcrumbs.twig`.
- Ajustes em layout/base e componentes globais (header/footer/cards/check-item).
- Home ajustada para atuar como hub e evitar repetição de blocos de conteúdo já presentes nas páginas internas.

### 4) Estilo e comportamento frontend
- Refinos em `public/assets/css/cedern.css` para header/footer, responsividade e hierarquia visual.
- Melhorias de comportamento em:
  - `public/assets/js/cedern-nav.js`
  - `public/assets/js/cedern-theme.js`
- Atualização de assets de marca em `public/assets/img/brands/`.

### 5) Visual regression
- Atualização dos snapshots visuais em `tests/visual/home.spec.js-snapshots/` para refletir o novo layout.

## Validação executada
- `composer test` ✅
  - Resultado: **OK (19 tests, 37 assertions)**
- Smoke test local em `http://localhost:8080` ✅
  - `/` 200
  - `/quem-somos` 200
  - `/estudos` 200
  - `/agenda` 200
  - `/faq` 200
  - `/contato` 200
  - `/quem-somos/nossa-marca` 200

## Observações importantes para revisão
- Há mudanças em arquivos de ambiente (`.env` e `.env.example`).
- Há substituição/remoção e inclusão de imagens de marca (arquivos binários).
- Há snapshots visuais atualizados.

## Checklist de merge
- [ ] Confirmar se `.env` deve entrar no PR (normalmente não).
- [ ] Validar se todos os assets de marca adicionados são necessários.
- [ ] Revisar snapshots de Playwright.
- [ ] Aprovar navegação multipágina e links de submenu.
- [ ] Validar copy institucional final.

## Comandos úteis
```bash
git add .
git commit -m "Refatora estrutura multipágina do site CEDE e consolida navegação institucional"
git push origin deploy-cedern
```
