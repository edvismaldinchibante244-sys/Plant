<?php

if (!function_exists('turno_normalizar_perfil')) {
    function turno_normalizar_perfil($perfil): string
    {
        $valor = strtoupper(trim((string)$perfil));
        $aliases = [
            'GARÇOM' => 'GARCOM',
            'OPERADOR' => 'GARCOM',
            'FUNCIONARIO' => 'GARCOM',
            'COZINHEIRO' => 'COZINHA',
            'CHEF' => 'COZINHA',
            'BARMAN' => 'BAR',
            'BARTENDER' => 'BAR',
        ];

        return $aliases[$valor] ?? $valor;
    }
}

if (!function_exists('turno_perfis_operacionais')) {
    function turno_perfis_operacionais(): array
    {
        return ['GARCOM', 'CAIXA', 'COZINHA', 'BAR'];
    }
}

if (!function_exists('turno_usuario_exige_turno_ativo')) {
    function turno_usuario_exige_turno_ativo($perfil): bool
    {
        return in_array(turno_normalizar_perfil($perfil), turno_perfis_operacionais(), true);
    }
}

if (!function_exists('turno_pode_intervir_em_outro_funcionario')) {
    function turno_pode_intervir_em_outro_funcionario($perfil): bool
    {
        return in_array(turno_normalizar_perfil($perfil), ['ADMIN', 'CAIXA'], true);
    }
}

if (!function_exists('turno_pode_ver_auditoria')) {
    function turno_pode_ver_auditoria($perfil): bool
    {
        return turno_normalizar_perfil($perfil) === 'ADMIN';
    }
}

if (!function_exists('turno_detectar_tipo_atual')) {
    function turno_detectar_tipo_atual(?DateTimeInterface $agora = null): string
    {
        $agora = $agora ?: new DateTimeImmutable('now');
        $hora = (int)$agora->format('H');

        if ($hora >= 16) {
            return 'NOITE';
        }

        return 'MANHA';
    }
}
