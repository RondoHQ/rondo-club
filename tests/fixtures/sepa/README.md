# Rabobank SEPA validation fixture

`pain.001.001.09.xsd` is the schema (line endings normalized to LF) downloaded on 2026-10-04 from
Rabobank's public format specifications:
https://media.rabobank.com/m/1cec8a77cf312b91/original/pain-001-001-09-xsd.zip

Bank profile used for the generator:
https://media.rabobank.com/m/1a4ffd39057d7938/original/Formaatbeschrijving-SEPA-Credit-Transfer-pain-001-001-09-pdf.pdf

Schema validation is a local structural check, not evidence of an accepted bank import.
The test uses fictional names and public example IBANs and never sends a payment.
