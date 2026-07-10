<?php

namespace App\Services;

use Illuminate\Support\Str;

class WeeklyDocumentFormKnowledgeService
{
    public function formatPromptContext(): string
    {
        $lines = [
            'WEEKLY DOCUMENT FORM REFERENCE (source of truth for plan/report form fields — do not invent fields):',
            'When staff ask what to fill in, which sections exist, what a field means, or what is required, use this reference.',
        ];

        $locationOptions = config('weekly-document-forms.location_options', []);

        foreach (['weekly_plan', 'weekly_report'] as $key) {
            $doc = config('weekly-document-forms.'.$key);
            if (! is_array($doc)) {
                continue;
            }

            $lines[] = '';
            $lines[] = strtoupper((string) ($doc['label'] ?? $key)).':';
            if (! empty($doc['about'])) {
                $lines[] = (string) $doc['about'];
            }
            if (! empty($doc['actions']) && is_array($doc['actions'])) {
                $lines[] = 'Actions: '.implode(', ', $doc['actions']);
            }

            foreach ($doc['sections'] ?? [] as $section) {
                if (! is_array($section)) {
                    continue;
                }
                $title = (string) ($section['title'] ?? 'Section');
                $suffix = ! empty($section['repeatable']) ? ' (repeatable rows)' : '';
                $lines[] = '- '.$title.$suffix.':';

                if (! empty($section['required_on_submit']) && is_string($section['required_on_submit'])) {
                    $lines[] = '  Required on submit: '.$section['required_on_submit'];
                }

                if (! empty($section['notes']) && is_string($section['notes'])) {
                    $lines[] = '  Note: '.$section['notes'];
                }

                foreach ($section['fields'] ?? [] as $field) {
                    if (! is_array($field)) {
                        continue;
                    }
                    $label = (string) ($field['label'] ?? 'Field');
                    $required = ! empty($field['required_on_submit']) ? ' [required on submit]' : '';
                    $parts = ['  · '.$label.$required];

                    $options = $this->resolveFieldOptions($field, $locationOptions);
                    if ($options !== []) {
                        $parts[] = 'options: '.$this->formatOptions($options);
                    }

                    if (! empty($field['notes'])) {
                        $parts[] = (string) $field['notes'];
                    }

                    $lines[] = implode(' — ', $parts);
                }
            }
        }

        return implode("\n", $lines);
    }

    public function questionNeedsFormKnowledge(string $question): bool
    {
        $normalized = Str::lower(trim($question));

        if ($normalized === '') {
            return false;
        }

        $keywords = [
            'what fields',
            'what inputs',
            'required field',
            'what do i fill',
            'what should i fill',
            'which sections',
            'what sections',
            'form fields',
            'plan fields',
            'report fields',
            'what to enter',
            'what to write',
            'progress options',
            'target accomplishment',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return (bool) preg_match(
            '/\b(weekly plan|weekly report)\b.*\b(field|input|section|fill|required)\b|\b(field|input|section|fill|required)\b.*\b(weekly plan|weekly report)\b/',
            $normalized
        );
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  list<string>  $locationOptions
     * @return array<int|string, string>
     */
    private function resolveFieldOptions(array $field, array $locationOptions): array
    {
        if (! empty($field['options']) && is_array($field['options'])) {
            return $field['options'];
        }

        if (($field['options_key'] ?? null) === 'location_options') {
            return $locationOptions;
        }

        return [];
    }

    /**
     * @param  array<int|string, string>  $options
     */
    private function formatOptions(array $options): string
    {
        $parts = [];

        foreach ($options as $value => $label) {
            if (is_int($value)) {
                $parts[] = (string) $label;
            } elseif ((string) $value === (string) $label) {
                $parts[] = (string) $label;
            } else {
                $parts[] = $label.' (stored as '.$value.')';
            }
        }

        return implode('; ', $parts);
    }
}
