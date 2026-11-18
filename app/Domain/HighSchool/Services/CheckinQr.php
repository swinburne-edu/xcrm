<?php

namespace App\Domain\HighSchool\Services;

use App\Domain\HighSchool\Models\SchoolActivity;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * The QR code of a school activity: an SVG that encodes its public check-in link. Uses BaconQrCode (already installed
 * for the two-factor setup), no new package. The SVG has no scripts and is safe to inline or to print.
 */
class CheckinQr
{
    public function svg(SchoolActivity $activity, int $size = 320): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($activity->checkinUrl());
    }
}
