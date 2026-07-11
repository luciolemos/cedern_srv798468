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
9. Gateway de cobrança Asaas
10. Uploads e armazenamento público

## Referência das variáveis

### 1. Bootstrap e runtime

- `APP_ENV`: define o perfil geral da aplicação. Aceitos: `production`, `development`, `test`, `local`, `dev`.
- `APP_ENV_FILE`: opcional. Permite mandar o bootstrap carregar outro arquivo em vez do `.env` padrão.
- `APP_LOG_PATH`: caminho absoluto do log da aplicação. Em Hostinger normalmente fica fora do projeto.
- `APP_ALLOW_REPOSITORY_FALLBACK`: controla se a aplicação pode cair para repositórios fallback quando o MySQL falha. Em produção, o recomendado é `false`.
- `docker`: opcional. Quando verdadeiro, o logger pode preferir `stdout`.
- `APP_BASE`: subdiretório de instalação. Use vazio quando o site roda na raiz do domínio; use `/cedern` quando roda em `https://host/cedern/`. Quando o valor fica vazio, o bootstrap tenta autodetectar o subdiretório a partir de `SCRIPT_NAME` e remove o sufixo `/public` de instalações reescritas, mas produção ainda deve preferir valor explícito.
- `APP_MANAGED_STORAGE_ROOT`: opcional. Define uma raiz única para uploads gerenciados fora da pasta publicada/versionada, por exemplo `/home/usuario/cedern-storage`.
- `APP_ENABLE_LEGACY_MEDIA_FALLBACK`: opcional. Quando `true`, libera leitura temporária de fotos de membros ainda presas em `public/assets/...`. Livraria, biblioteca e patrimônio continuam com compatibilidade legada de leitura enquanto a migração completa desses buckets não for auditada.
- `APP_ASSET_VERSION`: versão manual usada para trocar o namespace dos caches públicos e internos. Além de quebrar cache de CSS, JS, ícones e marcação renderizada, o projeto também usa esse valor para mudar o diretório do container compilado e do cache do Twig. Em produção, quando um deploy alterar definições do container, templates ou bootstrap, incremente esse valor.

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
- `APP_DIAGNOSTIC_TOKEN`: token exigido para acessar rotas diagnósticas fora de ambientes de desenvolvimento.

### 5. reCAPTCHA

- `RECAPTCHA_ENABLED`: ativa ou desativa a validação do reCAPTCHA.
- `RECAPTCHA_SITE_KEY`: chave pública usada pelo frontend.
- `RECAPTCHA_SECRET_KEY`: chave privada usada pelo backend.
- `RECAPTCHA_MIN_SCORE`: nota mínima esperada do token. Baseline operacional atual do projeto para `reCAPTCHA v3`: `0.1`.
- `RECAPTCHA_ALLOWED_HOSTNAME`: hostname que o token deve informar como origem válida.

Regra prática atual:

- `0.5` se mostrou agressivo demais para o tráfego real do CEDE em produção.
- `0.1` é o valor de estabilização adotado após o incidente de julho de 2026.
- Se a produção permanecer estável por alguns dias, a revisão deve ser gradual: `0.2`, depois `0.3`, nunca pular direto sem teste.
- Se o fluxo crítico continuar sofrendo falso positivo, o caminho mais previsível é migrar login/cadastro para `reCAPTCHA v2` checkbox.

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

### 9. Gateway de cobrança Asaas

- `ASAAS_ENVIRONMENT`: ambiente da API. Use `sandbox` em desenvolvimento e `production` apenas no ambiente real.
- `ASAAS_API_KEY`: chave da API do Asaas.
- `ASAAS_WEBHOOK_TOKEN`: token opcional de validação do webhook.
- `ASAAS_USER_AGENT`: identificador do integrador nas chamadas.
- `ASAAS_CUSTOMER_NOTIFICATION_DISABLED`: controla se o Asaas envia as notificações próprias ao cliente.
- `ASAAS_ALLOW_PRODUCTION_IN_NON_PRODUCTION`: cerca de segurança. Mantenha `false` em desenvolvimento, homologação e testes.

Regra prática:

- `APP_ENV=development` ou equivalente deve andar com `ASAAS_ENVIRONMENT=sandbox`.
- Se `APP_ENV` não for produção e `ASAAS_ENVIRONMENT=production`, o gateway fica bloqueado por padrão.
- Só use `ASAAS_ALLOW_PRODUCTION_IN_NON_PRODUCTION=true` para um teste deliberado e temporário.

### 10. Uploads e armazenamento público

