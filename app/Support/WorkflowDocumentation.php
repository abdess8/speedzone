<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Enums\ReturnStatus;
use App\Enums\TransferContentType;

/**
 * Payload behind the Help Center's process viewer.
 *
 * Everything here is derived from the enums the application actually enforces,
 * rather than retyped into a CMS. Documentation that is generated from the
 * rules cannot describe a workflow the code no longer runs.
 */
class WorkflowDocumentation
{
    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return [
            'flows' => [
                self::successFlow(),
                self::failureFlow(),
            ],
            'matrices' => [
                'orders' => OrderStatus::matrix(),
                'returns' => ReturnStatus::matrix(),
            ],
            'contentTypes' => TransferContentType::options(),
            'billing' => self::billing(),
        ];
    }

    /**
     * Order placed → parcel delivered → seller paid.
     *
     * @return array<string, mixed>
     */
    private static function successFlow(): array
    {
        $steps = array_map(self::orderStep(...), OrderStatus::successPath());

        // Invoicing is not an order status — it happens on the invoice, once the
        // order is delivered — but leaving it off would end the story one step
        // before the part the seller actually cares about.
        $steps[] = [
            'key' => 'INVOICED',
            'kind' => 'billing',
            'label' => __('help.flows.success.invoiced.label'),
            'description' => __('help.flows.success.invoiced.description'),
            'actor' => __('help.flows.success.invoiced.actor'),
            'color' => 'success',
            'icon' => 'ri-bill-line',
        ];

        return [
            'key' => 'success',
            'title' => __('help.flows.success.title'),
            'summary' => __('help.flows.success.summary'),
            'tone' => 'success',
            'steps' => $steps,
        ];
    }

    /**
     * Delivery fails → the six-step reverse logistics workflow brings the parcel
     * back to its seller.
     *
     * @return array<string, mixed>
     */
    private static function failureFlow(): array
    {
        return [
            'key' => 'failure',
            'title' => __('help.flows.failure.title'),
            'summary' => __('help.flows.failure.summary'),
            'tone' => 'danger',
            'steps' => array_map(self::orderStep(...), OrderStatus::failurePath()),
            'branch' => [
                'title' => __('help.flows.failure.branch_title'),
                'summary' => __('help.flows.failure.branch_summary'),
                'tone' => 'warning',
                'steps' => array_map(self::returnStep(...), ReturnStatus::pipeline()),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function orderStep(OrderStatus $status): array
    {
        return [
            'key' => $status->value,
            'kind' => 'order',
            'label' => $status->label(),
            'description' => $status->description(),
            'actor' => $status->actorLabel(),
            'color' => $status->color(),
            'icon' => $status->icon(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function returnStep(ReturnStatus $status): array
    {
        return [
            'key' => $status->value,
            'kind' => 'return',
            'step' => $status->step(),
            'label' => $status->label(),
            'description' => $status->description(),
            'actor' => $status->actorLabel(),
            'color' => $status->color(),
            'icon' => $status->icon(),
        ];
    }

    /**
     * How money moves once a parcel stops moving.
     *
     * @return array<string, mixed>
     */
    private static function billing(): array
    {
        return [
            'seller' => [
                'title' => __('help.billing.seller.title'),
                'summary' => __('help.billing.seller.summary'),
                'formula' => [
                    ['label' => __('help.billing.seller.collected'), 'sign' => null, 'tone' => 'success'],
                    ['label' => __('help.billing.seller.delivery_fees'), 'sign' => '−', 'tone' => 'danger'],
                    ['label' => __('help.billing.seller.return_fees'), 'sign' => '−', 'tone' => 'danger'],
                    ['label' => __('help.billing.seller.payout'), 'sign' => '=', 'tone' => 'primary'],
                ],
                'notes' => [
                    __('help.billing.seller.note_delivered_only'),
                    __('help.billing.seller.note_returns'),
                    __('help.billing.seller.note_frequency'),
                ],
            ],
            'driver' => [
                'title' => __('help.billing.driver.title'),
                'summary' => __('help.billing.driver.summary'),
                'formula' => [
                    ['label' => __('help.billing.driver.collected'), 'sign' => null, 'tone' => 'success'],
                    ['label' => __('help.billing.driver.commission'), 'sign' => '−', 'tone' => 'danger'],
                    ['label' => __('help.billing.driver.due'), 'sign' => '=', 'tone' => 'primary'],
                ],
                'notes' => [
                    __('help.billing.driver.note_discharge'),
                    __('help.billing.driver.note_settlement'),
                ],
            ],
        ];
    }
}
