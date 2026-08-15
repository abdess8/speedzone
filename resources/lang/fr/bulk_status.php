<?php

return [
    'title' => 'Modification en masse',
    'page_title' => 'Statuts',
    'subtitle' => 'Modifiez le statut de plusieurs commandes ou retours en une seule opération.',
    'menu' => 'Modification en masse',
    'quick_action' => 'Actions rapides',

    'entities' => [
        'ORDER' => 'Commandes',
        'RETURN' => 'Retours',
    ],

    'steps' => [
        'entity' => 'Type',
        'target' => 'Statut cible',
        'selection' => 'Sélection',
        'confirmation' => 'Confirmation',
        'result' => 'Résultat',
    ],

    'entity_step' => [
        'title' => 'Que souhaitez-vous modifier ?',
        'help' => 'Seuls les types pour lesquels vous disposez d\'au moins une transition autorisée sont proposés.',
    ],

    'target_step' => [
        'title' => 'Modifier le statut vers',
        'help' => 'Vous ne voyez que les statuts vers lesquels vos permissions vous autorisent à basculer.',
        'sources' => 'Depuis : :statuses',
        'empty' => 'Aucun statut cible ne vous est autorisé pour ce type.',
    ],

    'selection' => [
        'title' => 'Éléments éligibles',
        'eligible' => '{0} Aucun élément éligible|{1} :count élément éligible|[2,*] :count éléments éligibles',
        'selected' => '{0} Aucun élément sélectionné|{1} :count élément sélectionné|[2,*] :count éléments sélectionnés',
        'select_all' => 'Sélectionner tout',
        'select_page' => 'Sélectionner la page',
        'clear' => 'Tout désélectionner',
        'empty' => 'Aucun élément ne peut passer vers ce statut avec vos accès actuels.',
        'search' => 'N° de commande, référence, destinataire, téléphone…',
        'source_filter' => 'Statut actuel',
        'all_sources' => 'Tous les statuts sources',
        'loading' => 'Chargement des éléments…',
    ],

    'columns' => [
        'reference' => 'Référence',
        'current_status' => 'Statut actuel',
        'new_status' => 'Nouveau statut',
        'customer' => 'Destinataire',
        'details' => 'Informations',
        'created' => 'Créé le',
    ],

    'details' => [
        'city' => 'Ville',
        'sector' => 'Secteur',
        'seller' => 'Vendeur',
        'to_collect' => 'À encaisser',
        'order' => 'Commande',
        'current_city' => 'Ville actuelle',
        'reason' => 'Motif',
        'driver' => 'Livreur',
    ],

    'scan' => [
        'button' => 'Scanner un QR Code',
        'title' => 'Scanner les QR Codes',
        'help' => 'Scannez les codes les uns après les autres : chaque élément valide est ajouté à votre sélection.',
        'manual' => 'Ou saisissez la référence',
        'add' => 'Ajouter',
        'start' => 'Activer la caméra',
        'stop' => 'Arrêter la caméra',
        'added' => ':reference ajouté à la sélection.',
        'already' => ':reference est déjà sélectionné.',
        'unreadable' => 'Ce QR Code ne correspond à aucune référence connue.',
        'not_found' => 'Aucun élément trouvé pour la référence :reference.',
        'inaccessible' => 'L\'élément :reference n\'est pas accessible avec votre compte.',
        'transition_forbidden' => ':reference ne peut pas passer de « :from » à « :to ».',
        'camera_unsupported' => 'Ce navigateur ne permet pas d\'accéder à la caméra. Saisissez la référence à la main.',
        'camera_error' => 'Impossible d\'accéder à la caméra. Vérifiez l\'autorisation dans votre navigateur.',
        'unreachable' => 'Vérification impossible. Réessayez.',
    ],

    'confirm' => [
        'title' => 'Confirmer la modification',
        'intro' => 'Vous êtes sur le point de modifier :',
        'items' => '{1} :count élément|[2,*] :count éléments',
        'breakdown' => ':count × :from → :to',
        'comment' => 'Commentaire (facultatif)',
        'comment_help' => 'Ajouté à l\'historique de chaque élément modifié.',
        'submit' => 'Confirmer la modification',
        'irreversible' => 'Cette action est enregistrée dans l\'historique de chaque élément et ne peut pas être annulée en masse.',
        'consequences' => 'Certaines transitions déclenchent des actions métier (paiement du livreur, synchronisation partenaire, clôture du retour).',
    ],

    'result' => [
        'title' => 'Résultat de la modification',
        'succeeded' => '{0} Aucun élément traité|{1} :count élément traité avec succès|[2,*] :count éléments traités avec succès',
        'failed' => '{1} :count élément n\'a pas pu être modifié|[2,*] :count éléments n\'ont pas pu être modifiés',
        'reason' => 'Raison',
        'restart' => 'Nouvelle modification',
        'back_to_list' => 'Retour à la liste',
    ],

    'failures' => [
        'NOT_FOUND' => 'Élément introuvable.',
        'INACCESSIBLE' => 'Élément inaccessible avec votre compte.',
        'PERMISSION_DENIED' => 'Permission insuffisante.',
        'TRANSITION_NOT_ALLOWED' => 'Transition non autorisée.',
        'STATUS_CHANGED' => 'Le statut a déjà été modifié.',
        'BUSINESS_RULE' => 'Erreur métier.',
        'status_changed_detail' => 'Sélectionné en « :expected », il est désormais en « :actual ».',
        'unexpected' => 'Une erreur inattendue est survenue.',
    ],

    'flash' => [
        'success' => '{1} :succeeded élément modifié avec succès.|[2,*] :succeeded éléments modifiés avec succès.',
        'warning' => ':succeeded élément(s) modifié(s), :failed en échec.',
        'error' => 'Aucun élément n\'a pu être modifié (:failed en échec).',
    ],

    'errors' => [
        'no_transition' => 'Aucune transition de statut ne vous est autorisée.',
        'unknown_entity' => 'Type d\'élément inconnu.',
        'target_forbidden' => 'Ce statut cible ne vous est pas autorisé.',
    ],

    'audit' => [
        'comment' => 'Modification en masse',
    ],

    'permissions' => [
        'title' => 'Permissions de modification des statuts',
        'page_title' => 'Administration',
        'subtitle' => 'Définissez, pour chaque rôle, les transitions « statut actuel → nouveau statut » utilisables en modification en masse.',
        'menu' => 'Permissions de statuts',
        'admin_only' => 'Seul un administrateur peut gérer ces permissions.',
        'saved' => 'Permission mise à jour.',
        'transition' => 'Transition',
        'roles' => 'Rôles',
        'search' => 'Filtrer les transitions…',
        'empty' => 'Aucune transition ne correspond à votre recherche.',
        'granted_count' => '{0} Aucun rôle|{1} 1 rôle|[2,*] :count rôles',
        'help' => 'Les transitions listées sont celles que le workflow autorise réellement : une transition impossible ne peut pas être accordée.',
        'note' => 'Les utilisateurs héritent des permissions de leur rôle. Un administrateur dispose de toutes les transitions.',
    ],
];
