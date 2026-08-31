<?php

return [
    'title' => 'Préparation des commandes',
    'queue_title' => 'À préparer',
    'empty' => 'Rien à préparer',
    'empty_hint' => 'Les commandes passées avec des produits en dépôt arrivent ici dès leur création.',

    'columns' => [
        'tracking' => 'Numéro de suivi',
        'created' => 'Commandée le',
        'store' => 'Boutique',
        'customer' => 'Client',
        'city' => 'Ville',
        'lines' => 'À prélever',
        'routing' => 'Après emballage',
        'hub' => 'Dépôt',
        'units' => 'Unités',
        'check' => 'Contrôle',
    ],

    'filters' => [
        'search' => 'Numéro de suivi…',
        'all_hubs' => 'Tous les dépôts',
    ],

    'routing' => [
        'local' => 'Livraison sur place',
        'transfer' => 'Transfert à prévoir',
    ],

    'actions' => [
        'scan' => 'Scanner des colis',
        'mark_prepared' => 'Marquer préparée(s)',
        'prepare_short' => 'Préparée',
    ],

    'selection' => [
        'count' => '{count} commande(s) sélectionnée(s)',
        'units' => '{count} unité(s) à prélever',
    ],

    'confirm' => [
        'title' => 'Marquer {count} commande(s) comme préparée(s) ?',
        'text' => 'Les colis dont le dépôt est dans la ville du client partent aussitôt en livraison. Les autres attendent un transfert.',
    ],

    'scanner' => [
        'title' => 'Scanner les colis préparés',
        'camera_preview' => 'Présentez le QR code du bordereau devant la caméra.',
        'camera_error' => 'Impossible d\'ouvrir la caméra. Saisissez les numéros à la main.',
        'camera_unsupported' => 'Ce navigateur ne donne pas accès à la caméra. Saisissez les numéros à la main.',
        'start_camera' => 'Activer la caméra',
        'aim' => 'Placez le QR Code dans le cadre',
        'manual_label' => 'Numéro de suivi',
        'manual_placeholder' => 'Scannez ou saisissez un numéro puis Entrée',
        'invalid_tracking' => 'Ce numéro de suivi n\'est pas reconnu.',
        'already' => ':reference est déjà dans la liste.',
        'add' => 'Ajouter',
        'scanned' => '{count} colis scanné(s)',
        'valid_count' => '{count} accepté(s)',
        'clear_all' => 'Tout vider',
        'nothing_scanned' => 'Aucun colis scanné pour le moment.',
        'valid' => 'Accepté',
        'rejected' => 'Refusé',
        'unable_validate' => 'Vérification impossible. Réessayez.',
        'mark_prepared' => 'Marquer préparées',
        'mark_prepared_count' => 'Marquer {count} colis préparés',
        'confirm' => 'Marquer {count} colis comme préparés ?',
        'confirm_hint' => 'Les colis dont le dépôt est dans la ville du client partent aussitôt en livraison. Les autres attendent un transfert.',
        'done' => '{prepared} colis préparé(s), {skipped} ignoré(s).',
        'bulk_failed' => 'La mise à jour a échoué',
    ],

    // Read by Laravel, hence the `:placeholder` syntax. The scanner's own
    // counterpart lives under `scanner.done` because vue-i18n cannot read these.
    'flash' => [
        'prepared' => ':prepared commande(s) préparée(s), :skipped ignorée(s).',
        'none_prepared' => 'Aucune commande préparée : elles ont déjà été traitées ou ne vous sont pas accessibles.',
    ],

    'errors' => [
        'unknown_order' => 'Numéro de suivi inconnu.',
        'not_yours' => 'Cette commande ne vous est pas accessible.',
        'wrong_status' => 'Cette commande est déjà en « :status ».',
        'unknown_in_batch' => 'Numéros de suivi inconnus : :codes',
    ],
];
