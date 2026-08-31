<?php

namespace Database\Seeders\Support;

use Database\Seeders\MoroccanDatasetSeeder;

/**
 * Moroccan content generator used by {@see MoroccanDatasetSeeder}.
 *
 * Roughly one record out of five is emitted in Arabic script (names, streets,
 * notes), the rest in French / Latin transliteration — the mix the platform
 * actually receives from Moroccan sellers. Arabic records are sometimes fully
 * Arabic and sometimes mixed ("محمد Alami"), which is what real address books
 * look like here.
 */
class MoroccanLocaleFaker
{
    /** Share of records emitted in Arabic script. */
    public const ARABIC_SHARE = 20;

    /** @var array<int, string> */
    private array $latinFirstNames = [
        'Mohamed', 'Youssef', 'Ahmed', 'Hamza', 'Omar', 'Ayoub', 'Yassine', 'Bilal',
        'Khalid', 'Anas', 'Reda', 'Mehdi', 'Othmane', 'Zakaria', 'Soufiane', 'Ismail',
        'Abdelilah', 'Karim', 'Hicham', 'Nabil', 'Rachid', 'Tarik', 'Jawad', 'Marouane',
        'Fatima', 'Khadija', 'Salma', 'Imane', 'Sara', 'Nadia', 'Hajar', 'Meryem',
        'Asmae', 'Hanane', 'Loubna', 'Ghita', 'Oumaima', 'Chaimae', 'Nada', 'Rim',
        'Zineb', 'Amal', 'Siham', 'Aicha', 'Ilham', 'Malika', 'Wafae', 'Btissam',
    ];

    /** @var array<int, string> */
    private array $latinLastNames = [
        'Alaoui', 'Bennani', 'El Amrani', 'Idrissi', 'Tazi', 'Cherkaoui', 'Bouazza',
        'El Fassi', 'Berrada', 'Lahlou', 'Sbai', 'Naciri', 'Saidi', 'Ouahbi',
        'Mansouri', 'Chraibi', 'Benjelloun', 'El Khattabi', 'Boukhris', 'Zniber',
        'Hilali', 'Daoudi', 'Sekkat', 'Bargach', 'El Ghazali', 'Smires', 'Belkacem',
        'Ait Benhaddou', 'El Moutawakil', 'Tahiri', 'Kabbaj', 'Amrani', 'Rifai',
    ];

    /** @var array<int, string> */
    private array $arabicFirstNames = [
        'محمد', 'يوسف', 'أحمد', 'حمزة', 'عمر', 'أيوب', 'ياسين', 'بلال',
        'خالد', 'أنس', 'رضى', 'المهدي', 'عثمان', 'زكرياء', 'سفيان', 'إسماعيل',
        'عبد الإله', 'كريم', 'هشام', 'نبيل', 'رشيد', 'طارق',
        'فاطمة', 'خديجة', 'سلمى', 'إيمان', 'سارة', 'نادية', 'هاجر', 'مريم',
        'أسماء', 'حنان', 'لبنى', 'غيثة', 'أميمة', 'شيماء', 'ندى', 'ريم',
        'زينب', 'أمل', 'سهام', 'عائشة', 'إلهام', 'مليكة', 'وفاء',
    ];

    /** @var array<int, string> */
    private array $arabicLastNames = [
        'العلوي', 'بناني', 'العمراني', 'الإدريسي', 'التازي', 'الشرقاوي', 'بوعزة',
        'الفاسي', 'برادة', 'لحلو', 'السباعي', 'الناصري', 'السعيدي', 'الوهبي',
        'المنصوري', 'الشرايبي', 'بنجلون', 'الخطابي', 'بوخريص', 'زنيبر',
        'الهلالي', 'الداودي', 'السقاط', 'الغزالي', 'بلقاسم', 'الطاهري', 'القباج',
    ];

    /** @var array<int, string> */
    private array $latinStreets = [
        'Rue de Fès, Quartier Maarif',
        'Avenue Mohammed V, Centre Ville',
        'Boulevard Zerktouni, Résidence Al Andalous',
        'Hay Riad, Secteur 12, Immeuble C, Appt 4',
        'Résidence Al Manar, Immeuble B, Appartement 8',
        'Lotissement Ennassim, Villa 24',
        'Rue Ibn Battouta, N° 37, Étage 2',
        'Avenue Hassan II, près de la gare routière',
        'Quartier Industriel, Zone 3, Local 12',
        'Hay Al Qods, Bloc 7, N° 142',
        'Rue Tarik Ibn Ziad, Immeuble Yasmine',
        'Boulevard Anfa, Résidence Racine, Appt 15',
        'Lotissement Riad Salam, N° 56',
        'Derb Loubila, Rue 9, N° 21',
        'Avenue des FAR, Immeuble El Baraka, 3ème étage',
        'Rue Oued Sebou, Villa 8, en face de la pharmacie',
        'Complexe Al Firdaous, Bâtiment D, Appt 22',
    ];

