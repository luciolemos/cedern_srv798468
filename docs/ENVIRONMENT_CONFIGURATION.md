# Configuração de Ambiente do CEDE

Este documento explica o papel das variáveis de ambiente usadas pelo projeto `cedern`.
O objetivo é manter produção, desenvolvimento e homologação previsíveis, sem espalhar
segredos em arquivos versionados.

## Regras gerais

- O arquivo real `.env` não deve ser versionado.
- O arquivo versionado do projeto deve ser apenas o [.env.example](/var/www/cedern/.env.example).
- Senhas, tokens, chaves SMTP, credenciais de banco e `RECAPTCHA_SECRET_KEY` devem existir apenas no servidor ou no ambiente local.
- Sempre que um segredo real for compartilhado em chat, ticket, e-mail ou qualquer outro canal fora do servidor, o correto é rotacioná-lo.

## Estrutura recomendada

Organize o `.env` em blocos:

1. Bootstrap e runtime
2. Aparência e seletor de tema
3. Metadados institucionais e redes sociais
4. Agenda pública e rotas de diagnóstico
5. reCAPTCHA
6. Banco de dados
7. Credenciais auxiliares do Admin Agenda
8. E-mail transacional
9. Uploads e armazenamento público

## Referência das variáveis

### 1. Bootstrap e runtime

- `APP_ENV`: define o perfil geral da aplicação. Aceitos: `production`, `development`, `test`, `local`, `dev`.
- `APP_ENV_FILE`: opcional. Permite mandar o bootstrap carregar outro arquivo em vez do `.env` padrão.
- `APP_LOG_PATH`: caminho absoluto do log da aplicação. Em Hostinger normalmente fica fora do projeto.
- `docker`: opcional. Quando verdadeiro, o logger pode preferir `stdout`.
- `APP_BASE`: subdiretório de instalação. Use vazio quando o site roda na raiz do domínio; use `/cedern` quando roda em `https://host/cedern/`.
- `APP_ASSET_VERSION`: versão manual dos assets. Troque o valor para quebrar cache de CSS, JS, ícones e templates que dependem de asset busting.

### 2. Aparência e seletor de tema

- `APP_DEFAULT_THEME`: tema padrão da interface antes de qualquer preferência do navegador.
- `APP_DEFAULT_MODE`: modo padrão (`light` ou `dark`).
- `APP_DEFAULT_DARK_INTENSITY`: intensidade padrão do dark mode (`neutral` ou `vivid`).
- `APP_THEME_ALLOWED_THEMES`: lista separada por vírgulas com os temas liberados no seletor.
- `APP_THEME_ALLOWED_MODES`: lista separada por vírgulas com os modos liberados no seletor.
- `APP_THEME_ALLOWED_DARK_INTENSITIES`: intensidades de escuro liberadas quando o modo `dark` existe.
- `APP_ENABLE_THEME_PALETTE`: liga ou desliga o seletor no site público.
- `APP_ENABLE_DASHBOARD_THEME_PALETTE`: liga ou desliga o seletor no dashboard administrativo.

Comportamento atual:

- Quando o seletor está desligado, o site usa os defaults do `.env`.
- Quando o seletor está ligado, preferências salvas no navegador continuam tendo prioridade.
- Quando houver apenas uma opção útil, o seletor se recolhe sozinho.

### 3. Metadados institucionais e redes sociais

- `APP_DEFAULT_PAGE_TITLE`: título padrão das páginas para SEO e compartilhamento.
- `APP_DEFAULT_PAGE_DESCRIPTION`: descrição padrão institucional.
- `APP_DEFAULT_PAGE_URL`: URL canônica base do site.
- `APP_DEFAULT_PAGE_IMAGE`: imagem padrão para Open Graph e Twitter Card.
- `APP_DEFAULT_SITE_NAME`: nome do site usado em `og:site_name`.
- `APP_DEFAULT_TWITTER_SITE`: identificador do Twitter/X.
- `APP_SOCIAL_FACEBOOK_URL`: link institucional do Facebook.
- `APP_SOCIAL_INSTAGRAM_URL`: link institucional do Instagram.

### 4. Agenda pública e rotas de diagnóstico

- `APP_AGENDA_PUBLIC_LIMIT`: quantidade máxima de eventos públicos exibidos.
- `APP_ENABLE_DIAGNOSTIC_ROUTES`: libera rotas auxiliares de diagnóstico. Deve ficar `false` em produção.

### 5. reCAPTCHA

