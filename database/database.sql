
-- ============================================================================
-- SENAI DEV EXPERIENCE 2026 - MÓDULO A
-- SENN AI-X - BANCO DE DADOS
-- ATENÇÃO: ESTE SCRIPT APAGA E RECRIA O BANCO EXISTENTE.
-- CONTAS DE TESTE: senha = password
-- Altere as credenciais antes de usar o sistema em produção.
-- ============================================================================

DROP DATABASE IF EXISTS senn_logistica_db;

CREATE DATABASE senn_logistica_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE senn_logistica_db;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. USUÁRIOS E PERFIS (RF01)
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM(
        'administrador',
        'gestor',
        'despachante',
        'motorista'
    ) NOT NULL DEFAULT 'despachante',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. VEÍCULOS (RF02)
DROP TABLE IF EXISTS veiculos;

CREATE TABLE veiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    placa VARCHAR(10) UNIQUE NOT NULL,
    modelo VARCHAR(50) NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    capacidade_kg DECIMAL(10,2) NOT NULL,
    capacidade_m3 DECIMAL(10,2) NOT NULL,
    autonomia_km_l DECIMAL(5,2) DEFAULT 10.0,
    status ENUM(
        'disponivel',
        'em_rota',
        'em_manutencao'
    ) DEFAULT 'disponivel',
    data_proxima_manutencao DATE NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. MOTORISTAS (RF03)
DROP TABLE IF EXISTS motoristas;

