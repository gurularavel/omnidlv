<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

/** System statuses shared by every company. Idempotent: safe to run on every deploy. */
class StatusSeeder extends Seeder
{
    public const STATUSES = [
        // category, slug, notify recipient, [az, en, ru, tr]
        ['created', 'created', false, ['Yaradıldı', 'Created', 'Создан', 'Oluşturuldu']],
        ['pool', 'pool', false, ['Hovuzda', 'In pool', 'В пуле', 'Havuzda']],
        ['assigned', 'assigned', false, ['Təyin edildi', 'Assigned', 'Назначен', 'Atandı']],
        ['assigned', 'accepted', false, ['Kuryer qəbul etdi', 'Accepted by courier', 'Принят курьером', 'Kurye kabul etti']],
        ['in_progress', 'going_to_pickup', false, ['Götürməyə gedir', 'Going to pickup', 'Едет за заказом', 'Teslim almaya gidiyor']],
        ['in_progress', 'processing', true, ['Hazırlanır', 'Processing', 'В обработке', 'İşleniyor']],
        ['in_progress', 'picked_up', false, ['Götürüldü', 'Picked up', 'Забран', 'Teslim alındı']],
        ['on_delivery', 'on_the_way', true, ['Yoldadır', 'On the way', 'В пути', 'Yolda']],
        ['on_delivery', 'arrived', true, ['Ünvana çatdı', 'Arrived', 'Прибыл', 'Adrese ulaştı']],
        ['completed', 'delivered', true, ['Çatdırıldı', 'Delivered', 'Доставлен', 'Teslim edildi']],
        ['completed', 'left_at_door', true, ['Qapıda qoyuldu', 'Left at door', 'Оставлен у двери', 'Kapıya bırakıldı']],
        ['completed', 'delivered_to_other', true, ['Başqa şəxsə təhvil verildi', 'Handed to another person', 'Передан другому лицу', 'Başka kişiye teslim edildi']],
        ['cancelled', 'cancelled', true, ['Ləğv edildi', 'Cancelled', 'Отменён', 'İptal']],
        ['cancelled', 'cancelled_by_recipient', true, ['Müştəri ləğv etdi', 'Cancelled by recipient', 'Отменён клиентом', 'Müşteri iptal etti']],
        ['failed', 'failed', true, ['Uğursuz', 'Failed', 'Неудачно', 'Başarısız']],
        ['failed', 'recipient_not_available', true, ['Müştəri ünvanda deyil', 'Recipient not available', 'Клиента нет на месте', 'Alıcı adreste yok']],
        ['failed', 'wrong_address', true, ['Yanlış ünvan', 'Wrong address', 'Неверный адрес', 'Yanlış adres']],
        ['failed', 'refused', true, ['Müştəri imtina etdi', 'Refused by recipient', 'Клиент отказался', 'Alıcı reddetti']],
        ['failed', 'damaged', false, ['Məhsul zədələnib', 'Damaged', 'Повреждён', 'Hasarlı']],
    ];

    private const COLORS = [
        'created' => '#64748b', 'pool' => '#a855f7', 'assigned' => '#3b82f6', 'in_progress' => '#f59e0b',
        'on_delivery' => '#0ea5e9', 'completed' => '#16a34a', 'cancelled' => '#71717a', 'failed' => '#dc2626',
    ];

    public function run(): void
    {
        foreach (self::STATUSES as $sort => [$category, $slug, $notify, $names]) {
            Status::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => null, 'slug' => $slug],
                [
                    'status_category_slug' => $category,
                    'name' => array_combine(['az', 'en', 'ru', 'tr'], $names),
                    'color' => self::COLORS[$category],
                    'is_system' => true,
                    'is_active' => true,
                    'notify_recipient' => $notify,
                    'sort' => $sort,
                ],
            );
        }
    }
}
