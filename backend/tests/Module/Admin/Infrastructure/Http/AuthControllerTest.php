<?php

declare(strict_types=1);

namespace App\Tests\Module\Admin\Infrastructure\Http;

use App\Module\Admin\Domain\AdminUser;
use App\Module\Admin\Infrastructure\Http\AuthController;
use PHPUnit\Framework\TestCase;

final class AuthControllerTest extends TestCase
{
    private AuthController $controller;

    protected function setUp(): void
    {
        $this->controller = new AuthController();
    }

    public function testMeReturnsUserData(): void
    {
        $user = new AdminUser('admin', '$2y$13$fakehashedpassword');

        $response = $this->controller->me($user);

        $this->assertSame(200, $response->getStatusCode());

        /** @var array{username: string, roles: list<string>} $data */
        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame('admin', $data['username']);
        $this->assertContains('ROLE_ADMIN', $data['roles']);
    }

    public function testMeReturns401WhenUserNull(): void
    {
        $response = $this->controller->me(null);

        $this->assertSame(401, $response->getStatusCode());

        /** @var array{error: string} $data */
        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame('unauthenticated', $data['error']);
    }

    public function testLoginMethodThrowsLogicException(): void
    {
        $this->expectException(\LogicException::class);
        $this->controller->login();
    }

    public function testLogoutMethodThrowsLogicException(): void
    {
        $this->expectException(\LogicException::class);
        $this->controller->logout();
    }
}
