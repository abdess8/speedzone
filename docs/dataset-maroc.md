# Jeu de données logistique Maroc

Générateur de données de démonstration ultra-réalistes pour le dashboard SpeedZone :
commandes marocaines sur les 31 derniers jours, cycle de vie complet (vendeur →
dispatcheur → livreur), documents d'agrégation (ramassages, bordereaux, factures,
décharges de caisse) et réclamations avec fils de discussion.

## 1. Utilisation

```bash
# Ajout au jeu existant (non destructif)
php artisan db:seed --class=MoroccanDatasetSeeder

# Repartir d'une base opérationnelle vide (garde comptes, boutiques, villes et secteurs)
DATASET_PURGE=1 php artisan db:seed --class=MoroccanDatasetSeeder

# Volume personnalisé
DATASET_ORDERS=400 php artisan db:seed --class=MoroccanDatasetSeeder

# Complément de volume sans recréer de réclamations
DATASET_ORDERS=50 DATASET_TICKETS=0 php artisan db:seed --class=MoroccanDatasetSeeder
```

| Variable | Défaut | Rôle |
| --- | --- | --- |
| `DATASET_ORDERS` | `260` | Nombre de commandes visé (le générateur dépasse légèrement pour terminer ses lots) |
| `DATASET_TICKETS` | `30` | Nombre de réclamations |
| `DATASET_PURGE` | `0` | À `1`, purge les tables opérationnelles avant génération |

Export du schéma et des données :

```bash
php artisan dataset:export                      # storage/app/dataset/{schema,dataset}.json + dataset.sql
php artisan dataset:export --format=sql
php artisan dataset:export --dir=/tmp/export --with-credentials
```

- `schema.json` : colonnes, types, clés primaires, index uniques, clés étrangères,
  vocabulaires d'énumération et nombre de lignes, table par table.
- `dataset.json` : toutes les lignes, table par table, en UTF-8 non échappé (arabe lisible).
- `dataset.sql` : script `INSERT` groupé, encadré par `SET FOREIGN_KEY_CHECKS=0` et une
  transaction, rejouable sur une base fraîchement migrée.

Les mots de passe et jetons de `users` sont masqués sauf avec `--with-credentials`.

## 2. Composition d'une génération type (260 commandes visées)

| Élément | Volume | Règle appliquée |
| --- | --- | --- |
| Commandes | 280+ | 17 statuts couverts, chronologie stricte |
| Historiques de statut | ~1 200 | Plus de 85 % des commandes ont une piste d'audit de 2 lignes ou plus |
| Historiques de modification | ~15 | Corrections d'adresse ou de téléphone client |
| Demandes de ramassage | ~35 | 5 à 10 commandes chacune |
| Bordereaux de transfert | ~12 | 5 à 10 commandes chacun, une seule ville d'arrivée |
| Retours | ~35 | 6 statuts de retour couverts |
| Factures vendeurs | ~17 | 5 à 10 commandes livrées, net à payer = encaissé − frais |
| Décharges de caisse livreurs | ~16 | 5 à 10 livraisons, montant arrêté par le livreur |
| Réclamations | 30 | 2 à 4 messages, pièces jointes référencées |

Contexte marocain : 15 villes (Casablanca, Rabat, Marrakech, Fès, Tanger, Agadir,
Meknès, Oujda, Kénitra, Tétouan, Safi, El Jadida, Mohammedia, Béni Mellal, Nador),
65 secteurs, 14 vendeurs et 15 boutiques, 18 livreurs, 2 dispatcheurs, 1 admin.

**Langue** : exactement 20 % des clients (nom, prénom, adresse, note de livraison)
sont en arabe, via un quota et non un tirage aléatoire — par exemple
`مريم الفاسي`, `شارع محمد الخامس، عمارة النور، الطابق 2، الرباط`,
`التسليم بعد السادسة مساء.`. Les 80 % restants sont en français/translittération
(`Othmane Saidi`, `Rue de Fès, Quartier Maarif, Rabat`). Les réclamations, notes de
ramassage et messages de support suivent la même répartition.

## 3. Correspondance des statuts métier ↔ base

### Commandes (`orders.status`)

