<?php

return [
    'title' => 'Mon équipe',
    'subtitle' => 'Gérez les collaborateurs qui accèdent à vos boutiques.',
    'create_title' => 'Nouveau collaborateur',
    'edit_title' => 'Modifier le collaborateur',
    'empty' => 'Aucun collaborateur pour le moment.',
    'empty_hint' => 'Créez un rôle, puis invitez votre premier collaborateur.',
    'add' => 'Ajouter un collaborateur',
    'manage_roles' => 'Gérer les rôles',

    'fields' => [
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'email' => 'E-mail',
        'phone_number' => 'Téléphone',
        'password' => 'Mot de passe',
        'password_confirmation' => 'Confirmer le mot de passe',
        'locale' => 'Langue',
        'stores' => 'Boutiques accessibles',
        'roles' => 'Rôles',
        'status' => 'Statut',
        'sessions' => 'Sessions actives',
        'last_activity' => 'Dernière activité',
    ],

    'hints' => [
        'password' => 'Communiquez ce mot de passe au collaborateur ; il pourra le changer depuis son profil.',
        'password_edit' => 'Laissez vide pour conserver le mot de passe actuel. Le modifier déconnecte immédiatement le collaborateur.',
        'stores' => 'Le collaborateur ne verra que les commandes, factures et ramassages des boutiques cochées.',
        'roles' => 'Les rôles déterminent ce que le collaborateur peut faire. Ils ne peuvent jamais dépasser vos propres droits.',
    ],

    'sections' => [
        'identity' => 'Identité',
        'access' => 'Accès',
        'security' => 'Connexion',
    ],

    'sessions' => [
        'none' => 'Aucune session ouverte',
        'count' => ':count session ouverte|:count sessions ouvertes',
        'never' => 'Jamais connecté',
    ],

    'actions' => [
        'suspend' => 'Suspendre',
        'reactivate' => 'Réactiver',
        'edit' => 'Modifier',
    ],

    'suspend_confirm_title' => 'Suspendre :name ?',
    'suspend_confirm_text' => 'Ses sessions ouvertes seront fermées immédiatement et il ne pourra plus se connecter.',

    'flash' => [
        'created' => 'Collaborateur :name créé.',
        'updated' => 'Collaborateur :name mis à jour.',
        'suspended' => 'Accès de :name suspendu et sessions fermées.',
        'reactivated' => 'Accès de :name rétabli.',
    ],

    'errors' => [
        'not_a_member' => 'Ce compte ne fait pas partie de votre équipe.',
        'store_required' => 'Sélectionnez au moins une boutique.',
        'role_required' => 'Sélectionnez au moins un rôle de votre équipe.',
        'no_store' => 'Créez d\'abord une boutique avant d\'ajouter un collaborateur.',
        'no_role' => 'Créez d\'abord un rôle avant d\'ajouter un collaborateur.',
    ],

    'login' => [
        'suspended' => 'Votre accès a été suspendu par l\'administrateur de votre compte.',
    ],

    'roles' => [
        'title' => 'Rôles de l\'équipe',
        'subtitle' => 'Définissez ce que chaque type de collaborateur peut faire.',
        'create_title' => 'Nouveau rôle',
        'edit_title' => 'Modifier le rôle',
        'add' => 'Créer un rôle',
        'empty' => 'Aucun rôle personnalisé.',
        'empty_hint' => 'Exemples : Gestionnaire de stock, Préparateur de commandes.',
        'back' => 'Retour à l\'équipe',

        'fields' => [
            'label' => 'Nom du rôle',
            'permissions' => 'Permissions',
        ],

        'hints' => [
            'label' => 'Exemple : Préparateur de commandes.',
            'permissions' => 'Seules les permissions que vous détenez vous-même sont proposées. La gestion des boutiques et de l\'équipe reste réservée à l\'administrateur du compte.',
        ],

        'members_count' => ':count collaborateur|:count collaborateurs',
        'permissions_count' => ':count permission|:count permissions',
        'select_all' => 'Tout cocher',
        'clear_all' => 'Tout décocher',

        'delete_confirm_title' => 'Supprimer le rôle :name ?',
        'delete_confirm_text' => 'Cette action est définitive.',

        'flash' => [
            'created' => 'Rôle :name créé.',
            'updated' => 'Rôle :name mis à jour.',
            'deleted' => 'Rôle :name supprimé.',
        ],

        'errors' => [
            'in_use' => 'Ce rôle est encore attribué à des collaborateurs.',
            'system_role' => 'Ce rôle appartient à la plateforme et ne peut pas être modifié.',
            'permission_required' => 'Sélectionnez au moins une permission.',
        ],
    ],

    'resources' => [
        'orders' => 'Commandes',
        'pickup_requests' => 'Ramassages',
        'returns' => 'Retours',
        'invoices' => 'Factures',
        'stores' => 'Boutiques',
        'support' => 'Support',
        'cities' => 'Villes',
        'sectors' => 'Secteurs',
    ],

    'actions_labels' => [
        'create' => 'Créer',
        'read' => 'Consulter',
        'update' => 'Modifier',
        'delete' => 'Supprimer',
        'export' => 'Exporter',
        'print' => 'Imprimer',
        'create_request' => 'Demander un retour',
        'reply' => 'Répondre',
        'close' => 'Clôturer',
        'update_status' => 'Changer le statut',
    ],

    'scopes' => [
        'own' => 'ses propres données',
        'all' => 'toutes les données',
        'assigned' => 'les données assignées',
    ],
];
