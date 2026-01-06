# Loonkosten.nl Dashboard

Een moderne, veilige loonadministratie-tool voor Nederlandse en Belgische MKB-bedrijven.

## Features
- Urenregistratie via kloksysteem (in-/uitklokken)
- Automatische loonstroken (NL + BE)
- Arbeidscontract-generator met bedrijfslogo en clausules
- Ziekteverzuim-calculator
- Auto van de zaak (bijtelling/VAA)
- Vakantiegeld, pensioen, reiskosten
- SEPA-betaalbestand export
- Multi-country (NL/BE)

## Installatie
1. Importeer `sql/create_db.sql`
2. Run `composer install`
3. Maak mappen writable: logos/, payslips/, contracts/, sepa/
4. Pas `config.php` aan (DB + SMTP)
5. Start met `register_bedrijf.php`

## Veiligheid
- AJAX-backend voor alle DB-calls
- CSRF-protectie
- Prepared statements
- Tooltips op alle inputs/buttons
