-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 17/09/2026 às 10:24
-- Versão do servidor: 10.11.18-MariaDB-cll-lve
-- Versão do PHP: 8.4.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `missaorocket`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `config_ofensivas`
--

CREATE TABLE `config_ofensivas` (
  `id` int(11) NOT NULL,
  `dias_requeridos` int(11) NOT NULL,
  `pontos_bonus` int(11) NOT NULL,
  `titulo_medalha` varchar(100) DEFAULT NULL,
  `icone_medalha` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupos`
--

CREATE TABLE `grupos` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `logo` longblob DEFAULT NULL,
  `logo_tipo` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_pontos`
--

CREATE TABLE `historico_pontos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `grupo_id` int(11) DEFAULT NULL,
  `prova_id` int(11) NOT NULL,
  `pontos` int(11) NOT NULL,
  `evidencia` mediumblob NOT NULL,
  `status` enum('pendente','aprovado','rejeitado') DEFAULT 'pendente',
  `motivo_rejeicao` text DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `medalhas`
--

CREATE TABLE `medalhas` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `icone` varchar(50) NOT NULL,
  `conquistada_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `penalizacoes_grupos`
--

CREATE TABLE `penalizacoes_grupos` (
  `id` int(10) UNSIGNED NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `usuario_admin_id` int(11) NOT NULL,
  `tipo` enum('adicao','penalizacao') NOT NULL,
  `pontos` int(11) NOT NULL,
  `motivo` varchar(255) NOT NULL,
  `observacao` text DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas`
--

CREATE TABLE `provas` (
  `id` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `pontos` int(11) NOT NULL,
  `tipo` enum('global','exclusiva','direcionado') DEFAULT 'global',
  `grupo_id` int(11) DEFAULT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `prova_destinatarios`
--

CREATE TABLE `prova_destinatarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `prova_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `grupo_id` int(11) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nivel` varchar(50) DEFAULT 'Bronze',
  `perfil` enum('admin','participante') DEFAULT 'participante',
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `api_token` varchar(255) DEFAULT NULL,
  `foto` longblob DEFAULT NULL,
  `biometria_facial` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios_notificacoes`
--

CREATE TABLE `usuarios_notificacoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `endpoint` text NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios_ofensivas`
--

CREATE TABLE `usuarios_ofensivas` (
  `usuario_id` int(11) NOT NULL,
  `ofensiva_atual` int(11) DEFAULT 0,
  `ofensiva_maxima` int(11) DEFAULT 0,
  `ultima_ofensiva_em` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios_sessoes`
--

CREATE TABLE `usuarios_sessoes` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `dispositivo` varchar(255) DEFAULT NULL,
  `ip_criacao` varchar(45) DEFAULT NULL,
  `ip_ultimo_acesso` varchar(45) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `ultimo_acesso` datetime NOT NULL DEFAULT current_timestamp(),
  `expira_em` datetime NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `config_ofensivas`
--
ALTER TABLE `config_ofensivas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dias_requeridos` (`dias_requeridos`);

--
-- Índices de tabela `grupos`
--
ALTER TABLE `grupos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `historico_pontos`
--
ALTER TABLE `historico_pontos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grupo_id` (`grupo_id`),
  ADD KEY `prova_id` (`prova_id`),
  ADD KEY `idx_historico_usuario_prova_id` (`usuario_id`,`prova_id`,`id`);

--
-- Índices de tabela `medalhas`
--
ALTER TABLE `medalhas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `penalizacoes_grupos`
--
ALTER TABLE `penalizacoes_grupos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_grupo_id` (`grupo_id`),
  ADD KEY `idx_usuario_admin_id` (`usuario_admin_id`),
  ADD KEY `idx_criado_em` (`criado_em`);

--
-- Índices de tabela `provas`
--
ALTER TABLE `provas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_provas_grupo_data` (`grupo_id`,`data_inicio`,`data_fim`),
  ADD KEY `idx_provas_tipo_grupo_datas` (`tipo`,`grupo_id`,`data_inicio`,`data_fim`);

--
-- Índices de tabela `prova_destinatarios`
--
ALTER TABLE `prova_destinatarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_prova_usuario` (`prova_id`,`usuario_id`),
  ADD KEY `idx_prova_id` (`prova_id`),
  ADD KEY `idx_usuario_id` (`usuario_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `grupo_id` (`grupo_id`),
  ADD KEY `idx_usuarios_perfil` (`perfil`);

--
-- Índices de tabela `usuarios_notificacoes`
--
ALTER TABLE `usuarios_notificacoes`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `usuarios_ofensivas`
--
ALTER TABLE `usuarios_ofensivas`
  ADD PRIMARY KEY (`usuario_id`);

--
-- Índices de tabela `usuarios_sessoes`
--
ALTER TABLE `usuarios_sessoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_token_hash` (`token_hash`),
  ADD KEY `idx_usuario_id` (`usuario_id`),
  ADD KEY `idx_ativo_expira` (`ativo`,`expira_em`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `config_ofensivas`
--
ALTER TABLE `config_ofensivas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `grupos`
--
ALTER TABLE `grupos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `historico_pontos`
--
ALTER TABLE `historico_pontos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `medalhas`
--
ALTER TABLE `medalhas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `penalizacoes_grupos`
--
ALTER TABLE `penalizacoes_grupos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas`
--
ALTER TABLE `provas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `prova_destinatarios`
--
ALTER TABLE `prova_destinatarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios_notificacoes`
--
ALTER TABLE `usuarios_notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios_sessoes`
--
ALTER TABLE `usuarios_sessoes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `historico_pontos`
--
ALTER TABLE `historico_pontos`
  ADD CONSTRAINT `historico_pontos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `historico_pontos_ibfk_2` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `historico_pontos_ibfk_3` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `medalhas`
--
ALTER TABLE `medalhas`
  ADD CONSTRAINT `medalhas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `penalizacoes_grupos`
--
ALTER TABLE `penalizacoes_grupos`
  ADD CONSTRAINT `fk_penalizacao_admin` FOREIGN KEY (`usuario_admin_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_penalizacao_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`);

--
-- Restrições para tabelas `provas`
--
ALTER TABLE `provas`
  ADD CONSTRAINT `provas_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `prova_destinatarios`
--
ALTER TABLE `prova_destinatarios`
  ADD CONSTRAINT `fk_prova_destinatarios_prova` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_prova_destinatarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `usuarios_ofensivas`
--
ALTER TABLE `usuarios_ofensivas`
  ADD CONSTRAINT `usuarios_ofensivas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `usuarios_sessoes`
--
ALTER TABLE `usuarios_sessoes`
  ADD CONSTRAINT `fk_usuarios_sessoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
