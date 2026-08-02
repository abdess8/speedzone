<?php

return [
    'register' => [
        'title' => 'Inscription vendeur',
        'subtitle' => 'Rejoignez Speed Zone en tant que vendeur',
        'heading' => 'Créez votre compte vendeur',
        'description' => 'Inscrivez-vous pour expédier avec Speed Zone Express.',
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'email' => 'E-mail',
        'phone' => 'Téléphone',
        'city' => 'Ville',
        'city_placeholder' => 'Rechercher et sélectionner votre ville',
        'password' => 'Mot de passe',
        'password_confirmation' => 'Confirmer le mot de passe',
        'submit' => 'Créer le compte',
        'already_have_account' => 'Vous avez déjà un compte ?',
        'sign_in' => 'Se connecter',
    ],

    'registered' => 'Inscription réussie. Veuillez vérifier votre adresse e-mail pour continuer.',

    'login' => [
        'unverified' => 'Veuillez vérifier votre adresse e-mail avant d\'accéder à votre compte.',
        'rejected' => 'Votre demande d\'inscription a été rejetée.',
    ],

    'pending' => [
        'title' => 'Compte en attente d\'approbation',
        'heading' => 'Inscription en cours de validation',
        'message' => 'Votre inscription est en cours de validation. L\'équipe Speed Zone vérifie votre compte.',
        'review_note' => 'Vous recevrez un e-mail une fois votre compte approuvé.',
        'contact_support' => 'Contacter le support',
        'sign_out' => 'Se déconnecter',
    ],

    'admin' => [
        'page_title' => 'Inscriptions vendeurs en attente',
        'details_title' => 'Examiner l\'inscription vendeur',
        'search_placeholder' => 'Rechercher par nom, e-mail ou téléphone…',
        'all_statuses' => 'Tous les statuts',
        'empty' => 'Aucune inscription vendeur en attente.',
        'view_details' => 'Voir les détails',
        'personal_info' => 'Informations personnelles',
        'approval_section' => 'Approbation et permissions',
        'permissions_help' => 'Sélectionnez les permissions que ce vendeur aura une fois approuvé.',
        'approve' => 'Approuver',
        'reject' => 'Rejeter',
        'rejection_reason' => 'Motif du rejet',
        'approved_success' => 'Compte vendeur approuvé avec succès.',
        'rejected_success' => 'Inscription vendeur rejetée.',
        'columns' => [
            'name' => 'Nom',
            'email' => 'E-mail',
            'phone' => 'Téléphone',
            'city' => 'Ville',
            'registered_at' => 'Date d\'inscription',
            'status' => 'Statut',
            'actions' => 'Actions',
        ],
    ],

    'emails' => [
        'verification_subject' => 'Vérifiez votre compte Speed Zone',
        'approved_subject' => 'Votre compte Speed Zone est approuvé',
        'approved_heading' => 'Votre compte est approuvé',
        'approved_body' => 'Bonjour :name, votre compte vendeur Speed Zone a été approuvé. Vous pouvez maintenant accéder à la plateforme.',
        'approved_button' => 'Se connecter à Speed Zone',
        'approved_footer' => 'Si vous n\'avez pas demandé ce compte, veuillez contacter le support.',
        'rejected_subject' => 'Votre inscription Speed Zone a été rejetée',
        'rejected_heading' => 'Inscription non approuvée',
        'rejected_body' => 'Bonjour :name, votre demande d\'inscription a été rejetée.',
        'rejected_reason_label' => 'Motif',
        'rejected_footer' => 'Si vous pensez qu\'il s\'agit d\'une erreur, contactez notre support.',
    ],
];
