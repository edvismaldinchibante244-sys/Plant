# Security Operations Level 4 (Production SaaS)

Este documento fecha os controles operacionais faltantes para nivel corporativo.

## 1) WAF de borda gerenciado

Objetivo: bloquear trafego malicioso antes de chegar ao servidor.

### Baseline obrigatoria

- Ativar WAF gerenciado do provedor (Cloudflare/AWS/Azure).
- Modo de protecao: bloqueio ativo (nao apenas monitoramento).
- Regras minimas:
  - SQL Injection
  - XSS
  - Path Traversal / LFI probes
  - Bot/Scanner conhecidos
  - Rate limit por IP para `/api/login_process.php`, `/api/esqueci_senha.php`, `/api/reserva_publica.php`, `/api/gateway_webhook_plano.php`
- Geoblocking opcional por risco.
- Permitir apenas metodos esperados por rota (GET/POST/PUT/PATCH/DELETE).

### Politica de resposta

- Bloqueio imediato quando score de ameaca >= alto.
- Challenge para score medio.
- Registrar eventos no SIEM.

## 2) SIEM central com resposta automatica

Objetivo: consolidar eventos de seguranca e disparar acao automatica.

### Fontes do sistema

- `app/storage/logs/security.log`
- `app/storage/logs/security_alerts.log`
- tabela `audit_logs`

### Alertas minimos

- `waf_blocked_request` em burst
- `login_rate_limited` acima do limiar
- `tenant_violation` >= 1
- `authorization_denied` em volume anormal
- falhas de webhook de plano por assinatura/integridade

### Resposta automatica recomendada

- Enviar alerta para canal SOC (email/Slack/Teams).
- Aplicar bloqueio temporario no WAF para IP reincidente.
- Abrir incidente (SEV-2/SEV-1) automaticamente.

## 3) Pentest externo independente recorrente

Objetivo: validacao imparcial por equipe externa.

### Frequencia

- Trimestral (minimo) + reteste apos mudancas criticas.

### Escopo minimo

- Autenticacao, 2FA e sessao
- RBAC e bypass de permissao
- Multi-tenant (IDOR)
- Planos/pagamento/webhooks
- Upload e restauracao de backup
- APIs publicas e privadas

### Entregaveis obrigatorios

- Relatorio executivo
- Relatorio tecnico com PoC e CVSS
- Evidencia de reteste com status corrigido

## 4) Exercicios formais de DR/IR em producao

Objetivo: provar continuidade operacional e resposta a incidente.

### Frequencia

- DR tecnico mensal (restore real em ambiente isolado)
- Tabletop IR quinzenal
- Simulado completo trimestral

### Metas

- RPO <= 24h (alvo 1h para clientes criticos)
- RTO <= 4h

### Evidencias

- tempo de deteccao, contencao e recuperacao
- gaps e plano de acao com prazo e responsavel

## 5) Checklist de ativacao final

- [ ] WAF de borda em modo bloqueio ativo.
- [ ] SIEM recebendo logs `security.log` e `security_alerts.log`.
- [ ] Regra automatica de bloqueio para abuso recorrente.
- [ ] Pentest externo contratado e agendado.
- [ ] Exercicio DR/IR executado com evidencias.

## 6) Automacao continua (implementado no projeto)

Foi adicionado um runner operacional unico:

- `tools/security_ops_runner.php`

Esse runner executa em sequencia:

1. `tools/security_monitor.php` (correlacao por janela de tempo)
2. `tools/security_siem_reactor.php` (bloqueio automatico de IP por limiar)
3. `tools/security_preflight.php` (hardening tecnico)
4. `tools/security_ops_preflight.php` (controles operacionais L4)

Saidas geradas automaticamente:

- `app/storage/logs/security_ops_status.json` (ultimo estado consolidado)
- `app/storage/logs/security_ops_runner.log` (historico de execucoes)
- validacao de cadencia operacional em `docs/security/security_governance.json`

Comandos manuais:

```bash
php tools/security_ops_runner.php
php tools/security_ops_runner.php --json
```

Windows Task Scheduler (a cada 10 minutos):

```powershell
schtasks /Create /TN "RestauranteSaaS\SecurityOpsRunner" /SC MINUTE /MO 10 /TR "powershell.exe -NoProfile -ExecutionPolicy Bypass -File \"C:\xampp\htdocs\V0001\V00\run_security_ops_cron.ps1\"" /RL HIGHEST /F
schtasks /Run /TN "RestauranteSaaS\SecurityOpsRunner"
```