| Libellé métier | Valeur en base | Sens |
| --- | --- | --- |
| nouvelle / créée | `CREATED` | Saisie par le vendeur, aucun ramassage demandé |
| ramassage demandé | `PICKUP_REQUESTED` | Ajoutée à une demande de ramassage |
| en attente de ramassage | `WAITING_PICKUP` | Demande confirmée, le livreur va passer |
| ramassée | `PICKED_UP` | Collectée chez le vendeur, pas encore scannée au dépôt |
| au dépôt | `IN_DEPOT` | Réceptionnée au hub d'origine |
| bordereau créé | `TRANSFER_CREATED` | Affectée à un bordereau de transfert |
| en transit hub | `IN_TRANSIT` | Bordereau parti vers la ville de destination |
| reçue à destination | `RECEIVED_IN_DESTINATION` | Bordereau réceptionné au hub d'arrivée |
| en ville de livraison | `IN_DELIVERY_CITY` | Prête pour le dernier kilomètre |
| en cours de livraison | `OUT_FOR_DELIVERY` | Affectée à un livreur, en tournée |
| livrée | `DELIVERED` | `delivered_at` renseigné, encaissement enregistré |
| non livrée | `FAILED` | `failure_reason` + `failed_at` renseignés |
| refusée | `REJECTED` | Refus du destinataire |
| annulée | `CANCELED` | Annulation vendeur ou plateforme |
| injoignable | `FAILED` + `failure_reason = CUSTOMER_UNREACHABLE` | Client injoignable |
| retour demandé | `RETURN_REQUESTED` | Retour créé sur la commande |
| retour en cours | `RETURN_IN_PROGRESS` | Retour en circulation |
| retournée au vendeur | `RETURNED` | `returned_at` renseigné, `is_returned = 1` |

Le statut « injoignable » n'est pas un statut distinct en base : il est porté par
`failure_reason`, dont les valeurs sont `CUSTOMER_REFUSED`, `CUSTOMER_UNREACHABLE`,
`CUSTOMER_CANCELED`, `WRONG_ADDRESS`, `CUSTOMER_ABSENT`, `POSTPONED` et `OTHER`.

### Retours (`returns.status`)

| Libellé métier | Valeur en base |
| --- | --- |
| retour demandé | `CREATED` |
| retour reçu au dépôt de la ville de livraison | `RECEIVED_AT_HUB` |
| retour en transit vers le hub du vendeur | `IN_TRANSIT_TO_DEPOT` |
| retour arrivé au hub du vendeur | `ARRIVED_VENDOR_HUB` |
| retour en cours de restitution | `IN_DELIVERY_TO_VENDOR` |
| retour restitué au vendeur | `DELIVERED_TO_VENDOR` |
| retour annulé | `CANCELLED` |

### Ramassages (`pickup_requests.status`)

| Libellé métier | Valeur en base |
| --- | --- |
| en attente | `WAITING_FOR_PICKUP` |
| ramassé | `PICKED_UP` |
| reçu au dépôt | `IN_DEPOT` |
| annulé | `CANCELLED` |

### Bordereaux (`transfers.status`)

