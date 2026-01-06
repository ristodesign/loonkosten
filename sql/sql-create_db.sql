-- Volledige SQL-structuur voor Loonkosten.nl inclusief indexes en dummy data
-- Versie: januari 2026

CREATE DATABASE IF NOT EXISTS loonstroken_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE loonstroken_db;

-- 1. Bedrijven
CREATE TABLE bedrijven (
	id INT AUTO_INCREMENT PRIMARY KEY,
	naam VARCHAR(255) NOT NULL,
	adres VARCHAR(255) NOT NULL,
	loonheffingsnummer VARCHAR(20) NOT NULL,
	email VARCHAR(255) NOT NULL UNIQUE,
	password VARCHAR(255) NOT NULL,
	land ENUM('NL', 'BE') DEFAULT 'NL',
	logo_path VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

ALTER TABLE bedrijven ADD INDEX idx_email (email);
ALTER TABLE bedrijven ADD INDEX idx_land (land);

-- 2. Werknemers
CREATE TABLE werknemers (
	id INT AUTO_INCREMENT PRIMARY KEY,
	bedrijf_id INT NOT NULL,
	naam VARCHAR(255) NOT NULL,
	adres VARCHAR(255) NOT NULL,
	geboortedatum DATE NULL,
	bsn VARCHAR(11) NOT NULL,
	nationaliteit ENUM('NL', 'Buitenland') DEFAULT 'NL',
	email VARCHAR(255) NOT NULL UNIQUE,
	password VARCHAR(255) NOT NULL,
	loonheffingskorting TINYINT(1) DEFAULT 1,
	last_ip VARCHAR(45) DEFAULT NULL,
	2fa_code VARCHAR(6) DEFAULT NULL,
	2fa_expiry DATETIME DEFAULT NULL,
	rijksregisternummer VARCHAR(20) DEFAULT NULL,
	statuut ENUM('bediende', 'arbeider') DEFAULT 'bediende',
	FOREIGN KEY (bedrijf_id) REFERENCES bedrijven(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE werknemers ADD INDEX idx_bedrijf_id (bedrijf_id);
ALTER TABLE werknemers ADD INDEX idx_email (email);
ALTER TABLE werknemers ADD INDEX idx_land_statuut (nationaliteit, statuut);

-- 3. Contracten
CREATE TABLE contracten (
	id INT AUTO_INCREMENT PRIMARY KEY,
	werknemer_id INT NOT NULL,
	type ENUM('Fulltime', 'Parttime', 'AllInOverurenBetaald', 'AllInVastSalaris') NOT NULL,
	bruto_salaris DECIMAL(10,2) NOT NULL,
	is_uurloon TINYINT(1) DEFAULT 0,
	contract_uren_per_week DECIMAL(5,2) NOT NULL,
	pensioen_totaal_percentage DECIMAL(5,2) DEFAULT 25.00,
	pensioen_werknemer_percentage DECIMAL(5,2) DEFAULT 8.00,
	vakantie_percentage DECIMAL(5,2) DEFAULT 8.00,
	reiskosten_recht TINYINT(1) DEFAULT 0,
	reiskosten_km_per_dag DECIMAL(5,2) DEFAULT 0.00,
	cao VARCHAR(100) DEFAULT NULL,
	paritair_comite VARCHAR(50) DEFAULT NULL,
	heeft_13e_maand TINYINT(1) DEFAULT 0,
	proeftijd_maanden TINYINT DEFAULT 0,
	duur_type ENUM('onbepaald', 'bepaald') DEFAULT 'onbepaald',
	einddatum DATE NULL,
	verzuimverzekering_actief TINYINT(1) DEFAULT 0,
	verzuimverzekering_premie_pct DECIMAL(5,2) DEFAULT 2.00,
	verzuim_wachttijd_dagen INT DEFAULT 14,
	heeft_auto_van_de_zaak TINYINT(1) DEFAULT 0,
	auto_cataloguswaarde DECIMAL(10,2) DEFAULT 0.00,
	auto_co2_uitstoot INT DEFAULT 0,
	auto_elektrisch TINYINT(1) DEFAULT 0,
	auto_eerste_inschrijving_jaar YEAR DEFAULT NULL,
	auto_bijdrage_werknemer DECIMAL(10,2) DEFAULT 0.00,
	FOREIGN KEY (werknemer_id) REFERENCES werknemers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE contracten ADD INDEX idx_werknemer_id (werknemer_id);
ALTER TABLE contracten ADD INDEX idx_type_auto (type, heeft_auto_van_de_zaak);

-- 4. Loonperiodes
CREATE TABLE loonperiodes (
	id INT AUTO_INCREMENT PRIMARY KEY,
	start_datum DATE NOT NULL,
	eind_datum DATE NOT NULL,
	type ENUM('Maand', 'Week', '4-weken') DEFAULT 'Maand',
	aow_franchise_jaar DECIMAL(10,2) DEFAULT 19172.00,
	reiskosten_tarief DECIMAL(5,2) DEFAULT 0.23,
	rsz_werknemer_pct DECIMAL(5,2) DEFAULT 13.07,
	rsz_werkgever_pct DECIMAL(5,2) DEFAULT 25.00,
	vakantiegeld_enkel_pct DECIMAL(5,2) DEFAULT 92.00
) ENGINE=InnoDB;

ALTER TABLE loonperiodes ADD INDEX idx_datum (start_datum, eind_datum);

-- 5. Tijdsblokken
CREATE TABLE tijdsblokken (
	id INT AUTO_INCREMENT PRIMARY KEY,
	werknemer_id INT NOT NULL,
	periode_id INT NULL,
	datum DATE NOT NULL,
	in_tijd TIME NOT NULL,
	uit_tijd TIME NOT NULL,
	pauze_min INT DEFAULT 30,
	berekende_uren DECIMAL(5,2) GENERATED ALWAYS AS (
		(TIMESTAMPDIFF(MINUTE, in_tijd, uit_tijd) - pauze_min) / 60
	) STORED,
	is_overuur DECIMAL(5,2) DEFAULT 0.00,
	FOREIGN KEY (werknemer_id) REFERENCES werknemers(id) ON DELETE CASCADE,
	FOREIGN KEY (periode_id) REFERENCES loonperiodes(id)
) ENGINE=InnoDB;

ALTER TABLE tijdsblokken ADD INDEX idx_werknemer_periode (werknemer_id, periode_id);
ALTER TABLE tijdsblokken ADD INDEX idx_datum (datum);

-- 6. Uren
CREATE TABLE uren (
	id INT AUTO_INCREMENT PRIMARY KEY,
	werknemer_id INT NOT NULL,
	periode_id INT NOT NULL,
	datum DATE NOT NULL,
	uren DECIMAL(5,2) NOT NULL,
	toeslag DECIMAL(10,2) DEFAULT 0.00,
	type ENUM('normaal', 'vakantie', 'ziekte') DEFAULT 'normaal',
	is_overuur TINYINT(1) DEFAULT 0,
	toeslag_percentage DECIMAL(5,2) DEFAULT NULL,
	FOREIGN KEY (werknemer_id) REFERENCES werknemers(id) ON DELETE CASCADE,
	FOREIGN KEY (periode_id) REFERENCES loonperiodes(id)
) ENGINE=InnoDB;

ALTER TABLE uren ADD INDEX idx_werknemer_periode (werknemer_id, periode_id);
ALTER TABLE uren ADD INDEX idx_datum_type (datum, type);

-- 7. Vakantie-saldo
CREATE TABLE vakantie_saldo (
	id INT AUTO_INCREMENT PRIMARY KEY,
	werknemer_id INT NOT NULL,
	jaar YEAR NOT NULL,
	opbouw DECIMAL(5,2) DEFAULT 0.00,
	gebruikt DECIMAL(5,2) DEFAULT 0.00,
	UNIQUE KEY unique_werknemer_jaar (werknemer_id, jaar),
	FOREIGN KEY (werknemer_id) REFERENCES werknemers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE vakantie_saldo ADD INDEX idx_werknemer_jaar (werknemer_id, jaar);

-- 8. Vakantiegeld reservering
CREATE TABLE vakantiegeld_reservering (
	id INT AUTO_INCREMENT PRIMARY KEY,
	werknemer_id INT NOT NULL,
	jaar YEAR NOT NULL,
	opgebouwd DECIMAL(10,2) DEFAULT 0.00,
	uitbetaald DECIMAL(10,2) DEFAULT 0.00,
	enkel DECIMAL(10,2) DEFAULT 0.00,
	dubbel DECIMAL(10,2) DEFAULT 0.00,
	UNIQUE KEY unique_werknemer_jaar (werknemer_id, jaar),
	FOREIGN KEY (werknemer_id) REFERENCES werknemers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE vakantiegeld_reservering ADD INDEX idx_werknemer_jaar (werknemer_id, jaar);

-- 9. Loonstroken
CREATE TABLE loonstroken (
	id INT AUTO_INCREMENT PRIMARY KEY,
	werknemer_id INT NOT NULL,
	periode_id INT NOT NULL,
	brutoloon DECIMAL(10,2) NOT NULL,
	reiskosten DECIMAL(10,2) DEFAULT 0.00,
	pensioen_werknemer DECIMAL(10,2) DEFAULT 0.00,
	loonheffing DECIMAL(10,2) NOT NULL,
	nettoloon DECIMAL(10,2) NOT NULL,
	vakantiegeld_opbouw DECIMAL(10,2) DEFAULT 0.00,
	pdf_path VARCHAR(255) DEFAULT NULL,
	FOREIGN KEY (werknemer_id) REFERENCES werknemers(id) ON DELETE CASCADE,
	FOREIGN KEY (periode_id) REFERENCES loonperiodes(id)
) ENGINE=InnoDB;

ALTER TABLE loonstroken ADD INDEX idx_werknemer_periode (werknemer_id, periode_id);

-- 10. Contract clausules
CREATE TABLE contract_clausules (
	id INT AUTO_INCREMENT PRIMARY KEY,
	bedrijf_id INT NOT NULL,
	titel VARCHAR(255) NOT NULL,
	tekst TEXT NOT NULL,
	positie ENUM('begin', 'midden', 'einde') DEFAULT 'einde',
	FOREIGN KEY (bedrijf_id) REFERENCES bedrijven(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE contract_clausules ADD INDEX idx_bedrijf_id (bedrijf_id);

-- 11. Regels Nederland
CREATE TABLE regels_nl (
	id INT AUTO_INCREMENT PRIMARY KEY,
	jaar YEAR NOT NULL,
	schijf1_grens DECIMAL(10,2) DEFAULT 38883.00,
	tarief1 DECIMAL(5,2) DEFAULT 35.75,
	tarief1_aow DECIMAL(5,2) DEFAULT 17.85,
	tarief2 DECIMAL(5,2) DEFAULT 49.50,
	premies_volks DECIMAL(5,2) DEFAULT 23.40,
	algemene_heffingskorting_max DECIMAL(10,2) DEFAULT 3115.00,
	arbeidskorting_max DECIMAL(10,2) DEFAULT 5685.00,
	minimumloon_uur DECIMAL(5,2) DEFAULT 14.71,
	aow_franchise DECIMAL(10,2) DEFAULT 19172.00,
	reiskosten_tarief DECIMAL(5,2) DEFAULT 0.23,
	vakantie_pct DECIMAL(5,2) DEFAULT 8.00,
	UNIQUE KEY unique_jaar (jaar)
) ENGINE=InnoDB;

ALTER TABLE regels_nl ADD INDEX idx_jaar (jaar);

-- 12. Regels België
CREATE TABLE regels_be (
	id INT AUTO_INCREMENT PRIMARY KEY,
	jaar YEAR NOT NULL,
	rsz_werknemer_pct DECIMAL(5,2) DEFAULT 13.07,
	rsz_werkgever_pct DECIMAL(5,2) DEFAULT 25.00,
	voorheffing_schijf1_grens DECIMAL(10,2) DEFAULT 15200.00,
	voorheffing_schijf1_pct DECIMAL(5,2) DEFAULT 25.00,
	voorheffing_schijf2_grens DECIMAL(10,2) DEFAULT 26830.00,
	voorheffing_schijf2_pct DECIMAL(5,2) DEFAULT 40.00,
	voorheffing_schijf3_grens DECIMAL(10,2) DEFAULT 46440.00,
	voorheffing_schijf3_pct DECIMAL(5,2) DEFAULT 45.00,
	voorheffing_schijf4_pct DECIMAL(5,2) DEFAULT 50.00,
	belastingvrije_som DECIMAL(10,2) DEFAULT 10570.00,
	vakantiegeld_enkel_pct DECIMAL(5,2) DEFAULT 92.00,
	reiskosten_tarief DECIMAL(5,2) DEFAULT 0.4287,
	overuren_toeslag_ma_do DECIMAL(5,2) DEFAULT 50.00,
	overuren_toeslag_za_zo DECIMAL(5,2) DEFAULT 100.00,
	minimumloon_maand DECIMAL(10,2) DEFAULT 2029.88,
	UNIQUE KEY unique_jaar (jaar)
) ENGINE=InnoDB;

ALTER TABLE regels_be ADD INDEX idx_jaar (jaar);

-- 13. Ziekteverzuim regels
CREATE TABLE ziekteverzuim_regels (
	id INT AUTO_INCREMENT PRIMARY KEY,
	jaar YEAR NOT NULL,
	land ENUM('NL', 'BE') NOT NULL,
	doorbetaling_pct_eerste_jaar DECIMAL(5,2) DEFAULT 70.00,
	doorbetaling_dagen_eerste_maand INT DEFAULT 30,
	gemiddelde_dagkost_indirect DECIMAL(10,2) DEFAULT 400.00,
	arbodienst_kosten_jaar DECIMAL(10,2) DEFAULT 500.00,
	UNIQUE KEY unique_jaar_land (jaar, land)
) ENGINE=InnoDB;

ALTER TABLE ziekteverzuim_regels ADD INDEX idx_jaar_land (jaar, land);

-- Dummy data voor test
INSERT IGNORE INTO regels_nl (jaar) VALUES (2026);
INSERT IGNORE INTO regels_be (jaar) VALUES (2026);
INSERT IGNORE INTO ziekteverzuim_regels (jaar, land) VALUES 
(2026, 'NL'), 
(2026, 'BE');
INSERT IGNORE INTO loonperiodes (start_datum, eind_datum, type) VALUES 
('2026-01-01', '2026-01-31', 'Maand');