<?php

namespace Tests\Unit;

use App\Enums\StatusCategory;
use App\Support\GeoPoint;
use App\Support\Phone;
use App\Support\Polygon;
use PHPUnit\Framework\TestCase;

class DomainRulesTest extends TestCase
{
    public function test_polygon_contains(): void
    {
        $square = [[40.0, 49.0], [40.0, 50.0], [41.0, 50.0], [41.0, 49.0]];

        $this->assertTrue(Polygon::contains($square, new GeoPoint(40.5, 49.5)));
        $this->assertFalse(Polygon::contains($square, new GeoPoint(41.5, 49.5)));
        $this->assertFalse(Polygon::contains([[40, 49], [41, 50]], new GeoPoint(40.5, 49.5)));
        $this->assertSame(['min_lat' => 40.0, 'max_lat' => 41.0, 'min_lng' => 49.0, 'max_lng' => 50.0], Polygon::bbox($square));
    }

    public function test_geo_point(): void
    {
        $this->assertNull(GeoPoint::tryFrom(0, 0));
        $this->assertNull(GeoPoint::tryFrom(95, 10));
        $this->assertNull(GeoPoint::tryFrom(null, 10));
        $this->assertSame([49.867092, 40.409264], (new GeoPoint(40.4092641, 49.8670924))->toLngLat());

        // Baku → Sumgait is roughly 26 km.
        $d = (new GeoPoint(40.4093, 49.8671))->distanceTo(new GeoPoint(40.5897, 49.6686));
        $this->assertEqualsWithDelta(26000, $d, 1500);
    }

    public function test_status_transitions(): void
    {
        $this->assertTrue(StatusCategory::Pool->canMoveTo(StatusCategory::Assigned));
        $this->assertTrue(StatusCategory::OnDelivery->canMoveTo(StatusCategory::Completed));
        $this->assertFalse(StatusCategory::Created->canMoveTo(StatusCategory::Completed));
        $this->assertFalse(StatusCategory::Completed->canMoveTo(StatusCategory::Failed));
        $this->assertSame([], StatusCategory::Cancelled->allowedNext());
    }

    public function test_phone(): void
    {
        $this->assertSame('+994501234567', Phone::normalize('050 123 45 67', 'AZ'));
        $this->assertSame('+994501234567', Phone::normalize('+994 50 123-45-67', 'TR'));
        $this->assertSame('+905321234567', Phone::normalize('0532 123 45 67', 'TR'));
        $this->assertNull(Phone::normalize('12345', 'AZ'));
        $this->assertSame('+99450***4567', Phone::mask('+994501234567'));
    }
}
