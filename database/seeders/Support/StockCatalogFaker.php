<?php

namespace Database\Seeders\Support;

use Database\Seeders\StockDatasetSeeder;

/**
 * Catalog content generator used by {@see StockDatasetSeeder}.
 *
 * Products are drawn from families that mirror what Moroccan e-commerce sellers
 * actually ship through us — cosmetics, phone accessories, ready-to-wear — with
 * plausible selling prices, purchase costs and shipping weights, because the
 * stock screens display margins and the pick-list totals real amounts. A dataset
 * of "Product 1 … Product 40" at 100 MAD would exercise the code but tell a
 * reviewer nothing about whether the numbers look right.
 */
class StockCatalogFaker
{
    /**
     * Product families: name, price floor, price ceiling, fragile, weight (g).
     *
     * The price ceiling stays above the floor by enough that two units of the
     * same family rarely carry the same price, which keeps order totals varied.
     *
     * @var array<string, array<int, array{0: string, 1: int, 2: int, 3: bool, 4: int}>>
     */
    private const FAMILIES = [
        'Cosmétique' => [
            ['Huile d\'argan bio 100 ml', 145, 220, true, 280],
            ['Savon noir beldi 250 g', 45, 75, false, 300],
            ['Ghassoul naturel 500 g', 55, 90, false, 550],
            ['Eau de rose de Kelâat M\'Gouna 200 ml', 60, 110, true, 320],
            ['Sérum vitamine C 30 ml', 180, 320, true, 120],
            ['Masque capillaire kératine 300 ml', 95, 165, false, 350],
            ['Crème hydratante karité 150 ml', 85, 140, false, 200],
            ['Huile de figue de barbarie 30 ml', 240, 420, true, 95],
        ],
        'Électronique' => [
            ['Écouteurs Bluetooth TWS', 190, 380, true, 180],
            ['Chargeur rapide 20W USB-C', 90, 160, false, 140],
            ['Batterie externe 10 000 mAh', 180, 320, false, 320],
            ['Câble tressé USB-C 2 m', 45, 85, false, 90],
            ['Montre connectée sport', 320, 690, true, 260],
            ['Enceinte Bluetooth étanche', 220, 480, true, 540],
            ['Support téléphone voiture magnétique', 65, 120, false, 150],
            ['Souris sans fil silencieuse', 95, 180, false, 110],
        ],
        'Prêt-à-porter' => [
            ['T-shirt coton peigné', 79, 149, false, 220],
            ['Sweat à capuche molletonné', 189, 320, false, 620],
            ['Jean slim stretch', 220, 390, false, 700],
            ['Caftan moderne brodé', 450, 980, false, 850],
            ['Robe midi plissée', 240, 420, false, 380],
            ['Chemise lin manches longues', 180, 310, false, 300],
            ['Pantalon chino', 190, 330, false, 520],
        ],
        'Accessoires' => [
            ['Sac à main cuir grainé', 320, 690, false, 780],
            ['Ceinture cuir véritable', 120, 220, false, 240],
            ['Portefeuille cuir RFID', 140, 260, false, 180],
            ['Lunettes de soleil polarisées', 150, 320, true, 130],
            ['Foulard soie imprimé', 110, 210, false, 90],
            ['Sac à dos urbain 20 L', 240, 450, false, 620],
        ],
        'Décoration' => [
            ['Lanterne marocaine ciselée', 180, 340, true, 900],
            ['Plateau zellige 40 cm', 220, 420, true, 1400],
            ['Tapis berbère 80 × 150 cm', 480, 1200, false, 2600],
            ['Théière traditionnelle inox', 160, 290, false, 780],
            ['Verres à thé gravés (lot de 6)', 120, 230, true, 1100],
            ['Bougie parfumée ambre', 75, 140, true, 380],
        ],
        'Sport & Fitness' => [
            ['Tapis de yoga antidérapant 6 mm', 150, 280, false, 1200],
            ['Élastiques de résistance (lot de 5)', 90, 170, false, 420],
            ['Corde à sauter roulement à billes', 65, 120, false, 210],
            ['Gourde isotherme 750 ml', 110, 200, false, 340],
            ['Gants de musculation cuir', 95, 180, false, 190],
        ],
        'Puériculture' => [
            ['Biberon anti-colique 260 ml', 75, 130, true, 180],
            ['Tapis d\'éveil matelassé', 240, 420, false, 900],
            ['Thermomètre frontal infrarouge', 180, 320, true, 140],
            ['Lot de 3 bavoirs coton bio', 65, 120, false, 150],
        ],
        'Épicerie fine' => [
            ['Miel d\'euphorbe 500 g', 220, 420, true, 700],
            ['Amlou aux amandes 300 g', 95, 170, true, 400],
            ['Safran de Taliouine 1 g', 180, 300, false, 25],
            ['Dattes Mejhoul premium 1 kg', 140, 260, false, 1050],
            ['Huile d\'olive extra vierge 750 ml', 110, 190, true, 950],
        ],
    ];

    /**
     * GS1 country prefix issued to Morocco, so the generated barcodes look like
     * the ones a vendor reads off his own packaging.
     */
    private const GS1_MOROCCO_PREFIX = '611';

    public function __construct(
        private readonly MoroccanLocaleFaker $faker,
    ) {}

