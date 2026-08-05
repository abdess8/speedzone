<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Centre d'aide
    |--------------------------------------------------------------------------
    */
    'page_title' => 'Aide',
    'title' => 'Guide d\'utilisation',
    'subtitle' => 'Des parcours guidés, directement dans l\'application. Choisissez un guide : nous vous emmenons sur le bon écran et vous accompagnons étape par étape.',
    'search' => 'Rechercher un guide…',
    'empty' => 'Aucun guide ne correspond à votre recherche.',
    'empty_catalog' => 'Aucun guide n\'est encore disponible pour votre rôle.',
    'available' => '{count} guide disponible|{count} guides disponibles',
    'completed_count' => '{completed} sur {total} terminés',

    'card' => [
        'steps' => '{count} étape|{count} étapes',
        'minutes' => '{count} min',
        'start' => 'Démarrer le guide',
        'resume' => 'Reprendre à l\'étape {step}',
        'replay' => 'Rejouer le guide',
        'reset' => 'Réinitialiser',
        'reset_confirm' => 'Oublier votre progression sur ce guide ?',
    ],

    'status' => [
        'new' => 'Nouveau',
        'in_progress' => 'En cours',
        'completed' => 'Terminé',
        'completed_times' => 'Suivi {count} fois',
    ],

    'categories' => [
        'orders' => 'Commandes',
        'pickups' => 'Ramassages',
        'returns' => 'Retours',
        'invoices' => 'Facturation',
        'finance' => 'Caisse',
        'stock' => 'Stock',
        'stores' => 'Boutiques',
        'team' => 'Équipe',
        'settings' => 'Réglages',
    ],

    /*
    |--------------------------------------------------------------------------
    | Contrôles du tour
    |--------------------------------------------------------------------------
    */
    'tour' => [
        'progress' => 'Étape {current} sur {total}',
        'start' => 'Commencer',
        'next' => 'Suivant',
        'previous' => 'Précédent',
        'finish' => 'Terminer',
        'quit' => 'Quitter le guide',
        'quit_short' => 'Quitter',
        'quit_confirm_title' => 'Quitter le guide ?',
        'quit_confirm_text' => 'Votre progression est enregistrée : vous pourrez reprendre depuis le centre d\'aide.',
        'quit_confirm_yes' => 'Oui, quitter',
        'quit_confirm_no' => 'Continuer le guide',
        'waiting' => 'À vous de jouer',
        'loading' => 'Recherche de l\'élément…',
        'lost_title' => 'Élément introuvable',
        'lost_body' => 'L\'écran a changé depuis le début du guide. Reprenez le guide depuis le centre d\'aide.',
        'lost_restart' => 'Revenir au bon écran',
    ],

    /*
    |--------------------------------------------------------------------------
    | Guides par rôle (écran d'administration)
    |--------------------------------------------------------------------------
    */
    'access' => [
        'title' => 'Guides par rôle',
        'subtitle' => 'Choisissez quels rôles se voient proposer chaque guide interactif dans le centre d\'aide.',
        'back_to_roles' => 'Retour aux rôles',
        'guide_column' => 'Guide',
        'toggle_column' => 'Tout cocher / décocher pour ce rôle',
        'unrestricted' => 'Tous les rôles',
        'not_playable' => 'Sans parcours',
        'unrestricted_help' => 'Un guide dont aucun rôle n\'est coché reste proposé à tous les rôles : le silence signifie « pas de restriction », jamais « caché pour tout le monde ».',
        'permission_help' => 'Les permissions affichées restent prioritaires : un rôle qui ne peut pas ouvrir l\'écran concerné ne verra pas le guide, même coché.',
        'saved' => 'Accès aux guides mis à jour.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalogue — une entrée par clé de guide (les tirets deviennent des « _ »)
    |--------------------------------------------------------------------------
    */
    'catalog' => [
        'orders_create' => [
            'title' => 'Créer une commande avec le formulaire',
            'summary' => 'Saisissez une commande de bout en bout : coordonnées du client, ville et secteur, options du colis, mode de paiement et montants.',
            'audience' => 'Vendeur, Équipe',
        ],
        'orders_import' => [
            'title' => 'Ajout de commandes en masse',
            'summary' => 'Importez des dizaines de commandes depuis un fichier Excel ou CSV : modèle, mappage des colonnes, correction des erreurs et validation finale.',
            'audience' => 'Vendeur, Administrateur',
        ],
        'pickups_create' => [
            'title' => 'Créer une demande de ramassage',
            'summary' => 'Regroupez vos commandes prêtes en une demande de ramassage : sélection des commandes, adresse d\'enlèvement et consignes au livreur.',
            'audience' => 'Vendeur, Équipe',
        ],
        'returns_request' => [
            'title' => 'Demander un retour',
            'summary' => 'Renvoyez un colis vers votre boutique : commandes éligibles, motif du retour et suivi de la demande.',
            'audience' => 'Vendeur',
        ],
        'invoices_read' => [
            'title' => 'Lire vos factures',
            'summary' => 'Comprenez une facture ligne par ligne : période, montants livrés, frais de livraison et de retour, net à percevoir et export PDF.',
            'audience' => 'Vendeur, Administrateur',
        ],
        'stock_catalog' => [
            'title' => 'Gérer votre catalogue produits',
            'summary' => 'Créez une référence de bout en bout : nom et code-barres, prix de vente et prix d\'achat, poids et dimensions, photo. Vous verrez aussi comment lire les indicateurs de rupture et importer un catalogue entier depuis Excel.',
            'audience' => 'Vendeur, Équipe',
        ],
        'stock_shipment' => [
            'title' => 'Envoyer du stock au dépôt',
            'summary' => 'Préparez un bon d\'envoi : produits et quantités, dépôt de destination, date d\'expédition. Vous suivrez ensuite la collecte chez vous et le comptage à l\'arrivée.',
            'audience' => 'Vendeur, Équipe',
        ],
        'stock_inventory' => [
            'title' => 'Faire l\'inventaire de votre stock',
            'summary' => 'Comptez vos références en une seule passe : saisie au clavier ou au scanner, écarts calculés en direct, motif obligatoire sur chaque correction et traçabilité complète sur la fiche produit.',
            'audience' => 'Vendeur, Équipe',
        ],
        'stores_manage' => [
            'title' => 'Ajouter et changer de boutique',
            'summary' => 'Créez une boutique et apprenez à basculer d\'une boutique à l\'autre : chaque commande, facture et ramassage appartient à la boutique active.',
            'audience' => 'Vendeur',
        ],
        'team_member' => [
            'title' => 'Ajouter un membre à votre équipe',
            'summary' => 'Créez un sous-utilisateur pour votre compte vendeur : rôles personnalisés, boutiques accessibles et mot de passe d\'accès.',
            'audience' => 'Vendeur',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Étapes des tours — guides.tours.<clé>.<id d'étape>.{title,body,hint}
    |--------------------------------------------------------------------------
    */
    'tours' => [
        'orders_import' => [
            'welcome' => [
                'title' => 'Bienvenue dans le guide d\'importation en masse !',
                'body' => 'Suivons ensemble les étapes. En moins de 3 minutes, vous saurez transformer un fichier Excel en commandes prêtes à être ramassées.',
            ],
            'template' => [
                'title' => 'Téléchargez le modèle',
                'body' => 'Cliquez ici pour obtenir le format exact requis. Le fichier contient une ligne d\'exemple : conservez la ligne d\'en-tête et remplacez simplement les données.',
            ],
            'dropzone' => [
                'title' => 'Déposez votre fichier',
                'body' => 'Glissez votre fichier rempli ici, ou cliquez pour le parcourir. Nous le lisons dans votre navigateur : rien n\'est envoyé tant que vous n\'avez pas validé.',
                'hint' => 'Sélectionnez un fichier .xlsx ou .csv pour continuer.',
            ],
            'mapping' => [
                'title' => 'Vérifiez la correspondance des colonnes',
                'body' => 'Vérifiez que les colonnes de votre Excel (Nom, Téléphone, Adresse, Prix) correspondent bien aux champs du système. Les correspondances trouvées automatiquement portent une étoile — corrigez celles qui ne conviennent pas.',
                'hint' => 'Cliquez sur « Suivant » pour ouvrir le mappage des colonnes.',
            ],
            'review' => [
                'title' => 'Prévisualisez et corrigez',
                'body' => 'Corrigez les erreurs éventuelles en direct avant de valider : les lignes en rouge sont bloquantes, les lignes orange signalent un doublon. Chaque cellule est modifiable ici même.',
                'hint' => 'Cliquez sur « Valider le mappage » pour afficher la prévisualisation.',
            ],
            'save' => [
                'title' => 'Lancez l\'importation',
                'body' => 'Vérifiez d\'abord la liste, puis enregistrez. Le bouton reste gris tant qu\'une erreur subsiste ou qu\'une modification n\'a pas été revérifiée.',
                'hint' => 'Enregistrez la liste pour terminer le guide.',
            ],
            'done' => [
                'title' => 'Félicitations !',
                'body' => 'Vos commandes sont importées et apparaissent désormais dans votre liste. Vous pouvez rejouer ce guide à tout moment depuis le centre d\'aide.',
                'cta' => 'Voir mes commandes',
            ],
        ],

        'orders_create' => [
            'welcome' => [
                'title' => 'Créons votre première commande',
                'body' => 'Le formulaire tient en trois blocs : le client, le colis, puis le paiement. Nous les parcourons dans cet ordre.',
            ],
            'customer' => [
                'title' => 'Les coordonnées du client',
                'body' => 'Nom, téléphone, puis ville et secteur : c\'est le secteur qui détermine le tarif de livraison, il est donc obligatoire. L\'adresse doit être assez précise pour que le livreur trouve du premier coup.',
            ],
            'package' => [
                'title' => 'Le colis et ses options',
                'body' => 'Signalez ici un colis fragile, autorisez ou non l\'ouverture avant paiement, et cochez l\'échange si le livreur doit repartir avec un autre colis. Les notes sont lues par le livreur.',
            ],
            'payment' => [
                'title' => 'Paiement et montants',
                'body' => 'En paiement à la livraison, saisissez le montant à encaisser auprès du client. Pour une commande déjà payée, indiquez seulement la valeur déclarée du colis — elle sert en cas de litige.',
            ],
            'submit' => [
                'title' => 'Enregistrez la commande',
                'body' => 'Enregistrez pour ouvrir la fiche de la commande, ou utilisez « Créer et nouvelle » pour enchaîner une deuxième saisie sans revenir à la liste.',
                'hint' => 'Enregistrez la commande pour continuer.',
            ],
            'done' => [
                'title' => 'Commande créée !',
                'body' => 'Elle est maintenant au statut « Créée » et attend un ramassage. La suite logique : la joindre à une demande de ramassage.',
                'cta' => 'Voir mes commandes',
            ],
        ],

        'pickups_create' => [
            'welcome' => [
                'title' => 'Demander un ramassage',
                'body' => 'Une demande de ramassage regroupe les commandes qu\'un livreur viendra chercher chez vous. Voyons comment en créer une.',
            ],
            'open' => [
                'title' => 'Ouvrez le formulaire',
                'body' => 'Toutes vos demandes passées figurent sur cette page, avec leur statut. Commençons par en créer une nouvelle.',
                'hint' => 'Cliquez sur « Nouvelle demande » pour ouvrir le formulaire.',
            ],
            'orders' => [
                'title' => 'Choisissez les commandes',
                'body' => 'Seules les commandes créées et pas encore rattachées à un ramassage apparaissent ici. Cochez celles que le livreur emportera.',
                'hint' => 'Sélectionnez au moins une commande.',
            ],
            'address' => [
                'title' => 'Indiquez l\'adresse d\'enlèvement',
                'body' => 'C\'est là que le livreur se présentera. L\'adresse de votre boutique est proposée par défaut ; vous pouvez en saisir une autre pour ce ramassage seulement.',
                'hint' => 'Cliquez sur « Suivant » pour passer à l\'adresse.',
            ],
            'summary' => [
                'title' => 'Vérifiez et ajoutez vos consignes',
                'body' => 'Le récapitulatif rappelle le nombre de colis. Les notes sont visibles par le livreur : créneau souhaité, étage, personne à demander.',
                'hint' => 'Cliquez sur « Suivant » pour afficher le récapitulatif.',
            ],
            'submit' => [
                'title' => 'Envoyez la demande',
                'body' => 'Une fois envoyée, la demande part en attente d\'affectation : un livreur lui sera assigné, et vous suivrez son avancement depuis la liste.',
                'hint' => 'Envoyez la demande pour terminer le guide.',
            ],
            'done' => [
                'title' => 'Demande envoyée !',
                'body' => 'Vous êtes sur sa fiche de suivi. Le statut évoluera jusqu\'à la prise en charge effective de vos colis.',
            ],
        ],

        'returns_request' => [
            'welcome' => [
                'title' => 'Demander le retour d\'un colis',
                'body' => 'Un retour ramène un colis vers votre boutique — refus du client, erreur d\'article, adresse introuvable. Voici la marche à suivre.',
            ],
            'open' => [
                'title' => 'Ouvrez le formulaire',
                'body' => 'Cette page liste vos retours en cours et leur statut. Créons-en un nouveau.',
                'hint' => 'Cliquez sur « Nouvelle demande » pour ouvrir le formulaire.',
            ],
            'order' => [
                'title' => 'Choisissez la commande',
                'body' => 'Seules les commandes déjà en circulation sont éligibles, et une commande ne peut avoir qu\'un retour actif à la fois. Si la vôtre n\'apparaît pas, c\'est l\'une de ces deux raisons.',
                'hint' => 'Sélectionnez la commande concernée.',
            ],
            'reason' => [
                'title' => 'Précisez le motif',
                'body' => 'Le motif oriente le traitement du colis à son arrivée, et vos notes sont lues par l\'équipe logistique : soyez précis, cela évite un aller-retour.',
                'hint' => 'Choisissez un motif de retour.',
            ],
            'submit' => [
                'title' => 'Envoyez la demande',
                'body' => 'La demande part en attente de validation. Une fois acceptée, le colis est programmé pour revenir vers votre boutique.',
                'hint' => 'Envoyez la demande pour terminer le guide.',
            ],
            'done' => [
                'title' => 'Retour demandé !',
                'body' => 'Vous suivrez son avancement depuis cette fiche, et depuis la liste des retours.',
            ],
        ],

        'invoices_read' => [
            'welcome' => [
                'title' => 'Comprendre vos factures',
                'body' => 'Une facture regroupe les commandes livrées sur une période et calcule ce qui vous revient. Décomposons-la ensemble.',
            ],
            'filters' => [
                'title' => 'Retrouver une facture',
                'body' => 'Filtrez par numéro, par statut ou par période de génération. Le statut vous dit où en est le règlement : en attente, payée ou annulée.',
            ],
            'table' => [
                'title' => 'Lire la liste',
                'body' => 'Chaque ligne résume une facture : période couverte, nombre de commandes et net à percevoir. Les colonnes chiffrées sont triables d\'un clic.',
            ],
            'open' => [
                'title' => 'Ouvrez une facture',
                'body' => 'Le détail est là où tout se joue : le récapitulatif des montants et la liste des commandes qui les composent.',
                'hint' => 'Ouvrez une facture pour continuer.',
            ],
            'summary' => [
                'title' => 'Le récapitulatif',
                'body' => 'Montant livré moins frais de livraison, moins frais de retour : le résultat est le net à percevoir, celui qui vous sera versé. Les frais de retour concernent les colis revenus.',
            ],
            'orders' => [
                'title' => 'Le détail par commande',
                'body' => 'Chaque commande facturée apparaît avec son montant final. C\'est ici qu\'il faut regarder quand un total vous surprend.',
            ],
            'pdf' => [
                'title' => 'Exportez la facture',
                'body' => 'Consultez le PDF ou téléchargez-le pour votre comptabilité. Le document reprend exactement les montants affichés ici.',
            ],
            'done' => [
                'title' => 'Vous savez lire une facture !',
                'body' => 'En cas d\'écart sur un montant, ouvrez un ticket de support en citant le numéro de facture et la commande concernée.',
            ],
        ],

        'stock_catalog' => [
            'welcome' => [
                'title' => 'Votre catalogue produits',
                'body' => 'Le catalogue est la liste de ce que vous vendez. Une fois vos références créées, vous composerez vos commandes en les choisissant au lieu de saisir un montant à la main.',
            ],
            'summary' => [
                'title' => 'L\'état de votre stock en un coup d\'œil',
                'body' => 'Nombre de références, unités disponibles, ruptures et stocks bas, valeur totale. C\'est la ligne à regarder en arrivant : elle vous dit s\'il faut réapprovisionner avant même de lire le tableau.',
            ],
            'list' => [
                'title' => 'Retrouver une référence',
                'body' => 'Recherchez par nom, référence ou code-barres, ou filtrez par catégorie et par niveau de stock. Un produit archivé disparaît des ventes sans être supprimé : son historique reste consultable.',
            ],
            'create' => [
                'title' => 'Créez votre première référence',
                'body' => 'Chaque produit vendu depuis votre stock a besoin d\'une fiche. C\'est elle qui porte le prix, le code-barres et les dimensions utilisés partout ailleurs.',
                'hint' => 'Cliquez sur « Nouveau produit » pour ouvrir le formulaire.',
            ],
            'identity' => [
                'title' => 'L\'identité du produit',
                'body' => 'Seul le nom est obligatoire : laissez la référence vide et nous la générons pour vous. Le code-barres, lui, est ce qui permet de scanner le produit à la préparation et à l\'inventaire — renseignez-le si vos articles en portent un.',
            ],
            'pricing' => [
                'title' => 'Prix de vente et prix d\'achat',
                'body' => 'Le prix de vente est repris automatiquement quand vous ajoutez le produit à une commande. Le prix d\'achat reste privé : il ne sert qu\'à calculer la marge affichée juste en dessous.',
            ],
            'logistics' => [
                'title' => 'Fragilité, poids et dimensions',
                'body' => 'Un produit signalé fragile transmet l\'alerte au livreur sur chaque commande qui le contient. Poids et dimensions sont facultatifs, mais ce sont eux qui permettent d\'estimer un colis avant de l\'expédier.',
            ],
            'media' => [
                'title' => 'La photo et l\'état du produit',
                'body' => 'Une photo rend la référence reconnaissable en un coup d\'œil dans les listes et à la préparation. L\'interrupteur en bas retire le produit de la vente sans toucher à son stock ni à son historique.',
            ],
            'submit' => [
                'title' => 'Enregistrez la fiche',
                'body' => 'Notez que la quantité en stock ne se saisit pas ici : elle est le résultat des mouvements. Un produit naît à zéro et se remplit par une réception au dépôt ou par un inventaire.',
                'hint' => 'Enregistrez le produit pour continuer.',
            ],
            'import' => [
                'title' => 'Importer tout un catalogue',
                'body' => 'Pour démarrer avec des dizaines de références, ne les saisissez pas une à une : téléchargez le modèle Excel, remplissez-le et importez-le. Les colonnes sont reconnues automatiquement et vous corrigez les erreurs avant de valider.',
            ],
            'done' => [
                'title' => 'Votre catalogue est lancé !',
                'body' => 'Prochaine étape : faire entrer la marchandise. Le guide « Envoyer du stock au dépôt » vous montre comment déclarer un envoi et suivre son arrivée.',
            ],
        ],

        'stock_shipment' => [
            'welcome' => [
                'title' => 'Envoyer du stock au dépôt',
                'body' => 'Vos produits sont stockés chez nous avant d\'être livrés. Un bon d\'envoi déclare ce que vous expédiez : c\'est le document que le collecteur comptera chez vous, puis le dépôt à l\'arrivée.',
            ],
            'create' => [
                'title' => 'Créez un bon d\'envoi',
                'body' => 'Cette page liste vos envois passés et en cours, avec ce qui a été déclaré, collecté et réellement reçu. Créons-en un nouveau.',
                'hint' => 'Cliquez sur « Nouvel envoi » pour ouvrir le formulaire.',
            ],
            'items' => [
                'title' => 'Ce que contient l\'envoi',
                'body' => 'Recherchez par nom, référence ou code-barres, puis indiquez la quantité expédiée. Les produits en rupture apparaissent aussi : envoyer du stock est justement ce qu\'on fait pour une étagère vide. La note par ligne sert à signaler un lot ou un défaut.',
            ],
            'shipping' => [
                'title' => 'Destination et date',
                'body' => 'Le dépôt de destination est celui où votre marchandise sera entreposée. Vous ne le choisissez qu\'au premier envoi : les suivants y sont rattachés automatiquement. Les notes d\'expédition sont lues par le collecteur.',
            ],
            'submit' => [
                'title' => 'Brouillon ou demande de collecte',
                'body' => 'Deux sorties : le brouillon reste modifiable, la demande de collecte fige les quantités parce que c\'est ce document que le collecteur comptera face à vous.',
                'hint' => 'Enregistrez l\'envoi pour continuer.',
            ],
            'actions' => [
                'title' => 'Faire avancer l\'envoi',
                'body' => 'Tant qu\'il est en brouillon, l\'envoi se modifie et « Demander la collecte » le met en file d\'attente. Un livreur passera compter les colis chez vous avant de les acheminer au dépôt.',
            ],
            'timeline' => [
                'title' => 'Suivre l\'arrivée',
                'body' => 'Chaque étape est horodatée et signée : collecte chez vous, acheminement, comptage au dépôt. Votre stock n\'est crédité qu\'à la validation finale, sur les quantités réellement comptées à l\'arrivée — pas sur celles déclarées au départ.',
            ],
            'done' => [
                'title' => 'Envoi enregistré !',
                'body' => 'Vous suivrez son avancement depuis la liste des envois. En cas d\'écart entre déclaré et reçu, la fiche vous dit exactement où il se situe.',
                'cta' => 'Voir mes envois',
            ],
        ],

        'stock_inventory' => [
            'welcome' => [
                'title' => 'Faire l\'inventaire',
                'body' => 'Un inventaire compare ce que le système croit avoir avec ce qu\'il y a réellement sur l\'étagère. Tout se fait sur une seule feuille : on compte, on justifie les écarts, on enregistre.',
            ],
            'summary' => [
                'title' => 'Le point de départ',
                'body' => 'Les références et les unités que le système enregistre aujourd\'hui, et la valeur que cela représente. C\'est le chiffre que votre comptage va confirmer ou corriger.',
            ],
            'filters' => [
                'title' => 'Comptez par zone',
                'body' => 'Un inventaire complet se fait rarement d\'un bloc. Filtrez sur une catégorie ou sur une recherche pour ne voir que l\'étagère devant vous : vos comptages déjà saisis sont conservés quand vous changez de filtre ou de page.',
            ],
            'sheet' => [
                'title' => 'La feuille de comptage',
                'body' => 'Trois colonnes : ce qui est enregistré, ce que vous comptez, l\'écart calculé en direct. Sur ordinateur, Entrée et les flèches descendent la colonne — la feuille entière se remplit sans lâcher le clavier ni toucher la souris.',
            ],
            'match_all' => [
                'title' => 'Tout est conforme ?',
                'body' => 'Ce bouton recopie le stock enregistré sur toutes les lignes encore vides. Pratique pour finir une zone où rien n\'a bougé : une ligne comptée sans écart est tracée, mais ne crée aucun mouvement de stock.',
            ],
            'reason' => [
                'title' => 'Un écart demande un motif',
                'body' => 'Saisissez un comptage différent du stock enregistré : la colonne « Motif » s\'ouvre et devient obligatoire. Casse, vol, erreur de saisie, retour non enregistré — c\'est ce motif qui rendra l\'écart lisible dans six mois.',
                'hint' => 'Saisissez une quantité différente du stock enregistré sur une ligne.',
            ],
            'save' => [
                'title' => 'Enregistrez le comptage',
                'body' => 'La barre reste sous la main tant qu\'il y a des lignes en attente. À l\'enregistrement, seuls les écarts corrigent le stock — et chaque ligne comptée est tracée sur la fiche produit avec votre nom, l\'heure, la machine utilisée et, si votre navigateur l\'autorise, votre position.',
            ],
            'done' => [
                'title' => 'Vous savez inventorier !',
                'body' => 'Retrouvez chaque comptage dans l\'onglet « Inventaires » de la fiche produit, et chaque correction dans l\'historique des mouvements. Rien n\'est modifiable après coup : c\'est ce qui donne sa valeur à la trace.',
                'cta' => 'Voir mon catalogue',
            ],
        ],

        'stores_manage' => [
            'welcome' => [
                'title' => 'Gérer vos boutiques',
                'body' => 'Une boutique regroupe ses propres commandes, factures et ramassages. Créons-en une, puis voyons comment basculer de l\'une à l\'autre.',
            ],
            'create' => [
                'title' => 'Créez une boutique',
                'body' => 'Vous pouvez gérer plusieurs boutiques depuis un seul compte — une par marque, par ville ou par activité.',
                'hint' => 'Cliquez sur « Créer une boutique » pour continuer.',
            ],
            'identity' => [
                'title' => 'L\'identité de la boutique',
                'body' => 'Le nom est celui que verront vos clients et l\'équipe logistique. La catégorie aide au tri quand vous en gérez plusieurs ; une boutique inactive n\'accepte plus de nouvelles commandes.',
            ],
            'branding' => [
                'title' => 'Le logo',
                'body' => 'Le logo apparaît dans le sélecteur de boutique et sur vos documents. Facultatif, mais c\'est ce qui rend le basculement lisible d\'un coup d\'œil.',
            ],
            'contact' => [
                'title' => 'Contact et adresse',
                'body' => 'Cette adresse est proposée par défaut comme point d\'enlèvement de vos ramassages : renseignez-la avec soin, elle vous fera gagner du temps à chaque demande.',
            ],
            'submit' => [
                'title' => 'Enregistrez',
                'body' => 'Votre boutique sera immédiatement disponible dans le sélecteur, en haut de l\'écran.',
                'hint' => 'Enregistrez la boutique pour continuer.',
            ],
            'switcher' => [
                'title' => 'Changer de boutique active',
                'body' => 'Voici le sélecteur : la boutique active détermine ce que vous voyez et ce que vous créez. Une commande saisie ici appartiendra à la boutique affichée — vérifiez-la avant de saisir.',
            ],
            'done' => [
                'title' => 'C\'est fait !',
                'body' => 'Votre boutique est créée et vous savez basculer entre elles. Prochaine étape utile : inviter votre équipe.',
            ],
        ],

        'team_member' => [
            'welcome' => [
                'title' => 'Ajouter un membre à votre équipe',
                'body' => 'Un sous-utilisateur travaille sur votre compte avec ses propres identifiants, et seulement sur ce que vous l\'autorisez à voir.',
            ],
            'roles' => [
                'title' => 'D\'abord, les rôles',
                'body' => 'Un rôle est un jeu de permissions que vous définissez une fois et réutilisez : « saisie des commandes », « suivi des livraisons ». Créez-le avant le membre, vous le lui attribuerez directement.',
            ],
            'create' => [
                'title' => 'Créez le membre',
                'body' => 'Chaque membre a son propre compte : les actions restent tracées à son nom, et vous pouvez le suspendre sans toucher aux autres.',
                'hint' => 'Cliquez sur « Ajouter un membre » pour continuer.',
            ],
            'identity' => [
                'title' => 'Son identité',
                'body' => 'L\'e-mail servira d\'identifiant de connexion : il doit être unique et lui appartenir. Le téléphone permet à l\'équipe logistique de le joindre.',
            ],
            'access' => [
                'title' => 'Ses accès',
                'body' => 'Choisissez les boutiques qu\'il pourra ouvrir et les rôles qui définissent ses droits. Au moins une boutique et un rôle sont requis — sans quoi il se connecterait sur un écran vide.',
            ],
            'security' => [
                'title' => 'Son mot de passe',
                'body' => 'Vous définissez le mot de passe initial et le lui transmettez. Il pourra le changer depuis son profil après sa première connexion.',
            ],
            'submit' => [
                'title' => 'Enregistrez',
                'body' => 'Le compte est actif immédiatement : votre collaborateur peut se connecter dès que vous lui donnez ses identifiants.',
                'hint' => 'Enregistrez le membre pour terminer le guide.',
            ],
            'done' => [
                'title' => 'Membre ajouté !',
                'body' => 'Vous pouvez modifier ses accès à tout moment, ou suspendre son compte depuis la liste de l\'équipe.',
            ],
        ],
    ],
];
