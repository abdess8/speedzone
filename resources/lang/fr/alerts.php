<?php

return [
    'title' => 'Annonces',
    'subtitle' => 'Diffusez un message à vos utilisateurs, en bandeau ou en fenêtre à la connexion.',

    'create_title' => 'Nouvelle annonce',
    'edit_title' => "Modifier l'annonce",
    'create_button' => 'Publier',
    'update_button' => 'Enregistrer',

    'types' => [
        'info' => 'Information',
        'warning' => 'Avertissement',
        'danger' => 'Critique',
        'success' => 'Bonne nouvelle',
    ],

    'formats' => [
        'modal' => 'Fenêtre',
        'banner' => 'Bandeau',
        'modal_hint' => "S'ouvre par-dessus l'interface à la première page de la session. Le lecteur doit la fermer pour continuer.",
        'banner_hint' => 'Se place en haut de la zone de contenu, sur toutes les pages.',
    ],

    'statuses' => [
        'active' => "À l'antenne",
        'expired' => 'Expirée',
        'disabled' => 'Désactivée',
    ],

    'table' => [
        'announcement' => 'Annonce',
        'type' => 'Type',
        'format' => 'Format',
        'audience' => 'Destinataires',
        'status' => 'Statut',
        'end_date' => 'Fin',
        'author' => 'Créée par',
        'actions' => 'Actions',
        'empty' => 'Aucune annonce pour le moment.',
        'empty_hint' => 'Publiez-en une pour joindre vos vendeurs, livreurs ou dispatcheurs.',
    ],

    'filters' => [
        'title' => 'Filtres',
        'search' => 'Rechercher un titre',
        'type' => 'Type',
        'format' => 'Format',
        'status' => 'Statut',
        'all' => 'Tous',
    ],

    'form' => [
        'appearance' => 'Type et format',
        'appearance_hint' => "L'apparence de l'annonce, et l'endroit où elle apparaît.",
        'audience' => 'Destinataires',
        'audience_hint' => "Les rôles et les villes se restreignent mutuellement. Les personnes nommées s'ajoutent par-dessus.",
        'content' => 'Contenu',
        'schedule' => 'Planification',
        'schedule_hint' => "L'annonce disparaît d'elle-même une fois ce moment passé.",

        'title_field' => 'Titre',
        'message' => 'Message',
        'message_hint' => 'Gras, italique, couleur, taille, listes et liens sont conservés. Le reste est retiré par sécurité.',
        'end_date' => 'Masquer à partir du',
        'dismissible' => 'Le lecteur peut la fermer',
        'dismissible_hint' => 'Désactivez pour figer le bandeau sur toutes les pages, sans bouton de fermeture.',
        'dismissible_modal_note' => 'Une fenêtre est toujours fermable, sans quoi le lecteur ne pourrait plus rien faire.',
        'active' => 'Publier immédiatement',

        'roles' => 'Par rôle',
        'all_roles' => 'Tous les rôles',
        'cities' => 'Par ville',
        'all_cities' => 'Toutes les villes',
        'cities_hint' => 'Un livreur est reconnu sur les villes des secteurs qui lui sont affectés, un vendeur sur les villes de ses boutiques.',
        'users' => 'Personnes nommées',
        'users_placeholder' => 'Rechercher un nom ou une adresse e-mail',
        'users_hint' => "Ces personnes reçoivent l'annonce quelle que soit la sélection de rôles et de villes.",
    ],

    'audience' => [
        'everyone' => 'Tout le monde',
        'nobody' => 'Personne pour le moment',
        'all_roles' => 'tous les rôles',
        'all_cities' => 'partout',
        'roles_in_cities' => ':roles, :cities',
        'plus_users' => '+ :count nommées',
        'only_users' => ':count personnes nommées',
        'summary_label' => 'Cette annonce touchera',
    ],

    'flash' => [
        'created' => 'Annonce « :title » publiée.',
        'updated' => 'Annonce « :title » mise à jour.',
        'deleted' => 'Annonce « :title » supprimée.',
        'enabled' => 'Annonce « :title » remise à l\'antenne.',
        'disabled' => 'Annonce « :title » désactivée.',
        'expired_cannot_enable' => 'Cette annonce est expirée. Repoussez sa date de fin avant de la réactiver.',
    ],

    'validation' => [
        'end_date_future' => "Choisissez un moment dans le futur, sinon l'annonce est expirée dès l'enregistrement.",
        'no_audience' => 'Cette annonce ne toucherait personne. Choisissez au moins un rôle et une ville, ou nommez les personnes concernées.',
        'unknown_role' => "L'un des rôles sélectionnés n'existe plus. Rechargez la page et choisissez à nouveau les rôles.",
        'unknown_city' => "L'une des villes sélectionnées est inconnue ou n'est plus active. Rechargez la page et choisissez à nouveau les villes.",
    ],

    'actions' => [
        'enable' => 'Activer',
        'disable' => 'Désactiver',
        'edit' => 'Modifier',
        'delete' => 'Supprimer',
        'dismiss' => 'Fermer',
        'understood' => "J'ai compris",
    ],

    'delete_confirm_title' => 'Supprimer cette annonce ?',
    'delete_confirm_text' => '« :title » sera définitivement retirée.',
];
