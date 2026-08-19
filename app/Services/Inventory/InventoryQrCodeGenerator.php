<?php

namespace App\Services\Inventory;

use App\Models\InventoryAsset;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class InventoryQrCodeGenerator
{
    public function scanUrl(InventoryAsset $asset): string
    {
        return route('inventory.scan', $asset->asset_code);
    }

    public function pngDataUri(string $data, int $size = 180): string
    {
        $result = (new PngWriter)->write(new QrCode(
            data: $data,
            size: $size,
            margin: 8,
        ));

        return 'data:image/png;base64,'.base64_encode($result->getString());
    }
}
