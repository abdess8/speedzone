<?php

return [
    'title' => 'Mes boutiques',
    'list_title' => 'Boutiques',
    'list_hint' => 'Chaque boutique fonctionne de manière indépendante : commandes, ramassages, retours et factures ne sont jamais partagés entre deux boutiques.',
    'create_title' => 'Nouvelle boutique',
    'create_button' => 'Créer la boutique',
    'empty' => 'Aucune boutique pour le moment.',
    'no_category' => 'Sans catégorie',
    'switch_to' => 'Basculer',
    'orders_count' => ':count commande(s)',

    'badges' => [
        'default' => 'Par défaut',
        'active_session' => 'Boutique active',
    ],

    'switcher' => [
        'label' => 'Boutique active',
        'heading' => 'Changer de boutique',
        'manage' => 'Gérer mes boutiques',
    ],

    'picker' => [
        'title' => 'Choisissez une boutique',
        'subtitle' => 'Vous avez accès à plusieurs boutiques. Sélectionnez celle sur laquelle vous souhaitez travailler.',
    ],

    'fields' => [
        'name' => 'Nom de la boutique',
        'category' => 'Catégorie',
        'website' => 'Site web',
        'logo' => 'Logo',
        'contact_name' => 'Nom du contact',
        'contact_phone' => 'Téléphone',
        'contact_email' => 'E-mail',
        'city' => 'Ville',
        'stock_hub_city' => 'Ville du dépôt de stock',
        'address' => 'Adresse',
        'pickup_address_1' => 'Adresse de ramassage 1',
        'pickup_address_2' => 'Adresse de ramassage 2',
        'is_default' => 'Définir comme boutique par défaut',
    ],

    'form' => [
        'identity' => 'Identité de la boutique',
        'branding' => 'Logo et impression',
        'branding_hint' => 'Ce logo et ce nom sont imprimés sur les bordereaux des colis de cette boutique.',
        'contact' => 'Contact et adresses',
        'name_placeholder' => 'Ex. : Nova Cosmétiques',
        'category_placeholder' => 'Ex. : Cosmétique',
        'logo_hint' => 'PNG, JPG ou WEBP — 2 Mo maximum. Un format carré rend le mieux sur les étiquettes thermiques.',
        'default_hint' => 'La boutique par défaut est celle proposée en premier à la connexion.',
        'fulfilment' => 'Stock et préparation',
        'fulfilment_hint' => 'Le dépôt qui garde le stock de cette boutique. C\'est aussi la ville d\'où partent les commandes préparées avec vos produits.',
        'no_stock_hub' => 'Aucun dépôt — je n\'entrepose pas chez vous',
    ],

    'delete_confirm_title' => 'Supprimer cette boutique ?',
    'delete_confirm_text' => 'La boutique « :name » sera archivée. Cette action est irréversible.',

    'flash' => [
        'created' => 'Boutique « :name » créée.',
        'updated' => 'Boutique « :name » mise à jour.',
        'deleted' => 'Boutique « :name » supprimée.',
        'cannot_delete' => 'Cette boutique ne peut pas être supprimée : elle est la boutique par défaut ou contient déjà des commandes.',
    ],

    'errors' => [
        'not_accessible' => 'Vous n\'avez pas accès à cette boutique.',
        'depot_not_empty' => 'Impossible de changer de dépôt : :units unité(s) sont encore en stock dans le dépôt actuel. Écoulez ou corrigez ce stock avant de déménager.',
    ],
];
