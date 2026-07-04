# Automação de Cobranças de Contribuição

Este documento descreve o fluxo recomendado para manter o **SISCEDE** como fonte da recorrência
e usar o **Asaas** como emissor, notificador e recebedor das cobranças.

## Modelo adotado

O fluxo atual recomendado é:

1. o SISCEDE gera a cobrança local da competência;
2. o SISCEDE cria a cobrança externa no Asaas;
3. o Asaas envia notificações ao cliente conforme o cadastro e as regras dele;
4. o webhook do Asaas atualiza a cobrança local quando houver mudança de status.

Este modelo preserva no SISCEDE:

- competência mensal;
- situação local (`pendente`, `recebida`, `isenta`);
- histórico administrativo;
- inadimplência por associado;
- coerência com a área do membro e o painel financeiro.

## Pré-requisitos

Antes de automatizar por cron, confirme:

- `ASAAS_API_KEY` configurada no ambiente correto;
- `ASAAS_ENVIRONMENT` consistente com o ambiente;
- `ASAAS_WEBHOOK_TOKEN` configurado quando o webhook for protegido;
- `ASAAS_CUSTOMER_NOTIFICATION_DISABLED=false` se o Asaas for o canal oficial de aviso;
- `APP_DEFAULT_PAGE_URL` apontando para o ambiente correto;
- usuários contribuintes com:
  - `association_status=member`
  - `status=active`
  - `is_contributor=1`
  - `preferred_due_day` preenchido
  - `contribution_amount` preenchido
  - `preferred_payment_method` coerente com a operação.

## Comando manual

Rodar para a competência atual:

```bash
composer billing:contributions:run -- --billing-mode=preferred
```

Rodar para uma competência específica:

```bash
composer billing:contributions:run -- --competence=2026-07 --billing-mode=preferred
```

Forçar todo mundo em boleto:

```bash
composer billing:contributions:run -- --competence=2026-07 --billing-mode=boleto
```

## Comportamento do comando

O script faz duas etapas:

1. gera as cobranças locais que ainda não existem para a competência;
2. cria no Asaas apenas as cobranças locais pendentes que ainda não possuem `gateway_payment_id`.

Ele é idempotente para a mesma competência:

- não recria cobrança local já existente;
- não recria cobrança externa já vinculada no Asaas.

## Lock de execução

Por padrão, o script usa lock em:

```txt
var/locks/contribution-billing-cycle.lock
```

Isso evita concorrência entre execuções de cron.

Opções disponíveis:

- `--lock-file=/caminho/arquivo.lock`
- `--no-lock`

## Códigos de saída

- `0`: sucesso sem falhas externas
- `1`: erro de configuração ou falha de execução
- `2`: processamento concluído, mas houve falhas ao criar cobranças no Asaas
- `3`: já existe outro processo em execução

## Cron recomendado

Como o processo é idempotente, a recomendação pragmática é rodar **uma vez por dia**.
Assim, se um novo contribuinte for regularizado no meio do mês, ele entra no próximo ciclo sem depender
de execução manual esquecida.

Exemplo em produção:

```cron
5 6 * * * cd /var/www/cedern && /usr/bin/composer billing:contributions:run -- --billing-mode=preferred >> /var/log/cedern-contribution-billing.log 2>&1
```

Exemplo em desenvolvimento com `APP_ENV_FILE` explícito:

```cron
5 6 * * * cd /var/www/cedern && APP_ENV_FILE=/var/www/cedern/.env /usr/bin/composer billing:contributions:run -- --billing-mode=preferred >> /var/log/cedern-contribution-billing-dev.log 2>&1
```

Se o servidor não tiver `composer` no path, use o PHP diretamente:

```cron
5 6 * * * cd /var/www/cedern && /usr/bin/php scripts/process_contribution_billing_cycle.php --billing-mode=preferred >> /var/log/cedern-contribution-billing.log 2>&1
```

## Webhook do Asaas

Para a automação ficar completa, o webhook precisa continuar apontando para:

- desenvolvimento: `https://srv798468.hstgr.cloud/cedern/webhooks/asaas/contribuicoes`
- produção: `https://cedern.org/webhooks/asaas/contribuicoes`

O webhook atual:

- localiza a cobrança local por `gateway_payment_id`;
- atualiza os dados retornados pelo Asaas;
- marca a cobrança local como recebida quando o gateway voltar com status pago.

## Limitações atuais

- o processo cria cobranças avulsas no Asaas, não assinaturas recorrentes;
- cobranças criadas diretamente no painel do Asaas continuam fora do fluxo local do SISCEDE;
- o vínculo persistente com o cliente do Asaas ainda está por cobrança (`gateway_customer_id`) e não em um cadastro próprio de integração por membro.

## Quando usar assinatura do Asaas

Não é o modelo principal recomendado neste momento.

Assinaturas do Asaas fazem sentido quando o Asaas precisa ser a fonte primária da recorrência.
Hoje o SISCEDE ainda trata melhor o cenário em que:

- a competência nasce localmente;
- a emissão externa acontece depois;
- o retorno de pagamento volta pelo webhook.
