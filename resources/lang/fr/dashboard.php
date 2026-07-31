<?php

return [
    'greeting' => 'Bonjour, :name !',
    'subtitle' => 'Vue d\'ensemble logistique SpeedZone Express — données en direct de vos opérations.',
    'default_team' => 'Équipe opérations',

    'periods' => [
        'today' => 'Aujourd\'hui',
        'yesterday' => 'Hier',
        'last_7_days' => '7 derniers jours',
        'last_30_days' => '30 derniers jours',
        'this_month' => 'Ce mois-ci',
        'last_month' => 'Mois dernier',
        'custom' => 'Plage personnalisée',
    ],

    'select_date_range' => 'Sélectionner une plage de dates',
    'create_shipment' => 'Créer une expédition',
    'retry' => 'Réessayer',
    'view_all' => 'Tout voir',
    'view' => 'Voir',
    'view_orders' => 'Voir les commandes',
    'view_transfers' => 'Voir les transferts',

    'errors' => [
        'load_failed' => 'Impossible de charger le tableau de bord.',
    ],

    'empty' => [
        'chart' => 'Aucune donnée pour cette période.',
        'orders' => 'Aucune commande pour le moment.',
        'activity' => 'Aucune activité enregistrée.',
        'customers' => 'Aucune donnée client pour cette période.',
    ],

    'kpis' => [
        'orders_today' => 'Commandes aujourd\'hui',
        'orders_this_month' => 'Commandes ce mois-ci',
        'delivered_orders' => 'Commandes livrées',
        'pending_pickup' => 'En attente de ramassage',
        'in_transit' => 'En transit',
        'out_for_delivery' => 'En cours de livraison',
        'returned_orders' => 'Commandes retournées',
        'cancelled_orders' => 'Commandes annulées',
        'cash_to_collect' => 'Espèces à encaisser',
        'cod_collected' => 'COD encaissé',
        'delivery_success_rate' => 'Taux de réussite livraison',
        'average_delivery_time' => 'Délai moyen de livraison',
        'active_sellers' => 'Vendeurs actifs',
        'active_delivery_agents' => 'Livreurs actifs',
        'new_customers' => 'Nouveaux clients',
        'revenue_in_period' => 'Revenus (période)',
        'revenue_today' => 'Revenus aujourd\'hui',
        'revenue_this_month' => 'Revenus ce mois-ci',
        'average_order_value' => 'Panier moyen',
        'pending_transfers' => 'Transferts en attente',
        'orders_at_agency' => 'Colis en agence',
        'failed_deliveries' => 'Livraisons échouées',
        'orders_in_period' => 'Commandes sur la période',
    ],

    'charts' => [
        'orders_by_day' => 'Commandes par jour (30 derniers jours)',
        'orders_by_status' => 'Commandes par statut',
        'orders_by_status_summary' => 'Commandes par statut (résumé)',
        'orders_by_city' => 'Commandes par ville',
        'payment_methods' => 'Modes de paiement',
        'monthly_revenue' => 'Revenus mensuels (12 derniers mois)',
        'delivery_success_rate' => 'Taux de réussite livraison',
        'orders_per_seller' => 'Commandes par vendeur (Top 10)',
        'delivery_agents_performance' => 'Performance des livreurs',
        'delivered_failed' => ':delivered livrées · :failed échouées',
    ],

    'series' => [
        'orders' => 'Commandes',
        'revenue' => 'Revenus',
        'delivered' => 'Livrées',
        'success_rate' => 'Taux de réussite',
    ],

    'tables' => [
        'recent_orders' => 'Commandes récentes',
        'recent_activity' => 'Activité récente',
        'top_customers' => 'Meilleurs clients',
        'tracking' => 'Suivi',
        'customer' => 'Client',
        'seller' => 'Vendeur',
        'pickup' => 'Ramassage',
        'destination' => 'Destination',
        'status' => 'Statut',
        'payment' => 'Paiement',
        'amount' => 'Montant',
        'agent' => 'Livreur',
        'created' => 'Créé le',
        'phone' => 'Téléphone',
        'orders' => 'Commandes',
        'total_cod' => 'Total COD',
        'delivered' => 'Livrées',
        'pending' => 'En attente',
        'success' => 'Réussite',
        'avg_time' => 'Délai moy.',
    ],

    'status_buckets' => [
        'created' => 'Créé',
        'waiting_pickup' => 'En attente de ramassage',
        'picked_up' => 'Ramassé',
        'at_agency' => 'En agence',
        'in_transit' => 'En transit',
        'received' => 'Reçu',
        'out_for_delivery' => 'En cours de livraison',
        'delivered' => 'Livré',
        'not_delivered' => 'Non livré',
        'returned' => 'Retourné',
        'cancelled' => 'Annulé',
    ],

    'payment_methods_note' => 'Le virement bancaire n\'est pas un mode de paiement commande dans la base (seulement Espèces et Paiement par carte).',

    'limitations' => [
        'late_deliveries' => [
            'metric' => 'Livraisons en retard',
            'reason' => 'Aucun SLA ni date de livraison promise n\'est enregistré sur les commandes.',
        ],
        'payment_method_transfer' => [
            'metric' => 'Paiement par virement',
            'reason' => 'Les modes de paiement commande sont limités à ESPÈCES et PAIEMENT PAR CARTE.',
        ],
    ],

    'system' => 'Système',
    'unknown' => 'Inconnu',

    /*
     * Écran mobile. Le tableau de bord bureau empile 23 indicateurs et 8
     * graphiques : sur un téléphone cela devient un mur à faire défiler. La
     * version mobile ne garde que ce qui se lit d'un coup d'œil et ce sur quoi
     * on peut agir, d'où un vocabulaire qui lui est propre.
     */
    'mobile' => [
        'title' => 'Mon activité',
        'subtitle' => 'SpeedZone Express',
        'cash_headline' => 'Espèces à encaisser',
        'refresh' => 'Actualiser',
        'previous_period' => 'Période précédente',
        'next_period' => 'Période suivante',
        'currency' => 'MAD',

        'stats' => [
            'delivered' => 'Livrées',
            'in_transit' => 'En transit',
            'returns' => 'Retours',
            'collected' => 'COD encaissé',
            'success_caption' => ':rate% de réussite',
            'out_for_delivery_caption' => ':count en livraison',
            'failed_caption' => ':count échouées',
            'orders_caption' => 'sur :count commandes',
        ],

        'tasks' => [
            'title' => 'À traiter',
            'pending_pickup' => 'En attente de ramassage',
            'failed' => 'Livraisons échouées',
            'transfers' => 'Transferts en attente',
            'at_agency' => 'Colis en agence',
            'empty' => 'Rien à traiter, tout est à jour.',
            'open' => 'Ouvrir',
        ],

        'overview' => [
            'title' => 'Aperçu des commandes',
            'total' => 'Total',
            'delivered' => 'Livrées',
            'in_progress' => 'En cours',
            'on_track' => 'Taux de réussite de :rate%',
            'at_risk' => ':count livraisons à rattraper',
            'empty' => 'Aucune commande sur la période.',
        ],

        'trend' => [
            'title' => 'Activité quotidienne',
            'caption' => '30 derniers jours',
            'count' => ':count commandes',
            'on_day' => 'le :day',
            'previous_day' => ':count commandes le :day',
            'stable' => 'stable',
        ],

        'recent' => [
            'title' => 'Dernières commandes',
            'view_all' => 'Tout voir',
        ],

        'breakdown' => [
            'title' => 'Répartition',
            'by_status' => 'Statuts',
            'by_city' => 'Villes',
            'total' => 'Commandes',
            'all' => 'Toutes les catégories',
            'share' => ':count sur :total commandes',
        ],
    ],
];
