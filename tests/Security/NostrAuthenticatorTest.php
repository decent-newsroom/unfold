<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Kernel;
use swentel\nostr\Event\Event;
use swentel\nostr\Sign\Sign;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class NostrAuthenticatorTest extends WebTestCase
{
    /**
     * Tests various authentication scenarios for the Nostr authenticator.
     *
     * This test sends a GET request to the /login endpoint with different Authorization headers
     * and asserts that the response status code and content match the expected values provided
     * by the data provider.
     *
     * @dataProvider provideAuthenticationData
     */
    public function testAuthenticationScenarios(string $authorizationHeader, int $expectedStatusCode, string $expectedContent)
    {
        $client = static::createClient();

        $client->request('GET', '/login', [], [], [
            'HTTP_Authorization' => $authorizationHeader,
        ]);

        $response = $client->getResponse();
        $this->assertSame($expectedStatusCode, $response->getStatusCode());
        $this->assertStringContainsString($expectedContent, $response->getContent());
    }

    /**
     * @throws \JsonException
     */
    public function provideAuthenticationData(): array
    {
        // Boot the kernel manually
        $kernel = new Kernel('local', true);
        $kernel->boot();
        $container = $kernel->getContainer();

        $nsec = $container->getParameter('nsec');

        $note = new Event();
        $note->setContent('');
        $note->setKind(27235);
        $note->setTags([
            ["u", "https://localhost/login"],
            ["method", "POST"]
        ]);
        $signer = new Sign();
        $signer->signEvent($note, $nsec);
        $ser = $note->toJson();
        $validToken = 'Nostr ' . base64_encode($ser);

        $expiredToken = 'Nostr eyJjcmVhdGVkX2F0IjoxNzMzMzIxMzUyLCJraW5kIjoyNzIzNSwidGFncyI6W1sidSIsImh0dHBzOi8vbG9jYWxob3N0L2xvZ2luIl0sWyJtZXRob2QiLCJHRVQiXV0sImNvbnRlbnQiOiIiLCJwdWJrZXkiOiJkNDc1Y2U0YjM5Nzc1MDcxMzBmNDJjN2Y4NjM0NmVmOTM2ODAwZjNhZTc0ZDVlY2Y4MDg5MjgwY2RjMTkyM2U5IiwiaWQiOiJhYjA4NGM1NWQ5Y2UzMDliN2UxNzIyZGI2ODNjZTc2ZDg5NGNjN2QyYTIzZTRkNWUyMTUyYTM2Y2M2ODI1MTQ5Iiwic2lnIjoiOWI1Yjk2YjhkN2U2ZGM4YWU3ZmM4NjU2ZTE0NDVlZjkwYzc1YWQxNzZkYTRmNmNhMjI0NTRkNTJjNTk3ZTBmNjYwZjAwZjE3MmIxYjMzYzM4YTg2Y2U0YTBiMTdmMDgwMWEyNzJmZmVmYWU0NmY2OTgzZGZjYjRlM2YyZDgwZGYifQ==';

        $invalidToken = 'InvalidHeader';

        return [
            // Scenario: Valid token
            'valid_token' => [
                'authorizationHeader' => $validToken,
                'expectedStatusCode' => Response::HTTP_OK,
                'expectedContent' => 'Authentication Successful',
            ],
            // Scenario: Expired token
            'expired_token' => [
                'authorizationHeader' => $expiredToken,
                'expectedStatusCode' => Response::HTTP_UNAUTHORIZED,
                'expectedContent' => 'Unauthenticated',
            ],
            // Scenario: Invalid header
            'invalid_token' => [
                'authorizationHeader' => $invalidToken,
                'expectedStatusCode' => Response::HTTP_UNAUTHORIZED,
                'expectedContent' => 'Unauthenticated',
            ]
        ];
    }
}
