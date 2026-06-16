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
    ],

    'names' => [
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
