<?php

return [
    'types' => [
        'drop_off' => 'Çatdırılma',
        'pickup' => 'Götürmə',
    ],
    'assignment' => [
        'pool' => 'Hovuz: kuryerlər özü götürür',
        'capacity_aware' => 'Avtomatik, boş tutuma görə',
        'capacity_unaware' => 'Avtomatik, ən yaxın kuryer',
        'slot' => 'Marşrut planlı vaxt slotu',
        'selected_courier' => 'Seçdiyim kuryer',
    ],
    'errors' => [
        'final_status' => 'Tapşırıq bitib, artıq dəyişdirilə bilməz.',
        'invalid_transition' => 'Tapşırıq ":from" statusundan ":to" statusuna keçə bilməz.',
        'locked' => 'Tapşırıq dispetçer tərəfindən kilidlənib.',
        'courier_required' => 'Bu statusdan əvvəl kuryer təyin edin.',
        'not_a_courier' => 'Bu şəxs şirkətin aktiv kuryeri deyil.',
        'already_claimed' => 'Bu tapşırığı artıq başqa kuryer götürüb.',
        'capacity_exceeded' => 'Kuryerin tutumu doludur (limit :limit).',
        'ends_before_starts' => 'Son tarix başlanğıcdan sonra olmalıdır.',
        'destination_required' => 'Alıcının ünvanını əlavə edin.',
        'address_unresolved' => 'Bu ünvanı xəritədə tapa bilmədik. Yoxlayın, koordinat əlavə edin və ya nöqtəsiz saxlamağa icazə verin.',
        'duplicate' => ':id nömrəli sifarişlə tapşırıq artıq var.',
    ],
];
