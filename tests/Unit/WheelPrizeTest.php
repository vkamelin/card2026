<?php

declare(strict_types=1);

use App\Models\WheelPrize;
use PHPUnit\Framework\TestCase;

final class WheelPrizeTest extends TestCase
{
    public function testConstants(): void
    {
        $this->assertSame('discount', WheelPrize::TYPE_DISCOUNT);
        $this->assertSame('free_item', WheelPrize::TYPE_FREE_ITEM);
        $this->assertSame('free_delivery', WheelPrize::TYPE_FREE_DELIVERY);
        $this->assertSame('consolation', WheelPrize::TYPE_CONSOLATION);
        $this->assertSame('none', WheelPrize::TYPE_NONE);
        $this->assertSame('custom', WheelPrize::TYPE_CUSTOM);
        
        $this->assertContains(WheelPrize::TYPE_DISCOUNT, WheelPrize::VALID_TYPES);
        $this->assertContains(WheelPrize::TYPE_FREE_ITEM, WheelPrize::VALID_TYPES);
        $this->assertContains(WheelPrize::TYPE_FREE_DELIVERY, WheelPrize::VALID_TYPES);
        $this->assertContains(WheelPrize::TYPE_CONSOLATION, WheelPrize::VALID_TYPES);
        $this->assertContains(WheelPrize::TYPE_NONE, WheelPrize::VALID_TYPES);
        $this->assertContains(WheelPrize::TYPE_CUSTOM, WheelPrize::VALID_TYPES);
    }
    
    public function testIsCustom(): void
    {
        $prize = new WheelPrize(
            1,
            'Test Prize',
            'Test Description',
            WheelPrize::TYPE_CUSTOM,
            'test_value',
            0.5,
            true,
            1,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertTrue($prize->isCustom());
    }
    
    public function testIsNone(): void
    {
        $prize = new WheelPrize(
            1,
            'Test Prize',
            'Test Description',
            WheelPrize::TYPE_NONE,
            'test_value',
            0.5,
            true,
            1,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertTrue($prize->isNone());
    }
    
    public function testIsConsolation(): void
    {
        $prize = new WheelPrize(
            1,
            'Test Prize',
            'Test Description',
            WheelPrize::TYPE_CONSOLATION,
            'test_value',
            0.5,
            true,
            1,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertTrue($prize->isConsolation());
    }
    
    public function testIsWinning(): void
    {
        // Test winning prize
        $winningPrize = new WheelPrize(
            1,
            'Test Prize',
            'Test Description',
            WheelPrize::TYPE_DISCOUNT,
            '10',
            0.5,
            true,
            1,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertTrue($winningPrize->isWinning());
        
        // Test consolation prize
        $consolationPrize = new WheelPrize(
            2,
            'Consolation Prize',
            'Test Description',
            WheelPrize::TYPE_CONSOLATION,
            null,
            0.5,
            true,
            2,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertFalse($consolationPrize->isWinning());
        
        // Test none prize
        $nonePrize = new WheelPrize(
            3,
            'None Prize',
            'Test Description',
            WheelPrize::TYPE_NONE,
            null,
            0.5,
            true,
            3,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertFalse($nonePrize->isWinning());
    }
    
    public function testGetDisplayText(): void
    {
        // Test discount prize
        $discountPrize = new WheelPrize(
            1,
            'Discount Prize',
            'Test Description',
            WheelPrize::TYPE_DISCOUNT,
            '10',
            0.5,
            true,
            1,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertSame('Скидка 10%', $discountPrize->getDisplayText());
        
        // Test free item prize
        $freeItemPrize = new WheelPrize(
            2,
            'Free Item Prize',
            'Test Description',
            WheelPrize::TYPE_FREE_ITEM,
            'coffee',
            0.5,
            true,
            2,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertSame('Бесплатный coffee', $freeItemPrize->getDisplayText());
        
        // Test free delivery prize
        $freeDeliveryPrize = new WheelPrize(
            3,
            'Free Delivery Prize',
            'Test Description',
            WheelPrize::TYPE_FREE_DELIVERY,
            null,
            0.5,
            true,
            3,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertSame('Бесплатная доставка', $freeDeliveryPrize->getDisplayText());
        
        // Test consolation prize
        $consolationPrize = new WheelPrize(
            4,
            'Consolation Prize',
            'Test Description',
            WheelPrize::TYPE_CONSOLATION,
            null,
            0.5,
            true,
            4,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertSame('Consolation Prize', $consolationPrize->getDisplayText());
        
        // Test none prize
        $nonePrize = new WheelPrize(
            5,
            'None Prize',
            'Test Description',
            WheelPrize::TYPE_NONE,
            null,
            0.5,
            true,
            5,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertSame('Пустой приз', $nonePrize->getDisplayText());
        
        // Test custom prize
        $customPrize = new WheelPrize(
            6,
            'Custom Prize',
            'Test Description',
            WheelPrize::TYPE_CUSTOM,
            null,
            0.5,
            true,
            6,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertSame('Custom Prize', $customPrize->getDisplayText());
    }
}