- Quando `APP_MANAGED_STORAGE_ROOT` estiver definido, qualquer diretório relativo iniciado por `var/storage/` passa a ser rebaseado para essa raiz compartilhada. Diretórios absolutos continuam respeitados exatamente como informados.
- Com `APP_MANAGED_STORAGE_ROOT` ativo, a aplicação deixa de usar `var/storage/...` do release atual como fallback implícito de leitura; o ambiente precisa estar consistente no storage compartilhado.
- Quando `APP_MANAGED_STORAGE_ROOT` estiver vazio ou comentado, os buckets padrão continuam em `var/storage/...` dentro do projeto.
- Quando `APP_ENABLE_LEGACY_MEDIA_FALLBACK=true`, fotos de membros também podem aceitar paths legados diretos `assets/...` durante uma janela de migração.
- O fluxo operacional principal de producao esta em [PRODUCTION_OPERATIONS_RUNBOOK.md](/var/www/cedern/docs/PRODUCTION_OPERATIONS_RUNBOOK.md).
- O contrato funcional completo de banco, URL pública, storage físico e deploy de mídia gerenciada está em [MANAGED_MEDIA_STANDARD.md](/var/www/cedern/docs/MANAGED_MEDIA_STANDARD.md).
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
- `APP_ALLOW_REPOSITORY_FALLBACK=false`
- `APP_BASE=""` se o app roda na raiz do domínio
- `RECAPTCHA_MIN_SCORE=0.1`
- `ASAAS_ENVIRONMENT=production`
- `ASAAS_ALLOW_PRODUCTION_IN_NON_PRODUCTION=false`

Observação:
Se a produção estiver acessível na raiz `https://cedern.org/`, `APP_BASE="/cedern"` vira configuração herdada, não a ideal. O projeto agora consegue autodetectar subdiretórios quando `APP_BASE` vier vazio, mas o valor correto para domínio raiz continua sendo vazio.

### Desenvolvimento em `https://srv798468.hstgr.cloud/cedern/`

- `APP_ENV=development`
- `APP_BASE="/cedern"`
- `APP_ENABLE_THEME_PALETTE=false` se quiser espelhar o site público.
- `APP_ENABLE_DASHBOARD_THEME_PALETTE=true` para manter personalização no painel.
- `ASAAS_ENVIRONMENT=sandbox`
- `ASAAS_ALLOW_PRODUCTION_IN_NON_PRODUCTION=false`

Observação:
Não mantenha `APP_ENV=production` no ambiente de desenvolvimento só para simular produção. Para aparência e comportamento público, ajuste as flags específicas; para cobrança, a combinação segura continua sendo desenvolvimento com Asaas sandbox.

## Procedimento seguro ao editar o `.env`

1. Ajuste as variáveis no servidor correto.
2. Se a mudança afetar CSS, JS ou marcação renderizada, incremente `APP_ASSET_VERSION`.
3. Se houver cache Twig/container no ambiente, limpe ou force novo namespace de cache.
4. Faça uma checagem simples no navegador:
   - home pública
   - login público
   - login do painel
   - dashboard autenticado
   - `/health/readiness?token=...` quando o diagnóstico estiver habilitado
5. Em alterações de SMTP, teste contato, recuperação de senha e cadastros que disparam e-mail.

## Política operacional de fallback

- Em `development`, `local`, `test`, `qa` e `homolog`, o projeto pode usar repositórios fallback para não travar o ambiente de trabalho.
- Em `production`, o padrão agora é estrito: se o repositório MySQL falhar ao inicializar, a aplicação registra erro crítico e falha alto.
- Só ligue `APP_ALLOW_REPOSITORY_FALLBACK=true` em produção como medida temporária e consciente de contingência.

## Checklist de encerramento do incidente de reCAPTCHA

Quando a produção apresentar mensagens como `Sua solicitacao nao passou na verificacao de seguranca. Tente novamente.`, encerre o incidente apenas depois de validar:

1. `RECAPTCHA_MIN_SCORE` real do servidor.
2. `RECAPTCHA_ALLOWED_HOSTNAME` compatível com o domínio final.
3. Login público funcionando com usuário real.
4. Cadastro público funcionando.
5. Recuperação de senha funcionando.
6. Formulário de contato funcionando.
7. Log sem novos registros recorrentes de `reCAPTCHA score below threshold.` após o ajuste.

Se os itens 3 a 6 passarem e o log parar de acumular rejeições por score, o incidente pode ser considerado estabilizado.

## Rota consolidada de prontidão

Quando `APP_ENABLE_DIAGNOSTIC_ROUTES=true`, a aplicação expõe `GET /health/readiness`.

O relatório consolida:

- conectividade PDO;
- classe real de cada repositório e detecção de fallback;
- status dos patches SQL;
- gravabilidade do logger;
- prontidão dos diretórios de storage gerenciado;
- proteções operacionais do ambiente.

Em produção, a rota exige `APP_DIAGNOSTIC_TOKEN` e deve ser chamada com `?token=...` ou header `X-Diagnostic-Token`.

## Arquivos relacionados

- Template versionado: [.env.example](/var/www/cedern/.env.example)
- Resolução de tema: [ThemeConfig.php](/var/www/cedern/src/Support/ThemeConfig.php)
- Injeção de variáveis no Twig: [dependencies.php](/var/www/cedern/app/dependencies.php)
- Bootstrap do `.env`: [index.php](/var/www/cedern/public/index.php)
- Runbook principal de producao: [PRODUCTION_OPERATIONS_RUNBOOK.md](/var/www/cedern/docs/PRODUCTION_OPERATIONS_RUNBOOK.md)
- Padrão de mídia gerenciada: [MANAGED_MEDIA_STANDARD.md](/var/www/cedern/docs/MANAGED_MEDIA_STANDARD.md)
