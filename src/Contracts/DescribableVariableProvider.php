<?php

namespace Lalalili\EmailCampaign\Contracts;

/**
 * Optional companion to {@see VariableProvider}: lets a provider advertise the
 * template variables it can supply so the campaign builder can render an
 * "available variables" sidebar. Kept separate from VariableProvider so that
 * existing providers (in this package and consumers such as survey-core) remain
 * valid without implementing it.
 */
interface DescribableVariableProvider
{
    /**
     * Describe the static template variables this provider supplies.
     * Per-recipient dynamic keys (e.g. arbitrary payload fields) may be omitted.
     *
     * @return list<array{key: string, label: string}>
     */
    public function availableVariables(): array;
}
