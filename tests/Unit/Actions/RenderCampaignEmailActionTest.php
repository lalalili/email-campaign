<?php

use Illuminate\Container\Container;
use Lalalili\EmailCampaign\Actions\BuildVariableMapAction;
use Lalalili\EmailCampaign\Actions\RenderCampaignEmailAction;
use Lalalili\EmailCampaign\Actions\RenderEmailTemplateAction;
use Lalalili\EmailCampaign\Contracts\VariableProvider;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Support\VariableProviderRegistry;

it('renders survey url as a blank target link in html only', function (): void {
    $campaign = new EmailCampaign([
        'name' => 'Survey campaign',
        'subject_template' => '請填寫 {{ survey_url }}',
        'html_template' => '<p>請填寫 {{ survey_url }}</p>',
        'text_template' => '請填寫 {{ survey_url }}',
    ]);

    $recipient = new EmailCampaignRecipient([
        'email' => 'member@example.com',
    ]);

    $registry = new VariableProviderRegistry(new Container());
    $registry->register(new class () implements VariableProvider {
        public function variablesFor(EmailCampaign $campaign, EmailCampaignRecipient $recipient): array
        {
            return ['survey_url' => 'https://example.com/survey?t=abc&x=1'];
        }
    });

    $rendered = (new RenderCampaignEmailAction(
        new BuildVariableMapAction($registry),
        new RenderEmailTemplateAction(),
    ))->execute($campaign, $recipient);

    expect($rendered->subject)->toBe('請填寫 https://example.com/survey?t=abc&x=1')
        ->and($rendered->html)->toBe('<p>請填寫 <a href="https://example.com/survey?t=abc&amp;x=1" target="_blank" rel="noopener noreferrer">https://example.com/survey?t=abc&amp;x=1</a></p>')
        ->and($rendered->text)->toBe('請填寫 https://example.com/survey?t=abc&x=1');
});
