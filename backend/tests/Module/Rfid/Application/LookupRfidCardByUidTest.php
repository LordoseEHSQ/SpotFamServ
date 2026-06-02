<?php

declare(strict_types=1);

namespace App\Tests\Module\Rfid\Application;

use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Module\FamilyProfile\Domain\FamilyProfile;
use App\Module\Rfid\Application\LookupRfidCardByUid;
use App\Module\Rfid\Application\Port\CardPlaylistBindingRepositoryInterface;
use App\Module\Rfid\Application\Port\RfidCardRepositoryInterface;
use App\Module\Rfid\Domain\CardPlaylistBinding;
use App\Module\Rfid\Domain\RfidCard;
use App\Module\Spotify\Application\Port\SpotifyPlaylistReferenceRepositoryInterface;
use App\Module\Spotify\Domain\SpotifyPlaylistReference;
use PHPUnit\Framework\TestCase;

class LookupRfidCardByUidTest extends TestCase
{
    private RfidCardRepositoryInterface $cards;
    private FamilyProfileRepositoryInterface $profiles;
    private CardPlaylistBindingRepositoryInterface $bindings;
    private SpotifyPlaylistReferenceRepositoryInterface $playlistRefs;

    protected function setUp(): void
    {
        $this->cards = $this->createMock(RfidCardRepositoryInterface::class);
        $this->profiles = $this->createMock(FamilyProfileRepositoryInterface::class);
        $this->bindings = $this->createMock(CardPlaylistBindingRepositoryInterface::class);
        $this->playlistRefs = $this->createMock(SpotifyPlaylistReferenceRepositoryInterface::class);
    }

    private function lookup(): LookupRfidCardByUid
    {
        return new LookupRfidCardByUid($this->cards, $this->profiles, $this->bindings, $this->playlistRefs);
    }

    private static function cardWithId(string $profileId, string $uid, ?string $label, ?string $id): RfidCard
    {
        $card = new RfidCard($profileId, $uid, $label);
        if ($id !== null) {
            $ref = new \ReflectionProperty(RfidCard::class, 'id');
            $ref->setValue($card, $id);
        }
        return $card;
    }

    public function test_unknown_uid_is_free(): void
    {
        $this->cards->method('findByCardUid')->willReturn(null);

        $result = $this->lookup()->__invoke('E3D43735');

        $this->assertSame('free', $result->status);
        $this->assertSame('E3D43735', $result->cardUid);
        $this->assertNull($result->profileId);
        $this->assertFalse($result->hasBinding);
    }

    public function test_blank_uid_is_free_without_repo_hit(): void
    {
        $this->cards->expects($this->never())->method('findByCardUid');

        $result = $this->lookup()->__invoke('   ');

        $this->assertSame('free', $result->status);
        $this->assertSame('', $result->cardUid);
    }

    public function test_assigned_card_without_binding(): void
    {
        $this->cards->method('findByCardUid')->willReturn(
            self::cardWithId('profile-1', 'E3D43735', 'Kinderzimmer', 'card-1'),
        );
        $this->profiles->method('find')->willReturn(new FamilyProfile('Lena'));
        $this->bindings->method('findByCardId')->willReturn(null);

        $result = $this->lookup()->__invoke('E3D43735');

        $this->assertSame('assigned', $result->status);
        $this->assertSame('profile-1', $result->profileId);
        $this->assertSame('Lena', $result->profileName);
        $this->assertSame('Kinderzimmer', $result->label);
        $this->assertFalse($result->hasBinding);
        $this->assertNull($result->bindingName);
    }

    public function test_assigned_card_with_binding(): void
    {
        $this->cards->method('findByCardUid')->willReturn(
            self::cardWithId('profile-1', 'E3D43735', null, 'card-1'),
        );
        $this->profiles->method('find')->willReturn(new FamilyProfile('Lena'));
        $this->bindings->method('findByCardId')->willReturn(new CardPlaylistBinding('card-1', 'ref-1'));
        $this->playlistRefs->method('findByIdAndProfile')->willReturn(
            new SpotifyPlaylistReference('profile-1', 'spotify:playlist:abc', 'Gute-Nacht-Lieder'),
        );

        $result = $this->lookup()->__invoke('E3D43735');

        $this->assertSame('assigned', $result->status);
        $this->assertTrue($result->hasBinding);
        $this->assertSame('Gute-Nacht-Lieder', $result->bindingName);
    }

    public function test_assigned_card_with_orphaned_profile(): void
    {
        $this->cards->method('findByCardUid')->willReturn(
            self::cardWithId('profile-gone', 'E3D43735', null, 'card-1'),
        );
        $this->profiles->method('find')->willReturn(null);
        $this->bindings->method('findByCardId')->willReturn(null);

        $result = $this->lookup()->__invoke('E3D43735');

        $this->assertSame('assigned', $result->status);
        $this->assertSame('profile-gone', $result->profileId);
        $this->assertNull($result->profileName);
    }
}
