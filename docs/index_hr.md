# Upute za Comment modul

## 1. Odgovornost

Ovaj modul posjeduje podatke i ponašanje komentara. HTML Editor posjeduje
dokumente i verzije, Workspace naslijeđena prava i objavljivanje, Auth
identitet korisnika, ORM prenosivi pristup bazi, a Notification ulaznu poštu.
Takve granice sprječavaju skrivene ovisnosti.

## 2. Model podataka

Jedina početna migracija kreira četiri prazne tablice:

| Tablica | Namjena |
| --- | --- |
| `document_comment_settings` | Objavljena i zajednička nacrtna postavka po dokumentu i jeziku |
| `document_comments` | Tekst, snimka autora, vremena i audit brisanja |
| `document_comment_reactions` | Jedna `up` ili `down` reakcija po korisniku i komentaru |
| `document_comment_reports` | Jedna moderacijska prijava po prijavitelju i komentaru |

Sve shematske operacije koriste ORM schema builder. Nema SQL-a specifičnog za
bazu niti seed podataka u paketu.

Tekst komentara sprema se kao običan tekst i escapa prije prikaza. Prijelomi
redaka ostaju sačuvani, ali poslani HTML nikada se ne izvršava.

## 3. Pravila pristupa

Svaka write operacija ponovno učitava objavljeni dokument i provjerava pravo.
Skrivanje gumba zato je samo pogodnost sučelja, a ne sigurnosna zaštita.

- Čitanje komentara: svatko tko smije čitati objavljeni dokument.
- Dodavanje, reakcija ili prijava: prijavljeni korisnik koji smije čitati.
- Brisanje: vlasnik sadržaja, zadnji objavljeni urednik, korisnik s Workspace
  pravom objave ili administrator samostalnog Editor dokumenta.
- Promjena “Omogućeni komentari”: korisnik koji smije uređivati dokument.
- Konačna primjena postavke: korisnik koji smije objaviti Workspace nacrt.

Modul ne kreira vlastita Workspace prava.

## 4. Nacrt i objavljivanje

`published_enabled` je vrijednost koju vide čitatelji. `draft_enabled` je
zahtjev urednika. `has_draft_setting` razlikuje izričit odabir u nacrtu od
nasljeđivanja objavljene vrijednosti.

1. Spremanje Workspace nacrta mijenja samo `draft_enabled`.
2. Objavljivanje ga kopira u `published_enabled`.
3. Odbacivanje vraća Editor na objavljenu postavku.
4. Samostalni Editor sprema izravno u obje vrijednosti.

Postojeći komentari ostaju vidljivi kada se novi komentari isključe. Time se
postojeća rasprava ne skriva bez upozorenja.

## 5. Prijava i obavijesti

Prijava je idempotentna po prijavitelju i komentaru. Nova prijava obavještava
vlasnika sadržaja i zadnjeg objavljenog urednika, osim samog prijavitelja.
Obavijest vodi izravno na komentar kada je dostupna sigurna lokalna URL putanja.

Brisanje komentara je soft delete jer je moderacijski audit operativni podatak,
a ne kompatibilnost sa starim dokumentima. Obrisani komentar nestaje sa
stranice, dok autor, izvršitelj brisanja i vrijeme ostaju u bazi.

## 6. HTTP rute

| Metoda | Ime rute | Namjena |
| --- | --- | --- |
| `GET` | `comment.assets.css` | Javni stilovi modula |
| `GET` | `comment.assets.js` | Javna skripta interakcija |
| `GET` | `comment.csrf` | Svježi token za AJAX promjene |
| `POST` | `comment.create` | Kreiranje komentara |
| `POST` | `comment.reaction` | Uključivanje, promjena ili uklanjanje reakcije |
| `POST` | `comment.report` | Prijava komentara |
| `POST` | `comment.delete` | Soft-brisanje komentara |

Sve rute koje mijenjaju stanje zahtijevaju prijavu i CSRF provjeru.

## 7. Integracijski ugovor

Editor koristi opcionalni most `EditorCommentIntegration`. On razrješava
`CommentIntegrationService` samo kada je paket instaliran, pa Editor nastavlja
raditi samostalno bez ovog modula. Statični HTML export može uključiti Comment
CSS, ali interaktivni komentari namjerno se ne ugrađuju u offline export.

## 8. Checklist developera

```bash
composer validate --strict
composer check-platform-reqs
composer on-commit
```

Pri promjeni sheme tijekom razvoja izravno ažuriraj
`resources/migrations/initial_comment_schema.php` i ponovno kreiraj testnu bazu.
Za ovaj modul prije objave ne dodaj migracije kompatibilnosti.

## 9. Backup i povrat

Vidi [sigurnosnu kopiju i povrat](backup_hr.md) za potpune i na područje
ograničene postavke komentara, reakcije i prijave.

## 10. Događaj osobnog praćenja

Uspješno stvaranje ili brisanje objavljuje nepromjenjivi `CommentChanged`.
Događaj sadrži identifikatore i jezik, ali nikada tijelo komentara. Simbioza
User može stvaranje pretvoriti u obavijest pratiteljima bez aplikacijske
ovisnosti u Comment modulu.
