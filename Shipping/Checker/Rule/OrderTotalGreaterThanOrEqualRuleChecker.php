<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\Component\Core\Shipping\Checker\Rule;

final class OrderTotalGreaterThanOrEqualRuleChecker extends OrderTotalRuleChecker
{
    public const TYPE = 'order_total_greater_than_or_equal';

    protected function compare(int $total, $threshold): bool
    {
        return $total >= $threshold;
    }
}
