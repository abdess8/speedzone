<?php

return [
    'title' => 'Assistant OWL Delivery',
    'open' => 'Ouvrir l\'assistant',
    'close' => 'Fermer l\'assistant',
    'reset' => 'Effacer la conversation',
    'ready' => 'Prêt',
    'thinking' => 'L\'IA réfléchit…',
    'placeholder' => 'Posez une question ou demandez une action…',
    'greeting' => 'Bonjour :name,',
    'intro' => 'Je peux faire évoluer le statut d\'une commande, générer une facture, retrouver une commande ou un livreur, et lire vos KPI de livraison.',
    'currency' => 'MAD',
    'empty_reply' => 'C\'est fait.',

    'suggestions' => [
        'kpi' => 'Quel est le taux de réussite des livraisons cette semaine ?',
        'search' => 'Trouve les commandes pour Casablanca',
        'invoice' => 'Génère la facture de la dernière commande livrée',
    ],

    'actions' => [
        'status_changed' => 'Statut mis à jour',
        'invoice_ready' => 'Facture prête',
        'statement_ready' => 'Relevé prêt',
        'download' => 'Télécharger',
        'results' => '{count} résultat trouvé|{count} résultats trouvés',
        'kpis' => 'KPI de livraison',
    ],

    'search' => [
        'orders' => 'Commandes',
        'drivers' => 'Livreurs',
        'sellers' => 'Vendeurs',
        'customers' => 'Clients',
        'orders_count' => '{count} commande|{count} commandes',
    ],

    'kpis' => [
        'delivery_success_rate' => 'Taux de réussite',
        'delivered_orders' => 'Livrées',
        'failed_deliveries' => 'Échouées',
        'orders_in_period' => 'Commandes',
        'in_transit' => 'En transit',
        'out_for_delivery' => 'En livraison',
        'returned_orders' => 'Retournées',
        'average_delivery_time_hours' => 'Délai moyen',
        'revenue_in_period' => 'Chiffre d\'affaires',
        'cash_to_collect' => 'Cash à encaisser',
        'top_drivers' => 'Meilleurs livreurs',
        'delivered_count' => '{count} livrée|{count} livrées',
        'hours' => ':value h',
    ],

    'errors' => [
        'disabled' => 'L\'assistant n\'est pas disponible sur cette instance.',
        'unavailable' => 'L\'assistant est momentanément injoignable. Réessayez dans un instant.',
        'busy' => 'L\'assistant a atteint son quota d\'utilisation. Réessayez dans quelques secondes.',
    ],

    'pdf' => [
        'title' => 'FACTURE',
        'proforma_title' => 'RELEVÉ',
        'proforma_notice' => 'Proforma — commande non encore facturée',
        'generated_on' => 'Généré le',
        'seller' => 'Vendeur',
        'recipient' => 'Destinataire',
        'currency' => 'MAD',
        'footer' => 'Document généré par l\'assistant OWL Delivery.',

        'columns' => [
            'description' => 'Désignation',
            'reference' => 'Référence',
            'date' => 'Date',
            'amount' => 'Montant',
            'delivery_service' => 'Prestation de livraison de colis',
        ],

        'totals' => [
            'order_amount' => 'Montant de la commande',
            'delivery_fee' => 'Frais de livraison',
            'return_fee' => 'Frais de retour',
            'net' => 'Net vendeur',
        ],
    ],
];
