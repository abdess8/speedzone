<?php

return [
    'resources' => [
        'support' => 'Tickets de support',
        'orders' => 'Commandes',
        'pickup_requests' => 'Ramassages',
        'invoices' => 'Factures vendeurs',
        'driver_invoices' => 'Factures livreurs',
        'returns' => 'Retours',
        'transfers' => 'Transferts',
        'partners' => 'Intégrations partenaires',
    ],

    'names' => [
        'partners.create' => 'Créer des partenaires',
        'partners.read' => 'Voir les partenaires',
        'partners.update' => 'Modifier les partenaires',
        'partners.delete' => 'Supprimer les partenaires',
        'partners.sync' => 'Forcer la synchronisation',
        'partners.deliveries.manage' => 'Gérer les livraisons partenaires',

        'support.create' => 'Créer des tickets',
        'support.read.own' => 'Voir ses propres tickets',
        'support.read.all' => 'Voir tous les tickets',
        'support.reply' => 'Répondre aux tickets',
        'support.assign' => 'Assigner des tickets',
        'support.update_status' => 'Modifier le statut',
        'support.close' => 'Clôturer des tickets',
        'support.manage' => 'Gérer le support (accès complet)',
    ],

    'descriptions' => [
        'partners.create' => 'Enregistrer un nouveau partenaire B2B et ses identifiants API.',
        'partners.read' => 'Consulter les configurations partenaires, correspondances et journaux API.',
        'partners.update' => 'Modifier les paramètres, identifiants, villes et correspondances du partenaire.',
        'partners.delete' => 'Supprimer une intégration partenaire.',
        'partners.sync' => 'Lancer une ingestion « synchroniser maintenant » pour un partenaire.',
        'partners.deliveries.manage' => 'Mettre à jour et scanner en masse les livraisons des partenaires assignés.',
        'support.create' => 'Permet aux vendeurs d\'ouvrir des tickets liés à leurs commandes, factures ou ramassages.',
        'support.read.own' => 'Consulter uniquement les tickets créés par l\'utilisateur connecté.',
        'support.read.all' => 'Consulter tous les tickets dans le centre de support.',
        'support.reply' => 'Envoyer des messages sur les tickets accessibles.',
        'support.assign' => 'Assigner ou réassigner des tickets aux agents support.',
        'support.update_status' => 'Changer le statut (Ouvert, En cours, En attente vendeur, Résolu, Clôturé).',
        'support.close' => 'Clôturer un ticket. Les vendeurs peuvent clôturer leurs tickets ; le staff peut clôturer n\'importe quel ticket.',
        'support.manage' => 'Accès complet au support : voir tous les tickets, assigner, changer le statut, répondre et clôturer. Destiné aux agents support.',
    ],
];
