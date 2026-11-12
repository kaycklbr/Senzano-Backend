# Importação de imóveis

`POST /api/properties/import`, sem parâmetros, com `Authorization: Bearer <JWT>`.
A rota exige autenticação, como as demais operações de imóveis no painel.
Executa `properties:import --json` no processo da requisição e responde apenas ao terminar.
Apenas o Imoview está ativo; o Imobzi continua desabilitado.

- **200**: `status: completed`, `success: true`.
- **409**: `status: conflict`, `success: false`; outra importação (painel, cron ou CLI) mantém o lock.
- **500**: `status: failed`, `success: false`; relatório com os totais parciais e logs até a falha.
- **401**: autenticação ausente ou inválida; resposta padrão da API.

O relatório inclui `message`, `import_id` (UUID), `source`, `started_at`, `finished_at`,
`duration_seconds`, `summary` e `logs` (timestamp ISO 8601, level e message).
`summary` contém `received`, `synced`, `created`, `updated`, `unchanged`, `removed`, `pages`, `errors`.
`received` conta os itens recebidos; `synced = created + updated + unchanged` conta gravações bem sucedidas.
`updated` ignora mudanças apenas em `updated_at` e `synced_at`.
A listagem deve ser completa, com total estável e códigos únicos, antes de qualquer remoção.
Uma lista válida vazia (`lista: [], quantidade: 0`) remove todos os imóveis do Imoview.
Imóveis de outros CRMs não são removidos. Em falhas, as sincronizações já feitas são mantidas.

Os logs exibidos vêm do objeto de relatório criado para cada execução, sem ler `laravel.log`.
Cada execução também possui `storage/logs/property-imports/<import_id>.log` no servidor;
detalhes técnicos de exceções ficam apenas nesse arquivo. Aplicar a política de retenção de logs do servidor a esse diretório.
O lock compartilhado usa o cache configurado e expira após 24 horas em caso de término abrupto.
Em múltiplas instâncias da aplicação, o cache deve ser compartilhado.

O painel está em `/admin/importacao`, mantém a requisição sem timeout do Axios,
mostra um modal durante a execução e avisa ao fechar/recarregar a página.
O PHP não limita a duração do comando; os limites do proxy, servidor HTTP e PHP-FPM
precisam comportar a duração da importação (cada chamada ao Imoview tem timeout de 60 s).
Se a conexão cair, não há repetição automática: a execução pode continuar no servidor.

Verificação local isolada, sem acessar o CRM ou o banco configurado no `.env`:

```sh
php vendor/bin/phpunit -c phpunit.import.xml
```

## Chave do Imoview

Na tela `/admin/importacao`, o editor salva a chave em `settings`, opção `imoview_key`,
criptografada com a chave da aplicação. Não exige alteração de esquema ou migration.
Cadastre a chave no editor antes da primeira execução com esta versão.
Importações, consultas de detalhes e envio de leads usam essa opção, sem fallback para `IMOVIEW_KEY` do ambiente.
Cada importação usa a mesma chave do início ao fim.

- `GET /api/settings/imoview` exige JWT e retorna `configured` e `masked_key` (`*****` seguido dos últimos 5 caracteres, ou `null` sem chave).
- `PUT /api/settings/imoview` exige JWT e recebe `{"key": "<nova chave>"}`.
  Valida chave não vazia, até 4096 caracteres e sem quebras de linha; retorna `configured`, `masked_key` e `message`, sem a chave completa.
- Ambos retornam 401 sem autenticação; o PUT retorna 422 para entradas inválidas, preservando a chave salva.
- `/api/public/config` nunca inclui a opção `imoview_key`.

As respostas das rotas genéricas de `settings` também ocultam o valor de `imoview_key`.

Com uma chave salva, o painel exibe a versão mascarada e o botão Editar. O campo para a nova chave só aparece ao editar; Cancelar descarta o texto digitado.
