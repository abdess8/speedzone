export default {
    meta: {
        title: 'SpeedZone | Livraison Express au Maroc',
        description:
            'SpeedZone livre vos colis partout au Maroc grâce à une plateforme moderne de suivi, de paiement à la livraison et de gestion logistique.',
        ogDescription:
            'La plateforme de livraison la plus fiable au Maroc. Gérez vos expéditions, vos paiements et vos retours depuis une seule plateforme.',
        slogan: 'Livraison rapide. Confiance assurée.',
    },

    locale: {
        label: 'Langue',
    },

    nav: {
        home: 'Accueil',
        services: 'Services',
        platform: 'Plateforme',
        zones: 'Zones couvertes',
        pricing: 'Tarifs',
        about: 'À propos',
        contact: 'Contact',
        login: 'Connexion',
        register: 'Créer un compte',
        dashboard: 'Tableau de bord',
        menu: 'Menu',
    },

    hero: {
        badge: 'La livraison nouvelle génération au Maroc',
        titleLead: 'Livrez vos colis',
        titleHighlight: 'rapidement',
        titleTail: 'avec SpeedZone.',
        subtitle:
            'La plateforme de livraison la plus fiable au Maroc. Gérez vos expéditions, vos paiements et vos retours depuis une seule plateforme.',
        cta: 'Créer un compte',
        ctaSecondary: 'Voir nos tarifs',
        rating: '4.9/5',
        ratingLabel: 'Note 4.9 sur 5',
        proof: '+500 entreprises nous font confiance',
        mock: {
            delivered: 'Colis livrés aujourd’hui',
            statusDelivered: 'Livré',
            statusOnTheWay: 'En route',
            liveTracking: 'Suivi en direct',
            driverOnTheWay: 'Livreur en route',
            eta: 'Arrivée dans 12 min',
            parcelDelivered: 'Colis livré',
            minutesAgo: 'il y a 2 min',
            downtown: 'Rabat centre',
            codPayment: 'Paiement COD',
            pickupConfirmed: 'Ramassage confirmé',
        },
    },

    services: {
        eyebrow: 'Nos services',
        title: 'Tout ce qu’il vous faut pour livrer en toute sérénité',
        subtitle:
            'Une suite complète de services logistiques conçue pour les e-commerçants et les entreprises marocaines.',
        more: 'En savoir plus',
        items: {
            pickup: {
                title: 'Ramassage',
                description:
                    'Nos livreurs récupèrent vos colis directement dans votre boutique ou entrepôt, selon votre planning.',
            },
            express: {
                title: 'Livraison Express',
                description:
                    'Livraison en 24h dans les grandes villes du Maroc pour satisfaire vos clients les plus exigeants.',
            },
            national: {
                title: 'Livraison Nationale',
                description:
                    'Expédiez partout au Maroc, de Tanger à Laâyoune, grâce à un réseau logistique national.',
            },
            cod: {
                title: 'Paiement à la livraison',
                description:
                    'Encaissement COD sécurisé et reversement rapide, avec un suivi précis de chaque transaction.',
            },
            returns: {
                title: 'Gestion des retours',
                description:
                    'Traitement automatique des retours clients de façon simple, tracée et sans friction.',
            },
            platform: {
                title: 'Plateforme Web',
                description:
                    'Pilotez toute votre logistique depuis une seule plateforme moderne et intuitive.',
            },
        },
    },

    stats: {
        parcels: 'Colis / mois',
        success: 'Livraisons réussies',
        companies: 'Entreprises',
        tracking: 'Suivi en temps réel',
        satisfaction: 'Satisfaction client',
    },

    platform: {
        eyebrow: 'Plateforme',
        title: 'Une plateforme intelligente pour gérer toute votre logistique',
        subtitle:
            'Centralisez commandes, livreurs, paiements et retours. Prenez des décisions plus rapidement grâce à des données claires en temps réel.',
        cta: 'Découvrir la plateforme',
        features: {
            orders: 'Gestion des commandes',
            tracking: 'Suivi temps réel (GPS)',
            drivers: 'Gestion des livreurs',
            cod: 'Paiements COD',
            history: 'Historique complet',
            notifications: 'Notifications instantanées',
            billing: 'Facturation & reversement',
            qr: 'QR Codes & scan',
        },
        mock: {
            orders: 'Commandes',
            delivered: 'Livrées',
            notifications: 'Notifications',
            newOrder: 'Nouvelle commande',
            parcelDelivered: 'Colis livré · COD 240 DH',
            transferInTransit: 'Transfert en transit',
        },
    },

    process: {
        eyebrow: 'Comment ça marche',
        title: 'De la commande au paiement, en 5 étapes',
        subtitle:
            'Un processus fluide et entièrement traçable, du premier clic jusqu’au reversement.',
        steps: {
            order: { title: 'Créer la commande', description: 'Ajoutez vos colis en quelques secondes.' },
            pickup: { title: 'Ramassage', description: 'Un livreur récupère vos colis.' },
            transit: { title: 'Transit', description: 'Acheminement entre nos hubs.' },
            delivery: { title: 'Livraison', description: 'Remise au client final.' },
            payment: { title: 'Paiement', description: 'Encaissement COD & reversement.' },
        },
    },

    coverage: {
        eyebrow: 'Zones couvertes',
        title: 'Nous livrons partout au Maroc',
        subtitle:
            'De Tanger à Laâyoune, nos équipes couvrent l’ensemble du territoire national avec un tarif clair pour chaque ville.',
        national: 'Couverture nationale',
        mapHint: 'Survolez une ville pour afficher son tarif de livraison.',
        stats: {
            cities: 'Villes couvertes',
            sectors: 'Secteurs de livraison',
            regions: 'Régions du Maroc',
        },
        from: 'À partir de',
        currency: 'DH',
        delay: 'Délai',
        allCities: 'Toutes les villes couvertes',
    },

    pricing: {
        eyebrow: 'Grille tarifaire',
        title: 'Des tarifs simples et transparents',
        subtitle:
            'Le prix de livraison est affiché ville par ville, sans frais cachés. Le paiement à la livraison est inclus.',
        priceRange: 'Livraison de {min} à {max} DH selon la ville',
        table: {
            city: 'Ville',
            region: 'Région',
            delay: 'Délai de livraison',
            price: 'Prix de livraison',
        },
        search: 'Rechercher une ville…',
        noResult: 'Aucune ville ne correspond à votre recherche.',
        included: {
            title: 'Inclus dans chaque livraison',
            items: {
                pickup: 'Ramassage chez vous',
                cod: 'Encaissement à la livraison',
                tracking: 'Suivi en temps réel',
                attempts: 'Deuxième tentative de livraison',
                support: 'Support dédié',
            },
        },
        note: 'Tarifs indicatifs par colis standard. Un tarif sur mesure est proposé au-delà de 200 colis par mois.',
        cta: 'Ouvrir un compte',
    },

    testimonials: {
        eyebrow: 'Ils nous font confiance',
        title: 'Ce que nos clients disent de SpeedZone',
        subtitle: 'Des centaines d’entreprises marocaines nous confient leurs livraisons chaque jour.',
        items: {
            first: {
                name: 'Yassine El Amrani',
                company: 'Boutique Mode Rabat',
                comment:
                    'Depuis que nous utilisons SpeedZone, nos livraisons sont plus rapides et nos clients beaucoup plus satisfaits. Le suivi en temps réel change tout.',
            },
            second: {
                name: 'Salma Bennani',
                company: 'E-commerce Kénitra',
                comment:
                    'La plateforme est vraiment simple et le service COD est fiable. On gère toutes nos commandes et reversements sans stress au quotidien.',
            },
            third: {
                name: 'Karim Tazi',
                company: 'Parapharmacie Gharb',
                comment:
                    'Les reversements COD sont rapides et transparents. Une équipe réactive et des livreurs toujours professionnels. Je recommande vivement.',
            },
            fourth: {
                name: 'Nabila Chraibi',
                company: 'Cosmétiques Salé',
                comment:
                    'Enfin un partenaire logistique qui comprend le e-commerce marocain. La gestion des retours nous fait gagner un temps précieux.',
            },
        },
    },

    cta: {
        badge: 'Prêt à accélérer ?',
        title: 'Prêt à améliorer vos livraisons ?',
        text: 'Rejoignez les centaines d’entreprises qui font déjà confiance à SpeedZone pour livrer plus vite et plus sereinement.',
        primary: 'Créer un compte gratuitement',
        dashboard: 'Ouvrir le tableau de bord',
        secondary: 'Nous contacter',
    },

    footer: {
        tagline: 'Livraison rapide. Confiance assurée.',
        description: 'La plateforme de livraison la plus fiable au Maroc, partout sur le territoire national.',
        columns: {
            navigation: 'Navigation',
            services: 'Services',
            support: 'Support',
            contact: 'Contact',
        },
        support: {
            help: 'Centre d’aide',
            faq: 'FAQ',
            terms: 'Conditions générales',
            privacy: 'Confidentialité',
            contact: 'Nous contacter',
        },
        follow: 'Suivez-nous sur Instagram',
        rights: '© {year} SpeedZone. Tous droits réservés.',
        terms: 'Conditions générales',
        privacy: 'Politique de confidentialité',
    },

    tracking: {
        placeholder: 'Entrez votre numéro de suivi',
        inputLabel: 'Numéro de suivi',
        submit: 'Suivre',
        pageTitle: 'Suivi de colis {number} | SpeedZone',
        eyebrow: 'Suivi de colis',
        title: 'Suivez votre colis en temps réel',
        number: 'Numéro de suivi',
        destination: 'Destination : {city}',
        empty: 'Aucun historique disponible pour le moment.',
        notFoundTitle: 'Numéro introuvable.',
        notFoundText: 'Aucun colis ne correspond au numéro « {number} ». Vérifiez le numéro et réessayez.',
        back: 'Retour à l’accueil',
    },

    statuses: {
        CREATED: 'Créée',
        AWAITING_PREPARATION: 'En attente de préparation',
        PREPARED: 'Préparée',
        PICKUP_REQUESTED: 'Ramassage demandé',
        WAITING_PICKUP: 'En attente de ramassage',
        PICKED_UP: 'Ramassée',
        IN_DEPOT: 'Au dépôt',
        TRANSFER_CREATED: 'Transfert créé',
        IN_TRANSIT: 'En transit',
        RECEIVED_IN_DESTINATION: 'Reçue à destination',
        IN_DELIVERY_CITY: 'Dans la ville de livraison',
        OUT_FOR_DELIVERY: 'En cours de livraison',
        DELIVERED: 'Livrée',
        FAILED: 'Échec de livraison',
        REJECTED: 'Refusée',
        CANCELED: 'Annulée',
        READY_TO_RETURN: 'Prête au retour',
        RETURN_REQUESTED: 'Retour demandé',
        RETURN_IN_PROGRESS: 'Retour en cours',
        RETURNED: 'Retournée',
    },

    cities: {},

    regions: {},
};
