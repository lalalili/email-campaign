<?php

use Lalalili\EmailCampaign\Actions\RenderEmailTemplateAction;

beforeEach(function () {
    $this->action = new RenderEmailTemplateAction();
});

it('replaces a basic placeholder', function () {
    $result = $this->action->execute('Hello {{ user_name }}!', ['user_name' => 'Alice']);
    expect($result)->toBe('Hello Alice!');
});

it('replaces multiple different placeholders', function () {
    $result = $this->action->execute(
        'Hi {{ user_name }}, your code is {{ coupon_code }}.',
        ['user_name' => 'Bob', 'coupon_code' => 'SAVE20'],
    );
    expect($result)->toBe('Hi Bob, your code is SAVE20.');
});

it('replaces the same placeholder appearing multiple times', function () {
    $result = $this->action->execute(
        '{{ name }} loves {{ name }}.',
        ['name' => 'Laravel'],
    );
    expect($result)->toBe('Laravel loves Laravel.');
});

it('renders missing variable as empty string', function () {
    $result = $this->action->execute('Hello {{ ghost }}!', []);
    expect($result)->toBe('Hello !');
});

it('records missing variable keys', function () {
    $missing = [];
    $this->action->execute('{{ a }} and {{ b }}', ['a' => 'ok'], false, $missing);
    expect($missing)->toBe(['b']);
});

it('records each missing key only once even when placeholder repeats', function () {
    $missing = [];
    $this->action->execute('{{ x }} and {{ x }}', [], false, $missing);
    expect($missing)->toHaveCount(1)->toBe(['x']);
});

it('treats null value as missing', function () {
    $missing = [];
    $result = $this->action->execute('{{ val }}', ['val' => null], false, $missing);
    expect($result)->toBe('')
        ->and($missing)->toBe(['val']);
});

it('accepts placeholders with underscores and dots', function () {
    $result = $this->action->execute(
        '{{ user_name }} {{ survey.url }}',
        ['user_name' => 'Eve', 'survey.url' => 'https://example.com'],
    );
    expect($result)->toBe('Eve https://example.com');
});

it('casts non-string scalar values to string', function () {
    $result = $this->action->execute('Score: {{ score }}', ['score' => 42]);
    expect($result)->toBe('Score: 42');
});

it('returns empty string for non-scalar (array) values and records as missing', function () {
    $missing = [];
    $result = $this->action->execute('{{ items }}', ['items' => ['a', 'b']], false, $missing);
    expect($result)->toBe('')
        ->and($missing)->toContain('items');
});

it('does not escape HTML when escape=false', function () {
    $result = $this->action->execute('{{ html }}', ['html' => '<b>bold</b>'], false);
    expect($result)->toBe('<b>bold</b>');
});

it('escapes HTML entities when escape=true', function () {
    $result = $this->action->execute('{{ html }}', ['html' => '<script>alert(1)</script>'], true);
    expect($result)->toBe('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('renders configured html link variables as blank target links', function () {
    $missing = [];

    $result = $this->action->execute(
        '請填寫 {{ survey_url }}',
        ['survey_url' => 'https://example.com/survey?t=abc&x=1'],
        true,
        $missing,
        ['survey_url'],
    );

    expect($result)->toBe('請填寫 <a href="https://example.com/survey?t=abc&amp;x=1" target="_blank" rel="noopener noreferrer">https://example.com/survey?t=abc&amp;x=1</a>')
        ->and($missing)->toBe([]);
});

it('does not render non-url html link variables as links', function () {
    $missing = [];

    $result = $this->action->execute(
        '{{ survey_url }}',
        ['survey_url' => '<script>alert(1)</script>'],
        true,
        $missing,
        ['survey_url'],
    );

    expect($result)->toBe('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($missing)->toBe([]);
});

it('does not execute injected code (template is not evaluated)', function () {
    // The value is returned as a literal string; PHP tags are NOT executed.
    $result = $this->action->execute('{{ php }}', ['php' => '<?php echo "hacked"; ?>']);
    expect($result)->toBe('<?php echo "hacked"; ?>');
});

it('returns empty string unchanged', function () {
    $result = $this->action->execute('', ['key' => 'value']);
    expect($result)->toBe('');
});

it('returns plain text unchanged when no placeholders', function () {
    $result = $this->action->execute('No placeholders here.', ['key' => 'value']);
    expect($result)->toBe('No placeholders here.');
});

it('handles placeholders with extra whitespace inside braces', function () {
    $result = $this->action->execute('{{  user_name  }}', ['user_name' => 'Charlie']);
    expect($result)->toBe('Charlie');
});
