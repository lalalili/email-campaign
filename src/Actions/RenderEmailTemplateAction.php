<?php

namespace Lalalili\EmailCampaign\Actions;

class RenderEmailTemplateAction
{
    private const PATTERN = '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_.]*)\s*\}\}/';

    /**
     * Replace {{ key }} placeholders in $template with $variables values.
     *
     * @param  array<string, mixed>  $variables
     * @param  array<int, string>  $missing  populated with keys that had no matching variable
     * @param  array<int, string>  $htmlLinkKeys
     */
    public function execute(
        string $template,
        array $variables,
        bool $escape = false,
        array &$missing = [],
        array $htmlLinkKeys = [],
    ): string {
        return preg_replace_callback(self::PATTERN, function (array $matches) use ($variables, $escape, &$missing, $htmlLinkKeys): string {
            $key = $matches[1];

            $hasSafeValue = array_key_exists($key, $variables)
                && $variables[$key] !== null
                && is_scalar($variables[$key]);

            if (! $hasSafeValue) {
                if (! in_array($key, $missing, true)) {
                    $missing[] = $key;
                }

                return '';
            }

            $value = (string) $variables[$key];

            if ($escape && in_array($key, $htmlLinkKeys, true) && $this->isHttpUrl($value)) {
                $href = e($value);

                return '<a href="'.$href.'" target="_blank" rel="noopener noreferrer">'.$href.'</a>';
            }

            return $escape ? e($value) : $value;
        }, $template) ?? $template;
    }

    private function isHttpUrl(string $value): bool
    {
        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        return in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);
    }
}
