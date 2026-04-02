CREATE TABLE IF NOT EXISTS funcionarios_turnos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL,
    restaurante_id BIGINT UNSIGNED NOT NULL,
    cargo VARCHAR(30) NOT NULL,
    data DATE NOT NULL,
    data_saida DATE NULL DEFAULT NULL,
    turno VARCHAR(20) NOT NULL DEFAULT 'MANHA',
    hora_entrada TIME NOT NULL,
    hora_saida TIME NULL DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ATIVO',
    observacoes TEXT NULL,
    motivo_intervencao TEXT NULL,
    responsavel_abertura_id BIGINT UNSIGNED NULL DEFAULT NULL,
    responsavel_fechamento_id BIGINT UNSIGNED NULL DEFAULT NULL,
    abertura_manual TINYINT(1) NOT NULL DEFAULT 0,
    fechamento_manual TINYINT(1) NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_turnos_restaurante_status (restaurante_id, status, data),
    INDEX idx_turnos_usuario_status (usuario_id, status),
    INDEX idx_turnos_restaurante_data (restaurante_id, data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS caixa_turnos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurante_id BIGINT UNSIGNED NOT NULL,
    caixa_id BIGINT UNSIGNED NOT NULL,
    turno_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    data_abertura DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_fechamento DATETIME NULL DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ABERTO',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_caixa_turno_aberto (caixa_id, turno_id),
    INDEX idx_caixa_turnos_restaurante_status (restaurante_id, status),
    INDEX idx_caixa_turnos_turno (turno_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria_turnos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurante_id BIGINT UNSIGNED NOT NULL,
    turno_id BIGINT UNSIGNED NULL DEFAULT NULL,
    responsavel_id BIGINT UNSIGNED NOT NULL,
    funcionario_afetado_id BIGINT UNSIGNED NOT NULL,
    tipo_acao VARCHAR(40) NOT NULL,
    motivo TEXT NOT NULL,
    payload_json JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auditoria_restaurante_data (restaurante_id, criado_em),
    INDEX idx_auditoria_funcionario (funcionario_afetado_id, criado_em),
    INDEX idx_auditoria_tipo (tipo_acao, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
