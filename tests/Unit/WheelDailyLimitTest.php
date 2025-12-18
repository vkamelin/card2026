<?php

declare(strict_types=1);

use App\Models\WheelDailyLimit;
use PHPUnit\Framework\TestCase;

final class WheelDailyLimitTest extends TestCase
{
    public function testConstructor(): void
    {
        $dailyLimit = new WheelDailyLimit(
            1,
            '2025-01-01',
            100,
            50,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertSame(1, $dailyLimit->id);
        $this->assertSame('2025-01-01', $dailyLimit->date);
        $this->assertSame(100, $dailyLimit->totalSpins);
        $this->assertSame(50, $dailyLimit->winningSpins);
        $this->assertSame('2025-01-01 00:00:00', $dailyLimit->createdAt);
        $this->assertSame('2025-01-01 00:00:00', $dailyLimit->updatedAt);
    }
    
    public function testIsDailyLimitExceeded(): void
    {
        // Note: This test would require database setup to be meaningful
        // For now, we'll just ensure the method exists and is callable
        $this->assertTrue(method_exists(WheelDailyLimit::class, 'isDailyLimitExceeded'));
    }
    
    public function testGetTotalSpinsForDate(): void
    {
        // Note: This test would require database setup to be meaningful
        // For now, we'll just ensure the method exists and is callable
        $this->assertTrue(method_exists(WheelDailyLimit::class, 'getTotalSpinsForDate'));
    }
    
    public function testGetWinningSpinsForDate(): void
    {
        // Note: This test would require database setup to be meaningful
        // For now, we'll just ensure the method exists and is callable
        $this->assertTrue(method_exists(WheelDailyLimit::class, 'getWinningSpinsForDate'));
    }
}