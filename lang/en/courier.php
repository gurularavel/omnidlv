<?php

return [
    'otp_sms' => 'OmniDLV sign-in code: :code. Do not share it with anyone.',
    'otp_expired' => 'The code has expired. Ask for a new one.',
    'otp_wrong' => 'Wrong code. :left tries left.',
    'not_a_courier' => 'This number is not registered as a courier. Ask your dispatcher to add you.',
    'delivery_code_sms' => ':company: your order is on the way. Give the courier this code: :code. Track: :url',
    'errors' => [
        'shift_has_tasks' => 'Finish or hand back your active tasks before ending the shift.',
        'unknown_action' => 'This action is not available.',
        'not_your_task' => 'This task is assigned to another courier.',
        'not_your_team' => 'This order belongs to another team.',
        'photo_required' => 'Take the proof photo first.',
        'code_required' => 'Enter the recipient\'s code first.',
        'no_code' => 'This order has no delivery code.',
        'code_locked' => 'Too many wrong codes. Call the dispatcher.',
    ],
    'deletion_requested' => 'Request received. Your company will delete your account within 30 days.',
    'push' => [
        'assigned_title' => 'New task',
        'assigned_body' => 'Address: :address',
        'cancelled_title' => 'Task cancelled',
        'cancelled_body' => ':code is no longer yours.',
    ],
];
