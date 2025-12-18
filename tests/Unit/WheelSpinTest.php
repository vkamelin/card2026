<?php

declare(strict_types=1);

use App\Models\WheelSpin;
use PHPUnit\Framework\TestCase;

final class WheelSpinTest extends TestCase
{
    public function testConstructor(): void
    {
        $spin = new WheelSpin(
            1,
            123456,
            1,
            'Test Prize',
            'test_value',
            'TEST1234',
            '2025-01-01 00:00:00',
            '2025-01-02 00:00:00',
            true,
            1,
            '192.168.1.1',
            'Mozilla/5.0'
        );
        
        $this->assertSame(1, $spin->id);
        $this->assertSame(123456, $spin->telegramUserId);
        $this->assertSame(1, $spin->prizeId);
        $this->assertSame('Test Prize', $spin->prizeName);
        $this->assertSame('test_value', $spin->prizeValue);
        $this->assertSame('TEST1234', $spin->promoCode);
        $this->assertSame('2025-01-01 00:00:00', $spin->spunAt);
        $this->assertSame('2025-01-02 00:00:00', $spin->expiresAt);
        $this->assertTrue($spin->isWinning);
        $this->assertSame(1, $spin->customPrizeId);
        $this->assertSame('192.168.1.1', $spin->ipAddress);
        $this->assertSame('Mozilla/5.0', $spin->userAgent);
    }
    
    public function testIsWinningSpin(): void
    {
        $winningSpin = new WheelSpin(
            1,
            123456,
            1,
            'Test Prize',
            'test_value',
            'TEST1234',
            '2025-01-01 00:00:00',
            '2025-01-02 00:00:00',
            true,
            null,
            null,
            null
        );
        
        $this->assertTrue($winningSpin->isWinningSpin());
        
        $losingSpin = new WheelSpin(
            2,
            123456,
            2,
            'Consolation Prize',
            null,
            null,
            '2025-01-01 00:00:00',
            null,
            false,
            null,
            null,
            null
        );
        
        $this->assertFalse($losingSpin->isWinningSpin());
    }
    
    public function testHasCustomPrize(): void
    {
        $customSpin = new WheelSpin(
            1,
            123456,
            1,
            'Custom Prize',
            null,
            null,
            '2025-01-01 00:00:00',
            null,
            true,
            5,
            null,
            null
        );
        
        $this->assertTrue($customSpin->hasCustomPrize());
        
        $regularSpin = new WheelSpin(
            2,
            123456,
            2,
            'Regular Prize',
            '10',
            'TEST1234',
            '2025-01-01 00:00:00',
            '2025-01-02 00:00:00',
            true,
            null,
            null,
            null
        );
        
        $this->assertFalse($regularSpin->hasCustomPrize());
    }
}