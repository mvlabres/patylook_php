<?php

declare(strict_types=1);

namespace Patylook\Models;

/** The seller whose WhatsApp the public showcase links to. */
final class MainSeller
{
    /** Brazil's country code, added when the stored number has only area code and number. */
    private const COUNTRY_CODE = '55';

    public function __construct(
        public int $id = 0,
        public string $name = '',
        public string $whatsapp = '',
    ) {
    }

    /**
     * The number in the international form wa.me needs: digits only, with country code. A Brazilian
     * number registered without it (area code + 8 or 9 digits) gets 55. Null when nothing usable is stored.
     */
    public function whatsappDigits(): ?string
    {
        $digits = preg_replace('/\D+/', '', $this->whatsapp) ?? '';
        if ($digits === '') {
            return null;
        }
        return strlen($digits) <= 11 ? self::COUNTRY_CODE . $digits : $digits;
    }

    /**
     * wa.me link that opens a chat with the seller, text already filled in: it goes to the
     * WhatsApp app on phones and tablets and to WhatsApp Web / Desktop elsewhere.
     */
    public function chatUrl(string $message): ?string
    {
        $digits = $this->whatsappDigits();
        return $digits === null ? null : 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
    }

    /** The message that starts the conversation about a product. */
    public static function productMessage(string $productName, string $sku): string
    {
        return 'Olá. Gostaria de saber mais detalhes do produto ' . $productName . ' - ' . $sku . '.';
    }
}
