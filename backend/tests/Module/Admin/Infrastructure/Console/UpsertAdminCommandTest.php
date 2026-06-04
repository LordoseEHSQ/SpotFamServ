<?php

declare(strict_types=1);

namespace App\Tests\Module\Admin\Infrastructure\Console;

use App\Module\Admin\Application\Port\AdminUserRepositoryInterface;
use App\Module\Admin\Domain\AdminUser;
use App\Module\Admin\Infrastructure\Console\UpsertAdminCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UpsertAdminCommandTest extends TestCase
{
    private AdminUserRepositoryInterface $repo;
    private UserPasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        $this->repo   = $this->createMock(AdminUserRepositoryInterface::class);
        $this->hasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->hasher->method('hashPassword')->willReturn('$2y$13$hashedpassword');
    }

    public function testCreatesNewUser(): void
    {
        $this->repo->method('findByUsername')->willReturn(null);
        $this->repo->expects($this->once())->method('save')->with($this->isInstanceOf(AdminUser::class));

        $cmd    = new UpsertAdminCommand($this->repo, $this->hasher);
        $tester = new CommandTester($cmd);
        $tester->execute(['--username' => 'admin', '--password' => 'secret']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('angelegt', $tester->getDisplay());
    }

    public function testUpdatesExistingUser(): void
    {
        $existing = new AdminUser('admin', '$2y$13$oldhash');
        $this->repo->method('findByUsername')->willReturn($existing);
        $this->repo->expects($this->once())->method('save')->with($existing);

        $cmd    = new UpsertAdminCommand($this->repo, $this->hasher);
        $tester = new CommandTester($cmd);
        $tester->execute(['--username' => 'admin', '--password' => 'newsecret']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('aktualisiert', $tester->getDisplay());
    }

    public function testFailsWhenUsernameMissing(): void
    {
        $cmd    = new UpsertAdminCommand($this->repo, $this->hasher);
        $tester = new CommandTester($cmd);
        $tester->execute(['--password' => 'secret']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function testFailsWhenPasswordMissing(): void
    {
        $cmd    = new UpsertAdminCommand($this->repo, $this->hasher);
        $tester = new CommandTester($cmd);
        $tester->execute(['--username' => 'admin']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function testUsesEnvDefaultsWhenOptionsOmitted(): void
    {
        $this->repo->method('findByUsername')->willReturn(null);
        $this->repo->expects($this->once())->method('save');

        $cmd    = new UpsertAdminCommand($this->repo, $this->hasher, defaultUsername: 'envuser', defaultPassword: 'envpass');
        $tester = new CommandTester($cmd);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
    }
}
