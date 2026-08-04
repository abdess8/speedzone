<?php

return [
    'title' => 'Centre d\'aide & Partenariat',

    'partnership' => [
        'page_title' => 'Contrat & Conditions de partenariat',
        'intro' => 'Les règles de collaboration entre OWL Delivery et ses vendeurs partenaires. Elles s\'appliquent à toute commande confiée à la plateforme.',

        'scope' => [
            'title' => 'Objet du partenariat',
            'summary' => 'Ce que OWL Delivery prend en charge, et ce qui reste à la main du vendeur.',
            'points' => [
                'OWL Delivery assure le ramassage chez le vendeur, l\'acheminement inter-villes, la livraison au client final et l\'encaissement du montant à la livraison.',
                'Le vendeur reste responsable du contenu du colis, de sa conformité à la commande et de son emballage.',
                'Chaque colis doit porter l\'étiquette générée par la plateforme : un colis non étiqueté peut être refusé au ramassage.',
                'Le partenariat est sans engagement de volume et sans durée minimale.',
            ],
        ],

        'pricing' => [
            'title' => 'Grille tarifaire par zone',
            'summary' => 'Les frais de livraison dépendent de la ville de destination et du secteur.',
            'points' => [
                'Le tarif applicable est celui affiché sur la commande au moment de sa création : une révision de grille ne s\'applique jamais rétroactivement.',
                'Les livraisons intra-ville sont facturées au tarif zone locale ; les livraisons inter-villes ajoutent le coût de transfert de la zone concernée.',
                'Les zones éloignées et les secteurs hors périmètre standard font l\'objet d\'un supplément indiqué sur la commande.',
                'Le montant encaissé auprès du client (contre-remboursement) est distinct des frais de livraison et vous est reversé intégralement, déduction faite de ces frais.',
            ],
        ],

        'payouts' => [
            'title' => 'Délais et modalités de versement',
            'summary' => 'Quand et comment le montant encaissé revient sur votre compte.',
            'points' => [
                'Seules les commandes au statut « Livré » entrent dans une facture : une commande en cours de livraison n\'est jamais versée par anticipation.',
                'Les factures sont générées selon la fréquence configurée sur votre compte (hebdomadaire par défaut) et couvrent toutes les commandes livrées sur la période.',
                'Le versement est effectué par virement ou par chèque, selon le mode de paiement enregistré sur votre profil.',
                'Chaque facture détaille, ligne par ligne, le montant encaissé, les frais de livraison et le net à payer.',
            ],
        ],

        'returns' => [
            'title' => 'Frais et traitement des retours',
            'summary' => 'Ce qui se passe, et ce qui est facturé, lorsqu\'un colis ne peut pas être livré.',
            'points' => [
                'Un colis refusé, annulé ou dont la livraison a définitivement échoué entre automatiquement dans le circuit de retour et vous est restitué.',
                'Les frais de retour couvrent le trajet inverse jusqu\'au dépôt de votre ville ; ils sont déduits de la facture de la période concernée.',
                'Le retour suit six étapes traçables, de la réception au dépôt jusqu\'à la remise en main propre. Vous pouvez suivre chaque étape depuis la rubrique Retours.',
                'Vous pouvez corriger les coordonnées de restitution tant que le retour est au statut « Créé » ou « En cours de restitution ».',
                'Un retour non réclamé après trente jours au dépôt peut faire l\'objet de frais de stockage.',
            ],
        ],

        'liability' => [
            'title' => 'Responsabilité en cas de perte ou de casse',
            'summary' => 'La couverture applicable et ses limites.',
            'points' => [
                'OWL Delivery est responsable du colis à compter de sa prise en charge au ramassage et jusqu\'à sa remise au client ou sa restitution au vendeur.',
                'En cas de perte avérée, le colis est indemnisé à hauteur de la valeur déclarée sur la commande, dans la limite du plafond contractuel.',
                'La casse est indemnisée sur présentation de la commande et du constat établi à la livraison, sauf emballage manifestement insuffisant.',
                'Les denrées périssables, les produits fragiles non signalés et les objets de valeur non déclarés sont exclus de la couverture.',
                'Toute réclamation doit être ouverte via un ticket de support dans les quarante-huit heures suivant l\'incident.',
            ],
        ],

        'obligations' => [
            'title' => 'Engagements réciproques',
            'summary' => 'Ce que chaque partie garantit à l\'autre.',
            'points' => [
                'Le vendeur s\'engage à fournir des coordonnées client exactes : une adresse ou un téléphone erroné est la première cause d\'échec de livraison.',
                'OWL Delivery s\'engage à tenir informé le vendeur de chaque changement de statut, en temps réel dans l\'application.',
                'Les deux parties peuvent mettre fin au partenariat à tout moment, sous réserve du traitement des colis déjà en circulation.',
                'Tout litige est traité en premier lieu par le support ; à défaut d\'accord, il relève des tribunaux compétents.',
            ],
        ],
    ],

    'processes' => [
        'page_title' => 'Processus & Statuts',
        'intro' => 'Le parcours complet d\'un colis, statut par statut, avec le rôle habilité à faire avancer chaque étape.',
        'tabs' => [
            'flows' => 'Parcours',
            'orders' => 'Statuts commandes',
            'returns' => 'Statuts retours',
            'billing' => 'Facturation',
        ],
        'legend_actor' => 'Modifié par',
        'legend_permissions' => 'Permissions',
        'no_permission' => 'Automatique (aucune action manuelle)',
        'step_of' => 'Étape {current} sur {total}',
        'play' => 'Lancer l\'animation',
        'pause' => 'Mettre en pause',
        'replay' => 'Rejouer',
        'matrix' => [
            'status' => 'Statut',
            'meaning' => 'Signification métier',
            'actor' => 'Qui peut le modifier',
            'permissions' => 'Permissions requises',
        ],
        'transfer_types' => [
            'title' => 'Types de bordereaux de transfert',
            'summary' => 'À la création d\'un bordereau, le type choisi détermine les colis proposés à la sélection.',
        ],
    ],

    'flows' => [
        'success' => [
            'title' => 'Parcours nominal',
            'summary' => 'De la commande enregistrée au versement du vendeur, sans incident.',
            'invoiced' => [
                'label' => 'Facturée',
                'description' => 'La commande livrée entre dans la facture de la période. Le montant encaissé, moins les frais de livraison, est versé au vendeur.',
                'actor' => 'Facturation (automatique à la génération)',
            ],
        ],
        'failure' => [
            'title' => 'Parcours échec & retour',
            'summary' => 'La livraison échoue : le colis repart vers son vendeur par le circuit inverse.',
            'branch_title' => 'Circuit de retour',
            'branch_summary' => 'Six étapes, de la ville de livraison jusqu\'au vendeur.',
        ],
    ],

    'billing' => [
        'seller' => [
            'title' => 'Facture vendeur',
            'summary' => 'Calculée automatiquement sur les commandes livrées de la période.',
            'collected' => 'Total encaissé auprès des clients',
            'delivery_fees' => 'Frais de livraison',
            'return_fees' => 'Frais de retour de la période',
            'payout' => 'Net à verser au vendeur',
            'note_delivered_only' => 'Seules les commandes au statut « Livré » sont retenues ; les commandes en cours restent en attente de facturation.',
            'note_returns' => 'Les commandes restituées au vendeur n\'apportent aucun encaissement mais génèrent des frais de retour.',
            'note_frequency' => 'La fréquence de facturation est réglée sur le profil du vendeur.',
        ],
        'driver' => [
            'title' => 'Décharge livreur',
            'summary' => 'Ce que le livreur doit reverser à la caisse après sa tournée.',
            'collected' => 'Espèces encaissées sur la tournée',
            'commission' => 'Commission du livreur',
            'due' => 'Montant à reverser à la caisse',
            'note_discharge' => 'La décharge est générée à partir des commandes livrées et encaissées par le livreur sur la période.',
            'note_settlement' => 'Une fois le règlement enregistré, la décharge est marquée comme payée et n\'entre plus dans les périodes suivantes.',
        ],
    ],
];
