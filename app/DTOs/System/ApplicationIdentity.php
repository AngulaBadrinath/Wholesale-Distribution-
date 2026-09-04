<?php

namespace App\DTOs\System;

use JsonSerializable;

readonly class ApplicationIdentity implements JsonSerializable
{
    public function __construct(
        public string $name,
        public string $company_name,
        public string $tagline,
        public string $support_email,
        public string $support_phone,
        public string $logo_path,
        public string $favicon_path,
        public string $footer_text,
    ) {}

    /**
     * Convert the identity value object to an array for serialization and frontend delivery.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'company_name' => $this->company_name,
            'tagline' => $this->tagline,
            'support_email' => $this->support_email,
            'support_phone' => $this->support_phone,
            'logo_path' => $this->logo_path,
            'favicon_path' => $this->favicon_path,
            'footer_text' => $this->footer_text,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
