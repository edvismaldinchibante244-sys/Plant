# Security Governance Schedule

Este arquivo define a rotina corporativa para manter nivel 9+ de seguranca.

## Cadencias obrigatorias

- Revisao de evidencias WAF: a cada 30 dias.
- Pentest externo independente: a cada 90 dias.
- Reteste formal de pentest: sempre apos correcao critica/alta.
- DR drill tecnico (restore validado): a cada 30 dias.
- IR tabletop (simulado de incidente): a cada 15 dias.

## Fonte de verdade automatizada

O status operacional e calculado por:

- `docs/security/security_governance.json`
- `tools/security_ops_preflight.php`

Sempre que concluir uma atividade (WAF review, pentest, reteste, DR ou IR), atualize as datas no JSON e anexe evidencias nos arquivos:

- `docs/security/WAF_EVIDENCE.md`
- `docs/security/PENTEST_EVIDENCE.md`
- `docs/security/DR_DRILL_EVIDENCE.md`

