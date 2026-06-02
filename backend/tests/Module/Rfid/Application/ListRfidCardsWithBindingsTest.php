<?php

declare(strict_types=1);

namespace App\Tests\Module\Rfid\Application;

use App\Module\Rfid\Application\ListRfidCardsWithBindings;
use App\Module\Rfid\Application\Port\CardPlaylistBindingRepositoryInterface;
use App\Module\Rfid\Application\Port\RfidCardRepositoryInterface;
use App\Module\Rfid\Domain\CardPlaylistBinding;
use App\Module\Rfid\Domain\RfidCard;
use App\Module\Spotify\Application\Port\SpotifyPlaylistReferenceRepositoryInterface;
use App\Module\Spotify\Domain\SpotifyPlaylistReference;
use PHPUnit\Framework\TestCase;

class ListRfidCardsWithBindingsTest extends TestCase
{
    private RfidCardRepositoryInterface $cards;
    private CardPlaylistBindingRepositoryInterface $bindings;
    private SpotifyPlaylistReferenceRepositoryInterface $playlistRefs;

    protected function setUp(): void
    {
        $this->cards = $this->createMock(RfidCardRepositoryInterface::class);
        $this->bindings = $this->createMock(CardPlaylistBindingRepositoryInterface::class);
        $this->playlistRefs = $this->createMock(SpotifyPlaylistReferenceRepositoryInterface::class);
    }

    private function useCase(): ListRfidCardsWithBindings
    {
        return new ListRfidCardsWithBindings($this->cards, $this->bindings, $this->playlistRefs);
    }

    private static function cardWithId(string $profileId, string $uid, ?string $label, string $id): RfidCard
    {
        $card = new RfidCard($profileId, $uid, $label);
        $ref = new \ReflectionProperty(RfidCard::class, 'id');
        $ref->setValue($card, $id);
        return $card;
    }

    private static function refWithId(string $profileId, string $spotifyId, string $name, string $id): SpotifyPlaylistReference
    {
        $ref = new SpotifyPlaylistReference($profileId, $spotifyId, $name);
        $refProp = new \ReflectionProperty(SpotifyPlaylistReference::class, 'id');
        $refProp->setValue($ref, $id);
        return $ref;
    }

    public function test_empty_profile_returns_empty_list(): void
    {
        $this->cards->method('findByProfileId')->willReturn([]);
        $this->bindings->expects($this->never())->method('findByCardIds');
        $this->playlistRefs->expects($this->never())->method('findByIds');

        $result = $this->useCase()->__invoke('profile-1');

        $this->assertSame([], $result);
    }

    public function test_card_without_binding_has_null_binding(): void
    {
        $card = self::cardWithId('profile-1', 'AABBCCDD', 'Kinderzimmer', 'card-1');
        $this->cards->method('findByProfileId')->willReturn([$card]);
        $this->bindings->method('findByCardIds')->willReturn([]);
        $this->playlistRefs->expects($this->never())->method('findByIds');

        $result = $this->useCase()->__invoke('profile-1');

        $this->assertCount(1, $result);
        $this->assertSame($card, $result[0]['card']);
        $this->assertNull($result[0]['binding']);
    }

    public function test_card_with_binding_returns_playlist_reference(): void
    {
        $card = self::cardWithId('profile-1', 'AABBCCDD', null, 'card-1');
        $binding = new CardPlaylistBinding('card-1', 'ref-1');
        $playlistRef = self::refWithId('profile-1', 'spotify123', 'Gute-Nacht-Lieder', 'ref-1');

        $this->cards->method('findByProfileId')->willReturn([$card]);
        $this->bindings->method('findByCardIds')
            ->with(['card-1'])
            ->willReturn(['card-1' => $binding]);
        $this->playlistRefs->method('findByIds')
            ->with(['ref-1'])
            ->willReturn(['ref-1' => $playlistRef]);

        $result = $this->useCase()->__invoke('profile-1');

        $this->assertCount(1, $result);
        $this->assertSame($card, $result[0]['card']);
        $this->assertSame($playlistRef, $result[0]['binding']);
        $this->assertSame('Gute-Nacht-Lieder', $result[0]['binding']->getName());
    }

    public function test_mixed_cards_resolved_correctly(): void
    {
        $card1 = self::cardWithId('profile-1', 'AA', 'Eins', 'card-1');
        $card2 = self::cardWithId('profile-1', 'BB', 'Zwei', 'card-2');
        $binding = new CardPlaylistBinding('card-1', 'ref-1');
        $playlistRef = self::refWithId('profile-1', 'spotify123', 'Meine Playlist', 'ref-1');

        $this->cards->method('findByProfileId')->willReturn([$card1, $card2]);
        $this->bindings->method('findByCardIds')
            ->with(['card-1', 'card-2'])
            ->willReturn(['card-1' => $binding]);
        $this->playlistRefs->method('findByIds')
            ->with(['ref-1'])
            ->willReturn(['ref-1' => $playlistRef]);

        $result = $this->useCase()->__invoke('profile-1');

        $this->assertCount(2, $result);
        $this->assertSame($playlistRef, $result[0]['binding']);
        $this->assertNull($result[1]['binding']);
    }
}
