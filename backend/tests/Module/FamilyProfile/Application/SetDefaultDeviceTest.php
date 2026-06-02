<?php

declare(strict_types=1);

namespace App\Tests\Module\FamilyProfile\Application;

use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Module\FamilyProfile\Application\SetDefaultDevice;
use App\Module\FamilyProfile\Domain\FamilyProfile;
use App\Shared\Application\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class SetDefaultDeviceTest extends TestCase
{
    public function test_sets_device_id_and_name(): void
    {
        $profile = new FamilyProfile('Kids', null);
        $repo = $this->createMock(FamilyProfileRepositoryInterface::class);
        $repo->method('find')->willReturn($profile);
        $repo->expects($this->once())->method('save')->with($profile);

        $useCase = new SetDefaultDevice($repo);
        $result = $useCase->__invoke('profile-1', '  device-abc  ', '  Connect Box  ');

        $this->assertSame('device-abc', $result->getDefaultSpotifyDeviceId());
        $this->assertSame('Connect Box', $result->getDefaultDeviceName());
    }

    public function test_clears_default_when_device_id_null(): void
    {
        $profile = new FamilyProfile('Kids', null);
        $profile->setDefaultDevice('old-id', 'Old Speaker');
        $repo = $this->createMock(FamilyProfileRepositoryInterface::class);
        $repo->method('find')->willReturn($profile);

        $useCase = new SetDefaultDevice($repo);
        $result = $useCase->__invoke('profile-1', null, null);

        $this->assertNull($result->getDefaultSpotifyDeviceId());
        $this->assertNull($result->getDefaultDeviceName());
    }

    public function test_throws_when_profile_missing(): void
    {
        $repo = $this->createMock(FamilyProfileRepositoryInterface::class);
        $repo->method('find')->willReturn(null);

        $useCase = new SetDefaultDevice($repo);
        $this->expectException(NotFoundException::class);
        $useCase->__invoke('missing', 'device-abc', 'Connect Box');
    }
}