- `RECAPTCHA_ENABLED`: ativa ou desativa a validação do reCAPTCHA.
- `RECAPTCHA_SITE_KEY`: chave pública usada pelo frontend.
- `RECAPTCHA_SECRET_KEY`: chave privada usada pelo backend.
- `RECAPTCHA_MIN_SCORE`: nota mínima esperada do token.
- `RECAPTCHA_ALLOWED_HOSTNAME`: hostname que o token deve informar como origem válida.

### 6. Banco de dados

- `DB_HOST`: host do MySQL.
- `DB_PORT`: porta do MySQL.
- `DB_NAME`: nome do banco.
- `DB_USER`: usuário do banco.
- `DB_PASS`: senha do banco.
- `DB_CHARSET`: charset da conexão, normalmente `utf8mb4`.
- `DB_TIMEZONE`: timezone da conexão.

### 7. Credenciais auxiliares do Admin Agenda

- `ADMIN_AGENDA_USER`: usuário de Basic Auth do módulo.
- `ADMIN_AGENDA_PASS`: senha de Basic Auth do módulo.

### 8. E-mail transacional

O código hoje usa o contrato `MAIL_*`.

- `MAIL_HOST`: servidor SMTP.
- `MAIL_PORT`: porta SMTP.
- `MAIL_USERNAME`: usuário SMTP.
- `MAIL_PASSWORD`: senha SMTP.
- `MAIL_FROM_ADDRESS`: remetente real do e-mail.
- `MAIL_FROM_NAME`: nome amigável do remetente.
- `MAIL_TO_ADDRESS`: destinatário principal das notificações institucionais.
- `MAIL_PUBLIC_EMAIL`: e-mail público exibido no site.
- `MAIL_ENCRYPTION`: criptografia, normalmente `ssl` ou `tls`.
- `MAIL_TIMEOUT`: timeout do PHPMailer em segundos.
- `MAIL_SMTP_DEBUG`: habilita debug SMTP em log.
- `MAIL_ALLOW_EXTERNAL_REPLYTO`: quando `true`, permite `Reply-To` externo informado pelo visitante.

### 9. Uploads e armazenamento público

- `LIBRARY_UPLOAD_DIR`: diretório físico dos documentos da biblioteca.
- `LIBRARY_UPLOAD_PUBLIC_PREFIX`: prefixo público desses documentos.
- `LIBRARY_COVER_UPLOAD_DIR`: diretório físico das capas da biblioteca.
- `LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX`: prefixo público das capas da biblioteca.
- `BOOKSHOP_COVER_UPLOAD_DIR`: diretório físico das capas da livraria.
- `BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX`: prefixo público das capas da livraria.

## Recomendações para os ambientes atuais

### Produção em `https://cedern.org/`

- `APP_ENV=production`
- `APP_ENABLE_THEME_PALETTE=false`
- `APP_ENABLE_DASHBOARD_THEME_PALETTE=true`
- `APP_DEFAULT_THEME=amber`
- `APP_DEFAULT_MODE=light`
- `APP_DEFAULT_DARK_INTENSITY=neutral`
- `APP_BASE=""` se o app roda na raiz do domínio

Observação:
Se a produção estiver acessível na raiz `https://cedern.org/`, `APP_BASE="/cedern"` vira configuração herdada, não a ideal. O projeto tem fallback defensivo, então ele pode continuar funcionando, mas o valor correto para domínio raiz é vazio.

### Desenvolvimento em `https://srv798468.hstgr.cloud/cedern/`

- `APP_ENV=production` pode ser mantido se a intenção for simular o comportamento real do público.
- `APP_BASE="/cedern"`
- `APP_ENABLE_THEME_PALETTE=false` se quiser espelhar o site público.
- `APP_ENABLE_DASHBOARD_THEME_PALETTE=true` para manter personalização no painel.

## Procedimento seguro ao editar o `.env`

1. Ajuste as variáveis no servidor correto.
2. Se a mudança afetar CSS, JS ou marcação renderizada, incremente `APP_ASSET_VERSION`.
3. Se houver cache Twig/container no ambiente, limpe ou force novo namespace de cache.
4. Faça uma checagem simples no navegador:
   - home pública
   - login público
   - login do painel
   - dashboard autenticado
5. Em alterações de SMTP, teste contato, recuperação de senha e cadastros que disparam e-mail.

## Arquivos relacionados

- Template versionado: [.env.example](/var/www/cedern/.env.example)
- Resolução de tema: [ThemeConfig.php](/var/www/cedern/src/Support/ThemeConfig.php)
- Injeção de variáveis no Twig: [dependencies.php](/var/www/cedern/app/dependencies.php)
- Bootstrap do `.env`: [index.php](/var/www/cedern/public/index.php)
