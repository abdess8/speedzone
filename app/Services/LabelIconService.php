<?php

namespace App\Services;

class LabelIconService
{
    /**
     * Inline SVG icons used by the shipping label, keyed by name.
     *
     * They are returned as data URIs because dompdf only resolves SVG through
     * <img> sources, not through inline markup.
     *
     * @return array<string, string>
     */
    public function labelIcons(string $color = '#16334F', string $mutedColor = '#7C8DA3'): array
    {
        return [
            'speed' => $this->dataUri($this->speed($color)),
            'speed_muted' => $this->dataUri($this->speed($mutedColor)),
            'pin' => $this->dataUri($this->pin($color)),
            'cash' => $this->dataUri($this->cash($color)),
            'card' => $this->dataUri($this->card($color)),
        ];
    }

    private function dataUri(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function speed(string $color): string
    {
        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 68 40" width="68" height="40">
            <g fill="none" stroke="{$color}" stroke-width="4.5">
                <path d="M2 9 H24"/>
                <path d="M2 20 H17"/>
                <path d="M2 31 H24"/>
                <circle cx="45" cy="20" r="16"/>
                <path d="M45 20 L55 11"/>
            </g>
        </svg>
        SVG;
    }

    private function pin(string $color): string
    {
        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 32" width="24" height="32">
            <path d="M12 1.5 C6.2 1.5 1.5 6.2 1.5 12 C1.5 20.2 12 30.5 12 30.5 C12 30.5 22.5 20.2 22.5 12 C22.5 6.2 17.8 1.5 12 1.5 Z"
                  fill="none" stroke="{$color}" stroke-width="2.4"/>
            <circle cx="12" cy="12" r="4" fill="{$color}"/>
        </svg>
        SVG;
    }

    private function cash(string $color): string
    {
        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 26" width="40" height="26">
            <rect x="1.6" y="1.6" width="36.8" height="22.8" rx="3" fill="none" stroke="{$color}" stroke-width="3"/>
            <circle cx="20" cy="13" r="5.6" fill="none" stroke="{$color}" stroke-width="3"/>
            <path d="M7 13 H9"/>
            <path d="M31 13 H33"/>
        </svg>
        SVG;
    }

    private function card(string $color): string
    {
        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 26" width="40" height="26">
            <rect x="1.6" y="1.6" width="36.8" height="22.8" rx="3" fill="none" stroke="{$color}" stroke-width="3"/>
            <rect x="1.6" y="7.5" width="36.8" height="5" fill="{$color}"/>
            <rect x="6" y="17" width="10" height="3" fill="{$color}"/>
        </svg>
        SVG;
    }
}
