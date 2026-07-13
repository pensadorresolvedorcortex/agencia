# Sistema de Ordens Administrativas

Sistema web para emissão de Ordens de Fornecimento (OF) e Ordens de Serviço (OS), integrado ao controle de saldo de contratos administrativos por contrato, lote e item.

## Arquitetura proposta

- **Frontend:** Next.js App Router com TypeScript e Tailwind CSS.
- **Backend:** Route Handlers e Server Actions do Next.js, com validação por Zod.
- **Banco de dados:** PostgreSQL acessado via Prisma ORM.
- **Autenticação:** sessão HTTP-only assinada, perfis de acesso e trilha de auditoria.
- **Documentos:** camada futura de armazenamento com metadados no banco e objetos em volume/S3 compatível.
- **Relatórios:** geração futura de PDF para ordens e exportação Excel para saldos, execução e auditoria.
- **Execução local:** Docker Compose para PostgreSQL e scripts npm para aplicação.

## Etapas de implementação

1. **Base segura do projeto:** estrutura Next.js, Tailwind, Prisma, Docker, autenticação, perfis, cadastro de usuários e auditoria inicial.
2. **Cadastro contratual:** contratos, fornecedores, lotes, itens, vigência, valores, quantitativos e anexos.
3. **Motor de saldos:** apuração por contrato/lote/item, reservas, emissão, cancelamentos, estornos e bloqueio de estouro.
4. **Ordens de Fornecimento e Serviço:** fluxo de criação, numeração, aprovação, emissão e PDF.
5. **Medição/recebimento:** registro de entregas/serviços, documentos fiscais, aceite e abatimento de saldo.
6. **Relatórios e exportações:** Excel, painéis de execução, alertas de vigência/saldo e relatório de auditoria.
7. **Hardening:** testes automatizados, rate limit, logs estruturados, backup, política de retenção e revisão de segurança.

## Modelo de banco de dados proposto

- `User`, `Role/Profile`, `AuditLog` para autenticação, autorização e rastreabilidade.
- `Supplier` para fornecedores.
- `AdministrativeContract` com número, processo, objeto, datas, status, valor global e unidade gestora.
- `ContractLot` para agrupamento por lote.
- `ContractItem` com descrição, unidade, quantidade, valor unitário e saldo contratado.
- `SupplyOrder` e `ServiceOrder` com tipo, número, status, requisitante, aprovador e vínculo contratual.
- `OrderItem` com item contratado, quantidade, valor unitário, valor total e saldo reservado/executado.
- `BalanceLedger` como razão imutável de movimentos: contrato inicial, aditivo, reserva, emissão, cancelamento, medição e estorno.
- `Document` para metadados, hash, origem, entidade vinculada e caminho de armazenamento.

## Regras críticas de cálculo e auditoria

- Toda ordem deve validar saldo disponível por **contrato, lote e item** antes de reservar ou emitir.
- Saldo disponível = valor contratado + aditivos - reservas ativas - valores emitidos/executados + cancelamentos/estornos permitidos.
- Quantidade e valor total devem ser calculados com precisão decimal; arredondamentos precisam ser padronizados e auditáveis.
- Nenhuma operação pode permitir valor ou quantidade acima do saldo contratado vigente.
- Alterações financeiras devem gerar lançamento imutável no `BalanceLedger` e registro em `AuditLog`.
- Cancelamentos e estornos devem referenciar a transação original.
- Documentos devem ter hash para integridade e vínculo explícito com usuário, data e entidade.
- Perfis mínimos: `ADMIN`, `GESTOR_CONTRATOS`, `FISCAL_CONTRATO`, `OPERADOR_ORDENS` e `CONSULTA`.

## Primeira etapa implementada

Esta entrega implementa apenas a base inicial: estrutura do projeto, configuração do banco, autenticação por sessão segura, controle de acesso por perfil, cadastro de usuários e auditoria de login/criação de usuários.

## Execução local

1. Copie `.env.example` para `.env` e ajuste os segredos.
2. Suba o PostgreSQL:

```bash
docker compose up -d
```

3. Instale dependências e prepare o banco:

```bash
npm install
npm run prisma:migrate -- --name init
npm run prisma:seed
npm run dev
```

O usuário administrador padrão é definido por `ADMIN_EMAIL` e `ADMIN_PASSWORD`.
