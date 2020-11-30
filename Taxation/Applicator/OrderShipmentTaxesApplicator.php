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

namespace Sylius\Component\Core\Taxation\Applicator;

use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Core\Model\TaxRateInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Webmozart\Assert\Assert;

class OrderShipmentTaxesApplicator implements OrderTaxesApplicatorInterface
{
    /** @var CalculatorInterface */
    private $calculator;

    /** @var AdjustmentFactoryInterface */
    private $adjustmentFactory;

    /** @var TaxRateResolverInterface */
    private $taxRateResolver;

    public function __construct(
        CalculatorInterface $calculator,
        AdjustmentFactoryInterface $adjustmentFactory,
        TaxRateResolverInterface $taxRateResolver
    ) {
        $this->calculator = $calculator;
        $this->adjustmentFactory = $adjustmentFactory;
        $this->taxRateResolver = $taxRateResolver;
    }

    public function apply(OrderInterface $order, ZoneInterface $zone): void
    {
        $shippingTotal = $order->getShippingTotal();
        if (0 === $shippingTotal) {
            return;
        }

        $shippingMethod = $this->getShippingMethod($order);

        /** @var TaxRateInterface|null $taxRate */
        $taxRate = $this->taxRateResolver->resolve($shippingMethod, ['zone' => $zone]);
        if (null === $taxRate) {
            return;
        }

        $taxAmount = $this->calculator->calculate($shippingTotal, $taxRate);
        if (0.00 === $taxAmount) {
            return;
        }

        $this->addAdjustment($order, (int) $taxAmount, $taxRate, $shippingMethod);
    }

    private function addAdjustment(
        OrderInterface $order,
        int $taxAmount,
        TaxRateInterface $taxRate,
        ShippingMethodInterface $shippingMethod
    ): void {
        /** @var AdjustmentInterface $shippingTaxAdjustment */
        $shippingTaxAdjustment = $this->adjustmentFactory->createWithData(
            AdjustmentInterface::TAX_ADJUSTMENT,
            $taxRate->getLabel(),
            $taxAmount,
            $taxRate->isIncludedInPrice(),
            [
                'shippingMethodCode' => $shippingMethod->getCode(),
                'shippingMethodName' => $shippingMethod->getName(),
                'taxRateCode' => $taxRate->getCode(),
                'taxRateName' => $taxRate->getName(),
                'taxRateAmount' => $taxRate->getAmount(),
            ]
        );
        $order->addAdjustment($shippingTaxAdjustment);
    }

    /**
     * @throws \LogicException
     */
    private function getShippingMethod(OrderInterface $order): ShippingMethodInterface
    {
        /** @var ShipmentInterface|bool $shipment */
        $shipment = $order->getShipments()->first();
        if (false === $shipment) {
            throw new \LogicException('Order should have at least one shipment.');
        }

        $method = $shipment->getMethod();

        /** @var ShippingMethodInterface $method */
        Assert::isInstanceOf($method, ShippingMethodInterface::class);

        return $method;
    }
}
