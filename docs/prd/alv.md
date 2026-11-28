# ALV in Rondo Club

## Stemrecht en start

De beheerder stelt de startdatum en -tijd per ALV in, in de tijdzone van de
WordPress-site. Voor de ALV van 14 december 2026 is dit 19.30 uur
(Europe/Amsterdam). De start kan tot aanvang worden gewijzigd.

De stemlijst wordt bij aanvang vastgelegd. De WordPress-taak maakt de
momentopname bij de eerstvolgende verwerking. Een ALV-aanvraag of een reguliere
wijziging aan de relevante persoonsgegevens na aanvang legt een nog openstaande
momentopname eerst vast. Wijzigingen rechtstreeks in de database vallen buiten
deze afspraak. Lidmaatschapsvelden bevatten kalenderdatums. Voor de ALV op
14 december keurt de vereniging die dag geen nieuwe lidmaatschappen goed.

Een actief bondslid of verenigingslid staat eenmaal op de lijst, op basis van het
Rondo-persoonsnummer. Jeugdleden tellen mee. Ouders tellen uitsluitend mee via
hun eigen lidmaatschap. Openstaande lidmaatschapsaanvragen tellen niet mee.

De koppeling tussen account en persoon blijft doorlopend werken via de
bestaande registratie. Leden zonder account staan op de lijst. Zodra een eigen
account is gekoppeld, kan het lid tijdens open stemvragen één stem uitbrengen.
Huishoudtoegang geeft geen stemrecht namens een ander.

## Stemming

Stemvragen krijgen vaste antwoordopties en een open of geheime stemvorm. De
beheerder opent een vraag na aanvang en kiest een sluitingstijd. De server weigert
nieuwe stemmen vanaf dat tijdstip. De beheerder sluit de vraag definitief voor
het vaststellen van de uitslag. Deelname is uniek per vergadering, vraag en
persoon. Opslaan en sluiten delen dezelfde databasevergrendeling.

Open stemmen bevatten deelname en keuze. Geheime stembiljetten staan in een
aparte tabel met een willekeurige sleutel. Er is geen persoonsreferentie of
tijdstempel op het biljet. De deelname en het biljet worden in één transactie
vastgelegd. De applicatie toont geen individuele geheime stemmen. Directe
waarneming van databasetransacties of aanvraaginhoud kan tot correlatie leiden.
Log daarom geen aanvraaginhoud of databasequeries voor geheime stemmingen.

De uitslag bevat aantallen per antwoordoptie. Het bestuur legt een formele
conclusie desgewenst vast in de tekst bij de uitslag.

## Ledenpagina’s

Een redacteur maakt een HTML-concept of een pagina met de vastgestelde uitslag.
De server schoont HTML op. Een specifieke versie doorloopt concept, ter
goedkeuring, goedgekeurd en gepubliceerd. Bij wijzigingen blijft de vorige
gepubliceerde versie zichtbaar voor leden totdat de nieuwe versie is
goedgekeurd en gepubliceerd. Standaard kunnen het bestuur en beheerders
stemmen beheren en pagina’s redigeren. Goedkeuren en publiceren zijn standaard
beheerdersrechten, met afzonderlijk toekenbare WordPress-capabilities
`rondo_alv_approve` en `rondo_alv_publish`. Redactie en stembeheer hebben ook
elk een afzonderlijke capability.

Leden lezen gepubliceerde pagina’s in Persoonlijk → ALV, op basis van hun huidige
lidmaatschap. De ALV-module biedt geen openbare publicatie of upload van
bijlagen.

## Proefvergadering

Controleer de instelbare start, een accountsaanmaak na aanvang, jeugdleden,
ouders, twee accounts op één persoon, stemmen tijdens sluiten, geheim stemmen
en de volledige goedkeuringsworkflow. Verifieer ook dat oude gepubliceerde
pagina’s zichtbaar blijven tijdens revisie en dat directe API-verzoeken van
niet-leden geen gepubliceerde stukken ophalen.
