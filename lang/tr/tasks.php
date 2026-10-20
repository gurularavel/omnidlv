<?php

return [
    'types' => [
        'drop_off' => 'Teslimat',
        'pickup' => 'Alım',
    ],
    'assignment' => [
        'pool' => 'Havuz: kuryeler kendisi alır',
        'capacity_aware' => 'Otomatik, boş kapasiteye göre',
        'capacity_unaware' => 'Otomatik, en yakın kurye',
        'slot' => 'Rota planlı zaman dilimi',
        'selected_courier' => 'Seçtiğim kurye',
    ],
    'errors' => [
        'final_status' => 'Görev tamamlandı ve artık değiştirilemez.',
        'invalid_transition' => 'Görev ":from" durumundan ":to" durumuna geçemez.',
        'locked' => 'Görev dispeçer tarafından kilitlendi.',
        'courier_required' => 'Bu durumdan önce kurye atayın.',
        'not_a_courier' => 'Bu kişi şirketin aktif kuryesi değil.',
        'already_claimed' => 'Bu görevi başka bir kurye zaten aldı.',
        'capacity_exceeded' => 'Kuryenin kapasitesi dolu (sınır :limit).',
        'ends_before_starts' => 'Son tarih başlangıçtan sonra olmalıdır.',
        'destination_required' => 'Alıcı adresini ekleyin.',
        'address_unresolved' => 'Bu adres haritada bulunamadı. Kontrol edin, koordinat ekleyin veya noktasız kaydetmeye izin verin.',
        'duplicate' => ':id numaralı siparişle görev zaten var.',
    ],
];
