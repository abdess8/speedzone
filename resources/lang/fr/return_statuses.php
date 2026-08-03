<?php

return [
    'CREATED' => 'Créé',
    'RECEIVED_AT_HUB' => 'Reçu au dépôt',
    'IN_TRANSIT_TO_DEPOT' => 'En transit vers hub',
    'ARRIVED_VENDOR_HUB' => 'Arrivé hub vendeur',
    'IN_DELIVERY_TO_VENDOR' => 'En cours de restitution',
    'DELIVERED_TO_VENDOR' => 'Restitué au vendeur',
    'CANCELLED' => 'Annulé',

    'descriptions' => [
        'CREATED' => 'Le retour vient d\'être ouvert, suite à l\'échec définitif d\'une livraison ou à la demande du vendeur. Le colis est encore chez le livreur.',
        'RECEIVED_AT_HUB' => 'Le livreur a déposé le colis non livré au hub de la ville de livraison. Le colis attend d\'être intégré à un bordereau de transfert.',
        'IN_TRANSIT_TO_DEPOT' => 'Le colis roule vers le hub de la ville du vendeur, à l\'intérieur d\'un bordereau de transfert inter-villes.',
        'ARRIVED_VENDOR_HUB' => 'Le transfert a été réceptionné et scanné au hub de la ville d\'origine du vendeur. Le colis attend un livreur pour la restitution.',
        'IN_DELIVERY_TO_VENDOR' => 'Un livreur a pris le colis en charge pour le remettre en main propre au vendeur.',
        'DELIVERED_TO_VENDOR' => 'Le colis a été remis au vendeur. Le retour est clos et la commande est comptabilisée comme retournée.',
        'CANCELLED' => 'Le retour a été abandonné : la commande reprend le statut qu\'elle avait avant l\'ouverture du retour.',
    ],

    'actors' => [
        'CREATED' => 'Livreur, Vendeur ou système (échec de livraison)',
        'RECEIVED_AT_HUB' => 'Responsable du hub destinataire',
        'IN_TRANSIT_TO_DEPOT' => 'Responsable du hub destinataire (au départ du transfert)',
        'ARRIVED_VENDOR_HUB' => 'Responsable du hub vendeur (à la réception du transfert)',
        'IN_DELIVERY_TO_VENDOR' => 'Livreur affecté à la restitution',
        'DELIVERED_TO_VENDOR' => 'Livreur affecté à la restitution',
        'CANCELLED' => 'Administration (gestion des retours)',
    ],
];
