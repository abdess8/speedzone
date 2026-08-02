<?php

namespace Database\Seeders\Support;

use App\Enums\SupportObjectType;
use App\Enums\SupportTicketCategory;
use Database\Seeders\MoroccanDatasetSeeder;

/**
 * Realistic claim threads used by {@see MoroccanDatasetSeeder}.
 *
 * Each template is a complete conversation: the seller's opening message plus
 * the replies exchanged with the support desk. Roughly one template in five is
 * written in Arabic. Placeholders are resolved by the seeder:
 *
 *  - `{tracking}`  order tracking number
 *  - `{city}`      delivery city
 *  - `{customer}`  customer full name
 *  - `{reference}` pickup / invoice reference
 *  - `{amount}`    amount in dirhams
 */
class SupportTicketTemplates
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function latin(): array
    {
        return [
            [
                'category' => SupportTicketCategory::DELIVERY_DELAY,
                'object' => SupportObjectType::ORDER,
                'subject' => 'Retard de livraison {city}',
                'message' => "Bonjour,\nLe colis {tracking} destiné à {customer} est bloqué depuis 4 jours à {city}. Le client commence à s'impatienter et menace de refuser la commande. Merci de vérifier avec le livreur.",
                'replies' => [
                    ['from' => 'support', 'body' => 'Bonjour, merci pour votre message. Le colis est bien arrivé au dépôt de {city}, il a été rattaché à la tournée du jour. Nous relançons le livreur maintenant.'],
                    ['from' => 'seller', 'body' => 'Merci. Le client est joignable uniquement après 17h, pouvez-vous transmettre cette information au livreur ?'],
                    ['from' => 'support', 'body' => "C'est noté sur la fiche de tournée. Livraison prévue demain entre 17h et 19h.", 'attachment' => 'bon_livraison.pdf'],
                    ['from' => 'seller', 'body' => 'Parfait, je confirme au client. Merci pour la réactivité.'],
                ],
                'attachments' => ['capture_suivi_colis.png'],
            ],
            [
                'category' => SupportTicketCategory::OTHER,
                'object' => SupportObjectType::ORDER,
                'subject' => 'Colis endommagé',
                'message' => "Bonjour,\nLe client {customer} a reçu le colis {tracking} avec l'emballage déchiré et le produit cassé. Il refuse de payer. Je joins les photos envoyées par le client.",
                'replies' => [
                    ['from' => 'support', 'body' => 'Bonjour, nous ouvrons une enquête auprès du dépôt de {city}. Pouvez-vous confirmer que le colis portait bien la mention « fragile » au dépôt ?', 'attachment' => null],
                    ['from' => 'seller', 'body' => 'Oui, la case fragile était cochée à la création de la commande.', 'attachment' => 'photo_colis_abime.jpg'],
                    ['from' => 'support', 'body' => 'Dossier confirmé : les frais de livraison de cette commande seront annulés sur votre prochaine facture et le colis part en retour.'],
                ],
                'attachments' => ['photo_colis_abime.jpg', 'preuve_signature.png'],
            ],
            [
                'category' => SupportTicketCategory::CHANGE_INFORMATION,
                'object' => SupportObjectType::ORDER,
                'subject' => "Changement d'adresse client",
                'message' => "Bonjour,\nLe client de la commande {tracking} a déménagé. Nouvelle adresse : Hay Riad, Secteur 9, Immeuble F, Appt 3 — {city}. Merci de mettre à jour avant la tournée.",
                'replies' => [
                    ['from' => 'support', 'body' => 'Bonjour, adresse mise à jour sur la commande. Le secteur de livraison passe de Centre Ville à Hay Riad, les frais restent identiques.'],
                    ['from' => 'seller', 'body' => 'Merci beaucoup, le client est prévenu.'],
                ],
                'attachments' => [],
            ],
            [
                'category' => SupportTicketCategory::CALCULATION_ERROR,
                'object' => SupportObjectType::INVOICE,
                'subject' => 'Montant encaissé incorrect',
                'message' => "Bonjour,\nSur la facture {reference}, la commande {tracking} est comptée à {amount} DH alors que le client a payé le montant complet au livreur. Merci de corriger le versement.",
                'replies' => [
                    ['from' => 'support', 'body' => 'Bonjour, nous vérifions la décharge de caisse du livreur pour cette journée.'],
                    ['from' => 'seller', 'body' => 'Je joins le reçu remis au client par le livreur.', 'attachment' => 'recu_paiement.pdf'],
                    ['from' => 'support', 'body' => 'Écart confirmé côté caisse livreur. Un avoir de {amount} DH sera ajouté à votre prochain versement.'],
                    ['from' => 'seller', 'body' => 'Reçu, merci. Je clôture de mon côté.'],
                ],
                'attachments' => ['recu_paiement.pdf'],
            ],
            [
                'category' => SupportTicketCategory::STATUS_CHANGE,
                'object' => SupportObjectType::ORDER,
                'subject' => 'Demande de retour rapide',
                'message' => "Bonjour,\nLe client de la commande {tracking} ne répond plus depuis 3 tentatives. Merci de lancer le retour vers mon dépôt sans attendre le délai habituel.",
                'replies' => [
                    ['from' => 'support', 'body' => 'Bonjour, retour créé et rattaché à la commande. Le colis part au dépôt de {city} ce soir.'],
                    ['from' => 'seller', 'body' => 'Merci. Pouvez-vous me confirmer les frais de retour appliqués ?'],
                    ['from' => 'support', 'body' => 'Les frais de retour du secteur seront appliqués sur la facture de la période en cours.'],
                ],
                'attachments' => [],
            ],
            [
                'category' => SupportTicketCategory::PICKUP_ISSUE,
                'object' => SupportObjectType::PICKUP_REQUEST,
                'subject' => 'Ramassage non effectué',
                'message' => "Bonjour,\nLe ramassage {reference} était prévu hier matin, personne n'est passé à la boutique. J'ai 8 colis prêts qui attendent.",
                'replies' => [
                    ['from' => 'support', 'body' => 'Bonjour, le livreur du secteur était en congé. Le ramassage est replanifié aujourd\'hui avant 13h.'],
                    ['from' => 'seller', 'body' => 'Merci, je préviens mon équipe pour préparer les colis à l\'accueil.'],
                    ['from' => 'support', 'body' => 'Ramassage effectué, les 8 colis sont au dépôt de {city}.'],
                ],
                'attachments' => ['photo_colis_prets.jpg'],
            ],
            [
                'category' => SupportTicketCategory::PAYMENT_DELAY,
                'object' => SupportObjectType::INVOICE,
                'subject' => 'Versement en retard',
                'message' => "Bonjour,\nLa facture {reference} est marquée comme envoyée depuis 6 jours mais je n'ai reçu aucun virement sur mon RIB. Merci de vérifier.",
                'replies' => [
                    ['from' => 'support', 'body' => 'Bonjour, le virement a été émis hier soir. Le délai interbancaire est de 24 à 48h.'],
                    ['from' => 'seller', 'body' => 'Pouvez-vous me transmettre le reçu du virement pour ma comptabilité ?'],
                    ['from' => 'support', 'body' => 'Voici le reçu de paiement.', 'attachment' => 'recu_virement.pdf'],
                ],
                'attachments' => ['releve_bancaire.pdf'],
            ],
            [
                'category' => SupportTicketCategory::INVOICE_ISSUE,
                'object' => SupportObjectType::INVOICE,
                'subject' => 'Facture non reçue par email',
                'message' => "Bonjour,\nLa facture {reference} apparaît dans mon espace mais je ne l'ai pas reçue par email et je n'arrive pas à télécharger le PDF.",
                'replies' => [
                    ['from' => 'support', 'body' => 'Bonjour, le PDF a été régénéré, le téléchargement fonctionne à nouveau depuis la fiche facture.'],
                    ['from' => 'seller', 'body' => 'Confirmé, je l\'ai récupéré. Merci.'],
                ],
                'attachments' => [],
            ],
            [
                'category' => SupportTicketCategory::INFORMATION_REQUEST,
                'object' => SupportObjectType::ORDER,
                'subject' => 'Demande de preuve de livraison',
                'message' => "Bonjour,\nLe client de la commande {tracking} affirme n'avoir jamais reçu son colis alors qu'il est marqué livré. Merci de m'envoyer la preuve de livraison.",
                'replies' => [
                    ['from' => 'support', 'body' => 'Bonjour, voici la preuve de livraison signée récupérée auprès du livreur.', 'attachment' => 'preuve_signature.png'],
                    ['from' => 'seller', 'body' => 'Merci, la signature correspond bien au client. Dossier classé.'],
                ],
                'attachments' => ['preuve_signature.png'],
            ],
            [
                'category' => SupportTicketCategory::DELIVERY_DELAY,
                'object' => SupportObjectType::ORDER,
                'subject' => 'Colis bloqué au transfert vers {city}',
                'message' => "Bonjour,\nLa commande {tracking} est en transit depuis 5 jours vers {city}. Le bordereau n'a jamais été reçu à destination.",
                'replies' => [
                    ['from' => 'support', 'body' => 'Bonjour, le camion a été immobilisé. Le bordereau est réceptionné aujourd\'hui à {city}.'],
                    ['from' => 'seller', 'body' => 'Merci de prioriser la livraison, le client a déjà payé en ligne.'],
                    ['from' => 'support', 'body' => 'Commande placée en tête de tournée pour demain matin.'],
                ],
                'attachments' => [],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function arabic(): array
    {
        return [
            [
                'category' => SupportTicketCategory::DELIVERY_DELAY,
                'object' => SupportObjectType::ORDER,
                'subject' => 'تأخر في تسليم الطرد بمدينة {city}',
                'message' => "السلام عليكم،\nالطرد {tracking} الخاص بالزبون {customer} متوقف منذ أربعة أيام بمدينة {city}. الزبون غير راض ويهدد برفض الطلب. المرجو التحقق مع الموزع.",
                'replies' => [
                    ['from' => 'support', 'body' => 'وعليكم السلام، الطرد موجود بمستودع {city} وتمت إضافته إلى جولة اليوم. سنتصل بالموزع الآن.'],
                    ['from' => 'seller', 'body' => 'شكرا. الزبون متوفر فقط بعد الخامسة مساء، المرجو إبلاغ الموزع.'],
                    ['from' => 'support', 'body' => 'تم تدوين الملاحظة في ورقة الجولة. التسليم غدا بين الخامسة والسابعة مساء.'],
                ],
                'attachments' => ['صورة_الطرد.jpg'],
            ],
            [
                'category' => SupportTicketCategory::CALCULATION_ERROR,
                'object' => SupportObjectType::INVOICE,
                'subject' => 'مبلغ محصل غير صحيح',
                'message' => "السلام عليكم،\nفي الفاتورة {reference}، الطلب {tracking} محسوب بمبلغ {amount} درهم، لكن الزبون أدى المبلغ كاملا للموزع. المرجو تصحيح الوصل.",
                'replies' => [
                    ['from' => 'support', 'body' => 'وعليكم السلام، نتحقق حاليا من صندوق الموزع الخاص بذلك اليوم.'],
                    ['from' => 'seller', 'body' => 'أرفقت وصل الأداء الذي سلمه الموزع للزبون.', 'attachment' => 'recu_paiement.pdf'],
                    ['from' => 'support', 'body' => 'تم تأكيد الفرق. سيضاف مبلغ {amount} درهم إلى الوصل المقبل.'],
                    ['from' => 'seller', 'body' => 'شكرا جزيلا على التعاون.'],
                ],
                'attachments' => ['recu_paiement.pdf'],
            ],
            [
                'category' => SupportTicketCategory::CHANGE_INFORMATION,
                'object' => SupportObjectType::ORDER,
                'subject' => 'تغيير عنوان الزبون',
                'message' => "السلام عليكم،\nزبون الطلب {tracking} غير عنوانه. العنوان الجديد: حي الرياض، القطاع 9، عمارة و، شقة 3 — {city}. المرجو التحديث قبل الجولة.",
                'replies' => [
                    ['from' => 'support', 'body' => 'وعليكم السلام، تم تحديث العنوان على الطلب. رسوم التوصيل تبقى كما هي.'],
                    ['from' => 'seller', 'body' => 'شكرا، تم إبلاغ الزبون.'],
                ],
                'attachments' => [],
            ],
            [
                'category' => SupportTicketCategory::STATUS_CHANGE,
                'object' => SupportObjectType::ORDER,
                'subject' => 'طلب إرجاع سريع للطرد',
                'message' => "السلام عليكم،\nزبون الطلب {tracking} لا يجيب على الهاتف بعد ثلاث محاولات. المرجو إرجاع الطرد إلى المستودع دون انتظار المدة المعتادة.",
                'replies' => [
                    ['from' => 'support', 'body' => 'وعليكم السلام، تم إنشاء طلب الإرجاع وربطه بالطلب. الطرد سيتوجه إلى مستودع {city} هذا المساء.'],
                    ['from' => 'seller', 'body' => 'المرجو تأكيد رسوم الإرجاع المطبقة.'],
                    ['from' => 'support', 'body' => 'رسوم الإرجاع الخاصة بالقطاع ستطبق في فاتورة الفترة الحالية.'],
                ],
                'attachments' => [],
            ],
            [
                'category' => SupportTicketCategory::PICKUP_ISSUE,
                'object' => SupportObjectType::PICKUP_REQUEST,
                'subject' => 'لم يتم القيام بعملية الجمع',
                'message' => "السلام عليكم،\nطلب الجمع {reference} كان مبرمجا صباح أمس ولم يحضر أحد إلى المتجر. لدي ثمانية طرود جاهزة في الانتظار.",
                'replies' => [
                    ['from' => 'support', 'body' => 'وعليكم السلام، موزع القطاع كان في رخصة. تمت إعادة برمجة الجمع اليوم قبل الواحدة زوالا.'],
                    ['from' => 'seller', 'body' => 'شكرا، سأخبر الفريق بتحضير الطرود في الاستقبال.'],
                    ['from' => 'support', 'body' => 'تم الجمع، الطرود الثمانية موجودة بمستودع {city}.'],
                ],
                'attachments' => ['صورة_الطرود.jpg'],
            ],
        ];
    }
}