CREATE TABLE motoristas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cpf VARCHAR(14) UNIQUE NOT NULL,
    cnh VARCHAR(20) NOT NULL,
    categoria_cnh VARCHAR(5) NOT NULL,
    validade_cnh DATE NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    status ENUM(
        'disponivel',
        'em_rota',
        'inativo'
    ) DEFAULT 'disponivel',
    veiculo_id INT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_motoristas_veiculo
        FOREIGN KEY (veiculo_id)
        REFERENCES veiculos(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- 4. ENTREGAS E PEDIDOS (RF04, RF05, RF08, RF09)
DROP TABLE IF EXISTS entregas;

CREATE TABLE entregas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_pedido VARCHAR(30) UNIQUE NOT NULL,
    cliente_nome VARCHAR(100) NOT NULL,
    endereco VARCHAR(255) NOT NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    janela_inicio TIME NULL,
    janela_fim TIME NULL,
    peso_kg DECIMAL(10,2) NOT NULL DEFAULT 1.0,
    volume_m3 DECIMAL(10,2) NOT NULL DEFAULT 0.1,
    prioridade ENUM(
        'baixa',
        'media',
        'alta',
        'urgente'
    ) DEFAULT 'media',
    status ENUM(
        'pendente',
        'em_rota',
        'entregue',
        'falha'
    ) DEFAULT 'pendente',
    risco_atraso_porcentagem INT DEFAULT 0,
    sugestao_ia TEXT NULL,
    motorista_id INT NULL,
    veiculo_id INT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_entregas_status (status),
    INDEX idx_entregas_prioridade (prioridade),
    INDEX idx_entregas_motorista (motorista_id),
    INDEX idx_entregas_veiculo (veiculo_id),
    INDEX idx_entregas_data (data_criacao),

    CONSTRAINT fk_entregas_motorista
        FOREIGN KEY (motorista_id)
        REFERENCES motoristas(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_entregas_veiculo
        FOREIGN KEY (veiculo_id)
        REFERENCES veiculos(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. COMPROVANTES DE ENTREGA / POD (RF07)
DROP TABLE IF EXISTS comprovantes_pod;

CREATE TABLE comprovantes_pod (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entrega_id INT NOT NULL,
    data_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    evidencia_tipo ENUM(
        'codigo',
        'assinatura',
        'foto'
    ) DEFAULT 'codigo',
    evidencia_dado TEXT NOT NULL,
    observacoes TEXT NULL,

    INDEX idx_pod_entrega (entrega_id),

    CONSTRAINT fk_pod_entrega
        FOREIGN KEY (entrega_id)
        REFERENCES entregas(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. ALERTAS (RF11)
DROP TABLE IF EXISTS alertas;

CREATE TABLE alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM(
        'atraso',
        'veiculo_parado',
        'manutencao_cnh',
        'rota_desvio'
    ) NOT NULL,
    mensagem TEXT NOT NULL,
    nivel ENUM(
        'info',
        'aviso',
        'critico'
    ) DEFAULT 'aviso',
    resolvido TINYINT(1) DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_alertas_resolvido (resolvido),
    INDEX idx_alertas_criado (criado_em)
) ENGINE=InnoDB;

-- 7. LOGS DE NOTIFICAÇÕES (RF11)
DROP TABLE IF EXISTS logs_notificacoes;

CREATE TABLE logs_notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alerta_id INT NULL,
    email_destino VARCHAR(150) NOT NULL,
    assunto VARCHAR(200) NOT NULL,
    status ENUM('enviado', 'falha') DEFAULT 'enviado',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_logs_alerta
        FOREIGN KEY (alerta_id)
        REFERENCES alertas(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- DADOS DE TESTE
-- Todas as contas abaixo usam a senha: password
-- Hash bcrypt de demonstração; a autenticação PHP deve usar password_verify().
-- ============================================================================

INSERT INTO usuarios (nome, email, senha, perfil) VALUES
(
    'Administrador SENN AI-X',
    'admin@senn.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igy',
    'administrador'
),
(
    'Gestor Logístico',
    'gestor@senn.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igy',
    'gestor'
),
(
    'Despachante Silva',
    'despachante@senn.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igy',
    'despachante'
),
(
    'Motorista Carlos',
    'motorista@senn.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igy',
    'motorista'
);

INSERT INTO veiculos (
    placa, modelo, tipo, capacidade_kg, capacidade_m3,
    autonomia_km_l, status, data_proxima_manutencao
) VALUES
(
    'ABC-1234', 'Mercedes Sprinter', 'Van',
    1500.00, 10.50, 9.5, 'disponivel', '2026-11-15'
),
(
    'XYZ-9876', 'VW Delivery', 'VUC',
    3500.00, 18.00, 7.2, 'em_rota', '2026-12-01'
),
(
    'KLY-5544', 'Fiat Ducato', 'Van',
    1300.00, 9.00, 10.0, 'em_manutencao', '2026-10-25'
);

INSERT INTO motoristas (
    nome, cpf, cnh, categoria_cnh, validade_cnh,
    telefone, status, veiculo_id
) VALUES
(
    'Carlos Eduardo', '111.222.333-44', '12345678900',
    'D', '2027-05-10', '(12) 99111-2233', 'em_rota', 2
),
(
    'Roberto Santos', '555.666.777-88', '98765432100',
    'C', '2026-11-01', '(12) 98888-4455', 'disponivel', 1
);

INSERT INTO entregas (
    codigo_pedido, cliente_nome, endereco,
    latitude, longitude, janela_inicio, janela_fim,
    peso_kg, volume_m3, prioridade, status,
    risco_atraso_porcentagem, motorista_id, veiculo_id
) VALUES
(
    'PED-1001', 'Supermercado Central',
    'Rua das Flores, 123, Taubaté - SP',
    -23.0264, -45.5558, '08:00:00', '12:00:00',
    450.00, 3.20, 'alta', 'em_rota', 15, 1, 2
),
(
    'PED-1002', 'Loja de Eletro Taubaté',
    'Av. Independência, 900, Taubaté - SP',
    -23.0301, -45.5480, '10:00:00', '14:00:00',
    120.00, 1.50, 'media', 'pendente', 75, NULL, NULL
),
(
    'PED-1003', 'Farmácia Saúde',
    'Rua Visconde do Rio Branco, 45, Taubaté - SP',
    -23.0210, -45.5510, '13:00:00', '17:00:00',
    35.00, 0.40, 'urgente', 'entregue', 5, 1, 2
);
