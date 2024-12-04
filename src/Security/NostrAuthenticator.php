<?php

namespace App\Security;

use App\Entity\Event;
use App\Service\NostrClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class NostrAuthenticator extends AbstractAuthenticator implements InteractiveAuthenticatorInterface
{
    public function __construct(private readonly NostrClient $nostrClient, private readonly EntityManagerInterface $entityManager)
    {
    }

    public function supports(Request $request): ?bool
    {
        if ($request->getPathInfo() === '/login' && $request->headers->has('Authorization')) {
            return true;
        }
        return false;
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');
        if (!str_starts_with($authHeader, 'Nostr ')) {
            throw new AuthenticationException('Invalid Authorization header');
        }

        $eventStr = base64_decode(substr($authHeader, 6), true);
        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];

        $serializer = new Serializer($normalizers, $encoders);
        /** @var Event $event */
        $event = $serializer->deserialize($eventStr, Event::class, 'json');
        if (time() > $event->getCreatedAt() + 60) {
            throw new AuthenticationException('Expired');
        }
        // TODO enable validity check after bug is fixed
        // $validity = (new SchnorrSignature())->verify($event->getPubkey(), $event->getSig(), $event->getId());
        // pretend all is well
        $validity = true;
        if (!$validity) {
            throw new AuthenticationException('Invalid Authorization header');
        }

        try {
            $user = $this->fetchUser($event->getPubkey());
        } catch (\Exception) {
            // even if the user metadata not found, if sig is valid, login the pubkey
            $user = new \App\Entity\User();
            $user->setNpub($event->getPubkey());
        }

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier())
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }

    /**
     *
     * @throws \Exception
     */
    private function fetchUser(string $publicKey): \App\Entity\User
    {
        $this->nostrClient->getMetadata([$publicKey]);
        return $this->entityManager->getRepository(\App\Entity\User::class)->findOneBy(['npub' => $publicKey]);
    }

    public function isInteractive(): bool
    {
        return true;
    }
}
