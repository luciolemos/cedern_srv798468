## Resumo

Descreva objetivamente o que mudou e o motivo.

## Tipo de mudanca

- [ ] Conteudo (textos/links em `app/content/home.php`)
- [ ] UI/estilo (Twig/CSS/JS)
- [ ] CI/qualidade (workflows/checks)
- [ ] Outro

## Checklist rapido

- [ ] Mantive a estrutura dos arrays de conteudo (sem remover chaves obrigatorias)
- [ ] Validei links alterados (`href`) localmente
- [ ] Mantive delays de animacao em ordem progressiva (quando aplicavel)
- [ ] Rodei os testes/checks aplicaveis localmente
- [ ] Se houve mudanca visual intencional, rodei `npm run test:visual:update` e versionei snapshots

## Validacoes executadas

- [ ] `vendor/bin/phpunit --configuration phpunit.xml`
- [ ] `vendor/bin/phpstan analyse --configuration phpstan.neon.dist --no-progress`
- [ ] `vendor/bin/phpcs --standard=phpcs.xml --extensions=php -n src app tests`
- [ ] `npm run test:visual`

## Evidencias (opcional)

Inclua prints, GIFs, links de preview ou observacoes relevantes.
