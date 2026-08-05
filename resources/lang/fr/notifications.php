<?php

return [
    'unknown_user' => 'Utilisateur inconnu',

    'titles' => [
        'invoice_generated' => 'Facture générée',
        'ticket_created' => 'Nouveau ticket support',
        'ticket_message' => 'Nouveau message ticket',
        'ticket_closed' => 'Ticket fermé',
        'ticket_assigned' => 'Ticket assigné',
        'return_requested' => 'Demande de retour',
        'stock_pickup_requested' => 'Stock à ramasser',
        'new_seller_registration' => 'Nouvelle inscription vendeur',
    ],

    'messages' => [
        'invoice_generated' => 'Votre facture :reference a été générée.',
        'ticket_created' => 'Nouveau ticket support :reference créé par :seller.',
        'ticket_created_with_subject' => 'Nouveau ticket support :reference — :subject.',
        'ticket_message' => 'Nouveau message sur le ticket :reference.',
        'ticket_closed' => 'Votre ticket support :reference a été fermé.',
        'ticket_assigned' => 'Le ticket :reference vous a été assigné.',
        'return_requested' => 'Une nouvelle demande de retour a été créée.',
        'stock_pickup_requested' => ':shop a du stock prêt à être ramassé à :city.',
        'new_seller_registration' => 'Une nouvelle inscription vendeur nécessite une approbation.',
    ],

    'types' => [
        'invoice_generated' => 'Factures générées',
        'ticket_created' => 'Nouveaux tickets',
        'ticket_message' => 'Messages de tickets',
        'ticket_closed' => 'Tickets fermés',
        'return_requested' => 'Demandes de retour',
        'stock_pickup_requested' => 'Stock à ramasser chez un vendeur',
        'system_notifications' => 'Notifications système',
    ],

    'settings' => [
        'title' => 'Paramètres de notification',
        'description' => 'Choisissez les notifications que vous souhaitez recevoir.',
        'master_toggle' => 'Activer les notifications',
        'master_toggle_help' => 'Désactiver pour couper toutes les notifications.',
        'saved' => 'Préférences de notification enregistrées.',
    ],

    'center' => [
        'mark_all_read' => 'Tout marquer comme lu',
        'no_notifications' => 'Aucune notification',
        'view_all' => 'Voir toutes les notifications',
    ],

    'icons' => [
        'invoice_generated' => 'bx-receipt',
        'ticket_created' => 'bx-support',
        'ticket_message' => 'bx-message-dots',
        'ticket_closed' => 'bx-check-circle',
        'return_requested' => 'bx-undo',
        'stock_pickup_requested' => 'bx-package',
        'system_notifications' => 'bx-cog',
    ],
];
