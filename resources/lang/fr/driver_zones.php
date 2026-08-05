<?php

return [
    'title' => 'Affectation des livreurs',
    'list_title' => 'Gestion des zones livreurs',
    'filters' => [
        'all_cities' => 'Toutes les villes',
        'all_sectors' => 'Tous les secteurs',
        'search_driver' => 'Rechercher un livreur',
        'search_placeholder' => 'Nom, email ou téléphone…',
        'filter_by_city' => 'Filtrer par ville',
        'city_placeholder' => 'Ville…',
        'filter_by_sector' => 'Filtrer par secteur',
        'sector_placeholder' => 'Secteur…',
    ],
    'table' => [
        'driver' => 'Livreur',
        'zones' => 'Zones',
        'assigned_sectors' => 'Secteurs assignés',
    ],
    'no_sectors_assigned' => 'Aucun secteur assigné.',
    'actions' => [
        'manage' => 'Gérer',
        'remove' => 'Retirer',
    ],
    'remove_confirm_title' => 'Retirer le secteur ?',
    'remove_confirm_text' => ':sector sera retiré de :driver.',
    'pagination_range' => ':from–:to sur :total livreurs',
    'modal' => [
        'title' => 'Assigner des secteurs — :name',
        'description' => 'Sélectionnez les secteurs de livraison que ce livreur doit desservir. La sélection actuelle remplace l\'affectation existante.',
        'sectors_placeholder' => 'Rechercher et sélectionner des secteurs…',
        'save' => 'Enregistrer l\'affectation',
    ],
    'sector_option_label' => ':name (:price MAD)',
    'empty' => 'Aucun livreur trouvé.',
];