    /** @var array<int, string> */
    private array $arabicStreets = [
        'حي السلام، زنقة 4، رقم 12',
        'شارع محمد الخامس، عمارة النور، الطابق 2',
        'حي المسيرة، بلوك ب، رقم 47',
        'تجزئة النسيم، فيلا 24',
        'درب غلف، زنقة 8، رقم 15',
        'حي الأمل، عمارة 3، الطابق الثاني، شقة 6',
        'شارع الحسن الثاني، قرب المحطة الطرقية',
        'حي القدس، بلوك 7، رقم 142',
        'حي الفتح، زنقة الزرقطوني، رقم 9',
        'إقامة الياسمين، عمارة د، شقة 22',
        'حي الرياض، القطاع 12، عمارة ج',
        'زنقة ابن بطوطة، رقم 37، الطابق الأول',
        'حي المحمدي، درب لوبيلا، رقم 21',
        'تجزئة رياض السلام، رقم 56، أمام الصيدلية',
    ];

    /** @var array<int, string> */
    private array $latinNotes = [
        'Appeler le client avant la livraison.',
        'Livraison après 18h de préférence.',
        'Colis fragile, manipuler avec soin.',
        'Laisser chez le concierge si le client est absent.',
        'Le client demande un paiement par TPE si possible.',
        'Adresse difficile à trouver, appeler en arrivant.',
        'Ne pas livrer le vendredi entre 12h et 15h.',
        'Client déjà informé de la date de livraison.',
        'Vérifier la taille avant de repartir.',
        null,
        null,
    ];

    /** @var array<int, string> */
    private array $arabicNotes = [
        'الرجاء الاتصال قبل الوصول.',
        'التسليم بعد السادسة مساء.',
        'الطرد قابل للكسر، الرجاء الانتباه.',
        'إذا كان الزبون غائبا، الرجاء ترك الطرد عند الحارس.',
        'العنوان قريب من الصيدلية، الرجاء الاتصال عند الوصول.',
        'الزبون يفضل الدفع عند التسليم.',
        'الرجاء عدم التسليم يوم الجمعة بين الزوال والثالثة.',
        'تم إخبار الزبون بموعد التسليم.',
    ];

    /** @var array<string, string> Arabic name of each city served, bare of any VILLE / REGION qualifier. */
    private array $arabicCities = [
        'AGADIR' => 'أكادير',
        'AL HOCEIMA' => 'الحسيمة',
        'BENI MELLAL' => 'بني ملال',
        'BERRECHID' => 'برشيد',
        'CASABLANCA' => 'الدار البيضاء',
        'EL JADIDA' => 'الجديدة',
        'FES' => 'فاس',
        'KENITRA' => 'القنيطرة',
        'KHENIFRA' => 'خنيفرة',
        'KHOURIBGA' => 'خريبكة',
        'LAAYOUNE' => 'العيون',
        'MARRAKECH' => 'مراكش',
        'MEKNES' => 'مكناس',
        'NADOR' => 'الناظور',
        'OUARZAZATE' => 'ورزازات',
        'OUJDA' => 'وجدة',
        'RABAT' => 'الرباط',
        'SAFI' => 'آسفي',
        'SALE' => 'سلا',
        'SETTAT' => 'سطات',
        'TANGER' => 'طنجة',
        'TEMARA' => 'تمارة',
    ];

    /** @var array<int, string> */
    private array $banks = [
        'Attijariwafa Bank', 'Banque Populaire', 'Bank of Africa (BMCE)', 'CIH Bank',
        'Crédit Agricole du Maroc', 'Société Générale Maroc', 'BMCI', 'Al Barid Bank',
        'Crédit du Maroc', 'CFG Bank',
    ];

    /** @var array<int, string> */
    private array $cinPrefixes = ['A', 'AB', 'B', 'BE', 'BH', 'BK', 'C', 'CD', 'D', 'EE', 'J', 'K', 'X', 'Z'];

    /** @var array<int, array{0: string, 1: string}> Latin shop name + category. */
    private array $latinShops = [
        ['Atlas Cosmétique', 'Cosmétique'],
        ['Nova Mode', 'Prêt-à-porter'],
        ['Souk Digital', 'Électronique'],
        ['Bab Store', 'Accessoires'],
        ['Maroc Fit', 'Sport & Fitness'],
        ['Zellige Deco', 'Décoration'],
        ['Argan Beauty', 'Cosmétique'],
        ['Kids Corner', 'Puériculture'],
        ['Menara Phone', 'Téléphonie'],
        ['Chic Bazar', 'Prêt-à-porter'],
        ['Perle Bijoux', 'Bijouterie'],
        ['Terroir Maroc', 'Épicerie fine'],
    ];

