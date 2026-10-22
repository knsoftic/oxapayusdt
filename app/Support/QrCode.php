<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR codes drawn on our own server as inline SVG.
 */
final class QrCode
{
    /**
     * Inline <svg> for a QR code of the given text (no XML declaration).
     */
    public static function svg(string $text, int $size = 240): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));
        $svg = $writer->writeString($text, Encoder::DEFAULT_BYTE_MODE_ECODING, ErrorCorrectionLevel::M());

        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }
}
