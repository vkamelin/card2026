<?php

declare(strict_types=1);

use App\Models\WheelCustomPrize;
use PHPUnit\Framework\TestCase;

final class WheelCustomPrizeTest extends TestCase
{
    public function testConstants(): void
    {
        $this->assertSame('link', WheelCustomPrize::ACTION_TYPE_LINK);
        $this->assertSame('message', WheelCustomPrize::ACTION_TYPE_MESSAGE);
        $this->assertSame('callback', WheelCustomPrize::ACTION_TYPE_CALLBACK);
        
        $this->assertContains(WheelCustomPrize::ACTION_TYPE_LINK, WheelCustomPrize::VALID_ACTION_TYPES);
        $this->assertContains(WheelCustomPrize::ACTION_TYPE_MESSAGE, WheelCustomPrize::VALID_ACTION_TYPES);
        $this->assertContains(WheelCustomPrize::ACTION_TYPE_CALLBACK, WheelCustomPrize::VALID_ACTION_TYPES);
    }
    
    public function testConstructor(): void
    {
        $customPrize = new WheelCustomPrize(
            1,
            5,
            'Test Custom Prize',
            'Test Description',
            'https://example.com/image.jpg',
            WheelCustomPrize::ACTION_TYPE_LINK,
            'https://example.com',
            true,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertSame(1, $customPrize->id);
        $this->assertSame(5, $customPrize->wheelPrizeId);
        $this->assertSame('Test Custom Prize', $customPrize->title);
        $this->assertSame('Test Description', $customPrize->description);
        $this->assertSame('https://example.com/image.jpg', $customPrize->imageUrl);
        $this->assertSame(WheelCustomPrize::ACTION_TYPE_LINK, $customPrize->actionType);
        $this->assertSame('https://example.com', $customPrize->actionData);
        $this->assertTrue($customPrize->isActive);
        $this->assertSame('2025-01-01 00:00:00', $customPrize->createdAt);
        $this->assertSame('2025-01-01 00:00:00', $customPrize->updatedAt);
    }
    
    public function testIsActive(): void
    {
        $activePrize = new WheelCustomPrize(
            1,
            5,
            'Test Custom Prize',
            'Test Description',
            'https://example.com/image.jpg',
            WheelCustomPrize::ACTION_TYPE_LINK,
            'https://example.com',
            true,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertTrue($activePrize->isActive());
        
        $inactivePrize = new WheelCustomPrize(
            2,
            6,
            'Test Custom Prize',
            'Test Description',
            'https://example.com/image.jpg',
            WheelCustomPrize::ACTION_TYPE_LINK,
            'https://example.com',
            false,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertFalse($inactivePrize->isActive());
    }
    
    public function testGetActionData(): void
    {
        $customPrize = new WheelCustomPrize(
            1,
            5,
            'Test Custom Prize',
            'Test Description',
            'https://example.com/image.jpg',
            WheelCustomPrize::ACTION_TYPE_LINK,
            'https://example.com',
            true,
            '2025-01-01 00:00:00',
            '2025-01-01 00:00:00'
        );
        
        $this->assertSame('https://example.com', $customPrize->getActionData());
    }
    
    public function testIsValidActionType(): void
    {
        $this->assertTrue(WheelCustomPrize::isValidActionType(WheelCustomPrize::ACTION_TYPE_LINK));
        $this->assertTrue(WheelCustomPrize::isValidActionType(WheelCustomPrize::ACTION_TYPE_MESSAGE));
        $this->assertTrue(WheelCustomPrize::isValidActionType(WheelCustomPrize::ACTION_TYPE_CALLBACK));
        $this->assertFalse(WheelCustomPrize::isValidActionType('invalid'));
    }
}