    /** @var array<int, array{0: string, 1: string}> Arabic shop name + category. */
    private array $arabicShops = [
        ['متجر النخبة', 'ملابس'],
        ['بازار الأصالة', 'ديكور'],
        ['دار العطور', 'عطور'],
        ['سوق الأناقة', 'أحذية'],
        ['واحة الجمال', 'مستحضرات التجميل'],
        ['ركن الهدايا', 'هدايا'],
    ];

    /** Number of records emitted so far, used to hold the Arabic quota. */
    private int $cursor = 0;

    /** Position of the Arabic record inside the current block. */
    private int $arabicSlot = 0;

    /**
     * True for exactly one record out of five, at a random position inside each
     * block, so the Arabic share is guaranteed rather than merely probable.
     */
    public function arabic(): bool
    {
        $block = (int) (100 / self::ARABIC_SHARE);
        $position = $this->cursor % $block;

        if ($position === 0) {
            $this->arabicSlot = random_int(0, $block - 1);
        }

        $this->cursor++;

        return $position === $this->arabicSlot;
    }

    /**
     * A Moroccan person. Arabic records are 60% fully Arabic and 40% mixed
     * (Arabic given name + transliterated family name).
     *
     * @return array{first_name: string, last_name: string, arabic: bool}
     */
    public function person(?bool $arabic = null): array
    {
        $arabic ??= $this->arabic();

        if (! $arabic) {
            return [
                'first_name' => $this->pick($this->latinFirstNames),
                'last_name' => $this->pick($this->latinLastNames),
                'arabic' => false,
            ];
        }

        return [
            'first_name' => $this->pick($this->arabicFirstNames),
            'last_name' => random_int(1, 100) <= 60
                ? $this->pick($this->arabicLastNames)
                : $this->pick($this->latinLastNames),
            'arabic' => true,
        ];
    }

    /**
     * A full customer record for an order delivered in the given city.
     *
     * @return array{first_name: string, last_name: string, phone: string, address: string, notes: ?string, arabic: bool}
     */
    public function customer(string $cityName, ?bool $arabic = null): array
    {
        $arabic ??= $this->arabic();
        $person = $this->person($arabic);

        return [
            'first_name' => $person['first_name'],
            'last_name' => $person['last_name'],
            'phone' => $this->phone(),
            'address' => $this->address($cityName, $arabic),
            'notes' => $this->note($arabic),
            'arabic' => $arabic,
        ];
    }

    public function address(string $cityName, bool $arabic = false): string
    {
        return $arabic
            ? $this->pick($this->arabicStreets).'، '.$this->cityName($cityName, true)
            : $this->pick($this->latinStreets).', '.$cityName;
    }

    public function note(bool $arabic = false): ?string
    {
        return $arabic ? $this->pick($this->arabicNotes) : $this->pick($this->latinNotes);
    }

    /**
     * The coverage grid splits a metropolis in two — "TANGER VILLE" for the
     * boroughs, "TANGER REGION" for the towns around it — so the Arabic name is
     * looked up on the bare city and the outskirts marked with نواحي.
     */
    public function cityName(string $cityName, bool $arabic = false): string
    {
        if (! $arabic) {
            return $cityName;
        }

        $bare = trim(str_ireplace(['REGION', 'VILLE'], '', $cityName));
        $translated = $this->arabicCities[mb_strtoupper($bare)] ?? null;

        if ($translated === null) {
            return $cityName;
        }

        return stripos($cityName, 'REGION') === false ? $translated : 'نواحي '.$translated;
    }

    /**
     * @return array{0: string, 1: string} Shop name and its category.
     */
    public function shop(bool $arabic = false): array
    {
        return $arabic ? $this->pick($this->arabicShops) : $this->pick($this->latinShops);
    }

    public function bank(): string
    {
        return $this->pick($this->banks);
    }

    /**
     * Moroccan mobile number: 06xxxxxxxx or 07xxxxxxxx.
     */
    public function phone(): string
    {
        return '0'.$this->pick(['6', '7']).str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    public function cin(): string
    {
        return $this->pick($this->cinPrefixes).random_int(100000, 999999);
    }

    public function rib(): string
    {
        $rib = '';
        for ($i = 0; $i < 24; $i++) {
            $rib .= random_int(0, 9);
        }

        return $rib;
    }

    public function iceNumber(): string
    {
        return (string) random_int(100000000000000, 999999999999999);
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    public function pick(array $items)
    {
        return $items[array_rand($items)];
    }

    /**
     * Latin slug usable in an email address or a city code.
     */
    public function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii));

        return trim($slug, '-');
    }
}
