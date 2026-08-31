<?php

/**
 * Libellés lisibles des permissions, pour l'écran « Rôles et permissions ».
 *
 * Ce groupe n'est volontairement pas exposé au bundle de traduction du
 * frontend : il pèse plusieurs dizaines de kilo-octets et n'est lu que par
 * PermissionLabels, côté serveur, au moment de construire l'écran des rôles.
 *
 * 'names'        : ce que la permission autorise, en une ligne.
 * 'descriptions' : le texte d'aide affiché sous l'icône (i).
 */
return [
    'resources' => [
        'dashboard' => 'Tableau de bord',
        'orders' => 'Commandes',
        'pickup_requests' => 'Ramassages',
        'transfers' => 'Transferts inter-villes',
        'returns' => 'Retours',
        'invoices' => 'Factures vendeurs',
        'driver_invoices' => 'Décomptes livreurs',
        'stock' => 'Stock & catalogue',
        'support' => 'Tickets de support',
        'stores' => 'Boutiques',
        'team' => 'Équipe du vendeur',
        'team_roles' => 'Rôles de l\'équipe vendeur',
        'cities' => 'Villes',
        'sectors' => 'Secteurs de livraison',
        'driver_zones' => 'Secteurs des livreurs',
        'alerts' => 'Annonces',
        'notifications' => 'Notifications',
        'partners' => 'Intégrations partenaires',
        'integrations' => 'Boutiques e-commerce',
        'users' => 'Utilisateurs',
        'roles' => 'Rôles',
        'permissions' => 'Catalogue des permissions',
    ],

    /**
     * Périmètre d'une permission, affiché en pastille à côté du libellé. Les
     * portées inconnues — les permissions de notification rangent le sujet de
     * la notification dans ce champ — n'ont pas de pastille : le sujet est
     * déjà dans le libellé.
     */
    'scopes' => [
        'own' => 'ses données',
        'all' => 'tout le monde',
        'assigned' => 'ce qui lui est affecté',
    ],

    /**
     * Gabarits pour les permissions de changement de statut, trop nombreuses
     * pour être écrites une par une : le nom des statuts vient des mêmes
     * énumérations que les écrans de suivi, donc le vocabulaire reste le même.
     */
    'transitions' => [
        'workflow' => [
            'orders' => [
                'label' => 'Passer au statut « :status »',
                'description' => 'Autorise à faire passer une commande au statut « :status », à l\'unité comme en masse.',
            ],
            'returns' => [
                'label' => 'Passer au statut « :status »',
                'description' => 'Autorise à faire passer un retour au statut « :status », à l\'unité comme en masse.',
            ],
        ],
        'pair' => [
            'orders' => [
                'label' => ':from → :to',
                'description' => 'Autorise, dans l\'écran de changement de statut en masse, le passage d\'une commande de « :from » à « :to ».',
            ],
            'returns' => [
                'label' => ':from → :to',
                'description' => 'Autorise, dans l\'écran de changement de statut en masse, le passage d\'un retour de « :from » à « :to ».',
            ],
        ],
    ],

    'names' => [
        // Tableau de bord
        'dashboard.view' => 'Accéder au tableau de bord',
        'dashboard.view_financials' => 'Voir les montants et le chiffre d\'affaires',
        'dashboard.view_operations' => 'Voir l\'état des commandes et les tâches',
        'dashboard.view_performance' => 'Voir les taux de réussite et les délais',
        'dashboard.view_customers' => 'Voir les meilleurs clients',
        'dashboard.view_network' => 'Voir les vendeurs et livreurs actifs',

        // Commandes
        'orders.create' => 'Créer une commande',
        'orders.create_with_stock' => 'Créer une commande depuis le stock',
        'orders.read.own' => 'Voir les commandes de ses boutiques',
        'orders.read.assigned' => 'Voir les commandes qui lui sont affectées',
        'orders.read.all' => 'Voir toutes les commandes',
        'orders.update.own' => 'Modifier les commandes de ses boutiques',
        'orders.update.assigned' => 'Faire avancer les commandes qui lui sont affectées',
        'orders.update.all' => 'Modifier toutes les commandes',
        'orders.delete.own' => 'Supprimer les commandes de ses boutiques',
        'orders.delete.all' => 'Supprimer n\'importe quelle commande',
        'orders.export' => 'Exporter les commandes en Excel',
        'orders.print' => 'Imprimer les étiquettes d\'expédition',

        // Ramassages
        'pickup_requests.create' => 'Demander un ramassage',
        'pickup_requests.read.own' => 'Voir ses demandes de ramassage',
        'pickup_requests.read.all' => 'Voir tous les ramassages',
        'pickup_requests.read.assigned' => 'Voir les ramassages qui lui sont affectés',
        'pickup_requests.assign' => 'Affecter un ramassage à un livreur',
        'pickup_requests.change_status' => 'Changer le statut d\'un ramassage',
        'pickup_requests.pickup' => 'Confirmer le ramassage sur le terrain',

        // Transferts
        'transfers.create' => 'Créer un transfert',
        'transfers.read' => 'Consulter les transferts',
        'transfers.read.assigned' => 'Voir les transferts qui lui sont confiés',
        'transfers.update' => 'Modifier un transfert',
        'transfers.dispatch' => 'Faire partir un transfert',
        'transfers.receive' => 'Réceptionner un transfert',

        // Retours
        'returns.create_request' => 'Demander le retour d\'un colis',
        'returns.create' => 'Ouvrir un retour sur le terrain',
        'returns.read.own' => 'Voir les retours de ses boutiques',
        'returns.read.all' => 'Voir tous les retours',
        'returns.manage' => 'Gérer les retours (accès complet)',
        'returns.update_status' => 'Changer le statut d\'un retour',
        'returns.edit_customer_data' => 'Corriger l\'adresse de renvoi',

        // Factures vendeurs
        'invoices.read.own' => 'Voir ses propres factures',
        'invoices.read.all' => 'Voir les factures de tous les vendeurs',
        'invoices.generate' => 'Générer les factures vendeurs',
        'invoices.pay' => 'Marquer une facture comme payée',
        'invoices.cancel' => 'Annuler une facture',
        'invoices.delete' => 'Supprimer une facture annulée',
        'invoices.print' => 'Imprimer une facture',

        // Décomptes livreurs
        'driver_invoices.read.own' => 'Voir ses propres décomptes',
        'driver_invoices.read.all' => 'Voir les décomptes de tous les livreurs',
        'driver_invoices.generate' => 'Générer les décomptes livreurs',
        'driver_invoices.pay' => 'Marquer un décompte comme payé',
        'driver_invoices.cancel' => 'Annuler un décompte',
        'driver_invoices.delete' => 'Supprimer un décompte annulé',
        'driver_invoices.print' => 'Imprimer un décompte',
        'driver_invoices.adjust' => 'Saisir une prime ou une retenue',
        'driver_invoices.assign_driver' => 'Affecter un livreur aux commandes',

        // Stock & catalogue
        'stock.view' => 'Consulter le catalogue et les stocks',
        'stock.create_product' => 'Ajouter et modifier des produits',
        'stock.import_products' => 'Importer des produits en masse (Excel/CSV)',
        'stock.create_inbound' => 'Créer des envois de stock',
        'stock.adjust' => 'Réaliser des inventaires et corriger les stocks',
        'stock.collect_inbound' => 'Ramasser le stock chez le vendeur',
        'stock.receive_inbound' => 'Réceptionner le stock au dépôt',
        'stock.admin_override' => 'Auditer et bloquer les stocks (tous vendeurs)',

        // Support
        'support.create' => 'Ouvrir un ticket',
        'support.read.own' => 'Voir ses propres tickets',
        'support.read.all' => 'Voir tous les tickets',
        'support.reply' => 'Répondre aux tickets',
        'support.assign' => 'Assigner un ticket à un agent',
        'support.update_status' => 'Changer le statut d\'un ticket',
        'support.close' => 'Clôturer un ticket',
        'support.manage' => 'Gérer le support (accès complet)',

        // Boutiques
        'stores.read' => 'Consulter les boutiques',
        'stores.create' => 'Créer une boutique',
        'stores.update' => 'Modifier une boutique',
        'stores.delete' => 'Supprimer une boutique',

        // Équipe du vendeur
        'team.read' => 'Consulter l\'équipe',
        'team.create' => 'Ajouter un collaborateur',
        'team.update' => 'Modifier un collaborateur',
        'team.suspend' => 'Suspendre un collaborateur',
        'team_roles.manage' => 'Gérer les rôles de l\'équipe',

        // Villes
        'cities.read' => 'Consulter les villes',
        'cities.create' => 'Ouvrir une ville à la livraison',
        'cities.update' => 'Modifier une ville',
        'cities.delete' => 'Supprimer une ville',

        // Secteurs
        'sectors.read' => 'Consulter les secteurs',
        'sectors.create' => 'Créer un secteur',
        'sectors.update' => 'Modifier un secteur',
        'sectors.delete' => 'Supprimer un secteur',
        'sectors.read_driver_price' => 'Voir le montant payé au livreur',

        // Secteurs des livreurs
        'driver_zones.read' => 'Voir la couverture des livreurs',
        'driver_zones.assign' => 'Rattacher un livreur à un secteur',
        'driver_zones.remove' => 'Retirer un livreur d\'un secteur',

        // Annonces
        'alerts.read' => 'Consulter les annonces',
        'alerts.create' => 'Publier une annonce',
        'alerts.update' => 'Modifier une annonce',
        'alerts.delete' => 'Supprimer une annonce',

        // Notifications
        'notifications.invoice_generated' => 'Être averti des factures émises',
        'notifications.ticket_created' => 'Être averti des nouveaux tickets',
        'notifications.ticket_message' => 'Être averti des réponses aux tickets',
        'notifications.ticket_closed' => 'Être averti de la clôture des tickets',
        'notifications.return_requested' => 'Être averti des demandes de retour',
        'notifications.stock_pickup_requested' => 'Être averti des stocks à ramasser',
        'notifications.seller_registered' => 'Être averti des inscriptions vendeurs',
        'notifications.system_notifications' => 'Être averti des messages de service',

        // Partenaires
        'partners.create' => 'Créer un partenaire',
        'partners.read' => 'Consulter les partenaires',
        'partners.update' => 'Modifier un partenaire',
        'partners.delete' => 'Supprimer un partenaire',
        'partners.sync' => 'Forcer la synchronisation',
        'partners.deliveries.manage' => 'Gérer les livraisons partenaires',

        // Boutiques e-commerce
        'integrations.read' => 'Voir les boutiques e-commerce reliées',
        'integrations.manage' => 'Connecter et configurer une boutique e-commerce',

        // Utilisateurs
        'users.read' => 'Consulter les utilisateurs',
        'users.create' => 'Créer un utilisateur',
        'users.update' => 'Modifier un utilisateur',
        'users.delete' => 'Supprimer un utilisateur',
        'users.roles.assign' => 'Attribuer un rôle à un utilisateur',

        // Rôles
        'roles.read' => 'Consulter les rôles',
        'roles.create' => 'Créer un rôle',
        'roles.update' => 'Modifier les permissions d\'un rôle',
        'roles.delete' => 'Supprimer un rôle',

        // Catalogue des permissions
        'permissions.read' => 'Lire le catalogue des permissions (API)',
        'permissions.create' => 'Ajouter une permission au catalogue (API)',
        'permissions.update' => 'Modifier une permission du catalogue (API)',
        'permissions.delete' => 'Supprimer une permission du catalogue (API)',
    ],

    'descriptions' => [
        // Tableau de bord
        'dashboard.view' => 'Ouvrir le tableau de bord. Les chiffres restent limités à la boutique active et aux commandes que l\'utilisateur a le droit de lire.',
        'dashboard.view_financials' => 'Voir les espèces à encaisser, les encaissements, le chiffre d\'affaires et le panier moyen. À retirer d\'un rôle qui prépare les colis sans avoir à connaître les montants de la boutique.',
        'dashboard.view_operations' => 'Voir la répartition des commandes par statut et par ville, les transferts en attente et les tâches à traiter.',
        'dashboard.view_performance' => 'Voir le taux de livraison réussie, les délais moyens et le classement des livreurs.',
        'dashboard.view_customers' => 'Voir les meilleurs clients et le nombre de nouveaux clients de la période.',
        'dashboard.view_network' => 'Voir le volume par vendeur ainsi que le nombre de vendeurs et de livreurs actifs.',

        // Commandes
        'orders.create' => 'Enregistrer une commande en saisissant le client, l\'adresse de livraison et le montant à encaisser.',
        'orders.create_with_stock' => 'Composer une commande à partir des produits du catalogue : le stock est décrémenté et le montant calculé automatiquement.',
        'orders.read.own' => 'Consulter les commandes des boutiques auxquelles l\'utilisateur est rattaché, et rien au-delà.',
        'orders.read.assigned' => 'Consulter uniquement les commandes affectées au livreur connecté.',
        'orders.read.all' => 'Consulter les commandes de tous les vendeurs, sans restriction de boutique. Permission d\'exploitation.',
        'orders.update.own' => 'Corriger le contenu d\'une commande de ses boutiques tant qu\'elle n\'est pas partie : passé le statut « Créée », elle n\'est plus modifiable par le vendeur.',
        'orders.update.assigned' => 'Déclarer l\'issue d\'une livraison sur les commandes affectées au livreur. Ne permet pas de modifier le contenu de la commande.',
        'orders.update.all' => 'Modifier n\'importe quelle commande à n\'importe quelle étape, y compris l\'adresse, les montants et le livreur affecté.',
        'orders.delete.own' => 'Supprimer une commande appartenant à ses propres boutiques.',
        'orders.delete.all' => 'Supprimer une commande quel qu\'en soit le vendeur. Permission sensible : la suppression est définitive.',
        'orders.export' => 'Télécharger la liste au format Excel (.xlsx) : suivi, statut, motif d\'échec, client, montants, livreur et vendeur. L\'export reprend exactement les filtres actifs à l\'écran.',
        'orders.print' => 'Générer les étiquettes d\'expédition en PDF, à l\'unité ou en lot, pour les coller sur les colis.',

        // Ramassages
        'pickup_requests.create' => 'Demander le passage d\'un livreur à la boutique pour récupérer les colis prêts à partir.',
        'pickup_requests.read.own' => 'Consulter uniquement les ramassages demandés par ses propres boutiques.',
        'pickup_requests.read.all' => 'Consulter les demandes de ramassage de tous les vendeurs.',
        'pickup_requests.read.assigned' => 'Consulter uniquement les ramassages confiés au livreur connecté.',
        'pickup_requests.assign' => 'Désigner le livreur qui passera récupérer les colis chez le vendeur.',
        'pickup_requests.change_status' => 'Faire avancer une demande depuis le back-office : en attente, ramassée, au dépôt ou annulée.',
        'pickup_requests.pickup' => 'Action du livreur chez le vendeur : scanner les colis et déclarer le ramassage effectué.',

        // Transferts
        'transfers.create' => 'Constituer un bordereau de colis et de retours à acheminer d\'une ville à une autre.',
        'transfers.read' => 'Voir les bordereaux inter-villes, leur contenu et leur avancement.',
        'transfers.read.assigned' => 'Consulter uniquement les bordereaux dont l\'utilisateur est le convoyeur.',
        'transfers.update' => 'Ajouter ou retirer des colis, changer le convoyeur ou annuler un bordereau, tant qu\'il n\'est pas parti.',
        'transfers.dispatch' => 'Déclarer le départ du bordereau : les colis passent en transit vers la ville de destination.',
        'transfers.receive' => 'Enregistrer l\'arrivée du bordereau à destination : les colis deviennent disponibles pour la livraison locale.',

        // Retours
        'returns.create_request' => 'Côté vendeur : demander le renvoi d\'un colis encore en circulation ou déjà livré.',
        'returns.create' => 'Côté livreur : basculer en retour un colis qu\'il n\'a pas pu remettre au client.',
        'returns.read.own' => 'Consulter uniquement les retours des boutiques auxquelles l\'utilisateur est rattaché.',
        'returns.read.all' => 'Consulter les retours de tous les vendeurs.',
        'returns.manage' => 'Accès complet aux retours : les créer, les faire avancer et les remettre au vendeur. Destiné à l\'exploitation.',
        'returns.update_status' => 'Faire avancer un retour dans son parcours sans disposer de l\'accès complet à la gestion des retours.',
        'returns.edit_customer_data' => 'Modifier le nom, le téléphone, l\'adresse ou la ville où le colis doit être rendu, tant que le retour n\'est pas clôturé.',

        // Factures vendeurs
        'invoices.read.own' => 'Consulter les factures de ses propres boutiques et le détail des colis facturés.',
        'invoices.read.all' => 'Consulter les factures de tous les vendeurs.',
        'invoices.generate' => 'Établir le décompte d\'un vendeur sur une période : une facture par boutique, reprenant les colis livrés et retournés non encore facturés.',
        'invoices.pay' => 'Enregistrer le versement au vendeur et joindre le justificatif de virement.',
        'invoices.cancel' => 'Annuler une facture émise. Les commandes qu\'elle contenait redeviennent facturables sur la période suivante.',
        'invoices.delete' => 'Effacer définitivement une facture déjà annulée. Sans effet sur une facture en cours.',
        'invoices.print' => 'Télécharger la facture vendeur au format PDF.',

        // Décomptes livreurs
        'driver_invoices.read.own' => 'Côté livreur : consulter ses propres relevés de rémunération et le détail des courses qui les composent.',
        'driver_invoices.read.all' => 'Consulter les relevés de rémunération de tous les livreurs. Donne accès aux montants versés à chacun.',
        'driver_invoices.generate' => 'Établir le relevé de rémunération d\'un livreur sur une période, à partir des livraisons effectuées et des primes enregistrées.',
        'driver_invoices.pay' => 'Enregistrer le paiement du livreur et joindre le justificatif.',
        'driver_invoices.cancel' => 'Annuler un relevé émis. Les lignes qu\'il contenait redeviennent disponibles pour un prochain décompte.',
        'driver_invoices.delete' => 'Effacer définitivement un décompte déjà annulé.',
        'driver_invoices.print' => 'Télécharger le relevé de rémunération du livreur au format PDF.',
        'driver_invoices.adjust' => 'Ajouter manuellement une prime, une pénalité ou une régularisation sur le compte d\'un livreur, en dehors de tout décompte.',
        'driver_invoices.assign_driver' => 'Désigner le livreur qui prend en charge une commande, à l\'unité ou en masse depuis le dispatch par secteur et les commandes partenaires. Malgré son nom technique, cette permission concerne le dispatch et non la facturation.',

        // Stock & catalogue
        'stock.view' => 'Consulter le catalogue produits, les niveaux de stock et les bordereaux de réception de sa boutique.',
        'stock.create_product' => 'Créer, modifier et archiver des fiches produits une par une.',
        'stock.import_products' => 'Créer des fiches produits en masse depuis un fichier Excel/CSV. Distincte de la création à l\'unité : un import remplace tout un catalogue en une opération.',
        'stock.create_inbound' => 'Préparer un bordereau et déclarer un envoi de stock vers notre dépôt.',
        'stock.adjust' => 'Corriger les quantités en stock lors d\'un inventaire. Chaque écart exige un motif et reste tracé dans un audit non modifiable.',
        'stock.collect_inbound' => 'Se déplacer chez les vendeurs de ses villes, compter devant eux le stock chargé et l\'expédier vers le dépôt. Ce comptage devient la référence pour la suite du trajet, mais ne crédite aucun stock. Permission côté hub : elle n\'est pas délégable à une équipe vendeur, car l\'intérêt du comptage est qu\'il soit fait par quelqu\'un d\'autre que le vendeur.',
        'stock.receive_inbound' => 'Compter physiquement le stock arrivant au dépôt et créditer les quantités réellement reçues. Limitée aux envois adressés au dépôt de ses villes. Permission côté hub : elle n\'est pas délégable à une équipe vendeur.',
        'stock.admin_override' => 'Auditer tous les mouvements de stock, toutes boutiques confondues, et bloquer un produit défectueux. Permission sensible réservée à l\'administration.',

        // Support
        'support.create' => 'Ouvrir un ticket lié à une commande, une facture ou un ramassage.',
        'support.read.own' => 'Consulter uniquement les tickets créés par l\'utilisateur connecté.',
        'support.read.all' => 'Consulter tous les tickets dans le centre de support.',
        'support.reply' => 'Envoyer des messages sur les tickets accessibles.',
        'support.assign' => 'Assigner ou réassigner un ticket à un agent du support.',
        'support.update_status' => 'Changer le statut d\'un ticket : ouvert, en cours, en attente vendeur, résolu ou clôturé.',
        'support.close' => 'Clôturer un ticket. Les vendeurs peuvent clôturer les leurs ; le staff peut clôturer n\'importe quel ticket.',
        'support.manage' => 'Accès complet au support : voir tous les tickets, assigner, changer le statut, répondre et clôturer. Destiné aux agents support.',

        // Boutiques
        'stores.read' => 'Voir les boutiques du compte, leurs coordonnées et leur ville de rattachement.',
        'stores.create' => 'Ouvrir une boutique supplémentaire sur le compte vendeur. Chaque boutique cloisonne ses commandes, son stock et sa facturation.',
        'stores.update' => 'Changer le nom, l\'adresse ou la ville d\'une boutique existante.',
        'stores.delete' => 'Fermer une boutique du compte. Réservé au titulaire du compte : un rôle d\'équipe ne peut jamais recevoir cette permission.',

        // Équipe du vendeur
        'team.read' => 'Voir les collaborateurs rattachés au compte vendeur, leur rôle et les boutiques auxquelles ils ont accès.',
        'team.create' => 'Créer un accès pour un membre de l\'équipe et lui attribuer un rôle ainsi que ses boutiques.',
        'team.update' => 'Changer le rôle, les boutiques ou les informations d\'un membre de l\'équipe.',
        'team.suspend' => 'Couper l\'accès d\'un membre de l\'équipe. Ses sessions ouvertes sont fermées immédiatement et la connexion lui est refusée.',
        'team_roles.manage' => 'Créer des rôles sur mesure pour l\'équipe (préparateur, service client…) et cocher leurs permissions. Un rôle d\'équipe ne peut jamais dépasser les droits du compte vendeur lui-même, ni s\'octroyer la gestion des boutiques ou de l\'équipe.',

        // Villes
        'cities.read' => 'Voir les villes desservies, leur code, leur région et les dépôts qui s\'y trouvent.',
        'cities.create' => 'Ouvrir une nouvelle ville à la livraison. Elle devient sélectionnable dans les commandes, les boutiques et les transferts.',
        'cities.update' => 'Renommer une ville, changer sa région ou la désactiver. Une ville désactivée disparaît des listes déroulantes sans toucher aux commandes existantes.',
        'cities.delete' => 'Retirer une ville du réseau. L\'opération est refusée tant que la ville porte encore des secteurs actifs.',

        // Secteurs
        'sectors.read' => 'Voir les circuits de livraison de chaque ville, leurs tarifs vendeur et leurs délais annoncés.',
        'sectors.create' => 'Découper une ville en un nouveau circuit et fixer son tarif de livraison, son tarif de retour et son délai.',
        'sectors.update' => 'Changer le périmètre, les tarifs ou le délai annoncé d\'un circuit existant.',
        'sectors.delete' => 'Retirer un circuit du réseau de livraison.',
        'sectors.read_driver_price' => 'Afficher la rémunération versée au livreur pour une livraison dans le secteur. Information confidentielle : sans cette permission, le montant n\'apparaît ni à l\'écran, ni dans l\'API, ni dans les formulaires, et une valeur envoyée malgré tout est ignorée.',

        // Secteurs des livreurs
        'driver_zones.read' => 'Voir quels circuits chaque livreur couvre.',
        'driver_zones.assign' => 'Ajouter un circuit à la tournée d\'un livreur. Il devient alors candidat à l\'affectation automatique et au dispatch par secteur de ce circuit.',
        'driver_zones.remove' => 'Détacher un circuit d\'un livreur : il cesse d\'être proposé pour les commandes de ce secteur.',

        // Annonces
        'alerts.read' => 'Ouvrir la liste des annonces diffusées sur la plateforme, avec leur audience et leur date de fin.',
        'alerts.create' => 'Diffuser un message en bandeau en haut des pages ou en fenêtre à la connexion, ciblé par rôle, par ville ou sur des personnes nommées.',
        'alerts.update' => 'Changer le texte, l\'audience ou la date de fin d\'une annonce déjà publiée.',
        'alerts.delete' => 'Retirer définitivement une annonce ; les destinataires cessent aussitôt de la voir.',

        // Notifications
        'notifications.invoice_generated' => 'Recevoir une notification à chaque émission d\'une facture vendeur. Utile au vendeur et à la comptabilité, sans objet pour un livreur.',
        'notifications.ticket_created' => 'Recevoir une notification à l\'ouverture d\'un ticket de support.',
        'notifications.ticket_message' => 'Recevoir une notification à chaque nouveau message sur un ticket suivi.',
        'notifications.ticket_closed' => 'Recevoir une notification lorsqu\'un ticket est clôturé.',
        'notifications.return_requested' => 'Recevoir une notification lorsqu\'un vendeur demande le renvoi d\'un colis. Destiné à l\'exploitation.',
        'notifications.stock_pickup_requested' => 'Recevoir une notification lorsqu\'un vendeur déclare un stock prêt à être collecté. Destiné aux collecteurs de la ville concernée.',
        'notifications.seller_registered' => 'Recevoir une notification à chaque création de compte vendeur en attente de validation. À réserver au bureau qui approuve les inscriptions.',
        'notifications.system_notifications' => 'Recevoir les messages de service qui n\'entrent dans aucune autre catégorie, comme l\'assignation d\'un ticket ou une information de la plateforme.',

        // Partenaires
        'partners.create' => 'Enregistrer un nouveau partenaire B2B et ses identifiants API.',
        'partners.read' => 'Consulter les configurations partenaires, les correspondances de villes et les journaux d\'appels API.',
        'partners.update' => 'Modifier les paramètres, identifiants, villes et correspondances d\'un partenaire.',
        'partners.delete' => 'Supprimer une intégration partenaire.',
        'partners.sync' => 'Lancer une ingestion « synchroniser maintenant » pour récupérer immédiatement les livraisons d\'un partenaire.',
        'partners.deliveries.manage' => 'Traiter la file des colis partenaires : scanner, changer les statuts en masse et affecter les livreurs.',

        // Boutiques e-commerce
        'integrations.read' => 'Consulter les boutiques e-commerce reliées au compte et leur état de synchronisation.',
        'integrations.manage' => 'Relier, reconfigurer ou débrancher une boutique Shopify, YouCan, WooCommerce ou PrestaShop. Donne accès aux clés d\'API de la boutique.',

        // Utilisateurs
        'users.read' => 'Accéder à la liste et aux fiches des comptes de la plateforme, y compris les inscriptions en attente.',
        'users.create' => 'Créer des comptes utilisateurs : vendeurs, livreurs ou personnel interne.',
        'users.update' => 'Modifier les informations et l\'état d\'un compte utilisateur.',
        'users.delete' => 'Supprimer un compte utilisateur.',
        'users.roles.assign' => 'Attribuer ou retirer un rôle à un utilisateur. Permission sensible : elle permet une élévation de privilèges.',

        // Rôles
        'roles.read' => 'Voir les rôles de la plateforme et les permissions attribuées à chacun.',
        'roles.create' => 'Définir un nouveau rôle et sélectionner les permissions qu\'il porte.',
        'roles.update' => 'Ajouter ou retirer des permissions à un rôle existant. La modification s\'applique immédiatement à tous ses titulaires. Permission sensible.',
        'roles.delete' => 'Retirer un rôle de la plateforme.',

        // Catalogue des permissions
        'permissions.read' => 'Lire la liste technique des permissions par l\'API. L\'attribution courante se fait depuis l\'écran des rôles, pas ici.',
        'permissions.create' => 'Créer une entrée dans le catalogue des permissions par l\'API. Opération technique, sans écran dédié : le catalogue est normalement alimenté par les migrations.',
        'permissions.update' => 'Modifier une entrée du catalogue des permissions par l\'API. Opération technique, sans écran dédié.',
        'permissions.delete' => 'Supprimer une entrée du catalogue par l\'API. Retirer une permission la révoque pour tous les rôles qui la portaient.',
    ],
];
