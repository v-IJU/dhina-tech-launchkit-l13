<?php

declare(strict_types=1);

namespace App\Modules\Pages\Support;

/**
 * Content blocks available in the page builder.
 *
 * To add a block:
 *   1. add it here (label, icon, fields)
 *   2. create app/Modules/Pages/Views/web/blocks/{type}.blade.php
 * Field types: text, textarea, editor, url, media, select, toggle, number.
 */
class BlockRegistry
{
    /**
     * @return array<string, array{label: string, icon: string, description: string, fields: array<string, array<string, mixed>>}>
     */
    public static function all(): array
    {
        return [
            'hero' => [
                'label' => 'Hero banner', 'icon' => 'bi-window-fullscreen', 'description' => 'Big headline with buttons. Can show the Sliders instead.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading'],
                    'subheading' => ['type' => 'textarea', 'label' => 'Sub-heading'],
                    'button_text' => ['type' => 'text', 'label' => 'Button text'],
                    'button_url' => ['type' => 'text', 'label' => 'Button link'],
                    'secondary_text' => ['type' => 'text', 'label' => 'Second button text'],
                    'secondary_url' => ['type' => 'text', 'label' => 'Second button link'],
                    'image' => ['type' => 'media', 'label' => 'Image'],
                    'use_sliders' => ['type' => 'toggle', 'label' => 'Show the slides from Website CMS › Sliders instead'],
                ],
            ],
            'text' => [
                'label' => 'Rich text', 'icon' => 'bi-text-paragraph', 'description' => 'Free text with headings, lists and links.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading'],
                    'body' => ['type' => 'editor', 'label' => 'Text'],
                ],
            ],
            'image_text' => [
                'label' => 'Image + text', 'icon' => 'bi-layout-split', 'description' => 'Image on one side, text on the other.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading'],
                    'body' => ['type' => 'editor', 'label' => 'Text'],
                    'image' => ['type' => 'media', 'label' => 'Image'],
                    'image_position' => ['type' => 'select', 'label' => 'Image side', 'options' => ['left' => 'Left', 'right' => 'Right']],
                    'button_text' => ['type' => 'text', 'label' => 'Button text'],
                    'button_url' => ['type' => 'text', 'label' => 'Button link'],
                ],
            ],
            'features' => [
                'label' => 'Services grid', 'icon' => 'bi-grid-3x3-gap', 'description' => 'Cards from Website CMS › Services.',
                'fields' => [
                    'eyebrow' => ['type' => 'text', 'label' => 'Small label above heading'],
                    'heading' => ['type' => 'text', 'label' => 'Heading'],
                    'subheading' => ['type' => 'textarea', 'label' => 'Sub-heading'],
                ],
            ],
            'stats' => [
                'label' => 'Numbers', 'icon' => 'bi-123', 'description' => 'A row of key figures.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading'],
                    'items' => ['type' => 'textarea', 'label' => 'Figures', 'hint' => 'One per line as value|label, e.g. 120+|Projects delivered'],
                ],
            ],
            'testimonials' => [
                'label' => 'Testimonials', 'icon' => 'bi-chat-quote', 'description' => 'Quotes from Website CMS › Testimonials.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading'],
                    'subheading' => ['type' => 'textarea', 'label' => 'Sub-heading'],
                ],
            ],
            'faq' => [
                'label' => 'FAQ', 'icon' => 'bi-question-circle', 'description' => 'Accordion from Website CMS › FAQs.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading'],
                    'group' => ['type' => 'text', 'label' => 'Only show this group (optional)'],
                ],
            ],
            'latest_posts' => [
                'label' => 'Latest posts', 'icon' => 'bi-journal-richtext', 'description' => 'Newest blog posts.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading'],
                    'count' => ['type' => 'number', 'label' => 'How many', 'default' => 3],
                ],
            ],
            'cta' => [
                'label' => 'Call to action', 'icon' => 'bi-megaphone', 'description' => 'Coloured band with a heading and a button.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading'],
                    'text' => ['type' => 'textarea', 'label' => 'Text'],
                    'button_text' => ['type' => 'text', 'label' => 'Button text'],
                    'button_url' => ['type' => 'text', 'label' => 'Button link'],
                ],
            ],
            'contact_form' => [
                'label' => 'Contact form', 'icon' => 'bi-envelope-paper', 'description' => 'Saves to the Contact inbox and alerts the team.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading'],
                    'text' => ['type' => 'textarea', 'label' => 'Intro text'],
                ],
            ],
            'map' => [
                'label' => 'Map', 'icon' => 'bi-geo-alt', 'description' => 'Google map. Uses the URL from Settings when empty.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading'],
                    'embed_url' => ['type' => 'url', 'label' => 'Google Maps embed URL'],
                ],
            ],
            'gallery' => [
                'label' => 'Gallery', 'icon' => 'bi-images', 'description' => 'Grid of images.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading'],
                    'images' => ['type' => 'textarea', 'label' => 'Image URLs', 'hint' => 'One per line. Copy URLs from the Media library.'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }
}
