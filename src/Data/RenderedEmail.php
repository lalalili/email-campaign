<?php

namespace Lalalili\EmailCampaign\Data;

readonly class RenderedEmail
{
    /**
     * @param  array<int, string>  $missingVariables  placeholder keys that had no value
     */
    public function __construct(
        public string $subject,
        public ?string $html,
        public ?string $text,
        public array $missingVariables = [],
    ) {
    }

    public function withHtml(?string $html): self
    {
        return new self($this->subject, $html, $this->text, $this->missingVariables);
    }
}
