<?php

return [
    'types' => [
        'drop_off' => 'Delivery',
        'pickup' => 'Pickup',
    ],
    'assignment' => [
        'pool' => 'Pool: couriers claim it',
        'capacity_aware' => 'Automatic, by free capacity',
        'capacity_unaware' => 'Automatic, nearest courier',
        'slot' => 'Time slot with route planning',
        'selected_courier' => 'A courier I choose',
    ],
    'errors' => [
        'final_status' => 'The task is finished and can no longer change.',
        'invalid_transition' => 'A task cannot go from ":from" to ":to".',
        'locked' => 'The task is locked by the dispatcher.',
        'courier_required' => 'Assign a courier before this status.',
        'not_a_courier' => 'This person is not an active courier of the company.',
        'already_claimed' => 'Another courier has already taken this task.',
        'capacity_exceeded' => 'The courier is full (limit :limit).',
        'ends_before_starts' => 'The deadline must be after the start.',
        'destination_required' => 'Add the recipient address.',
        'address_unresolved' => 'We could not find this address on the map. Check it, add coordinates, or allow saving without a point.',
        'duplicate' => 'A task with order number :id already exists.',
    ],
];
