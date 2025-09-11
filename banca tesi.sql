-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Creato il: Set 11, 2025 alle 14:08
-- Versione del server: 10.4.32-MariaDB
-- Versione PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `banca`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `bonifici`
--

CREATE TABLE `bonifici` (
  `id` int(11) NOT NULL,
  `destinatario` varchar(100) NOT NULL,
  `iban_destinatario` varchar(34) NOT NULL,
  `istantaneo` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_movimento` int(11) NOT NULL,
  `causale` text NOT NULL,
  `data_esecuzione` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `bonifici`
--

INSERT INTO `bonifici` (`id`, `destinatario`, `iban_destinatario`, `istantaneo`, `created_at`, `id_movimento`, `causale`, `data_esecuzione`) VALUES
(2, 'mario bianchi', 'it73637300686637', 1, '2025-07-22 09:58:12', 26, 'regalo', NULL),
(3, 'mario bianchi', 'it73637300686637', 1, '2025-07-22 09:59:24', 27, 'regalo', NULL),
(4, 'mario bianchi', 'it73637300686637', 0, '2025-07-28 07:57:59', 28, 'regalo', '2025-07-29'),
(5, 'mario bianchi', 'it73637300686637', 0, '2025-07-27 08:00:33', 29, 'regalo', '2025-07-27'),
(8, 'mario bianchi', 'it73637300686637', 0, '2025-07-28 09:26:03', 32, 'regalo', '0000-00-00'),
(9, 'mirco pizzo', 'it9999999912', 0, '2025-07-28 09:45:34', 33, 'regalo', '0000-00-00'),
(10, 'Manuel Conti', 'IT60X0542811101000000123456', 1, '2025-07-29 13:06:32', 34, 'regalo', '2025-07-29'),
(11, 'Da:  ', 'IT60X0542811101000000123459', 1, '2025-07-29 13:06:32', 35, 'regalo', '2025-07-29'),
(14, 'Manuel Conti', 'IT60X0542811101000000123456', 1, '2025-07-29 13:12:50', 40, 'regalo', '2025-07-29'),
(15, 'Da: Sara Rossi', 'IT60X0542811101000000123459', 1, '2025-07-29 13:12:50', 41, 'regalo', '2025-07-29'),
(16, 'Manuel Conti', 'IT60X0542811101000000123456', 1, '2025-07-29 13:12:59', 42, 'regalo', '2025-07-29'),
(17, 'Da: Sara Rossi', 'IT60X0542811101000000123459', 1, '2025-07-29 13:12:59', 43, 'regalo', '2025-07-29'),
(18, 'mario bianchi', 'IT60X0542811101000000123459', 1, '2025-08-27 12:40:45', 54, 'regalo', '2025-08-27'),
(19, 'Da: Manuel Conti', 'IT60X0542811101000000123456', 1, '2025-08-27 12:40:45', 55, 'regalo', '2025-08-27');

-- --------------------------------------------------------

--
-- Struttura della tabella `carta`
--

CREATE TABLE `carta` (
  `id` int(11) NOT NULL,
  `numero_carta` varchar(19) NOT NULL,
  `cvv` char(3) NOT NULL,
  `data_scadenza` date NOT NULL,
  `circuito` enum('Visa','MasterCard','Amex','Maestro') NOT NULL,
  `tipo` enum('debito','credito','prepagata') NOT NULL,
  `stato` enum('attiva','bloccata','scaduta') NOT NULL DEFAULT 'attiva',
  `pin_hash` varchar(255) NOT NULL,
  `conto_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `carta`
--

INSERT INTO `carta` (`id`, `numero_carta`, `cvv`, `data_scadenza`, `circuito`, `tipo`, `stato`, `pin_hash`, `conto_id`) VALUES
(1, '4539310012345678', '123', '2027-12-31', 'Visa', 'credito', 'attiva', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 1),
(2, '5404000000000001', '456', '2026-08-31', 'MasterCard', 'debito', 'attiva', 'f8638b979b2f4f793ddb6dbd197e0ee25a7a6ea32b0ae22f5e3c5d119d839e75', 1),
(3, '371449635398431', '789', '2028-05-31', 'Amex', 'prepagata', 'attiva', '9af15b336e6a9619928537df30b2e6a2376569fcf9d7e773eccede65606529a0', 1),
(4, '\'4539310012345670', '111', '2029-07-17', 'Maestro', 'debito', 'attiva', '9af15b336e6a9619928537df30b2e6a2376569fcf9d7e773eccede65606529yy', 2);

-- --------------------------------------------------------

--
-- Struttura della tabella `carte_movimenti`
--

CREATE TABLE `carte_movimenti` (
  `id` int(11) NOT NULL,
  `id_carta` int(11) NOT NULL,
  `data` date NOT NULL,
  `tipo_movimento` int(11) NOT NULL DEFAULT 7,
  `importo` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `carte_movimenti`
--

INSERT INTO `carte_movimenti` (`id`, `id_carta`, `data`, `tipo_movimento`, `importo`) VALUES
(1, 1, '2022-07-01', 7, 45.50),
(2, 1, '2025-07-03', 7, 120.00),
(3, 2, '2025-07-04', 7, 200.00),
(8, 1, '2025-01-05', 7, 145.60),
(9, 1, '2025-01-12', 7, 78.90),
(10, 1, '2025-01-19', 7, 234.50),
(11, 1, '2025-01-26', 7, 89.30),
(12, 1, '2025-02-02', 7, 167.80),
(13, 1, '2025-02-09', 7, 123.45),
(14, 1, '2025-02-16', 7, 298.70),
(15, 1, '2025-02-23', 7, 67.20),
(16, 1, '2025-03-02', 7, 189.90),
(17, 1, '2025-03-09', 7, 156.40),
(18, 1, '2025-03-16', 7, 278.60),
(19, 1, '2025-03-23', 7, 94.30),
(20, 1, '2025-03-30', 7, 212.80),
(21, 1, '2025-04-06', 7, 87.50),
(22, 1, '2025-04-13', 7, 345.20),
(23, 1, '2025-04-20', 7, 123.70),
(24, 1, '2025-04-27', 7, 198.40),
(25, 1, '2025-05-04', 7, 76.90),
(26, 1, '2025-05-11', 7, 267.30),
(27, 1, '2025-05-18', 7, 134.60),
(28, 1, '2025-05-25', 7, 189.80),
(29, 1, '2025-06-01', 7, 98.20),
(30, 1, '2025-06-08', 7, 245.70),
(31, 1, '2025-06-15', 7, 167.40),
(32, 1, '2025-06-22', 7, 312.90),
(33, 1, '2025-06-29', 7, 89.60),
(34, 1, '2025-07-06', 7, 178.30),
(35, 1, '2025-07-13', 7, 234.80),
(38, 1, '2025-08-03', 7, 156.20),
(39, 1, '2025-08-10', 7, 267.40),
(40, 1, '2025-08-17', 7, 89.90),
(41, 2, '2025-01-03', 7, 234.80),
(42, 2, '2025-01-10', 7, 156.40),
(43, 2, '2025-01-17', 7, 89.60),
(44, 2, '2025-01-24', 7, 278.30),
(45, 2, '2025-01-31', 7, 123.70),
(46, 2, '2025-02-07', 7, 198.50),
(47, 2, '2025-02-14', 7, 167.20),
(48, 2, '2025-02-21', 7, 345.60),
(49, 2, '2025-02-28', 7, 94.80),
(50, 2, '2025-03-07', 7, 267.40),
(51, 2, '2025-03-14', 7, 134.90),
(52, 2, '2025-03-21', 7, 189.70),
(53, 2, '2025-03-28', 7, 78.30),
(54, 2, '2025-04-04', 7, 312.50),
(55, 2, '2025-04-11', 7, 156.80),
(56, 2, '2025-04-18', 7, 234.20),
(57, 2, '2025-04-25', 7, 98.60),
(58, 2, '2025-05-02', 7, 278.90),
(59, 2, '2025-05-09', 7, 167.40),
(60, 2, '2025-05-16', 7, 123.70),
(61, 2, '2025-05-23', 7, 345.30),
(62, 2, '2025-05-30', 7, 89.50),
(63, 2, '2025-06-06', 7, 267.80),
(64, 2, '2025-06-13', 7, 134.60),
(65, 2, '2025-06-20', 7, 198.40),
(66, 2, '2025-06-27', 7, 87.20),
(67, 2, '2025-07-04', 7, 312.70),
(68, 2, '2025-07-11', 7, 156.30),
(69, 2, '2025-07-18', 7, 234.90),
(70, 2, '2025-07-25', 7, 98.80),
(71, 2, '2025-08-01', 7, 278.50),
(72, 2, '2025-08-08', 7, 167.60),
(73, 2, '2025-08-15', 7, 123.40),
(74, 3, '2025-01-04', 7, 156.90),
(75, 3, '2025-01-11', 7, 234.70),
(76, 3, '2025-01-18', 7, 98.30),
(77, 3, '2025-01-25', 7, 278.60),
(78, 3, '2025-02-01', 7, 134.50),
(79, 3, '2025-02-08', 7, 189.80),
(80, 3, '2025-02-15', 7, 87.20),
(81, 3, '2025-02-22', 7, 345.70),
(82, 3, '2025-03-01', 7, 167.40),
(83, 3, '2025-03-08', 7, 234.90),
(84, 3, '2025-03-15', 7, 123.60),
(85, 3, '2025-03-22', 7, 298.30),
(86, 3, '2025-03-29', 7, 89.50),
(87, 3, '2025-04-05', 7, 267.80),
(88, 3, '2025-04-12', 7, 156.20),
(89, 3, '2025-04-19', 7, 198.70),
(90, 3, '2025-04-26', 7, 78.90),
(91, 3, '2025-05-03', 7, 312.50),
(92, 3, '2025-05-10', 7, 134.80),
(93, 3, '2025-05-17', 7, 267.40),
(94, 3, '2025-05-24', 7, 98.60),
(95, 3, '2025-05-31', 7, 189.30),
(96, 3, '2025-06-07', 7, 123.70),
(97, 3, '2025-06-14', 7, 345.20),
(98, 3, '2025-06-21', 7, 87.50),
(99, 3, '2025-06-28', 7, 234.80),
(100, 3, '2025-07-05', 7, 167.60),
(101, 3, '2025-07-12', 7, 298.40),
(102, 3, '2025-07-19', 7, 134.20),
(103, 3, '2025-07-26', 7, 189.70),
(104, 3, '2025-08-02', 7, 89.30),
(105, 3, '2025-08-09', 7, 267.90),
(106, 3, '2025-08-16', 7, 156.50);

-- --------------------------------------------------------

--
-- Struttura della tabella `conto`
--

CREATE TABLE `conto` (
  `id` int(11) NOT NULL,
  `iban` varchar(34) NOT NULL,
  `tipo_conto` enum('corrente','risparmio','deposito') NOT NULL DEFAULT 'corrente',
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `conto`
--

INSERT INTO `conto` (`id`, `iban`, `tipo_conto`, `user_id`) VALUES
(1, 'IT60X0542811101000000123456', 'corrente', 1),
(2, 'IT60X0542811101000000123459', 'corrente', 2);

-- --------------------------------------------------------

--
-- Struttura della tabella `investimenti`
--

CREATE TABLE `investimenti` (
  `id` int(11) NOT NULL,
  `id_movimento` int(11) NOT NULL,
  `tipo_investimento` varchar(50) NOT NULL,
  `simbolo` varchar(10) NOT NULL,
  `quantita` decimal(15,8) NOT NULL,
  `prezzo_acquisto` decimal(10,4) NOT NULL,
  `data_acquisto` timestamp NOT NULL DEFAULT current_timestamp(),
  `stato` enum('attivo','venduto','chiuso') DEFAULT 'attivo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `investimenti`
--

INSERT INTO `investimenti` (`id`, `id_movimento`, `tipo_investimento`, `simbolo`, `quantita`, `prezzo_acquisto`, `data_acquisto`, `stato`) VALUES
(7, 26, 'Azione', 'AAPL', 5.00000000, 200.0000, '2025-07-15 08:30:00', 'attivo'),
(8, 27, 'ETF', 'SPY', 10.00000000, 250.0000, '2025-07-18 12:15:00', 'attivo'),
(9, 28, 'Criptovaluta', 'BTC-USD', 0.01000000, 50000.0000, '2025-07-20 14:45:00', 'attivo'),
(10, 51, 'Azione', 'AAPL', 5.00000000, 1000.0000, '2025-07-31 09:50:02', 'attivo');

-- --------------------------------------------------------

--
-- Struttura della tabella `movimenti`
--

CREATE TABLE `movimenti` (
  `id` int(11) NOT NULL,
  `id_conto` int(11) NOT NULL,
  `data` date NOT NULL,
  `tipo_movimento` int(11) NOT NULL,
  `importo` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `movimenti`
--

INSERT INTO `movimenti` (`id`, `id_conto`, `data`, `tipo_movimento`, `importo`) VALUES
(15, 1, '2025-07-01', 6, 1200.00),
(16, 1, '2025-07-03', 2, 250.00),
(17, 1, '2025-07-06', 6, 130.00),
(19, 1, '2025-07-02', 6, 2000.00),
(20, 1, '2022-07-24', 2, 800.00),
(22, 1, '2025-07-20', 1, 50.00),
(23, 1, '2025-07-20', 1, 50.00),
(25, 1, '2025-07-20', 1, 40.00),
(26, 1, '2025-07-22', 1, 150.00),
(27, 1, '2025-07-22', 1, 150.55),
(28, 1, '2025-07-30', 1, 150.55),
(29, 1, '2025-07-30', 1, 120.00),
(30, 1, '2025-07-30', 1, 120.00),
(31, 1, '2025-07-28', 1, 100.00),
(32, 2, '2025-07-22', 6, 1000.00),
(33, 1, '2025-07-28', 1, 12.00),
(34, 2, '2025-07-29', 1, 100.00),
(35, 1, '2025-07-29', 2, 100.00),
(40, 2, '2025-07-29', 1, 100.00),
(41, 1, '2025-07-29', 6, 100.00),
(42, 2, '2025-07-29', 1, 100.00),
(43, 1, '2025-07-29', 6, 100.00),
(44, 1, '2024-09-20', 5, 15000.00),
(45, 1, '2025-07-15', 2, 1000.00),
(46, 1, '2025-07-18', 2, 2500.00),
(47, 1, '2025-07-20', 2, 500.00),
(48, 1, '2025-07-15', 2, 1000.00),
(49, 1, '2025-07-18', 2, 2500.00),
(50, 1, '2025-07-20', 2, 500.00),
(51, 1, '2025-07-31', 2, 5000.00),
(52, 1, '2025-08-18', 2, 2500.00),
(53, 1, '2025-08-19', 6, 20000.00),
(54, 1, '2025-08-27', 1, 1.00),
(55, 2, '2025-08-27', 6, 1.00);

-- --------------------------------------------------------

--
-- Struttura della tabella `polizza`
--

CREATE TABLE `polizza` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `premio` decimal(12,2) NOT NULL,
  `spesa_annua` decimal(12,2) DEFAULT NULL,
  `data_inizio` date NOT NULL,
  `data_fine` date DEFAULT NULL,
  `copertura` text DEFAULT NULL,
  `stato` enum('attiva','scaduta','sospesa') NOT NULL DEFAULT 'attiva',
  `conto_id` int(11) NOT NULL,
  `importo_copertura` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `polizza`
--

INSERT INTO `polizza` (`id`, `nome`, `premio`, `spesa_annua`, `data_inizio`, `data_fine`, `copertura`, `stato`, `conto_id`, `importo_copertura`) VALUES
(1, 'Polizza Vita Allianz', 300.00, 1200.00, '2024-01-15', '2029-01-15', 'Copertura per decesso e infortunio', 'attiva', 1, 50000.00),
(2, 'Assicurazione Auto', 400.00, 800.00, '2025-01-17', '2029-01-19', 'kasko', 'attiva', 1, 10000.00);

-- --------------------------------------------------------

--
-- Struttura della tabella `tipologia_movimento`
--

CREATE TABLE `tipologia_movimento` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `segno` varchar(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `tipologia_movimento`
--

INSERT INTO `tipologia_movimento` (`id`, `nome`, `segno`) VALUES
(1, 'Bonifico', '-'),
(2, 'Investimento', '-'),
(3, 'Polizza', '-'),
(4, 'Ricarica', '+'),
(5, 'Versamento', '+'),
(6, 'Bonifico in Entrata', '+'),
(7, 'Pagamento con Carta', '-');

-- --------------------------------------------------------

--
-- Struttura della tabella `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cognome` varchar(100) NOT NULL,
  `indirizzo` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(150) NOT NULL,
  `numero_telefonico` varchar(20) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `data_nascita` date DEFAULT NULL,
  `ruolo` enum('admin','cliente','operatore','manager') NOT NULL DEFAULT 'cliente',
  `data_registrazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `numero_conto` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `user`
--

INSERT INTO `user` (`id`, `nome`, `cognome`, `indirizzo`, `password`, `email`, `numero_telefonico`, `foto`, `data_nascita`, `ruolo`, `data_registrazione`, `numero_conto`) VALUES
(1, 'Manuel', 'Conti', 'Via Roma 10, Milano', 'a109e36947ad56de1dca1cc49f0ef8ac9ad9a7b1aa0df41fb3c4cb73c1ff01ea', 'manuel.conti@example.com', '3331234567', 'uploads/foto_profilo/user_1_1753969928.webp', '1990-05-15', 'cliente', '2025-07-19 13:20:03', '123'),
(2, 'Sara', 'Rossi', 'Via Milano 22, Torino', 'a109e36947ad56de1dca1cc49f0ef8ac9ad9a7b1aa0df41fb3c4cb73c1ff01ea', 'sara.rossi@example.com', '3332345678', 'sara.jpg', '1988-04-10', 'cliente', '2025-07-19 13:20:03', '124'),
(3, 'Luca', 'Bianchi', 'Via Firenze 5, Roma', 'a109e36947ad56de1dca1cc49f0ef8ac9ad9a7b1aa0df41fb3c4cb73c1ff01ea', 'luca.bianchi@example.com', '3333456789', 'luca.jpg', '1992-07-20', 'cliente', '2025-07-19 13:20:03', '125');

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `bonifici`
--
ALTER TABLE `bonifici`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bonifici_movimento` (`id_movimento`);

--
-- Indici per le tabelle `carta`
--
ALTER TABLE `carta`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_carta` (`numero_carta`),
  ADD KEY `conto_id` (`conto_id`);

--
-- Indici per le tabelle `carte_movimenti`
--
ALTER TABLE `carte_movimenti`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_carta` (`id_carta`),
  ADD KEY `fk_carte_tipo_movimento` (`tipo_movimento`);

--
-- Indici per le tabelle `conto`
--
ALTER TABLE `conto`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `iban` (`iban`),
  ADD KEY `user_id` (`user_id`);

--
-- Indici per le tabelle `investimenti`
--
ALTER TABLE `investimenti`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_movimento` (`id_movimento`),
  ADD KEY `simbolo` (`simbolo`);

--
-- Indici per le tabelle `movimenti`
--
ALTER TABLE `movimenti`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_conto` (`id_conto`),
  ADD KEY `tipo_movimento` (`tipo_movimento`);

--
-- Indici per le tabelle `polizza`
--
ALTER TABLE `polizza`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conto_id` (`conto_id`);

--
-- Indici per le tabelle `tipologia_movimento`
--
ALTER TABLE `tipologia_movimento`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `bonifici`
--
ALTER TABLE `bonifici`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT per la tabella `carta`
--
ALTER TABLE `carta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT per la tabella `carte_movimenti`
--
ALTER TABLE `carte_movimenti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT per la tabella `conto`
--
ALTER TABLE `conto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT per la tabella `investimenti`
--
ALTER TABLE `investimenti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT per la tabella `movimenti`
--
ALTER TABLE `movimenti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT per la tabella `polizza`
--
ALTER TABLE `polizza`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT per la tabella `tipologia_movimento`
--
ALTER TABLE `tipologia_movimento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT per la tabella `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `bonifici`
--
ALTER TABLE `bonifici`
  ADD CONSTRAINT `fk_bonifici_movimento` FOREIGN KEY (`id_movimento`) REFERENCES `movimenti` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `carta`
--
ALTER TABLE `carta`
  ADD CONSTRAINT `carta_ibfk_1` FOREIGN KEY (`conto_id`) REFERENCES `conto` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `carte_movimenti`
--
ALTER TABLE `carte_movimenti`
  ADD CONSTRAINT `carte_movimenti_ibfk_1` FOREIGN KEY (`id_carta`) REFERENCES `carta` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_carte_tipo_movimento` FOREIGN KEY (`tipo_movimento`) REFERENCES `tipologia_movimento` (`id`);

--
-- Limiti per la tabella `conto`
--
ALTER TABLE `conto`
  ADD CONSTRAINT `conto_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `investimenti`
--
ALTER TABLE `investimenti`
  ADD CONSTRAINT `investimenti_ibfk_1` FOREIGN KEY (`id_movimento`) REFERENCES `movimenti` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `movimenti`
--
ALTER TABLE `movimenti`
  ADD CONSTRAINT `movimenti_ibfk_1` FOREIGN KEY (`id_conto`) REFERENCES `conto` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `polizza`
--
ALTER TABLE `polizza`
  ADD CONSTRAINT `polizza_ibfk_1` FOREIGN KEY (`conto_id`) REFERENCES `conto` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