`CREATED` (créé) · `WAITING_DISPATCH` (en attente d'affectation) · `IN_TRANSIT` (en transit) ·
`RECEIVED` (réceptionné) · `CANCELLED` (annulé)

### Factures vendeurs (`invoices.status`)

`DRAFT` (brouillon) · `GENERATED` (générée) · `SENT` (envoyée) ·
`PAID` (payée, avec reçu de virement) · `CANCELLED` (annulée)

### Décharges de caisse (`driver_invoices.status`)

`DRAFT` · `GENERATED` · `PAID` · `CANCELLED` — il n'y a pas d'étape « envoyée » côté livreur.

### Réclamations (`support_tickets.status`)

| Libellé métier | Valeur en base |
| --- | --- |
| ouvert | `OPEN` |
| en cours | `IN_PROGRESS` |
| en attente client / vendeur | `WAITING_SELLER` |
| résolu | `RESOLVED` |
| fermé | `CLOSED` (avec `closed_at` et `closed_by`) |

## 4. Modèle relationnel (ERD textuel)

```
cities ──┬─< sectors ──< driver_sector >── users (livreurs)
         ├─< users (ville de rattachement)
         ├─< orders.city_id (ville de livraison)
         └─< transfers.from_city_id / to_city_id

roles ──< role_user >── users ──┬─< stores (owner_id) ──< store_user >── users
                                ├─< orders.seller_id
                                ├─< orders.driver_id
                                └─< support_tickets.created_by / assigned_to

stores ──< orders.store_id            (cloisonnement multi-boutique)

orders ──┬─< order_status_histories   (order_id, status, changed_by, note, created_at)
         ├─< order_change_histories   (corrections d'adresse ou de téléphone)
         ├─< transfer_orders >── transfers
         ├─< invoice_orders   >── invoices
         ├─< driver_transactions ──< driver_invoice_transactions >── driver_invoices
         ├──  pickup_requests   (orders.pickup_request_id, nullable)
         └──  returns           (orders.return_id, nullable)

pickup_requests ──< pickup_status_histories
transfers       ──< transfer_status_histories
returns         ──< return_status_histories
invoices        ──< invoice_logs
driver_invoices ──< driver_finance_logs

support_tickets ──┬─< support_messages            (ticket_id, sender_id, attachment)
                  └─< support_ticket_attachments  (ticket_id, uploaded_by, file_path)
support_tickets.object_type/object_id ──> ORDER | INVOICE | PICKUP_REQUEST
```

Clés étrangères, types exacts et index sont décrits table par table dans
`storage/app/dataset/schema.json` après un `php artisan dataset:export`.

## 5. Cohérence garantie par le générateur

Chronologie — chaque commande suit `created_at < ramassage < expédition < livraison ou retour`.
Aucun horodatage ne dépasse l'instant présent : une commande dont l'étape suivante
tomberait dans le futur reste simplement au statut atteint, ce qui produit
naturellement du trafic « en vol » pour le dashboard (dont des lots ramassés dans
les dernières heures et pas encore scannés au dépôt).

Acteurs — le vendeur crée, le dispatcheur affecte et fait avancer les statuts hub,
le livreur ramasse et livre. Chaque ligne d'historique porte l'auteur réel du
changement et un motif en français ou en arabe.

Agrégation 5 à 10 — ramassages, bordereaux, factures vendeurs et décharges de caisse
regroupent toujours entre 5 et 10 commandes. Les documents annulés libèrent leurs
commandes (`pickup_request_id` remis à `NULL`), exactement comme les services
applicatifs : ils conservent le nombre de colis déclarés mais plus de commande liée.

Finances — `invoices.net_amount` égale la somme des `invoice_orders.final_amount`
(encaissé − frais de livraison) et `driver_invoices.total_amount` la somme des
snapshots de transactions. Les factures payées portent un reçu de virement.

Un audit complet (chronologie, pistes d'audit, règles d'agrégation, sommes
financières, cohérence ville départ/arrivée des bordereaux, intégrité
référentielle après réimport du SQL) est passé sur le jeu généré : toutes les
vérifications sont vertes.

## 6. Fichiers du générateur

| Fichier | Rôle |
| --- | --- |
| `database/seeders/MoroccanDatasetSeeder.php` | Orchestration, réseau de villes, purge, rapport |
| `database/seeders/Support/DatasetContext.php` | Fenêtre temporelle, acteurs, géographie, persistance horodatée |
| `database/seeders/Support/MoroccanLocaleFaker.php` | Noms, adresses, téléphones, CIN, RIB — quota arabe de 20 % |
| `database/seeders/Support/OrderFlowGenerator.php` | Cycle de vie commandes, ramassages, transferts, retours |
| `database/seeders/Support/BillingDatasetGenerator.php` | Factures vendeurs et décharges de caisse livreurs |
| `database/seeders/Support/SupportTicketGenerator.php` | Réclamations, fils de discussion, pièces jointes |
| `database/seeders/Support/SupportTicketTemplates.php` | Sujets et échanges réalistes (français et arabe) |
| `app/Console/Commands/DatasetExportCommand.php` | Export `schema.json`, `dataset.json`, `dataset.sql` |
