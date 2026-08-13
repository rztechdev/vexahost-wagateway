<?php

namespace App\Services\Providers;

use App\Models\WaSession;
use InvalidArgumentException;

class ProviderManager
{
    /** @var array<string, WhatsAppProvider> */
    private array $resolved = [];

    public function for(WaSession $session): WhatsAppProvider
    {
        return $this->driver($session->driver);
    }

    public function driver(string $name): WhatsAppProvider
    {
        return $this->resolved[$name] ??= match ($name) {
            'wwebjs' => new WwebjsProvider,
            'cloud_api' => new CloudApiProvider,
            'fonnte' => new FonnteProvider,
            default => throw new InvalidArgumentException("Driver WhatsApp tidak dikenal: {$name}"),
        };
    }
}
