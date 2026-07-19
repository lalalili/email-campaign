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
        // 缺值語意：解析不到的變數輸出空字串，並記入 $missing 供呼叫端提示作者。
        // 注意 marketing-automation 的 RenderDispatchMessageAction 採相反策略
        // （保留字面 {{var}}，改由 DetectUnresolvedTemplateVarsAction 事前警告）。
        // 兩者刻意不同，修改任一邊前請先確認不是誤以為它們該一致。
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
