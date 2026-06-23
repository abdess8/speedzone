<?php

test('orders create and new route is registered', function () {
    expect(route('orders.store-and-new'))->toContain('/orders/create-and-new');
});

test('orders create route accepts clone query parameter', function () {
    expect(route('orders.create', ['clone' => 12]))->toContain('clone=12');
});
