<?php

declare(strict_types=1);

namespace App\Tests\Module\FamilyProfile\Application;

use App\Module\FamilyProfile\Application\ListFamilyProfiles;
use App\Module\FamilyProfile\Domain\FamilyProfile;
use App\Module\FamilyProfile\Infrastructure\Repository\DoctrineFamilyProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
class ListFamilyProfilesTest extends TestCase
{
    public function test_list_returns_empty_array_when_no_profiles(): void
    {
        $repo = $this->createMock(DoctrineFamilyProfileRepository::class);
        $repo->method('findAll')->willReturn([]);
        $useCase = new ListFamilyProfiles($repo);
        $result = $useCase->__invoke();
        $this->assertSame([], $result);
    }

    public function test_list_returns_profiles_from_repository(): void
    {
        $profile = new FamilyProfile('Test', null);
        $repo = $this->createMock(DoctrineFamilyProfileRepository::class);
        $repo->method('findAll')->willReturn([$profile]);
        $useCase = new ListFamilyProfiles($repo);
        $result = $useCase->__invoke();
        $this->assertCount(1, $result);
        $this->assertSame('Test', $result[0]->getName());
    }
}
