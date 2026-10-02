# Biztonsági hotfix — `torrehub-security.php`

Must-use plugin az **éles** oldalra, amíg az új téma le nem váltja a régi plugin-stacket. Élesítés után is bent maradhat: ha az érintett pluginok már nincsenek, nem csinál semmit.

## Mit zár be

| # | Hiba | Javítás |
|---|---|---|
| 1 | `cldirectory-core`: bármely URL `?export_user=1` paraméterrel, **bejelentkezés nélkül** kiírja a 2–9 ID-jű userek sorát (email, jelszó-hash) + usermetát a `wp-content/plugins/cldirectory-core/demo-users/*.json` fájlokba | A paramétert a pluginok betöltése előtt eltávolítja. **Mindenkinél**, adminnál is: a plugin a fájl betöltésekor fut, amikor a WordPress még nem tudja, ki a user, így admin/nem-admin ott nem különíthető el. Az export egy demo-adat fejlesztői eszköz, adminnak sincs rá szüksége. |
| 2 | `rtcl-seller-verification`: a dokumentum feltöltés / törlés / letöltés AJAX a kérésben kapott `user_id`-ben megbízik (feltöltés/törlés nonce nélkül) → bármely belépett user cserélheti, törölheti, **letöltheti** más NIE/NIF dokumentumát | A `user_id`-nek a saját usernek kell lennie; kivétel: `edit_users` jog (admin). Egyébként HTTP 403. A letöltés (`rtcl_ajax_documents_file_download`) is benne van — ugyanaz a hiba, olvasási irányban. |

## Telepítés (éles, GoDaddy)

1. Fájlkezelővel / SFTP-vel nyisd meg: `wp-content/mu-plugins/` (létezik — benne van a GoDaddy `gd-system-plugin.php`).
2. Töltsd fel ide: `hotfix/torrehub-security.php` → `wp-content/mu-plugins/torrehub-security.php` (**közvetlenül** a `mu-plugins` mappába, nem almappába — az almappákat a WordPress nem tölti be).
3. Ellenőrzés: WP admin → Plugins → **Must-Use** fül → „Torrehub — security hotfix” látszik.
4. Ellenőrzés kívülről (kijelentkezve, inkognitóban): nyisd meg `https://torrehub.com/?export_user=1` — majd nézd meg, hogy a `wp-content/plugins/cldirectory-core/demo-users/users.json` **módosítási dátuma nem változott** (fájlkezelőben).
5. Opcionális utómunka: ha a `demo-users/users.json` / `usermeta.json` dátuma a plugin telepítésénél (2026-06-12) **későbbi**, az export már lefutott → ezeket a fájlokat töröld, és **mindenkinek** kényszeríts jelszócserét.

Visszaállítás: a fájl törlése a `mu-plugins` mappából.

## Ajánlott, de nem a hotfix része
- `cldirectory-core` deaktiválása (a régi témának kell — csak a témaváltás után).
- A régi téma `delete_listing_logo_attachment` / `delete_food_attachment` AJAX-a is jogosultság-ellenőrzés nélküli — témaváltással megszűnik.
- A korábbi GitHub-commitban (`909e296`, `audit-data/06-rtcl-options.json`) élő kulcsok vannak (OpenAI, Pusher, Google Maps, licencek) → rotálni.

## Tesztelve
Lokálisan (`bin/` környezet), lásd a commit üzenetét.
