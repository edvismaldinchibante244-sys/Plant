# CI Security Gate

Pipeline de seguranca automatizado em:

- `.github/workflows/security-gate.yml`

## O que o gate valida

1. Lint PHP (`src/public`, `app/src`, `tools`)
2. Vulnerabilidades de dependencias PHP (`composer audit`)
3. Vulnerabilidades de dependencias Node (`npm audit --audit-level=high`)
4. Secret scanning (`gitleaks`)
5. Hardening baseline (`tools/security_preflight.php --json`)
6. Operacao nivel 4 (`tools/security_ops_preflight.php --json`)
7. Readiness mensal (`tools/security_readiness_report.php --json`)

## Politica de bloqueio

O merge deve ser bloqueado quando qualquer etapa falhar.

## Artefato gerado

- `docs/security/SECURITY_MONTHLY_READINESS.md` (uploadado como artifact no job)

## Requisitos para branch protection

No GitHub, marque o check `Security Gate / security-gate` como obrigatorio para merge.

