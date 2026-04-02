-- View de resumo de uso dos planos.
-- Execute este script depois de importar as tabelas `restaurantes` e `plano_logs`.

DROP VIEW IF EXISTS `v_plano_usage_resumo`;

CREATE ALGORITHM=UNDEFINED
SQL SECURITY DEFINER
VIEW `v_plano_usage_resumo` AS
select
    `r`.`id` AS `restaurante_id`,
    `r`.`nome` AS `restaurante_nome`,
    `r`.`plano` AS `plano`,
    `pl`.`funcionalidade` AS `funcionalidade`,
    count(case when `pl`.`acao` = 'BLOQUEADO' then 1 end) AS `bloqueados`,
    count(case when `pl`.`acao` = 'SUCESSO' then 1 end) AS `exitos`,
    count(`pl`.`id`) AS `total_tentativas`,
    max(`pl`.`created_at`) AS `ultima_tentativa`
from (`restaurantes` `r`
left join `plano_logs` `pl` on(`r`.`id` = `pl`.`restaurante_id`))
group by `r`.`id`,`r`.`nome`,`r`.`plano`,`pl`.`funcionalidade`;