    /**
     * A catalog for one shop.
     *
     * Two thirds of the references come from the shop's own trade and the rest
     * from neighbouring families, which is what a real catalog looks like once a
     * seller starts widening his offer.
     *
     * @return array<int, array<string, mixed>> Payloads accepted by ProductService::create().
     */
    public function catalog(string $shopCategory, int $count): array
    {
        $own = self::FAMILIES[$shopCategory] ?? [];
        $others = [];

        foreach (self::FAMILIES as $family => $items) {
            if ($family !== $shopCategory) {
                $others[] = [$family, $items];
            }
        }

        $products = [];
        $usedNames = [];

        // Own trade first, in listed order: a shop carries its core range whole
        // rather than a random sample of it.
        foreach ($own as [$name, $floor, $ceiling, $fragile, $weight]) {
            if (count($products) >= (int) ceil($count * 0.66)) {
                break;
            }

            $products[] = $this->product($name, $shopCategory, $floor, $ceiling, $fragile, $weight);
            $usedNames[$name] = true;
        }

        // Then fill up from the other families, skipping repeats.
        for ($guard = 0; count($products) < $count && $guard < $count * 20; $guard++) {
            [$family, $items] = $others[array_rand($others)];
            [$name, $floor, $ceiling, $fragile, $weight] = $items[array_rand($items)];

            if (isset($usedNames[$name])) {
                continue;
            }

            $products[] = $this->product($name, $family, $floor, $ceiling, $fragile, $weight);
            $usedNames[$name] = true;
        }

        return $products;
    }

    /**
     * @return array<string, mixed>
     */
    private function product(
        string $name,
        string $category,
        int $floor,
        int $ceiling,
        bool $fragile,
        int $weight,
    ): array {
        $price = $this->price($floor, $ceiling);

        return [
            'name' => $name,
            // A quarter of the references are left without one so the generated
            // dataset also exercises SkuGenerator, as a real import does.
            'sku' => random_int(1, 100) <= 75 ? $this->sku($name, $category) : null,
            'barcode' => random_int(1, 100) <= 80 ? $this->barcode() : null,
            'category' => $category,
            'description' => $this->description($name, $category),
            'unit_price' => $price,
            // Retail margins here sit between 35% and 60% of the selling price.
            'cost_price' => round($price * (random_int(40, 65) / 100), 2),
            'is_fragile' => $fragile,
            'weight_grams' => max(10, (int) round($weight * (random_int(85, 115) / 100))),
            'is_active' => random_int(1, 100) <= 94,
        ];
    }

    /**
     * Round the price the way a shop actually displays it: 149, 189, 249.
     */
    private function price(int $floor, int $ceiling): float
    {
        $raw = random_int($floor, $ceiling);

        if ($raw < 100) {
            return (float) (int) (round($raw / 5) * 5);
        }

        return (float) ((int) (floor($raw / 10) * 10) + 9);
    }

    /**
     * Vendor-style reference: three letters of the family, three of the product,
     * then a serial — the shape sellers key in themselves.
     */
    private function sku(string $name, string $category): string
    {
        $stem = fn (string $value, int $length): string => strtoupper(substr(
            (string) preg_replace('/[^A-Za-z0-9]/', '', $this->faker->slug($value)),
            0,
            $length
        ));

        return sprintf('%s-%s-%03d', $stem($category, 3), $stem($name, 3), random_int(1, 999));
    }

    /**
     * EAN-13 with a valid check digit, so scanning a seeded label works.
     */
    private function barcode(): string
    {
        $body = self::GS1_MOROCCO_PREFIX.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        $sum = 0;

        foreach (str_split($body) as $position => $digit) {
            $sum += (int) $digit * ($position % 2 === 0 ? 1 : 3);
        }

        return $body.((10 - $sum % 10) % 10);
    }

    private function description(string $name, string $category): ?string
    {
        if (random_int(1, 100) > 70) {
            return null;
        }

        return $this->faker->pick([
            "{$name} — {$category}. Stock déposé dans notre entrepôt de Casablanca.",
            "{$name}. Article vendu à l'unité, emballage d'origine.",
            "{$name} — référence best-seller, rotation rapide.",
            "{$name}. Prévoir un emballage renforcé pour l'expédition.",
        ]);
    }

    /**
     * Note a vendor writes on a shipment slip.
     */
    public function sendingNote(): ?string
    {
        if (random_int(1, 100) > 65) {
            return null;
        }

        return $this->faker->pick([
            'Deux cartons scellés, palette n°'.random_int(1, 12).'.',
            'Colis déposé par notre transporteur, '.random_int(1, 6).' cartons.',
            'Réassort mensuel, merci de vérifier les quantités à la réception.',
            'Articles fragiles sur le dessus du carton.',
            'Envoi partiel, le reste suivra la semaine prochaine.',
        ]);
    }

    /**
     * Note a receiving agent writes once the parcel is counted.
     */
    public function receptionNote(int $rejected): ?string
    {
        if ($rejected > 0) {
            return $this->faker->pick([
                "{$rejected} article(s) endommagé(s) lors du transport.",
                "{$rejected} article(s) rejeté(s) : emballage ouvert à l'arrivée.",
                "{$rejected} unité(s) écartée(s), produit non conforme au bordereau.",
            ]);
        }

        if (random_int(1, 100) > 55) {
            return null;
        }

        return $this->faker->pick([
            'Colis conforme au bordereau, mis en rayon.',
            'Comptage effectué à la réception, aucun écart.',
            'Réception validée, cartons en bon état.',
        ]);
    }
}
