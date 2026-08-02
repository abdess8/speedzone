<?php

return [
    'title' => 'Documentation API',
    'subtitle' => 'Connectez votre boutique, votre ERP ou votre plateforme e-commerce à SpeedZone et pilotez vos expéditions par programmation.',
    'page_title' => 'Intégrations API',

    'search' => [
        'placeholder' => 'Rechercher un endpoint…',
        'empty' => 'Aucun endpoint ne correspond à « :query ».',
    ],

    'actions' => [
        'copy' => 'Copier',
        'copied' => 'Copié',
        'manage_tokens' => 'Gérer mes jetons',
        'back_to_top' => 'Haut de page',
        'toggle_nav' => 'Sommaire',
        'download_postman' => 'Collection Postman',
        'downloaded' => 'Téléchargée',
    ],

    // Les noms de variables sont écrits sans leurs accolades à dessein : le
    // compilateur de messages Vue i18n lit `{…}` comme un placeholder.
    'postman' => [
        'collection_name' => 'API SpeedZone',
        'hint' => 'Importez-la dans Postman pour éprouver chaque endpoint sur votre propre compte. Les requêtes sont enchaînées : une seule exécution de la collection crée une commande, la relit, la modifie et programme son ramassage.',
        'token_embedded' => 'Le jeton collé ci-dessus sera inscrit dans le fichier téléchargé. Traitez ce fichier comme un mot de passe.',
        'description' => "Requêtes prêtes à l'emploi pour l'API de livraison SpeedZone.\n\n### Avant de commencer\n\n1. Ouvrez les variables de la collection et collez votre jeton personnel dans `token`. Vous en créez un depuis votre compte SpeedZone, rubrique jetons API.\n2. Vérifiez que `baseUrl` pointe bien vers l'environnement visé.\n3. Compte multi-boutiques ? Renseignez `storeId` avec la boutique sur laquelle agir. Laissez vide pour utiliser votre boutique par défaut.\n\n### Exécuter toute la collection\n\nLes dossiers sont ordonnés pour qu'une exécution complète aboutisse. Les requêtes de référence renseignent `cityId` et `sectorId`, la création de commande enregistre son `orderId` et son `trackingNumber`, et toutes les requêtes suivantes les réutilisent.\n\nLa suppression de commande est branchée sur sa propre variable `deletableOrderId`, volontairement laissée vide, pour qu'une exécution de la collection ne détruise jamais la commande que les autres requêtes utilisent encore. Renseignez-la à la main quand vous voulez tester la suppression.\n\n### Limites\n\nLes appels sont plafonnés à :limit requêtes par minute, comptées par jeton. Chaque réponse indique le quota restant dans `X-RateLimit-Remaining`.",
    ],

    'console' => [
        'title' => 'Vos identifiants',
        'description' => 'Chaque appel est authentifié par un jeton personnel. Créez-en un depuis votre compte, puis collez-le dans l\'en-tête :header.',
        'base_url' => 'URL de base',
        'auth_header' => 'En-tête d\'authentification',
        'store_header' => 'En-tête boutique',
        'store_hint' => 'Utile uniquement si votre compte gère plusieurs boutiques.',
        'no_store' => 'Votre compte ne gère qu\'une boutique — vous pouvez omettre cet en-tête.',
        'token_placeholder' => 'VOTRE_JETON_API',
        'token_notice' => 'Un jeton ne s\'affiche qu\'une seule fois, à sa création. Conservez-le en lieu sûr : il donne accès à toutes vos commandes.',
    ],

    'nav' => [
        'getting_started' => 'Démarrer',
        'orders' => 'Commandes',
        'pickups' => 'Demandes de ramassage',
        'reference' => 'Données de référence',
    ],

    'labels' => [
        'endpoint' => 'Endpoint',
        'headers' => 'En-têtes',
        'query_params' => 'Paramètres de requête',
        'path_params' => 'Paramètres d\'URL',
        'body_params' => 'Paramètres du corps',
        'responses' => 'Réponses',
        'request_example' => 'Requête',
        'response_example' => 'Réponse',
        'required' => 'obligatoire',
        'optional' => 'optionnel',
        'permission' => 'Permission',
        'default' => 'Défaut',
        'notes' => 'À savoir',
        'no_body' => 'Cet endpoint n\'attend aucun corps de requête.',
        'no_params' => 'Cet endpoint n\'attend aucun paramètre.',
        'name' => 'Nom',
        'type' => 'Type',
        'description' => 'Description',
        'status_code' => 'Code',
        'value' => 'Valeur',
        'label' => 'Libellé',
        'meaning' => 'Signification',
    ],

    'sections' => [
        'introduction' => [
            'title' => 'Introduction',
            'lead' => 'L\'API SpeedZone est une API REST en HTTPS. Elle consomme et renvoie du JSON, et vous permet de créer des expéditions, de les suivre tout au long du circuit de livraison et d\'en récupérer le statut sans jamais ouvrir le tableau de bord.',
            'conventions_title' => 'Conventions',
            'conventions' => [
                'json' => 'Tous les corps de requête sont en JSON, toutes les réponses le sont également.',
                'wrapper' => 'Une ressource unique est encapsulée dans un objet `data`. Une liste y ajoute `links` et `meta` pour la pagination.',
                // L'exemple est injecté plutôt qu'écrit en dur : le pont Vue i18n
                // réécrit les placeholders `:nom` de Laravel, et les deux-points
                // d'une heure ISO seraient pris pour l'un d'eux.
                'dates' => 'Toutes les dates sont des chaînes ISO 8601 en UTC, par exemple :example.',
                'amounts' => 'Tous les montants sont des nombres en dirhams marocains (MAD), jamais des chaînes.',
                'ids' => 'Les identifiants de commande dans les URL sont numériques. Pour retrouver une commande par son numéro de suivi, utilisez l\'endpoint de suivi dédié.',
            ],
            'accept_title' => 'Envoyez toujours l\'en-tête Accept',
            'accept_body' => 'Sans `Accept: application/json`, un appel non authentifié est redirigé vers la page de connexion et vous recevez du HTML au lieu d\'un 401. C\'est de loin l\'erreur d\'intégration la plus fréquente.',
        ],

        'authentication' => [
            'title' => 'Authentification',
            'lead' => 'L\'API utilise des jetons personnels de type bearer. Un jeton appartient à votre compte utilisateur et hérite de ses permissions : il ne peut jamais atteindre des données que vous ne voyez pas dans le tableau de bord.',
            'create_title' => 'Créer un jeton',
            'create_steps' => [
                'open' => 'Ouvrez :link depuis votre compte.',
                'name' => 'Donnez au jeton un nom qui indique où il sera utilisé, par exemple « Shopify production ».',
                'abilities' => 'Cochez les droits dont il a besoin : `read`, `create`, `update`, `delete`.',
                'copy' => 'Copiez le jeton immédiatement — il n\'est affiché qu\'une fois et ne sera jamais réaffiché.',
            ],
            'usage_title' => 'Utiliser le jeton',
            'usage_body' => 'Envoyez-le à chaque appel dans l\'en-tête `Authorization`, préfixé par `Bearer`.',
            'abilities_title' => 'Droits du jeton',
            'abilities_body' => 'Un jeton limité à `read` peut lister et consulter, mais sera refusé sur toute écriture. Accordez le strict nécessaire.',
            'revoke_title' => 'Révoquer un jeton',
            'revoke_body' => 'La suppression d\'un jeton depuis votre compte prend effet immédiatement : tout appel qui l\'utilise encore renvoie 401.',
        ],

        'stores' => [
            'title' => 'Comptes multi-boutiques',
            'lead' => 'Si votre compte gère plusieurs boutiques, les commandes, ramassages et factures sont cloisonnés par boutique. Un appel qui ne précise pas sa cible est servi depuis votre boutique par défaut.',
            'header_body' => 'Pour viser une autre boutique, envoyez son identifiant dans l\'en-tête `X-Store-Id`. Une boutique dont vous n\'êtes pas membre est ignorée silencieusement et l\'appel retombe sur votre boutique par défaut.',
            'team_title' => 'Membres d\'équipe',
            'team_body' => 'Un jeton créé par un membre d\'équipe lit et écrit pour le compte du vendeur auquel il est rattaché, dans la limite des boutiques auxquelles il a accès.',
            'your_stores' => 'Boutiques accessibles avec votre compte',
            'store_id' => 'ID boutique',
            'store_name' => 'Boutique',
            'store_default' => 'Par défaut',
        ],

        'errors' => [
            'title' => 'Erreurs',
            'lead' => 'L\'API utilise les codes HTTP standards. Toute réponse 4xx ou 5xx porte un `message`, et les échecs de validation y ajoutent un objet `errors` indexé par nom de champ.',
            'codes_title' => 'Codes de statut',
            // Les clés sont préfixées pour rester des chaînes : PHP convertit une
            // clé numérique en entier, ce qui rend le chemin i18n pénible à résoudre.
            'codes' => [
                'c200' => 'L\'appel a réussi.',
                'c201' => 'La ressource a été créée.',
                'c204' => 'L\'appel a réussi et il n\'y a rien à renvoyer.',
                'c401' => 'Jeton absent, malformé ou révoqué.',
                'c403' => 'Le jeton est valide mais votre compte n\'a pas la permission, ou la ressource appartient à quelqu\'un d\'autre.',
                'c404' => 'Ressource introuvable, ou située hors de la boutique que vous ciblez.',
                'c422' => 'Le corps de la requête n\'a pas passé la validation. Lisez l\'objet `errors` pour savoir quel champ est en cause.',
                'c429' => 'Limite de débit dépassée.',
                'c500' => 'Un incident de notre côté. Réessayez, puis contactez le support si cela persiste.',
            ],
            'validation_title' => 'Erreurs de validation',
            'validation_body' => 'Un 422 énumère chaque champ en échec, avec un ou plusieurs messages. Les champs absents de `errors` ont été acceptés.',
        ],

        'rate_limits' => [
            'title' => 'Limitation de débit',
            'lead' => 'Les appels sont plafonnés à :limit requêtes par minute, comptées par jeton. Au-delà, l\'API renvoie 429 avec un en-tête `Retry-After` indiquant le nombre de secondes à patienter.',
            'headers_title' => 'En-têtes de quota',
            'headers_body' => 'Chaque réponse porte `X-RateLimit-Limit` et `X-RateLimit-Remaining`, de quoi vous auto-limiter avant d\'être bloqué.',
            'advice_title' => 'Rester sous la limite',
            'advice_body' => 'Groupez vos lectures avec `per_page` plutôt que de récupérer les commandes une par une, et espacez vos tentatives de façon exponentielle après un 429.',
        ],

        'statuses' => [
            'title' => 'Statuts de commande',
            'lead' => 'Une commande suit un circuit fixe. Les statuts sont des chaînes en majuscules — filtrez dessus avec le paramètre `status`.',
            'groups_title' => 'Raccourcis de statut',
            'groups_body' => 'Le paramètre `status_group` sélectionne tout un groupe de statuts d\'un coup, pratique pour un tableau de bord.',
            'transitions_title' => 'Qui fait avancer une commande',
            'transitions_body' => 'Un compte vendeur ne peut pas faire avancer une commande dans le circuit lui-même. Vous créez la commande, puis demandez un ramassage ; à partir de là le statut est piloté par nos équipes d\'exploitation et par le livreur. Votre intégration lit le statut, elle ne l\'écrit pas.',
            'group_pickup' => 'En attente de ramassage',
            'group_delivery' => 'En cours de livraison',
            'group_delivered' => 'Livrée',
            'group_failed' => 'Échouée ou refusée',
        ],
    ],

    'endpoints' => [
        'orders_list' => [
            'title' => 'Lister les commandes',
            'description' => 'Renvoie vos commandes, les plus récentes d\'abord, paginées. Tous les filtres ci-dessous sont combinables.',
        ],
        'orders_create' => [
            'title' => 'Créer une commande',
            'description' => 'Enregistre une nouvelle expédition. La commande démarre en `CREATED` et reçoit immédiatement un numéro de suivi. Le prix de livraison est repris du secteur si vous ne le précisez pas.',
        ],
        'orders_show' => [
            'title' => 'Consulter une commande',
            'description' => 'Renvoie une commande avec sa ville, son secteur, sa demande de ramassage, sa chronologie de statuts et son historique de modifications.',
        ],
        'orders_track' => [
            'title' => 'Consulter une commande par numéro de suivi',
            'description' => 'Même contenu que la consultation par ID, mais indexé sur le numéro de suivi que voit votre client. À utiliser quand votre système ne stocke que ce numéro.',
        ],
        'orders_update' => [
            'title' => 'Modifier une commande',
            'description' => 'Corrige une commande qui n\'a pas encore été ramassée. N\'envoyez que les champs à modifier.',
        ],
        'orders_delete' => [
            'title' => 'Supprimer une commande',
            'description' => 'Supprime définitivement une commande appartenant à votre compte.',
        ],
        'orders_tracking' => [
            'title' => 'Chronologie des statuts',
            'description' => 'Renvoie tous les statuts par lesquels la commande est passée, du plus ancien au plus récent, avec l\'auteur et la date de chaque changement. C\'est ce qui alimente une page « suivre mon colis ».',
        ],
        'orders_pdf' => [
            'title' => 'Télécharger l\'étiquette',
            'description' => 'Renvoie l\'étiquette d\'expédition thermique en PDF, prête à imprimer. La réponse est de type `application/pdf`, pas du JSON.',
        ],

        'pickups_list' => [
            'title' => 'Lister les demandes de ramassage',
            'description' => 'Renvoie les demandes de ramassage émises par votre compte, les plus récentes d\'abord.',
        ],
        'pickups_create' => [
            'title' => 'Demander un ramassage',
            'description' => 'Demande à un livreur de venir collecter un lot de commandes. Chaque commande doit vous appartenir, être encore en `CREATED` et ne pas déjà être rattachée à une autre demande.',
        ],
        'pickups_show' => [
            'title' => 'Consulter une demande de ramassage',
            'description' => 'Renvoie une demande de ramassage avec ses commandes, le livreur affecté et son historique de statuts.',
        ],

        'cities_list' => [
            'title' => 'Lister les villes',
            'description' => 'Renvoie les villes que nous desservons. Il vous faut un `city_id` issu de cet endpoint pour créer une commande.',
        ],
        'city_sectors' => [
            'title' => 'Lister les secteurs d\'une ville',
            'description' => 'Renvoie les secteurs actifs d\'une ville, avec leur prix de livraison. Idéal pour construire le sélecteur ville/secteur dépendant dans votre propre interface.',
        ],
        'sectors_list' => [
            'title' => 'Lister les secteurs',
            'description' => 'Renvoie tous les secteurs, toutes villes confondues, paginés. Préférez l\'endpoint par ville pour alimenter une liste déroulante.',
        ],
        'user_me' => [
            'title' => 'Compte courant',
            'description' => 'Renvoie le compte auquel appartient le jeton. Pratique comme test de bon fonctionnement : si cet appel renvoie 200, votre jeton et vos en-têtes sont corrects.',
        ],
    ],

    'notes' => [
        'orders_update_status' => 'Seule une commande encore en `CREATED` peut être modifiée. Une fois ramassée, l\'appel renvoie 403.',
        'orders_update_sector' => 'Si vous changez `city_id`, envoyez un `sector_id` appartenant à cette ville, sans quoi la validation échoue.',
        'orders_delete_scope' => 'La commande doit appartenir à votre compte et à la boutique ciblée. L\'API n\'empêche pas de supprimer une commande déjà engagée dans le circuit de livraison — à manier avec précaution.',
        'orders_create_amount' => 'Avec `payment_method: CASH`, `order_amount` est la somme encaissée par le livreur et devient obligatoire. Avec `CARD_PAYMENT`, le client a déjà payé : `order_amount` est forcé à null et vous pouvez renseigner `order_value` à titre d\'assurance.',
        'orders_create_sector' => '`sector_id` doit appartenir à `city_id`, et les deux doivent être actifs. Récupérez-les depuis les endpoints de référence ci-dessous.',
        'orders_list_partner' => 'Les commandes issues d\'une place de marché partenaire ne figurent pas dans cette liste.',
        'pickups_create_address' => '`pickup_address` doit correspondre à l\'une des deux adresses de ramassage enregistrées sur votre profil. Configurez-les d\'abord dans le tableau de bord.',
        'pdf_accept' => 'N\'envoyez pas `Accept: application/json` sur cet appel : vous recevriez une erreur JSON au lieu du fichier.',
    ],

    'fields' => [
        'customer_first_name' => 'Prénom du destinataire.',
        'customer_last_name' => 'Nom du destinataire.',
        'customer_phone' => 'Téléphone du destinataire, tel que le livreur le composera.',
        'customer_address' => 'Adresse complète, avec les éventuelles consignes de livraison.',
        'city_id' => 'Ville de destination. Doit être une ville active.',
        'sector_id' => 'Secteur de destination dans la ville. Détermine le prix de livraison.',
        'payment_method' => '`CASH` si le livreur encaisse à la livraison, `CARD_PAYMENT` si le client vous a déjà payé.',
        'order_amount' => 'Montant encaissé par le livreur auprès du client. Obligatoire pour `CASH`, ignoré pour `CARD_PAYMENT`.',
        'order_value' => 'Valeur déclarée de la marchandise. N\'a de sens que pour `CARD_PAYMENT`.',
        'delivery_price' => 'Frais de livraison. Omettez-le pour appliquer le prix du secteur.',
        'notes' => 'Consignes libres à destination du livreur.',
        'is_fragile' => 'Signale le colis comme fragile sur l\'étiquette.',
        'can_be_opened' => 'Autorise le client à ouvrir le colis avant de payer.',
        'option_exchange' => 'Le livreur récupère un article en échange lors de la livraison.',
        'order_ids' => 'Commandes à collecter. Chacune doit vous appartenir et être encore en `CREATED`.',
        'pickup_address' => 'L\'une des adresses de ramassage configurées sur votre profil.',
        'pickup_notes' => 'Consignes pour le livreur qui vient collecter.',
        'to_status' => 'Statut cible.',
        'comment' => 'Note libre enregistrée avec le changement de statut.',
    ],

    'filters' => [
        'page' => 'Numéro de page.',
        'per_page' => 'Résultats par page. Plafonné à 100.',
        'tracking_number' => 'Correspondance partielle sur le numéro de suivi. `order_number` est accepté comme alias.',
        'customer_name' => 'Correspondance partielle sur le prénom, le nom ou le nom complet du destinataire.',
        'customer_phone' => 'Correspondance partielle sur le téléphone du destinataire.',
        'status' => 'Un statut, ou plusieurs pour élargir la recherche.',
        'status_group' => 'Un groupe de statuts nommé : `pickup`, `delivery`, `delivered` ou `failed`. Ignoré si `status` est fourni.',
        'payment_method' => 'Filtre sur `CASH` ou `CARD_PAYMENT`.',
        'city_id' => 'Restreint à une ville de destination.',
        'sector_id' => 'Restreint à un secteur de destination.',
        'created_from' => 'Commandes créées à partir de cette date, incluse.',
        'created_to' => 'Commandes créées jusqu\'à cette date, incluse.',
        'delivery_from' => 'Commandes livrées à partir de cette date, incluse.',
        'delivery_to' => 'Commandes livrées jusqu\'à cette date, incluse.',
        'is_fragile' => 'Ne garde que les colis fragiles, ou seulement les autres.',
        'can_be_opened' => 'Ne garde que les colis ouvrables avant paiement.',
        'sort' => 'Colonne de tri : `created_at`, `tracking_number`, `order_amount`, `order_value`, `delivery_price` ou `status`.',
        'direction' => 'Sens du tri, `asc` ou `desc`.',
        'pickup_status' => 'Filtre sur le statut de la demande de ramassage.',
        'city_search' => 'Correspondance partielle sur le nom de la ville.',
        'order_id' => 'Identifiant numérique de la commande.',
        'tracking_path' => 'Numéro de suivi de la commande, par exemple `SPD-2026-583920`.',
        'pickup_id' => 'Identifiant numérique de la demande de ramassage.',
        'city_path' => 'Identifiant numérique de la ville.',
    ],

    'headers' => [
        'authorization' => 'Votre jeton personnel, préfixé par `Bearer`.',
        'accept' => 'Doit valoir `application/json`.',
        'content_type' => 'Doit valoir `application/json` sur les requêtes avec corps.',
        'store' => 'Identifiant de la boutique visée. Optionnel sur un compte mono-boutique.',
    ],
];
