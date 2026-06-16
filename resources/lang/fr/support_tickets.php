<?php

return [
    'title' => 'Centre de support',
    'page_title' => 'Support',
    'list_title' => 'Tickets de support',
    'create' => 'Créer un ticket',
    'empty' => 'Aucun ticket de support trouvé.',

    'filters' => [
        'reference' => 'N° ticket',
        'subject' => 'Sujet',
        'seller' => 'Vendeur',
        'seller_placeholder' => 'Nom ou e-mail',
        'assigned_to' => 'Assigné à',
        'unassigned' => 'Non assigné',
        'status' => 'Statut',
        'all_statuses' => 'Tous les statuts',
        'category' => 'Catégorie',
        'all_categories' => 'Toutes les catégories',
        'created_from' => 'Créé à partir du',
        'created_to' => 'Créé jusqu\'au',
    ],

    'table' => [
        'reference' => 'Référence',
        'seller' => 'Vendeur',
        'object' => 'Objet lié',
        'category' => 'Catégorie',
        'status' => 'Statut',
        'assigned' => 'Assigné à',
        'subject' => 'Sujet',
        'messages' => 'Messages',
        'created_at' => 'Créé le',
        'last_reply' => 'Dernière réponse',
    ],

    'detail' => [
        'title' => 'Ticket :reference',
        'page_title' => 'Ticket de support',
        'info' => 'Informations du ticket',
        'conversation' => 'Conversation',
        'no_messages' => 'Aucun message pour le moment. Commencez la conversation ci-dessous.',
        'read_only' => 'Ce ticket est clôturé. La conversation est en lecture seule.',
        'initial_message' => 'Demande initiale',
        'attachments' => 'Pièces jointes',
        'related_object' => 'Objet lié',
        'no_object' => 'Aucun objet lié',
    ],

    'create_form' => [
        'title' => 'Créer un ticket de support',
        'page_title' => 'Nouveau ticket',
        'step_category' => 'Type de support',
        'step_object' => 'Objet lié',
        'step_message' => 'Décrivez votre demande',
        'step_attachment' => 'Pièce jointe (optionnel)',
        'category' => 'Catégorie',
        'category_placeholder' => 'Sélectionnez une catégorie',
        'object_type' => 'Type d\'objet',
        'object_type_placeholder' => 'Sélectionnez le type',
        'object_id' => 'Sélectionner l\'enregistrement',
        'object_id_placeholder' => 'Choisissez un enregistrement',
        'object_hint' => 'Seuls vos propres enregistrements sont affichés.',
        'subject' => 'Sujet',
        'subject_placeholder' => 'Titre court de votre demande',
        'message' => 'Message',
        'message_placeholder' => 'Décrivez votre demande en détail…',
        'attachment' => 'Pièce jointe',
        'attachment_hint' => 'Images, PDF ou documents jusqu\'à 10 Mo.',
        'submit' => 'Soumettre le ticket',
        'loading_objects' => 'Chargement des enregistrements…',
        'no_objects' => 'Aucun enregistrement trouvé pour ce type.',
    ],

    'chat' => [
        'placeholder' => 'Saisissez votre message…',
        'send' => 'Envoyer',
        'attach' => 'Joindre un fichier',
        'you' => 'Vous',
        'support' => 'Support',
        'seller' => 'Vendeur',
    ],

    'actions' => [
        'view' => 'Voir',
        'assign' => 'Assigner',
        'change_status' => 'Changer le statut',
        'close' => 'Clôturer le ticket',
        'back_to_list' => 'Retour aux tickets',
        'create_for_object' => 'Créer un ticket',
    ],

    'assign' => [
        'title' => 'Assigner le ticket',
        'select_agent' => 'Sélectionner un agent support',
        'unassign' => 'Désassigner',
        'submit' => 'Assigner',
    ],

    'status' => [
        'title' => 'Mettre à jour le statut',
        'select' => 'Sélectionner le nouveau statut',
        'submit' => 'Mettre à jour',
    ],

    'panel' => [
        'title' => 'Tickets de support',
        'empty' => 'Aucun ticket de support pour cet enregistrement.',
        'create' => 'Créer un ticket',
    ],

    'confirms' => [
        'close_title' => 'Clôturer ce ticket ?',
        'close_text' => 'La conversation deviendra en lecture seule.',
        'confirm' => 'Oui, continuer',
    ],

    'created' => 'Ticket :reference créé avec succès.',
    'message_sent' => 'Message envoyé.',
    'assigned' => 'Ticket assigné avec succès.',
    'status_updated' => 'Statut du ticket mis à jour.',
    'closed' => 'Ticket clôturé.',

    'errors' => [
        'object_not_found' => 'L\'enregistrement sélectionné n\'existe pas.',
        'object_forbidden' => 'Vous n\'avez pas accès à cet enregistrement.',
        'empty_message' => 'Veuillez saisir un message ou joindre un fichier.',
        'action_not_allowed' => 'Cette action n\'est pas autorisée pour ce ticket.',
        'ticket_closed' => 'Ce ticket est clôturé.',
    ],

    'notifications' => [
        'new_ticket' => 'Nouveau ticket :reference',
        'new_reply' => 'Nouvelle réponse sur le ticket :reference',
        'assigned' => 'Ticket :reference assigné à vous',
    ],
];
